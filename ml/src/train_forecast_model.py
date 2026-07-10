import copy
import json
import pathlib

import numpy as np
import pandas as pd
import torch
import torch.nn as nn
import torch.optim as optim
from sklearn.metrics import mean_absolute_error, r2_score
from sklearn.preprocessing import StandardScaler
from torch.export import Dim

CURRENT_DIR = pathlib.Path(__file__).parent.resolve()

CSV_DATA_PATH = CURRENT_DIR / "../../data/weather/ml/ml.csv"
ONNX_MODEL_PATH = CURRENT_DIR / "../../data/weather/ml/model_pytorch.onnx"
SCALER_PARAMS_PATH = CURRENT_DIR / "../../data/weather/ml/scaler_params.json"
# Layer weights/biases as JSON, for the dependency-free PHP inference service (App\ML).
WEIGHTS_PATH = CURRENT_DIR / "../../data/weather/ml/model_weights.json"

# Kept stable: the ONNX input/output contract the PHP-ORT inference relies on (9 forecast/time
# features in, 3 targets out). Only forecast-derived features are used — they are the only ones
# available at inference time.
FEATURE_COLS = [
    'forecast_pressure', 'forecast_wind_sin', 'forecast_wind_cos',
    'forecast_humidity', 'forecast_temperature',
    'day_sin', 'day_cos', 'hour_sin', 'hour_cos'
]
TARGET_COLS = ['temperature', 'wind_sin', 'wind_cos']

# Chronological hold-out fractions (no shuffling: adjacent hours are correlated, so a random split
# leaks near-duplicate rows across train/test and inflates the score).
TEST_FRACTION = 0.2
VAL_FRACTION = 0.15

EPOCHS = 500
BATCH_SIZE = 64
EARLY_STOPPING_PATIENCE = 30


def cartesian_to_polar_wind(sin_col, cos_col):
    """Convert the (sin*speed, cos*speed) projection back to speed (kts) and bearing (degrees)."""
    speed = np.sqrt(sin_col ** 2 + cos_col ** 2)
    angle_deg = (np.degrees(np.arctan2(sin_col, cos_col)) + 360) % 360
    return speed, angle_deg


def circular_abs_error(pred_deg, real_deg):
    """Absolute angular error in degrees, wrapped to [0, 180]."""
    diff = np.abs(pred_deg - real_deg) % 360
    return np.minimum(diff, 360 - diff)


def report_per_target(name, y_true, y_pred):
    """Print R²/MAE per physical target instead of one blended score across mixed units."""
    temp_r2 = r2_score(y_true[:, 0], y_pred[:, 0])
    temp_mae = mean_absolute_error(y_true[:, 0], y_pred[:, 0])

    real_speed, real_dir = cartesian_to_polar_wind(y_true[:, 1], y_true[:, 2])
    pred_speed, pred_dir = cartesian_to_polar_wind(y_pred[:, 1], y_pred[:, 2])
    speed_r2 = r2_score(real_speed, pred_speed)
    speed_mae = mean_absolute_error(real_speed, pred_speed)
    dir_mae = circular_abs_error(pred_dir, real_dir).mean()

    print(f"\n--- {name} ---")
    print(f"Température   : R²={temp_r2:.4f}  MAE={temp_mae:.2f} °C")
    print(f"Vent (vitesse): R²={speed_r2:.4f}  MAE={speed_mae:.2f} kts")
    print(f"Vent (direct.): MAE={dir_mae:.1f}° (erreur angulaire circulaire)")


# Load and order chronologically so the split is a real forecast (past → future).
df = pd.read_csv(CSV_DATA_PATH).dropna().sort_values('recorded_hour').reset_index(drop=True)

X = df[FEATURE_COLS].values.astype(np.float32)
y = df[TARGET_COLS].values.astype(np.float32)

n = len(df)
n_test = int(n * TEST_FRACTION)
n_val = int((n - n_test) * VAL_FRACTION)
n_train = n - n_test - n_val

X_train_raw, y_train_raw = X[:n_train], y[:n_train]
X_val_raw, y_val_raw = X[n_train:n_train + n_val], y[n_train:n_train + n_val]
X_test_raw, y_test_raw = X[n_train + n_val:], y[n_train + n_val:]
print(f"Samples: {n} (train={n_train}, val={n_val}, test={n_test})")

# Scalers are fit on the TRAINING slice only, then reused everywhere (no test/val leakage).
scaler_X = StandardScaler().fit(X_train_raw)
scaler_y = StandardScaler().fit(y_train_raw)

X_train_th = torch.tensor(scaler_X.transform(X_train_raw))
y_train_th = torch.tensor(scaler_y.transform(y_train_raw))
X_val_th = torch.tensor(scaler_X.transform(X_val_raw))
y_val_th = torch.tensor(scaler_y.transform(y_val_raw))
X_test_th = torch.tensor(scaler_X.transform(X_test_raw))

torch.manual_seed(42)


class CustomWeatherForecastNet(nn.Module):
    def __init__(self):
        super(CustomWeatherForecastNet, self).__init__()

        # 4 Layers of Affines neurons (y=xA^T+b)
        self.layer1 = nn.Linear(9, 128)
        self.layer2 = nn.Linear(128, 128)
        self.layer3 = nn.Linear(128, 64)
        self.output = nn.Linear(64, 3)

        # ReLU returns max(0,x) (only positive values)
        self.relu = nn.ReLU()
        self.dropout = nn.Dropout(0.2)

    def forward(self, x):
        x = self.relu(self.layer1(x))
        x = self.dropout(x)

        x = self.relu(self.layer2(x))
        x = self.dropout(x)

        x = self.relu(self.layer3(x))
        x = self.output(x)

        return x


model = CustomWeatherForecastNet()
criterion = nn.MSELoss()  # Measure Mean Squared Error
optimizer = optim.Adam(model.parameters(), lr=0.001)  # Adam Stochastic Optimization. Didn't understand, it's from docs.
scheduler = optim.lr_scheduler.ReduceLROnPlateau(optimizer, factor=0.5, patience=10)


print("Start Training...")
n_samples = X_train_th.shape[0]
best_val_loss = float('inf')
best_state = copy.deepcopy(model.state_dict())
epochs_without_improvement = 0

for epoch in range(EPOCHS):
    model.train()
    permutation = torch.randperm(n_samples)

    for i in range(0, n_samples, BATCH_SIZE):
        indices = permutation[i:i + BATCH_SIZE]
        batch_x, batch_y = X_train_th[indices], y_train_th[indices]

        optimizer.zero_grad()
        outputs = model(batch_x)
        loss = criterion(outputs, batch_y)
        loss.backward()
        optimizer.step()

    # Validation loss drives both the LR schedule and early stopping.
    model.eval()
    with torch.no_grad():
        val_loss = criterion(model(X_val_th), y_val_th).item()
    scheduler.step(val_loss)

    if val_loss < best_val_loss - 1e-4:
        best_val_loss = val_loss
        best_state = copy.deepcopy(model.state_dict())
        epochs_without_improvement = 0
    else:
        epochs_without_improvement += 1

    if (epoch + 1) % 50 == 0:
        print(f"Epoch [{epoch + 1}/{EPOCHS}], Train Loss: {loss.item():.4f}, Val Loss: {val_loss:.4f}")

    if epochs_without_improvement >= EARLY_STOPPING_PATIENCE:
        print(f"Early stopping at epoch {epoch + 1} (best val loss {best_val_loss:.4f}).")
        break

# Restore the best-validation weights before evaluation / export.
model.load_state_dict(best_state)

print("Training finished.")
print("Evaluating on the held-out test slice...")
model.eval()
with torch.no_grad():
    y_pred_scaled = model(X_test_th).numpy()

# Unscale to get real physical values (kts and °C).
y_pred_final = scaler_y.inverse_transform(y_pred_scaled)
report_per_target("Test (hold-out)", y_test_raw, y_pred_final)

# Export the model to ONNX format (portable and usable in PHP-ORT).
print("\nONNX Export...")

batch_dim = Dim("batch_size", min=1)
dummy_input = torch.randn(1, 9)
dynamic_shapes = {
    "x": {0: batch_dim}
}

torch.onnx.export(
    model,
    dummy_input,
    ONNX_MODEL_PATH,
    export_params=True,
    opset_version=18,
    do_constant_folding=True,
    input_names=['input'],
    output_names=['output'],
    dynamic_shapes=dynamic_shapes
)

print("ONNX saved : " + str(ONNX_MODEL_PATH))

# Dense weights for the pure-PHP inference service. nn.Linear stores weight as [out, in], bias as
# [out] and computes y = x·Wᵀ + b, which App\ML\Mlp replicates. ReLU on every layer but the output.
ordered_layers = [model.layer1, model.layer2, model.layer3, model.output]
weights = {
    "layers": [
        {
            "weight": layer.weight.detach().numpy().tolist(),
            "bias": layer.bias.detach().numpy().tolist(),
            "relu": index < len(ordered_layers) - 1,
        }
        for index, layer in enumerate(ordered_layers)
    ]
}
with open(WEIGHTS_PATH, "w") as f:
    json.dump(weights, f)
print("Weights saved : " + str(WEIGHTS_PATH))

# Scaler params to give to PHP-ORT later. We'll export it to JSON.
print(f"Mean (Input) : {scaler_X.mean_}")
print(f"Scale (Input)   : {scaler_X.scale_}")
print(f"Mean (Target): {scaler_y.mean_}")
print(f"Scale (Target)  : {scaler_y.scale_}")
print("Scaler params saved : " + str(SCALER_PARAMS_PATH))

scaler_params = {
    "input": {
        "mean": scaler_X.mean_.tolist(),
        "scale": scaler_X.scale_.tolist(),
    },
    "target": {
        "mean": scaler_y.mean_.tolist(),
        "scale": scaler_y.scale_.tolist()
    },
    "cols": {
        "input": FEATURE_COLS,
        "target": TARGET_COLS
    }
}

with open(SCALER_PARAMS_PATH, "w") as f:
    json.dump(scaler_params, f)

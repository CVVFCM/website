import json
import pathlib

import numpy as np
import pandas as pd
import torch
import torch.nn as nn
import torch.optim as optim
from sklearn.metrics import r2_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from torch.export import Dim

CURRENT_DIR = pathlib.Path(__file__).parent.resolve()

CSV_DATA_PATH = CURRENT_DIR / "../../data/weather/ml/ml.csv"
ONNX_MODEL_PATH = CURRENT_DIR / "../../data/weather/ml/model_pytorch.onnx"
SCALER_PARAMS_PATH = CURRENT_DIR / "../../data/weather/ml/scaler_params.json"

FEATURE_COLS = [
    'forecast_pressure', 'forecast_wind_sin', 'forecast_wind_cos',
    'forecast_humidity', 'forecast_temperature',
    'day_sin', 'day_cos', 'hour_sin', 'hour_cos'
]
TARGET_COLS = ['temperature', 'wind_sin', 'wind_cos']

df = pd.read_csv(CSV_DATA_PATH).dropna()

X = df[FEATURE_COLS].values.astype(np.float32)
y = df[TARGET_COLS].values.astype(np.float32)

# Scaler to export for later use in PHP-ORT
scaler_X = StandardScaler()
scaler_y = StandardScaler()

X_scaled = scaler_X.fit_transform(X)
y_scaled = scaler_y.fit_transform(y)

# Extract 20% of data for testing (won't be used in training), and convert to PyTorch Tensors
X_train, X_test, y_train, y_test = train_test_split(X_scaled, y_scaled, test_size=0.2, random_state=42, shuffle=True)
X_train_th = torch.tensor(X_train)
y_train_th = torch.tensor(y_train)
X_test_th  = torch.tensor(X_test)
y_test_th  = torch.tensor(y_test)

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
criterion = nn.MSELoss() # Measure Mean Squared Error
optimizer = optim.Adam(model.parameters(), lr=0.001) # Adam Stochastic Optimization. Didn't understand, it's from docs.


print("Start Training...")
epochs = 500
batch_size = 64
n_samples = X_train_th.shape[0]

for epoch in range(epochs):
    model.train()
    permutation = torch.randperm(n_samples)

    for i in range(0, n_samples, batch_size):
        indices = permutation[i:i+batch_size]
        batch_x, batch_y = X_train_th[indices], y_train_th[indices]

        optimizer.zero_grad()
        outputs = model(batch_x)
        loss = criterion(outputs, batch_y)
        loss.backward()
        optimizer.step()

    if (epoch+1) % 50 == 0:
        print(f"Epoch [{epoch+1}/{epochs}], Loss: {loss.item():.4f}")


print("Training finished.")
print("Evaluating on test data...")
model.eval()
with torch.no_grad():
    y_pred_scaled = model(X_test_th).numpy()

# Unscale to get Real physical values (kts and °C)
y_pred_final = scaler_y.inverse_transform(y_pred_scaled)
y_test_final = scaler_y.inverse_transform(y_test)

r2 = r2_score(y_test_final, y_pred_final)
print(f"R² : {r2:.4f}")

# Export the model to ONNX format (portable and usable in PHP-ORT)
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
    input_names = ['input'],
    output_names = ['output'],
    dynamic_shapes=dynamic_shapes
)

print("ONNX saved : " + str(ONNX_MODEL_PATH))

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

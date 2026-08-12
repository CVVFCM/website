import json
import pathlib

import numpy as np
import pandas as pd
import torch
import torch.nn as nn
from sklearn.linear_model import Ridge
from sklearn.metrics import mean_absolute_error, r2_score
from sklearn.model_selection import TimeSeriesSplit
from sklearn.preprocessing import StandardScaler
from torch.export import Dim

CURRENT_DIR = pathlib.Path(__file__).parent.resolve()

CSV_DATA_PATH = CURRENT_DIR / "../../data/weather/ml/ml.csv"
ONNX_MODEL_PATH = CURRENT_DIR / "../../data/weather/ml/model_pytorch.onnx"
SCALER_PARAMS_PATH = CURRENT_DIR / "../../data/weather/ml/scaler_params.json"
# Layer weights/biases as JSON, for the dependency-free PHP inference service (App\ML).
WEIGHTS_PATH = CURRENT_DIR / "../../data/weather/ml/model_weights.json"

# Model input: 9 forecast/time features (the only ones available at inference time).
FEATURE_COLS = [
    'forecast_pressure', 'forecast_wind_sin', 'forecast_wind_cos',
    'forecast_humidity', 'forecast_temperature',
    'day_sin', 'day_cos', 'hour_sin', 'hour_cos'
]
# The model predicts the RESIDUAL wind vector (observed - forecast), not the absolute wind. A
# ridge (L2) linear model is used, not a deep net: with ~hundreds of rows a big MLP overfits and
# scores *below* the forecast, while ridge on the residual measurably beats it (see CV report).
# Temperature is dropped (unused, and it stole capacity from wind).
OBSERVED_WIND_COLS = ['wind_sin', 'wind_cos']
FORECAST_WIND_COLS = ['forecast_wind_sin', 'forecast_wind_cos']
TARGET_COLS = ['wind_sin_residual', 'wind_cos_residual']

RIDGE_ALPHA = 100.0
CV_SPLITS = 5
# Hours whose observed wind is below this are excluded from the FIT — never from the evaluation.
# Two out of five hours at this station are a dead calm, where the residual is mostly noise and the
# bearing means nothing. Letting them into the fit dragged the model down exactly where it matters:
# above 6 knots it scored a 2.55 kn speed MAE against the forecast's 2.12, i.e. worse than doing
# nothing. Fitting on the windy hours only turns that into 1.93, and the model then beats the
# forecast in every band instead of just the light ones.
MIN_FIT_WIND_KN = 1.0


def cartesian_to_polar_wind(sin_col, cos_col):
    """Convert the (sin*speed, cos*speed) projection back to speed (kts) and bearing (degrees)."""
    speed = np.sqrt(sin_col ** 2 + cos_col ** 2)
    angle_deg = (np.degrees(np.arctan2(sin_col, cos_col)) + 360) % 360
    return speed, angle_deg


def circular_abs_error(pred_deg, real_deg):
    """Absolute angular error in degrees, wrapped to [0, 180]."""
    diff = np.abs(pred_deg - real_deg) % 360
    return np.minimum(diff, 360 - diff)


def wind_scores(observed_wind, predicted_wind, bearing_wind):
    """Score a prediction whose speed and bearing may come from different vectors.

    App\\ML\\WeatherModelInference ships the corrected magnitude but the forecast's bearing, so the
    model is scored the same way here: `predicted_wind` gives the speed, `bearing_wind` the angle.
    """
    real_speed, real_dir = cartesian_to_polar_wind(observed_wind[:, 0], observed_wind[:, 1])
    pred_speed, _ = cartesian_to_polar_wind(predicted_wind[:, 0], predicted_wind[:, 1])
    _, pred_dir = cartesian_to_polar_wind(bearing_wind[:, 0], bearing_wind[:, 1])
    return (
        mean_absolute_error(real_speed, pred_speed),
        r2_score(real_speed, pred_speed),
        circular_abs_error(pred_dir, real_dir).mean(),
    )


# Load and order chronologically so the split is a real forecast (past → future). Only the columns
# actually consumed may disqualify a row: the export also carries observed pressure/humidity and the
# 3-hour pressure trend, none of which the model reads, and the trend is empty for the three hours
# following every gap in the observations. A bare dropna() threw those rows away for nothing.
df = (
    pd.read_csv(CSV_DATA_PATH)
    .dropna(subset=FEATURE_COLS + OBSERVED_WIND_COLS)
    .sort_values('recorded_hour')
    .reset_index(drop=True)
)
X = df[FEATURE_COLS].values.astype(np.float64)
observed_wind = df[OBSERVED_WIND_COLS].values.astype(np.float64)
forecast_wind = df[FORECAST_WIND_COLS].values.astype(np.float64)
residual = observed_wind - forecast_wind  # what the model learns
observed_speed = np.sqrt(observed_wind[:, 0] ** 2 + observed_wind[:, 1] ** 2)
windy = observed_speed >= MIN_FIT_WIND_KN

# Honest evaluation: expanding-window CV (no leakage, no single-slice luck). Scaler + ridge are refit
# inside each fold, on the windy hours of that fold only; the test folds keep every hour, so the
# scores below cover the calms the model was not fitted on.
print(f"Samples: {len(df)} — {windy.sum()} above {MIN_FIT_WIND_KN:g} kn used to fit "
      f"— {CV_SPLITS}-fold TimeSeriesSplit CV\n")
pooled_obs, pooled_model, pooled_forecast = [], [], []
for train_idx, test_idx in TimeSeriesSplit(n_splits=CV_SPLITS).split(X):
    fit_idx = train_idx[windy[train_idx]]
    fold_scaler = StandardScaler().fit(X[fit_idx])
    fold_ridge = Ridge(alpha=RIDGE_ALPHA).fit(fold_scaler.transform(X[fit_idx]), residual[fit_idx])
    fold_pred_residual = fold_ridge.predict(fold_scaler.transform(X[test_idx]))

    pooled_obs.append(observed_wind[test_idx])
    pooled_model.append(forecast_wind[test_idx] + fold_pred_residual)
    pooled_forecast.append(forecast_wind[test_idx])

pooled_obs = np.vstack(pooled_obs)
pooled_model = np.vstack(pooled_model)
pooled_forecast = np.vstack(pooled_forecast)
# The bearing comes from the forecast in both rows — that is what the PHP service serves — so the
# dir MAE column is identical by construction, and the model can only win or draw.
model_mae, model_r2, model_dir = wind_scores(pooled_obs, pooled_model, pooled_forecast)
fc_mae, fc_r2, fc_dir = wind_scores(pooled_obs, pooled_forecast, pooled_forecast)
print(f"{'':10}{'speed MAE':>10}{'speed R²':>10}{'dir MAE':>9}")
print(f"{'Modèle':10}{model_mae:10.2f}{model_r2:10.3f}{model_dir:9.1f}")
print(f"{'Prévision':10}{fc_mae:10.2f}{fc_r2:10.3f}{fc_dir:9.1f}")

# Final model for export: fit scaler + ridge on every windy hour available.
scaler_X = StandardScaler().fit(X[windy])
ridge = Ridge(alpha=RIDGE_ALPHA).fit(scaler_X.transform(X[windy]), residual[windy])

# Ridge is a single affine layer on the scaled input. Transfer it into an nn.Linear so we can reuse
# the existing ONNX export, and so App\ML\Mlp (generic dense layers) runs it unchanged.
torch.manual_seed(42)
linear = nn.Linear(len(FEATURE_COLS), len(TARGET_COLS))
with torch.no_grad():
    linear.weight.copy_(torch.tensor(ridge.coef_, dtype=torch.float32))
    linear.bias.copy_(torch.tensor(ridge.intercept_, dtype=torch.float32))
linear.eval()

print("\nONNX Export...")
batch_dim = Dim("batch_size", min=1)
dummy_input = torch.randn(1, len(FEATURE_COLS))
torch.onnx.export(
    linear,
    dummy_input,
    ONNX_MODEL_PATH,
    export_params=True,
    opset_version=18,
    do_constant_folding=True,
    input_names=['input'],
    output_names=['output'],
    dynamic_shapes=({0: batch_dim},),
)
print("ONNX saved : " + str(ONNX_MODEL_PATH))

# Single dense layer (no activation) for the pure-PHP inference service.
weights = {
    "layers": [
        {
            "weight": linear.weight.detach().numpy().tolist(),
            "bias": linear.bias.detach().numpy().tolist(),
            "relu": False,
        }
    ]
}
with open(WEIGHTS_PATH, "w") as f:
    json.dump(weights, f)
print("Weights saved : " + str(WEIGHTS_PATH))

# Input scaler feeds the linear layer; the target is the raw residual (no scaling), so the target
# transform is the identity — PHP applies mean/scale verbatim.
scaler_params = {
    "input": {
        "mean": scaler_X.mean_.tolist(),
        "scale": scaler_X.scale_.tolist(),
    },
    "target": {
        "mean": [0.0] * len(TARGET_COLS),
        "scale": [1.0] * len(TARGET_COLS),
    },
    "cols": {
        "input": FEATURE_COLS,
        "target": TARGET_COLS,
    },
}
with open(SCALER_PARAMS_PATH, "w") as f:
    json.dump(scaler_params, f)
print("Scaler params saved : " + str(SCALER_PARAMS_PATH))

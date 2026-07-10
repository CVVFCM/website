import json
import pathlib
import shutil

import matplotlib.pyplot as plt
import numpy as np
import onnxruntime as rt
import pandas as pd
import seaborn as sns
from sklearn.metrics import r2_score

CURRENT_DIR = pathlib.Path(__file__).parent.resolve()
ONNX_MODEL_PATH = CURRENT_DIR / ".." / ".." / "data" / "weather" / "ml" / "model_pytorch.onnx"
SCALER_PARAMS_PATH = CURRENT_DIR /  ".." / ".." / "data" / "weather" / "ml" / "scaler_params.json"
WEIGHTS_PATH = CURRENT_DIR / ".." / ".." / "data" / "weather" / "ml" / "model_weights.json"
CSV_DATA_PATH = CURRENT_DIR / ".." / ".." / "data" / "weather" / "ml" / "ml.csv"
# Committed parity bundle consumed by the PHP inference tests (tests/ML/Fixtures).
FIXTURES_DIR = (CURRENT_DIR / ".." / ".." / "tests" / "ML" / "Fixtures").resolve()

# Must match train_forecast_model.py so evaluation runs on the same unseen tail.
TEST_FRACTION = 0.2

sns.set_theme(style="whitegrid")

# Loads training and scaler params
with open(SCALER_PARAMS_PATH, 'r') as f:
    scaler_params = json.load(f)
    INPUT_MEAN = np.array(scaler_params['input']['mean'], dtype=np.float32)
    INPUT_SCALE = np.array(scaler_params['input']['scale'], dtype=np.float32)
    TARGET_MEAN = np.array(scaler_params['target']['mean'], dtype=np.float32)
    TARGET_SCALE = np.array(scaler_params['target']['scale'], dtype=np.float32)
    FEATURE_COLS = scaler_params['cols']['input']
    TARGET_COLS = scaler_params['cols']['target']

def cartesian_to_polar_wind(sin_col, cos_col):
    """Convert Orthogonal speed projection (X,Y) to Speed and Direction (Knots/Degrees)."""
    speed = np.sqrt(sin_col**2 + cos_col**2)
    angle_rad = np.arctan2(sin_col, cos_col)
    angle_deg = (np.degrees(angle_rad) + 360) % 360

    return speed, angle_deg

def circular_abs_error(pred_deg, real_deg):
    """Absolute angular error in degrees, wrapped to [0, 180]."""
    diff = np.abs(pred_deg - real_deg) % 360
    return np.minimum(diff, 360 - diff)

def load_and_prepare_data(csv_path):
    print("Loads CSV data...")
    # Same chronological order + hold-out tail as training, so we score only unseen rows.
    df = pd.read_csv(csv_path).dropna().sort_values('recorded_hour').reset_index(drop=True)
    n_test = int(len(df) * TEST_FRACTION)
    df = df.iloc[len(df) - n_test:]

    X_raw = df[FEATURE_COLS].values.astype(np.float32)
    # Observed wind (ground truth) and raw forecast wind (baseline to beat). The model outputs a
    # residual, so we reconstruct absolute wind as forecast + residual downstream.
    observed_wind = df[['wind_sin', 'wind_cos']].values.astype(np.float32)
    forecast_wind = df[['forecast_wind_sin', 'forecast_wind_cos']].values.astype(np.float32)

    # (Raw - Mean) / Scale : manual normalization. Same will be done in PHP-ORT
    X_norm = (X_raw - INPUT_MEAN) / INPUT_SCALE

    print(f"{len(df)} hold-out lines loaded and normalized.")

    return X_norm, observed_wind, forecast_wind

def export_parity_fixture(csv_path):
    """Write the PHP parity bundle: known hold-out rows + random inputs, with ONNX expectations,
    plus copies of the weights/scaler the PHP service consumes. Regenerated whenever the model is."""
    FIXTURES_DIR.mkdir(parents=True, exist_ok=True)

    df = pd.read_csv(csv_path).dropna().sort_values('recorded_hour').reset_index(drop=True)
    n_test = int(len(df) * TEST_FRACTION)
    known = df[FEATURE_COLS].iloc[len(df) - n_test:].head(10).values.astype(np.float32)

    # Random raw feature vectors within mean ± 2·scale of each input.
    rng = np.random.default_rng(42)
    random_cases = (INPUT_MEAN + rng.uniform(-2, 2, size=(10, len(FEATURE_COLS))).astype(np.float32) * INPUT_SCALE)

    features = np.vstack([known, random_cases]).astype(np.float32)
    residual = run_onnx_inference((features - INPUT_MEAN) / INPUT_SCALE)
    # Reconstruct absolute wind: forecast (features[1]=sin, features[2]=cos) + predicted residual.
    predicted_wind = features[:, 1:3] + residual
    speed, direction = cartesian_to_polar_wind(predicted_wind[:, 0], predicted_wind[:, 1])

    cases = [
        {
            "features": features[i].tolist(),
            "expected": {
                "windSpeed": float(speed[i]),
                "windDirection": int(round(float(direction[i]))) % 360,
            },
        }
        for i in range(len(features))
    ]

    with open(FIXTURES_DIR / "inference_cases.json", "w") as f:
        json.dump({"cases": cases}, f, indent=1)
    shutil.copy(SCALER_PARAMS_PATH, FIXTURES_DIR / "scaler_params.json")
    shutil.copy(WEIGHTS_PATH, FIXTURES_DIR / "model_weights.json")
    print(f"Parity fixture written: {FIXTURES_DIR}")


def run_onnx_inference(X_norm):
    print(f"Loads ONNX model : {ONNX_MODEL_PATH}...")
    sess = rt.InferenceSession(ONNX_MODEL_PATH, providers=["CPUExecutionProvider"])
    input_name = sess.get_inputs()[0].name

    print("Runs inference...")
    preds_norm = sess.run(None, {input_name: X_norm.astype(np.float32)})[0]

    # De-scaled residual wind vector (observed - forecast).
    residual = (preds_norm * TARGET_SCALE) + TARGET_MEAN

    return residual

if __name__ == "__main__":
    X_norm, observed_wind, forecast_wind = load_and_prepare_data(CSV_DATA_PATH)

    residual = run_onnx_inference(X_norm)
    predicted_wind = forecast_wind + residual  # reconstruct absolute wind from the residual

    real_speed, real_dir = cartesian_to_polar_wind(observed_wind[:, 0], observed_wind[:, 1])
    pred_speed, pred_dir = cartesian_to_polar_wind(predicted_wind[:, 0], predicted_wind[:, 1])

    # NOTE: honest generalization metrics live in train_forecast_model.py (TimeSeriesSplit CV). The
    # exported model is fit on ALL data, so scoring it here is in-sample — these plots are only a
    # visual sanity check of the fit, not a performance claim.
    export_parity_fixture(CSV_DATA_PATH)

    r2_speed = r2_score(real_speed, pred_speed)

    plt.figure(figsize=(8, 8))
    plt.scatter(real_speed, pred_speed, alpha=0.2, s=10, color='purple')

    # Diagonal line => Perfect prediction
    max_val = max(real_speed.max(), pred_speed.max())
    plt.plot([0, max_val], [0, max_val], 'k--', label='Prédiction Parfaite')

    plt.title(f"Vitesse Vent : Réalité vs PyTorch (R²={r2_speed:.2f})")
    plt.xlabel("Vitesse Réelle (noeuds)")
    plt.ylabel("Vitesse Prédite par PyTorch (noeuds)")
    plt.legend()
    plt.xlim(0, max_val)
    plt.ylim(0, max_val)
    plt.tight_layout()
    plt.show()

    subset = 200
    plt.figure(figsize=(14, 6))
    plt.plot(real_speed[:subset], label='Réalité (Target)', color='black', linewidth=1.5, alpha=0.7)
    plt.plot(pred_speed[:subset], label='Prédiction PyTorch', color='#007acc', linewidth=2)

    plt.title(f"Dynamique Temporelle (Zoom sur {subset} points)")
    plt.ylabel("Vitesse (noeuds)")
    plt.xlabel("Temps (index)")
    plt.legend()
    plt.tight_layout()
    plt.show()

    speed_error = pred_speed - real_speed

    plt.figure(figsize=(10, 10))
    ax = plt.subplot(111, polar=True)
    ax.set_theta_zero_location("N")
    ax.set_theta_direction(-1) # Clockwise

    sc = ax.scatter(np.radians(real_dir), speed_error,
                    c=speed_error, cmap='coolwarm',
                    alpha=0.6, s=20, vmin=-5, vmax=5)

    plt.title("Où le modèle est-il faible?\n(Erreur de vitesse en fonction de la direction du vent)", va='bottom')
    cbar = plt.colorbar(sc, pad=0.1)
    cbar.set_label("Erreur (noeuds) : Rouge=Surestimation, Bleu=Sous-estimation")
    plt.tight_layout()
    plt.show()

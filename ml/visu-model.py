import pandas as pd
import numpy as np
import onnxruntime as rt
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.metrics import r2_score

# Activation du style Seaborn pour des graphiques plus jolis
sns.set_theme(style="whitegrid")

# ==========================================
# 1. CONFIG UTILISATEUR (A REMPLIR !)
# ==========================================
ONNX_MODEL_PATH = "./ml/model_pytorch.onnx"
CSV_DATA_PATH = "./data/ml.csv"

# --- TRES IMPORTANT : COPIE-COLLE TES VALEURS ICI ---
# Ces valeurs sortent de la fin de ton script d'entraînement PyTorch.
# Sans elles, le modèle ne comprendra pas les entrées et sortira du bruit.

# Ordre Inputs : [Pression, W_Sin, W_Cos, Hum, Temp, D_Sin, D_Cos, H_Sin, H_Cos]
INPUT_MEAN = np.array([1.01837e+03, 9.20436e-01, -1.78854e+00, 8.96079e+01, 5.11393e+00, -2.55327e-01, 9.54684e-01, -3.18572e-03, 4.85887e-03], dtype=np.float32)
INPUT_SCALE = np.array([7.57291, 1.99673, 4.15208, 12.01470, 3.94530, 0.14743, 0.04060, 0.70896, 0.70522], dtype=np.float32)

# Ordre Targets : [Temp, W_Sin, W_Cos]
TARGET_MEAN = np.array([5.23164, 1.45494, -1.77619], dtype=np.float32)
TARGET_SCALE = np.array([4.39401, 2.27482, 3.15535], dtype=np.float32)

# Colonnes à utiliser
FEATURE_COLS = [
    'forecast_pressure', 'forecast_wind_sin', 'forecast_wind_cos', 
    'forecast_humidity', 'forecast_temperature',
    'day_sin', 'day_cos', 'hour_sin', 'hour_cos'
]
TARGET_COLS = ['temperature', 'wind_sin', 'wind_cos']

# ==========================================
# 2. FONCTIONS UTILITAIRES
# ==========================================
def get_physical_wind(sin_col, cos_col):
    """Convertit U/V (Sin/Cos) en Vitesse et Direction (Degrés)"""
    # Vitesse (Magnitude)
    speed = np.sqrt(sin_col**2 + cos_col**2)
    # Direction (Angle 0-360)
    angle_rad = np.arctan2(sin_col, cos_col)
    angle_deg = (np.degrees(angle_rad) + 360) % 360
    return speed, angle_deg

def load_and_prep_data(csv_path):
    """Charge le CSV et applique la normalisation manuelle"""
    print("Chargement des données...")
    df = pd.read_csv(csv_path).dropna()
    
    # On prend tout le CSV pour la visu (ou tu peux faire un sample)
    X_raw = df[FEATURE_COLS].values.astype(np.float32)
    y_raw = df[TARGET_COLS].values.astype(np.float32)
    
    # --- NORMALISATION MANUELLE (Comme en PROD) ---
    # (Raw - Mean) / Scale
    X_norm = (X_raw - INPUT_MEAN) / INPUT_SCALE
    
    print(f"{len(df)} lignes chargées et normalisées.")
    return X_norm, y_raw # On retourne y_raw car c'est la vérité terrain

def run_onnx_inference(X_norm):
    """Exécute le modèle ONNX et dé-normalise la sortie"""
    print(f"Chargement du modèle ONNX : {ONNX_MODEL_PATH}...")
    sess = rt.InferenceSession(ONNX_MODEL_PATH, providers=["CPUExecutionProvider"])
    input_name = sess.get_inputs()[0].name
    
    print("Inférence en cours...")
    # ONNX attend des float32
    preds_norm = sess.run(None, {input_name: X_norm.astype(np.float32)})[0]
    
    # --- DÉ-NORMALISATION MANUELLE ---
    # (Pred_Norm * Scale) + Mean
    preds_physical = (preds_norm * TARGET_SCALE) + TARGET_MEAN
    
    return preds_physical

# ==========================================
# 3. MAIN : GÉNÉRATION DES GRAPHIQUES
# ==========================================
if __name__ == "__main__":
    # A. Préparation
    X_norm, y_real_physical = load_and_prep_data(CSV_DATA_PATH)
    
    # B. Prédiction ONNX
    y_pred_physical = run_onnx_inference(X_norm)
    
    # C. Calcul du Score R2 global sur la vitesse
    real_speed, real_dir = get_physical_wind(y_real_physical[:, 1], y_real_physical[:, 2])
    pred_speed, pred_dir = get_physical_wind(y_pred_physical[:, 1], y_pred_physical[:, 2])
    
    r2_speed = r2_score(real_speed, pred_speed)
    print(f"\n--- Score R² sur la Vitesse du Vent : {r2_speed:.4f} ---")

    # ==========================================
    # VISU 1 : SCATTER PLOT (VITESSE)
    # ==========================================
    plt.figure(figsize=(8, 8))
    plt.scatter(real_speed, pred_speed, alpha=0.2, s=10, color='purple')
    
    # Ligne diagonale parfaite
    max_val = max(real_speed.max(), pred_speed.max())
    plt.plot([0, max_val], [0, max_val], 'k--', label='Prédiction Parfaite')
    
    plt.title(f"Vitesse Vent : Réalité vs ONNX (R²={r2_speed:.2f})")
    plt.xlabel("Vitesse Réelle (m/s)")
    plt.ylabel("Vitesse Prédite par ONNX (m/s)")
    plt.legend()
    plt.xlim(0, max_val)
    plt.ylim(0, max_val)
    plt.tight_layout()
    plt.show()

    # ==========================================
    # VISU 2 : TIME SERIES (ZOOM)
    # ==========================================
    # On regarde les 200 premiers points (si le CSV est trié chronologiquement)
    subset = 200 
    plt.figure(figsize=(14, 6))
    plt.plot(real_speed[:subset], label='Réalité (Target)', color='black', linewidth=1.5, alpha=0.7)
    plt.plot(pred_speed[:subset], label='Prédiction ONNX', color='#007acc', linewidth=2)
    
    plt.title(f"Dynamique Temporelle (Zoom sur {subset} points)")
    plt.ylabel("Vitesse (m/s)")
    plt.xlabel("Temps (index)")
    plt.legend()
    plt.tight_layout()
    plt.show()

    # ==========================================
    # VISU 3 : ERREUR POLAIRE (BIAS DIRECTIONNEL)
    # ==========================================
    # Positif = Le modèle surestime. Négatif = Il sous-estime.
    speed_error = pred_speed - real_speed
    
    plt.figure(figsize=(10, 10))
    ax = plt.subplot(111, polar=True)
    ax.set_theta_zero_location("N")
    ax.set_theta_direction(-1) # Sens horaire

    # Plot: Angle = Direction Réelle, Rayon = Erreur Absolue, Couleur = Signe de l'erreur
    # On utilise real_dir (en degrés) converti en radians pour le plot
    sc = ax.scatter(np.radians(real_dir), speed_error, 
                    c=speed_error, cmap='coolwarm', 
                    alpha=0.6, s=20, vmin=-5, vmax=5) # vmin/vmax bornent les couleurs entre -5 et +5 m/s

    plt.title("Où le modèle se trompe-t-il ?\n(Erreur de vitesse selon la direction du vent)", va='bottom')
    cbar = plt.colorbar(sc, pad=0.1)
    cbar.set_label("Erreur (m/s) : Rouge=Surestimation, Bleu=Sous-estimation")
    plt.tight_layout()
    plt.show()
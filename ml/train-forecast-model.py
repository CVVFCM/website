import pandas as pd
import numpy as np
from sklearn.experimental import enable_hist_gradient_boosting  # Nécessaire pour certaines versions sklearn
from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.multioutput import MultiOutputRegressor
from sklearn.model_selection import train_test_split
from skl2onnx import to_onnx
from skl2onnx.common.data_types import FloatTensorType

# ==========================================
# 1. CHARGEMENT
# ==========================================
df = pd.read_csv('./data/ml.csv').dropna()

# Inputs et Targets
feature_cols = ['forecast_pressure', 'forecast_wind_sin', 'forecast_wind_cos', 'forecast_humidity', 'forecast_temperature']
target_cols = ['temperature', 'wind_sin', 'wind_cos']

X = df[feature_cols]
y = df[target_cols]

# Split (Shuffle=True est important si tu n'as pas 3 ans de données pour casser la saisonnalité)
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, shuffle=True)

# ==========================================
# 2. ENTRAÎNEMENT (CHANGEMENT DE STRATÉGIE)
# ==========================================
print("Entraînement avec HistGradientBoosting...")

# HistGradientBoosting est excellent pour corriger les biais d'échelle
# Mais il ne gère qu'une sortie à la fois, donc on utilise MultiOutputRegressor
hgb = HistGradientBoostingRegressor(
    max_iter=100, 
    learning_rate=0.1, 
    max_depth=10, 
    random_state=42
)

model = MultiOutputRegressor(hgb)
model.fit(X_train, y_train)

# Score de vérification
score = model.score(X_test, y_test)
print(f"Nouveau Score R² : {score:.4f}") 
# Si ce score est > 0.3 ou 0.4, c'est gagné pour un début.

# ==========================================
# 3. EXPORT ONNX (COMPATIBLE MULTI-OUTPUT)
# ==========================================
print("\nConversion ONNX...")

# On définit l'entrée : 1 matrice float, N lignes, 5 colonnes
n_features = len(feature_cols)
initial_type = [('X', FloatTensorType([None, n_features]))]

# L'astuce pour MultiOutput avec ONNX :
# skl2onnx gère mieux MultiOutputRegressor si on ne force pas trop les options manuelles
onx = to_onnx(
    model, 
    initial_types=initial_type,
    target_opset=12 # Nécessaire pour les opérateurs récents
)

onnx_filename = "./ml/model_meteo_boost.onnx"
with open(onnx_filename, "wb") as f:
    f.write(onx.SerializeToString())

print(f"Exporté : {onnx_filename}")

# ==========================================
# 4. TEST DE FORME
# ==========================================
import onnxruntime as rt
sess = rt.InferenceSession(onnx_filename, providers=["CPUExecutionProvider"])

# Test avec une fausse donnée
dummy = X_test.iloc[:1].to_numpy().astype(np.float32)
pred = sess.run(None, {'X': dummy})[0]

print(f"Input shape: {dummy.shape}")
print(f"Output shape: {pred.shape}") # Doit être (1, 3)
print(f"Valeurs prédites : {pred}")


# Score rapide (R2)
score = model.score(X_test, y_test)
print(f"Score R² global sur le test : {score:.4f}")






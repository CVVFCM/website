import pandas as pd
import numpy as np
import torch
import torch.nn as nn
import torch.optim as optim
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import r2_score

# ==========================================
# 1. PRÉPARATION DES DONNÉES (CRITIQUE)
# ==========================================
df = pd.read_csv('./data/ml.csv').dropna()

feature_cols = ['forecast_pressure', 'forecast_wind_sin', 'forecast_wind_cos', 'forecast_humidity', 'forecast_temperature', 'day_sin', 'day_cos', 'hour_sin', 'hour_cos']
target_cols = ['temperature', 'wind_sin', 'wind_cos']

X = df[feature_cols].values.astype(np.float32)
y = df[target_cols].values.astype(np.float32)

# --- NORMALISATION (OBLIGATOIRE EN DEEP LEARNING) ---
# On garde les scalers en mémoire, car il faudra les appliquer en prod !
scaler_X = StandardScaler()
scaler_y = StandardScaler()

X_scaled = scaler_X.fit_transform(X)
y_scaled = scaler_y.fit_transform(y)

# Split
X_train, X_test, y_train, y_test = train_test_split(X_scaled, y_scaled, test_size=0.2, random_state=42, shuffle=True)

# Conversion en Tenseurs PyTorch
X_train_th = torch.tensor(X_train)
y_train_th = torch.tensor(y_train)
X_test_th  = torch.tensor(X_test)
y_test_th  = torch.tensor(y_test)

# ==========================================
# 2. DÉFINITION DU MODÈLE (RESEAU DE NEURONES)
# ==========================================
class WeatherNet(nn.Module):
    def __init__(self):
        super(WeatherNet, self).__init__()
        # Architecture simple : 5 entrées -> 64 neurones -> 64 neurones -> 3 sorties
        self.layer1 = nn.Linear(9, 128)
        self.layer2 = nn.Linear(128, 128)
        self.layer3 = nn.Linear(128, 64)
        self.output = nn.Linear(64, 3)
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

model = WeatherNet()
criterion = nn.MSELoss() # On cherche à minimiser l'erreur quadratique moyenne
optimizer = optim.Adam(model.parameters(), lr=0.001)

# ==========================================
# 3. BOUCLE D'ENTRAÎNEMENT
# ==========================================
print("Début de l'entraînement PyTorch...")
epochs = 500
batch_size = 64
n_samples = X_train_th.shape[0]

for epoch in range(epochs):
    model.train()
    
    # Mélange des indices pour faire des batchs aléatoires
    permutation = torch.randperm(n_samples)
    
    for i in range(0, n_samples, batch_size):
        indices = permutation[i:i+batch_size]
        batch_x, batch_y = X_train_th[indices], y_train_th[indices]
        
        # 1. Reset gradients
        optimizer.zero_grad()
        # 2. Prediction
        outputs = model(batch_x)
        # 3. Calcul de l'erreur
        loss = criterion(outputs, batch_y)
        # 4. Backpropagation (apprentissage)
        loss.backward()
        # 5. Mise à jour des poids
        optimizer.step()
        
    if (epoch+1) % 50 == 0:
        print(f"Epoch [{epoch+1}/{epochs}], Loss: {loss.item():.4f}")

# ==========================================
# 4. ÉVALUATION
# ==========================================
model.eval()
with torch.no_grad():
    y_pred_scaled = model(X_test_th).numpy()

# IMPORTANT : On dé-normalise pour comparer avec la vraie vie (kts et °C)
y_pred_final = scaler_y.inverse_transform(y_pred_scaled)
y_test_final = scaler_y.inverse_transform(y_test)

r2 = r2_score(y_test_final, y_pred_final)
print(f"R² : {r2:.4f}")

# ==========================================
print("\nONNX Export...")

dummy_input = torch.randn(1, 9) 

torch.onnx.export(
    model, 
    dummy_input, 
    "./ml/model_pytorch.onnx",
    export_params=True,
    opset_version=17,
    do_constant_folding=True,
    input_names = ['input'],  
    output_names = ['output'],
    dynamic_axes={'input' : {0 : 'batch_size'}, 'output' : {0 : 'batch_size'}}
)

print("Modèle sauvegardé : model_pytorch.onnx")

# Scaler params to give to PHP-ORT later
print(f"Moyenne (Input) : {scaler_X.mean_}")
print(f"Scale (Input)   : {scaler_X.scale_}")
print(f"Moyenne (Target): {scaler_y.mean_}")
print(f"Scale (Target)  : {scaler_y.scale_}")
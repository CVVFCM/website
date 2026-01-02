import matplotlib.pyplot as plt
import seaborn as sns
import numpy as np
import pandas as pd

# ==========================================
# 0. PRÉPARATION DES DONNÉES
# ==========================================
# On refait des prédictions propres sur le test set
y_pred = model.predict(X_test)

# On convertit tout en DataFrame pour faciliter la manip
# Attention : y_test est déjà un DataFrame, mais y_pred est un array numpy
df_res = y_test.copy()
df_res.columns = ['Real_Temp', 'Real_Sin', 'Real_Cos']

df_res['Pred_Temp'] = y_pred[:, 0]
df_res['Pred_Sin']  = y_pred[:, 1]
df_res['Pred_Cos']  = y_pred[:, 2]

# Reconstruction de la Vitesse du Vent (Magnitude)
# Vitesse = sqrt(Sin² + Cos²)
# Note: Si tes targets étaient en m/s (ce qui semble être le cas vu tes graphes -10 à 10), c'est direct.
df_res['Real_Speed'] = np.sqrt(df_res['Real_Sin']**2 + df_res['Real_Cos']**2)
df_res['Pred_Speed'] = np.sqrt(df_res['Pred_Sin']**2 + df_res['Pred_Cos']**2)

# Reconstruction de la Direction (Angles)
df_res['Real_Dir'] = (np.degrees(np.arctan2(df_res['Real_Sin'], df_res['Real_Cos'])) + 360) % 360
df_res['Pred_Dir'] = (np.degrees(np.arctan2(df_res['Pred_Sin'], df_res['Pred_Cos'])) + 360) % 360

# ==========================================
# VISUALISATION 1 : RÉALITÉ vs PRÉDICTION (Scatter Plot)
# ==========================================
plt.figure(figsize=(10, 6))
plt.scatter(df_res['Real_Speed'], df_res['Pred_Speed'], alpha=0.3, color='#4c72b0', s=15)

# Ligne parfaite idéale
max_val = max(df_res['Real_Speed'].max(), df_res['Pred_Speed'].max())
plt.plot([0, max_val], [0, max_val], 'r--', linewidth=2, label='Parfait')

plt.title(f"Vitesse du Vent : Réalité vs Modèle (R² = {score:.2f})")
plt.xlabel("Vitesse Réelle (m/s)")
plt.ylabel("Vitesse Prédite (m/s)")
plt.grid(True, alpha=0.3)
plt.legend()
plt.show()

# ==========================================
# VISUALISATION 2 : SÉRIE TEMPORELLE (Zoom sur 100h)
# ==========================================
# On prend un échantillon aléatoire de 100 points consécutifs s'ils sont triés, 
# ou juste les 100 premiers du test set.
subset = 100
plt.figure(figsize=(15, 5))

# Attention: X_test a été mélangé (shuffle), donc l'ordre temporel est perdu.
# Pour l'affichage "Time Series", c'est moins joli mais ça permet de voir si les pics sont suivis.
plt.plot(df_res['Real_Speed'].values[:subset], label='Réalité', color='black', linewidth=2)
plt.plot(df_res['Pred_Speed'].values[:subset], label='Prédiction IA', color='#55a868', linewidth=2)

plt.title("Comparaison sur un échantillon de 100 points de test")
plt.ylabel("Vitesse Vent (m/s)")
plt.xlabel("Points de test")
plt.legend()
plt.grid(True, alpha=0.3)
plt.show()

# ==========================================
# VISUALISATION 3 : L'ERREUR SELON LA DIRECTION (Radar Plot)
# ==========================================
# Où est-ce que le modèle se trompe encore ?

df_res['Error_Speed'] = df_res['Pred_Speed'] - df_res['Real_Speed'] # Positif = Surestimation, Négatif = Sous-estimation

plt.figure(figsize=(10, 10))
ax = plt.subplot(111, polar=True)

# On plotte l'erreur en fonction de la direction RÉELLE du vent
sc = ax.scatter(np.radians(df_res['Real_Dir']), df_res['Error_Speed'], 
                c=df_res['Error_Speed'], cmap='coolwarm', alpha=0.5, s=20, vmin=-5, vmax=5)

ax.set_theta_zero_location("N")
ax.set_theta_direction(-1)
plt.colorbar(sc, label="Erreur de Vitesse (m/s) : Rouge=Trop fort, Bleu=Trop faible")
plt.title("Biais du modèle selon la direction du vent")
plt.show()

# ==========================================
# VISUALISATION 4 : DISTRIBUTION DES ERREURS
# ==========================================
plt.figure(figsize=(10, 5))
sns.histplot(df_res['Error_Speed'], kde=True, bins=50, color='purple')
plt.axvline(0, color='k', linestyle='--')
plt.title("Distribution des Erreurs (Doit être centrée sur 0)")
plt.xlabel("Erreur (m/s)")
plt.show()
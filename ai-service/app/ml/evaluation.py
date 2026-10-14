"""Métriques d'évaluation (Phase 9, §15) : MAE, RMSE, R² — calculées uniquement sur le jeu de
test, jamais sur les données d'entraînement (cahier des charges §24 — pas de résultat gonflé)."""
import numpy as np
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score


def compute_metrics(y_true: np.ndarray, y_pred: np.ndarray) -> dict[str, float]:
    return {
        "mae": round(float(mean_absolute_error(y_true, y_pred)), 3),
        "rmse": round(float(np.sqrt(mean_squared_error(y_true, y_pred))), 3),
        "r2": round(float(r2_score(y_true, y_pred)), 4),
    }

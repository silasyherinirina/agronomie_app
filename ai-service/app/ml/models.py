"""
Modèles candidats comparés en Phase 9 (§15) : régression linéaire (référence simple),
Random Forest et Gradient Boosting (capturent les relations non linéaires du signal
engrais/pluviométrie — voir synthetic_data.py). Hyperparamètres fixés et documentés ici plutôt
que réglés arbitrairement à l'entraînement ; une recherche d'hyperparamètres plus poussée
(GridSearch/Optuna) est une amélioration possible, hors périmètre du mémoire M2.
"""
from dataclasses import dataclass

from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.pipeline import Pipeline

from app.ml.features import build_preprocessor

RANDOM_STATE = 42


@dataclass(frozen=True)
class CandidateModel:
    key: str
    label: str
    pipeline: Pipeline


def build_candidate_models() -> list[CandidateModel]:
    return [
        CandidateModel(
            key="linear_regression",
            label="Régression linéaire",
            pipeline=Pipeline(
                [
                    ("preprocess", build_preprocessor(scale_numeric=True)),
                    ("model", LinearRegression()),
                ]
            ),
        ),
        CandidateModel(
            key="random_forest",
            label="Random Forest",
            pipeline=Pipeline(
                [
                    ("preprocess", build_preprocessor(scale_numeric=False)),
                    (
                        "model",
                        RandomForestRegressor(n_estimators=200, max_depth=8, random_state=RANDOM_STATE),
                    ),
                ]
            ),
        ),
        CandidateModel(
            key="gradient_boosting",
            label="Gradient Boosting",
            pipeline=Pipeline(
                [
                    ("preprocess", build_preprocessor(scale_numeric=False)),
                    (
                        "model",
                        GradientBoostingRegressor(
                            n_estimators=200, learning_rate=0.05, max_depth=3, random_state=RANDOM_STATE
                        ),
                    ),
                ]
            ),
        ),
    ]

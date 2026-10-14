"""
Sélection des variables (Phase 9, §15 étape 5) — figée ici pour que schemas/prediction.py,
le pipeline d'entraînement et le service de prédiction restent cohérents entre eux.
"""
from sklearn.compose import ColumnTransformer
from sklearn.preprocessing import OneHotEncoder, StandardScaler

NUMERIC_FEATURES = ["area_ha", "fertilizer_kg_per_ha", "rainfall_mm", "planting_month"]
CATEGORICAL_FEATURES = ["variety_class"]
FEATURE_COLUMNS = NUMERIC_FEATURES + CATEGORICAL_FEATURES
TARGET_COLUMN = "yield_kg_per_ha"


def build_preprocessor(scale_numeric: bool) -> ColumnTransformer:
    """
    scale_numeric=True pour la régression linéaire (sensible à l'échelle des variables) ;
    False pour les modèles à base d'arbres (Random Forest, Gradient Boosting), qui n'en ont
    pas besoin — pratique standard, documentée ici plutôt que laissée implicite.
    """
    numeric_transformer = StandardScaler() if scale_numeric else "passthrough"

    return ColumnTransformer(
        transformers=[
            ("numeric", numeric_transformer, NUMERIC_FEATURES),
            ("categorical", OneHotEncoder(drop="if_binary", handle_unknown="ignore"), CATEGORICAL_FEATURES),
        ]
    )

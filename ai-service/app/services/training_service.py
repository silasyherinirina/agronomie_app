"""
Orchestration du pipeline d'entraînement (Phase 9, §15, étapes 6 à 12), généralisée à une
architecture multi-cultures : `run(crop_key)` entraîne un modèle pour LA culture demandée,
jamais implicitement pour le riz (cause exacte de l'ancien comportement "toujours Riz" —
voir app/ml/crop_registry.py pour le détail des cultures supportées).

Règle de sélection (documentée pour ne pas être arbitraire, §15) : le modèle retenu est celui
qui obtient le meilleur R² sur le jeu de TEST (jamais sur l'entraînement) — inchangée.
"""
from datetime import datetime, timezone

from sklearn.model_selection import train_test_split

from app.errors import AiServiceError
from app.ml.crop_registry import crop_label, is_known_crop
from app.ml.dataset import load_training_dataset
from app.ml.evaluation import compute_metrics
from app.ml.features import FEATURE_COLUMNS, TARGET_COLUMN
from app.ml.models import build_candidate_models
from app.schemas.model import ModelMetadata
from app.services.model_registry import ModelRegistryService

TEST_SIZE = 0.2
RANDOM_STATE = 42


class TrainingService:
    def __init__(self, registry: ModelRegistryService | None = None) -> None:
        self.registry = registry or ModelRegistryService()

    def run(self, crop_key: str) -> dict:
        if not is_known_crop(crop_key):
            # Culture absente du registre (app.ml.crop_registry) — jamais d'entraînement à
            # l'aveugle sur un jeu de données qui n'existe pas (cahier des charges §19).
            raise AiServiceError(
                code="UNKNOWN_CROP",
                message=(
                    f"Culture inconnue du registre multi-cultures : '{crop_key}'. "
                    "Ajoutez-la à app/ml/crop_registry.py avant de l'entraîner."
                ),
                status_code=422,
                crop_key=crop_key,
            )

        df = load_training_dataset(crop_key)
        X = df[FEATURE_COLUMNS]
        y = df[TARGET_COLUMN]

        # Séparation train/test (§15 étape 6) : 80/20, seed fixée pour la reproductibilité.
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=TEST_SIZE, random_state=RANDOM_STATE
        )

        comparison = []
        fitted = {}
        for candidate in build_candidate_models():
            candidate.pipeline.fit(X_train, y_train)
            predictions = candidate.pipeline.predict(X_test)
            metrics = compute_metrics(y_test.to_numpy(), predictions)
            comparison.append({"algorithm": candidate.key, "label": candidate.label, "metrics": metrics})
            fitted[candidate.key] = candidate.pipeline

        # Sélection : meilleur R² sur le jeu de test (règle documentée ci-dessus, pas arbitraire).
        best = max(comparison, key=lambda c: c["metrics"]["r2"])
        best_key = best["algorithm"]
        best_pipeline = fitted[best_key]

        trained_at = datetime.now(timezone.utc)
        model_id = f"{crop_key}-yield-{best_key}-{trained_at.strftime('%Y%m%d-%H%M%S')}"

        metadata = ModelMetadata(
            id=model_id,
            name=f"Rendement du {crop_label(crop_key)} (kg/ha)",
            algorithm=best_key,
            version="1.0.0",
            trained_at=trained_at,
            crop_key=crop_key,
            metrics={
                **best["metrics"],
                "dataset_size": len(df),
                "test_size": TEST_SIZE,
                "comparison": comparison,
            },
            is_active=True,
            # Toujours vrai pour ce pipeline : le seul générateur de données disponible est
            # synthétique (app/ml/synthetic_data.py) — cahier des charges §11/§19/§33. Explicite
            # ici plutôt que de compter sur le défaut du schéma, pour que ce ne soit jamais oublié
            # le jour où une source réelle sera branchée.
            data_source="synthetic",
            is_experimental=True,
        )
        self.registry.save_model(model_id, best_pipeline, metadata, activate=True)

        return {
            "model_id": model_id,
            "crop_key": crop_key,
            "status": "trained",
            "algorithm": best_key,
            "metrics": best["metrics"],
            "comparison": comparison,
        }

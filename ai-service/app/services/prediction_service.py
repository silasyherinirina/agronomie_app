"""Service de prédiction (Phase 9) : charge le modèle actif du registre et l'applique à une
observation. Aucune prédiction n'est renvoyée si aucun modèle n'a encore été entraîné."""
import logging
from datetime import datetime, timezone

import numpy as np
import pandas as pd
from sklearn.pipeline import Pipeline

from app.errors import AiServiceError
from app.ml.crop_registry import crop_label, is_known_crop
from app.schemas.prediction import PredictionFeatures, PredictionResponse
from app.services.model_registry import ModelRegistryService

logger = logging.getLogger(__name__)


class PredictionService:
    def __init__(self, registry: ModelRegistryService | None = None) -> None:
        self.registry = registry or ModelRegistryService()

    def predict(self, crop_key: str, features: PredictionFeatures) -> PredictionResponse:
        """
        Ne JAMAIS retomber silencieusement sur le modèle d'une autre culture (ex. Riz) pour
        "quand même répondre quelque chose" — c'est exactement le comportement interdit par le
        cahier des charges §19. Distingue trois échecs (§9) plutôt qu'un unique 503 générique :
          - UNKNOWN_CROP (422)      : `crop_key` absent du registre (app/ml/crop_registry.py) ;
          - MODEL_INACTIVE (503)    : un modèle existe pour cette culture mais a été désactivé ;
          - TRAINING_REQUIRED (503) : aucun modèle n'a jamais été entraîné pour cette culture.
        """
        if not is_known_crop(crop_key):
            raise AiServiceError(
                code="UNKNOWN_CROP",
                message=f"« {crop_key} » n'est pas une culture reconnue par le service IA.",
                status_code=422,
                crop_key=crop_key,
            )

        active = self.registry.get_active_model(crop_key)
        if active is None:
            if self.registry.list_models_for_crop(crop_key):
                raise AiServiceError(
                    code="MODEL_INACTIVE",
                    message=(
                        f"Le modèle de rendement de la culture « {crop_label(crop_key)} » existe "
                        f"mais n'est actuellement pas actif. Réentraînez-le (POST /train) ou "
                        f"réactivez une version existante."
                    ),
                    status_code=503,
                    crop_key=crop_key,
                )
            raise AiServiceError(
                code="TRAINING_REQUIRED",
                message=(
                    f"Aucun modèle entraîné n'est disponible pour la culture « {crop_label(crop_key)} ». "
                    f"Cette culture ne dispose pas encore d'un modèle suffisamment entraîné — "
                    f"lancez d'abord POST /train avec crop_key=\"{crop_key}\" (aucune prédiction "
                    f"inventée ne sera renvoyée, cahier des charges §19)."
                ),
                status_code=503,
                crop_key=crop_key,
            )

        estimator = self.registry.load_estimator(active.id)
        row = pd.DataFrame([features.model_dump()])
        predicted_yield = float(estimator.predict(row)[0])

        metrics = {k: v for k, v in (active.metrics or {}).items() if k in {"mae", "rmse", "r2"}}

        return PredictionResponse(
            predicted_yield=round(predicted_yield, 1),
            crop_key=active.crop_key,
            model_id=active.id,
            algorithm=active.algorithm,
            model_version=active.version,
            metrics=metrics,
            predicted_at=datetime.now(timezone.utc),
            feature_importances=self._extract_feature_importances(estimator),
            # Reflète la provenance réelle du modèle chargé (voir ModelMetadata.is_experimental)
            # plutôt qu'une valeur figée — jamais un modèle synthétique présenté comme validé.
            is_experimental=active.is_experimental,
        )

    @staticmethod
    def _extract_feature_importances(estimator: Pipeline) -> dict[str, float] | None:
        """
        Importance réelle calculée par le modèle entraîné (jamais inventée, §24) :
        - modèles à base d'arbres : `feature_importances_` (réduction d'impureté) ;
        - régression linéaire : valeur absolue des coefficients, normalisée à somme 1 —
          comparable entre variables car celles-ci sont standardisées (StandardScaler).
        Renvoie None si l'un ou l'autre n'est pas disponible plutôt que de faire échouer /predict.
        """
        try:
            preprocessor = estimator.named_steps["preprocess"]
            model = estimator.named_steps["model"]
            names = [n.split("__", 1)[-1] for n in preprocessor.get_feature_names_out()]

            if hasattr(model, "feature_importances_"):
                values = np.asarray(model.feature_importances_, dtype=float)
            elif hasattr(model, "coef_"):
                coef = np.abs(np.asarray(model.coef_, dtype=float))
                total = coef.sum()
                values = coef / total if total > 0 else coef
            else:
                return None

            importances = {name: round(float(value), 4) for name, value in zip(names, values)}
            return dict(sorted(importances.items(), key=lambda kv: -kv[1]))
        except Exception:  # défensif : /predict ne doit jamais échouer à cause de ce calcul annexe
            logger.warning("Impossible de calculer l'importance des variables.", exc_info=True)
            return None

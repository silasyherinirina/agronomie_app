"""POST /train — implémentation réelle (Phase 9) : entraîne, compare et sauvegarde le meilleur
des 3 modèles candidats (voir app/services/training_service.py). Une culture inconnue du
registre (app.ml.crop_registry) lève un AiServiceError(UNKNOWN_CROP) sérialisé par le
gestionnaire global (app/main.py) — jamais un entraînement à l'aveugle."""
from fastapi import APIRouter

from app.schemas.training import TrainingRequest, TrainingResponse
from app.services.training_service import TrainingService

router = APIRouter(tags=["training"])


def get_training_service() -> TrainingService:
    return TrainingService()


@router.post("/train", response_model=TrainingResponse)
def train(request: TrainingRequest) -> TrainingResponse:
    result = get_training_service().run(request.crop_key)
    return TrainingResponse(**result)

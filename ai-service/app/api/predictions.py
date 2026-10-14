"""
POST /predict — implémentation réelle (Phase 9), branchée sur le modèle actif du registre.

Ne renvoie jamais de valeur inventée : les échecs métier (culture inconnue, modèle inactif,
modèle jamais entraîné) sont levés par PredictionService sous forme d'AiServiceError et
sérialisés par le gestionnaire global (app/main.py) en enveloppe structurée — cahier des
charges §9/§24.
"""
from fastapi import APIRouter

from app.schemas.prediction import PredictionRequest, PredictionResponse
from app.services.prediction_service import PredictionService

router = APIRouter(tags=["predictions"])


def get_prediction_service() -> PredictionService:
    return PredictionService()


@router.post("/predict", response_model=PredictionResponse)
def predict(request: PredictionRequest) -> PredictionResponse:
    return get_prediction_service().predict(request.crop_key, request.features)

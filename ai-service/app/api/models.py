"""
Registre des modèles entraînés. Contrairement à /predict et /train, ces endpoints sont
pleinement fonctionnels dès la Phase 8 : lister un registre vide est une réponse honnête
(aucun modèle n'existe encore), pas un résultat inventé.
"""
from fastapi import APIRouter, HTTPException

from app.ml.crop_registry import CROP_PROFILES
from app.schemas.model import CropModelStatus, ModelListResponse, ModelMetadata, ModelStatusResponse
from app.services.model_registry import ModelRegistryService

router = APIRouter(prefix="/models", tags=["models"])


def get_registry() -> ModelRegistryService:
    return ModelRegistryService()


@router.get("", response_model=ModelListResponse)
def list_models() -> ModelListResponse:
    return ModelListResponse(data=get_registry().list_models())


@router.get("/status", response_model=ModelStatusResponse)
def list_model_status() -> ModelStatusResponse:
    """
    Statut par culture pour TOUTES les cultures connues du service IA (app/ml/crop_registry.py),
    y compris celles n'ayant jamais été entraînées — c'est le point d'entrée qui permet au
    frontend de savoir, pour chaque culture, si elle dispose d'un modèle SANS avoir à deviner
    (cahier des charges §13/§16) : "culture supportée" (présente ici) ≠ "modèle disponible"
    (`available=true`).
    """
    registry = get_registry()
    entries = []
    for crop_key, profile in CROP_PROFILES.items():
        active = registry.get_active_model(crop_key)
        if active is not None:
            entries.append(CropModelStatus(
                crop_key=crop_key, crop_label=profile.label, available=True, status="ready",
                algorithm=active.algorithm, version=active.version, model_id=active.id,
                is_experimental=active.is_experimental,
            ))
        elif registry.list_models_for_crop(crop_key):
            entries.append(CropModelStatus(
                crop_key=crop_key, crop_label=profile.label, available=False, status="inactive",
            ))
        else:
            entries.append(CropModelStatus(
                crop_key=crop_key, crop_label=profile.label, available=False, status="training_required",
            ))
    return ModelStatusResponse(data=entries)


@router.get("/{model_id}", response_model=ModelMetadata)
def get_model(model_id: str) -> ModelMetadata:
    model = get_registry().get_model(model_id)
    if model is None:
        raise HTTPException(status_code=404, detail=f"Modèle '{model_id}' introuvable.")
    return model

"""Schémas d'entraînement (Phase 9 — pipeline réel branché sur TrainingService)."""
from pydantic import BaseModel


class TrainingRequest(BaseModel):
    # Architecture multi-cultures : culture pour laquelle entraîner un modèle (voir
    # app/ml/crop_registry.py pour la liste des cultures supportées). OBLIGATOIRE — aucun
    # entraînement "par défaut" sur le riz (cahier des charges §7/§40).
    crop_key: str


class CandidateResult(BaseModel):
    algorithm: str
    label: str
    metrics: dict


class TrainingResponse(BaseModel):
    model_id: str
    crop_key: str
    status: str
    algorithm: str
    metrics: dict
    comparison: list[CandidateResult]

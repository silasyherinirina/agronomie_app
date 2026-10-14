"""Agrège tous les routeurs de l'application en un seul point d'inclusion pour main.py."""
from fastapi import APIRouter

from app.api import health, models, predictions, training

api_router = APIRouter()
api_router.include_router(health.router)
api_router.include_router(predictions.router)
api_router.include_router(training.router)
api_router.include_router(models.router)

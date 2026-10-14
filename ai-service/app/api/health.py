"""Endpoint de santé — déplacé depuis main.py en Phase 8 pour séparer routage et bootstrap."""
from fastapi import APIRouter

from app.config.settings import settings
from app.schemas.health import HealthResponse

router = APIRouter(tags=["health"])


@router.get("/health", response_model=HealthResponse)
def health_check() -> HealthResponse:
    """Vérifie que le service est démarré et répond."""
    return HealthResponse(status="ok", service=settings.name, version=settings.version)

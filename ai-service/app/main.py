"""
Point d'entrée du service IA (FastAPI).

Phase 8 — Service Python : architecture propre (api/services/schemas/config), routage
séparé du bootstrap. Les endpoints /predict et /train exposent leur contrat d'API mais
répondent 501 tant que le pipeline Machine Learning (Phase 9) n'existe pas ; /models et
/health sont pleinement fonctionnels dès cette phase.
"""
from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

from app.api.router import api_router
from app.config.settings import settings
from app.errors import AiServiceError

app = FastAPI(
    title="Agriculture Mada - AI Service",
    description="Service Machine Learning indépendant pour la prévision de production agricole.",
    version=settings.version,
)

app.include_router(api_router)


@app.exception_handler(AiServiceError)
async def handle_ai_service_error(request: Request, exc: AiServiceError) -> JSONResponse:
    """Sérialise toute erreur métier (§9) en enveloppe structurée plutôt qu'en 500 générique ou
    en simple chaîne `detail` que le frontend devrait analyser au jugé."""
    error: dict = {"code": exc.code, "message": exc.message}
    if exc.crop_key is not None:
        error["crop_key"] = exc.crop_key
    return JSONResponse(status_code=exc.status_code, content={"success": False, "error": error})

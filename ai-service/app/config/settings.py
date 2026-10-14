"""
Configuration centralisée du service IA (Phase 8), via pydantic-settings.

Compatibilité Python 3.14.5 vérifiée (cahier des charges §26) : pydantic-settings 2.15.0
installé et fonctionnel dans le venv de ce service (wheel pur Python, aucune dépendance
binaire). Les paramètres liés au Machine Learning (hyperparamètres, seuils d'évaluation)
seront ajoutés en Phase 9, une fois le pipeline conçu.
"""
from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore", case_sensitive=False)

    name: str = "agriculture-mada-ai-service"
    version: str = "0.2.0"

    debug: bool = Field(default=True, alias="AI_SERVICE_DEBUG")
    host: str = Field(default="127.0.0.1", alias="AI_SERVICE_HOST")
    port: int = Field(default=8001, alias="AI_SERVICE_PORT")

    # Le service IA ne se connecte jamais directement à PostgreSQL (cahier des charges §6) :
    # il reçoit ses données via l'API Laravel.
    laravel_api_base_url: str = Field(default="http://127.0.0.1:8000/api", alias="LARAVEL_API_BASE_URL")

    # Emplacement des modèles entraînés sérialisés (Phase 9) et de leurs métadonnées.
    models_dir: str = "models"


settings = Settings()

"""
Schémas du registre de modèles entraînés (Phase 8 : structure ; Phase 9 : alimentation réelle).

Champs alignés sur la table `model_versions` du backend Laravel (voir
backend/database/migrations/2026_08_28_082940_create_model_versions_table.php) pour que
l'intégration Phase 10 (Laravel -> FastAPI) n'ait pas à faire de traduction de schéma.
"""
from datetime import datetime

from pydantic import BaseModel


class ModelMetadata(BaseModel):
    # Identifiant local du modèle sur ce service (nom de dossier sous settings.models_dir),
    # distinct de l'id auto-incrémenté côté table model_versions de Laravel.
    id: str
    name: str
    # linear_regression | random_forest | gradient_boosting (Phase 9)
    algorithm: str
    version: str
    trained_at: datetime | None = None
    # MAE, RMSE, R², hyperparamètres, taille du dataset... (documenté en Phase 9)
    metrics: dict | None = None
    is_active: bool = False
    # Architecture multi-cultures : clé stable identifiant la culture (voir app/ml/crop_registry).
    # Obligatoire — plus de défaut implicite "riz" (cahier des charges §7/§40 : "aucune culture
    # sélectionnée => pas de prédiction possible", jamais un repli sur le riz). Tous les modèles
    # déjà sur disque au moment de ce changement ont été vérifiés porteurs de ce champ.
    # "Un seul modèle actif" (voir ModelRegistryService.save_model) s'applique PAR culture.
    crop_key: str
    # Provenance du jeu d'entraînement (cahier des charges §11/§19/§33) : "synthetic" tant
    # qu'aucun jeu de données agricole réel n'a été intégré (voir app/ml/synthetic_data.py) —
    # jamais présenté comme une observation de terrain. `is_experimental` en est la conséquence
    # directe et doit rester vrai tant que `data_source == "synthetic"`.
    data_source: str = "synthetic"
    is_experimental: bool = True


class ModelListResponse(BaseModel):
    data: list[ModelMetadata]


class CropModelStatus(BaseModel):
    """Statut du modèle pour UNE culture (cahier des charges §13/§16) — permet au frontend de
    distinguer une culture supportée par la plateforme (présente ici) d'une culture disposant
    réellement d'un modèle entraîné et actif (`available=true`, `status="ready"`)."""

    crop_key: str
    crop_label: str
    available: bool
    # ready | inactive | training_required
    status: str
    algorithm: str | None = None
    version: str | None = None
    model_id: str | None = None
    is_experimental: bool | None = None


class ModelStatusResponse(BaseModel):
    data: list[CropModelStatus]

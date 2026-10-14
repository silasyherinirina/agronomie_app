"""
Registre des modèles entraînés (Phase 8 : lecture ; Phase 9 : écriture par le pipeline
d'entraînement).

Convention de stockage :
    models/<model_id>/metadata.json   — champs de ModelMetadata (schemas/model.py)
    models/<model_id>/model.joblib    — pipeline scikit-learn sérialisé (preprocessing inclus)

Un seul modèle est "actif" (is_active=true) à la fois : c'est celui que /predict charge par
défaut. Aucun modèle fictif n'est créé ici : tant qu'aucun entraînement n'a eu lieu, le
registre est vide et le dit honnêtement (cahier des charges §24).
"""
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from app.config.settings import settings
from app.schemas.model import ModelMetadata


class ModelRegistryService:
    def __init__(self, models_dir: str | Path | None = None) -> None:
        self.models_dir = Path(models_dir or settings.models_dir)

    def list_models(self) -> list[ModelMetadata]:
        if not self.models_dir.exists():
            return []

        return [self._load_metadata(f) for f in sorted(self.models_dir.glob("*/metadata.json"))]

    def get_model(self, model_id: str) -> ModelMetadata | None:
        metadata_file = self.models_dir / model_id / "metadata.json"
        if not metadata_file.is_file():
            return None
        return self._load_metadata(metadata_file)

    def list_models_for_crop(self, crop_key: str) -> list[ModelMetadata]:
        """Tous les modèles (actifs ou non) déjà entraînés pour une culture — utilisé pour
        distinguer MODEL_INACTIVE (un modèle existe mais a été désactivé) de TRAINING_REQUIRED
        (aucun modèle n'a jamais été entraîné pour cette culture), cahier des charges §9."""
        return [m for m in self.list_models() if m.crop_key == crop_key]

    def get_active_model(self, crop_key: str) -> ModelMetadata | None:
        """
        Modèle actif pour UNE culture donnée (architecture multi-cultures — avant cette
        extension, un seul modèle pouvait être actif globalement, ce qui empêchait
        structurellement toute autre culture que le riz d'avoir son propre modèle actif).
        `crop_key` est obligatoire : aucun défaut implicite (cahier des charges §7/§13).
        """
        active = [m for m in self.list_models() if m.is_active and m.crop_key == crop_key]
        # Le pipeline d'entraînement garantit l'unicité par culture (voir save_model()) ; en cas
        # d'incohérence manuelle du dossier models/, on prend le plus récent par sûreté.
        return max(active, key=lambda m: m.trained_at or datetime.min.replace(tzinfo=timezone.utc)) if active else None

    def load_estimator(self, model_id: str):  # -> sklearn Pipeline chargé
        import joblib

        model_file = self.models_dir / model_id / "model.joblib"
        if not model_file.is_file():
            raise FileNotFoundError(f"Fichier de modèle introuvable pour '{model_id}'.")
        return joblib.load(model_file)

    def save_model(self, model_id: str, estimator: Any, metadata: ModelMetadata, activate: bool = True) -> ModelMetadata:
        """Sauvegarde un nouveau modèle entraîné et, si activate=True, désactive les autres."""
        import joblib

        model_dir = self.models_dir / model_id
        model_dir.mkdir(parents=True, exist_ok=True)

        joblib.dump(estimator, model_dir / "model.joblib")

        if activate:
            # Un seul modèle actif PAR CULTURE (pas globalement) : entraîner un nouveau modèle
            # Maïs ne doit jamais désactiver le modèle Riz déjà actif — c'était le comportement
            # avant l'architecture multi-cultures, corrigé ici en filtrant sur crop_key.
            for existing in self.list_models():
                if existing.id != model_id and existing.is_active and existing.crop_key == metadata.crop_key:
                    self._write_metadata(existing.model_copy(update={"is_active": False}))
            metadata = metadata.model_copy(update={"is_active": True})

        self._write_metadata(metadata)
        return metadata

    def _write_metadata(self, metadata: ModelMetadata) -> None:
        model_dir = self.models_dir / metadata.id
        model_dir.mkdir(parents=True, exist_ok=True)
        payload = json.loads(metadata.model_dump_json(exclude={"id"}))
        with (model_dir / "metadata.json").open("w", encoding="utf-8") as f:
            json.dump(payload, f, ensure_ascii=False, indent=2)

    @staticmethod
    def _load_metadata(metadata_file: Path) -> ModelMetadata:
        with metadata_file.open(encoding="utf-8") as f:
            payload = json.load(f)
        payload.setdefault("id", metadata_file.parent.name)
        return ModelMetadata.model_validate(payload)

"""GET /models/status : statut par culture pour les 20 cultures connues du service IA (cahier
des charges §13/§16) — "culture supportée" (présente dans la liste) ne veut jamais dire "modèle
disponible" (`available=true`, `status="ready"`)."""
from fastapi.testclient import TestClient

from app.main import app
from app.ml.crop_registry import CROP_PROFILES
from app.services.model_registry import ModelRegistryService
from app.services.training_service import TrainingService

client = TestClient(app)


def use_registry_dir(monkeypatch, tmp_path) -> ModelRegistryService:
    registry = ModelRegistryService(models_dir=tmp_path)
    monkeypatch.setattr("app.api.models.get_registry", lambda: registry)
    return registry


def test_status_lists_all_known_crops_even_untrained(tmp_path, monkeypatch):
    use_registry_dir(monkeypatch, tmp_path)

    response = client.get("/models/status")
    assert response.status_code == 200
    body = response.json()["data"]

    assert len(body) == len(CROP_PROFILES)
    keys = {entry["crop_key"] for entry in body}
    assert keys == set(CROP_PROFILES.keys())
    assert all(entry["status"] == "training_required" and entry["available"] is False for entry in body)


def test_status_marks_a_trained_crop_as_ready_and_others_untouched(tmp_path, monkeypatch):
    registry = use_registry_dir(monkeypatch, tmp_path)
    TrainingService(registry=registry).run("patate_douce")

    body = {entry["crop_key"]: entry for entry in client.get("/models/status").json()["data"]}

    assert body["patate_douce"]["available"] is True
    assert body["patate_douce"]["status"] == "ready"
    assert body["patate_douce"]["is_experimental"] is True
    assert body["riz"]["available"] is False
    assert body["riz"]["status"] == "training_required"

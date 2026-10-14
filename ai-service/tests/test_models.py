"""GET /models et GET /models/{id} : registre réellement vide tant que la Phase 9 n'a rien
entraîné (pas de contenu fictif à tester ici — seulement le comportement honnête à vide)."""
from fastapi.testclient import TestClient

from app.main import app
from app.services.model_registry import ModelRegistryService

client = TestClient(app)


def use_registry_dir(monkeypatch, tmp_path) -> None:
    """Redirige le registre utilisé par les routes vers un dossier isolé pour le test."""
    monkeypatch.setattr("app.api.models.get_registry", lambda: ModelRegistryService(models_dir=tmp_path))


def test_list_models_is_empty_before_any_training(tmp_path, monkeypatch):
    use_registry_dir(monkeypatch, tmp_path)

    response = client.get("/models")
    assert response.status_code == 200
    assert response.json() == {"data": []}


def test_get_unknown_model_returns_404(tmp_path, monkeypatch):
    use_registry_dir(monkeypatch, tmp_path)

    response = client.get("/models/unknown-model")
    assert response.status_code == 404


def test_get_existing_model_reads_metadata_json(tmp_path, monkeypatch):
    model_dir = tmp_path / "yield-rf-v1"
    model_dir.mkdir()
    (model_dir / "metadata.json").write_text(
        '{"name": "Rendement riz", "algorithm": "random_forest", "version": "1.0.0", '
        '"crop_key": "riz", "is_active": true, "metrics": {"mae": 120.5, "rmse": 150.2, "r2": 0.81}}',
        encoding="utf-8",
    )
    use_registry_dir(monkeypatch, tmp_path)

    response = client.get("/models/yield-rf-v1")
    assert response.status_code == 200
    body = response.json()
    assert body["id"] == "yield-rf-v1"
    assert body["algorithm"] == "random_forest"
    assert body["metrics"]["r2"] == 0.81

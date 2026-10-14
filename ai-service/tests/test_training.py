"""POST /train : pipeline réel (Phase 9) — entraîne, compare 3 modèles, sauvegarde le meilleur."""
from fastapi.testclient import TestClient

from app.main import app
from app.services.model_registry import ModelRegistryService
from app.services.training_service import TrainingService

client = TestClient(app)


def use_isolated_registry(monkeypatch, tmp_path) -> None:
    registry = ModelRegistryService(models_dir=tmp_path)
    monkeypatch.setattr("app.api.training.get_training_service", lambda: TrainingService(registry=registry))


def test_train_requires_a_crop_key():
    """Plus d'entraînement "par défaut" sur le riz (cahier des charges §7/§40)."""
    response = client.post("/train", json={})
    assert response.status_code == 422


def test_train_compares_three_candidate_algorithms_and_saves_the_best(tmp_path, monkeypatch):
    use_isolated_registry(monkeypatch, tmp_path)

    response = client.post("/train", json={"crop_key": "riz"})
    assert response.status_code == 200
    body = response.json()

    assert body["status"] == "trained"
    assert body["crop_key"] == "riz"
    assert len(body["comparison"]) == 3
    algorithms = {c["algorithm"] for c in body["comparison"]}
    assert algorithms == {"linear_regression", "random_forest", "gradient_boosting"}

    for metrics in ("mae", "rmse", "r2"):
        assert metrics in body["metrics"]

    # Le modèle choisi doit réellement être celui avec le meilleur R² du comparatif.
    best_r2 = max(c["metrics"]["r2"] for c in body["comparison"])
    assert body["metrics"]["r2"] == best_r2

    # Le modèle sauvegardé doit être rechargeable et marqué actif.
    saved = ModelRegistryService(models_dir=tmp_path).get_active_model("riz")
    assert saved is not None
    assert saved.id == body["model_id"]
    assert saved.is_active is True


def test_training_saved_model_is_actually_usable_for_prediction(tmp_path, monkeypatch):
    use_isolated_registry(monkeypatch, tmp_path)
    client.post("/train", json={"crop_key": "riz"})

    registry = ModelRegistryService(models_dir=tmp_path)
    active = registry.get_active_model("riz")
    estimator = registry.load_estimator(active.id)

    import pandas as pd

    row = pd.DataFrame([{
        "area_ha": 1.5, "fertilizer_kg_per_ha": 50, "rainfall_mm": 1300,
        "planting_month": 11, "variety_class": "amelioree",
    }])
    prediction = estimator.predict(row)[0]
    assert prediction > 0

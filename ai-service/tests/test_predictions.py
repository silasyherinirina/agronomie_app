"""POST /predict : 503 explicite sans modèle entraîné, 200 avec un modèle réellement entraîné
(jamais de prédiction inventée — cahier des charges §24)."""
from fastapi.testclient import TestClient

from app.main import app
from app.services.model_registry import ModelRegistryService
from app.services.prediction_service import PredictionService
from app.services.training_service import TrainingService

client = TestClient(app)

VALID_FEATURES = {
    "area_ha": 1.5,
    "fertilizer_kg_per_ha": 50,
    "rainfall_mm": 1300,
    "planting_month": 11,
    "variety_class": "amelioree",
}


def use_isolated_registry(monkeypatch, tmp_path) -> ModelRegistryService:
    registry = ModelRegistryService(models_dir=tmp_path)
    monkeypatch.setattr("app.api.predictions.get_prediction_service", lambda: PredictionService(registry=registry))
    return registry


def test_predict_returns_503_before_any_trained_model(tmp_path, monkeypatch):
    use_isolated_registry(monkeypatch, tmp_path)

    response = client.post("/predict", json={"crop_key": "riz", "features": VALID_FEATURES})
    assert response.status_code == 503


def test_predict_validates_request_schema():
    response = client.post("/predict", json={"crop_key": "riz", "features": {"area_ha": -1}})
    assert response.status_code == 422


def test_predict_requires_a_crop_key():
    """Plus de repli implicite sur le riz (cahier des charges §7/§40) : une requête sans
    `crop_key` est une erreur de validation, jamais une prédiction silencieuse."""
    response = client.post("/predict", json={"features": VALID_FEATURES})
    assert response.status_code == 422


def test_predict_returns_real_value_after_training(tmp_path, monkeypatch):
    registry = use_isolated_registry(monkeypatch, tmp_path)
    TrainingService(registry=registry).run("riz")

    response = client.post("/predict", json={"plot_id": 1, "crop_key": "riz", "features": VALID_FEATURES})
    assert response.status_code == 200
    body = response.json()

    assert body["predicted_yield"] > 0
    assert body["unit"] == "kg/ha"
    assert body["is_experimental"] is True
    assert set(body["metrics"].keys()) == {"mae", "rmse", "r2"}

    # Variables importantes réellement calculées (pas de valeur inventée, §24) : présentes,
    # positives, et couvrant les 5 variables d'entrée (une variable catégorielle -> 1 colonne
    # one-hot, cf. build_preprocessor(drop='if_binary')).
    assert body["feature_importances"] is not None
    assert len(body["feature_importances"]) == 5
    assert all(v >= 0 for v in body["feature_importances"].values())

    # Absence assumée et documentée d'un niveau de confiance non statistiquement justifié.
    assert "confidence_level" not in body

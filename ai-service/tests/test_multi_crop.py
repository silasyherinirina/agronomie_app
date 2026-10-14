"""
Architecture multi-cultures (voir app/ml/crop_registry.py) : chaque culture entraîne et sert
SON PROPRE modèle, jamais celui du riz par substitution silencieuse (cahier des charges §19).
Couvre les scénarios de validation demandés : prédiction Riz/Maïs/Haricot, culture sans modèle,
mauvaise culture, modèle indisponible, culture inconnue du registre.
"""
from fastapi.testclient import TestClient

from app.main import app
from app.services.model_registry import ModelRegistryService
from app.services.prediction_service import PredictionService
from app.services.training_service import TrainingService

client = TestClient(app)

VALID_FEATURES = {
    "area_ha": 1.5,
    "fertilizer_kg_per_ha": 50,
    "rainfall_mm": 900,
    "planting_month": 11,
    "variety_class": "amelioree",
}


def use_isolated_registry(monkeypatch, tmp_path) -> ModelRegistryService:
    registry = ModelRegistryService(models_dir=tmp_path)
    monkeypatch.setattr("app.api.predictions.get_prediction_service", lambda: PredictionService(registry=registry))
    monkeypatch.setattr("app.api.training.get_training_service", lambda: TrainingService(registry=registry))
    return registry


def test_training_an_unknown_crop_returns_422_without_guessing_a_dataset(tmp_path, monkeypatch):
    use_isolated_registry(monkeypatch, tmp_path)

    response = client.post("/train", json={"crop_key": "culture_inexistante"})
    assert response.status_code == 422
    body = response.json()
    assert body["success"] is False
    assert body["error"]["code"] == "UNKNOWN_CROP"
    assert body["error"]["crop_key"] == "culture_inexistante"


def test_predicting_a_crop_with_no_trained_model_returns_503_not_a_rice_fallback(tmp_path, monkeypatch):
    """Le cœur de la règle §19 : sans modèle Maïs, /predict ne doit JAMAIS utiliser un modèle
    Riz déjà entraîné pour répondre quand même — même si un modèle Riz actif existe."""
    registry = use_isolated_registry(monkeypatch, tmp_path)
    TrainingService(registry=registry).run("riz")

    response = client.post("/predict", json={"crop_key": "mais", "features": VALID_FEATURES})
    assert response.status_code == 503
    body = response.json()
    assert body["success"] is False
    assert body["error"]["code"] == "TRAINING_REQUIRED"
    assert body["error"]["crop_key"] == "mais"
    assert "maïs" in body["error"]["message"].lower()


def test_predicting_an_unsupported_crop_key_returns_unknown_crop_not_a_generic_503(tmp_path, monkeypatch):
    """§9 : une culture totalement absente du registre (jamais un nom fantaisiste inventé) doit
    être distinguée d'une culture connue mais pas encore entraînée."""
    use_isolated_registry(monkeypatch, tmp_path)

    response = client.post("/predict", json={"crop_key": "culture_inexistante", "features": VALID_FEATURES})
    assert response.status_code == 422
    assert response.json()["error"]["code"] == "UNKNOWN_CROP"


def test_predicting_with_an_inactive_model_returns_model_inactive(tmp_path, monkeypatch):
    """§9/test 10 : un modèle existe pour la culture mais a été désactivé manuellement — ce
    n'est pas la même situation qu'une culture jamais entraînée (TRAINING_REQUIRED)."""
    registry = use_isolated_registry(monkeypatch, tmp_path)
    TrainingService(registry=registry).run("riz")
    active = registry.get_active_model("riz")
    registry._write_metadata(active.model_copy(update={"is_active": False}))

    response = client.post("/predict", json={"crop_key": "riz", "features": VALID_FEATURES})
    assert response.status_code == 503
    assert response.json()["error"]["code"] == "MODEL_INACTIVE"


def test_sweet_potato_must_never_use_the_rice_model(tmp_path, monkeypatch):
    """Test de régression critique explicitement demandé par le cahier des charges (§25) :
    même avec un modèle Riz actif, une demande de prédiction Patate douce ne doit jamais
    utiliser ce modèle — soit elle utilise SON PROPRE modèle, soit elle échoue explicitement."""
    registry = use_isolated_registry(monkeypatch, tmp_path)
    TrainingService(registry=registry).run("riz")
    rice_model_id = registry.get_active_model("riz").id

    response = client.post("/predict", json={"crop_key": "patate_douce", "features": VALID_FEATURES})
    if response.status_code == 200:
        assert response.json()["model_id"] != rice_model_id
        assert response.json()["crop_key"] == "patate_douce"
    else:
        assert response.status_code == 503
        assert response.json()["error"]["code"] == "TRAINING_REQUIRED"

    TrainingService(registry=registry).run("patate_douce")
    response = client.post("/predict", json={"crop_key": "patate_douce", "features": VALID_FEATURES})
    assert response.status_code == 200
    body = response.json()
    assert body["crop_key"] == "patate_douce"
    assert body["model_id"] != rice_model_id


def test_training_and_predicting_maize_and_bean_independently(tmp_path, monkeypatch):
    registry = use_isolated_registry(monkeypatch, tmp_path)

    for crop_key in ["mais", "haricot"]:
        train_response = client.post("/train", json={"crop_key": crop_key})
        assert train_response.status_code == 200
        assert train_response.json()["crop_key"] == crop_key

        predict_response = client.post("/predict", json={"crop_key": crop_key, "features": VALID_FEATURES})
        assert predict_response.status_code == 200
        body = predict_response.json()
        assert body["crop_key"] == crop_key
        assert body["predicted_yield"] > 0

    # Les deux modèles doivent coexister, actifs chacun pour SA culture (pas de désactivation
    # croisée — voir ModelRegistryService.save_model()).
    assert registry.get_active_model("mais") is not None
    assert registry.get_active_model("haricot") is not None
    assert registry.get_active_model("mais").id != registry.get_active_model("haricot").id


def test_training_a_second_crop_does_not_deactivate_the_first(tmp_path, monkeypatch):
    registry = use_isolated_registry(monkeypatch, tmp_path)
    TrainingService(registry=registry).run("riz")
    riz_model_before = registry.get_active_model("riz")
    assert riz_model_before is not None

    TrainingService(registry=registry).run("pomme_de_terre")

    riz_model_after = registry.get_active_model("riz")
    assert riz_model_after is not None
    assert riz_model_after.id == riz_model_before.id
    assert riz_model_after.is_active is True


def test_predict_with_missing_features_returns_422(tmp_path, monkeypatch):
    use_isolated_registry(monkeypatch, tmp_path)

    response = client.post("/predict", json={"crop_key": "riz", "features": {"area_ha": 1.5}})
    assert response.status_code == 422


def test_no_crop_key_means_no_prediction_ever(tmp_path, monkeypatch):
    """Règle absolue (cahier des charges §7/§40) : « aucune culture sélectionnée => pas de
    prédiction possible ». Une requête sans `crop_key` doit être rejetée par la validation,
    jamais silencieusement traitée comme une demande de prédiction riz."""
    registry = use_isolated_registry(monkeypatch, tmp_path)
    TrainingService(registry=registry).run("riz")

    response = client.post("/predict", json={"features": VALID_FEATURES})
    assert response.status_code == 422

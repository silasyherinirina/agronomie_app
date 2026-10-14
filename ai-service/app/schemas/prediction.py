"""
Schémas de prédiction (Phase 9 — variables figées après sélection, voir app/ml/features.py).

Le modèle actuel prédit un rendement de riz en kg/ha à partir de données SYNTHÉTIQUES
(app/ml/synthetic_data.py) : toute prédiction renvoyée par /predict doit être comprise comme
expérimentale tant qu'aucun modèle n'a été ré-entraîné sur des données réelles (§16).
"""
from datetime import datetime
from typing import Literal

from pydantic import BaseModel, Field

VarietyClass = Literal["traditionnelle", "amelioree"]


class PredictionFeatures(BaseModel):
    area_ha: float = Field(gt=0, le=50, description="Superficie de la parcelle (ha)")
    fertilizer_kg_per_ha: float = Field(ge=0, le=1000, description="Engrais utilisé (kg/ha)")
    rainfall_mm: float = Field(ge=0, le=5000, description="Pluviométrie cumulée sur le cycle (mm)")
    planting_month: int = Field(ge=1, le=12, description="Mois de plantation (1-12)")
    variety_class: VarietyClass


class PredictionRequest(BaseModel):
    # plot_id/crop_id : purement informatifs pour la traçabilité côté Laravel (Phase 10) — non
    # utilisés par le modèle, qui ne prend en entrée que `features`.
    plot_id: int | None = None
    crop_id: int | None = None
    # Architecture multi-cultures : détermine RÉELLEMENT quel modèle est chargé (voir
    # app/services/model_registry.py, app/ml/crop_registry.py). OBLIGATOIRE — aucun défaut
    # implicite (cahier des charges §7/§40 : "aucune culture sélectionnée => pas de prédiction
    # possible", jamais un repli automatique sur le riz). C'est ce champ — pas `crop_id`, un
    # identifiant Laravel opaque pour ce service — qui route vers le bon modèle.
    crop_key: str
    campaign_id: int | None = None
    features: PredictionFeatures


class PredictionResponse(BaseModel):
    predicted_yield: float
    unit: str = "kg/ha"
    # Culture réellement utilisée pour cette prédiction (celle du modèle chargé, jamais
    # supposée) — permet à l'appelant de vérifier qu'aucune substitution silencieuse n'a eu
    # lieu (cahier des charges §19).
    crop_key: str
    model_id: str
    algorithm: str
    model_version: str
    # Métriques du modèle utilisé (MAE, RMSE, R² sur le jeu de test), affichées à côté de la
    # prédiction — jamais de prédiction sans ses métriques (cahier des charges §10).
    metrics: dict
    predicted_at: datetime
    is_experimental: bool = True
    # Importance relative des variables (Phase 10, "afficher les variables importantes si
    # disponibles") — coefficients standardisés pour la régression linéaire, réduction
    # d'impureté pour les modèles à base d'arbres. Valeurs réelles issues du modèle entraîné,
    # jamais inventées. Absent (None) si le calcul échoue pour une raison quelconque.
    feature_importances: dict[str, float] | None = None
    # Aucun niveau de confiance n'est renvoyé : son calcul rigoureux (intervalle de prédiction,
    # ex. quantile regression ou approximation gaussienne sur les résidus) n'est pas implémenté
    # dans le périmètre de ce mémoire — cahier des charges §10 : "confiance uniquement si
    # statistiquement justifiée". Mieux vaut l'omettre que l'inventer.

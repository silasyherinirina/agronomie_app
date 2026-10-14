"""
Registre des cultures supportées par le pipeline ML (architecture multi-cultures).

Avant cette extension, le service ne savait produire qu'un unique modèle de rendement du riz
(voir synthetic_data.py::generate_synthetic_rice_yield_dataset, non modifiée) — `crop_id` était
accepté par PredictionRequest mais jamais utilisé pour sélectionner un modèle : c'est la cause
exacte pour laquelle /predict renvoyait toujours "Rendement du riz" quelle que soit la culture
demandée côté Laravel.

Chaque entrée ci-dessous décrit une culture pour laquelle un jeu de données SYNTHÉTIQUE de
démonstration peut être généré et un modèle entraîné séparément — toujours présenté comme
expérimental (cahier des charges §33), jamais comme une donnée réelle malgache. Les 20 clés
ci-dessous correspondent exactement aux `crops.code` seedés côté Laravel (voir CropSeeder) :
la présence d'une culture ici la rend ENTRAÎNABLE (POST /train), pas automatiquement pourvue
d'un modèle actif — "culture supportée" ≠ "modèle disponible" (cahier des charges §12/§28).

Pour riz/maïs/haricot/pomme de terre/manioc, les ordres de grandeur de `base_yield_kg_ha`
restent cohérents avec les références agronomiques déjà documentées côté backend Laravel (voir
docs/SOURCES-AGRICULTURE-MADAGASCAR.md : riz ~1700 kg/ha grande saison, maïs ~1500, haricot
~800, pomme de terre ~10000, manioc ~9000). Pour les 15 autres cultures, aucune source chiffrée
équivalente n'est encore documentée pour Madagascar : les valeurs ci-dessous ne sont que des
ordres de grandeur agronomiques génériques plausibles, choisis pour que le générateur synthétique
produise un signal réaliste — jamais une donnée de terrain, d'où `is_experimental=true` et
`data_source="synthetic"` systématiques sur tout modèle entraîné (voir TrainingService.run()).

Pour ajouter une nouvelle culture : ajouter une entrée ici (aucune autre modification requise —
dataset.py, training_service.py et prediction_service.py sont déjà génériques).
"""
from dataclasses import dataclass


@dataclass(frozen=True)
class CropProfile:
    key: str
    label: str
    base_yield_kg_ha: float
    fertilizer_coefficient: float  # effet racine carrée de l'engrais (rendements décroissants)
    rainfall_optimum_mm: float
    rainfall_penalty: float  # pénalité quadratique (par 100 mm²) autour de l'optimum
    variety_bonus_kg_ha: float
    favorable_planting_months: tuple[int, ...]
    seasonal_bonus_kg_ha: float
    noise_std_kg_ha: float


CROP_PROFILES: dict[str, CropProfile] = {
    # --- Céréales -----------------------------------------------------------------------
    "riz": CropProfile(
        key="riz", label="riz", base_yield_kg_ha=1800.0, fertilizer_coefficient=12.0,
        rainfall_optimum_mm=1300.0, rainfall_penalty=0.006, variety_bonus_kg_ha=350.0,
        favorable_planting_months=(10, 11, 12), seasonal_bonus_kg_ha=150.0, noise_std_kg_ha=180.0,
    ),
    "mais": CropProfile(
        key="mais", label="maïs", base_yield_kg_ha=1500.0, fertilizer_coefficient=10.0,
        rainfall_optimum_mm=900.0, rainfall_penalty=0.008, variety_bonus_kg_ha=280.0,
        favorable_planting_months=(10, 11, 12), seasonal_bonus_kg_ha=120.0, noise_std_kg_ha=150.0,
    ),
    "ble": CropProfile(
        key="ble", label="blé", base_yield_kg_ha=1200.0, fertilizer_coefficient=9.0,
        rainfall_optimum_mm=700.0, rainfall_penalty=0.01, variety_bonus_kg_ha=220.0,
        favorable_planting_months=(4, 5, 6), seasonal_bonus_kg_ha=100.0, noise_std_kg_ha=130.0,
    ),
    "triticale": CropProfile(
        key="triticale", label="triticale", base_yield_kg_ha=1300.0, fertilizer_coefficient=9.0,
        rainfall_optimum_mm=750.0, rainfall_penalty=0.009, variety_bonus_kg_ha=230.0,
        favorable_planting_months=(4, 5, 6), seasonal_bonus_kg_ha=110.0, noise_std_kg_ha=140.0,
    ),
    "sorgho": CropProfile(
        key="sorgho", label="sorgho", base_yield_kg_ha=1000.0, fertilizer_coefficient=7.0,
        rainfall_optimum_mm=650.0, rainfall_penalty=0.011, variety_bonus_kg_ha=180.0,
        favorable_planting_months=(10, 11, 12), seasonal_bonus_kg_ha=90.0, noise_std_kg_ha=110.0,
    ),
    # --- Tubercules -----------------------------------------------------------------------
    "manioc": CropProfile(
        key="manioc", label="manioc", base_yield_kg_ha=9000.0, fertilizer_coefficient=30.0,
        rainfall_optimum_mm=1100.0, rainfall_penalty=0.004, variety_bonus_kg_ha=1200.0,
        favorable_planting_months=(10, 11, 12, 1), seasonal_bonus_kg_ha=600.0, noise_std_kg_ha=900.0,
    ),
    "patate_douce": CropProfile(
        key="patate_douce", label="patate douce", base_yield_kg_ha=7000.0, fertilizer_coefficient=24.0,
        rainfall_optimum_mm=900.0, rainfall_penalty=0.007, variety_bonus_kg_ha=1000.0,
        favorable_planting_months=(9, 10, 4), seasonal_bonus_kg_ha=500.0, noise_std_kg_ha=700.0,
    ),
    "pomme_de_terre": CropProfile(
        key="pomme_de_terre", label="pomme de terre", base_yield_kg_ha=10000.0, fertilizer_coefficient=60.0,
        rainfall_optimum_mm=800.0, rainfall_penalty=0.01, variety_bonus_kg_ha=1800.0,
        favorable_planting_months=(4, 5, 9), seasonal_bonus_kg_ha=900.0, noise_std_kg_ha=1000.0,
    ),
    "taro": CropProfile(
        key="taro", label="taro", base_yield_kg_ha=6000.0, fertilizer_coefficient=20.0,
        rainfall_optimum_mm=1400.0, rainfall_penalty=0.005, variety_bonus_kg_ha=800.0,
        favorable_planting_months=(10, 11, 12, 1), seasonal_bonus_kg_ha=400.0, noise_std_kg_ha=600.0,
    ),
    # --- Épice -----------------------------------------------------------------------------
    "gingembre": CropProfile(
        key="gingembre", label="gingembre", base_yield_kg_ha=8000.0, fertilizer_coefficient=26.0,
        rainfall_optimum_mm=1600.0, rainfall_penalty=0.005, variety_bonus_kg_ha=1100.0,
        favorable_planting_months=(9, 10, 11), seasonal_bonus_kg_ha=550.0, noise_std_kg_ha=800.0,
    ),
    # --- Légumineuses ------------------------------------------------------------------------
    "haricot": CropProfile(
        key="haricot", label="haricot", base_yield_kg_ha=800.0, fertilizer_coefficient=6.0,
        rainfall_optimum_mm=650.0, rainfall_penalty=0.012, variety_bonus_kg_ha=150.0,
        favorable_planting_months=(3, 4, 9, 10), seasonal_bonus_kg_ha=80.0, noise_std_kg_ha=90.0,
    ),
    "arachide": CropProfile(
        key="arachide", label="arachide", base_yield_kg_ha=900.0, fertilizer_coefficient=6.5,
        rainfall_optimum_mm=700.0, rainfall_penalty=0.011, variety_bonus_kg_ha=170.0,
        favorable_planting_months=(10, 11, 12), seasonal_bonus_kg_ha=90.0, noise_std_kg_ha=100.0,
    ),
    "niebe": CropProfile(
        key="niebe", label="niébé", base_yield_kg_ha=600.0, fertilizer_coefficient=4.5,
        rainfall_optimum_mm=550.0, rainfall_penalty=0.013, variety_bonus_kg_ha=110.0,
        favorable_planting_months=(10, 11, 12), seasonal_bonus_kg_ha=60.0, noise_std_kg_ha=70.0,
    ),
    "pois_du_cap": CropProfile(
        key="pois_du_cap", label="pois du Cap", base_yield_kg_ha=800.0, fertilizer_coefficient=5.5,
        rainfall_optimum_mm=600.0, rainfall_penalty=0.012, variety_bonus_kg_ha=140.0,
        favorable_planting_months=(3, 4, 9), seasonal_bonus_kg_ha=80.0, noise_std_kg_ha=90.0,
    ),
    "voandzou": CropProfile(
        key="voandzou", label="voandzou", base_yield_kg_ha=700.0, fertilizer_coefficient=5.0,
        rainfall_optimum_mm=600.0, rainfall_penalty=0.012, variety_bonus_kg_ha=120.0,
        favorable_planting_months=(10, 11, 12), seasonal_bonus_kg_ha=70.0, noise_std_kg_ha=80.0,
    ),
    "soja": CropProfile(
        key="soja", label="soja", base_yield_kg_ha=1000.0, fertilizer_coefficient=7.0,
        rainfall_optimum_mm=700.0, rainfall_penalty=0.01, variety_bonus_kg_ha=190.0,
        favorable_planting_months=(10, 11, 12), seasonal_bonus_kg_ha=100.0, noise_std_kg_ha=110.0,
    ),
    # --- Oléagineuse -------------------------------------------------------------------------
    "sesame": CropProfile(
        key="sesame", label="sésame", base_yield_kg_ha=400.0, fertilizer_coefficient=3.0,
        rainfall_optimum_mm=500.0, rainfall_penalty=0.015, variety_bonus_kg_ha=70.0,
        favorable_planting_months=(9, 10, 11), seasonal_bonus_kg_ha=40.0, noise_std_kg_ha=50.0,
    ),
    # --- Cultures de rente -------------------------------------------------------------------
    "cafe": CropProfile(
        key="cafe", label="café", base_yield_kg_ha=500.0, fertilizer_coefficient=4.0,
        rainfall_optimum_mm=1500.0, rainfall_penalty=0.006, variety_bonus_kg_ha=100.0,
        favorable_planting_months=(11, 12, 1), seasonal_bonus_kg_ha=50.0, noise_std_kg_ha=60.0,
    ),
    "vanille": CropProfile(
        key="vanille", label="vanille", base_yield_kg_ha=300.0, fertilizer_coefficient=2.5,
        rainfall_optimum_mm=2000.0, rainfall_penalty=0.004, variety_bonus_kg_ha=60.0,
        favorable_planting_months=(10, 11), seasonal_bonus_kg_ha=30.0, noise_std_kg_ha=40.0,
    ),
    "coton": CropProfile(
        key="coton", label="coton", base_yield_kg_ha=1000.0, fertilizer_coefficient=8.0,
        rainfall_optimum_mm=750.0, rainfall_penalty=0.009, variety_bonus_kg_ha=180.0,
        favorable_planting_months=(11, 12, 1), seasonal_bonus_kg_ha=100.0, noise_std_kg_ha=110.0,
    ),
}


def crop_label(crop_key: str) -> str:
    profile = CROP_PROFILES.get(crop_key)
    return profile.label if profile else crop_key


def is_known_crop(crop_key: str) -> bool:
    return crop_key in CROP_PROFILES

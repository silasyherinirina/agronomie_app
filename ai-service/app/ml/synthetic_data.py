"""
Génération de données SYNTHÉTIQUES pour la prévision de rendement du riz (Phase 9).

⚠️ Ces données sont entièrement artificielles : elles ne représentent AUCUNE exploitation
agricole réelle à Madagascar. Elles servent uniquement à développer et démontrer le pipeline
Machine Learning (nettoyage → préparation → entraînement → évaluation) avant qu'un jeu de
données réel ne soit disponible (cahier des charges §16 — stratégie d'intégration future de
données réelles). Toute sortie de ce pipeline doit être présentée comme expérimentale.

Modèle génératif (documenté pour la reproductibilité, §15) :
    rendement (kg/ha) = base
                       + effet_engrais(fertilizer_kg_per_ha)      [rendements décroissants]
                       + effet_pluviométrie(rainfall_mm)          [optimum, pénalité si excès/déficit]
                       + bonus_variété_améliorée
                       + effet_saisonnalité(mois_de_plantation)
                       + bruit gaussien

Le signal combine des relations non linéaires (engrais, pluviométrie) : c'est volontaire,
pour que la comparaison Random Forest / Gradient Boosting vs régression linéaire (Phase 9,
§15) soit réellement informative plutôt qu'un exercice purement formel.
"""
import numpy as np
import pandas as pd

RANDOM_SEED = 42
VARIETY_CLASSES = ("traditionnelle", "amelioree")


def generate_synthetic_rice_yield_dataset(n_samples: int = 600, seed: int = RANDOM_SEED) -> pd.DataFrame:
    rng = np.random.default_rng(seed)

    area_ha = rng.uniform(0.2, 3.0, n_samples)
    fertilizer_kg_per_ha = rng.uniform(0, 150, n_samples)
    rainfall_mm = rng.normal(1200, 250, n_samples).clip(300, 2200)
    planting_month = rng.integers(1, 13, n_samples)
    variety_class = rng.choice(VARIETY_CLASSES, n_samples, p=[0.6, 0.4])

    base_yield = 1800.0

    # Rendements décroissants au-delà de ~80 kg/ha d'engrais (racine carrée bornée).
    fertilizer_effect = 12.0 * np.sqrt(fertilizer_kg_per_ha)

    # Optimum pluviométrique autour de 1300 mm ; pénalité quadratique de part et d'autre.
    rainfall_effect = -0.006 * (rainfall_mm - 1300.0) ** 2 / 100.0

    variety_bonus = np.where(variety_class == "amelioree", 350.0, 0.0)

    # Saisonnalité simplifiée : plantation en début de saison des pluies (oct-déc) favorisée.
    seasonal_effect = np.where(np.isin(planting_month, [10, 11, 12]), 150.0, 0.0)

    noise = rng.normal(0, 180, n_samples)

    yield_kg_per_ha = (
        base_yield + fertilizer_effect + rainfall_effect + variety_bonus + seasonal_effect + noise
    ).clip(min=200.0)

    return pd.DataFrame(
        {
            "area_ha": area_ha.round(3),
            "fertilizer_kg_per_ha": fertilizer_kg_per_ha.round(1),
            "rainfall_mm": rainfall_mm.round(1),
            "planting_month": planting_month,
            "variety_class": variety_class,
            "yield_kg_per_ha": yield_kg_per_ha.round(1),
        }
    )


def generate_synthetic_yield_dataset(crop_key: str, n_samples: int = 600, seed: int = RANDOM_SEED) -> pd.DataFrame:
    """
    Généralisation multi-cultures du générateur ci-dessus (architecture multi-cultures) : même
    modèle génératif (engrais en racine carrée, optimum pluviométrique quadratique, bonus
    variété/saison, bruit gaussien), mais paramétré par culture via app.ml.crop_registry plutôt
    que figé sur le riz. `crop_key == "riz"` délègue à generate_synthetic_rice_yield_dataset
    ci-dessus (fonction NON modifiée, pour ne jamais changer le comportement déjà testé/versionné
    de data/synthetic/rice_yield_synthetic.csv).

    Toujours des données SYNTHÉTIQUES de démonstration (cahier des charges §33) : jamais une
    récolte malgache réelle.
    """
    if crop_key == "riz":
        return generate_synthetic_rice_yield_dataset(n_samples=n_samples, seed=seed)

    from app.ml.crop_registry import CROP_PROFILES

    profile = CROP_PROFILES.get(crop_key)
    if profile is None:
        raise ValueError(f"Culture inconnue du registre synthétique : '{crop_key}'.")

    rng = np.random.default_rng(seed)

    area_ha = rng.uniform(0.2, 3.0, n_samples)
    fertilizer_kg_per_ha = rng.uniform(0, 150, n_samples)
    rainfall_mm = rng.normal(profile.rainfall_optimum_mm, 250, n_samples).clip(100, 2500)
    planting_month = rng.integers(1, 13, n_samples)
    variety_class = rng.choice(VARIETY_CLASSES, n_samples, p=[0.6, 0.4])

    fertilizer_effect = profile.fertilizer_coefficient * np.sqrt(fertilizer_kg_per_ha)
    rainfall_effect = -profile.rainfall_penalty * (rainfall_mm - profile.rainfall_optimum_mm) ** 2 / 100.0
    variety_bonus = np.where(variety_class == "amelioree", profile.variety_bonus_kg_ha, 0.0)
    seasonal_effect = np.where(np.isin(planting_month, profile.favorable_planting_months), profile.seasonal_bonus_kg_ha, 0.0)
    noise = rng.normal(0, profile.noise_std_kg_ha, n_samples)

    yield_kg_per_ha = (
        profile.base_yield_kg_ha + fertilizer_effect + rainfall_effect + variety_bonus + seasonal_effect + noise
    ).clip(min=profile.base_yield_kg_ha * 0.1)

    return pd.DataFrame(
        {
            "area_ha": area_ha.round(3),
            "fertilizer_kg_per_ha": fertilizer_kg_per_ha.round(1),
            "rainfall_mm": rainfall_mm.round(1),
            "planting_month": planting_month,
            "variety_class": variety_class,
            "yield_kg_per_ha": yield_kg_per_ha.round(1),
        }
    )

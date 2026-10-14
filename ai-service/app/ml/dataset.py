"""Chargement du jeu de données d'entraînement, par culture (architecture multi-cultures).

Charge le CSV synthétique versionné (data/synthetic/<crop_key>_yield_synthetic.csv, généré par
scripts/generate_synthetic_dataset.py) pour que chaque entraînement reparte du même jeu de
données reproductible plutôt que d'en régénérer un différent à chaque appel. Si ce fichier est
absent, le régénère à la volée (mêmes seed/paramètres) pour ne jamais bloquer le pipeline.

`crop_key` est obligatoire (aucun défaut implicite — cahier des charges §7/§40).
"""
from pathlib import Path

import pandas as pd

from app.ml.synthetic_data import generate_synthetic_yield_dataset

DATASET_DIR = Path(__file__).resolve().parents[2] / "data" / "synthetic"
# Nom de fichier historique conservé tel quel (déjà versionné dans le dépôt) : ne pas le
# renommer en "riz_yield_synthetic.csv" pour ne pas perdre la trace du fichier existant.
LEGACY_RICE_DATASET_PATH = DATASET_DIR / "rice_yield_synthetic.csv"


def dataset_path(crop_key: str) -> Path:
    if crop_key == "riz":
        return LEGACY_RICE_DATASET_PATH
    return DATASET_DIR / f"{crop_key}_yield_synthetic.csv"


def load_training_dataset(crop_key: str) -> pd.DataFrame:
    path = dataset_path(crop_key)
    if path.is_file():
        return pd.read_csv(path)
    return generate_synthetic_yield_dataset(crop_key)

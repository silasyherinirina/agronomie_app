"""
Régénère les jeux de données synthétiques versionnés (data/synthetic/*.csv), un par culture du
registre multi-cultures (app/ml/crop_registry.py).

Usage :
    ./venv/Scripts/python scripts/generate_synthetic_dataset.py            # toutes les cultures
    ./venv/Scripts/python scripts/generate_synthetic_dataset.py mais       # une seule culture

À relancer uniquement si la logique de génération (app/ml/synthetic_data.py) change — les
fichiers existants sont déjà reproductibles (seed fixe) et versionnés dans le dépôt.
Le riz garde son fichier historique (rice_yield_synthetic.csv, déjà versionné) ; les autres
cultures suivent le schéma <crop_key>_yield_synthetic.csv (voir app/ml/dataset.py).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.ml.crop_registry import CROP_PROFILES  # noqa: E402
from app.ml.dataset import dataset_path  # noqa: E402
from app.ml.synthetic_data import generate_synthetic_yield_dataset  # noqa: E402


def generate_one(crop_key: str) -> None:
    df = generate_synthetic_yield_dataset(crop_key)
    output_path = dataset_path(crop_key)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    df.to_csv(output_path, index=False)
    print(f"[{crop_key}] Écrit {len(df)} lignes dans {output_path}")


def main() -> None:
    requested = sys.argv[1:]
    crop_keys = requested if requested else list(CROP_PROFILES.keys())

    for crop_key in crop_keys:
        if crop_key not in CROP_PROFILES:
            print(f"Culture inconnue, ignorée : '{crop_key}' (voir app/ml/crop_registry.py)")
            continue
        generate_one(crop_key)


if __name__ == "__main__":
    main()

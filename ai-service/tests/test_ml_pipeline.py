"""Tests unitaires du pipeline ML (Phase 9) : données, features, modèles, évaluation —
indépendamment de la couche API."""
import numpy as np
import pandas as pd
import pytest

from app.ml.evaluation import compute_metrics
from app.ml.features import FEATURE_COLUMNS, NUMERIC_FEATURES, TARGET_COLUMN, build_preprocessor
from app.ml.models import build_candidate_models
from app.ml.synthetic_data import generate_synthetic_rice_yield_dataset


def test_synthetic_dataset_has_expected_shape_and_no_missing_values():
    df = generate_synthetic_rice_yield_dataset(n_samples=200)

    assert len(df) == 200
    assert set(FEATURE_COLUMNS + [TARGET_COLUMN]).issubset(df.columns)
    assert df.isna().sum().sum() == 0
    assert (df["yield_kg_per_ha"] > 0).all()


def test_synthetic_dataset_is_reproducible_with_same_seed():
    df1 = generate_synthetic_rice_yield_dataset(n_samples=50, seed=123)
    df2 = generate_synthetic_rice_yield_dataset(n_samples=50, seed=123)
    pd.testing.assert_frame_equal(df1, df2)


def test_improved_variety_yields_more_on_average_all_else_equal():
    """Vérifie que le signal généré est cohérent avec le modèle génératif documenté
    (synthetic_data.py) : la variété améliorée doit produire un rendement moyen supérieur."""
    df = generate_synthetic_rice_yield_dataset(n_samples=2000, seed=7)
    improved = df[df["variety_class"] == "amelioree"]["yield_kg_per_ha"].mean()
    traditional = df[df["variety_class"] == "traditionnelle"]["yield_kg_per_ha"].mean()
    assert improved > traditional


def test_preprocessor_produces_numeric_matrix_for_both_variants():
    df = generate_synthetic_rice_yield_dataset(n_samples=30)
    X = df[FEATURE_COLUMNS]

    for scale in (True, False):
        transformed = build_preprocessor(scale_numeric=scale).fit_transform(X)
        assert transformed.shape[0] == 30
        # NUMERIC_FEATURES + 1 colonne one-hot (variety_class binaire, drop='if_binary').
        assert transformed.shape[1] == len(NUMERIC_FEATURES) + 1


def test_compute_metrics_returns_expected_keys_and_perfect_score_for_identical_arrays():
    y_true = np.array([100.0, 200.0, 300.0])
    metrics = compute_metrics(y_true, y_true.copy())

    assert metrics == {"mae": 0.0, "rmse": 0.0, "r2": 1.0}


def test_build_candidate_models_returns_three_distinct_algorithms():
    candidates = build_candidate_models()
    keys = {c.key for c in candidates}
    assert keys == {"linear_regression", "random_forest", "gradient_boosting"}


@pytest.mark.parametrize("candidate", build_candidate_models(), ids=lambda c: c.key)
def test_each_candidate_model_can_fit_and_predict_on_synthetic_data(candidate):
    df = generate_synthetic_rice_yield_dataset(n_samples=100)
    X, y = df[FEATURE_COLUMNS], df[TARGET_COLUMN]

    candidate.pipeline.fit(X, y)
    predictions = candidate.pipeline.predict(X)

    assert len(predictions) == len(y)
    assert not np.isnan(predictions).any()

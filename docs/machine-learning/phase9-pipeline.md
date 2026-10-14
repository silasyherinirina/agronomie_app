# Phase 9 — Pipeline Machine Learning

> Service : `ai-service/` (FastAPI, scikit-learn). Objectif : prévoir le rendement du riz
> (kg/ha) à partir de variables agronomiques simples, avec un pipeline complet
> (données → nettoyage → préparation → entraînement → comparaison → évaluation → sauvegarde
> → prédiction), conformément au cahier des charges §15.

## 1. Nature des données — ⚠️ SYNTHÉTIQUES, pas réelles

Aucune donnée agricole réelle de Madagascar n'était disponible au moment de cette phase.
Conformément au cahier des charges (§16 et Phase 9), un jeu de données **entièrement
synthétique** a été généré (`app/ml/synthetic_data.py`, script de régénération
`scripts/generate_synthetic_dataset.py`, fichier versionné
`data/synthetic/rice_yield_synthetic.csv`).

**Ces données ne représentent aucune exploitation réelle.** Toute prédiction produite par ce
pipeline est explicitement marquée `is_experimental: true` dans la réponse de l'API et ne doit
jamais être présentée comme un résultat agronomique réel.

### Modèle génératif

```
rendement (kg/ha) = 1800
                   + 12 × √(engrais_kg_ha)                       [rendements décroissants]
                   − 0.00006 × (pluviométrie_mm − 1300)²         [optimum pluviométrique]
                   + 350 si variété améliorée sinon 0
                   + 150 si plantation oct/nov/déc sinon 0        [saisonnalité]
                   + bruit gaussien (σ = 180)
```

Ce signal combine volontairement des relations **non linéaires** (racine carrée, quadratique)
afin que la comparaison entre régression linéaire et modèles à base d'arbres (Random Forest,
Gradient Boosting) soit réellement informative.

- **Taille du dataset** : 600 observations (paramétrable, `n_samples`).
- **Période** : non applicable (données générées, pas historiques).
- **Seed** : 42 (reproductibilité garantie — voir `test_synthetic_dataset_is_reproducible_with_same_seed`).

## 2. Variables (sélection, §15 étape 5)

| Variable | Type | Rôle |
|---|---|---|
| `area_ha` | numérique | Superficie de la parcelle (ha) |
| `fertilizer_kg_per_ha` | numérique | Quantité d'engrais utilisée (kg/ha) |
| `rainfall_mm` | numérique | Pluviométrie cumulée sur le cycle (mm) — anticipe l'intégration météo réelle (Module L, Phase 11) |
| `planting_month` | numérique (1-12) | Mois de plantation (proxy de saisonnalité) |
| `variety_class` | catégorielle (2 classes) | `traditionnelle` \| `amelioree` |
| `yield_kg_per_ha` | **cible** | Rendement à prédire |

Préparation (`app/ml/features.py`) : `StandardScaler` sur les variables numériques
(régression linéaire uniquement — les modèles à base d'arbres n'en ont pas besoin),
`OneHotEncoder` sur `variety_class`.

## 3. Séparation train/test (§15 étape 6)

`train_test_split(test_size=0.2, random_state=42)` — 80 % entraînement, 20 % test. Toutes les
métriques rapportées sont calculées **uniquement sur le jeu de test**, jamais sur
l'entraînement (cahier des charges §24 — pas de résultat gonflé).

## 4. Modèles comparés (§15 étapes 7-10)

| Algorithme | Hyperparamètres |
|---|---|
| Régression linéaire | par défaut (scikit-learn `LinearRegression`) |
| Random Forest | `n_estimators=200`, `max_depth=8`, `random_state=42` |
| Gradient Boosting | `n_estimators=200`, `learning_rate=0.05`, `max_depth=3`, `random_state=42` |

Aucune recherche d'hyperparamètres automatisée (GridSearch/Optuna) n'a été effectuée — valeurs
raisonnables fixées manuellement et documentées ici plutôt que réglées arbitrairement à chaque
entraînement. Amélioration possible listée en section 7 (Limites).

## 5. Métriques et résultats (§15 étape 9, dernière exécution réelle du 2026-08-29)

Résultats obtenus via `POST /train` sur le jeu de données synthétique versionné (600 lignes,
480 en entraînement / 120 en test) :

| Algorithme | MAE (kg/ha) | RMSE (kg/ha) | R² (test) |
|---|---|---|---|
| **Régression linéaire** | **138.95** | **167.01** | **0.5381** ← retenu |
| Gradient Boosting | 135.72 | 170.24 | 0.5201 |
| Random Forest | 140.74 | 172.19 | 0.5090 |

**C'est la régression linéaire qui obtient le meilleur R² sur le jeu de test**, pas un modèle
d'ensemble. Résultat conservé tel quel (aucun réglage a posteriori pour "faire gagner" un
modèle plus complexe — cela irait à l'encontre du cahier des charges §24). Explication
plausible : le signal généré, bien que non linéaire (racine carrée, quadratique), reste lisse
et globalement monotone sur la plage observée ; combiné à un niveau de bruit modéré (σ=180) et
un jeu d'entraînement de taille modeste (480 lignes), un modèle linéaire bien spécifié peut
égaler, voire dépasser, des ensembles d'arbres qui ont davantage tendance à s'ajuster au bruit
local dans ces conditions. Ce résultat illustre d'ailleurs un point pédagogique pertinent pour
le mémoire : un modèle plus complexe n'est pas automatiquement meilleur, d'où l'intérêt de la
comparaison systématique plutôt que du choix a priori.

R² ≈ 0.54 signifie qu'environ 54 % de la variance du rendement est expliquée par les 5
variables retenues sur les données de test — cohérent avec le niveau de bruit délibérément
injecté dans le générateur (section 1), pas un score « parfait » artificiellement gonflé.

## 6. Sélection et sauvegarde (§15 étapes 11-12)

**Règle de sélection (non arbitraire)** : le modèle retenu est celui qui obtient le
**meilleur R² sur le jeu de test**. Il est sérialisé (`joblib`) dans
`ai-service/models/<model_id>/model.joblib`, avec ses métadonnées
(`metadata.json` : nom, algorithme, version, date d'entraînement, métriques des 3 candidats)
dans le même dossier. Un seul modèle est actif (`is_active: true`) à la fois ; c'est celui que
`POST /predict` charge.

## 7. Limites

- **Données synthétiques** : aucune validité agronomique réelle. Objectif de cette phase :
  démontrer un pipeline complet et correctement architecturé, pas produire des prévisions
  utilisables en l'état.
- Une seule culture modélisée (riz) et un seul jeu de variables — l'architecture
  (`app/ml/features.py`) permet d'ajouter d'autres cultures/variables sans réécrire le pipeline.
- Pas de validation croisée (k-fold) : un simple split train/test a été jugé suffisant pour la
  taille du jeu de données et le périmètre du mémoire M2. Amélioration possible.
- Hyperparamètres fixés manuellement, non optimisés automatiquement.
- Le remplacement par des données réelles (Phase 16 du cahier des charges) nécessitera de
  ré-entraîner (`POST /train`) sur un nouveau `load_training_dataset()` sans changer le reste
  du pipeline (architecture déjà prévue pour cela).

## 8. Intégration (Phase 10)

Laravel appelle ce service en HTTP (`App\Services\AiServiceClient`, config `services.ai.base_url`)
depuis `POST /api/predictions` : il ne calcule jamais de prédiction lui-même. Le résultat est
persisté (`production_predictions`, `model_versions` — colonne `external_id` ajoutée pour
faire correspondre le `model_id` du service IA à un `ModelVersion` Laravel) puis renvoyé au
frontend avec métriques, variables importantes et modèle utilisé. Voir
`docs/api/agriculture-mada.postman_collection.json` (dossier "Predictions") pour les exemples
de requêtes. Certaines variables (`rainfall_mm`, `variety_class`) ne sont pas dérivables des
données actuelles et restent à saisie manuelle côté formulaire — voir
`PredictionService::suggestInputs` (backend).

## 9. Reproduire l'entraînement

```powershell
cd ai-service
./venv/Scripts/python -m uvicorn app.main:app --port 8001
# Dans un autre terminal :
curl -X POST http://127.0.0.1:8001/train
```

# Phase 0 — Analyse et architecture

> Document validé le 2026-08-27. Sert de référence pour l'ensemble du développement
> et pour la rédaction du mémoire (chapitres contexte, conception, architecture).

## A. Architecture fonctionnelle

```
Gestion de référentiel          Suivi opérationnel           Analyse & Intelligence
─────────────────────           ───────────────────           ───────────────────────
Coopératives                    Campagnes agricoles           Tableau de bord (KPI)
Agriculteurs                    Cultures / cycles culturaux    Cartographie analytique
Parcelles (+ géométrie)         Récoltes                       Prévisions de production
Cultures & variétés             Intrants & utilisation          Comparaison de modèles ML
Utilisateurs & rôles                                            (extension future) Météo
```

Flux fonctionnel global :

1. Un **Administrateur** crée les comptes et les coopératives.
2. Un **Responsable de coopérative** enregistre ses agriculteurs, leurs parcelles (géométrie
   incluse), les cultures pratiquées par campagne, puis les récoltes et intrants au fil du temps.
3. Ces données alimentent en continu le **tableau de bord** et la **carte**.
4. Les données historiques (récoltes, intrants, campagnes) sont envoyées, sur demande, au
   **service ML** pour entraînement et prédiction.
5. L'**Agriculteur** et l'**Analyste** consultent en lecture les données qui les concernent.

## B. Architecture technique

Voir [`docs/uml/architecture-technique.mmd`](../uml/architecture-technique.mmd).

Règles d'architecture :
- React ne parle **qu'à** Laravel.
- Laravel est **seul** à parler à PostgreSQL/PostGIS.
- Laravel est **seul** à parler à FastAPI (le frontend ne l'appelle jamais directement).
- FastAPI ne stocke pas de données métier persistantes propriétaires : il reçoit les données
  d'entraînement/prédiction depuis Laravel (payload JSON) ou lit un extrait exporté, et
  conserve seulement ses artefacts de modèles (`ai-service/models/`).

## C. Architecture logicielle (backend Laravel)

```
Requête HTTP
   → routes/api.php
   → Middleware (auth:sanctum, rôle)
   → Form Request (validation)
   → Controller (fin, orchestrateur uniquement)
   → Service (logique métier : calcul rendement, appel FastAPI, calcul superficie PostGIS)
   → Model Eloquent (+ Policy pour autorisation fine)
   → API Resource (formatage JSON de sortie)
   → Réponse HTTP normalisée
```

## D. Liste complète des modules

| Code | Module | Statut |
|---|---|---|
| A | Coopératives | Cœur |
| B | Agriculteurs | Cœur |
| C | Parcelles (PostGIS) | Cœur |
| D | Cultures | Cœur |
| E | Campagnes agricoles | Cœur |
| F | Récoltes | Cœur |
| G | Intrants | Cœur |
| H | Tableau de bord analytique | Cœur |
| I | Cartographie (Leaflet/OSM) | Cœur |
| J | Prévision de production | Avancé |
| K | Service IA/ML (FastAPI) | Avancé |
| L | Météo | Extension (non bloquante) |

## E. Liste des entités

`users`, `cooperatives`, `farmers`, `plots`, `crops`, `crop_varieties`,
`agricultural_campaigns`, `crop_cycles` (association parcelle × culture × campagne),
`harvests`, `inputs`, `input_usages`, `production_predictions`, `model_versions`,
`weather_observations` (placeholder, Module L).

**Justification des ajouts non explicitement listés dans le cahier des charges :**
- `crop_cycles` : nécessaire pour représenter "quelle culture est plantée sur quelle
  parcelle durant quelle campagne" avant même la récolte.
- `weather_observations` : table conceptuelle, faible priorité, pour ne pas devoir
  remodeler le schéma plus tard (Module L, non bloquant).

## F. Relations entre entités

- `users` (1) — (0..1) `farmers`
- `users` (1) — (0..1) `cooperatives` (responsable)
- `cooperatives` (1) — (N) `farmers`
- `cooperatives` (1) — (N) `plots`
- `farmers` (0..1) — (N) `plots`
- `crops` (1) — (N) `crop_varieties`
- `plots` (1) — (N) `crop_cycles`
- `crops` (1) — (N) `crop_cycles`
- `agricultural_campaigns` (1) — (N) `crop_cycles`
- `crop_varieties` (0..1) — (N) `crop_cycles`
- `crop_cycles` (1) — (N) `harvests`
- `inputs` (1) — (N) `input_usages`
- `plots` (1) — (N) `input_usages`
- `agricultural_campaigns` (1) — (N) `input_usages`
- `farmers` (0..1) — (N) `input_usages`
- `model_versions` (1) — (N) `production_predictions`

## G–L. Diagrammes

Voir les fichiers sources dans [`docs/uml/`](../uml/) :

- `use-case.puml` — Diagramme de cas d'utilisation
- `class-diagram.mmd` — Diagramme de classes
- `sequence-*.mmd` — Diagrammes de séquence (création parcelle, récolte, prédiction, auth)
- `activity-crop-cycle.puml` — Diagramme d'activités (cycle cultural)
- `erd.mmd` — ERD complet avec clés
- `mcd.md` — MCD (notation Merise)

## M. Stratégie PostGIS

- **SRID retenu : 4326 (WGS 84)** — cohérent GPS/OSM/Leaflet.
- `cooperatives.location` → `geometry(Point, 4326)`.
- `plots.geom` → `geometry(Polygon, 4326)` (architecture ouverte à `MultiPolygon` plus tard).
- Superficie via `ST_Area(geography(geom)) / 10000` (hectares, précision géographique correcte).
- Index spatiaux `GIST` sur `plots.geom` et `cooperatives.location`.
- Validation `ST_IsValid` avant insertion.
- Accès Laravel : requêtes brutes / package spatial — **choix du package à confirmer en Phase 2**.

## N. Stratégie API

- Préfixe `/api`, versionnement différé.
- CRUD REST standard par ressource (suppression restreinte sur `harvests`/`input_usages`
  pour traçabilité).
- Pagination native Laravel, réponses enveloppées `{data, meta, links}`.
- Filtrage/recherche/tri via query params normalisés.
- Erreurs uniformes `{message, errors}` + codes HTTP standards.
- Documentation : collection Postman dans `docs/api/` (Phase 4).

## O. Stratégie Machine Learning

- Service FastAPI indépendant, aucun accès direct à PostgreSQL.
- Pipeline : nettoyage → EDA → sélection de variables → split train/test → entraînement
  (Linear Regression, Random Forest, Gradient Boosting) → évaluation (MAE, RMSE, R²) →
  sélection objective → sauvegarde (`joblib`) → `/predict`.
- Données de démarrage synthétiques, explicitement étiquetées `is_synthetic: true`.
- Traçabilité via `model_versions` (`is_active` = modèle en production).
- **Compatibilité Python** : machine sous Python 3.14.5 — compatibilité scikit-learn/pandas/
  NumPy à vérifier réellement en Phase 8/9 avant tout code ML (pas de supposition).

## P. Stratégie de tests

| Couche | Types | Exemples |
|---|---|---|
| Backend Laravel | Feature tests | CRUD par ressource, codes HTTP, validation, autorisations |
| Backend Laravel | Unit tests | Services (superficie, rendement, PredictionService mocké) |
| Frontend React | Composants | Formulaires, tableaux, carte |
| Frontend React | Services | Wrapper Axios (mock) |
| FastAPI | Endpoints | `/health`, `/predict`, `/train`, `/models` |
| FastAPI | Pipeline ML | Nettoyage, split, entraînement, non-régression des métriques |
| API globale | Manuel | Collection Postman documentée |

## Q. Structure finale des dossiers

Voir racine du dépôt.

## R. Plan complet des phases

| Phase | Contenu | Statut |
|---|---|---|
| 0 | Analyse & architecture | **Validée** (2026-08-27) |
| 1 | Initialisation des 3 projets + Git | **Validée** |
| 2 | Base de données, migrations, PostGIS | **Validée** |
| 3 | Backend Laravel | **Validée** |
| 4 | Tests API + Postman | **Validée** |
| 5 | Frontend | **Validée** |
| 6 | Cartographie Leaflet/OSM | **Validée** |
| 7 | Tableau de bord analytique | **Validée** |
| 8 | Service FastAPI (squelette) | **Validée** |
| 9 | Machine Learning (pipeline complet) | **Validée** |
| 10 | Intégration IA | **Validée** |
| 11 | Extension météo | **Validée** |
| 12 | Tests globaux | **Validée** |
| 13 | Optimisation & sécurité | **Validée** |
| 14 | Documentation finale | **Validée** |
| 15 | Préparation du mémoire | **En cours** |

## Décision validée — Authentification

**Laravel Sanctum, mode SPA (cookie httpOnly + CSRF)**, avec un champ `role` simple sur
`users` au départ (évolutif vers `spatie/laravel-permission` si besoin futur).

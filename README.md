# Agriculture Mada

Plateforme intelligente de gestion agricole pour les coopératives agricoles à Madagascar.

Projet réalisé dans le cadre d'un mémoire de Master 2 — *« Plateforme intelligente de gestion
agricole pour les coopératives agricoles à Madagascar »*.

**État du projet : Phases 0 à 14 terminées.** Les trois applications (backend, frontend,
service IA) sont fonctionnelles de bout en bout et testées automatiquement (121 tests :
80 backend, 23 frontend, 18 service IA, + une collection Postman pour les vérifications
manuelles).

## Contexte et problématique

L'agriculture représente un secteur économique et social majeur à Madagascar. De nombreuses
coopératives agricoles disposent encore de moyens limités pour centraliser, structurer,
analyser et exploiter leurs données (agriculteurs, parcelles, cultures, récoltes, intrants).
Cette plateforme centralise ces données, les géolocalise (PostGIS/Leaflet), les analyse
(tableau de bord) et prépare — avec un premier pipeline fonctionnel — des prévisions de
production via un service de Machine Learning indépendant.

Analyse complète (besoins, contraintes, utilisateurs) : [`docs/architecture/phase0-analyse.md`](docs/architecture/phase0-analyse.md).

## Architecture générale

```
React + TypeScript (Vite)  →  Laravel 13 REST API  →  PostgreSQL 18.6 + PostGIS 3.6.2
                                       ↓
                              FastAPI (Python) + scikit-learn
```

- Le frontend ne communique **qu'avec** l'API Laravel — jamais directement avec PostgreSQL.
- Laravel est seul à accéder à PostgreSQL/PostGIS.
- Laravel est seul à appeler le service FastAPI ; le service IA ne connaît pas la base
  PostgreSQL, il reçoit ses données par HTTP.

Détail complet (architecture fonctionnelle/technique/logicielle, diagrammes de flux) :
[`docs/architecture/phase0-analyse.md`](docs/architecture/phase0-analyse.md) (sections A à C).

### UML et modèle de données

| Diagramme | Fichier |
|---|---|
| Cas d'utilisation | `docs/uml/use-case.puml` |
| Classes | `docs/uml/class-diagram.mmd` |
| Séquence — authentification | `docs/uml/sequence-auth.mmd` |
| Séquence — création de parcelle | `docs/uml/sequence-create-plot.mmd` |
| Séquence — enregistrement d'une récolte | `docs/uml/sequence-record-harvest.mmd` |
| Séquence — prédiction ML | `docs/uml/sequence-prediction.mmd` |
| Activité — cycle cultural | `docs/uml/activity-crop-cycle.puml` |
| Architecture technique | `docs/uml/architecture-technique.mmd` |
| MCD / ERD | `docs/database/mcd.md`, `docs/uml/erd.mmd` |

## Structure du repository

```
agriculture-mada/
├── backend/         Laravel 13 (API REST, PHP 8.5.7)         — voir backend/README.md
├── frontend/        React + TypeScript + Vite                — voir frontend/README.md
├── ai-service/      FastAPI + scikit-learn (Python)           — voir ai-service/README.md
├── docs/
│   ├── architecture/    Analyse fonctionnelle/technique (Phase 0)
│   ├── uml/              Sources des diagrammes (Mermaid/PlantUML)
│   ├── database/         MCD, ERD, stratégie PostGIS
│   ├── api/               Spécification API + collection Postman
│   ├── machine-learning/  Pipeline ML, méthode, résultats réels
│   └── deployment.md      Guide de mise en production
├── README.md
└── .gitignore
```

## Stack technique

| Composant | Technologie |
|---|---|
| Frontend | React 19, TypeScript, Vite, React Router, Axios, **Tailwind CSS v4**, Leaflet, Recharts, Vitest, React Testing Library |
| Backend | Laravel 13, PHP 8.5.7, Sanctum, matanyadaev/laravel-eloquent-spatial |
| Base de données | PostgreSQL 18.6 + PostGIS 3.6.2 |
| Service IA | Python 3.14.5, FastAPI, pandas, NumPy, scikit-learn |
| Météo (extension) | Open-Meteo (API publique réelle, sans clé) — voir Module L |
| Outils | Git, Postman, pgAdmin, Laravel Pint, oxlint |

**Migration Tailwind CSS (audit de professionnalisation)** : le frontend utilisait initialement
du CSS classique (contrainte du cahier des charges d'origine) ; il a été migré vers
**Tailwind CSS v4** (config CSS-first via `@tailwindcss/vite`, pas de `tailwind.config.js`
obsolète) sur demande explicite d'un audit ultérieur. La migration est **progressive** : les
composants réutilisables (`Button`, `Badge`, `Alert`, layout, pages détaillées) sont en
Tailwind ; certaines pages plus anciennes utilisent encore les classes CSS historiques
(`src/styles/globals.css`, primitives `.stack`/`.card`/`.row`...), retirées au fur et à mesure
de leur migration plutôt qu'en un seul bloc (risque de régression). Les deux systèmes
cohabitent sans conflit (tokens de couleur alignés, voir `src/styles/tailwind-theme.css`).

## Installation rapide

Chaque sous-projet a son propre guide détaillé :

- [`backend/README.md`](backend/README.md) — API Laravel (installation, config, tests, sécurité)
- [`frontend/README.md`](frontend/README.md) — SPA React (installation, config, tests)
- [`ai-service/README.md`](ai-service/README.md) — Service ML (venv, compatibilité Python, endpoints)
- [`docs/deployment.md`](docs/deployment.md) — Mise en production des trois applications

Ordre de démarrage recommandé en développement :

```powershell
# 1. Backend (http://localhost:8000)
cd backend; composer install; copy .env.example .env; php artisan key:generate; php artisan migrate; php artisan serve

# 2. Service IA (http://127.0.0.1:8001) — optionnel pour tester les prédictions
cd ai-service; python -m venv venv; ./venv/Scripts/pip install -r requirements-dev.txt
./venv/Scripts/python -m uvicorn app.main:app --reload --port 8001

# 3. Frontend (http://localhost:5173 — jamais 127.0.0.1, voir frontend/README.md)
cd frontend; npm install; copy .env.example .env; npm run dev
```

## Fonctionnalités

| Module | Description |
|---|---|
| A — Coopératives | CRUD, localisation (PostGIS Point), responsable, statistiques |
| B — Agriculteurs | CRUD, rattachement à une coopérative |
| C — Parcelles | CRUD, géométrie PostGIS (Polygon), superficie calculée (jamais saisie) |
| D — Cultures | Référentiel cultures + variétés |
| E — Campagnes agricoles | Suivi temporel (année/saison) |
| F — Récoltes | Rendement calculé (quantité/superficie) ou déclaré |
| G — Intrants | Référentiel + suivi de consommation par parcelle/campagne |
| H — Tableau de bord | KPI, graphiques, tableaux, carte analytique — 100 % API réelle |
| I — Cartographie | Leaflet + OpenStreetMap, parcelles et coopératives réelles |
| J/K — Prévision & IA | Service FastAPI indépendant, pipeline ML complet, intégration Laravel |
| L — Météo | Extension non bloquante, saisie manuelle, alimente les prédictions si disponible |

## API

REST, authentification Sanctum SPA (cookie httpOnly + CSRF), ~60 endpoints, pagination/
filtrage/recherche, autorisation par rôle (admin, cooperative_manager, farmer, analyst).
Documentation : [`docs/api/README.md`](docs/api/README.md) + collection Postman.

## Base de données et PostGIS

17 tables normalisées (voir [`docs/database/mcd.md`](docs/database/mcd.md)). Géométries
réelles (`Point` pour les sièges de coopératives, `Polygon` pour les parcelles, SRID 4326),
index spatiaux GIST, superficie calculée par `ST_Area(geography(geom))` — jamais saisie
manuellement. Architecture ouverte à `MultiPolygon` si nécessaire (colonne dédiée, migration
additive, pas de refonte).

## Machine Learning

Pipeline complet (données → nettoyage → préparation → sélection de variables → entraînement
→ comparaison → évaluation → sauvegarde → prédiction), documenté avec ses **résultats réels**
dans [`docs/machine-learning/phase9-pipeline.md`](docs/machine-learning/phase9-pipeline.md).

Point important : **le modèle est entraîné sur des données synthétiques**, clairement
identifiées comme telles à chaque niveau (génération documentée, `is_experimental: true` dans
chaque réponse API, bandeau visible dans l'interface). Trois algorithmes comparés (régression
linéaire, Random Forest, Gradient Boosting) ; la régression linéaire l'emporte sur ce jeu de
données (R² = 0,538) — résultat conservé tel quel, non retouché pour favoriser un modèle plus
complexe.

## Tests

| Application | Commande | Résultat |
|---|---|---|
| Backend | `cd backend && vendor/bin/phpunit` | 80 tests |
| Frontend | `cd frontend && npm run test` | 23 tests |
| Service IA | `cd ai-service && ./venv/Scripts/python -m pytest` | 18 tests |
| API (manuel) | Collection Postman | `docs/api/agriculture-mada.postman_collection.json` |

Tests d'intégration complète (backend + service IA + frontend démarrés ensemble, parcours
utilisateur réel de bout en bout) vérifiés à la Phase 12.

## Rédaction du mémoire

[`docs/memoire/`](docs/memoire/) rassemble, en registre académique et prêt à être repris,
l'ensemble des éléments techniques du mémoire (présentation du système, conception,
architecture, choix technologiques, méthodologie, implémentation, tests et résultats — y
compris les résultats réels du Machine Learning —, limites et perspectives).

## Sécurité

Voir `backend/README.md` (section Sécurité) pour la checklist complète. Points notables
corrigés en Phase 13 : limitation de débit sur la connexion (anti bourrage d'identifiants),
plafonnement de la pagination, correction d'une faille XSS stockée (rendu de popups carte).

## Limites connues

- **Données agricoles synthétiques** pour le Machine Learning : aucune donnée réelle de
  Madagascar n'était disponible pendant le développement (cahier des charges §16). Le pipeline
  est conçu pour accueillir des données réelles sans changement d'architecture
  (`load_training_dataset()` dans `ai-service/`).
- **Un seul modèle de culture** (rendement du riz) et cinq variables ; l'architecture
  (`app/ml/features.py`) permet d'en ajouter sans réécrire le pipeline.
- **Météo** : extension non bloquante par saisie manuelle uniquement — aucun fournisseur
  météo externe réel n'est intégré (aucun accès/clé API spécifié pour ce mémoire).
- **Variables de prédiction non entièrement automatisables** : `rainfall_mm` (si aucune
  observation météo n'existe pour la période) et `variety_class` (le catalogue de variétés en
  texte libre n'est pas mappé aux deux classes du modèle) restent à saisie manuelle.
- **Pas de gestion d'utilisateurs en interface** : la création de comptes (admin,
  responsables de coopérative) se fait actuellement via `php artisan tinker`, pas d'écran
  dédié.
- **Pas de tests end-to-end navigateur** (Playwright/Cypress) : la couverture frontend
  (Vitest + RTL) teste unités/composants/services, pas de parcours dans un vrai navigateur.
- **Aucune haute disponibilité** : guide de déploiement mono-serveur (voir
  `docs/deployment.md`), pas d'orchestration ni de sauvegarde automatisée documentées.
- **Niveau de confiance des prédictions non affiché** : volontairement — son calcul rigoureux
  (intervalle de prédiction) n'est pas implémenté, et le cahier des charges interdit
  d'afficher un chiffre non statistiquement justifié.

## Perspectives

- **Données réelles** : remplacer le jeu de données synthétique par des données agricoles
  réelles (partenariat coopérative/institut agricole), ré-entraîner sans changer le pipeline.
- **Multi-cultures** : étendre `app/ml/` à d'autres cultures que le riz (maïs, manioc...),
  chacune avec son propre modèle sauvegardé dans le registre existant.
- **Météo réelle** : brancher un fournisseur (ex. données satellite/station Météo Madagascar)
  derrière l'architecture déjà prévue (`weather_observations`, Module L).
- **Gestion des utilisateurs** : écran d'administration des comptes et rôles (actuellement
  via console uniquement).
- **Dessin de géométrie sur carte** : actuellement les parcelles sont saisies en coordonnées
  JSON ; un outil de dessin interactif (Leaflet.draw) est une extension naturelle du module
  Cartographie.
- **Notifications** : alertes sur seuils (rendement anormal, campagne à échéance).
- **Tests end-to-end navigateur** et **CI/CD** (intégration continue automatisant les trois
  suites de tests à chaque changement).
- **Haute disponibilité / observabilité** : conteneurisation, sauvegardes automatisées,
  supervision applicative, pour un déploiement réel au-delà du périmètre académique.

## Auteur

Mémoire de Master 2 — Plateforme intelligente de gestion agricole (Madagascar).

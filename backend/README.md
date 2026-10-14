# Backend — Agriculture Mada API

API REST Laravel 13 (PHP 8.5.7) pour la plateforme intelligente de gestion agricole des
coopératives à Madagascar. Seule application autorisée à parler à PostgreSQL/PostGIS ; seule
application autorisée à appeler le service IA (FastAPI).

**État : fonctionnel de bout en bout** — 17 tables, ~65 endpoints REST, autorisation par rôle,
PostGIS réel, intégration Machine Learning, intégration météo externe réelle (Open-Meteo),
103 tests automatisés.

## Prérequis

- PHP 8.5.7 + Composer 2.10.2
- PostgreSQL 18.6 avec extension PostGIS 3.6.2, base `agriculture_mada` déjà créée
- Service IA (`ai-service/`) démarré si l'on veut tester les prédictions (Phase 10)

## Installation

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Renseigner `DB_PASSWORD` dans `.env` (ne jamais committer cette valeur), puis :

```powershell
php artisan migrate
```

## Configuration (`.env`)

| Variable | Rôle |
|---|---|
| `DB_*` | Connexion PostgreSQL/PostGIS |
| `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `FRONTEND_URLS` | Authentification SPA (voir section Authentification) — doivent correspondre exactement à l'origine du frontend |
| `AI_SERVICE_URL`, `AI_SERVICE_TIMEOUT` | URL du service FastAPI (Phase 10) |
| `WEATHER_PROVIDER_URL`, `WEATHER_PROVIDER_TIMEOUT`, `WEATHER_CACHE_TTL` | Fournisseur météo externe (Open-Meteo par défaut — API publique réelle, **aucune clé requise**) ; fournisseur configurable sans toucher au code (`App\Services\WeatherProviderService`) |

Voir aussi la section **Sécurité — checklist de déploiement** plus bas pour les variables à
changer en production.

## Authentification

**Laravel Sanctum, mode SPA** (cookie httpOnly + CSRF) — décision validée en Phase 0 (voir
`docs/architecture/phase0-analyse.md` et `docs/uml/sequence-auth.mmd`). Flux :

1. `GET /sanctum/csrf-cookie` — pose le cookie `XSRF-TOKEN`.
2. `POST /api/login` `{email, password}` — crée la session, limité à **5 tentatives/minute**
   par couple (email, IP) contre le bourrage d'identifiants (`AppServiceProvider::boot()`).
3. Requêtes suivantes : cookie de session envoyé automatiquement + en-tête `X-XSRF-TOKEN`.
4. `POST /api/logout`.

⚠️ Le frontend (et tout client HTTP de test) doit être servi via `localhost`, jamais
`127.0.0.1` — les cookies sont posés avec `Domain=localhost` et un navigateur ne les envoie
pas vers `127.0.0.1` même sur la même machine (constaté Phase 4).

## Lancer le serveur de développement

```powershell
php artisan serve
```

API disponible sur http://localhost:8000 — health check natif : `GET /up`.

## API

~65 endpoints REST couvrant les modules A à L (coopératives, agriculteurs, parcelles,
cultures/variétés, campagnes, cycles culturaux, récoltes, intrants, tableau de bord,
prédictions, météo — dont `GET /plots/{plot}/weather` : conditions réelles + prévision Open-Meteo
combinées aux observations manuelles). Pagination (`per_page`, borné à 100), filtrage et
recherche sur tous les endpoints de liste ; réponses `{data}` / `{data, links, meta}` ; erreurs
`422` (validation), `403` (autorisation), `404`, `409` (conflit d'intégrité référentielle),
`503` (service IA ou météo indisponible).

Documentation complète : [`docs/api/README.md`](../docs/api/README.md) + collection Postman
([`docs/api/agriculture-mada.postman_collection.json`](../docs/api/agriculture-mada.postman_collection.json)).

## Base de données et PostGIS

17 tables (voir `docs/database/mcd.md` pour le MCD/ERD complet). Points clés :

- **Géométries réelles** : `cooperatives.location` (`geometry(Point, 4326)`),
  `plots.geom` (`geometry(Polygon, 4326)`) — SRID 4326 (WGS 84), package
  `matanyadaev/laravel-eloquent-spatial`.
- **Superficie jamais saisie manuellement** : calculée par PostGIS
  (`ST_Area(geography(geom))/10000`, méthode géographique) dans `PlotService`.
- **Index spatiaux GIST** sur les deux colonnes géométriques (vérifiés après reconstruction
  complète du schéma, Phase 12).
- Toutes les clés étrangères indexées ; `model_versions.external_id` (Phase 10) fait le lien
  avec le registre de modèles du service IA.

Stratégie complète : `docs/architecture/phase0-analyse.md` (section M).

## Machine Learning (intégration)

Ce backend ne calcule jamais de prédiction lui-même : `App\Services\AiServiceClient` appelle
le service FastAPI (`POST /predict`, `GET /models/{id}`), `App\Services\PredictionService`
dérive des variables d'entrée à partir de données réelles (superficie PostGIS, intrants
enregistrés, observations météo) puis persiste le résultat (`production_predictions`,
`model_versions`). Voir `docs/machine-learning/phase9-pipeline.md` pour le pipeline complet.

## Tests

```powershell
vendor/bin/phpunit
```

**103 tests** (Feature) couvrant CRUD, autorisation par rôle, validation, PostGIS, intégration
IA et météo (`Http::fake()` — jamais d'appel réseau réel dans la suite), non-régression N+1
(comptage réel de requêtes SQL), limitation de débit.

> ⚠️ **Ne pas utiliser `php artisan test`** : cette commande boote Laravel avec `.env`
> (base de développement) avant que PHPUnit n'applique les surcharges `<php><env>` de
> `phpunit.xml`, donc `DB_DATABASE=agriculture_mada_test` est ignoré et `RefreshDatabase`
> vide silencieusement la base de développement à chaque exécution (constaté le
> 2026-08-28). `vendor/bin/phpunit` respecte correctement `phpunit.xml` et cible bien
> `agriculture_mada_test`.

Qualité de code : `vendor/bin/pint` (style PSR-12, appliqué sur tout le projet, Phase 13).

## Sécurité — checklist avant déploiement

Les valeurs par défaut de `.env.example` conviennent au développement local uniquement.
Avant toute mise en production, vérifier explicitement :

| Variable | Dev (actuel) | Production |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** — sinon les stack traces (chemins serveur, requêtes SQL) sont exposées dans les réponses d'erreur |
| `LOG_LEVEL` | `debug` | `error` ou `warning` |
| `SESSION_SECURE_COOKIE` | non défini | **`true`** dès que le site est servi en HTTPS (obligatoire, sinon le cookie de session peut être intercepté en clair) |
| `SANCTUM_STATEFUL_DOMAINS` / `FRONTEND_URLS` | `localhost:5173` | domaine(s) réel(s) du frontend déployé (jamais `*`, incompatible avec `supports_credentials`, voir `config/cors.php`) |
| `APP_KEY` | généré en local | régénérer avec `php artisan key:generate` sur l'environnement cible, ne jamais réutiliser la clé de dev |
| `DB_PASSWORD` | mot de passe de dev | identifiants de production dédiés, jamais commités |

Déjà en place, à ne pas retirer :
- Limitation de débit sur `POST /api/login` (5 tentatives/minute par couple email+IP).
- `per_page` borné à 100 sur tous les endpoints de liste (`Controller::perPage()`).
- Mass assignment protégé sur tous les modèles (attribut `#[Fillable]` explicite).
- CORS restreint à une origine explicite avec `supports_credentials: true` (jamais `*`).
- Construction DOM sans `innerHTML` côté frontend pour toute donnée saisie par l'utilisateur.

Voir aussi [`../docs/deployment.md`](../docs/deployment.md) pour le guide de déploiement complet
(les trois applications).
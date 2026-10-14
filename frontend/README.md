# Frontend — Agriculture Mada

Application React + TypeScript (Vite) pour la plateforme intelligente de gestion agricole.
Consomme exclusivement l'API REST Laravel (jamais d'accès direct à PostgreSQL).

**État : fonctionnel de bout en bout** — authentification complète (connexion, inscription, mot
de passe oublié/réinitialisation, vérification d'e-mail, profil), CRUD complet des 10 ressources
métier avec fiches détaillées à onglets (coopérative, agriculteur), cartographie Leaflet,
tableau de bord analytique (Recharts) professionnalisé, demande de prédiction ML avec page
pédagogique dédiée, module météo (fournisseur réel Open-Meteo + observations manuelles),
31 tests automatisés.

## Stack

React 19 · TypeScript · Vite · React Router · Axios · **Tailwind CSS v4** · Leaflet · Recharts ·
Vitest + React Testing Library.

Le CSS classique/modulaire d'origine (`src/styles/globals.css`) est en cours de migration
progressive vers Tailwind (composants réutilisables et pages principales déjà migrés ; voir
`src/styles/tailwind-theme.css` pour les tokens de design partagés entre les deux systèmes
pendant la transition).

## Installation

```powershell
cd frontend
npm install
copy .env.example .env
```

⚠️ Ouvrir l'application via `http://localhost:5173`, jamais `http://127.0.0.1:5173` : les
cookies d'authentification Sanctum sont posés avec `Domain=localhost` côté backend et un
navigateur ne les envoie pas vers `127.0.0.1`, même sur la même machine (voir
`.env.example` et `backend/README.md`).

## Configuration (`.env`)

| Variable | Rôle |
|---|---|
| `VITE_API_BASE_URL` | URL de base de l'API Laravel (`http://localhost:8000`) |
| `VITE_MAP_DEFAULT_LAT/LNG/ZOOM` | Centrage par défaut de la carte (Madagascar) |

## Lancer en développement

```powershell
npm run dev
```

Application sur http://localhost:5173. Le backend (`php artisan serve`) doit tourner sur
`http://localhost:8000`, et le service IA (`ai-service/`) sur `http://127.0.0.1:8001` si l'on
veut tester les prédictions.

## Fonctionnalités (par module)

| Module | Pages | Rôles pouvant modifier |
|---|---|---|
| Authentification | Connexion, session persistante | tous |
| Tableau de bord | KPI, graphiques, carte analytique, filtres | lecture : tous |
| Coopératives | Liste, création, fiche, édition | admin |
| Agriculteurs | Liste, création, édition | admin, cooperative_manager |
| Parcelles | Liste, création (géométrie GeoJSON), édition | admin, cooperative_manager |
| Carte | Parcelles + sièges de coopératives réels, filtres | lecture : tous |
| Cultures & variétés | Référentiel | admin |
| Campagnes agricoles | Liste, création, édition | admin |
| Cycles culturaux | Liste, création, édition | admin, cooperative_manager |
| Récoltes | Liste, création (rendement calculé/déclaré), édition | admin, cooperative_manager |
| Intrants & utilisations | Référentiel + suivi de consommation | admin, cooperative_manager |
| Prévisions | Demande de prédiction ML, historique | admin, cooperative_manager |
| Météo (extension) | Saisie manuelle d'observations | admin, cooperative_manager |

Les boutons créer/modifier/supprimer sont masqués selon le rôle connecté (miroir des Policies
backend) ; le backend reste l'autorité finale sur l'autorisation.

## Structure

```
src/
├── components/         Composants UI réutilisables (Button, DataTable, Pagination, ...)
├── pages/               Pages hors layout applicatif (LoginPage)
├── layouts/             AppLayout (nav + topbar), AuthLayout
├── services/            Client Axios centralisé (http.ts), fabrique CRUD générique (resource.ts)
├── hooks/               useAuth, useResourceList, useOptions, useDebouncedValue
├── types/               Types TypeScript miroir des API Resources Laravel
├── features/            Un dossier par module métier (liste ci-dessus)
├── styles/              Design system CSS (variables, primitives)
└── test/                Configuration Vitest (setup.ts)
```

## Build

```powershell
npm run build
```

Le bundle initial est optimisé par code-splitting (`React.lazy`) : les pages Tableau de bord
(Recharts) et Carte (Leaflet) — qui représentaient à elles seules la majorité du poids du
bundle — ne sont téléchargées qu'à la navigation vers ces pages (**903 Ko → 353 Ko** sur le
chargement initial, Phase 13).

## Tests

```powershell
npm run test
```

23 tests (Vitest + React Testing Library) : normalisation des erreurs API, debounce de
recherche, composants (`Button`, `Badge`, `DataTable`, `ProtectedRoute`), parcours critique de
connexion (succès/échec).

## Lint

```powershell
npm run lint
```

`oxlint` — 0 erreur (3 avertissements stylistiques connus et documentés dans le code).

## Sécurité

Aucune donnée saisie par l'utilisateur n'est injectée via `innerHTML` (risque XSS stocké
identifié et corrigé Phase 13, voir `features/map/MapPage.tsx`) : toute valeur dynamique passe
par `textContent`/`createTextNode` ou le rendu React standard (échappement automatique).

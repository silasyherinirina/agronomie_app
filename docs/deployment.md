# Déploiement

Guide de mise en production des trois applications. Le développement local (documenté dans
chaque `README.md`) utilise `php artisan serve`, `npm run dev` et `uvicorn --reload` — **aucun
de ces trois ne convient à la production**.

## Vue d'ensemble

```
Utilisateur (HTTPS)
      │
      ▼
Serveur web (nginx/Apache) — sert les fichiers statiques du frontend (npm run build)
      │  reverse-proxy /api/* et /sanctum/*
      ▼
PHP-FPM — Laravel (backend/)
      │
      ├──▶ PostgreSQL 18.6 + PostGIS 3.6.2
      │
      └──▶ Service IA (FastAPI, processus séparé derrière son propre reverse-proxy interne)
```

Les trois applications sont déployées **indépendamment** (conformément à l'architecture
validée Phase 0) : le frontend est un ensemble de fichiers statiques, le backend un processus
PHP-FPM, le service IA un processus Python persistant (pas de CGI/serverless, le modèle
entraîné doit rester chargé en mémoire entre les requêtes).

## 1. Base de données

```bash
# Sur le serveur PostgreSQL cible :
createdb agriculture_mada
psql -d agriculture_mada -c "CREATE EXTENSION IF NOT EXISTS postgis;"
```

Puis, depuis le serveur d'application :

```bash
cd backend
php artisan migrate --force
```

## 2. Backend (Laravel)

```bash
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env   # puis renseigner les valeurs de production (voir checklist ci-dessous)
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

Servir via PHP-FPM + nginx (ou Apache+mod_php), racine `backend/public/`. Ne jamais exposer
`php artisan serve` en production (serveur de développement mono-thread, sans durcissement).

**Checklist sécurité obligatoire** (détaillée dans `backend/README.md`) :
`APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`, `SESSION_SECURE_COOKIE=true`
(HTTPS obligatoire), `SANCTUM_STATEFUL_DOMAINS`/`FRONTEND_URLS` pointant vers le domaine réel
du frontend déployé, `APP_KEY` régénérée sur cet environnement, `AI_SERVICE_URL` pointant vers
le service IA déployé.

## 3. Frontend (React)

```bash
cd frontend
cp .env.example .env   # VITE_API_BASE_URL = URL publique du backend déployé
npm install
npm run build           # génère dist/
```

`dist/` est un ensemble de fichiers statiques : les servir directement via nginx (ou tout CDN),
avec une règle de fallback SPA (`try_files $uri /index.html;`) pour que les routes React
Router (`/plots`, `/dashboard`, ...) fonctionnent au rechargement de page.

## 4. Service IA (FastAPI)

```bash
cd ai-service
python -m venv venv
./venv/Scripts/pip install -r requirements.txt   # requirements.txt seul (sans -dev) en production
```

Lancer via un serveur ASGI de production, pas `--reload` :

```bash
./venv/Scripts/python -m uvicorn app.main:app --host 0.0.0.0 --port 8001 --workers 2
```

⚠️ Avec plusieurs `--workers`, chaque processus a son propre registre de modèles en mémoire au
démarrage mais relit `models/<id>/metadata.json` à chaque requête (`ModelRegistryService` ne
met rien en cache entre requêtes) — un entraînement (`POST /train`) déclenché sur un worker est
donc immédiatement visible par tous les autres à la requête suivante, sans redémarrage.

Entraîner au moins une fois avant la mise en service (`/predict` renvoie `503` tant qu'aucun
modèle n'est actif) :

```bash
curl -X POST http://127.0.0.1:8001/train
```

## 5. Vérification post-déploiement

1. `GET /up` (backend) → 200.
2. `GET /health` (service IA) → `{"status":"ok"}`.
3. `GET /sanctum/csrf-cookie` puis `POST /api/login` depuis le domaine frontend réel → cookie
   posé, pas d'erreur CORS dans la console navigateur.
4. `POST /api/predictions` (en tant qu'admin/cooperative_manager) → `201`, pas `503`.

## Limites connues de ce guide

Ce projet est un mémoire de Master 2 : le guide ci-dessus couvre un déploiement mono-serveur
simple. Il ne couvre pas : la haute disponibilité, l'orchestration (Docker/Kubernetes), les
sauvegardes automatisées de PostgreSQL, ni la supervision (monitoring/alerting) — hors périmètre
académique, mais des pistes réalistes pour une mise en production réelle (voir README racine,
section Perspectives).

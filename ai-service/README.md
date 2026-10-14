# AI Service — Agriculture Mada

Service Machine Learning indépendant (FastAPI) pour la prévision de production
agricole. Ne communique jamais directement avec PostgreSQL : il reçoit ses données
depuis l'API Laravel.

> **Service complet et intégré (Phases 8-10).** `/train` entraîne et compare 3 modèles
> sur un jeu de données **synthétique** (voir `docs/machine-learning/phase9-pipeline.md`),
> `/predict` utilise le modèle actif. Intégré à Laravel via `AiServiceClient` (Phase 10) :
> le frontend React déclenche des prédictions réelles depuis la page "Prévisions", stockées
> dans `production_predictions`.

## Prérequis

- Python (voir note de compatibilité ci-dessous)

## Note de compatibilité Python

La machine de développement a **Python 3.14.5** installé. FastAPI/uvicorn
fonctionnent correctement dessus (vérifié). Des `DeprecationWarning` liées à
`asyncio.iscoroutinefunction` apparaissent (dépréciation prévue pour Python 3.16),
sans impact fonctionnel actuel.

**Mise à jour Phase 8 — vérifié avant d'écrire le moindre code Machine Learning (§26)** :
compatibilité confirmée pour Python 3.14 via les métadonnées PyPI (wheels `cp314`
publiées) et une installation réelle dans le venv de ce service :

| Paquet | Version vérifiée | Wheel cp314 |
|---|---|---|
| numpy | 2.5.2 | ✅ |
| pandas | 3.0.5 | ✅ |
| scikit-learn | 1.9.0 | ✅ |
| pydantic-core (dépendance FastAPI) | 2.46.4/2.46.5 | ✅ (installé avec succès) |
| pydantic-settings | 2.15.0 | ✅ (pur Python) |

Aucun second environnement Python n'a été nécessaire. Ces versions sont épinglées dans
`requirements.txt` depuis la Phase 9.

## Installation

```powershell
cd ai-service
python -m venv venv
./venv/Scripts/pip install -r requirements-dev.txt
```

## Lancer le service en développement

```powershell
./venv/Scripts/python -m uvicorn app.main:app --reload --host 127.0.0.1 --port 8001
```

Vérifier : http://127.0.0.1:8001/health

## Lancer les tests

```powershell
./venv/Scripts/python -m pytest -q
```

## Structure

```
ai-service/
├── app/
│   ├── main.py          Point d'entrée FastAPI (bootstrap + inclusion du routeur)
│   ├── api/
│   │   ├── router.py     Agrège tous les sous-routeurs
│   │   ├── health.py     GET /health (réel)
│   │   ├── models.py     GET /models, GET /models/{id} (réel — registre de fichiers)
│   │   ├── predictions.py POST /predict (réel — charge le modèle actif)
│   │   └── training.py   POST /train (réel — entraîne, compare, sauvegarde)
│   ├── services/
│   │   ├── model_registry.py     Lit/écrit models/<id>/metadata.json + model.joblib
│   │   ├── training_service.py   Orchestration du pipeline d'entraînement
│   │   └── prediction_service.py Charge le modèle actif, calcule les variables importantes
│   ├── schemas/          Modèles Pydantic (health, model, prediction, training)
│   ├── ml/               Pipeline scikit-learn : données synthétiques, features, modèles, évaluation
│   ├── utils/
│   └── config/           Configuration typée (settings.py, pydantic-settings)
├── models/               <id>/metadata.json + model.joblib — non versionné (Phase 9)
├── data/                 Données d'entraînement — non versionnées
├── notebooks/            Notebooks d'exploration (Phase 9)
├── tests/
├── requirements.txt       Dépendances de production
├── requirements-dev.txt   + dépendances de test
└── .env.example
```

## Endpoints disponibles

| Méthode | Route | État |
|---|---|---|
| GET | `/health` | ✅ fonctionnel |
| GET | `/models` | ✅ fonctionnel (registre de fichiers, `models/<id>/`) |
| GET | `/models/{id}` | ✅ fonctionnel (404 si inconnu) |
| POST | `/train` | ✅ fonctionnel — entraîne et compare 3 modèles, sauvegarde le meilleur |
| POST | `/predict` | ✅ fonctionnel si un modèle est actif, sinon `503` explicite |

Voir `docs/machine-learning/phase9-pipeline.md` pour la méthode, les variables, les
hyperparamètres et les résultats réels obtenus.

## Avertissement — données synthétiques

Le modèle actuel est entraîné sur des données **entièrement synthétiques**
(`app/ml/synthetic_data.py`) : aucune prédiction de `/predict` ne doit être présentée comme un
résultat agronomique réel (réponse marquée `is_experimental: true`).

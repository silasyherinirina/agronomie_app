# Chapitre 3 — Choix technologiques

La stack technique était en grande partie imposée par le cadre du mémoire ; ce chapitre en
justifie néanmoins chaque composant, ainsi que les décisions laissées ouvertes (authentification,
package spatial, architecture de test) qui ont fait l'objet d'un arbitrage documenté pendant le
développement.

## 3.1 Frontend — React, TypeScript, Vite

**React + TypeScript** apporte un typage statique qui, combiné aux types miroir des ressources
API (générés manuellement à partir des API Resources Laravel), réduit une classe entière
d'erreurs d'intégration frontend/backend (champ renommé, type incompatible) détectées à la
compilation plutôt qu'à l'exécution. **Vite** a été préféré à un bundler plus ancien
(Webpack/Create React App, aujourd'hui déconseillé par l'équipe React elle-même) pour son temps
de démarrage et de rechargement à chaud quasi instantanés en développement, ainsi que pour sa
prise en charge native de la séparation de code (`import()` dynamique), exploitée en Phase 13
pour réduire le poids du chargement initial de 61 %.

**Absence de framework CSS utilitaire (pas de Tailwind)** : contrainte du cahier des charges,
respectée par un design system CSS classique (variables CSS, primitives de mise en page
réutilisables) plutôt que par des classes utilitaires en ligne — choix cohérent avec l'objectif
de lisibilité pour un mémoire académique.

**Leaflet + OpenStreetMap** plutôt qu'une solution propriétaire (Google Maps, Mapbox) : aucune
clé d'API ni coût associé, fonds de carte librement réutilisables, et compatibilité directe avec
les géométries GeoJSON produites par PostGIS.

**Recharts** pour la visualisation de données : bibliothèque de composants React déclaratifs,
suffisante pour les graphiques du tableau de bord (barres, courbes) sans complexité
d'intégration d'une bibliothèque bas niveau (D3.js) hors du périmètre de ce mémoire.

## 3.2 Backend — Laravel 13

Laravel a été retenu pour la richesse de son écosystème directement pertinent pour ce projet :
Eloquent ORM (relations, mutateurs, évènements de modèle), Form Requests (validation
déclarative), API Resources (contrôle explicite du format de sortie JSON), Policies
(autorisation), et **Sanctum** pour l'authentification SPA — évitant de reconstruire des
mécanismes déjà éprouvés et testés par une communauté large, ce qui aurait détourné l'effort du
cœur du sujet (gestion agricole, PostGIS, Machine Learning).

### Choix documenté — package spatial Laravel/PostGIS

Le cahier des charges imposait PostGIS sans imposer de bibliothèque cliente Laravel. Deux
options ont été comparées avant décision (Phase 2) :

| Option | Avantages | Inconvénients |
|---|---|---|
| `matanyadaev/laravel-eloquent-spatial` *(retenu)* | Compatible Laravel 13 (vérifié par résolution Composer réelle), casts Eloquent natifs `Point`/`Polygon`, scopes spatiaux prêts à l'emploi, API simple | — |
| `clickbar/laravel-magellan` | Plus complet sur la gestion fine des SRID par colonne | API plus verbeuse, moins répandu, courbe d'apprentissage plus longue pour un projet académique |
| Requêtes brutes (`DB::raw`) | Aucune dépendance, contrôle total | Verbeux, pas de cast Eloquent automatique, répétition de code |

Le premier package a été retenu pour son équilibre entre simplicité d'usage et fonctionnalités
réellement utilisées par le projet (casts `Point`/`Polygon`, pas de besoin des fonctionnalités
avancées du second package).

### Choix documenté — authentification SPA

Trois approches étaient envisageables pour connecter un frontend React à une API Laravel :

| Option | Retenue | Justification |
|---|---|---|
| **Laravel Sanctum, mode SPA** (cookie httpOnly + CSRF) | ✅ | Solution officielle Laravel pour ce cas d'usage exact (SPA de première partie) ; cookie httpOnly inaccessible en JavaScript (protection contre le vol de session par XSS) ; pas de gestion manuelle de stockage de jeton côté client |
| Jeton porteur (Sanctum en mode API token / JWT) | ✗ | Pertinent pour une API consommée par des clients tiers ou mobiles, pas pour un frontend de première partie ; nécessite un stockage client (localStorage) plus exposé aux attaques XSS |
| Solution d'authentification maison | ✗ | Explicitement écarté par le cahier des charges (« ne pas inventer une solution maison ») |

## 3.3 Base de données — PostgreSQL 18.6 + PostGIS 3.6.2

Imposé par le cahier des charges pour son support natif et mature des types géométriques
(PostGIS), indispensable à la géolocalisation réelle des parcelles — une base non géographique
(MySQL, SQLite) aurait nécessité soit une extension tierce moins éprouvée, soit un stockage des
coordonnées en texte/JSON sans les capacités de calcul géométrique natif (`ST_Area`,
`ST_Contains`...) utilisées pour le calcul de superficie.

## 3.4 Service IA — Python, FastAPI, scikit-learn

**FastAPI** a été préféré à Flask/Django pour sa validation de schéma native (Pydantic),
directement alignée avec le besoin de contrats d'API stricts entre le service IA et Laravel, et
pour sa documentation interactive générée automatiquement (utile en développement et pour la
soutenance). **scikit-learn** reste la référence pour un pipeline de Machine Learning classique
(régression) à l'échelle d'un mémoire M2, avec une API homogène entre algorithmes (`fit`/
`predict`) qui a permis de comparer trois modèles (régression linéaire, Random Forest, Gradient
Boosting) sans dupliquer de code d'entraînement ou d'évaluation.

### Vérification de compatibilité Python (démarche, pas supposition)

La machine de développement disposait de Python 3.14.5 — une version très récente au moment du
projet. Conformément à la règle du cahier des charges interdisant toute supposition de
compatibilité, chaque bibliothèque a été vérifiée **avant** d'écrire le moindre code de Machine
Learning, par deux méthodes complémentaires : consultation des métadonnées PyPI (disponibilité
de wheels compilées pour cette version de Python) puis installation réelle dans l'environnement
virtuel du service. Résultat : NumPy 2.5.2, pandas 3.0.5, scikit-learn 1.9.0 et les dépendances
de FastAPI (dont `pydantic-core`, qui contient du code compilé) se sont toutes installées sans
erreur ni compilation depuis les sources, validant l'absence de besoin d'un second environnement
Python à version différente.

## 3.5 Outils

Git/GitHub pour le versionnement, Postman pour la documentation et les tests manuels de l'API
(collection fournie dans `docs/api/`), pgAdmin pour l'administration de la base de données,
Laravel Pint et `oxlint` pour l'homogénéité du style de code (respectivement backend et
frontend), Vitest et React Testing Library pour les tests frontend, PHPUnit pour les tests
backend, `pytest` pour les tests du service IA.

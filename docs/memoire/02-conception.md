# Chapitre 2 — Conception

## 2.1 Analyse des besoins

### 2.1.1 Besoins fonctionnels

Les besoins fonctionnels ont été regroupés en douze modules (notés A à L), correspondant
chacun à un domaine métier cohérent et à un ensemble de ressources de l'API :

| Module | Domaine | Portée |
|---|---|---|
| A | Coopératives | Cœur |
| B | Agriculteurs | Cœur |
| C | Parcelles (géométrie PostGIS) | Cœur |
| D | Cultures et variétés | Cœur |
| E | Campagnes agricoles | Cœur |
| F | Récoltes | Cœur |
| G | Intrants | Cœur |
| H | Tableau de bord analytique | Cœur |
| I | Cartographie | Cœur |
| J | Prévision de production | Avancé |
| K | Service IA / Machine Learning | Avancé |
| L | Météo | Extension non bloquante |

La distinction entre modules « cœur » et « avancés »/« extension » a structuré l'ordre de
développement (voir chapitre Méthodologie) : aucune fonctionnalité avancée n'a été commencée
avant la stabilisation des modules cœur, et le module Météo a été explicitement conçu pour ne
jamais conditionner le fonctionnement du reste de la plateforme.

### 2.1.2 Besoins non fonctionnels

- **Sécurité** : authentification, autorisation par rôle, protection contre les attaques
  courantes (injection, XSS, bourrage d'identifiants), validation systématique des entrées.
- **Performance** : réponses paginées et bornées, absence de requêtes N+1, chargement différé
  des ressources lourdes côté frontend.
- **Maintenabilité** : séparation des responsabilités (Controllers, Services, Form Requests,
  Resources, Policies côté backend ; composants/hooks/services réutilisables côté frontend).
- **Testabilité** : chaque couche doit pouvoir être testée automatiquement et de façon isolée.
- **Évolutivité** : le schéma de données et l'architecture des rôles doivent absorber des
  besoins futurs (nouvelles cultures, nouveaux rôles, nouvelles sources de données) sans
  refonte structurelle.
- **Honnêteté des données** : aucune valeur géographique, agricole ou statistique ne doit être
  inventée ou estimée silencieusement ; toute donnée dérivée (superficie, rendement calculé,
  prédiction) doit être calculée par une méthode explicite et documentée, ou explicitement
  signalée comme indisponible.

### 2.1.3 Contraintes

- Base de données strictement **PostgreSQL/PostGIS** (aucune base alternative type SQLite pour
  la persistance applicative, notamment en raison du besoin de types géométriques réels).
- Frontend sans framework CSS utilitaire (pas de Tailwind CSS), en CSS classique/modulaire.
- Service de Machine Learning **architecturalement indépendant** du backend (processus
  distinct, aucun accès direct à PostgreSQL).
- Compatibilité de l'environnement Python de développement (3.14.5) à vérifier explicitement
  avant tout code de Machine Learning, plutôt que supposée.

## 2.2 Architecture générale

```
                         Utilisateur
                              │
                              ▼
                 React + TypeScript (SPA, Vite)
                              │  HTTP/JSON (Axios)
                              ▼
                    Laravel 13 — API REST
                              │
                ┌─────────────┴─────────────┐
                ▼                           ▼
   PostgreSQL 18.6 + PostGIS 3.6.2      FastAPI (Python) + scikit-learn
```

Trois règles d'architecture, non négociables, ont structuré l'ensemble du développement :

1. Le frontend ne communique **qu'avec** l'API Laravel — jamais directement avec la base de
   données ni avec le service IA.
2. Laravel est **seul** à accéder à PostgreSQL/PostGIS.
3. Laravel est **seul** à appeler le service FastAPI ; celui-ci ne connaît pas la base de
   données relationnelle et reçoit ses données par requête HTTP.

Cette architecture en couches strictement séparées limite le couplage entre applications :
le service de Machine Learning peut évoluer (nouveaux modèles, nouvelles variables) sans
modification du frontend, et inversement.

## 2.3 Architecture logicielle du backend

```
Requête HTTP
   → routes/api.php
   → Middleware (authentification Sanctum, limitation de débit)
   → Form Request (validation des entrées)
   → Controller (orchestrateur, volontairement fin)
   → Service (logique métier : calcul de superficie PostGIS, stratégie de rendement,
              appel au service IA, agrégation du tableau de bord)
   → Modèle Eloquent (+ Policy pour l'autorisation fine par ressource)
   → API Resource (formatage JSON de sortie, contrôle explicite des champs exposés)
   → Réponse HTTP normalisée
```

Ce découpage répond à un principe simple mais structurant : **un Controller ne contient jamais
de logique métier** — il valide (délégué au Form Request), autorise (délégué à la Policy),
orchestre un ou plusieurs Services, puis formate la réponse (délégué à l'API Resource). Cette
discipline, appliquée uniformément aux dix ressources métier de la plateforme, a permis
d'ajouter des fonctionnalités transverses (limitation de la pagination, par exemple) en un
seul point de code (une méthode utilitaire sur la classe `Controller` de base) plutôt que de
dupliquer un correctif dans chaque contrôleur.

## 2.4 Diagrammes UML

Les diagrammes sources (Mermaid et PlantUML, non dupliqués ici) se trouvent dans
[`docs/uml/`](../uml/). Résumé de leur contenu :

- **Diagramme de cas d'utilisation** (`use-case.puml`) — formalise les interactions des quatre
  rôles avec les modules A à L.
- **Diagramme de classes** (`class-diagram.mmd`) — modélise les 13 entités métier et leurs
  relations (voir MCD ci-dessous pour le détail des cardinalités).
- **Diagrammes de séquence** (`sequence-*.mmd`) — quatre scénarios clés documentés :
  authentification (flux Sanctum SPA complet, cookie CSRF puis session), création d'une
  parcelle (validation géographique puis calcul PostGIS de la superficie), enregistrement
  d'une récolte (stratégie de rendement calculé/déclaré), demande de prédiction (chaîne
  Laravel → FastAPI → modèle → persistance).
- **Diagramme d'activités** (`activity-crop-cycle.puml`) — cycle de vie d'un cycle cultural,
  de la plantation à la récolte.

## 2.5 Modèle conceptuel de données (MCD) et modèle relationnel

Le MCD complet (notation Merise) est disponible dans [`docs/database/mcd.md`](../database/mcd.md)
et sa traduction en modèle relationnel avec clés primaires/étrangères dans
[`docs/uml/erd.mmd`](../uml/erd.mmd). Il comprend 13 entités métier (`UTILISATEUR`,
`COOPERATIVE`, `AGRICULTEUR`, `PARCELLE`, `CULTURE`, `VARIETE`, `CAMPAGNE`, `CYCLE_CULTURAL`,
`RECOLTE`, `INTRANT`, `UTILISATION_INTRANT`, `MODELE_ML`, `PREDICTION`), traduites en **17
tables** PostgreSQL (les tables techniques de Laravel — sessions, files d'attente, jetons
d'accès — s'ajoutant aux 13 tables métier).

Deux choix de modélisation méritent d'être justifiés :

- **`CYCLE_CULTURAL`** est une entité à part entière (et non une simple association) car elle
  porte des attributs propres (dates de plantation/récolte prévue, statut) et sert de pivot
  entre parcelle, culture, variété et campagne — une récolte est toujours rattachée à un cycle
  cultural, jamais directement à une parcelle, ce qui permet de suivre plusieurs cycles
  successifs sur une même parcelle au fil des campagnes.
- **`weather_observations`** (Module L) a été créée dès la conception (Phase 0), avant même
  d'être exploitée (Phase 11), pour éviter une modification de schéma ultérieure — conforme au
  principe d'évolutivité énoncé en 2.1.2.

## 2.6 Stratégie PostGIS

Deux colonnes géométriques réelles portent l'information géographique de la plateforme :

| Table | Colonne | Type PostGIS | Usage |
|---|---|---|---|
| `cooperatives` | `location` | `geometry(Point, 4326)` | Siège de la coopérative |
| `plots` | `geom` | `geometry(Polygon, 4326)` | Limites de la parcelle |

Le SRID **4326 (WGS 84)** a été retenu pour sa cohérence directe avec les coordonnées GPS et
les fonds de carte OpenStreetMap utilisés par Leaflet, évitant toute reprojection côté client.
La superficie d'une parcelle n'est **jamais saisie manuellement** : elle est calculée par
PostgreSQL/PostGIS lui-même, via `ST_Area(geography(geom)) / 10000` — une méthode géographique
(prenant en compte la courbure terrestre), et non planaire, indispensable pour obtenir une
valeur en hectares fiable à l'échelle de parcelles agricoles réelles. Des index spatiaux
`GIST` sont posés sur les deux colonnes géométriques, vérifiés après reconstruction complète du
schéma (voir chapitre Tests). L'architecture reste ouverte à une évolution vers `MultiPolygon`
(parcelles en plusieurs parties disjointes) par une migration additive, sans remodelage.

## 2.7 Stratégie d'authentification et d'autorisation

L'authentification retenue est **Laravel Sanctum en mode SPA** (cookie de session httpOnly +
jeton CSRF), plutôt qu'une authentification par jeton porteur (Bearer token/JWT). Ce choix est
justifié au chapitre 3 (Choix technologiques). L'autorisation fine par ressource repose sur les
**Policies** Laravel : chaque ressource (parcelle, récolte, intrant...) définit ses règles de
visibilité et de modification par rôle, avec un principe de récursivité en cascade pour
l'administrateur (accès total via une méthode `before()`) et un filtrage explicite du périmètre
de données pour le responsable de coopérative et l'agriculteur (jamais une confiance aveugle
dans un identifiant fourni par le client — la coopérative ou la parcelle d'appartenance sont
systématiquement revérifiées côté serveur).

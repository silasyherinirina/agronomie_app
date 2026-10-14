# Chapitre 5 — Implémentation

Ce chapitre met en avant les points d'implémentation les plus significatifs du point de vue
technique, module par module, plutôt que de reprendre exhaustivement le code (disponible dans
le dépôt). Les extraits cités sont réels, tirés du code source du projet.

## 5.1 Gestion des coopératives, agriculteurs, parcelles (Modules A-C)

Ces trois ressources suivent un schéma d'implémentation homogène (Form Request → Controller →
Service si nécessaire → API Resource) répliqué ensuite sur l'ensemble des dix ressources
métier. La particularité de **Parcelles** est le calcul de superficie, extrait dans un Service
dédié plutôt que laissé dans le contrôleur :

```php
public function calculateAreaHa(Plot $plot): float
{
    $result = DB::selectOne(
        'SELECT ST_Area(geography(geom)) / 10000 AS area_ha FROM plots WHERE id = ?',
        [$plot->id]
    );
    return round((float) $result->area_ha, 4);
}
```

Ce calcul est déclenché automatiquement à la création et à toute modification de la géométrie
d'une parcelle — l'utilisateur ne peut jamais saisir manuellement une superficie.

## 5.2 Récoltes — stratégie de rendement (Module F)

Le rendement d'une récolte peut être **calculé** (quantité récoltée divisée par la superficie
de la parcelle) ou **déclaré** (saisi directement par l'utilisateur), selon un attribut
`yield_source` explicite. Cette double stratégie répond à un besoin métier réel : le rendement
calculé suppose que toute la superficie de la parcelle a été semée avec la culture concernée,
hypothèse qui ne tient pas toujours (culture partielle, association de cultures) — le
rendement déclaré permet alors à l'utilisateur de fournir une valeur plus juste.

## 5.3 Autorisation par rôle (transverse)

Chaque ressource dispose d'une Policy dédiée. Le motif suivant, commun à toutes, illustre le
principe de « confiance zéro » envers les identifiants fournis par le client — la Policy ne se
contente jamais de vérifier qu'un utilisateur a le bon rôle, elle revérifie systématiquement
que la ressource concernée appartient bien à son périmètre :

```php
public function update(User $user, CropCycle $cropCycle): bool
{
    return $user->role === 'cooperative_manager'
        && $user->managedCooperative?->id === $cropCycle->plot?->cooperative_id;
}
```

## 5.4 Tableau de bord analytique (Module H)

Le tableau de bord ne s'appuie pas sur les endpoints CRUD existants (qui auraient nécessité de
rapatrier côté client des milliers de lignes pour les agréger en JavaScript) mais sur un
endpoint d'agrégation dédié (`GET /api/dashboard`), dont les calculs (sommes, moyennes,
regroupements) sont effectués **en base de données**. Un point de rigueur mérite d'être
souligné : les quantités de récolte ou de consommation d'intrants sont exprimées dans des unités
libres (kilogrammes, sacs, tonnes) non normalisées dans le schéma. Additionner des quantités
d'unités différentes aurait produit un total dénué de sens ; le service d'agrégation regroupe
donc systématiquement ces sommes **par unité**, plutôt que de les fusionner :

```php
->select('harvests.unit', DB::raw('SUM(harvests.quantity) as total_quantity'), ...)
->groupBy('harvests.unit')
```

## 5.5 Cartographie (Module I)

L'intégration de Leaflet a été réalisée en JavaScript natif (API impérative de Leaflet) plutôt
qu'avec la bibliothèque `react-leaflet`, pour ne pas introduire de dépendance supplémentaire non
prévue par le cahier des charges. Un point de sécurité a été identifié et corrigé pendant l'audit
de la Phase 13 : la construction initiale des info-bulles de la carte insérait le nom d'une
parcelle ou d'une coopérative (saisi par l'utilisateur) directement dans le HTML de la page via
`innerHTML`, ouvrant une faille de type XSS stocké. La correction a consisté à construire le DOM
de l'info-bulle avec des nœuds texte (`document.createTextNode`), qui ne sont jamais interprétés
comme du HTML par le navigateur.

## 5.6 Service de Machine Learning (Modules J-K)

Le pipeline (détaillé au chapitre Méthodologie) est implémenté dans `ai-service/app/ml/` :
génération/chargement des données, définition des variables (`features.py`), construction des
trois modèles candidats avec leurs hyperparamètres documentés (`models.py`), calcul des
métriques (`evaluation.py`). L'orchestration (`TrainingService`) applique la règle de sélection
et sauvegarde le modèle retenu (`joblib`) accompagné de ses métadonnées (`metadata.json`),
consultables via `GET /models/{id}`. Le service de prédiction extrait également, pour le modèle
actif, l'**importance réelle des variables** — coefficients standardisés pour une régression
linéaire, réduction d'impureté pour un modèle à base d'arbres — jamais une valeur inventée.

## 5.7 Intégration Laravel → FastAPI (Module J/K, Phase 10)

`App\Services\PredictionService` (backend) illustre la cohérence entre les modules : avant
d'appeler le service IA, il tente de dériver les variables d'entrée à partir de **données
réellement enregistrées** dans la plateforme — superficie de la parcelle (PostGIS), quantité
d'engrais utilisée (somme des utilisations d'intrants de type « engrais » sur la période),
pluviométrie (somme des observations météo du module L sur la période du cycle cultural). Les
variables non déductibles (absence d'observation météo, catalogue de variétés non mappé aux
deux classes du modèle) restent explicitement `null`, à saisir manuellement dans l'interface —
jamais estimées silencieusement.

## 5.8 Module Météo (Phase 11, extension)

Conformément à la contrainte du cahier des charges (« ne pas rendre cette fonctionnalité
obligatoire »), le module Météo a été implémenté comme une simple ressource CRUD de saisie
manuelle d'observations, sans appel à un fournisseur météorologique externe (aucun accès à une
telle source n'étant disponible pour ce mémoire). Son seul point de couplage avec le reste de la
plateforme est la dérivation de pluviométrie décrite en 5.7, elle-même dégradée proprement (valeur
`null`) en l'absence de données.

## 5.9 Frontend — architecture par fonctionnalités

Le frontend est organisé par domaine métier (`src/features/<module>/`), chacun regroupant son
service d'accès à l'API, ses pages de liste/formulaire et, le cas échéant, ses styles. Deux
éléments transverses méritent mention : un client Axios centralisé (`services/http.ts`),
responsable de l'authentification par cookie et de la normalisation des erreurs API en une forme
exploitable uniformément par tous les formulaires ; et une fabrique générique de client CRUD
(`services/resource.ts`), qui a évité de dupliquer cinq fonctions d'accès HTTP quasi identiques
pour chacune des dix ressources métier.

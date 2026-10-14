# Chapitre 1 — Présentation du système

## 1.1 Contexte

L'agriculture constitue l'un des piliers économiques et sociaux de Madagascar, employant une
part importante de la population active, en particulier au sein de coopératives agricoles
locales. Ces structures jouent un rôle central dans l'organisation de la production, la
mutualisation des ressources et l'accompagnement des agriculteurs. Cependant, une large partie
d'entre elles gère encore ses informations — agriculteurs membres, parcelles exploitées,
cultures pratiquées, récoltes, intrants utilisés — de manière manuelle ou dispersée (registres
papier, tableurs isolés), sans centralisation ni exploitation analytique de ces données.

Cette dispersion limite fortement la capacité des coopératives et de leurs responsables à :
- disposer d'une vision consolidée de leur activité (superficies réellement exploitées,
  volumes produits, consommation d'intrants) ;
- localiser précisément les parcelles et en tirer des analyses géographiques ;
- anticiper la production à venir sur la base de l'historique disponible.

## 1.2 Problématique

Comment concevoir une plateforme numérique qui centralise les données agricoles des
coopératives malgaches, les rende exploitables (visualisation géographique, indicateurs
analytiques) et prépare — sans se substituer à une expertise agronomique qu'aucune donnée
réelle actuellement disponible ne permettrait de garantir — un mécanisme de prévision de
production reposant sur des méthodes de Machine Learning explicites, comparées et évaluées ?

La problématique se décline en trois axes techniques traités successivement dans ce mémoire :
1. **Axe données** : comment modéliser et stocker des informations agricoles hétérogènes,
   dont une composante géographique réelle (limites de parcelles), dans une base relationnelle
   normalisée ?
2. **Axe applicatif** : comment architecturer une plateforme web moderne (API REST, interface
   utilisateur, cartographie, tableau de bord) qui reste maintenable, sécurisée et évolutive ?
3. **Axe intelligence artificielle** : comment intégrer, de façon honnête et méthodologiquement
   rigoureuse, un service de Machine Learning indépendant, alors qu'aucun jeu de données
   agricole réel n'est disponible au moment du développement ?

## 1.3 Objectifs

### Objectif général

Concevoir et développer une plateforme intelligente de gestion agricole adaptée aux besoins
des coopératives agricoles à Madagascar, permettant la gestion, la visualisation, l'analyse et
la prévision des données agricoles.

### Objectifs spécifiques

1. Centraliser les informations des coopératives et de leurs agriculteurs membres.
2. Gérer et géolocaliser les parcelles agricoles (données géographiques réelles, PostGIS).
3. Suivre les cultures, campagnes agricoles et cycles culturaux.
4. Enregistrer les récoltes et calculer un rendement selon une stratégie explicite (calculé ou
   déclaré).
5. Gérer les intrants et leur consommation.
6. Construire un tableau de bord analytique alimenté exclusivement par des données réelles de
   l'API (aucun indicateur simulé côté interface).
7. Visualiser les parcelles sur une carte interactive (Leaflet/OpenStreetMap).
8. Développer un service de Machine Learning indépendant, avec un pipeline complet et une
   comparaison objective de plusieurs algorithmes.
9. Intégrer ce service à la plateforme principale sans compromettre la séparation des
   responsabilités entre applications.
10. Préparer, sans l'imposer comme fonctionnement de base, l'intégration future de données
    météorologiques réelles.

## 1.4 Utilisateurs du système

Quatre profils d'utilisateurs ont été identifiés, avec des périmètres d'action distincts,
implémentés par un contrôle d'accès basé sur les rôles (RBAC) au niveau de l'API :

| Rôle | Périmètre |
|---|---|
| **Administrateur** | Gestion complète de la plateforme, des coopératives, des référentiels (cultures, intrants, campagnes) ; supervision globale. |
| **Responsable de coopérative** | Gestion des agriculteurs, parcelles, cycles culturaux, récoltes et intrants **de sa propre coopérative uniquement** ; consultation des indicateurs et prévisions. |
| **Agriculteur** | Consultation de ses propres informations, parcelles, historique de production et prévisions le concernant (lecture seule). |
| **Analyste / décideur** | Consultation transverse des tableaux de bord, indicateurs et prévisions, sans droit de modification. |

L'architecture d'autorisation (détaillée au chapitre Conception) a été conçue pour permettre
l'ajout futur de rôles supplémentaires sans remodelage du schéma de données.

## 1.5 Présentation générale de la plateforme

Le système se compose de **trois applications indépendantes**, communiquant exclusivement par
API HTTP, jamais par accès direct à une base de données partagée :

1. **Backend** — API REST développée avec Laravel 13, seule application autorisée à accéder à
   la base PostgreSQL/PostGIS et seule à appeler le service de Machine Learning.
2. **Frontend** — application web monopage (SPA) développée avec React et TypeScript,
   consommant exclusivement l'API du backend.
3. **Service IA** — service FastAPI (Python) indépendant, portant le pipeline de Machine
   Learning (entraînement, sauvegarde de modèles, prédiction), appelé uniquement par le
   backend.

Cette séparation stricte (détaillée au chapitre 2, section Architecture) répond à un objectif
de maintenabilité et d'évolutivité : chaque application peut être développée, testée et, à
terme, déployée indépendamment des deux autres.

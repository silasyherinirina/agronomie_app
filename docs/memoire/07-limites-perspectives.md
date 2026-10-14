# Chapitre 7 — Limites et perspectives

## 7.1 Limites assumées

Les limites suivantes sont énoncées comme telles délibérément : chacune correspond à une
frontière de périmètre explicitement posée pendant le développement (contrainte du cahier des
charges, absence de ressource disponible, ou arbitrage de temps propre à un mémoire de Master),
plutôt qu'à un défaut non maîtrisé.

### 7.1.1 Données agricoles synthétiques

Aucune donnée agricole réelle de Madagascar n'était disponible pendant le développement. Le
modèle de Machine Learning a donc été entraîné et évalué sur un jeu de données **entièrement
synthétique**, généré selon un modèle mathématique documenté (chapitre 6). Cette limite est la
plus structurante du mémoire : elle signifie que les résultats numériques du chapitre 6, bien
que rigoureusement obtenus, ne peuvent être interprétés comme une évaluation de performance sur
un cas réel. Le pipeline a néanmoins été conçu pour que le remplacement de la source de données
(fonction `load_training_dataset()`) n'exige aucune modification du reste de l'architecture.

### 7.1.2 Périmètre du modèle prédictif

Un seul modèle a été développé, portant sur le rendement d'une seule culture (le riz) à partir
de cinq variables. Ce choix a permis de conduire l'intégralité du pipeline (de la donnée à
l'intégration applicative) avec rigueur plutôt que de multiplier les modèles partiels. Le
registre de modèles (`ai-service/models/`) et l'architecture de sélection de variables
(`app/ml/features.py`) sont conçus pour accueillir plusieurs cultures sans réécriture.

### 7.1.3 Absence de fournisseur météorologique réel

Le module Météo (Phase 11) repose exclusivement sur une saisie manuelle d'observations,
faute d'accès à un service météorologique externe dans le cadre de ce mémoire. Il en résulte
que la variable de pluviométrie utilisée par le modèle de prédiction n'est disponible que si des
observations ont été saisies pour la période concernée — l'architecture (table dédiée, service
de dérivation) est prête pour un fournisseur réel, son intégration reste à faire.

### 7.1.4 Variables de prédiction partiellement manuelles

Deux des cinq variables du modèle prédictif ne peuvent être entièrement automatisées avec les
données actuellement disponibles : la pluviométrie (limite précédente) et la classe de variété
(le catalogue de variétés de la plateforme est en texte libre, non mappé aux deux classes
binaires — traditionnelle/améliorée — sur lesquelles le modèle synthétique a été entraîné).

### 7.1.5 Absence d'interface de gestion des utilisateurs

La création des comptes (administrateurs, responsables de coopérative) s'effectue actuellement
en ligne de commande (console Laravel), non via un écran d'administration dédié — fonctionnalité
non demandée explicitement par le cahier des charges et laissée hors périmètre pour privilégier
les modules cœur.

### 7.1.6 Absence de tests de bout en bout dans un navigateur réel

La suite de tests frontend (Vitest + React Testing Library) couvre les services, hooks et
composants en environnement simulé (jsdom), pas des scénarios complets exécutés dans un
navigateur réel (outils du type Playwright ou Cypress). Le test d'intégration complète du
chapitre 6 compense partiellement cette limite en validant les échanges HTTP réels entre les
trois applications, sans toutefois couvrir le rendu visuel ni les interactions utilisateur dans
un navigateur.

### 7.1.7 Déploiement de portée académique

Le guide de déploiement produit (`docs/deployment.md`) couvre une mise en production
mono-serveur simple. Il ne traite ni la haute disponibilité, ni l'orchestration de conteneurs,
ni les sauvegardes automatisées de la base de données, ni la supervision applicative — autant
d'aspects hors du périmètre raisonnable d'un mémoire de Master, mais nécessaires à un
déploiement de production réelle.

### 7.1.8 Absence de niveau de confiance sur les prédictions

Aucune prédiction n'est accompagnée d'un intervalle de confiance ou d'un niveau de confiance
chiffré. Ce choix est délibéré, non une omission : le calcul rigoureux d'un tel intervalle
(par exemple par régression quantile, ou par une approximation gaussienne sur les résidus pour
un modèle linéaire) n'a pas été implémenté, et le cahier des charges interdit explicitement
d'afficher un niveau de confiance qui ne serait pas statistiquement justifié. Afficher un
chiffre malgré cette absence aurait été plus trompeur que de ne rien afficher.

## 7.2 Perspectives

Les perspectives suivantes prolongent directement les limites énoncées ci-dessus, dans un ordre
de priorité réaliste pour une suite de projet :

1. **Substitution des données synthétiques par des données réelles.** Priorité la plus élevée :
   un partenariat avec une coopérative ou un institut agricole malgache permettrait de
   ré-entraîner le pipeline existant (sans modification structurelle) sur des données réelles,
   et de conduire une véritable évaluation de la capacité prédictive du système.
2. **Extension à d'autres cultures.** L'ajout d'un modèle par culture (maïs, manioc, autres
   cultures vivrières majeures à Madagascar) s'appuierait sur le registre de modèles existant,
   sans remodelage de l'API de prédiction.
3. **Intégration d'un fournisseur météorologique réel**, en s'appuyant sur l'architecture déjà
   posée (table `weather_observations`, service de dérivation de la pluviométrie).
4. **Interface de gestion des utilisateurs et des rôles**, pour autonomiser les administrateurs
   de la plateforme vis-à-vis de la ligne de commande.
5. **Outil de dessin de géométrie sur la carte** (par exemple via une bibliothèque de dessin
   interactif pour Leaflet), en remplacement de la saisie actuelle de coordonnées au format
   JSON pour la création d'une parcelle.
6. **Tests de bout en bout dans un navigateur réel** et **intégration continue** (exécution
   automatique des trois suites de tests à chaque modification du code), pour sécuriser les
   évolutions futures du projet.
7. **Notifications et alertes** (seuils de rendement anormaux, échéance de campagne agricole),
   comme extension naturelle du tableau de bord analytique.
8. **Renforcement du niveau de production** : conteneurisation, sauvegardes automatisées,
   supervision applicative, pour un déploiement au-delà du périmètre académique de ce mémoire.

Ces perspectives, prises ensemble, dessinent une trajectoire cohérente : consolider d'abord la
validité scientifique du volet Machine Learning par des données réelles, avant d'élargir le
périmètre fonctionnel et opérationnel de la plateforme.

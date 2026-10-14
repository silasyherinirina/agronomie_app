# Chapitre 4 — Méthodologie

## 4.1 Démarche de développement par phases

Le développement a suivi une démarche **incrémentale et strictement séquentielle**, en quinze
phases numérotées (0 à 15, cette dernière correspondant à la rédaction du présent document),
chacune produisant un livrable vérifiable avant le passage à la suivante. Cette discipline
répondait à un principe explicite du cahier des charges : ne jamais générer l'ensemble du
projet en une seule fois, et ne jamais passer à la phase suivante sans validation explicite du
livrable de la phase courante.

| Étape | Contenu | Nature du livrable |
|---|---|---|
| 0 | Analyse et architecture | Documents (aucun code) : besoins, UML, MCD, stratégies |
| 1 | Initialisation | Structure des trois projets, dépôt Git |
| 2 | Base de données | Migrations, modèles Eloquent, PostGIS |
| 3 | Backend | Services, Controllers, Form Requests, Resources, Policies |
| 4 | API et tests | Tests automatisés par ressource, collection Postman |
| 5 | Frontend | Authentification, CRUD des dix ressources métier |
| 6 | Cartographie | Intégration Leaflet, affichage des parcelles réelles |
| 7 | Tableau de bord | Endpoint d'agrégation dédié, graphiques, carte analytique |
| 8 | Service Python | Architecture FastAPI (schémas, services, endpoints) |
| 9 | Machine Learning | Pipeline complet sur données synthétiques documentées |
| 10 | Intégration IA | Connexion Laravel → FastAPI, interface de prédiction |
| 11 | Météo | Extension non bloquante, intégrée aux prédictions si disponible |
| 12 | Tests globaux | Audit complet, ajout des tests frontend (absents jusque-là) |
| 13 | Optimisation et sécurité | Audit et correction (performance, sécurité) |
| 14 | Documentation | Consolidation de toute la documentation technique |
| 15 | Préparation du mémoire | Présent document |

Cette structuration en phases a eu un effet méthodologique notable au-delà de la simple
organisation du travail : elle a permis de détecter, à chaque étape, des anomalies qu'une
approche « tout en un bloc » aurait rendues plus difficiles à isoler (voir chapitre Tests et
résultats pour plusieurs exemples concrets d'anomalies détectées et corrigées grâce à cette
granularité).

## 4.2 Méthodologie de vérification continue

Une règle transversale a été appliquée à chaque phase : **toute affirmation technique
incertaine doit être vérifiée, jamais supposée**. Concrètement, cela s'est traduit par :

- la vérification de compatibilité d'une bibliothèque par consultation de ses métadonnées
  officielles et/ou une installation réelle, avant de l'utiliser (voir chapitre 3, section
  Python) ;
- la vérification du comportement réel d'un mécanisme du framework par lecture de son code
  source, lorsque la documentation ou le souvenir de son fonctionnement laissait un doute
  (par exemple, la liaison de modèle implicite de Laravel entre un paramètre de route et un
  argument de contrôleur, ou le comportement de sérialisation JSON de PHP pour les nombres à
  virgule flottante) ;
- la préférence systématique pour une preuve empirique (exécuter le code, lire une réponse
  HTTP réelle, compter les requêtes SQL effectivement émises) plutôt qu'un raisonnement
  théorique non vérifié, y compris lorsque ce raisonnement semblait initialement convaincant.

## 4.3 Méthodologie Machine Learning

Le pipeline de Machine Learning (détaillé au chapitre Implémentation et dans
`docs/machine-learning/phase9-pipeline.md`) a suivi une méthodologie en treize étapes,
conforme au cahier des charges :

```
Collecte des données
   → Nettoyage
   → Analyse exploratoire
   → Préparation / sélection des variables
   → Séparation train/test
   → Entraînement (plusieurs algorithmes)
   → Validation
   → Évaluation (métriques)
   → Comparaison
   → Sélection (règle explicite, non arbitraire)
   → Sauvegarde du modèle
   → Prédiction
```

Deux principes méthodologiques ont été appliqués strictement :

1. **Aucune métrique n'est calculée sur les données d'entraînement** : toutes les métriques
   rapportées (MAE, RMSE, R²) sont mesurées exclusivement sur le jeu de test, séparé avant tout
   entraînement (80 % / 20 %, graine aléatoire fixée à 42 pour la reproductibilité).
2. **La sélection du modèle final repose sur une règle objective et documentée** — le meilleur
   R² sur le jeu de test — et non sur un choix arbitraire ou une préférence a priori pour un
   algorithme plus sophistiqué. Cette règle a été respectée même lorsque son application a
   désigné le modèle le plus simple des trois (régression linéaire) comme vainqueur, ainsi
   qu'il est rapporté et analysé au chapitre Tests et résultats.

## 4.4 Gestion des données absentes de la réalité

Aucune donnée agricole réelle de Madagascar n'étant disponible pendant le développement, une
règle méthodologique stricte a été suivie : les données synthétiques utilisées pour développer
et démontrer le pipeline de Machine Learning sont **explicitement et systématiquement
identifiées comme telles**, à trois niveaux : dans le code (commentaires, nom des modules), dans
la documentation, et dans l'interface utilisateur (indicateur `is_experimental` renvoyé par
chaque prédiction). Cette règle prolonge, au niveau des données, le principe méthodologique
général énoncé en 4.2 : ne jamais présenter une information non vérifiée comme si elle l'était.

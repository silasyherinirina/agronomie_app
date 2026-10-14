# Chapitre 6 — Tests et résultats

## 6.1 Stratégie de tests

Chaque application dispose de sa propre suite de tests automatisés, exécutée indépendamment :

| Application | Outil | Portée | Nombre de tests |
|---|---|---|---|
| Backend (Laravel) | PHPUnit (tests Feature) | CRUD par ressource, autorisation par rôle, validation, PostGIS, intégration IA (simulée par `Http::fake()`), non-régression de performance (comptage de requêtes SQL), limitation de débit | **80** |
| Frontend (React) | Vitest + React Testing Library | Normalisation des erreurs API, hooks (debounce), composants réutilisables, parcours de connexion | **23** |
| Service IA (FastAPI) | pytest | Endpoints, pipeline de données/modèles, reproductibilité du jeu de données synthétique | **18** |
| API (manuel) | Collection Postman | Vérification exploratoire de l'ensemble des endpoints, avec exemples de requêtes documentés | — |

Soit **121 tests automatisés** au total, tous exécutés avec succès à la fin du développement.

Un test d'intégration complète a également été mené manuellement (Phase 12) : les trois
applications démarrées simultanément, un parcours utilisateur réel de treize étapes a été
exécuté par requêtes HTTP effectives (authentification, création d'une coopérative, d'un
agriculteur, d'une parcelle géoréférencée, d'une culture, d'une campagne, d'un cycle cultural,
d'une récolte, vérification du tableau de bord, enregistrement d'une observation météo,
vérification de la suggestion de variables, puis demande de prédiction réelle auprès du service
IA) — sans recourir à aucune simulation, ce qui a permis de vérifier la cohérence bout en bout
du système au-delà de ce que des tests unitaires isolés peuvent garantir.

## 6.2 Une anomalie identifiée, investiguée et écartée — illustration de la méthodologie

Lors de l'audit de performance (Phase 13), un test comparant le nombre de requêtes SQL émises
par un endpoint de liste pour 3 puis 15 lignes de résultat a d'abord signalé une croissance
linéaire du nombre de requêtes — symptôme caractéristique d'un problème dit « N+1 » (une
requête supplémentaire émise pour chaque ligne du résultat). Plutôt que de corriger le code sur
la base de ce seul signal, une investigation a été menée : lecture du code source du mécanisme
Laravel concerné (`whenLoaded`, filtrage des valeurs manquantes dans les ressources API),
reproduction isolée du comportement en environnement interactif. Cette investigation a révélé
que l'anomalie provenait du **script de test lui-même** (le compteur de requêtes SQL n'avait pas
été réinitialisé après la création des données de test supplémentaires, comptant ainsi des
insertions comme des requêtes de lecture) et non de l'application. Le test a été corrigé et
confirme, chiffres à l'appui, l'absence de problème N+1 sur les endpoints audités. Cet épisode
est rapporté ici car il illustre concrètement la méthodologie de vérification énoncée au
chapitre 4 : ne jamais accepter un résultat surprenant sans le comprendre, y compris lorsqu'il
aurait été plus rapide de « corriger » un code qui n'était pas en cause.

## 6.3 Résultats de l'audit sécurité et performance (Phase 13)

Trois anomalies réelles ont été identifiées et corrigées lors de cet audit :

1. **Faille XSS stockée** dans le rendu des info-bulles de la carte (données utilisateur
   injectées via `innerHTML`) — corrigée par construction du DOM avec des nœuds texte.
2. **Absence de limitation de débit** sur la connexion, exposant à une attaque par bourrage
   d'identifiants — corrigée par une limite de 5 tentatives par minute et par couple
   (adresse e-mail, adresse IP).
3. **Pagination non bornée** sur douze endpoints de liste, permettant à un client de demander
   une page arbitrairement volumineuse — corrigée par un plafond de 100 éléments par page.

Sur le plan performance, le regroupement du code frontend a été revu pour charger à la demande
(plutôt qu'au démarrage) les deux pages dépendant des bibliothèques les plus volumineuses
(cartographie/Leaflet, tableau de bord/Recharts), réduisant le poids du chargement initial de
l'application de **903 Ko à 353 Ko** (mesure réelle avant/après, soit une réduction de 61 %).

## 6.4 Résultats du pipeline de Machine Learning

Le pipeline a été exécuté réellement (et non simulé) sur le jeu de données synthétique
documenté (600 observations, 480 en entraînement, 120 en test, graine aléatoire fixée à 42).
Les trois modèles candidats ont été entraînés et évalués sur le jeu de test :

| Algorithme | MAE (kg/ha) | RMSE (kg/ha) | R² |
|---|---|---|---|
| **Régression linéaire** | **138,95** | **167,01** | **0,538** ← modèle retenu |
| Gradient Boosting | 135,72 | 170,24 | 0,520 |
| Random Forest | 140,74 | 172,19 | 0,509 |

### Analyse

Conformément à la règle de sélection définie au chapitre Méthodologie (meilleur R² sur le jeu
de test), c'est la **régression linéaire** qui a été retenue — et non un modèle d'ensemble a
priori perçu comme plus performant. Ce résultat, contre-intuitif au premier abord, s'explique
par la nature du signal généré : bien que non linéaire (effet en racine carrée de l'engrais,
effet quadratique de la pluviométrie autour d'un optimum), ce signal reste lisse et globalement
monotone sur la plage de valeurs observée. Combiné à un niveau de bruit volontairement modéré
et à une taille d'entraînement modeste (480 observations), un modèle linéaire correctement
spécifié peut égaler, voire dépasser localement, des modèles d'ensemble davantage susceptibles
de s'ajuster au bruit plutôt qu'au signal sous-jacent dans ces conditions. Un R² de 0,538
signifie qu'environ 54 % de la variance du rendement est expliquée par les cinq variables
retenues sur les données de test — un score cohérent avec le niveau de bruit délibérément
injecté dans le générateur de données, et non un score artificiellement gonflé.

Ce résultat conserve une valeur méthodologique au-delà de son contexte synthétique : il illustre
qu'une comparaison systématique et objective entre plusieurs algorithmes est préférable à un
choix a priori fondé sur la seule complexité apparente d'un modèle — point directement
réutilisable dans la discussion du mémoire.

**Avertissement méthodologique** : ces résultats ont été obtenus sur des données entièrement
synthétiques (voir chapitre 4, section 4.4) et ne constituent en aucun cas une évaluation de la
capacité du système à prédire un rendement agricole réel à Madagascar. Ils démontrent la
validité et la rigueur du pipeline mis en œuvre, préalable nécessaire à son application future
sur des données réelles (voir chapitre Limites et perspectives).

# Dataset de démonstration — Agriculture Mada

Ce document explique la méthode de construction du jeu de données de démonstration utilisé par
la plateforme Agriculture Mada, destiné à une soutenance de mémoire de fin d'études. Pour le
détail des sources consultées, voir
[`SOURCES-AGRICULTURE-MADAGASCAR.md`](SOURCES-AGRICULTURE-MADAGASCAR.md).

## Principe directeur

> _« Les données de référence agricoles proviennent de sources institutionnelles publiques,
> tandis que les données individuelles utilisées pour tester les fonctionnalités de la
> plateforme sont des données synthétiques construites pour reproduire des situations agricoles
> plausibles. »_

Concrètement, trois catégories de données coexistent dans le dataset — **jamais mélangées ni
présentées les unes pour les autres** :

| Catégorie                           | Exemples dans ce projet                                                                                                                                                        | Où le vérifier                                                              |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------- |
| **A. Officielle/publique**          | 23 régions de Madagascar, variétés de riz FOFIFA (Makalioka, X265, Besalohy…), rendements de référence CGIAR/Cornell                                                           | Commentaires de classe + `SOURCES-AGRICULTURE-MADAGASCAR.md`                |
| **B. Dérivée**                      | Superficie des parcelles (calculée par PostGIS, jamais saisie), quantités de récolte (rendement de référence × superficie réelle), rendement/ha (quantité ÷ superficie réelle) | `PlotService`, `HarvestService` (code existant, réutilisé tel quel)         |
| **C. Synthétique de démonstration** | Coopératives, agriculteurs, parcelles, utilisations d'intrants, observations météo manuelles, historique de versions de modèle IA, prédictions                                 | Noms explicites (« Coopérative Démo… »), commentaires de classe des Seeders |

## 1. Sources utilisées

Voir le détail complet dans `SOURCES-AGRICULTURE-MADAGASCAR.md`. En résumé : régions officielles
de Madagascar, variétés de riz et rendements de référence documentés par FOFIFA/CGIAR-Cornell.
Aucune autre donnée individuelle (variété non-riz, coopérative, agriculteur) n'a pu être
rattachée à une source publique fiable et exploitable — voir section « Limites » ci-dessous.

## 2. Données officielles

- 23 régions administratives de Madagascar + un district/commune/centroïde réel par région.
- 6 variétés de riz réellement documentées par la recherche agricole malgache (FOFIFA).
- Rendements de référence du riz par système de culture (irrigué grande saison / contre-saison /
  pluvial de tanety / tavy), source CGIAR/Cornell.

## 3. Données dérivées

- **Superficie des parcelles** (`plots.area_ha`) : jamais saisie à la main, toujours calculée par
  `PlotService::calculateAreaHa()` via `ST_Area(geography(geom))/10000` — y compris pour les
  parcelles de démonstration (`PlotSeeder` appelle ce même service, exactement comme le ferait
  une création via l'API).
- **Rendement des récoltes** (`harvests.yield_value`, quand `yield_source = 'calculated'`) :
  calculé par `HarvestService` comme `quantity / plot.area_ha`, jamais écrit directement.
- **Quantités de récolte de démonstration** : rendement de référence (catégorie A pour le riz,
  ordre de grandeur agronomique général FAO pour les autres cultures) × superficie réelle de la
  parcelle × variation plausible ±20 %.
- **Variables d'entrée des prédictions IA de démonstration** (superficie, mois de plantation,
  dose d'engrais) : dérivées des données réelles du cycle cultural/de la parcelle/des
  utilisations d'intrants correspondantes, exactement comme le ferait
  `PredictionService::suggestInputs()`.

## 4. Données synthétiques de démonstration

Explicitement marquées comme telles, jamais présentées comme réelles :

- **Coopératives** : préfixe « Coopérative Démo {Région} 01 » — aucune liste publique fiable de
  coopératives agricoles malgaches n'a été trouvée (voir Sources, section 6).
- **Agriculteurs** : prénoms/noms malgaches courants combinés librement (ex. « Rakoto
  Andriamahefa »), à l'image d'un « Jean Dupont » francophone — **aucun lien avec une personne
  réelle recensée**. Aucun compte de connexion n'est créé pour ces agriculteurs.
- **Parcelles** : géométries rectangulaires simples autour du centre réel du district de la
  coopérative, avec un décalage aléatoire — jamais présentées comme des limites cadastrales
  officielles.
- **Utilisations d'intrants** : quantités calculées au hectare (ordre de grandeur agronomique
  général) ; les **prix unitaires sont des prix de démonstration**, ne provenant d'aucune source
  officielle (voir `InputUsageSeeder`, constante `PRICES_AR`).
- **Observations météo manuelles** (table `weather_observations`) : valeurs synthétiques
  plausibles par grande zone climatique (hauts-plateaux / côtes) — **à ne pas confondre avec les
  prévisions Open-Meteo**, qui sont réelles et récupérées en direct (voir section Météo
  ci-dessous).
- **Historique de versions du modèle IA** (`model_versions`) : 20 versions représentant un
  historique d'entraînement plausible (métriques s'améliorant progressivement), du même ordre de
  grandeur que la version réelle déjà produite par le service IA (MAE ≈ 139 kg/ha pour un modèle
  linéaire sur le rendement du riz).
- **Prédictions de démonstration** (`production_predictions`) : ne proviennent **pas** d'un appel
  live au service IA (FastAPI) pendant le Seeder — construites directement à partir des données
  réelles du cycle cultural correspondant. Le champ `is_experimental` renvoyé par l'API reste
  `true` pour toutes (comportement du code existant, `ProductionPredictionResource`), donc le
  frontend continue d'afficher l'avertissement adéquat.

## 5. Limites du dataset

- Aucune liste publique de coopératives agricoles malgaches n'a été localisée : toutes les
  coopératives sont synthétiques.
- Le catalogue variétal complet du SOC (`soc-semences.mg`) était inaccessible depuis cet
  environnement (échec DNS) au moment de la recherche ; le catalogue national FOFIFA en PDF
  dépassait la taille exploitable automatiquement. En conséquence, seules les variétés de riz
  sont documentées nommément — les autres cultures reçoivent une variété générique
  « Variété locale (non cataloguée nommément) ».
- Les rendements de référence des cultures autres que le riz (`GENERIC_CROP_YIELD_REFERENCE_T_HA`
  dans `MadagascarReferenceData`) sont des ordres de grandeur agronomiques généraux (FAO,
  littérature tropicale), **pas** des statistiques INSTAT vérifiées culture par culture pour
  Madagascar — à traiter avec plus de prudence que les chiffres du riz.
- Les superficies moyennes de parcelle (0,35 à 3,5 ha) reflètent un ordre de grandeur plausible
  pour une petite exploitation familiale malgache, pas une statistique précise vérifiée pour ce
  dataset.

## 6. Méthode de génération

Un Seeder Laravel dédié par table métier (voir section Seeders ci-dessous), orchestré par
`DatabaseSeeder`, dans l'ordre imposé par les dépendances du schéma. Chaque Seeder :

- ne modifie jamais la table `users` ni les données qu'il ne possède pas (voir Idempotence) ;
- réutilise les services métier existants quand ils portent une règle de calcul
  (`PlotService`, `HarvestService`) plutôt que d'écrire une valeur dérivée à la main ;
- documente en commentaire PHP la catégorie (A/B/C ci-dessus) et la source de chaque donnée
  importante.

## 7. Relations entre les tables

```
Cooperative ─┬─▶ Farmer ─▶ Plot ─▶ CropCycle ──▶ Harvest
             │                       │  │
             │                       │  └─▶ Crop ──▶ CropVariety
             │                       └────▶ AgriculturalCampaign
             └─▶ Plot (cooperative_id direct)

Input ─┬─▶ InputUsage ──▶ Plot / AgriculturalCampaign / Farmer
Plot ──▶ WeatherObservation (relevé manuel)
ModelVersion ──▶ ProductionPrediction ──▶ Plot / Crop / AgriculturalCampaign
```

## 8. Comment exécuter les Seeders

Sur une base déjà migrée (`php artisan migrate`) :

```bash
# Tout le dataset (ordre des dépendances déjà géré par DatabaseSeeder) :
php artisan db:seed

# Un seul Seeder (utile pour ré-exécuter juste une table) :
php artisan db:seed --class=CooperativeSeeder
```

Tous les Seeders de ce dataset sont **idempotents** (rejouables sans doublon), à l'exception de
la ligne préexistante `User::factory()->create(['email' => 'test@example.com'])` dans
`DatabaseSeeder` (non modifiée par ce travail — hors périmètre, la table `users` ne devant pas
être touchée) : un second `php artisan db:seed` complet échouera sur cette ligne précise
(contrainte d'unicité sur l'e-mail) avant même d'atteindre les Seeders du dataset. Pour rejouer
uniquement le dataset agricole sans ce risque, utiliser `--class=` individuellement comme
ci-dessus, ou commenter temporairement cette ligne.

## 9. Comment réinitialiser la base

```bash
# Ré-applique toutes les migrations depuis zéro (⚠ efface les données) puis reseed :
php artisan migrate:fresh --seed
```

**Ne jamais exécuter `php artisan test`** pour lancer les tests (voir CLAUDE.md /
documentation projet existante) : utiliser `./vendor/bin/phpunit`, qui cible l'environnement de
test dédié et ne touche pas cette base de démonstration.

## 10. Comment mettre à jour les données météo

Les prévisions Open-Meteo ne sont **jamais persistées** : `WeatherProviderService::forecast()`
les récupère en direct à chaque appel de `getPlotWeather()`, avec une mise en cache courte durée
(clé par coordonnée arrondie, TTL configuré via `services.weather.cache_ttl`) pour éviter les
appels redondants. Il n'y a donc rien à « synchroniser » : la météo affichée est toujours la plus
récente disponible auprès du fournisseur, dans la limite du cache. Seule la table
`weather_observations` (relevés **manuels** de terrain, fonctionnalité indépendante) peut être
complétée en rejouant `WeatherObservationSeeder` ou via le formulaire existant de l'application.

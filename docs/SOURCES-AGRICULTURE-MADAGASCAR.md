# Sources consultées — dataset agricole Madagascar

Recherche documentaire effectuée le **2026-09-06** (voir dates de consultation ci-dessous).
Chaque source est reliée aux données précises qu'elle a permis de vérifier dans
`backend/database/seeders/Support/MadagascarReferenceData.php` et les Seeders associés.

## 1. Régions administratives de Madagascar

- **Donnée utilisée** : les 23 régions officielles (`MadagascarReferenceData::REGIONS`).
- **Constat** : Madagascar est découpé en 23 régions et 119 districts depuis la scission de
  l'ancienne région Vatovavy-Fitovinany en deux régions distinctes (Vatovavy et Fitovinany) en
  2021.
- Sources générales consultées (recoupées entre elles) :
  - https://www.aty-aminay.com/blog/les-23-regions-de-madagascar
  - https://en.wikipedia.org/wiki/Subdivisions_of_Madagascar (recoupement uniquement, pas
    utilisé comme source unique)
  - https://torolalana.gov.mg/fr/services/regions-et-districts/ (portail des services publics
    malgaches)
- Les districts/communes/coordonnées de chefs-lieux associés à chaque région
  (`REGION_CENTERS`) sont une connaissance géographique générale (villes de Madagascar), pas une
  mesure de terrain — utilisés uniquement pour situer une coopérative/parcelle **synthétique**
  dans une zone réelle.

## 2. Variétés de riz documentées par la recherche agricole malgache (FOFIFA)

| Variété | Détail | Source |
|---|---|---|
| Makalioka | Variété traditionnelle renommée d'Alaotra-Mangoro (Ambatondrazaka) | [FOFIFA](https://www.fofifa.mg/les-varietes-de-riz-qui-font-la-renommee-dalaotra-mangoro-dista-tsemaka-makalioka/) |
| Tsemaka | Idem | [FOFIFA](https://www.fofifa.mg/les-varietes-de-riz-qui-font-la-renommee-dalaotra-mangoro-dista-tsemaka-makalioka/) |
| Dista | Idem | [FOFIFA](https://www.fofifa.mg/les-varietes-de-riz-qui-font-la-renommee-dalaotra-mangoro-dista-tsemaka-makalioka/) |
| X265 | Variété la plus cultivée à Madagascar, référence des essais variétaux FOFIFA | [FOFIFA](https://www.fofifa.mg/besalohy-une-nouvelle-victoire-de-la-recherche-agricole-malgache/) |
| Besalohy (FOFIFA X265-MP3) | Croisement X265 × Koshihikari (gène MP3), homologuée après essais DHS ; lancement officiel FOFIFA/JIRCAS (programme SATREPS-FyVary, JICA) le **1er septembre 2026** | [FOFIFA](https://www.fofifa.mg/besalohy-une-nouvelle-victoire-de-la-recherche-agricole-malgache/) |
| Tsipala | Mentionnée dans les archives variétales FOFIFA (campagne 1992/93) | résultat de recherche web (extrait de recherche, page source non retrouvée en accès direct) |

Consultées le 2026-09-06. Ces six variétés sont les seules utilisées dans le dataset avec un
nom spécifique et documenté (voir `CropVarietySeeder`).

## 3. Rendements de référence du riz (calibration des récoltes de démonstration)

- **Source** : Andrianarisoa et al., *« Niveau, évolution et facteurs déterminants des
  rendements du riz à Madagascar »*, CGIAR/Cornell —
  https://cgspace.cgiar.org/bitstreams/e30323af-da1e-4fcd-8f8b-872ee129513d/download
- **Valeurs retenues** (tonnes/ha) : riz irrigué grande saison 1,7 ; riz irrigué contre-saison
  1,2 ; riz pluvial de tanety 0,9 ; riz sur brûlis (tavy) 0,7.
- Ces valeurs servent uniquement à calibrer l'**ordre de grandeur** des récoltes synthétiques
  (`HarvestSeeder`) — elles ne sont jamais présentées comme la production réelle d'une parcelle
  précise.

## 4. Statistiques agricoles générales (contexte, non utilisées pour une valeur précise)

- INSTAT Madagascar (Institut National de la Statistique) — https://www.instat.mg/thematique/agriculture
- Ministère de l'Agriculture et de l'Élevage — production rizicole nationale 2023-2024 (+10,6 %,
  de 5,32 à 5,88 millions de tonnes) : https://www.moov.mg/article/86155-riziculture-augmentation-progressive-de-la-production-de-riz
- Ces chiffres nationaux n'ont pas été utilisés pour calibrer une valeur du dataset (échelle
  incompatible avec une parcelle individuelle) — cités ici uniquement comme contexte consulté.

## 5. Catalogue variétal des autres cultures (limite documentée)

- SOC (Service Officiel de Contrôle des Semences et Matériel Végétal), rattaché à la Direction
  Générale de l'Agriculture du MINAE, gère le Catalogue National des Espèces et Variétés (CNEV) :
  https://soc-semences.mg/presentation-soc/ (description générale trouvée via recherche web).
- **Limite rencontrée** : le site `soc-semences.mg` s'est révélé inaccessible depuis cet
  environnement (échec de résolution DNS au moment de la recherche, 2026-09-06) ; le catalogue
  national PDF de FOFIFA (`CATALOGUE-NATIONAL.pdf`) dépassait la taille exploitable par l'outil
  de récupération automatique. En conséquence, **aucun nom de variété spécifique n'a été retenu**
  pour le maïs, le manioc, la pomme de terre, la patate douce, le haricot, l'arachide, le sorgho,
  le niébé, le pois du Cap, le voandzou, le soja, le sésame, le blé, le triticale, le taro, le
  café, la vanille, le coton et le gingembre : ces cultures reçoivent une variété générique
  explicitement nommée **« Variété locale (non cataloguée nommément) »** plutôt qu'un nom inventé
  à consonance officielle — voir `CropVarietySeeder`.
- Autres sources générales consultées confirmant la culture de ces espèces à Madagascar (sans
  détail variétal exploitable) :
  https://www.fao.org/4/i1500e/Madagascar.pdf ,
  https://www.prerad-oi.org (catalogue régional Océan Indien, PDF non exploitable
  automatiquement par l'outil disponible).

## 6. Coopératives agricoles

- **Constat** : aucune liste publique fiable et directement exploitable de coopératives
  agricoles malgaches (nom, région, statut) n'a été localisée lors de cette recherche (le SOC
  publie un catalogue de semences, pas un registre de coopératives).
- **Décision** : toutes les coopératives du dataset sont donc explicitement **synthétiques**
  (préfixe « Coopérative Démo »), conformément à la consigne du cahier des charges Phase 5
  ("pour les coopératives non disponibles publiquement, utiliser des noms explicitement
  synthétiques"). Seules leur région/district/commune de rattachement sont des données
  géographiques publiques réelles.

## 7. Météo

- Fournisseur : **Open-Meteo** (https://open-meteo.com), API publique sans clé — déjà intégrée
  au projet (`WeatherProviderService`), non modifiée par ce travail. Les prévisions sont
  récupérées **en direct** à chaque consultation (avec mise en cache courte durée côté backend)
  et ne sont jamais stockées en base — voir `docs/DATASET-MADAGASCAR.md`, section Météo.

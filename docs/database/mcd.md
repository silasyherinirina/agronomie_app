# MCD (notation Merise)

```
UTILISATEUR (id, nom, email, mot_de_passe, rôle)
COOPERATIVE (id, nom, région, district, commune, localisation, statut)
AGRICULTEUR (id, nom, prénom, date_adhésion, statut)
PARCELLE (id, nom, géométrie, superficie_ha, statut)
CULTURE (id, nom, catégorie, durée_cycle)
VARIETE (id, nom)
CAMPAGNE (id, nom, année, saison, date_début, date_fin, statut)
CYCLE_CULTURAL (id, date_plantation, date_récolte_prévue, statut)
RECOLTE (id, date, quantité, unité, rendement, qualité, observations)
INTRANT (id, nom, type, unité)
UTILISATION_INTRANT (id, date, quantité, coût, observations)
MODELE_ML (id, nom, algorithme, version, date_entraînement, métriques)
PREDICTION (id, date, rendement_prévu, features_json)

Associations :
GERE        (UTILISATEUR 0,1 — COOPERATIVE 1,1)
LIE_A       (UTILISATEUR 0,1 — AGRICULTEUR 1,1)
APPARTIENT  (AGRICULTEUR 1,1 — COOPERATIVE 1,N)
POSSEDE     (COOPERATIVE 1,1 — PARCELLE 1,N)
EXPLOITE    (AGRICULTEUR 0,1 — PARCELLE 0,N)
DECLINE     (CULTURE 1,1 — VARIETE 1,N)
CULTIVE     (PARCELLE 1,1 — CYCLE_CULTURAL 1,N) [+ CULTURE 1,1, + CAMPAGNE 1,1, + VARIETE 0,1]
PRODUIT     (CYCLE_CULTURAL 1,1 — RECOLTE 1,N)
CONSOMME    (CYCLE_CULTURAL / PARCELLE 1,1 — UTILISATION_INTRANT 1,N) [+ INTRANT 1,1]
GENERE      (MODELE_ML 1,1 — PREDICTION 1,N) [+ CULTURE/PARCELLE/CAMPAGNE 0,1]
```

Voir [`erd.mmd`](../uml/erd.mmd) pour la traduction en modèle relationnel (ERD avec clés
primaires/étrangères) et [`docs/architecture/phase0-analyse.md`](../architecture/phase0-analyse.md)
section M pour la stratégie PostGIS (SRID 4326, types géométriques, calcul de superficie).

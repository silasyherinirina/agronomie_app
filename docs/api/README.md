# Documentation API — Agriculture Mada

> Phase 4 du mémoire M2. Décrit l'usage de la collection Postman et les conventions de l'API
> REST Laravel définies en Phase 0 (§N) et implémentées en Phase 3.

## 1. Fichiers

- [`agriculture-mada.postman_collection.json`](./agriculture-mada.postman_collection.json) —
  collection Postman v2.1, organisée par module (Coopératives, Agriculteurs, Parcelles, Cultures,
  Campagnes, Cycles culturaux, Récoltes, Intrants).

## 2. Authentification (Sanctum SPA — cookie httpOnly + CSRF)

L'API n'utilise **pas** de jeton Bearer. Le flux est celui d'un SPA classique avec Laravel Sanctum :

1. **`GET {{base_url}}/sanctum/csrf-cookie`** — pose un cookie `XSRF-TOKEN`.
2. **`POST {{base_url}}/api/login`** `{ "email", "password" }` — crée la session (cookie httpOnly).
3. Toutes les requêtes suivantes envoient automatiquement le cookie de session (géré par Postman)
   et l'en-tête `X-XSRF-TOKEN` (injecté par le script de pré-requête de la collection, voir
   `event.prerequest` dans le JSON).
4. **`POST {{base_url}}/api/logout`** — détruit la session.

Dans Postman : activer la gestion des cookies (comportement par défaut), importer la collection,
exécuter dans l'ordre les requêtes du dossier **Auth**, puis les autres dossiers.

Utilisateur de test à créer via seeder ou `php artisan tinker` (voir §6).

## 3. Format des réponses

**Liste (paginée)** :

```json
{
  "data": [ { "id": 1, "...": "..." } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

**Ressource unique** : `{ "data": { "id": 1, "...": "..." } }`

**Erreur de validation (422)** :

```json
{ "message": "The name field is required.", "errors": { "name": ["The name field is required."] } }
```

**Erreur d'autorisation (403)** : `{ "message": "This action is unauthorized." }`

**Conflit d'intégrité référentielle (409)** — suppression d'une donnée encore référencée :

```json
{ "message": "Impossible de supprimer cette culture : elle est encore utilisée par des cycles culturaux." }
```

## 4. Paramètres communs des listes (`GET .../index`)

| Paramètre | Description |
|---|---|
| `per_page` | Taille de page (défaut 15) |
| `search` | Recherche partielle insensible à la casse (nom, prénom/nom selon la ressource) |
| `status` | Filtre exact sur le statut, quand pertinent |
| `cooperative_id`, `farmer_id`, `plot_id`, `campaign_id`, `crop_id`, `crop_cycle_id`, `type`, `year` | Filtres exacts selon la ressource (voir la collection) |

## 5. Rôles et autorisations (résumé — détail dans `app/Policies/`)

| Rôle | Portée |
|---|---|
| `admin` | Accès total à toutes les ressources (`before()` dans chaque Policy) |
| `cooperative_manager` | CRUD sur agriculteurs/parcelles/cycles/récoltes/intrants **de sa propre coopérative uniquement** ; ne peut ni créer/modifier de coopérative, de culture, variété, campagne ou intrant (données de référence, admin uniquement) ; ne peut pas supprimer une récolte ou une utilisation d'intrant (traçabilité) |
| `farmer` | Lecture seule de ses propres données (parcelles, cycles, récoltes le concernant) |
| `analyst` | Lecture seule globale (tableaux de bord, Phase 7) |

Les endpoints géré en donnée de référence (`crops`, `crop-varieties`, `agricultural-campaigns`,
`inputs`) sont en lecture ouverte à tout utilisateur authentifié, écriture réservée à `admin`.

## 6. Créer rapidement un utilisateur de test par rôle

```bash
php artisan tinker --execute="
App\Models\User::factory()->create(['email' => 'admin@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);
App\Models\User::factory()->create(['email' => 'manager@example.com', 'password' => bcrypt('password'), 'role' => 'cooperative_manager']);
"
```

## 7. Endpoints (référence complète)

Voir `php artisan route:list --path=api` dans le backend pour la liste exacte à jour, ou la
collection Postman ci-dessus qui couvre l'ensemble des 53 routes exposées (Auth + 10 ressources
CRUD).

## 8. Tests automatisés (complément à cette collection)

La collection Postman sert aux tests manuels/exploratoires et à la démonstration. La couverture
automatisée (exécutée en CI/local contre une base PostgreSQL/PostGIS dédiée) vit dans
`backend/tests/Feature/` :

- `AgriculturePlatformApiTest.php` — parcours transverses (auth, autorisation par rôle, calcul
  PostGIS de superficie, stratégie de rendement, conflits d'intégrité).
- `Api/*ApiTest.php` — pagination, validation, 404, soft-delete, par ressource.

```bash
./vendor/bin/phpunit
```

> ⚠️ **Ne jamais utiliser `php artisan test` sur ce projet.** Cette commande boote Laravel
> avec `.env` (donc la base de **développement**) avant que PHPUnit n'applique les
> surcharges `<php><env>` de `phpunit.xml` — `DB_DATABASE=agriculture_mada_test` est alors
> ignoré et `RefreshDatabase` vide la base de développement à chaque exécution (vérifié
> empiriquement le 2026-08-28). Toujours passer par `vendor/bin/phpunit` directement, qui
> respecte correctement `phpunit.xml`.

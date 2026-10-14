<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Point UNIQUE de normalisation du nom d'une culture en `crop_key` stable (cahier des charges
 * §5 : "créer ou centraliser UNE SEULE fonction de normalisation"). Utilisée par StoreCropRequest
 * (auto-génération à la création si `code` est omis) et CropSeeder (backfill des 20 cultures de
 * référence) — c'est la même transformation partout, jamais un slug recalculé différemment
 * selon l'endroit.
 *
 * `Str::slug()` translittère déjà correctement les accents (é/è/ê -> e, ï -> i...) : "Maïs" ->
 * "mais", "Blé" -> "ble", "Niébé" -> "niebe", "Pois du Cap" -> "pois_du_cap", "Sésame" ->
 * "sesame" — vérifié pour les 20 cultures listées dans le cahier des charges.
 *
 * Le résultat n'est qu'une PROPOSITION à la création (voir StoreCropRequest::validated()) :
 * une fois persisté dans `crops.code`, il ne doit plus jamais être recalculé (un renommage de
 * culture ne doit pas casser le lien vers ses modèles déjà entraînés).
 */
class CropKey
{
    public static function fromName(string $name): string
    {
        return Str::slug($name, '_');
    }
}

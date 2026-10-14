<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Vérifie qu'une coordonnée GeoJSON [longitude, latitude] tombe dans une boîte englobante
 * approximative de Madagascar (avec une marge de tolérance).
 *
 * Objectif : détecter les erreurs de saisie grossières (coordonnées inversées lat/lng,
 * valeurs (0,0), point hors du pays) sans jamais inventer ni corriger la valeur —
 * la requête est simplement rejetée avec un message explicite (règle §24 du cahier des charges).
 *
 * Bornes retenues (avec marge) : latitude [-27, -10], longitude [40, 52].
 */
class WithinMadagascarBounds implements ValidationRule
{
    private const LAT_MIN = -27.0;

    private const LAT_MAX = -10.0;

    private const LNG_MIN = 40.0;

    private const LNG_MAX = 52.0;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || count($value) !== 2) {
            $fail("Le champ {$attribute} doit être une paire [longitude, latitude].");

            return;
        }

        [$lng, $lat] = $value;

        if (! is_numeric($lng) || ! is_numeric($lat)) {
            $fail("Le champ {$attribute} doit contenir des coordonnées numériques.");

            return;
        }

        if ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
            $fail("Le champ {$attribute} doit être une coordonnée plausible pour Madagascar (latitude entre ".self::LAT_MIN.' et '.self::LAT_MAX.', longitude entre '.self::LNG_MIN.' et '.self::LNG_MAX.').');
        }
    }
}

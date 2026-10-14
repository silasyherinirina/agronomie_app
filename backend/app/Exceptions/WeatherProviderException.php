<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée quand le fournisseur météo externe (Open-Meteo) est injoignable ou renvoie une erreur.
 * Ne bloque jamais le fonctionnement de base de la plateforme (Module L, extension) : le
 * Controller appelant intercepte cette exception pour renvoyer un état "service indisponible"
 * explicite plutôt qu'une erreur 500 générique (cahier des charges §19, §23).
 */
class WeatherProviderException extends RuntimeException {}

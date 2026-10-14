<?php

namespace App\Services\Reports;

/**
 * `type` absent de la liste des rapports implémentés — jamais un rapport vide inventé pour un
 * type inconnu (même principe que UNKNOWN_CROP côté service IA : §19/§24, aucune donnée
 * fabriquée pour masquer une entrée invalide).
 */
class UnknownReportTypeException extends \InvalidArgumentException
{
    public const SUPPORTED_TYPES = ['production', 'harvests', 'sales'];

    public function __construct(string $type)
    {
        parent::__construct(sprintf(
            "Type de rapport inconnu : '%s'. Types disponibles : %s.",
            $type,
            implode(', ', self::SUPPORTED_TYPES),
        ));
    }
}

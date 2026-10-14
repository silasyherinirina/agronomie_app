<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée quand le service IA (FastAPI) est injoignable ou renvoie une erreur exploitable
 * (ex. 503 "aucun modèle entraîné") — jamais masquée en 500 générique (cahier des charges §23).
 *
 * `code`/`cropKey` relaient l'enveloppe structurée du service IA (voir ai-service/app/errors.py
 * et AiServiceClient::predict()) jusqu'au frontend — cahier des charges §9 : "le frontend doit
 * pouvoir distinguer ces situations", pas seulement afficher un message en texte libre.
 */
class AiServiceException extends RuntimeException
{
    // Nommé `errorCode` (pas `code`) : `Exception::$code` est une propriété interne déjà
    // déclarée par la classe de base — la redéclarer en `readonly` via la promotion de
    // constructeur entre en conflit avec sa gestion interne par le moteur Zend et provoque un
    // crash du process PHP (segfault silencieux), pas une simple erreur PHP propre.
    public function __construct(
        string $message,
        private readonly int $statusCode = 503,
        private readonly ?string $errorCode = null,
        private readonly ?string $cropKey = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function code(): string
    {
        // Valeur de repli pour les échecs de transport (service injoignable) qui n'ont jamais
        // transité par l'enveloppe structurée du service IA — jamais UNKNOWN_CROP/MODEL_* par
        // défaut, qui laisseraient penser à tort qu'une réponse métier a été reçue.
        return $this->errorCode ?? 'AI_SERVICE_UNAVAILABLE';
    }

    public function cropKey(): ?string
    {
        return $this->cropKey;
    }
}

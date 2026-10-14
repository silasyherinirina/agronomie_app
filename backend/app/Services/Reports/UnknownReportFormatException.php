<?php

namespace App\Services\Reports;

class UnknownReportFormatException extends \InvalidArgumentException
{
    public function __construct(string $format)
    {
        parent::__construct(sprintf(
            "Format d'export inconnu : '%s'. Formats disponibles : %s.",
            $format,
            implode(', ', ReportService::SUPPORTED_FORMATS),
        ));
    }
}

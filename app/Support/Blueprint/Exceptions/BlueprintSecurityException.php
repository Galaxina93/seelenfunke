<?php

namespace App\Support\Blueprint\Exceptions;

use RuntimeException;

class BlueprintSecurityException extends RuntimeException
{
    public static function aiWriteDenied(string $modelClass, string $policyLabel): self
    {
        return new self(
            "Architektur-Blueprint Sicherheitsblockade: KI-Schreibzugriff auf Model [{$modelClass}] verweigert (Richtlinie: {$policyLabel})."
        );
    }

    public static function aiReadDenied(string $modelClass, string $policyLabel): self
    {
        return new self(
            "Architektur-Blueprint Sicherheitsblockade: KI-Lesezugriff auf Model [{$modelClass}] verweigert (Richtlinie: {$policyLabel})."
        );
    }
}

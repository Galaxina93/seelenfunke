<?php

namespace App\Support\Blueprint\Concerns;

use App\Support\Blueprint\Exceptions\BlueprintSecurityException;
use App\Support\Blueprint\ModelBlueprint;

trait InteractsWithSystemBlueprint
{
    /**
     * Prüft hart zur Laufzeit, ob ein KI-Agent (Funki) schreibend auf dieses Model zugreifen darf.
     *
     * @throws BlueprintSecurityException
     */
    public static function assertAiWriteAllowed(): void
    {
        $blueprint = static::blueprint();
        if (!$blueprint->aiAccess->allowsAiWrite()) {
            try {
                app(\App\Services\System\SystemBlueprintService::class)
                    ->reportBlueprintGuardrailViolation(static::class, 'WRITE', $blueprint);
            } catch (\Throwable) {
            }
            throw BlueprintSecurityException::aiWriteDenied(static::class, $blueprint->aiAccess->label());
        }
    }

    /**
     * Prüft hart zur Laufzeit, ob ein KI-Agent (Funki) lesend auf dieses Model zugreifen darf.
     *
     * @throws BlueprintSecurityException
     */
    public static function assertAiReadAllowed(): void
    {
        $blueprint = static::blueprint();
        if (!$blueprint->aiAccess->allowsAiRead()) {
            try {
                app(\App\Services\System\SystemBlueprintService::class)
                    ->reportBlueprintGuardrailViolation(static::class, 'READ', $blueprint);
            } catch (\Throwable) {
            }
            throw BlueprintSecurityException::aiReadDenied(static::class, $blueprint->aiAccess->label());
        }
    }

    /**
     * Prüft, ob dieses Model der 10-jährigen GoBD-Aufbewahrungspflicht unterliegt.
     */
    public static function isGoBdProtectedModel(): bool
    {
        return static::blueprint()->isGoBdProtected();
    }

    /**
     * Gibt das serialisierte Blueprint-Array für die 3D-Brain-Map und das DSGVO-VVT zurück.
     */
    public static function blueprintToArray(): array
    {
        /** @var ModelBlueprint $bp */
        $bp = static::blueprint();
        return $bp->toArray();
    }
}

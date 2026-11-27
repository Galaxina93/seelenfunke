<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Log;

class RealtimeBroadcastService
{
    /**
     * @var array<string, int>
     */
    protected static array $logTimestamps = [];

    /**
     * Sendet ein Broadcast-Event sicher und best-effort, sodass ein nicht laufender WebSocket-/Reverb-Server
     * niemals Fachprozesse (z. B. IMAP-Abruf, Bestellungen, Tickets, KI-Tasks oder Telefonie) abbricht.
     */
    public static function dispatch(?object $event, string $context = 'app'): bool
    {
        if ($event === null) {
            return false;
        }

        try {
            broadcast($event);

            return true;
        } catch (\Throwable $e) {
            $safeMessage = class_exists(SystemErrorLogger::class)
                ? SystemErrorLogger::sanitizeSensitiveText((string) $e->getMessage())
                : (string) $e->getMessage();

            $isTransient = class_exists(ResilientPusherBroadcaster::class)
                && ResilientPusherBroadcaster::isTransientTransportFailure($e);

            $logKey = ($isTransient ? 'notice' : 'warning') . '|' . $context . '|' . get_class($event);
            $now = time();
            if (($now - (self::$logTimestamps[$logKey] ?? 0)) >= 300) {
                self::$logTimestamps[$logKey] = $now;
                $line = sprintf(
                    'RealtimeBroadcastService (%s | %s) übersprungen: %s',
                    $context,
                    class_basename($event),
                    $safeMessage
                );
                if ($isTransient) {
                    Log::notice($line);
                } else {
                    Log::warning($line);
                }
            }

            return false;
        }
    }

    public static function resetThrottle(): void
    {
        self::$logTimestamps = [];
    }
}

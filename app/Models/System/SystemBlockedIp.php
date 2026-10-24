<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemBlockedIp extends Model
{
    protected $table = 'system_blocked_ips';

    protected $fillable = [
        'ip_address',
        'reason',
        'blocked_until',
        'blocked_by',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
    ];

    /**
     * Prüft, ob eine IP-Adresse aktuell aktiv blockiert ist.
     * Läuft ein temporärer Ban ab, wird der Eintrag automatisch bereinigt.
     */
    public static function isBlocked(?string $ip): bool
    {
        if (empty($ip) || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return false;
        }

        $entry = static::where('ip_address', $ip)->first();
        if (!$entry) {
            return false;
        }

        if ($entry->blocked_until && $entry->blocked_until->isPast()) {
            $entry->delete();
            return false;
        }

        return true;
    }

    /**
     * Blockiert eine IP-Adresse für x Stunden (oder dauerhaft bei null).
     */
    public static function blockIp(string $ip, ?string $reason = null, ?int $durationHours = 24, ?string $blockedBy = null): self
    {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP) || in_array($ip, ['127.0.0.1', '::1'], true)) {
            throw new \InvalidArgumentException('Ungültige oder geschützte IP-Adresse kann nicht gesperrt werden.');
        }

        $clampedHours = ($durationHours !== null && $durationHours > 0) ? min(8760, $durationHours) : null;
        $blockedUntil = $clampedHours ? now()->addHours($clampedHours) : null;
        $cleanReason = $reason ? mb_substr(trim($reason), 0, 255) : 'Sicherheits-Blockierung (Threat Monitor)';

        return static::updateOrCreate(
            ['ip_address' => $ip],
            [
                'reason' => $cleanReason,
                'blocked_until' => $blockedUntil,
                'blocked_by' => $blockedBy ?? (auth()->check() ? auth()->user()->email ?? auth()->id() : 'System'),
            ]
        );
    }

    /**
     * Hebt die Sperre einer IP-Adresse auf.
     */
    public static function unblockIp(string $ip): bool
    {
        return (bool) static::where('ip_address', $ip)->delete();
    }
}

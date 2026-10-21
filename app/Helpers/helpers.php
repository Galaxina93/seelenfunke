<?php

use Illuminate\Support\Facades\Cache;

function allowed($key):bool
{
    return isset(session("permissions")[$key]);
}

function shop_setting($key, $default = null) {
    try {
        // 60 Sekunden TTL verhindert eingefrorene Caches bei Dateirechte-Problemen,
        // vermeidet aber redundante DB-Abfragen innerhalb desselben Zeitfensters.
        $settings = Cache::remember('global_shop_settings', 60, function() {
            if (!\Illuminate\Support\Facades\Schema::hasTable('shop_settings') && !\Illuminate\Support\Facades\Schema::hasTable('shop-settings')) {
                return [];
            }
            return \App\Models\System\SystemSetting::pluck('value', 'key');
        });
    } catch (\Throwable $e) {
        $settings = [];
    }

    $value = $settings[$key] ?? $default;

    // 1. JSON-Erkennung (bleibt gleich)
    if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
        return json_decode($value, true);
    }

    // 2. NEU: Intelligente Boolean-Erkennung
    // Wandelt "true"/"false", "yes"/"no", "1"/"0" in echte Booleans um
    if ($value === 'true' || $value === 'false') {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    return $value;
}

/**
 * Löst einen Snapshot-Pfad verlässlich auf (unter Berücksichtigung von system/snapshots, Legacy-snapshots und Existenzprüfung).
 * Falls kein Snapshot physisch existiert, wird der optionale Fallback zurückgegeben.
 */
function resolve_snapshot_url(?string $path, ?string $fallback = null): ?string
{
    if (empty($path)) {
        return $fallback;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
        return $path;
    }

    $clean = ltrim(preg_replace('#^/?storage/#', '', $path), '/');

    // Kandidaten in Prioritätsreihenfolge ermitteln
    $candidates = [];
    if (str_starts_with($clean, 'snapshots/')) {
        $candidates[] = 'system/' . $clean;
        $candidates[] = $clean;
    } elseif (str_starts_with($clean, 'system/snapshots/')) {
        $candidates[] = $clean;
        $candidates[] = substr($clean, 7); // 'snapshots/...'
    } else {
        $candidates[] = 'system/snapshots/' . $clean;
        $candidates[] = 'system/' . $clean;
        $candidates[] = $clean;
    }

    foreach ($candidates as $cand) {
        $diskPath = storage_path('app/public/' . $cand);
        if (file_exists($diskPath) && !is_dir($diskPath)) {
            return asset('storage/' . $cand);
        }
    }

    return $fallback;
}

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

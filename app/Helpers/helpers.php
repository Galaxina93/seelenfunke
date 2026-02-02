<?php

use Illuminate\Support\Facades\Cache;

function allowed($key):bool
{
    return isset(session("permissions")[$key]);
}

function shop_setting($key, $default = null) {
    $settings = Cache::rememberForever('global_shop_settings', function() {
        return \App\Models\ShopSetting::pluck('value', 'key');
    });

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

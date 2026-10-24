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
    if (empty($path) || str_contains($path, "\0") || str_contains($path, '..') || str_contains($path, '\\')) {
        return $fallback;
    }

    if (str_starts_with($path, 'data:')) {
        if (preg_match('/^data:image\/(png|jpeg|jpg|webp|gif);base64,[A-Za-z0-9+\/=\r\n]+$/i', $path)) {
            return $path;
        }
        return $fallback;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        if (filter_var($path, FILTER_VALIDATE_URL) && !preg_match('/[\x00-\x1F\x7F"\'<>]/', $path)) {
            return $path;
        }
        return $fallback;
    }

    $clean = ltrim(preg_replace('#^/?storage/#', '', $path), '/');
    if ($clean === '' || str_contains($clean, '..') || str_contains($clean, '\\')) {
        return $fallback;
    }

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

    $publicRoot = realpath(storage_path('app/public'));

    foreach ($candidates as $cand) {
        $diskPath = storage_path('app/public/' . $cand);
        if (file_exists($diskPath) && !is_dir($diskPath)) {
            $realDisk = realpath($diskPath);
            if ($publicRoot && $realDisk && str_starts_with($realDisk, $publicRoot . DIRECTORY_SEPARATOR)) {
                return asset('storage/' . $cand);
            }
        }
    }

    return $fallback;
}

/**
 * Gibt automatisch die zur aktuellen Jahreszeit bzw. zum aktuellen Feiertag passende
 * "Funki"-Grafik zurück (z. B. mit Weihnachtsmütze im Winter, Osterohren zu Ostern,
 * Sonnenhut im Sommer, Herbstlaub im Herbst, Herzen am Valentinstag/Muttertag, Partyhut an Silvester).
 * Fällt bei 'standard' oder nicht vorhandener Variante automatisch auf das Originalbild zurück.
 */
function funki_asset(string $path, ?string $theme = null): string
{
    $clean = ltrim(str_replace(['\\', "\0"], '', $path), '/');
    while (str_contains($clean, '..')) {
        $clean = str_replace('..', '', $clean);
    }
    $clean = ltrim($clean, '/');

    try {
        $activeTheme = $theme ?: (app(\App\Services\ConfiguratorService::class)->getCurrentActiveTheme()['theme_key'] ?? 'standard');
    } catch (\Throwable $e) {
        $activeTheme = 'standard';
    }

    $activeTheme = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $activeTheme) ?: 'standard';

    if ($activeTheme && $activeTheme !== 'standard') {
        if (str_starts_with($clean, 'shop/projekt/funki/')) {
            $subPath = substr($clean, strlen('shop/projekt/funki/'));
            if (!str_starts_with($subPath, 'seasons/')) {
                $seasonalRel = 'shop/projekt/funki/seasons/' . $activeTheme . '/' . $subPath;
                $diskPath = public_path($seasonalRel);
                if (is_file($diskPath)) {
                    return asset($seasonalRel) . '?v=' . filemtime($diskPath);
                }
            }
        } elseif (str_starts_with($clean, 'shop/ai/images/')) {
            $subPath = substr($clean, strlen('shop/ai/images/'));
            if (!str_starts_with($subPath, 'seasons/')) {
                $seasonalRel = 'shop/ai/images/seasons/' . $activeTheme . '/' . $subPath;
                $diskPath = public_path($seasonalRel);
                if (is_file($diskPath)) {
                    return asset($seasonalRel) . '?v=' . filemtime($diskPath);
                }
            }
        }
    }

    $origDisk = public_path($clean);
    $ver = is_file($origDisk) ? '?v=' . filemtime($origDisk) : '';
    return asset($clean) . $ver;
}

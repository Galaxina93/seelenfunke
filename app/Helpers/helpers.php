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
            $dbSettings = \App\Models\System\SystemSetting::pluck('value', 'key')->all();

            // Wenn Versandzonen (LogisticsShippingZone) existieren, sind ausschließlich die Länder
            // aus AKTIVEN Versandzonen für active_countries maßgeblich (Single Source of Truth).
            if (\Illuminate\Support\Facades\Schema::hasTable('logistics_shipping_zones')
                && \Illuminate\Support\Facades\Schema::hasTable('logistics_shipping_zone_countries')
                && \App\Models\Logistics\LogisticsShippingZone::withTrashed()->exists()
            ) {
                $activeCodes = \App\Models\Logistics\LogisticsShippingZoneCountry::whereHas('zone', function ($q) {
                    $q->whereNull('deleted_at')->where('is_active', true);
                })->pluck('country_code')->map(fn($c) => strtoupper((string) $c))->unique()->toArray();

                $allCountries = \App\Livewire\Shop\System\SystemShipping::getAllCountries();
                $activeMap = [];
                foreach ($activeCodes as $code) {
                    if (isset($allCountries[$code])) {
                        $activeMap[$code] = $allCountries[$code];
                    }
                }
                if (empty($activeMap)) {
                    $activeMap = ['DE' => 'Deutschland'];
                }
                $dbSettings['active_countries'] = json_encode($activeMap);
            }

            return $dbSettings;
        });
    } catch (\Throwable $e) {
        $settings = [];
    }

    // Geplanter Wechsel aus der Kleinunternehmerregelung zum Folgejahr automatisch wirksam machen
    if ($key === 'is_small_business') {
        $scheduledEndYear = $settings['small_business_scheduled_end_year'] ?? null;
        if ($scheduledEndYear !== null && $scheduledEndYear !== '' && (int) now()->year > (int) $scheduledEndYear) {
            return false;
        }
        $endYear = $settings['small_business_end_year'] ?? null;
        if ($endYear !== null && $endYear !== '' && (int) now()->year > (int) $endYear) {
            return false;
        }
    }

    $rawDbValue = $settings[$key] ?? null;
    $value = $rawDbValue ?? $default;

    // Falls in der Datenbank ein leerer String ('') gespeichert wurde (z.B. nach Leeren eines optionalen Feldes),
    // greift der übergebene Fallback-Standardwert, sofern ein nicht-leerer Default angegeben wurde.
    $usedDefault = ($rawDbValue === null || $rawDbValue === '');
    if ($value === '' && $default !== null && $default !== '') {
        $value = $default;
    }

    // Normalisierung bei Fallback auf kombinierte Legacy-Felder (owner_city = "38518 Gifhorn", owner_street = "Carl-Goerdeler-Ring 26")
    // sowie bei doppelt gepflegten DB-Werten, damit es in Templates mit separatem company_zip / company_street_number
    // niemals zu "38518 38518 Gifhorn", "Carl-Goerdeler-Ring 26 26" oder "Neue Straße 10 26" kommt.
    if (is_string($value) && $value !== '') {
        if ($key === 'company_city') {
            $value = trim((string) preg_replace('/^\d{4,5}\s+/', '', $value));
        } elseif ($key === 'company_street') {
            if ($usedDefault) {
                $value = trim((string) preg_replace('/\s+\d+[a-zA-Z]?$/', '', $value));
            } else {
                $rawStreetNum = trim((string) ($settings['company_street_number'] ?? ''));
                if ($rawStreetNum !== '' && preg_match('/\s+' . preg_quote($rawStreetNum, '/') . '$/iu', $value)) {
                    $value = trim((string) preg_replace('/\s+' . preg_quote($rawStreetNum, '/') . '$/iu', '', $value));
                }
            }
        } elseif ($key === 'company_street_number' && $usedDefault) {
            $rawStreet = trim((string) ($settings['company_street'] ?? ''));
            if ($rawStreet !== '' && preg_match('/\s+\d+[a-zA-Z]?$/u', $rawStreet)) {
                $value = '';
            }
        }
    }

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

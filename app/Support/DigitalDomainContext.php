<?php

namespace App\Support;

use App\Models\System\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DigitalDomainContext
{
    public const DEFAULT_DIGITAL_HOSTS = [
        'digital.mein-seelenfunke.de',
        'digital.stage.mein-seelenfunke.de',
    ];

    public const SESSION_DOMAIN_KEY = 'sf_domain_override';
    public const SESSION_LOCALE_KEY = 'sf_digital_locale';

    /**
     * Prüft zuverlässig anhand von Hostname, konfiguriertem digital_domain_host
     * oder Vorschau-Parameter (?domain=digital / Reset ?domain=main), ob das
     * eigenständige Digital-Schaufenster aktiv ist.
     */
    public static function isDigitalDomain(?Request $request = null): bool
    {
        $req = $request ?? request();

        $domainQuery = $req->query('domain');
        if (is_string($domainQuery) && $domainQuery !== '') {
            $normalizedQuery = strtolower(trim($domainQuery));
            if ($normalizedQuery === 'digital') {
                self::putSessionSafe($req, self::SESSION_DOMAIN_KEY, 'digital');
                self::syncLocaleFromQuery($req);
                return true;
            }
            if ($normalizedQuery === 'main') {
                self::forgetSessionSafe($req, self::SESSION_DOMAIN_KEY);
                return self::isDigitalHost($req);
            }
        }

        if (self::isDigitalHost($req)) {
            self::syncLocaleFromQuery($req);
            return true;
        }

        $override = self::getSessionSafe($req, self::SESSION_DOMAIN_KEY);
        if ($override === 'digital') {
            self::syncLocaleFromQuery($req);
            return true;
        }

        return false;
    }

    /**
     * Prüft ausschließlich den HTTP-Host gegen die Digital-Subdomains.
     */
    public static function isDigitalHost(?Request $request = null): bool
    {
        $req = $request ?? request();
        $host = strtolower(trim((string) $req->getHost()));
        if ($host === '') {
            return false;
        }

        $allowedHosts = self::DEFAULT_DIGITAL_HOSTS;
        $configuredHost = strtolower(trim((string) self::readSetting('digital_domain_host', 'digital.mein-seelenfunke.de')));
        if ($configuredHost !== '' && !in_array($configuredHost, $allowedHosts, true)) {
            $allowedHosts[] = $configuredHost;
        }

        return in_array($host, $allowedHosts, true);
    }

    /**
     * Prüft den unabhängigen Hauptschalter digital_domain_is_live (Fail-Closed: Standard = false).
     */
    public static function isDigitalDomainLive(): bool
    {
        $raw = self::readSetting('digital_domain_is_live', '0');
        if ($raw === null || $raw === '') {
            return false;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Ermittelt die aktive Sprache auf der Digital-Domain ('de' oder 'en') inkl. ?lang=de|en Persistenz.
     */
    public static function resolveLocale(?Request $request = null): string
    {
        $req = $request ?? request();
        self::syncLocaleFromQuery($req);

        $stored = self::getSessionSafe($req, self::SESSION_LOCALE_KEY);
        if (is_string($stored) && in_array(strtolower($stored), ['de', 'en'], true)) {
            return strtolower($stored);
        }

        return 'de';
    }

    /**
     * Setzt die Sprache ('de' oder 'en') aktiv in der Session (z. B. aus Livewire).
     */
    public static function setLocale(string $locale, ?Request $request = null): string
    {
        $clean = strtolower(trim($locale)) === 'en' ? 'en' : 'de';
        $req = $request ?? request();
        self::putSessionSafe($req, self::SESSION_LOCALE_KEY, $clean);

        return $clean;
    }

    private static function syncLocaleFromQuery(Request $request): void
    {
        $lang = $request->query('lang');
        if (is_string($lang)) {
            $norm = strtolower(trim($lang));
            if (in_array($norm, ['de', 'en'], true)) {
                self::putSessionSafe($request, self::SESSION_LOCALE_KEY, $norm);
            }
        }
    }

    private static function readSetting(string $key, mixed $default = null): mixed
    {
        try {
            if (Schema::hasTable('shop_settings')) {
                $val = SystemSetting::query()->where('key', $key)->value('value');
                if ($val !== null && $val !== '') {
                    return $val;
                }
            }
        } catch (\Throwable $ignore) {
        }

        return function_exists('shop_setting') ? shop_setting($key, $default) : $default;
    }

    private static function getSessionSafe(Request $request, string $key): mixed
    {
        try {
            if ($request->hasSession()) {
                return $request->session()->get($key);
            }
            if (session()->isStarted()) {
                return session($key);
            }
        } catch (\Throwable $ignore) {
        }

        return null;
    }

    private static function putSessionSafe(Request $request, string $key, mixed $value): void
    {
        try {
            if ($request->hasSession()) {
                $request->session()->put($key, $value);
                return;
            }
            session([$key => $value]);
        } catch (\Throwable $ignore) {
        }
    }

    private static function forgetSessionSafe(Request $request, string $key): void
    {
        try {
            if ($request->hasSession()) {
                $request->session()->forget($key);
                return;
            }
            session()->forget($key);
        } catch (\Throwable $ignore) {
        }
    }
}

<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingLinktree extends Model
{
    use HasUuids;

    protected $table = 'marketing_linktrees';

    protected $fillable = [
        'title',
        'url',
        'icon',
        'type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function clicks()
    {
        return $this->hasMany(MarketingLinktreeClick::class, 'link_id');
    }

    /**
     * Löst interne Seelenfunke-URLs (z.B. https://www.mein-seelenfunke.de/shop/)
     * automatisch auf die aktuelle Umgebung (Local, Stage, Live) auf,
     * während externe Links (Instagram, TikTok etc.) unverändert bleiben.
     */
    public function getResolvedUrlAttribute(): string
    {
        return self::resolveEnvironmentUrl((string) $this->url);
    }

    public static function resolveEnvironmentUrl(string $rawUrl): string
    {
        $rawUrl = trim($rawUrl);
        if ($rawUrl === '') {
            return url('/shop');
        }

        // Relative Pfade direkt mit der aktuellen App-URL auflösen
        // Sicherheits-Härtung: Protokoll-relative URLs (//evil.com oder /\evil.com) abfangen!
        if (str_starts_with($rawUrl, '/')) {
            if (str_starts_with($rawUrl, '//') || str_starts_with($rawUrl, '/\\')) {
                return url('/shop');
            }
            $cleanRelative = '/' . ltrim($rawUrl, '/\\');
            $cleanPath = rtrim($cleanRelative, '/');
            return url($cleanPath === '' ? '/' : $cleanPath);
        }

        $parsed = parse_url($rawUrl);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return url('/shop');
        }

        if (!$parsed || empty($parsed['host'])) {
            return $rawUrl;
        }

        $host = strtolower($parsed['host']);
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        $internalHosts = array_filter([
            'mein-seelenfunke.de',
            'www.mein-seelenfunke.de',
            'seelenfunke.de',
            'www.seelenfunke.de',
            'stage.mein-seelenfunke.de',
            'stage.seelenfunke.de',
            'localhost',
            '127.0.0.1',
            $appHost,
        ]);

        if (in_array($host, $internalHosts, true)) {
            $path = $parsed['path'] ?? '/';
            $cleanPath = rtrim($path, '/');
            $resolved = url($cleanPath === '' ? '/' : $cleanPath);

            if (!empty($parsed['query'])) {
                $resolved .= '?' . $parsed['query'];
            }
            if (!empty($parsed['fragment'])) {
                $resolved .= '#' . $parsed['fragment'];
            }

            return $resolved;
        }

        return $rawUrl;
    }
}

<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingTrackingLink extends Model
{
    use HasUuids;

    protected $table = 'marketing_tracking_links';

    protected $fillable = [
        'influencer_id',
        'name',
        'slug',
        'target_url',
        'channel',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'clicks_count',
        'unique_clicks_count',
        'last_clicked_at',
        'is_active',
    ];

    protected $casts = [
        'clicks_count' => 'integer',
        'unique_clicks_count' => 'integer',
        'last_clicked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function influencer()
    {
        return $this->belongsTo(MarketingInfluencer::class, 'influencer_id');
    }

    public function hits()
    {
        return $this->hasMany(MarketingTrackingLinkHit::class, 'tracking_link_id');
    }

    /**
     * Der kurze, teilbare Tracking-Link (z.B. https://mein-seelenfunke.de/t/instagram-bio)
     */
    public function getShortUrlAttribute(): string
    {
        return url('/t/' . $this->slug);
    }

    /**
     * Die aufgelöste, garantiert interne Ziel-URL inkl. UTM-Parametern & Tracking-ID für die Weiterleitung.
     * Schützt /t/{slug} zu 100 % vor Open-Redirect-Schwachstellen auf externe Domains.
     */
    public function getFullUtmUrlAttribute(): string
    {
        $base = self::resolveInternalTargetUrl((string) ($this->target_url ?: '/shop'));

        $params = array_filter([
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign ?: $this->slug,
            'tid' => $this->slug,
        ], fn($v) => $v !== null && $v !== '');

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($params);
    }

    /**
     * Stellt sicher, dass Kurzlinks (/t/{slug}) ausschließlich auf interne Shop-Pfade weiterleiten
     * und niemals für externe Open-Redirects oder Phishing missbraucht werden können.
     */
    public static function resolveInternalTargetUrl(string $rawUrl): string
    {
        $rawUrl = trim($rawUrl);
        if ($rawUrl === '') {
            return url('/shop');
        }

        if (str_starts_with($rawUrl, '/')) {
            if (str_starts_with($rawUrl, '//') || str_starts_with($rawUrl, '/\\')) {
                return url('/shop');
            }
            return MarketingLinktree::resolveEnvironmentUrl($rawUrl);
        }

        $parsed = parse_url($rawUrl);
        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return url('/shop');
        }

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

        if (!in_array($host, $internalHosts, true)) {
            return url('/shop');
        }

        return MarketingLinktree::resolveEnvironmentUrl($rawUrl);
    }

    /**
     * Erfasst einen DSGVO-konformen Klick auf diesen Tracking-Link
     */
    public function recordHit(?string $ip, ?string $userAgent, ?string $referrer): void
    {
        $ipHash = hash('sha256', ($ip ?? '0.0.0.0') . config('app.key') . date('Y-m-d'));
        $agent = strtolower($userAgent ?? '');
        $device = 'desktop';

        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $agent)) {
            $device = 'tablet';
        } elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile|mobile|iphone)/i', $agent)) {
            $device = 'mobile';
        }

        $alreadyClickedToday = $this->hits()
            ->where('ip_hash', $ipHash)
            ->whereDate('created_at', today())
            ->exists();

        $this->hits()->create([
            'ip_hash' => $ipHash,
            'device_type' => $device,
            'referrer' => $referrer ? substr($referrer, 0, 250) : null,
        ]);

        $this->increment('clicks_count');
        if (!$alreadyClickedToday) {
            $this->increment('unique_clicks_count');
        }
        $this->update(['last_clicked_at' => now()]);
    }

    /**
     * Bestimmt automatisch die passende Kanal-Kategorie anhand von Quelle/Medium
     */
    public static function resolveChannel(string $source, ?string $medium = null, ?string $name = null): string
    {
        $s = strtolower(trim($source));
        $m = strtolower(trim($medium ?? ''));
        $n = strtolower(trim($name ?? ''));
        $combined = $s . ' ' . $n;

        if (in_array($m, ['cpc', 'ppc', 'paid', 'ad', 'ads', 'meta_ads', 'google_ads'], true) || str_contains($combined, 'ads')) {
            return 'campaign';
        }

        foreach (['instagram', 'facebook', 'tiktok', 'pinterest', 'youtube', 'linkedin', 'twitter', 'whatsapp', 'telegram', 'threads', 'snapchat'] as $social) {
            if ($s === $social || str_contains($combined, $social)) {
                return 'social';
            }
        }

        if (in_array($s, ['google', 'bing', 'ecosia', 'duckduckgo', 'yahoo'], true) || $m === 'organic') {
            return 'search';
        }

        foreach (['partner', 'referral', 'affiliate', 'influencer', 'kooperation', 'empfehlung'] as $ref) {
            if ($s === $ref || $m === 'referral' || str_contains($combined, $ref)) {
                return 'referral';
            }
        }

        return 'campaign';
    }
}

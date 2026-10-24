<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingTrackingLink extends Model
{
    use HasUuids;

    protected $table = 'marketing_tracking_links';

    protected $fillable = [
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
     * Die aufgelöste Ziel-URL inkl. UTM-Parametern & Tracking-ID für die Weiterleitung
     */
    public function getFullUtmUrlAttribute(): string
    {
        $base = MarketingLinktree::resolveEnvironmentUrl((string) ($this->target_url ?: '/shop'));

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

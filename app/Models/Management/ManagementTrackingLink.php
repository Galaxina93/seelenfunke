<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManagementTrackingLink extends Model
{
    use HasFactory;

    protected $table = 'management_tracking_links';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = static::generateUniqueSlug($model->name ?: $model->utm_source ?: 'link');
            }
            if (empty($model->channel)) {
                $model->channel = static::resolveChannel($model->utm_source ?? '', $model->utm_medium ?? '');
            }
        });
    }

    public static function generateUniqueSlug(string $base): string
    {
        $slugBase = Str::slug($base) ?: 'trk';
        $slugBase = substr($slugBase, 0, 40);
        $candidate = $slugBase;
        $counter = 1;

        while (static::where('slug', $candidate)->exists()) {
            $candidate = $slugBase . '-' . strtolower(Str::random(4));
            $counter++;
            if ($counter > 10) {
                $candidate = 'trk-' . strtolower(Str::random(8));
                break;
            }
        }

        return $candidate;
    }

    public static function inferSourceAndMedium(string $name, ?string $explicitSource = null, ?string $explicitMedium = null): array
    {
        $lower = mb_strtolower(trim($name));

        if ($explicitSource !== null && trim($explicitSource) !== '') {
            $src = strtolower(trim($explicitSource));
            $med = ($explicitMedium !== null && trim($explicitMedium) !== '') ? strtolower(trim($explicitMedium)) : 'link';
            return [$src, $med];
        }

        $socialMap = [
            'whatsapp' => 'whatsapp',
            'whats app' => 'whatsapp',
            'instagram' => 'instagram',
            'insta' => 'instagram',
            'tiktok' => 'tiktok',
            'tik tok' => 'tiktok',
            'facebook' => 'facebook',
            'youtube' => 'youtube',
            'pinterest' => 'pinterest',
            'telegram' => 'telegram',
            'linkedin' => 'linkedin',
        ];

        foreach ($socialMap as $needle => $sourceKey) {
            if (str_contains($lower, $needle)) {
                return [$sourceKey, 'social'];
            }
        }

        if (str_contains($lower, 'newsletter') || str_contains($lower, 'e-mail') || str_contains($lower, 'email') || str_contains($lower, 'mail')) {
            return ['newsletter', 'email'];
        }

        if (str_contains($lower, 'flyer') || str_contains($lower, 'qr') || str_contains($lower, 'print') || str_contains($lower, 'visitenkarte') || str_contains($lower, 'markt')) {
            return ['qr_flyer', 'qr'];
        }

        if (str_contains($lower, 'partner') || str_contains($lower, 'empfehlung') || str_contains($lower, 'influencer') || str_contains($lower, 'affiliate')) {
            return ['partner', 'referral'];
        }

        if (str_contains($lower, 'google')) {
            return ['google_ads', 'cpc'];
        }

        $slugSource = str_replace('-', '_', Str::slug($name));
        return [$slugSource !== '' ? substr($slugSource, 0, 50) : 'direktlink', 'link'];
    }

    public static function resolveChannel(string $source, string $medium = '', string $name = ''): string
    {
        $s = strtolower(trim($source));
        $m = strtolower(trim($medium));
        $n = mb_strtolower(trim($name));
        $combined = $s . ' ' . $n;

        foreach (['whatsapp', 'whats app', 'instagram', 'insta', 'facebook', 'tiktok', 'pinterest', 'youtube', 'linkedin', 'twitter', 'telegram'] as $socialKeyword) {
            if ($s === $socialKeyword || str_contains($combined, $socialKeyword)) {
                return 'social';
            }
        }

        if (in_array($s, ['partner', 'referral', 'affiliate', 'influencer', 'empfehlung'], true) || $m === 'referral' || str_contains($combined, 'partner') || str_contains($combined, 'empfehlung')) {
            return 'referral';
        }

        if (in_array($s, ['google', 'bing', 'ecosia', 'duckduckgo'], true) && $m === 'organic') {
            return 'search';
        }

        return 'campaign';
    }

    public function hits()
    {
        return $this->hasMany(ManagementTrackingLinkHit::class, 'tracking_link_id');
    }

    /**
     * Kurzer, umgebungsabhängiger Tracking-Link (funktioniert lokal, auf Stage und Live).
     */
    public function getShortUrlAttribute(): string
    {
        return url('/t/' . $this->slug);
    }

    /**
     * Aufgelöste Ziel-URL inkl. UTM-Parametern (funktioniert lokal, auf Stage und Live).
     */
    public function getFullUtmUrlAttribute(): string
    {
        $baseUrl = ManagementLinktree::resolveEnvironmentUrl($this->target_url ?: '/shop');

        $parsed = parse_url($baseUrl);
        $existingQuery = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $existingQuery);
        }

        $utmParams = array_filter([
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium ?: 'linktree',
            'utm_campaign' => $this->utm_campaign ?: $this->slug,
            'tid' => $this->slug,
        ], fn($v) => $v !== null && $v !== '');

        $mergedQuery = array_merge($existingQuery, $utmParams);
        $baseWithoutQuery = strtok($baseUrl, '?');

        return $baseWithoutQuery . '?' . http_build_query($mergedQuery);
    }

    /**
     * Erfasst einen Aufruf DSGVO-konform (ohne Speicherung der Klartext-IP, Tages-Hash mit App-Key).
     */
    public function recordHit(?string $ip = null, ?string $userAgent = null, ?string $referrer = null): void
    {
        $rawIp = $ip ?? (request() ? request()->ip() : '0.0.0.0');
        $ua = (string) ($userAgent ?? (request() ? request()->userAgent() : ''));
        $ref = $referrer ?? (request() ? request()->headers->get('referer') : null);

        // DSGVO-konformer Tages-Hash (keine Rückverfolgbarkeit auf reale IP)
        $ipHash = hash('sha256', $rawIp . '|' . config('app.key') . '|' . now()->format('Y-m-d'));

        $deviceType = 'desktop';
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $ua)) {
            $deviceType = 'mobile';
        }

        $alreadyHitToday = $this->hits()
            ->where('ip_hash', $ipHash)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();

        $this->hits()->create([
            'ip_hash' => $ipHash,
            'device_type' => $deviceType,
            'referrer' => $ref ? Str::limit(trim((string) $ref), 250, '') : null,
        ]);

        $this->increment('clicks_count');
        if (!$alreadyHitToday) {
            $this->increment('unique_clicks_count');
        }
        $this->forceFill(['last_clicked_at' => now()])->saveQuietly();
    }
}

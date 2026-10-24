<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManagementLinktree extends Model
{
    use HasFactory;

    protected $table = 'management_linktrees';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'url',
        'icon',
        'type', // standard, secure, highlight
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function clicks()
    {
        return $this->hasMany(ManagementLinktreeClick::class, 'link_id');
    }

    /**
     * Löst interne Shop-Links (z.B. "Zum Shop" oder "/shop") dynamisch für die aktuelle Umgebung
     * (Lokal, Stage oder Live) auf, während externe Links (Instagram, TikTok etc.) unverändert bleiben.
     */
    public function getResolvedUrlAttribute(): string
    {
        return static::resolveEnvironmentUrl((string) $this->url);
    }

    public static function resolveEnvironmentUrl(string $rawUrl): string
    {
        $trimmed = trim($rawUrl);
        if ($trimmed === '') {
            return url('/shop');
        }

        if (str_starts_with($trimmed, '/')) {
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/\\')) {
                return url('/shop');
            }
            return url($trimmed);
        }

        $parsed = parse_url($trimmed);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return url('/shop');
        }
        $host = strtolower(str_replace('www.', '', $parsed['host'] ?? ''));

        $internalHosts = array_filter([
            'mein-seelenfunke.de',
            'seelenfunke.de',
            'stage.mein-seelenfunke.de',
            'stage.seelenfunke.de',
            'localhost',
            '127.0.0.1',
            strtolower(str_replace('www.', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST))),
        ]);

        if ($host !== '' && in_array($host, $internalHosts, true)) {
            $path = '/' . ltrim($parsed['path'] ?? '/shop', '/');
            $resolved = url($path);
            if (!empty($parsed['query'])) {
                $resolved .= '?' . $parsed['query'];
            }
            if (!empty($parsed['fragment'])) {
                $resolved .= '#' . $parsed['fragment'];
            }
            return $resolved;
        }

        return $trimmed;
    }
}

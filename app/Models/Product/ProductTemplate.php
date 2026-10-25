<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Die Tabelle, die mit dem Model verknüpft ist.
     *
     * @var string
     */
    protected $table = 'product_templates';

    /**
     * Die Attribute, die massenweise zugewiesen werden können.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'name',
        'configuration',
        'is_active',
        'holiday',
        'preview_image',
        'is_ai_generated',
    ];

    /**
     * Die Attribute, die in native Typen gecastet werden sollen.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'configuration' => 'array',
        'is_active' => 'boolean',
        'is_ai_generated' => 'boolean',
    ];

    /**
     * Löst einen relativen Storage-Pfad eines Vorlagenbildes in eine Cache-sichere URL (mit ?v=filemtime) auf.
     */
    public static function resolveImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (\Illuminate\Support\Str::startsWith($path, ['shop/', 'shopverwaltung/images/'])) {
            $abs = public_path($path);
            $ver = is_file($abs) ? (int) @filemtime($abs) : 1;
            return asset($path) . '?v=' . $ver;
        }

        $clean = ltrim(preg_replace('/\?.*$/', '', $path), '/');
        if (\Illuminate\Support\Str::startsWith($clean, 'storage/')) {
            $clean = substr($clean, strlen('storage/'));
        }

        $abs = storage_path('app/public/' . $clean);
        $ver = is_file($abs) ? (int) @filemtime($abs) : 1;

        return \Illuminate\Support\Facades\Storage::disk('public')->url($clean) . '?v=' . $ver;
    }

    /**
     * Holt die korrekte öffentliche URL für das Vorschaubild.
     */
    public function getPreviewImageUrlAttribute(): ?string
    {
        return self::resolveImageUrl($this->preview_image);
    }

    /**
     * Holt das Produkt, zu dem diese Vorlage gehört.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

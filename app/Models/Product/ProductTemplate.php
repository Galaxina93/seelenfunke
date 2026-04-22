<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTemplate extends Model
{
    use HasFactory, HasUuids;

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
    ];

    /**
     * Die Attribute, die in native Typen gecastet werden sollen.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'configuration' => 'array', // Wandelt das JSON aus der Datenbank automatisch in ein PHP-Array um
        'is_active' => 'boolean',
    ];

    /**
     * Holt die korrekte öffentliche URL für das Vorschaubild.
     */
    public function getPreviewImageUrlAttribute(): ?string
    {
        if (empty($this->preview_image)) {
            return null;
        }

        // Falls es bereits eine vollständige URL ist (z.B. von S3 oder extern)
        if (\Illuminate\Support\Str::startsWith($this->preview_image, ['http://', 'https://'])) {
            return $this->preview_image;
        }

        // Falls das Bild hart in public/shop/... oder public/images/... liegt
        if (\Illuminate\Support\Str::startsWith($this->preview_image, ['shop/', 'shopverwaltung/images/'])) {
            return asset($this->preview_image);
        }

        // Ansonsten gehen wir davon aus, dass es im Storage liegt
        return \Illuminate\Support\Facades\Storage::url($this->preview_image);
    }

    /**
     * Holt das Produkt, zu dem diese Vorlage gehört.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

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
     * Holt das Produkt, zu dem diese Vorlage gehört.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

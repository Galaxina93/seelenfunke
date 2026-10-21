<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnalyticsEvent extends Model
{
    /**
     * Table associated with the model.
     */
    protected $table = 'product_analytics_events';

    /**
     * Indicates if the model should be timestamped.
     * We manage created_at via database default, no updated_at column needed.
     */
    public $timestamps = false;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'session_hash',
        'source',
        'event_name',
        'step',
        'product_id',
        'template_id',
        'item_identifier',
        'value_cents',
        'device_type',
        'meta_data',
        'created_at',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'step' => 'integer',
        'value_cents' => 'integer',
        'meta_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Relation to Product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Relation to ProductTemplate.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductTemplate::class, 'template_id');
    }
}

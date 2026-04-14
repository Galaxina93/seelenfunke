<?php

namespace App\Models\Marketing;

use App\Models\Shop\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingGoogleAdsCampaign extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'marketing_google_ads_campaigns';

    protected $fillable = [
        'product_id',
        'campaign_name',
        'ad_group_name',
        'keywords',
        'negative_keywords',
        'headline_1',
        'headline_2',
        'headline_3',
        'description_1',
        'description_2',
        'status',
    ];

    protected $casts = [
        'keywords' => 'array',
        'negative_keywords' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

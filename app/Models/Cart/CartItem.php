<?php

namespace App\Models\Cart;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Warenkorb-Position & Gravur-Entwurf',
            description: 'Einzelposition im Warenkorb inklusive der im 2D/3D-Konfigurator erstellten Personalisierungsdaten.',
            department: SystemDepartment::Cart,
            navigationNodes: ['shop.cart', 'shop.checkout'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::BGB_312g_CustomGoods],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: true,
            assetColumns: ['configuration'],
        );
    }

use HasFactory, HasUuids;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'unit_price',
        'configuration', // JSON Cast passiert hier automatisch in Laravel 10/11 oft, aber besser explizit casten
        'config_fingerprint'
    ];

    protected $casts = [
        'configuration' => 'array',
        'unit_price' => 'integer',
        'quantity' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

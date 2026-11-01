<?php

namespace App\Models\Product;


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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnalyticsEvent extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Produkt- & Konfigurator-Interaktionsereignis',
            description: 'Anonymisierte Analyse von Konfigurator-Aufrufen, Vorlagen-Wahl und Warenkorb-Abbrüchen.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.products.analytics'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

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

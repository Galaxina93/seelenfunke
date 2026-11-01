<?php

namespace App\Models\Order;


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

class OrderOrderItem extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Bestellposition & Laser-Gravurauftrag',
            description: 'Einzelposition einer Bestellung inklusive maßstabsgetreuer 2D/3D-Gravur-Konfiguration und Produktionsstatus.',
            department: SystemDepartment::Order,
            navigationNodes: ['admin.orders.index', 'admin.production.laser'],
            lifecycle: LifecycleBehavior::ImmutableAfterFinalized,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::EmployeeProduction, ActorScope::Admin],
            actorsWrite: [ActorScope::EmployeeProduction, ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::BGB_312g_CustomGoods],
            externalChannels: [ExternalChannel::LaserMachine_SVG_PDF],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: true,
            assetColumns: ['configuration'],
        );
    }

use HasFactory, HasUuids;

    protected $table = 'order_order_items';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'is_completed' => 'boolean',
        'completed_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(OrderOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Ermittelt den zum Kaufzeitpunkt gültigen Produktbild-Pfad (eingefrorenes 'purchased_image_path',
     * Variantenbild oder saisonales/Feiertags-Hauptbild zum Bestelldatum), damit sich Bestellbilder
     * bei späterem Jahreszeiten-/Feiertagswechsel niemals rückwirkend verändern.
     */
    public function resolveDisplayImagePath(): ?string
    {
        $conf = is_array($this->configuration) ? $this->configuration : [];

        if (!empty($conf['purchased_image_path']) && is_string($conf['purchased_image_path'])) {
            return $conf['purchased_image_path'];
        }

        if ($this->product) {
            $purchaseDate = ($this->relationLoaded('order') && $this->order && $this->order->created_at)
                ? $this->order->created_at
                : $this->created_at;

            return $this->product->resolveConfigurationImagePath($conf, $purchaseDate);
        }

        return null;
    }

    /**
     * Ermittelt die fertige öffentliche Produktbild-URL zum Kaufzeitpunkt.
     */
    public function resolveDisplayImageUrl(): ?string
    {
        $path = $this->resolveDisplayImagePath();
        return $path ? \App\Models\Product\ProductTemplate::resolveImageUrl($path) : null;
    }
}

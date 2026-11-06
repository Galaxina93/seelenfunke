<?php

namespace App\Models\Product;

use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductIdea extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;
    use HasUuids;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Produkt-Ideen & Sortiments-Planung',
            description: 'Interne Entwurfs- und Kalkulationsmatrix für geplante neue Produkte, Einzel-Komponenten (z. B. Echtgold-Anhänger & Kette), Kauflinks, Original-Foto-/Musterstatus und Ziel-Margen.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.products.ideas'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::UntilRevoked,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::GPSR_EU_2023_988],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $guarded = [];

    protected $casts = [
        'rank' => 'integer',
        'is_top_priority' => 'boolean',
        'purchase_price_net_cents' => 'integer',
        'purchase_price_gross_cents' => 'integer',
        'packaging_cost_cents' => 'integer',
        'shipping_cost_cents' => 'integer',
        'target_price_gross_cents' => 'integer',
        'expected_profit_cents' => 'integer',
        'components_links' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(ProductSupplier::class, 'product_supplier_id');
    }

    /**
     * Berechnet den erwarteten Netto-Reingewinn (vor Werbung) in Cent:
     * Netto-Verkaufspreis (aus Brutto / 1.19)
     * - Netto-Einkaufspreis der Komponenten
     * - Verpackungskosten Netto (Standard: 1,06 € Westpack-Box)
     * - Versandkosten Netto (Standard: 4,50 € DHL Paket versichert)
     * - Laser-/Manufaktur-Pauschale (1,50 € netto)
     * - Payment-Gebühr (~1,5 % + 0,25 €)
     */
    public static function computeExpectedProfitCents(
        int $targetGrossCents,
        int $purchaseNetCents,
        int $packagingNetCents = 106,
        int $shippingNetCents = 450,
        int $laserNetCents = 150
    ): int {
        if ($targetGrossCents <= 0) {
            return 0;
        }

        $netRevenueCents = (int) round($targetGrossCents / 1.19);
        $paymentFeeCents = (int) round($targetGrossCents * 0.015) + 25;
        $totalCostsCents = $purchaseNetCents + $packagingNetCents + $shippingNetCents + $laserNetCents + $paymentFeeCents;

        return $netRevenueCents - $totalCostsCents;
    }
}

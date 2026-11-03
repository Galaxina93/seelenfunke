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

class ProductPackagingItem extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Verpackungsmaterial-Katalog (Kartonagen, Geschenkboxen & Versandtaschen)',
            description: 'Zentraler Stammdatenkatalog für physische Verpackungsmaterialien inkl. Maßen (Post-Tarif-Prüfung), Gebindepreisen, Lieferanten-Nachbestelllinks, LUCID-Materialanteilen und Geschenkbox-Upsells.',
            department: SystemDepartment::Product,
            navigationNodes: ['admin.product-packaging'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::VerpackG_LUCID, ComplianceRegulation::GoBD],
            externalChannels: [ExternalChannel::LUCID_VerpackG],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $guarded = [];

    protected $casts = [
        'length_mm' => 'float',
        'width_mm' => 'float',
        'height_mm' => 'float',
        'weight_grams' => 'integer',
        'primary_weight_grams' => 'integer',
        'secondary_weight_grams' => 'integer',
        'pack_quantity' => 'integer',
        'pack_price_cents' => 'integer',
        'unit_cost_cents' => 'integer',
        'stock_quantity' => 'integer',
        'min_stock_threshold' => 'integer',
        'is_upsell_option' => 'boolean',
        'upsell_price_cents' => 'integer',
        'is_active' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(ProductSupplier::class, 'product_supplier_id');
    }

    public function productPackagings()
    {
        return $this->hasMany(ProductPackaging::class, 'packaging_item_id');
    }

    public static function getCategories(): array
    {
        return [
            'gift_box' => 'Geschenk- & Schmuckbox',
            'shipping_envelope' => 'Versandtasche / Großbrief',
            'shipping_box' => 'Versandkarton',
            'filler' => 'Polster & Füllmaterial',
            'accessory' => 'Klebeband, Sticker & Beileger',
        ];
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::getCategories()[$this->category] ?? 'Sonstige Verpackung';
    }

    public function getFormattedDimensionsAttribute(): string
    {
        if (!$this->length_mm && !$this->width_mm && !$this->height_mm) {
            return 'Keine Maße hinterlegt';
        }

        $l = rtrim(rtrim(number_format((float) $this->length_mm, 1, ',', '.'), '0'), ',');
        $w = rtrim(rtrim(number_format((float) $this->width_mm, 1, ',', '.'), '0'), ',');
        $h = rtrim(rtrim(number_format((float) $this->height_mm, 1, ',', '.'), '0'), ',');

        return "{$l} × {$w} × {$h} mm";
    }

    /**
     * Berechnet automatisch die Post-Versandklasse anhand der Bauhöhe (Schablonen-Check).
     */
    public function getPostalTariffInfoAttribute(): array
    {
        if ($this->category === 'filler' || $this->category === 'accessory') {
            return [
                'badge' => 'Innen- / Zusatzmaterial',
                'color' => 'gray',
                'tariff' => 'Kein eigenes Außenmaß',
                'detail' => 'Zählt zum Gesamtgewicht der Sendung (LUCID)',
            ];
        }

        if (!$this->height_mm || $this->height_mm <= 0) {
            return [
                'badge' => 'Maße offen',
                'color' => 'gray',
                'tariff' => 'Nicht bemaßt',
                'detail' => 'Bitte Länge × Breite × Höhe (mm) eintragen',
            ];
        }

        $h = (float) $this->height_mm;
        $l = (float) ($this->length_mm ?: 0);
        $w = (float) ($this->width_mm ?: 0);
        $fitsLetterFootprint = ($l <= 353 && $w <= 250) || ($w <= 353 && $l <= 250);

        if ($h <= 20 && $fitsLetterFootprint) {
            return [
                'badge' => 'Großbrief-tauglich ≤ 20 mm (' . rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',') . ' mm)',
                'color' => 'emerald',
                'tariff' => 'Deutsche Post Großbrief (1,80 €)',
                'detail' => 'Passt durch die 20-mm-Postschablone (max. 353 × 250 × 20 mm, bis 500 g). Ideal für hohe Marge!',
            ];
        }

        if ($h <= 50 && $fitsLetterFootprint) {
            return [
                'badge' => 'Maxibrief ≤ 50 mm (' . rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',') . ' mm)',
                'color' => 'amber',
                'tariff' => 'Deutsche Post Maxibrief (2,90 €)',
                'detail' => 'Über 20 mm Bauhöhe (kein Großbrief mehr). Versand als Maxibrief bis 50 mm (2,90 €).',
            ];
        }

        if ($h <= 80 && $fitsLetterFootprint) {
            return [
                'badge' => 'DHL Kleinpaket ≤ 80 mm (' . rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',') . ' mm)',
                'color' => 'sky',
                'tariff' => 'DHL Kleinpaket (~3,99 €)',
                'detail' => 'Bis 353 × 250 × 80 mm inkl. Sendungsverfolgung.',
            ];
        }

        return [
            'badge' => 'DHL Paket (' . rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',') . ' mm Höhe)',
            'color' => 'purple',
            'tariff' => 'DHL Paket (~5,49 € – 6,19 €)',
            'detail' => 'Klassischer Paketversand (über 80 mm Bauhöhe oder über 353 × 250 mm Grundfläche).',
        ];
    }
}

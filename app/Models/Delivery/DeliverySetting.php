<?php

namespace App\Models\Delivery;

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

class DeliverySetting extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Lieferzeit- & Produktionskapazitäts-Steuerung',
            description: 'Globale Einstellungen zur Berechnung des voraussichtlichen Lieferfensters im Shop.',
            department: SystemDepartment::Delivery,
            navigationNodes: ['admin.delivery.index'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [ComplianceRegulation::PAngV],
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
        'is_vacation_mode' => 'boolean',
        'is_sick_mode' => 'boolean',
        'vacation_start_date' => 'date',
        'vacation_end_date' => 'date',
    ];

    /**
     * Ermittelt die vollständigen Lieferzeit- und Datumsdetails inklusive Wochentagen
     */
    public static function getDeliveryDetails(): array
    {
        $setting = self::first();
        $activeTime = DeliveryTime::where('is_active', true)->first();

        $min = $activeTime ? (int) $activeTime->min_days : 3;
        $max = $activeTime ? (int) $activeTime->max_days : 5;

        $isVacation = (bool) ($setting && $setting->is_vacation_mode && $setting->vacation_end_date && !$setting->vacation_end_date->copy()->endOfDay()->isPast());
        $isSick = (bool) ($setting && $setting->is_sick_mode);

        if ($isSick) {
            $min += 6;
            $max += 6;
        }

        // Basis-Startdatum: Entweder Urlaubsende oder jetzt
        $baseDate = ($isVacation && !$setting->vacation_end_date->copy()->endOfDay()->isPast())
            ? $setting->vacation_end_date->copy()
            : now();

        $minDate = $baseDate->copy()->addDays($min);
        $maxDate = $baseDate->copy()->addDays($max);

        // Sonntage überspringen (keine Paketzustellung)
        if ($minDate->isSunday()) {
            $minDate->addDay();
        }
        if ($maxDate->isSunday()) {
            $maxDate->addDay();
        }

        // Sicherheitsprüfung: Max-Datum darf nicht vor Min-Datum liegen
        if ($maxDate->lessThan($minDate)) {
            $maxDate = $minDate->copy()->addDay();
        }

        $startDayStr = $minDate->translatedFormat('D, d. M');
        $endDayStr = $maxDate->translatedFormat('D, d. M');
        $dateRange = "ca. {$startDayStr} – {$endDayStr}";

        if ($isVacation) {
            $fullText = "Voraussichtliche Lieferung: {$dateRange}";
        } elseif ($isSick) {
            $fullText = "Voraussichtliche Lieferzeit: {$min}-{$max} Werktage ({$dateRange})";
        } else {
            $fullText = "Lieferzeit: {$min}-{$max} Werktage ({$dateRange})";
        }

        return [
            'min_days' => $min,
            'max_days' => $max,
            'start_date' => $minDate,
            'end_date' => $maxDate,
            'date_range_formatted' => $dateRange,
            'full_text' => $fullText,
            'is_vacation' => $isVacation,
            'is_sick' => $isSick,
            'is_backorder_procurement' => false,
            'supplier_lead_min' => 0,
            'supplier_lead_max' => 0,
            'procurement_badge_label' => null,
            'procurement_info_text' => null,
        ];
    }

    /**
     * Ermittelt die produktspezifischen Lieferzeit-Details:
     * Ist das physische Produkt nicht auf Lager (quantity <= 0 bei aktivem Überverkauf),
     * wird die Lieferanten-Vorlaufzeit (Beschaffungszeit des Rohlings) automatisch zur Manufaktur-Lieferzeit addiert.
     */
    public static function getDeliveryDetailsForProduct(?\App\Models\Product\Product $product = null, int $requestedQty = 1, ?string $variantId = null): array
    {
        $details = self::getDeliveryDetails();

        if (!$product || !$product->requiresSupplierProcurement($requestedQty, $variantId)) {
            return $details;
        }

        $leadRange = $product->getSupplierLeadDaysRange();
        $leadMin = max(1, (int) ($leadRange['min'] ?? 4));
        $leadMax = max($leadMin, (int) ($leadRange['max'] ?? 6));

        $min = (int) $details['min_days'] + $leadMin;
        $max = (int) $details['max_days'] + $leadMax;

        $setting = self::first();
        $isVacation = (bool) ($details['is_vacation'] ?? false);
        $isSick = (bool) ($details['is_sick'] ?? false);

        $baseDate = ($isVacation && $setting && $setting->vacation_end_date && !$setting->vacation_end_date->copy()->endOfDay()->isPast())
            ? $setting->vacation_end_date->copy()
            : now();

        $minDate = $baseDate->copy()->addDays($min);
        $maxDate = $baseDate->copy()->addDays($max);

        if ($minDate->isSunday()) {
            $minDate->addDay();
        }
        if ($maxDate->isSunday()) {
            $maxDate->addDay();
        }
        if ($maxDate->lessThan($minDate)) {
            $maxDate = $minDate->copy()->addDay();
        }

        $startDayStr = $minDate->translatedFormat('D, d. M');
        $endDayStr = $maxDate->translatedFormat('D, d. M');
        $dateRange = "ca. {$startDayStr} bis {$endDayStr}";

        if ($isVacation) {
            $fullText = "Voraussichtliche Lieferung: {$dateRange}";
        } elseif ($isSick) {
            $fullText = "Voraussichtliche Lieferzeit: {$min}-{$max} Werktage ({$dateRange})";
        } else {
            $fullText = "Lieferzeit: {$min}-{$max} Werktage ({$dateRange})";
        }

        $companyCity = trim((string) shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'))) ?: 'Gifhorn';

        return array_merge($details, [
            'min_days' => $min,
            'max_days' => $max,
            'start_date' => $minDate,
            'end_date' => $maxDate,
            'date_range_formatted' => $dateRange,
            'full_text' => $fullText,
            'is_backorder_procurement' => true,
            'supplier_lead_min' => $leadMin,
            'supplier_lead_max' => $leadMax,
            'procurement_badge_label' => 'Auftragsfertigung (Rohling wird auf Bestellung bezogen)',
            'procurement_info_text' => "Für dieses Stück beziehen wir den Rohling nach deiner Bestellung frisch vom Hersteller und veredeln ihn anschließend in unserer Manufaktur in {$companyCity}.",
        ]);
    }

    /**
     * Gibt den fertig berechneten Lieferzeit-Text für den Shop aus (optional produktspezifisch)
     */
    public static function getCurrentDeliveryText(?\App\Models\Product\Product $product = null, int $requestedQty = 1, ?string $variantId = null): string
    {
        if ($product !== null) {
            return self::getDeliveryDetailsForProduct($product, $requestedQty, $variantId)['full_text'];
        }

        return self::getDeliveryDetails()['full_text'];
    }
}

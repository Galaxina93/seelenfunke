<?php

namespace App\Models\Delivery;

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
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class DeliverySetting extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;

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
     * Prüft, ob ein Datum ein bundesweiter gesetzlicher Feiertag in Deutschland ist (keine Post-/DHL-Zustellung).
     */
    public static function isGermanNationalHoliday(CarbonInterface $date): bool
    {
        $md = $date->format('m-d');
        if (in_array($md, ['01-01', '05-01', '10-03', '12-25', '12-26'], true)) {
            return true;
        }

        $year = (int) $date->year;
        $easterSunday = Carbon::createFromDate($year, 3, 21)->addDays(easter_days($year));

        $movableHolidays = [
            $easterSunday->copy()->subDays(2)->format('Y-m-d'), // Karfreitag
            $easterSunday->copy()->addDay()->format('Y-m-d'),    // Ostermontag
            $easterSunday->copy()->addDays(39)->format('Y-m-d'), // Christi Himmelfahrt
            $easterSunday->copy()->addDays(50)->format('Y-m-d'), // Pfingstmontag
        ];

        return in_array($date->format('Y-m-d'), $movableHolidays, true);
    }

    /**
     * Addiert echte deutsche Post-Werktage (Montag bis Samstag, überspringt JEDEN Sonntag & bundesweiten Feiertag).
     */
    public static function addPostalWorkingDays(CarbonInterface $baseDate, int $workingDays): Carbon
    {
        $cursor = Carbon::instance($baseDate)->copy();
        $remaining = max(0, $workingDays);

        while ($remaining > 0) {
            $cursor->addDay();
            if ($cursor->isSunday() || self::isGermanNationalHoliday($cursor)) {
                continue;
            }
            $remaining--;
        }

        // Sicherheitshalber falls workingDays = 0 auf Sonntag/Feiertag fiel
        while ($cursor->isSunday() || self::isGermanNationalHoliday($cursor)) {
            $cursor->addDay();
        }

        return $cursor;
    }

    /**
     * Ermittelt die vollständigen Lieferzeit- und Datumsdetails inklusive echten Werktagen (Mo bis Sa).
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

        $baseDate = ($isVacation && !$setting->vacation_end_date->copy()->endOfDay()->isPast())
            ? $setting->vacation_end_date->copy()
            : now();

        $minDate = self::addPostalWorkingDays($baseDate, $min);
        $maxDate = self::addPostalWorkingDays($baseDate, $max);

        if ($maxDate->lessThan($minDate)) {
            $maxDate = self::addPostalWorkingDays($minDate, 1);
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
     * Ermittelt die produktspezifischen Lieferzeit-Details inkl. Lieferanten-Vorlaufzeit bei Auftragsfertigung.
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

        $minDate = self::addPostalWorkingDays($baseDate, $min);
        $maxDate = self::addPostalWorkingDays($baseDate, $max);

        if ($maxDate->lessThan($minDate)) {
            $maxDate = self::addPostalWorkingDays($minDate, 1);
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

    public static function getCurrentDeliveryText(?\App\Models\Product\Product $product = null, int $requestedQty = 1, ?string $variantId = null): string
    {
        if ($product !== null) {
            return self::getDeliveryDetailsForProduct($product, $requestedQty, $variantId)['full_text'];
        }

        return self::getDeliveryDetails()['full_text'];
    }
}

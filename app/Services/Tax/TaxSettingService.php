<?php

namespace App\Services\Tax;

use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class TaxSettingService
{
    /**
     * Prüft, ob für ein bestimmtes Steuerjahr die Kleinunternehmerregelung gilt.
     * Unter § 19 UStG gilt die Kleinunternehmerregelung zwingend für ein volles Kalenderjahr.
     */
    public function isSmallBusinessForYear(int $year): bool
    {
        $endYear = shop_setting('small_business_end_year');
        if ($endYear !== null && $endYear !== '') {
            $endYear = (int)$endYear;
            // Wenn das Jahr nach dem letzten Kleinunternehmer-Jahr liegt -> Regelbesteuerung
            if ($year > $endYear) {
                return false;
            }
            return true;
        }

        $scheduledEndYear = shop_setting('small_business_scheduled_end_year');
        if ($scheduledEndYear !== null && $scheduledEndYear !== '') {
            $scheduledEndYear = (int)$scheduledEndYear;
            if ($year > $scheduledEndYear) {
                return false;
            }
            return true;
        }

        return (bool)shop_setting('is_small_business', false);
    }

    /**
     * Prüft, ob der Shop aktuell (zum jetzigen Zeitpunkt) als Kleinunternehmer agiert.
     */
    public function isCurrentSmallBusiness(): bool
    {
        $currentYear = (int)now()->year;

        $scheduledEndYear = shop_setting('small_business_scheduled_end_year');
        if ($scheduledEndYear !== null && $scheduledEndYear !== '') {
            if ($currentYear > (int)$scheduledEndYear) {
                return false;
            }
        }

        $endYear = shop_setting('small_business_end_year');
        if ($endYear !== null && $endYear !== '') {
            if ($currentYear > (int)$endYear) {
                return false;
            }
        }

        return (bool)shop_setting('is_small_business', false);
    }

    /**
     * Prüft, ob im angegebenen Steuerjahr ein unterjähriger Wechsel zur Regelbesteuerung stattfand.
     */
    public function isMidyearSwitch(int $year): bool
    {
        $switchYear = shop_setting('small_business_switched_year');
        if ($switchYear !== null && $switchYear !== '') {
            return (int)$switchYear === $year;
        }
        return false;
    }

    /**
     * Gibt das genaue Datum des unterjährigen Wechsels zurück (falls vorhanden).
     */
    public function getMidyearSwitchDate(): ?Carbon
    {
        $dateStr = shop_setting('small_business_switched_at');
        if ($dateStr) {
            try {
                return Carbon::parse($dateStr);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Rechnet die steuerliche Zahllast für ein Brutto-Volumen bei unterjährigem Wechsel heraus.
     * B2C-Kunden haben den Endpreis bezahlt; der Händler schuldet die MwSt aus dem Brutto.
     */
    public function calculateRetroactiveVat(int|float $grossCents, float $taxRate = 19.0): array
    {
        $netCents = (int)round($grossCents / (1 + ($taxRate / 100)));
        $vatCents = (int)($grossCents - $netCents);

        return [
            'gross_cents' => (int)$grossCents,
            'net_cents'   => $netCents,
            'vat_cents'   => $vatCents,
            'rate'        => $taxRate,
        ];
    }

    /**
     * Führt eine Umschaltung des Steuer-Status durch und setzt die Kalenderjahr-Flags.
     *
     * @param bool $enableSmallBusiness Soll Kleinunternehmer aktiviert oder deaktiviert werden?
     * @param string $transitionMode 'immediate_retroactive' oder 'next_year'
     */
    public function applyTransition(bool $enableSmallBusiness, string $transitionMode = 'immediate_retroactive'): void
    {
        $currentYear = (int)now()->year;

        if (!$enableSmallBusiness) {
            // DEAKTIVIERUNG der Kleinunternehmerregelung -> Wechsel zur Regelbesteuerung
            if ($transitionMode === 'next_year') {
                // Wechsel zum 01.01. des Folgejahres
                // Bleibt dieses Jahr noch Kleinunternehmer
                SystemSetting::updateOrCreate(['key' => 'is_small_business'], ['value' => 'true']);
                SystemSetting::updateOrCreate(['key' => 'small_business_scheduled_end_year'], ['value' => (string)$currentYear]);
                SystemSetting::updateOrCreate(['key' => 'small_business_end_year'], ['value' => '']);
                SystemSetting::updateOrCreate(['key' => 'small_business_switched_year'], ['value' => '']);
                SystemSetting::updateOrCreate(['key' => 'small_business_switched_at'], ['value' => '']);
            } else {
                // Unterjähriger sofortiger Wechsel (rückwirkend für das gesamte Steuerjahr)
                SystemSetting::updateOrCreate(['key' => 'is_small_business'], ['value' => 'false']);
                SystemSetting::updateOrCreate(['key' => 'small_business_end_year'], ['value' => (string)($currentYear - 1)]);
                SystemSetting::updateOrCreate(['key' => 'small_business_switched_year'], ['value' => (string)$currentYear]);
                SystemSetting::updateOrCreate(['key' => 'small_business_switched_at'], ['value' => now()->toDateTimeString()]);
                SystemSetting::updateOrCreate(['key' => 'small_business_scheduled_end_year'], ['value' => '']);
            }
        } else {
            // AKTIVIERUNG der Kleinunternehmerregelung
            SystemSetting::updateOrCreate(['key' => 'is_small_business'], ['value' => 'true']);
            SystemSetting::updateOrCreate(['key' => 'small_business_end_year'], ['value' => '']);
            SystemSetting::updateOrCreate(['key' => 'small_business_scheduled_end_year'], ['value' => '']);
            SystemSetting::updateOrCreate(['key' => 'small_business_switched_year'], ['value' => '']);
            SystemSetting::updateOrCreate(['key' => 'small_business_switched_at'], ['value' => '']);
        }

        Cache::forget('global_shop_settings');

        try {
            $freshSettings = SystemSetting::pluck('value', 'key');
            Cache::put('global_shop_settings', $freshSettings, 60);
        } catch (\Throwable $e) {}
    }
}

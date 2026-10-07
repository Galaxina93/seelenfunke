<?php

namespace App\Models\Delivery;

use Illuminate\Database\Eloquent\Model;

class DeliverySetting extends Model
{
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

        $min = $activeTime ? (int)$activeTime->min_days : 3;
        $max = $activeTime ? (int)$activeTime->max_days : 5;

        $isVacation = (bool)($setting && $setting->is_vacation_mode && $setting->vacation_end_date);
        $isSick = (bool)($setting && $setting->is_sick_mode);

        if ($isSick) {
            $min += 6;
            $max += 6;
        }

        // Basis-Startdatum: Entweder Urlaubsende oder jetzt
        $baseDate = ($isVacation && $setting->vacation_end_date->isFuture())
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
        ];
    }

    /**
     * Gibt den fertig berechneten Lieferzeit-Text für den Shop aus
     */
    public static function getCurrentDeliveryText(): string
    {
        return self::getDeliveryDetails()['full_text'];
    }
}

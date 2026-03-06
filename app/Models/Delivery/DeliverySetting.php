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
     * Gibt den fertig berechneten Lieferzeit-Text für den Shop aus
     */
    public static function getCurrentDeliveryText()
    {
        $setting = self::first();
        $activeTime = DeliveryTime::where('is_active', true)->first();

        $min = $activeTime ? $activeTime->min_days : 3;
        $max = $activeTime ? $activeTime->max_days : 5;

        if ($setting && $setting->is_vacation_mode && $setting->vacation_end_date) {
            $minDate = $setting->vacation_end_date->copy()->addDays($min)->format('d.m.Y');
            $maxDate = $setting->vacation_end_date->copy()->addDays($max)->format('d.m.Y');
            return "Voraussichtliche Lieferung: {$minDate} - {$maxDate}";
        }

        if ($setting && $setting->is_sick_mode) {
            $min += 6;
            $max += 6;
            return "Voraussichtliche Lieferzeit: {$min}-{$max} Tage (geschätzt)";
        }

        return "Lieferzeit: {$min}-{$max} Tage";
    }
}

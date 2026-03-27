<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    // Explizite Angabe der Tabelle mit Bindestrich - korrigiert
    protected $table = 'shop_settings';

    protected $fillable = ['key', 'value'];
}

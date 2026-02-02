<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSetting extends Model
{
    // Explizite Angabe der Tabelle mit Bindestrich
    protected $table = 'shop-settings';

    protected $fillable = ['key', 'value'];
}

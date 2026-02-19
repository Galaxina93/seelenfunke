<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserDevice extends Model
{
    protected $fillable = ['userable_id', 'userable_type', 'fcm_token', 'device_name'];

    /**
     * Holt den Besitzer des Geräts (Admin, Customer oder Employee).
     */
    public function userable(): MorphTo
    {
        return $this->morphTo();
    }
}

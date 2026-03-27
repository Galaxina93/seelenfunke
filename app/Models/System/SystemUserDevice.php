<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemUserDevice extends Model
{
    protected $table = 'system_user_devices';
    protected $fillable = ['userable_id', 'userable_type', 'fcm_token', 'device_name'];

    /**
     * Holt den Besitzer des Geräts (Admin, Customer oder Employee).
     */
    public function userable(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSession extends Model
{
    use HasFactory;

    protected $table = 'system_sessions';

    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'payload',
        'last_activity'
    ];

    public function user()
    {
        return $this->belongsTo(SystemUser::class);
    }
}

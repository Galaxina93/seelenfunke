<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemLoginAttempt extends Model
{
    protected $table = 'system_login_attempts';
    protected $fillable = [
        'email',
        'ip_address',
        'success',
        'attempted_at',
    ];

    public $timestamps = true;
}

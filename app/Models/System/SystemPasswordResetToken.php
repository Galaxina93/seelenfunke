<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPasswordResetToken extends Model
{
    use HasFactory;

    protected $table = 'system_password_reset_tokens';
}

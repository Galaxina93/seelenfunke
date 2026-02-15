<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemCheckConfig extends Model
{
    protected $fillable = [
        'user_id',
        'filter_type',
        'date_start',
        'date_end',
        'range_mode'
    ];
}

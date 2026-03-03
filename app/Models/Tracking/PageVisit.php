<?php

namespace App\Models\Tracking;

use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    protected $fillable = [
        'session_id',
        'ip_hash',
        'url',
        'path',
        'method',
        'user_agent',
        'referer',
        'customer_id',
    ];
}

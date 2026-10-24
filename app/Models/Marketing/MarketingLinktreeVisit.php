<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingLinktreeVisit extends Model
{
    protected $table = 'marketing_linktree_visits';

    protected $fillable = [
        'ip_hash',
        'referrer',
        'device_type',
    ];
}

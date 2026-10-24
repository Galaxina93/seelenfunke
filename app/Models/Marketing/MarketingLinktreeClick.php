<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class MarketingLinktreeClick extends Model
{
    protected $table = 'marketing_linktree_clicks';

    protected $fillable = [
        'link_id',
        'ip_hash',
        'device_type',
    ];

    public function link()
    {
        return $this->belongsTo(MarketingLinktree::class, 'link_id');
    }
}

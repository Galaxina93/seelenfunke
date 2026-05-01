<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagementLinktreeClick extends Model
{
    use HasFactory;

    protected $table = 'management_linktree_clicks';

    protected $fillable = [
        'link_id',
        'ip_hash',
        'device_type',
    ];

    public function link()
    {
        return $this->belongsTo(ManagementLinktree::class, 'link_id');
    }
}

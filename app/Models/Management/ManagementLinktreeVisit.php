<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagementLinktreeVisit extends Model
{
    use HasFactory;

    protected $table = 'management_linktree_visits';

    protected $fillable = [
        'ip_hash',
        'referrer',
        'device_type',
    ];
}

<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SystemNeuralNode extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'file_path',
        'name',
        'group_id',
        'dependencies',
        'methods',
        'properties',
        'content_hash',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'methods' => 'array',
        'properties' => 'array',
    ];
}

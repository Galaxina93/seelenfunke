<?php

namespace App\Models\Funki;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FunkiraToolUsage extends Model
{
    use HasFactory;

    protected $table = 'funkira_tool_usages';

    protected $fillable = [
        'tool_name',
        'used_at',
        'context',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'context' => 'array',
    ];
}

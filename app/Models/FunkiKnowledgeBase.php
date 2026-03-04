<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FunkiKnowledgeBase extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'category',
        'content',
        'tags',
        'is_published',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_published' => 'boolean',
    ];
}

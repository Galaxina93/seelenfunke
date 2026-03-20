<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiKnowledgeBase extends Model
{
    use HasUuids;
    protected $fillable = [
        'title',
        'slug',
        'ai_knowledge_base_category_id',
        'content',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(AiKnowledgeBaseCategory::class, 'ai_knowledge_base_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(AiKnowledgeBaseTag::class, 'ai_knowledge_base_ai_knowledge_base_tag');
    }
}

<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiKnowledgeBaseTag extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'slug'];

    public function articles()
    {
        return $this->belongsToMany(AiKnowledgeBase::class, 'ai_knowledge_base_ai_knowledge_base_tag');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiKnowledgeBaseTag extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'slug'];

    public function articles()
    {
        return $this->belongsToMany(AiKnowledgeBase::class, 'ai_knowledge_base_ai_knowledge_base_tag');
    }
}

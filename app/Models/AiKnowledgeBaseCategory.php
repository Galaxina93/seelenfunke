<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiKnowledgeBaseCategory extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'slug'];

    public function articles()
    {
        return $this->hasMany(AiKnowledgeBase::class, 'ai_knowledge_base_category_id');
    }
}

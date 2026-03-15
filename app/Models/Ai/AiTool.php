<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiTool extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'identifier',
        'description',
    ];

    public function agents()
    {
        return $this->belongsToMany(AiAgent::class, 'ai_agent_tool')
                    ->withTimestamps();
    }
}

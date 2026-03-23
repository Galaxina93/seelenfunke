<?php

namespace App\Models\Ai\Health;

use App\Models\Ai\AiAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiHealthProtocol extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ai_agent_id',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}

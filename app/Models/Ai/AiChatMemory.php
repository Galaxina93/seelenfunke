<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiChatMemory extends Model
{
    use HasUuids;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'funkira_chat_memories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'session_id',
        'role',
        'content',
        'context_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'context_data' => 'array',
    ];
}

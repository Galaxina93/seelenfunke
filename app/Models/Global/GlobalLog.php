<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalLog extends Model
{
    protected $table = 'logs';

    protected $fillable = [
        'ai_agent_id',
        'type',
        'action_id',
        'title',
        'message',
        'status',
        'payload',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Relation zum ausführenden KI-Agenten
     */
    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }

    /**
     * Helper um einen Log-Eintrag schnell zu starten
     */
    public static function start($actionId, $title, $type = 'automation')
    {
        return self::create([
            'action_id' => $actionId,
            'title' => $title,
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Helper um einen laufenden Log zu beenden
     */
    public function finish($status = 'success', $message = null, $payload = null)
    {
        $this->update([
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'finished_at' => now(),
        ]);
    }
}

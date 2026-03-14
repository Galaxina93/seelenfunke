<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskList extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'icon',
        'color'
    ];

    /**
     * Alle Aufgaben dieser Liste.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Nur die Hauptaufgaben (ohne Eltern-Task).
     */
    public function rootTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id');
    }
}

<?php

namespace App\Models\Management;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementTaskList extends Model
{
    use HasUuids;

    protected $table = 'management_task_lists';

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
        return $this->hasMany(ManagementTask::class, 'task_list_id');
    }

    /**
     * Nur die Hauptaufgaben (ohne Eltern-Task).
     */
    public function rootTasks(): HasMany
    {
        return $this->hasMany(ManagementTask::class, 'task_list_id')->whereNull('parent_id');
    }
}

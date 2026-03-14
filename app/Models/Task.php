<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasUuids;

    protected $fillable = [
        'task_list_id',
        'parent_id',
        'title',
        'is_completed',
        'position',
        'priority'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    /**
     * Die Liste, zu der diese Aufgabe gehört.
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'task_list_id');
    }

    /**
     * Falls es ein Schritt ist: Die übergeordnete Aufgabe.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Die Schritte (Unteraufgaben) dieser Aufgabe.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')
            ->orderBy('is_completed', 'asc')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Hilfsmethode: Ist dies eine Hauptaufgabe?
     */
    public function isRootTask(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Hilfsmethode: Ist dies ein Unterschritt?
     */
    public function isSubtask(): bool
    {
        return !is_null($this->parent_id);
    }
}

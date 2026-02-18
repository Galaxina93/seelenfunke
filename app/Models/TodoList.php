<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TodoList extends Model
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
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    /**
     * Nur die Hauptaufgaben (ohne Eltern-Task).
     */
    public function rootTodos(): HasMany
    {
        return $this->hasMany(Todo::class)->whereNull('parent_id');
    }
}

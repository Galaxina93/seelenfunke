<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiArtifact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'session_id',
        'name',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiHealthMedication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'active_ingredients',
        'dosage',
        'frequency',
        'is_long_term',
        'image_path',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\Admin\Admin::class, 'user_id');
    }

    protected $casts = [
        'is_long_term' => 'boolean',
    ];
}

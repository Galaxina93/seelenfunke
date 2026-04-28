<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiDepartment extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'color',
        'order_index',
    ];

    public function roles()
    {
        return $this->hasMany(AiRole::class, 'ai_department_id')->orderBy('name');
    }

    public function agents()
    {
        return $this->hasMany(AiAgent::class, 'ai_department_id')->orderBy('name');
    }
}

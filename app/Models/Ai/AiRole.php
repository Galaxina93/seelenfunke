<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiRole extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ai_department_id',
        'name',
        'description',
    ];

    public function agents()
    {
        return $this->hasMany(AiAgent::class);
    }

    public function tools()
    {
        return $this->belongsToMany(AiTool::class, 'ai_role_tool')
                    ->withTimestamps();
    }

    public function department()
    {
        return $this->belongsTo(AiDepartment::class, 'ai_department_id');
    }
}

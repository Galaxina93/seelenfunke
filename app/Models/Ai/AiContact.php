<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiContact extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'phone_number',
        'email',
        'company',
        'notes',
    ];

    public function calls()
    {
        return $this->hasMany(AiCall::class);
    }
}

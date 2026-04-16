<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AiUserWorkspaceSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'chat_height_percent',
    ];
}

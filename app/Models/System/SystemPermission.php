<?php

namespace App\Models\System;

use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemPermission extends Model
{
    use HasFactory;

    protected $table = 'system_permissions';

    protected $fillable = ['name'];

    public $incrementing = false; // Deaktivieren Sie das Inkrementieren des Primärschlüssels
    protected $keyType = 'string'; // Setzen Sie den Primärschlüsseltyp auf 'string'

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SystemRole::class, 'permission_role', 'permission_id', 'role_id');
    }

}

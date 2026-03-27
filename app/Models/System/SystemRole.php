<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'system_roles';

    public $incrementing = false; // Deaktivieren Sie das Inkrementieren des Primärschlüssels
    protected $keyType = 'string'; // Setzen Sie den Primärschlüsseltyp auf 'string'

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });
    }

    protected $fillable = ['name'];

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SystemPermission::class, 'permission_role', 'role_id', 'permission_id');
    }

}

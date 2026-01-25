<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class Admin extends Model implements Authenticatable
{
    use HasFactory, SoftDeletes, AuthenticatableTrait;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    public $incrementing = false; // Deaktivieren Sie das Inkrementieren des Primärschlüssels
    protected $keyType = 'string'; // Setzen Sie den Primärschlüsseltyp auf 'string'

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });

        // Event-Listener für das erstellen eines Admin Profiles
        static::created(function (Admin $admin) {
            $adminProfile = new AdminProfile();
            $admin->profile()->save($adminProfile);

            $adminRole = Role::where('name', 'admin')->first();
            $admin->roles()->attach($adminRole->id);

        });

        // Event-Listener für das Löschen eines Admins Profiles
        static::deleting(function (Admin $admin) {
            $admin->profile()->delete();
        });
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'roleable');
    }

    public function directories(): MorphToMany
    {
        return $this->morphToMany(\App\Models\Directory::class, 'user', 'directory_user');
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

}

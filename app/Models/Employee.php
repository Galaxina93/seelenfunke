<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class Employee extends Model implements Authenticatable
{
    use HasFactory, SoftDeletes, AuthenticatableTrait;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });

        // Event-Listener für das Erstellen eines Employee Profiles
        static::created(function (Employee $employee) {
            $employeeProfile = new EmployeeProfile();
            $employee->profile()->save($employeeProfile);

            $employeeRole = Role::where('name', 'employee')->first();
            $employee->roles()->attach($employeeRole->id);
        });

        // Event-Listener für das Löschen eines Employee Profiles
        static::deleting(function (Employee $employee) {
            $employee->profile()->delete();
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
        return $this->hasOne(EmployeeProfile::class);
    }
}

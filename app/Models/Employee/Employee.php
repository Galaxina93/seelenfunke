<?php

namespace App\Models\Employee;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Ramsey\Uuid\Uuid;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Model implements Authenticatable
{
    use HasFactory, SoftDeletes, AuthenticatableTrait, HasApiTokens, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
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
        });

        // Event-Listener für das Löschen eines Employee Profiles
        static::deleting(function (Employee $employee) {
            if ($employee->isForceDeleting()) {
                $employee->profile()->withTrashed()->forceDelete();
            } elseif ($employee->profile) {
                $employee->profile()->delete();
            }
        });

        // Event-Listener für das Wiederherstellen eines Employee Profiles
        static::restoring(function (Employee $employee) {
            $employee->profile()->withTrashed()->restore();
        });
    }

    
    public function directories(): MorphToMany
    {
        return $this->morphToMany(\App\Models\System\SystemDirectory::class, 'user', 'directory_user');
    }

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }
}

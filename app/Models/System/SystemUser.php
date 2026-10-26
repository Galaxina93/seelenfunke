<?php

namespace App\Models\System;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

class SystemUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'system_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'rememberToken',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'emailVerifiedAt' => 'datetime',
    ];

    function getGuard(): int|string|null
    {
        $guards = array_keys(config('auth.guards'));

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    function getUserModelByGuard($guard): string
    {
        $models = [
            'web' => SystemUser::class,
            'admin' => Admin::class,
            'customer' => Customer::class,
            'employee' => Employee::class,
        ];

        return $models[$guard] ?? SystemUser::class;
    }

    function getCurrentUser()
    {
        $guard = $this->getGuard();
        return Auth::guard($guard)->user();
    }

    /**
     * Prüft über alle Account-Tabellen (Admins, Employees, Customers inkl. Soft-Deletes) hinweg,
     * ob eine E-Mail-Adresse bereits einem bestehenden Konto gehört.
     */
    public static function isEmailTakenAcrossGuards(string $email, ?string $ignoreGuard = null, ?string $ignoreId = null): bool
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '') {
            return false;
        }

        $guardMap = [
            'admin' => Admin::class,
            'employee' => Employee::class,
            'customer' => Customer::class,
        ];

        foreach ($guardMap as $guard => $modelClass) {
            $query = $modelClass::withTrashed()->whereRaw('LOWER(email) = ?', [$normalized]);
            if ($ignoreGuard === $guard && !empty($ignoreId)) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

}

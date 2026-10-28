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
        'role',
        'email_verified_at',
        'password',
        'google_id',
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

    public function influencerProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Marketing\MarketingInfluencer::class, 'employee_id');
    }

    public function employmentContract(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeContract::class, 'employee_id');
    }

    public function timeEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeTimeEntry::class, 'employee_id');
    }

    public function payslips(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeePayslip::class, 'employee_id');
    }

    public function isInfluencer(): bool
    {
        return ($this->role ?? 'internal') === 'influencer';
    }

    public function isTaxAdvisor(): bool
    {
        return ($this->role ?? 'internal') === 'tax_advisor';
    }

    public function isInternalEmployee(): bool
    {
        return !in_array($this->role ?? 'internal', ['influencer', 'tax_advisor'], true);
    }

    public function isAccountActive(): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($this->isInfluencer()) {
            if (\Illuminate\Support\Facades\Schema::hasTable('marketing_influencers')) {
                $inf = $this->relationLoaded('influencerProfile')
                    ? $this->influencerProfile
                    : $this->influencerProfile()->first();
                if ($inf && (!$inf->is_active || $inf->is_archived)) {
                    return false;
                }
            }
            return true;
        }

        if ($this->isInternalEmployee()) {
            if (\Illuminate\Support\Facades\Schema::hasTable('employee_contracts')) {
                $contract = $this->relationLoaded('employmentContract')
                    ? $this->employmentContract
                    : $this->employmentContract()->first();
                if ($contract && (!$contract->is_active || $contract->is_archived)) {
                    return false;
                }
            }
            return true;
        }

        return true;
    }

    public function revokeActiveSessionsAndTokens(): void
    {
        if (method_exists($this, 'tokens')) {
            $this->tokens()->delete();
        }

        if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable(config('session.table', 'sessions'))) {
            \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->delete();
        }
    }

    public function hasAiDataConsent(): bool
    {
        return (bool) ($this->profile?->ai_data_consent ?? false);
    }

    public function updateAiDataConsent(bool $consent, string $source = 'profile'): void
    {
        $profile = $this->profile ?: $this->profile()->create([]);
        $profile->update([
            'ai_data_consent' => $consent,
            'ai_data_consent_at' => $consent ? now() : null,
        ]);
        $this->setRelation('profile', $profile->fresh());
    }
}

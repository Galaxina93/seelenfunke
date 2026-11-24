<?php

namespace App\Models\Employee;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Ramsey\Uuid\Uuid;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Model implements Authenticatable, HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Mitarbeiter-Konto (Manufaktur & Support)',
            description: 'Zugangskonto für Mitarbeiter in der Laser-Produktion, Verpackung und Kundenbetreuung.',
            department: SystemDepartment::Employee,
            navigationNodes: ['admin.employees.index'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::None,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::GoBD],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

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

    public function canAccessFunkenwerkDomain(string $domain): bool
    {
        if (!$this->isInternalEmployee() || !$this->isAccountActive()) {
            return false;
        }

        $perms = $this->buildFunkenwerkPermissionsPayload();

        return match ($domain) {
            'tasks', 'todo'       => (bool) ($perms['can_access_tasks'] ?? false),
            'production', 'orders'=> (bool) ($perms['can_access_production'] ?? false),
            'support'             => (bool) ($perms['can_access_support'] ?? false),
            'inventory'           => (bool) ($perms['can_access_inventory'] ?? false),
            'time'                => (bool) ($perms['can_access_time'] ?? false),
            'payslips'            => (bool) ($perms['can_access_payslips'] ?? false),
            'ota_download'        => true,
            default               => false,
        };
    }

    /**
     * Erzeugt die 1:1 Rechte-Matrix analog zum Web-Mitarbeiter-Dashboard (EmployeeDashboard)
     * für Funkenwerk Windows & Funkenwerk Android.
     *
     * @return array<string, mixed>
     */
    public function buildFunkenwerkPermissionsPayload(): array
    {
        if (!$this->isInternalEmployee() || !$this->isAccountActive()) {
            return [
                'role'                  => $this->role ?? 'external',
                'is_admin'              => false,
                'is_internal_employee'  => false,
                'can_access_tasks'      => false,
                'can_access_production' => false,
                'can_access_support'    => false,
                'can_access_inventory'  => false,
                'can_access_time'       => false,
                'can_access_payslips'   => false,
                'allowed_spaces'        => [],
                'allowed_modules'       => [],
            ];
        }

        $contract = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('employee_contracts')) {
            $contract = $this->relationLoaded('employmentContract')
                ? $this->employmentContract
                : $this->employmentContract()->first();
        }

        $canTasks = $contract ? (bool) ($contract->can_access_tasks ?? true) : true;
        $canProduction = $contract ? (bool) ($contract->can_access_production ?? false) : false;
        $canSupport = $contract ? (bool) ($contract->can_access_support ?? false) : false;
        $canInventory = $contract ? (bool) ($contract->can_access_inventory ?? false) : false;
        $hasContract = $contract !== null;

        $allowedSpaces = ['launcher'];
        if ($canTasks) {
            $allowedSpaces[] = 'todo';
        }
        if ($canProduction) {
            $allowedSpaces[] = 'orders';
        }

        $allowedModules = ['pdf'];
        if ($canProduction) {
            $allowedModules[] = 'drucker';
            $allowedModules[] = '3d';
        }

        return [
            'role'                  => 'employee',
            'is_admin'              => false,
            'is_internal_employee'  => true,
            'can_access_tasks'      => $canTasks,
            'can_access_production' => $canProduction,
            'can_access_support'    => $canSupport,
            'can_access_inventory'  => $canInventory,
            'can_access_time'       => $hasContract,
            'can_access_payslips'   => $hasContract,
            'allowed_spaces'        => array_values(array_unique($allowedSpaces)),
            'allowed_modules'       => array_values(array_unique($allowedModules)),
        ];
    }

    /**
     * Einheitlicher Permissions-Builder für Admin, Employee und Customer (für /api/login & /api/user).
     *
     * @return array<string, mixed>
     */
    public static function buildPermissionsForUser(mixed $user): array
    {
        if ($user instanceof \App\Models\Admin\Admin) {
            return [
                'role'                  => 'admin',
                'is_admin'              => true,
                'is_internal_employee'  => false,
                'can_access_tasks'      => true,
                'can_access_production' => true,
                'can_access_support'    => true,
                'can_access_inventory'  => true,
                'can_access_time'       => true,
                'can_access_payslips'   => true,
                'allowed_spaces'        => ['*'],
                'allowed_modules'       => ['*'],
            ];
        }

        if ($user instanceof self) {
            return $user->buildFunkenwerkPermissionsPayload();
        }

        return [
            'role'                  => 'customer',
            'is_admin'              => false,
            'is_internal_employee'  => false,
            'can_access_tasks'      => false,
            'can_access_production' => false,
            'can_access_support'    => false,
            'can_access_inventory'  => false,
            'can_access_time'       => false,
            'can_access_payslips'   => false,
            'allowed_spaces'        => [],
            'allowed_modules'       => [],
        ];
    }
}

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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class EmployeeProfile extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Mitarbeiter-Personalakte',
            description: 'Privatanschrift, Bankverbindung, Steuer-ID und Sozialversicherungsdaten des Mitarbeiters.',
            department: SystemDepartment::Employee,
            navigationNodes: ['admin.employees.index'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::FinancialConfidential,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::None,
            actorsRead: [ActorScope::Admin],
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

use HasFactory, SoftDeletes;

    protected $fillable = [
        'id', 'employee_id', 'is_business', 'company_name', 'vat_id', 'internal_note',
        'photo_path', 'about', 'url', 'phone_number', 'street', 'house_number',
        'postal', 'city', 'country', 'two_factor_is_active', 'two_factor_secret',
        'two_factor_recovery_codes', 'email_verified_at', 'last_seen', 'last_ip',
        'ai_data_consent', 'ai_data_consent_at'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'is_business' => 'boolean',
        'ai_data_consent' => 'boolean',
        'ai_data_consent_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Uuid::uuid4();
        });
    }

    protected $hidden = [
        'password', 'rememberToken', 'two_factor_recovery_codes', 'two_factor_secret', 'internal_note', 'vat_id',
    ];

    public function getVatIdAttribute($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString((string) $value);
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public function setVatIdAttribute($value): void
    {
        if ($value === null || trim((string) $value) === '') {
            $this->attributes['vat_id'] = null;
            return;
        }
        $this->attributes['vat_id'] = \Illuminate\Support\Facades\Crypt::encryptString(trim((string) $value));
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

<?php

namespace App\Models\Admin;


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

class AdminProfile extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Administrator-Profil & Sicherheitspräferenzen',
            description: 'Persönliche Profileinstellungen, Benachrichtigungspräferenzen und DSGVO-KI-Freigaben des Administrators.',
            department: SystemDepartment::Admin,
            navigationNodes: ['admin.profile'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::None,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, softDeletes;

    protected $fillable = [
        'id', 'admin_id', 'is_business', 'company_name', 'vat_id', 'internal_note',
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
        'password', 'rememberToken', 'two_factor_recovery_codes', 'two_factor_secret', 'internal_note',
    ];

    public function admin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

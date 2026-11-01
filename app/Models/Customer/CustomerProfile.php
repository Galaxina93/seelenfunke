<?php

namespace App\Models\Customer;


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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CustomerProfile extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kundenprofil & DSGVO-Einwilligungen',
            description: 'Gespeicherte Rechnungs-/Lieferadressen, Telefonnummern sowie dokumentierte Werbe- und KI-Datenschutz-Freigaben.',
            department: SystemDepartment::Customer,
            navigationNodes: ['customer.profile', 'admin.customers.index'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Customer, ActorScope::Admin],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: true,
            customerForeignKey: 'customer_id',
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids, softDeletes;

    protected $fillable = [
        'id', 'customer_id', 'is_business', 'company_name', 'vat_id', 'internal_note',
        'birthday', 'photo_path', 'about', 'url', 'phone_number', 'street',
        'house_number', 'postal', 'city', 'country', 'two_factor_is_active',
        'two_factor_secret', 'two_factor_recovery_codes', 'email_verified_at', 'last_seen', 'last_ip',
        'cart_reminder_unsubscribed_at', 'ai_data_consent', 'ai_data_consent_at',
        'receive_birthday_emails', 'receive_anniversary_emails',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'is_business' => 'boolean',
        'birthday' => 'date',
        'cart_reminder_unsubscribed_at' => 'datetime',
        'ai_data_consent' => 'boolean',
        'ai_data_consent_at' => 'datetime',
        'receive_birthday_emails' => 'boolean',
        'receive_anniversary_emails' => 'boolean',
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

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

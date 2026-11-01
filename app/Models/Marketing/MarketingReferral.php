<?php

namespace App\Models\Marketing;


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
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketingReferral extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Freunde-werben-Freunde Empfehlung',
            description: 'Empfehlungsverknüpfung zwischen Bestandskunden und geworbenen Neukunden inkl. Prämienstatus.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['customer.referrals', 'admin.marketing.referrals'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Customer, ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory;

    protected $table = 'marketing_referrals';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'referrer_email',
        'referrer_customer_id',
        'referrer_first_name',
        'referrer_last_name',
        'code',
        'voucher_id',
        'discount_percent',
        'reward_cents',
        'successful_referrals_count',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'reward_cents' => 'integer',
        'successful_referrals_count' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function voucher()
    {
        return $this->belongsTo(MarketingVoucher::class, 'voucher_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }
}

<?php

namespace App\Models\Support;


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
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SupportCustomerChat extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kunden-Live-Chat-Sitzung',
            description: 'Interaktive Support-Chat-Sitzung zwischen Shop-Besuchern/Kunden und Support bzw. KI-Assistent.',
            department: SystemDepartment::Support,
            navigationNodes: ['admin.support.chats'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::EU_AI_Act_Art50],
            externalChannels: [ExternalChannel::GoogleGemini_AI],
            dependsOnSettings: [],
            isGdprArt15Exportable: true,
            customerForeignKey: 'customer_id',
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids;

    protected $guarded = [];

    protected $keyType = 'string';
    public $incrementing = false;

    public function messages()
    {
        return $this->hasMany(SupportCustomerChatMessage::class)->orderBy('created_at', 'asc');
    }
    
    // Status helpers
    public function getIsOpenAttribute()
    {
        return $this->status === 'open';
    }
    
    public function getIsResolvedAttribute()
    {
        return $this->status === 'resolved';
    }
    
    public function getNeedsEmployeeAttribute()
    {
        return $this->status === 'needs_employee';
    }
}

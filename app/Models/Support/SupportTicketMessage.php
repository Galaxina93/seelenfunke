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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Support-Ticket-Nachricht',
            description: 'Nachrichten- und Dateianhangsverlauf innerhalb eines Kunden-Support-Tickets.',
            department: SystemDepartment::Support,
            navigationNodes: ['admin.support.tickets', 'customer.tickets'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::TaxBusinessLetters_6Years,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::GoBD],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'is_read_by_customer' => 'boolean',
        'is_read_by_admin' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}

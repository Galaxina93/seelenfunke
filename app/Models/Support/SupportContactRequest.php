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

class SupportContactRequest extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kontaktformular-Anfrage',
            description: 'Über das öffentliche Shop-Kontaktformular eingegangene Kunden- und Interessentenanfragen.',
            department: SystemDepartment::Support,
            navigationNodes: ['admin.support.contacts', 'shop.contact'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::TaxBusinessLetters_6Years,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin, ActorScope::EmployeeProduction],
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

    // Erzeugt automatisch eine eindeutige REQ- Nummer beim Erstellen
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ticket_number)) {
                $model->ticket_number = 'REQ-' . date('y') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
            }
        });
    }

    public function messages()
    {
        return $this->hasMany(SupportContactRequestMessage::class)->orderBy('created_at', 'asc');
    }
}

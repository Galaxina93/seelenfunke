<?php

namespace App\Models\Order;


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

class OrderRevocation extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Elektronischer Vertragswiderruf (§ 356a BGB)',
            description: 'Revisionssicherer Nachweis über elektronisch eingereichte Widerrufe im gesetzlichen 2-Stufen-Verfahren.',
            department: SystemDepartment::Order,
            navigationNodes: ['admin.orders.revocations', 'shop.revocation'],
            lifecycle: LifecycleBehavior::ImmutableAfterFinalized,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::BGB_356a_ElectronicRevocation, ComplianceRegulation::BGB_312g_CustomGoods],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory;

    protected $table = 'order_revocations';

    protected $fillable = [
        'name',
        'email',
        'order_number',
        'items',
        'attachments',
        'status',
        'legal_check_at',
        'product_type',
        'rejection_reason',
        'customer_notified_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'legal_check_at' => 'datetime',
        'customer_notified_at' => 'datetime',
    ];

    /**
     * Zugehörige Bestellung anhand der Bestellnummer.
     */
    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'order_number', 'order_number');
    }

    /**
     * Findet die verknüpfte Bestellung tolerant (mit/ohne Raute oder Prefix).
     */
    public function getLinkedOrderAttribute(): ?OrderOrder
    {
        $rawNumber = trim((string) ($this->order_number ?? ''));
        if ($rawNumber === '') {
            return null;
        }

        $cleanNumber = preg_replace('/^(bestellung|order|nr\.?|rechnung)\s*/i', '', $rawNumber);
        $cleanNumber = strtoupper(trim(ltrim((string) $cleanNumber, '#')));

        return OrderOrder::with(['invoices', 'customer'])
            ->where('order_number', $rawNumber)
            ->orWhere('order_number', $cleanNumber)
            ->orWhere('order_number', 'ORD-' . $cleanNumber)
            ->orWhere('order_number', 'SF-' . $cleanNumber)
            ->first();
    }
}

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
use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGamificationBugReport extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kunden-Fehlermeldung (Bug-Bounty)',
            description: 'Von Kunden eingereichte Verbesserungsvorschläge und Fehlermeldungen zur Belohnung mit Seelenfunken.',
            department: SystemDepartment::Customer,
            navigationNodes: ['customer.gamification', 'admin.support.bugs'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
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

use HasFactory, HasUuids;

    protected $table = 'customer_gamification_bug_reports';

    protected $fillable = [
        'customer_id',
        'category',
        'title',
        'description',
        'status',
        'reward_sparks',
        'admin_reply',
        'resolved_by_admin_id',
        'resolved_at',
        'technical_snapshot',
    ];

    protected $casts = [
        'reward_sparks'      => 'integer',
        'resolved_at'        => 'datetime',
        'technical_snapshot' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerGamification::class, 'customer_id', 'customer_id');
    }

    public function gamification(): BelongsTo
    {
        return $this->belongsTo(CustomerGamification::class, 'customer_id', 'customer_id');
    }

    public function resolvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    public function getSparksRewardAttribute(): int
    {
        return (int) ($this->attributes['reward_sparks'] ?? 0);
    }

    public function getAdminResponseAttribute(): ?string
    {
        return $this->attributes['admin_reply'] ?? null;
    }

    public function getClientSnapshotAttribute(): array
    {
        $val = $this->technical_snapshot;
        return is_array($val) ? $val : [];
    }

    public static function getCategoryLabels(): array
    {
        return [
            'funkenflug' => '🚀 Funkenflug-Express',
            'kristall'   => '💎 Kristall-Kollaps 3D',
            'hangar'     => '🛸 Hangar & Raumschiffe',
            'equipment'  => '⚔️ Gegenstände & Rucksack',
            'shop'       => '🛒 Ingame-Shop & Käufe',
            'ui'         => '🖥️ Anzeige & Cockpit-UI',
            'ui_mobile'  => '📱 Mobile & Cockpit-UI',
            'other'      => '🔧 Sonstiges',
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            'open'      => 'Offen',
            'in_review' => 'In Prüfung',
            'rewarded'  => 'Belohnt (+ ✨)',
            'resolved'  => 'Gelöst',
            'rejected'  => 'Abgelehnt',
        ];
    }
}

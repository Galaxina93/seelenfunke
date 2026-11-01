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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingHerzensprojektAction extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Herzensprojekt-Förderaktion',
            description: 'Soziale Spenden- und Herzensprojekte, die durch einen Anteil jeder Bestellung unterstützt werden.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.herzensprojekt', 'shop.herzensprojekt'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'allocated_amount_cents' => 'integer',
        'completed_at' => 'datetime',
        'photos' => 'array',
        'is_public' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(MarketingHerzensprojektTransaction::class, 'action_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->allocated_amount_cents / 100, 2, ',', '.') . ' €';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'in_progress' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            default => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Erfolgreich übergeben',
            'in_progress' => 'In Umsetzung',
            default => 'Geplant',
        };
    }
}

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
use Illuminate\Database\Eloquent\Model;

class CustomerGamification extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Seelenfunken-Treuekonto (Gamification)',
            description: 'Punktestand, Rang-Level und freigeschaltete Privilegien des Kunden im Seelenfunken-Bonusprogramm.',
            department: SystemDepartment::Customer,
            navigationNodes: ['customer.gamification', 'admin.customers.index'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: true,
            customerForeignKey: 'customer_id',
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

protected $guarded = [];

    protected $appends = ['funki_level'];

    public function getFunkiLevelAttribute(): int
    {
        return max(1, min(10, (int) ($this->attributes['level'] ?? 1)));
    }

    public function setFunkiLevelAttribute($value): void
    {
        $this->attributes['level'] = max(1, min(10, (int) $value));
    }

    public function getGamerNameAttribute(): ?string
    {
        return $this->attributes['player_name'] ?? null;
    }

    public function setGamerNameAttribute($value): void
    {
        $this->attributes['player_name'] = $value;
    }

    protected $casts = [
        'is_active' => 'boolean',
        'titles_progress' => 'array',
        'unlocked_coupons' => 'array',
        'unlocked_skins' => 'array',
        'unlocked_hall_auras' => 'array',
        'show_seelengott_badge' => 'boolean',
        'last_spark_collection_date' => 'date',
        'last_energy_refill_at' => 'datetime',
        'ranking_opt_in' => 'boolean',
        'ticket_emails_enabled' => 'boolean',
        'left_handed_mode' => 'boolean',
        'use_real_name' => 'boolean',
        'name_change_tokens' => 'integer',
        'astral_crystals' => 'integer',
        'unlocked_backgrounds' => 'array',
        'unlocked_champion_skins' => 'array',
        'active_champion_skins' => 'array',
        'galactic_conquest_points' => 'integer',
        'seelen_pass_until' => 'datetime',
        'has_backpack' => 'boolean',
        'equipped_items' => 'array',
        'inventory_items' => 'array',
        'buyback_items' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function logs()
    {
        return $this->hasMany(CustomerGamificationLog::class, 'customer_id', 'customer_id')->latest();
    }

    public function bugReports()
    {
        return $this->hasMany(CustomerGamificationBugReport::class, 'customer_id', 'customer_id')->latest();
    }
}

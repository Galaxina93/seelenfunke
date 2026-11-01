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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarketingVoucher extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Saison- & Aktionsgutschein (Feiertags-Autopilot)',
            description: 'Zeitgesteuerte Rabattaktionen (Muttertag, Valentinstag, Weihnachten) mit automatischer Banner-Schaltung.',
            department: SystemDepartment::Marketing,
            navigationNodes: ['admin.marketing.vouchers', 'shop.checkout'],
            lifecycle: LifecycleBehavior::SeasonalPerpetual,
            sensitivity: DataSensitivity::PublicCatalog,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::PAngV],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory;

    protected $table = 'marketing_vouchers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'influencer_id', 'title', 'code', 'recipient_email', 'type', 'value', 'used_count', 'usage_limit',
        'min_order_value', 'valid_from', 'valid_until',
        'is_active',
        'mode', 'trigger_event', 'days_offset', 'validity_days'
    ];

    public function influencer()
    {
        return $this->belongsTo(MarketingInfluencer::class, 'influencer_id');
    }

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'integer',
        'min_order_value' => 'integer',
        'used_count' => 'integer',
        'usage_limit' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?? (string) Str::uuid());
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_active', true)
            ->whereNull('influencer_id')
            ->where(function ($q) {
                $q->whereNull('recipient_email')
                  ->orWhere('recipient_email', '');
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->where(function ($q) {
                $q->whereNull('valid_from')
                  ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', now());
            });
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) return false;

        if (!empty($this->influencer_id)) {
            $inf = $this->relationLoaded('influencer') ? $this->influencer : $this->influencer()->first();
            if (!$inf || !$inf->is_active || $inf->is_archived) {
                return false;
            }
        }

        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_until && $now->gt($this->valid_until)) return false;

        return true;
    }

    public function isPersonalEvent()
    {
        return in_array($this->trigger_event, ['birthday', 'registered_date']);
    }
}

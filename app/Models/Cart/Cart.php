<?php

namespace App\Models\Cart;


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
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kunden-Warenkorb',
            description: 'Temporärer oder kundenbezogener Warenkorb vor Abschluss des Bestellvorgangs.',
            department: SystemDepartment::Cart,
            navigationNodes: ['shop.cart', 'shop.checkout'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer],
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

    protected $fillable = [
        'session_id',
        'customer_id',
        'coupon_code',
        'is_express',
        'reminder_email_sent_at'
    ];

    protected $casts = [
        'is_express' => 'boolean',
        'reminder_email_sent_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // GEÄNDERT: Heißt jetzt customer() und referenziert den Customer (inkl. SoftDeletes)
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    /**
     * Kryptografischer HMAC-Hash für den nahtlosen Rückkehr-Link aus der Warenkorb-Erinnerung.
     */
    public function getReminderResumeHash(): string
    {
        return hash_hmac('sha256', 'cart_resume|' . $this->id . '|' . (string) $this->customer_id, (string) config('app.key'));
    }
}

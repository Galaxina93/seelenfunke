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
use App\Models\Customer\Customer;
use App\Traits\FormatsECommerceData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrderQuoteRequest extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'B2B- & Großmengen-Angebotsanfrage',
            description: 'Individuelle Angebotsanfragen von Firmenkunden, Vereinen und Hochzeitsgesellschaften.',
            department: SystemDepartment::Order,
            navigationNodes: ['admin.orders.quotes', 'shop.quote-request'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::TaxBusinessLetters_6Years,
            aiAccess: AiAccessPolicy::ReadAndPropose,
            actorsRead: [ActorScope::Customer, ActorScope::Admin],
            actorsWrite: [ActorScope::PublicVisitor, ActorScope::Customer, ActorScope::Admin],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasUuids, SoftDeletes, FormatsECommerceData;

    protected $table = 'order_quote_requests';

    protected $guarded = [];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'is_express' => 'boolean',
        'expires_at' => 'datetime',
        'configuration' => 'array',
        'billing_address' => 'array',
        'shipping_address' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($quote) {
            if (empty($quote->token)) {
                $quote->token = Str::random(32);
            }
            if (empty($quote->expires_at)) {
                $validityDays = (int)shop_setting('order_quote_validity_days', 14);
                $quote->expires_at = now()->addDays($validityDays);
            }
            if (empty($quote->status)) {
                $quote->status = 'open';
            }
        });
    }

    public function items()
    {
        return $this->hasMany(OrderQuoteRequestItem::class, 'quote_request_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(OrderOrder::class, 'converted_order_id');
    }

    public function getIsGuestAttribute()
    {
        return is_null($this->customer_id);
    }

    public function isValid()
    {
        return $this->status === 'open' && (!$this->expires_at || $this->expires_at->isFuture());
    }
}

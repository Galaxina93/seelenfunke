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
use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer; // GEÄNDERT: Customer statt User
use App\Traits\FormatsECommerceData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderOrder extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Kundenbestellung & Kaufvertrag',
            description: 'Zentrales Auftrags-Model für Zahlung, Manufaktur-Fertigung, DHL-Gewichtsberechnung und GoBD-Rechnungserstellung.',
            department: SystemDepartment::Order,
            navigationNodes: ['admin.orders.index', 'customer.orders.index', 'shop.checkout'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::GoBD_10Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Customer, ActorScope::EmployeeProduction, ActorScope::Admin],
            actorsWrite: [ActorScope::EmployeeProduction, ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::GoBD, ComplianceRegulation::DSGVO_Art15_Art30, ComplianceRegulation::BGB_312g_CustomGoods, ComplianceRegulation::BGB_356a_ElectronicRevocation, ComplianceRegulation::PAngV],
            externalChannels: [ExternalChannel::StripePayment, ExternalChannel::DHL_DeutschePost, ExternalChannel::DATEV_TaxExport, ExternalChannel::IMAP_SMTP_Mail],
            dependsOnSettings: ['default_tax_rate', 'is_small_business', 'packaging_weight_envelope_grams', 'packaging_weight_grams'],
            isGdprArt15Exportable: true,
            customerForeignKey: 'customer_id',
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasFactory, SoftDeletes, HasUuids, FormatsECommerceData;

    protected $table = 'order_orders';

    protected bool $cancellationAlreadyHandled = false;

    protected $fillable = [
        'order_number',
        'customer_id',
        'email',
        'status',
        'payment_status',
        'payment_method',
        'payment_url',
        'stripe_payment_intent_id',
        'billing_address',
        'shipping_address',
        'volume_discount',
        'coupon_code',
        'discount_amount',
        'subtotal_price',
        'tax_amount',
        'shipping_price',
        'total_price',
        'notes',
        'workshop_note',
        'workshop_note_updated_at',
        'workshop_next_check_at',
        'workshop_checkin_paused',
        'cancellation_reason',
        'is_express',
        'express_price',
        'expires_at',
        'token',
        'is_small_business',
        'digital_waiver_accepted_at',
        'utm_source_first',
        'utm_campaign_first',
        'utm_medium_first',
        'utm_source_last',
        'utm_campaign_last',
        'utm_medium_last',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'subtotal_price' => 'integer',
        'tax_amount' => 'integer',
        'shipping_price' => 'integer',
        'total_price' => 'integer',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'workshop_note_updated_at' => 'datetime',
        'workshop_next_check_at' => 'datetime',
        'workshop_checkin_paused' => 'boolean',
        'is_express' => 'boolean',
        'is_small_business' => 'boolean',
        'digital_waiver_accepted_at' => 'datetime',
        'cancellation_reason' => 'string',
    ];

    protected static function booted()
    {
        static::created(function ($order) {
            if (!app()->runningUnitTests()) {
                try {
                    broadcast(new \App\Events\OrderScopeUpdated($order, true));
                } catch (\Throwable $e) {
                    // WebSocket / Broadcaster optional
                }
            }
        });

        static::updated(function ($order) {
            if ($order->isDirty(['status', 'payment_status', 'total_price']) && !app()->runningUnitTests()) {
                try {
                    broadcast(new \App\Events\OrderScopeUpdated($order, false));
                } catch (\Throwable $e) {
                    // WebSocket / Broadcaster optional
                }
            }

            // 1. Synchronize "paid" status
            if ($order->isDirty('payment_status') && $order->payment_status === 'paid') {
                $invoices = $order->invoices()->where('type', 'invoice')->whereIn('status', ['open', 'draft'])->get();
                foreach ($invoices as $invoice) {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                    try {
                        app(\App\Services\InvoiceService::class)->storePdf($invoice);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to store PDF for invoice {$invoice->invoice_number} on order status sync: " . $e->getMessage());
                    }
                }

                try {
                    app(\App\Services\Marketing\InfluencerService::class)->processOrderCommission($order);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Influencer commission processing error on status sync: ' . $e->getMessage());
                }
            }

            // 1b. Synchronize "shipped" / "completed" -> approve pending influencer commission
            if ($order->isDirty('status') && in_array($order->status, ['shipped', 'completed'], true) && $order->payment_status === 'paid') {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('marketing_influencer_commissions')) {
                        \App\Models\Marketing\MarketingInfluencerCommission::where('order_id', $order->id)
                            ->where('status', 'pending')
                            ->whereNull('payout_id')
                            ->update([
                                'status' => 'approved',
                                'approved_at' => now(),
                            ]);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Influencer commission approval error: ' . $e->getMessage());
                }
            }

            // 2. Synchronize "cancelled" or "refunded" status
            if (
                ($order->isDirty('payment_status') && $order->payment_status === 'refunded') ||
                ($order->isDirty('status') && in_array($order->status, ['cancelled', 'refunded']))
            ) {
                $invoices = $order->invoices()->where('type', 'invoice')->whereIn('status', ['paid', 'open'])->get();
                foreach ($invoices as $invoice) {
                    try {
                        app(\App\Services\InvoiceService::class)->cancelInvoice($invoice);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Failed to cancel invoice {$invoice->invoice_number} on order status sync: " . $e->getMessage());
                    }
                }

                if (
                    !$order->cancellationAlreadyHandled
                    && !in_array((string) $order->getOriginal('status'), ['cancelled', 'refunded'], true)
                    && (string) $order->getOriginal('payment_status') !== 'refunded'
                ) {
                    $order->handleCancellation(
                        (string) ($order->cancellation_reason ?: 'Stornierung/Erstattung'),
                        (string) $order->getOriginal('status'),
                        (string) $order->getOriginal('payment_status')
                    );
                }
            }
        });
    }

    public function items()
    {
        return $this->hasMany(OrderOrderItem::class, 'order_id');
    }

    /**
     * Liefert den glasklaren physischen Artikel-Fokus statt abstrakter Bestellnummern (#ORD-...):
     * - 1 Artikel: "1× Der Seelen Kristall"
     * - 2 Artikel: "1× Der Seelen Kristall + 2× Herz-Schlüsselanhänger"
     * - >= 3 Artikel: "1× Der Seelen Kristall + 1× Schieferplatte (+1 weiterer)" bzw. "(+N weitere)"
     * - 0 Positionen: Fallback auf "Auftrag #<order_number>"
     */
    public function getProductionFocusLabel(int $maxItems = 2): string
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->with('product')->get();

        if ($items->isEmpty()) {
            return 'Auftrag #' . $this->order_number;
        }

        $limit = max(1, $maxItems);
        $parts = [];

        foreach ($items->take($limit) as $item) {
            $product = $item->relationLoaded('product') ? $item->product : $item->product()->first();
            $rawName = $product?->name ?? $item->product_name ?? $item->name ?? '';
            $cleanName = trim((string) $rawName);
            if ($cleanName === '') {
                $cleanName = 'Artikel';
            }
            $qty = max(1, (int) ($item->quantity ?: 1));
            $parts[] = $qty . '× ' . $cleanName;
        }

        $label = implode(' + ', $parts);
        $remaining = $items->count() - $limit;

        if ($remaining === 1) {
            $label .= ' (+1 weiterer)';
        } elseif ($remaining > 1) {
            $label .= ' (+' . $remaining . ' weitere)';
        }

        return $label;
    }

    public function shipments()
    {
        return $this->hasMany(OrderShipment::class, 'order_id');
    }

    public function getTrackingNumberAttribute()
    {
        $shipment = $this->shipments()->first();
        return $shipment ? $shipment->tracking_number : null;
    }

    public function getShippingLabelPathAttribute()
    {
        $shipment = $this->shipments()->latest()->first();
        return $shipment ? $shipment->shipping_label_path : null;
    }

    // Zeigt nun auf Customer::class (inkl. SoftDeletes/archivierte Accounts für GoBD-Konformität)
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    /**
     * Prüft, ob ein Firmenname redundant mit dem Vor- und Nachnamen identisch ist
     * (z. B. wenn Privatkunden oder Browser-Autofill ihren eigenen Namen ins Feld „Firma" eintragen).
     */
    public static function isRedundantCompanyName(?string $company, ?string $fullName): bool
    {
        $cleanCompany = preg_replace('/\s+/u', ' ', trim((string) $company));
        if ($cleanCompany === '') {
            return true;
        }

        $cleanName = preg_replace('/\s+/u', ' ', trim((string) $fullName));
        if ($cleanName === '') {
            return false;
        }

        return mb_strtolower($cleanCompany) === mb_strtolower($cleanName);
    }

    /**
     * Prüft sachlich anhand der zustellrelevanten Adressfelder (statt rohem Array-serialize()),
     * ob die Lieferadresse tatsächlich von der Rechnungsadresse abweicht.
     */
    public function hasDifferentShippingAddress(): bool
    {
        $shipping = is_array($this->shipping_address) ? $this->shipping_address : [];
        if (empty($shipping)) {
            return false;
        }

        $hasAnyShippingCore = trim((string) ($shipping['address'] ?? '')) !== ''
            || trim((string) ($shipping['postal_code'] ?? '')) !== ''
            || trim((string) ($shipping['city'] ?? '')) !== '';
        if (!$hasAnyShippingCore) {
            return false;
        }

        $billing = is_array($this->billing_address) ? $this->billing_address : [];

        $normalizeCountry = static function (?string $country): string {
            $c = mb_strtolower(trim((string) $country));
            return match ($c) {
                '', 'de', 'deu', 'deutschland', 'germany' => 'de',
                'at', 'aut', 'österreich', 'oesterreich', 'austria' => 'at',
                'ch', 'che', 'schweiz', 'switzerland' => 'ch',
                default => $c,
            };
        };

        $normalize = static function (array $addr, string $fallbackName = '') use ($normalizeCountry): array {
            $rawName = preg_replace('/\s+/u', ' ', trim(((string) ($addr['first_name'] ?? '')) . ' ' . ((string) ($addr['last_name'] ?? ''))));
            $effectiveName = $rawName !== '' ? $rawName : $fallbackName;
            $rawCompany = (string) ($addr['company'] ?? '');
            $company = self::isRedundantCompanyName($rawCompany, $effectiveName)
                ? ''
                : mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($rawCompany)));

            return [
                'name'        => mb_strtolower($effectiveName),
                'company'     => $company,
                'address'     => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim(((string) ($addr['address'] ?? '')) . ' ' . ((string) ($addr['address_addition'] ?? ''))))),
                'postal_code' => mb_strtolower((string) preg_replace('/\s+/u', '', trim((string) ($addr['postal_code'] ?? '')))),
                'city'        => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) ($addr['city'] ?? '')))),
                'country'     => $normalizeCountry($addr['country'] ?? 'DE'),
            ];
        };

        $normBilling = $normalize($billing);
        $normShipping = $normalize($shipping, $normBilling['name']);

        return $normBilling !== $normShipping;
    }

    public function sampleInfluencer()
    {
        return $this->hasOne(\App\Models\Marketing\MarketingInfluencer::class, 'sample_order_id')->withTrashed();
    }

    public function getIsInfluencerOrderAttribute(): bool
    {
        $couponCode = strtoupper(trim((string) $this->coupon_code));
        if ($couponCode !== '' && str_starts_with($couponCode, 'SAMPLE-')) {
            return true;
        }

        if ($this->relationLoaded('sampleInfluencer')) {
            if ($this->sampleInfluencer !== null) {
                return true;
            }
        } elseif ($this->sampleInfluencer()->exists()) {
            return true;
        }

        $email = strtolower(trim((string) $this->email));
        if ($email !== '' && \Illuminate\Support\Facades\Schema::hasTable('employees')) {
            return \App\Models\Employee\Employee::withTrashed()
                ->where('role', 'influencer')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->exists();
        }

        return false;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => '#FF7D4F',
            'processing' => '#3b82f6',
            'shipped' => '#a855f7',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
            'refunded' => '#6b7280',
            default => '#6b7280',
        };
    }

    public function getPaymentStatusColorAttribute()
    {
        return match($this->payment_status) {
            'paid' => '#10b981',
            'unpaid' => '#ef4444',
            'pending' => '#FF7D4F',
            'refunded' => '#6b7280',
            default => '#6b7280',
        };
    }

    public function getCustomerNameAttribute()
    {
        if (isset($this->billing_address['first_name'])) {
            return $this->billing_address['first_name'] . ' ' . $this->billing_address['last_name'];
        }
        return 'Gast';
    }

    public function getShippingTaxAmountAttribute()
    {
        $isSmallBusiness = $this->is_small_business !== null
            ? (bool) $this->is_small_business
            : (bool) shop_setting('is_small_business', false);

        $shippingAddr = is_array($this->shipping_address) ? $this->shipping_address : [];
        $billingAddr = is_array($this->billing_address) ? $this->billing_address : [];
        $targetCountry = strtoupper((string) ($shippingAddr['country'] ?? $billingAddr['country'] ?? 'DE'));
        $euCountries = ['DE', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

        if ($this->shipping_price <= 0 || $isSmallBusiness || !in_array($targetCountry, $euCountries, true)) {
            return 0;
        }
        $taxRate = (float)shop_setting('default_tax_rate', 19.00);
        $divisor = 1 + ($taxRate / 100);
        return (int)round($this->shipping_price - ($this->shipping_price / $divisor));
    }

    public function getShippingNetPriceAttribute()
    {
        return $this->shipping_price - $this->shipping_tax_amount;
    }

    public function getIsFreeShippingAttribute()
    {
        return $this->shipping_price === 0;
    }

    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ]);
    }

    public function invoices()
    {
        return $this->hasMany(AccountingInvoice::class, 'order_id');
    }

    /**
     * Check if the order contains only digital products.
     */
    public function isOnlyDigital(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }
        return $this->items->every(function ($item) {
            $product = $item->product;
            if (!$product) return false;
            
            // Check if it is a postal gift voucher
            $config = $item->configuration ?? [];
            if (!empty($config['is_gift_voucher']) && ($config['delivery_method'] ?? 'email') === 'post') {
                return false;
            }
            
            return $product->type === 'digital';
        });
    }

    /**
     * Prüft, ob diese Bestellung physisch ausschließlich aus postalisch versendeten Geschenkgutscheinen besteht.
     */
    public function isPostalVoucherOnly(): bool
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items.product');
        }

        $hasPostalVoucher = false;

        foreach ($this->items as $item) {
            $product = $item->product;
            $conf = is_array($item->configuration) ? $item->configuration : [];
            $isVoucherPost = !empty($conf['is_gift_voucher']) && ($conf['delivery_method'] ?? 'email') === 'post';

            if ($isVoucherPost) {
                $hasPostalVoucher = true;
                continue;
            }

            $isDigital = !empty($conf['is_digital']) || ($product && $product->type === 'digital');
            $isService = $product && $product->type === 'service';
            if (!$isDigital && !$isService) {
                return false;
            }
        }

        return $hasPostalVoucher;
    }

    /**
     * Prüft, ob die Bestellung als Deutsche Post Großbrief (z. B. Post-Gutschein oder leichter Flachartikel) versendet werden kann.
     */
    public function isBriefEligibleOrder(): bool
    {
        if ($this->isOnlyDigital()) {
            return false;
        }

        if ($this->isPostalVoucherOnly()) {
            return true;
        }

        $details = $this->getDhlWeightDetails(1, 'envelope');
        return !empty($details['can_fit_in_envelope']);
    }

    /**
     * Get DHL weight details for the order including item breakdown and recommendation.
     */
    public function getDhlWeightDetails(int $packageCount = 1, string $packagingType = 'box'): array
    {
        $packageCount = max(1, $packageCount);
        $itemsList = [];
        $totalProductWeightGrams = 0;
        $remainingProductWeightGrams = 0;
        $maxTaraWeight = 0;
        $maxSingleItemWeight = 0;
        $totalItemsCount = 0;
        $hasFallbackWeight = false;

        foreach ($this->items as $item) {
            $product = $item->product;
            // Digital-Prüfung: rein digitale Produkte nicht physisch ins Paket wiegen
            $isDigital = $product && $product->type === 'digital';
            $config = $item->configuration ?? [];
            if (!empty($config['is_gift_voucher']) && ($config['delivery_method'] ?? 'email') === 'email') {
                $isDigital = true;
            }

            if ($product && !$isDigital) {
                $isFallback = empty($product->weight) || $product->weight <= 0;
                if ($isFallback) {
                    $hasFallbackWeight = true;
                }
                $itemWeight = !$isFallback ? (int) $product->weight : 100;
                $lineTotalWeight = $itemWeight * $item->quantity;
                $totalProductWeightGrams += $lineTotalWeight;

                if ($itemWeight > $maxSingleItemWeight) {
                    $maxSingleItemWeight = $itemWeight;
                }

                $remainingQty = max(0, $item->quantity - ($item->completed_quantity ?? 0));
                $remainingProductWeightGrams += ($itemWeight * $remainingQty);

                if ($product->packaging_weight && $product->packaging_weight > $maxTaraWeight) {
                    $maxTaraWeight = (int) $product->packaging_weight;
                }

                $totalItemsCount += $item->quantity;

                $itemsList[] = [
                    'id'                  => $item->id,
                    'product_id'          => $item->product_id,
                    'name'                => $item->product_name ?: ($product->name ?? 'Unbekanntes Produkt'),
                    'quantity'            => $item->quantity,
                    'remaining_quantity'  => $remainingQty,
                    'single_weight_grams' => $itemWeight,
                    'total_weight_grams'  => $lineTotalWeight,
                    'is_fallback'         => $isFallback,
                ];
            }
        }

        $weightToUse = $remainingProductWeightGrams > 0 ? $remainingProductWeightGrams : $totalProductWeightGrams;

        if ($weightToUse == 0 && $totalItemsCount > 0) {
            $weightToUse = $totalItemsCount * 100;
        }

        // Automatische Eignungsprüfung für Luftpolsterumschlag:
        // Physische Artikel vorhanden, Gesamtreingewicht <= 250g, Einzelschwerstes Teil <= 100g, keine schwere Sonderverpackung
        $canFitInEnvelope = $totalItemsCount > 0 
            && $weightToUse <= 250 
            && $maxSingleItemWeight <= 100 
            && $maxTaraWeight <= 50;

        $suggestedPackagingType = $canFitInEnvelope ? 'envelope' : 'box';

        // Tara & Puffer je nach gewählter Verpackungsart
        $postalVoucherOnly = $totalItemsCount > 0 && $this->isPostalVoucherOnly();
        if ($packagingType === 'envelope') {
            if ($postalVoucherOnly) {
                $packagingName = 'Vollpapp-Versandtasche DIN A5';
                $packagingWeightGrams = 45;
                $bufferGrams = 5;
            } else {
                $packagingName = 'Luftpolsterumschlag';
                $packagingWeightGrams = (int)shop_setting('packaging_weight_envelope_grams', 30);
                $bufferGrams = $weightToUse > 0 ? (int)shop_setting('shipping_buffer_envelope_grams', 10) : 0;
            }
        } else {
            $packagingName = 'Karton';
            $packagingWeightGrams = $maxTaraWeight > 0 ? $maxTaraWeight : (int)shop_setting('packaging_weight_grams', 350);
            $bufferGrams = $weightToUse > 0 ? (int)shop_setting('shipping_buffer_grams', 50) : 0;
        }

        $totalGrams = $weightToUse + ($packageCount * $packagingWeightGrams) + ($packageCount * $bufferGrams);
        $weightPerPackageKg = round(($totalGrams / 1000) / $packageCount, 2);
        $weightPerPackageKg = max(0.01, $weightPerPackageKg);

        // DHL Tarifklasse & Schwellenwert-Prüfung
        $dhlClass = 'Paket bis 2 kg';
        $dhlBadgeColor = 'emerald';
        $isNearLimit = false;

        if ($weightPerPackageKg <= 2.00) {
            $dhlClass = 'Paket bis 2 kg';
            $dhlBadgeColor = 'emerald';
            if ($weightPerPackageKg >= 1.85) {
                $isNearLimit = true;
            }
        } elseif ($weightPerPackageKg <= 5.00) {
            $dhlClass = 'Paket bis 5 kg';
            $dhlBadgeColor = 'yellow';
            if ($weightPerPackageKg >= 4.80) {
                $isNearLimit = true;
            }
        } elseif ($weightPerPackageKg <= 10.00) {
            $dhlClass = 'Paket bis 10 kg';
            $dhlBadgeColor = 'amber';
            if ($weightPerPackageKg >= 9.70) {
                $isNearLimit = true;
            }
        } elseif ($weightPerPackageKg <= 31.50) {
            $dhlClass = 'Paket bis 31,5 kg';
            $dhlBadgeColor = 'orange';
            if ($weightPerPackageKg >= 31.00) {
                $isNearLimit = true;
            }
        } else {
            $dhlClass = 'Übergewicht (> 31,5 kg)';
            $dhlBadgeColor = 'red';
            $isNearLimit = true;
        }

        // Automatische Paketanzahl-Empfehlung für Großbestellungen (z.B. 100 Trophäen = ~92 kg Ware -> 5 Pakete à max. ~20 kg Ware im 600x400x400 mm 2-welligen Umkarton)
        $singleParcelGrossGrams = $weightToUse + $packagingWeightGrams + $bufferGrams;
        $recommendedPackageCount = 1;
        if ($singleParcelGrossGrams > 25000) {
            // Sicherer Split bei ~20 kg reinem Warengewicht pro Paket (max. 20 Trophäen pro 600x400x400 mm Karton = ~22,4 kg brutto, weit unter 30 kg Karton- & 31,5 kg DHL-Grenze)
            $recommendedPackageCount = max(2, min(30, (int) ceil($weightToUse / 20000)));
        }
        $isBulkOrder = $recommendedPackageCount > 1;
        if ($packagingType === 'envelope') {
            $recommendedBoxHint = $postalVoucherOnly
                ? 'Vollpapp-Versandtasche DIN A5 (167 × 240 mm · Verpacking Art.-Nr. 3895-001)'
                : 'Luftpolstertasche (260 × 180 × 10 mm · Karton.eu Art.-Nr. 135)';
        } elseif ($isBulkOrder) {
            $recommendedBoxHint = ($weightToUse / $recommendedPackageCount > 10000)
                ? 'Großer Karton (600 × 400 × 400 mm · Karton.eu Art.-Nr. 069)'
                : 'Mittlerer Karton (600 × 400 × 200 mm · Karton.eu Art.-Nr. 165)';
        } elseif ($weightToUse > 9000) {
            $recommendedBoxHint = 'Großer Karton (600 × 400 × 400 mm · Karton.eu Art.-Nr. 069)';
        } elseif ($weightToUse > 2800) {
            $recommendedBoxHint = 'Mittlerer Karton (600 × 400 × 200 mm · Karton.eu Art.-Nr. 165)';
        } elseif ($totalItemsCount === 1 && $weightToUse >= 400 && $weightToUse <= 1200) {
            $recommendedBoxHint = 'Buchverpackung (Trophäe) (284 × 244 × 70 mm · Karton.eu Art.-Nr. 6227)';
        } else {
            $recommendedBoxHint = 'Normaler Karton (300 × 230 × 150 mm · Karton.eu Art.-Nr. 2633)';
        }

        return [
            'items'                          => $itemsList,
            'total_items_count'              => $totalItemsCount,
            'product_weight_grams'           => $weightToUse,
            'packaging_type'                 => $packagingType,
            'packaging_name'                 => $packagingName,
            'packaging_weight_grams'         => $packagingWeightGrams,
            'buffer_grams'                   => $bufferGrams,
            'total_grams'                    => $totalGrams,
            'total_weight_kg'                => round($totalGrams / 1000, 2),
            'recommended_weight_per_package' => $weightPerPackageKg,
            'recommended_package_count'      => $recommendedPackageCount,
            'is_bulk_order'                  => $isBulkOrder,
            'recommended_box_hint'           => $recommendedBoxHint,
            'dhl_class'                      => $dhlClass,
            'dhl_badge_color'                => $dhlBadgeColor,
            'is_near_limit'                  => $isNearLimit,
            'has_fallback_weight'            => $hasFallbackWeight,
            'can_fit_in_envelope'            => $canFitInEnvelope,
            'suggested_packaging_type'       => $suggestedPackagingType,
        ];
    }

    /**
     * Calculate DHL shipping weight for the order.
     */
    public function calculateDhlWeight(int $packageCount = 1, string $packagingType = 'box'): float
    {
        $details = $this->getDhlWeightDetails($packageCount, $packagingType);
        return $details['recommended_weight_per_package'];
    }

    public function completePayment(?string $stripePaymentIntentId = null): void
    {
        if (
            in_array($this->payment_status, ['paid', 'refunded'], true)
            || in_array($this->status, ['cancelled', 'refunded'], true)
        ) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($stripePaymentIntentId) {
            // Lock the order row for update to prevent concurrent payment processing races
            $order = self::where('id', $this->id)->lockForUpdate()->first();
            if (
                !$order
                || in_array($order->payment_status, ['paid', 'refunded'], true)
                || in_array($order->status, ['cancelled', 'refunded'], true)
            ) {
                return;
            }

            // 1. GUTSCHEIN VERBRAUCHEN (Zuerst prüfen & abbuchen, damit bei 0-€-Fehlern sofort abgerollt wird, bevor neue Gutscheine entstehen)
            if ($order->coupon_code) {
                $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::where('code', $order->coupon_code)
                    ->lockForUpdate()
                    ->first();
                if ($giftVoucher) {
                    $requestedDiscount = max(0, (int) $order->discount_amount);
                    $actualDeducted = max(0, min((int) $giftVoucher->current_balance, $requestedDiscount));

                    if ($order->total_price == 0 && $requestedDiscount > 0 && (!$giftVoucher->isValid() || $actualDeducted < $requestedDiscount)) {
                        throw new \RuntimeException('Das Gutscheinguthaben reicht für diese Bestellung nicht mehr aus.');
                    }

                    $newBalance = max(0, (int) $giftVoucher->current_balance - $actualDeducted);
                    $giftVoucher->update([
                        'current_balance' => $newBalance,
                        'is_active' => $newBalance > 0
                    ]);

                    if ($actualDeducted > 0) {
                        \App\Models\Marketing\MarketingGiftVoucherLog::create([
                            'gift_voucher_id' => $giftVoucher->id,
                            'order_id' => $order->id,
                            'amount' => $actualDeducted,
                            'remaining_balance' => $newBalance
                        ]);
                    }
                } else {
                    $mv = \App\Models\Marketing\MarketingVoucher::where('code', $order->coupon_code)
                        ->lockForUpdate()
                        ->first();
                    if ($mv) {
                        if ($order->total_price == 0 && !$mv->isValid()) {
                            throw new \RuntimeException('Der verwendete Rabattcode ist nicht mehr gültig.');
                        }
                        $mv->increment('used_count');

                        if ($mv->isInfluencerSample() && !empty($mv->influencer_id)) {
                            $mv->update(['is_active' => false]);
                            \App\Models\Marketing\MarketingInfluencer::where('id', $mv->influencer_id)
                                ->update(['sample_order_id' => $order->id]);
                        }
                    }
                }
            }

            $newStatus = $order->isOnlyDigital() ? 'completed' : 'pending';

            $updateData = [
                'payment_status' => 'paid',
                'status' => $newStatus,
                'payment_url' => null,
            ];

            if ($stripePaymentIntentId) {
                $updateData['stripe_payment_intent_id'] = $stripePaymentIntentId;
            }

            $order->update($updateData);

            // Synchronize the current instance status
            $this->payment_status = 'paid';
            $this->status = $newStatus;
            $this->payment_url = null;

            // --- LAGERBESTAND REDUZIEREN ---
            foreach ($order->items as $item) {
                if ($item->product) {
                    $itemVariantId = is_array($item->configuration) ? ($item->configuration['variant_id'] ?? null) : null;
                    $item->product->reduceStock($item->quantity, $itemVariantId);
                }
            }

            // --- GIFT VOUCHERS GENERIEREN ---
            foreach ($order->items as $item) {
                $config = $item->configuration ?? [];
                $isActualVoucherProduct = ($item->product && $item->product->slug === 'geschenkgutschein')
                    || str_contains(strtolower((string) $item->product_name), 'geschenkgutschein');

                if (!empty($config['is_gift_voucher']) && !$isActualVoucherProduct) {
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:price_tampering_attempt',
                        'Unautorisierte Gutschein-Generierung verhindert',
                        "Order {$order->order_number}: Artikel '{$item->product_name}' enthielt is_gift_voucher-Flag, ist aber kein Geschenkgutschein-Produkt.",
                        'error',
                        ['order_id' => $order->id, 'order_item_id' => $item->id]
                    );
                    continue;
                }

                if (!empty($config['is_gift_voucher']) && $isActualVoucherProduct) {
                    // Safety check to prevent duplicate vouchers for this item
                    $alreadyExists = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)->exists();
                    if (!$alreadyExists) {
                        $cardColor = (($config['card_color'] ?? 'gold') === 'black') ? 'black' : 'gold';
                        $isPostDelivery = (($config['delivery_method'] ?? 'email') === 'post');
                        $surchargeCents = ($cardColor === 'black' && $isPostDelivery) ? 150 : 0;
                        $unitNetVoucherPrice = $item->unit_price > 0 ? max(0, (int) $item->unit_price - $surchargeCents) : (int) ($config['amount_cents'] ?? 500);
                        $configuredAmount = isset($config['amount_cents']) ? (int) $config['amount_cents'] : $unitNetVoucherPrice;
                        // Der Gutscheinwert darf niemals den tatsächlich berechneten/bezahlten Einzelpreis (abzgl. Karten-Design-Aufschlag) überschreiten
                        $paidVoucherValue = max(500, min(100000, min($unitNetVoucherPrice, $configuredAmount)));

                        for ($i = 0; $i < $item->quantity; $i++) {
                            $code = \App\Models\Marketing\MarketingGiftVoucher::generateCode();
                            $rawPersonalMsg = $config['personal_message'] ?? $config['message'] ?? null;
                            \App\Models\Marketing\MarketingGiftVoucher::create([
                                'code' => $code,
                                'order_item_id' => $item->id,
                                'initial_value' => $paidVoucherValue,
                                'current_balance' => $paidVoucherValue,
                                'recipient_name' => (string) ($config['recipient_name'] ?? $order->customer_name ?: 'Beschenkte Person'),
                                'recipient_email' => $config['recipient_email'] ?? null,
                                'personal_message' => $rawPersonalMsg !== null ? mb_substr((string) $rawPersonalMsg, 0, 100) : null,
                                'delivery_method' => $config['delivery_method'] ?? 'email',
                                'card_color' => $cardColor,
                                'is_active' => true,
                                'valid_until' => now()->addYears(3)->endOfYear(),
                            ]);
                        }
                    }
                }
            }

            // Gast-Kundenkonto NUR dann automatisch verifizieren, wenn es sich um ein frisch generiertes Gastkonto
            // mit Einmal-Passwort handelt (das ausschließlich an das E-Mail-Postfach gesendet wird)
            if (
                $order->customer
                && !$order->customer->hasVerifiedEmail()
                && (bool) $order->customer->needs_password_change
                && !empty($order->customer->temporary_password)
                && strcasecmp(trim((string) $order->email), trim((string) $order->customer->email)) === 0
            ) {
                $order->customer->markEmailAsVerified();
            }

            // --- FREUNDE WERBEN FREUNDE: 5 € GUTHABEN FÜR DEN EMPFEHLENDEN KUNDEN ---
            try {
                app(\App\Services\ReferralService::class)->processOrderReferralReward($order);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Referral reward processing error: ' . $e->getMessage());
            }

            // --- INFLUENCER & AFFILIATE: PROVISION BERECHNEN (DUAL-ATTRIBUTION) ---
            try {
                app(\App\Services\Marketing\InfluencerService::class)->processOrderCommission($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Influencer commission processing error: ' . $e->getMessage());
            }

            // --- HERZENSPROJEKT: AUTOMATISCHER BEITRAG IN DEN SPENDENTOPF ---
            \App\Services\HerzensprojektService::recordDonationForOrder($order);

            // --- GAMIFICATION: SEELENFUNKEN & BESTELL-TITEL GUTSCHREIBEN ---
            try {
                app(\App\Services\Gamification\GamificationService::class)->rewardOrderFunken($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Gamification order reward error: ' . $e->getMessage());
            }

            // --- DOKUMENTE & MAILS AN DEN BACKGROUND-WORKER ÜBERGEBEN ---
            if ($order->invoices()->where('type', 'invoice')->doesntExist()) {
                \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);
            }
        });
    }

    /**
     * Liefert alle sofort herunterladbaren digitalen Güter & Geschenkgutscheine dieser Bestellung
     * inklusive kryptografisch signierter Download-URLs (gültig für 7 Tage).
     */
    public function getInstantDownloads(): array
    {
        if (
            $this->payment_status !== 'paid'
            || in_array($this->status, ['cancelled', 'refunded'], true)
        ) {
            return [];
        }

        $this->loadMissing('items.product');
        $downloads = [];

        foreach ($this->items as $item) {
            $config = is_array($item->configuration) ? $item->configuration : [];

            // 1. Geschenkgutscheine (PDF + Code)
            if (!empty($config['is_gift_voucher'])) {
                $vouchers = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)
                    ->where('is_active', true)
                    ->get();

                foreach ($vouchers as $voucher) {
                    $downloads[] = [
                        'type' => 'gift_voucher',
                        'title' => 'Wertgutschein (' . number_format($voucher->initial_value / 100, 2, ',', '.') . ' €)',
                        'subtitle' => 'Für ' . ($voucher->recipient_name ?: 'dich') . ' • Sofort einlösbar & druckfertiges PDF',
                        'code' => $voucher->code,
                        'format' => 'PDF',
                        'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                            'customer.gift-voucher.download',
                            now()->addDays(7),
                            ['voucher' => $voucher->id]
                        ),
                    ];
                }
                continue;
            }

            // 2. Digitale Produkte
            if ($item->product && $item->product->type === 'digital' && !empty($item->product->digital_download_path)) {
                $ext = strtoupper(pathinfo((string) ($item->product->digital_filename ?: $item->product->digital_download_path), PATHINFO_EXTENSION) ?: 'DATEI');
                $downloads[] = [
                    'type' => 'digital_product',
                    'title' => (string) ($item->product_name ?: $item->product->name),
                    'subtitle' => 'Digitale Produktdatei • Sofort-Download',
                    'code' => null,
                    'format' => $ext,
                    'url' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'digital-product.download',
                        now()->addDays(7),
                        ['item' => $item->id]
                    ),
                ];
            }
        }

        return $downloads;
    }

    /**
     * Prüft, ob mindestens eine Bestellposition digitale Begleitdokumente (z. B. Echtheitszertifikat) enthält.
     */
    public function hasDigitalDocuments(): bool
    {
        return count($this->getDigitalDocumentsList()) > 0;
    }

    /**
     * Liefert alle digitalen Begleitdokumente aller Bestellpositionen (z. B. Echtheitszertifikate, Pflegehinweise).
     *
     * @return array<int, array{item_id: string, product_name: string, doc_id: string, title: string, filename: string, extension: string, size_bytes: int, path: string}>
     */
    public function getDigitalDocumentsList(): array
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items.product');
        }

        $documents = [];
        foreach ($this->items as $item) {
            if (!method_exists($item, 'resolveDigitalDocuments')) {
                continue;
            }
            $itemDocs = $item->resolveDigitalDocuments();
            foreach ($itemDocs as $doc) {
                $documents[] = [
                    'item_id' => (string) $item->id,
                    'product_name' => (string) ($item->product_name ?: ($item->product->name ?? 'Produkt')),
                    'doc_id' => (string) $doc['id'],
                    'title' => (string) $doc['title'],
                    'filename' => (string) $doc['filename'],
                    'extension' => strtoupper((string) ($doc['extension'] ?: 'PDF')),
                    'size_bytes' => (int) ($doc['size_bytes'] ?? 0),
                    'path' => (string) $doc['path'],
                ];
            }
        }

        return $documents;
    }

    /**
     * Führt alle geschäftslogischen Rückabwicklungen bei Stornierung oder Erstattung durch:
     * 1. Lagerbestand: Ausschließliche Rückbuchung unpersonalisierter Standardware (Laserprodukte verfallen als Ausschuss).
     * 2. Herzensprojekt: Rückbuchung des Spendenbeitrags.
     * 3. Gekaufte Wertgutscheine: Deaktivierung, um kostenlose Einlösung nach Erstattung zu verhindern.
     * 4. Eingelöste Gutscheine: Wiederherstellung des Guthabens bzw. Dekrementierung des Einlösezählers.
     * 5. Freunde-Werben: Stornierung unberechtigter Werbeprämien.
     */
    public function handleCancellation(string $reason = 'Stornierung', ?string $previousStatus = null, ?string $previousPaymentStatus = null): void
    {
        if ($this->cancellationAlreadyHandled) {
            return;
        }
        $this->cancellationAlreadyHandled = true;

        $effectiveStatus = $previousStatus ?? $this->getOriginal('status') ?? $this->status;
        $effectivePaymentStatus = $previousPaymentStatus ?? $this->getOriginal('payment_status') ?? $this->payment_status;
        $wasPaid = in_array($effectivePaymentStatus, ['paid', 'refunded'], true);
        $alreadyCancelledOrRefunded = in_array($effectiveStatus, ['cancelled', 'refunded'], true)
            || $effectivePaymentStatus === 'refunded';

        // 1. Lagerbestand: NUR für bezahlte, unpersonalisierte Standardware und nur beim ersten Storno-/Widerrufs-Durchlauf
        if ($wasPaid && !$alreadyCancelledOrRefunded && in_array($effectiveStatus, ['pending', 'processing', 'shipped', 'completed'], true)) {
            foreach ($this->items as $item) {
                $customConfig = is_array($item->configuration)
                    ? array_diff_key($item->configuration, array_flip(['digital_documents', 'purchased_image_path', 'variant_id', 'variant_name', 'selected_attributes']))
                    : $item->configuration;
                $isPersonalized = !empty($customConfig) || ($item->product && $item->product->is_personalizable);
                if (!$isPersonalized && $item->product) {
                    $itemVariantId = is_array($item->configuration) ? ($item->configuration['variant_id'] ?? null) : null;
                    $item->product->restoreStock($item->quantity, $itemVariantId);
                }
            }
        }

        // 2. Herzensprojekt: Spendenbeitrag stornieren
        \App\Services\HerzensprojektService::reverseDonationForOrder($this, $reason);

        // 3. In dieser Order gekaufte Geschenkgutscheine sperren (Finanzschutz)
        foreach ($this->items as $item) {
            \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'current_balance' => 0,
                ]);
        }

        // 4. Vom Kunden eingelöste Gutscheine wiederherstellen (nur wenn tatsächlich bezahlt/abgebucht und noch nicht rückabgewickelt)
        if ($wasPaid && !$alreadyCancelledOrRefunded) {
            $this->restoreVouchersOnCancellation();
        }

        // 5. Freunde-Werben: Belohnung zurückbuchen
        try {
            app(\App\Services\ReferralService::class)->reverseOrderReferralReward($this);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Referral reward reversal error: ' . $e->getMessage());
        }

        // 5b. Influencer-Provision bei Storno/Widerruf zurückbuchen
        try {
            app(\App\Services\Marketing\InfluencerService::class)->reverseOrderCommission($this, $reason);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Influencer commission reversal error: ' . $e->getMessage());
        }

        // 6. Gamification: Gutgeschriebene Seelenfunken & Bestell-Titel bei Storno/Widerruf/Erstattung korrigieren (idempotent)
        try {
            app(\App\Services\Gamification\GamificationService::class)->reverseOrderFunken($this);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gamification order reversal error: ' . $e->getMessage());
        }
    }

    /**
     * Stellt verbrauchte Wertgutschein-Guthaben oder Aktionsgutschein-Zähler bei Stornierung/Erstattung wieder her.
     */
    public function restoreVouchersOnCancellation(): void
    {
        if (empty($this->coupon_code)) {
            return;
        }

        $effectivePaymentStatus = $this->getOriginal('payment_status') ?? $this->payment_status;
        if (!in_array($effectivePaymentStatus, ['paid', 'refunded'], true) && !in_array($this->payment_status, ['paid', 'refunded'], true)) {
            return;
        }

        // 1. Wertgutschein (MarketingGiftVoucher)
        $giftVoucher = \App\Models\Marketing\MarketingGiftVoucher::where('code', $this->coupon_code)->first();
        if ($giftVoucher && $this->discount_amount > 0) {
            // Idempotenz: Wurde die Rückbuchung für diese Bestellung bereits durchgeführt?
            $alreadyReversed = \App\Models\Marketing\MarketingGiftVoucherLog::where('gift_voucher_id', $giftVoucher->id)
                ->where('order_id', $this->id)
                ->where('amount', '<', 0)
                ->exists();

            if (!$alreadyReversed) {
                $usedLog = \App\Models\Marketing\MarketingGiftVoucherLog::where('gift_voucher_id', $giftVoucher->id)
                    ->where('order_id', $this->id)
                    ->where('amount', '>', 0)
                    ->first();

                $refundAmount = ($usedLog && $usedLog->amount > 0)
                    ? (int) $usedLog->amount
                    : max(0, (int) $this->discount_amount);

                if ($refundAmount > 0) {
                    $maxAllowedBalance = max((int) $giftVoucher->initial_value, (int) $giftVoucher->current_balance);
                    $newBalance = min($maxAllowedBalance, (int) $giftVoucher->current_balance + $refundAmount);

                    $giftVoucher->update([
                        'current_balance' => $newBalance,
                        'is_active' => true,
                    ]);

                    \App\Models\Marketing\MarketingGiftVoucherLog::create([
                        'gift_voucher_id' => $giftVoucher->id,
                        'order_id' => $this->id,
                        'amount' => -$refundAmount,
                        'remaining_balance' => $newBalance,
                    ]);

                    \Illuminate\Support\Facades\Log::info("Gutscheinguthaben von {$refundAmount} Cents für Gutschein {$giftVoucher->code} nach Storno von Order {$this->order_number} erstattet.");
                }
            }
        }

        // 2. Rabattcode (MarketingVoucher)
        $mv = \App\Models\Marketing\MarketingVoucher::where('code', $this->coupon_code)->first();
        if ($mv && $mv->used_count > 0) {
            $mv->decrement('used_count');
            if ($mv->isInfluencerSample() && !empty($mv->influencer_id)) {
                $mv->update(['is_active' => true]);
                \App\Models\Marketing\MarketingInfluencer::where('id', $mv->influencer_id)
                    ->where('sample_order_id', $this->id)
                    ->update(['sample_order_id' => null]);
            }
            \Illuminate\Support\Facades\Log::info("Rabattcode-Zähler für {$mv->code} nach Storno von Order {$this->order_number} zurückgesetzt.");
        }
    }

    /**
     * Prüft, ob die Bestellung Positionen mit Lieferanten-Rohling-Beschaffung (Auftragsfertigung bei Bestand 0) enthält.
     */
    public function hasSupplierProcurementItems(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        return $items->contains(fn (OrderOrderItem $item) => $item->requiresSupplierProcurement());
    }

    /**
     * Prüft, ob für mindestens eine Position der Rohling noch beim Lieferanten nachbestellt werden muss.
     */
    public function hasPendingSupplierProcurement(): bool
    {
        if (in_array($this->status, ['cancelled', 'completed', 'shipped'], true)) {
            return false;
        }

        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        return $items->contains(fn (OrderOrderItem $item) => $item->requiresSupplierProcurement() && !$item->isSupplierOrderPlaced() && !$item->is_completed);
    }

    /**
     * Liefert alle Bestellpositionen, bei denen der Rohling noch nicht beim Lieferanten bestellt wurde.
     */
    public function getPendingSupplierProcurementItems(): \Illuminate\Support\Collection
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        return $items->filter(fn (OrderOrderItem $item) => $item->requiresSupplierProcurement() && !$item->isSupplierOrderPlaced() && !$item->is_completed)->values();
    }

    /**
     * Prüft, ob diese Bestellung gemäß GoBD / AO unwiderruflich aus der Datenbank gelöscht werden darf.
     * Erlaubt ist dies ausschließlich bei unbezahlten Kaufabbrüchen, Fehlversuchen oder Testbestellungen:
     * - Zahlungsstatus muss 'unpaid' oder 'failed' sein (niemals 'paid' oder 'refunded')
     * - Bestellstatus darf nicht produziert/versendet sein ('pending', 'failed' oder 'cancelled')
     * - Es darf weder eine Rechnung/Gutschrift noch ein DHL-Versandlabel existieren
     */
    public function canBePermanentlyDeleted(): bool
    {
        if (!in_array((string) $this->payment_status, ['unpaid', 'failed', 'open', 'pending'], true)) {
            return false;
        }

        if (!in_array((string) $this->status, ['pending', 'failed', 'cancelled'], true)) {
            return false;
        }

        $hasInvoices = $this->relationLoaded('invoices')
            ? $this->invoices->isNotEmpty()
            : $this->invoices()->exists();

        if ($hasInvoices) {
            return false;
        }

        $hasShipments = $this->relationLoaded('shipments')
            ? $this->shipments->isNotEmpty()
            : $this->shipments()->exists();

        if ($hasShipments) {
            return false;
        }

        return true;
    }

    /**
     * Löscht eine unbezahlte Abbruch-/Testbestellung rückstandslos (inkl. Positionen und nicht mehr anderweitig
     * benötigter Kunden-Uploads & 3D-Snapshots) per forceDelete().
     *
     * @return array<int, string> Liste der gelöschten Dateipfade
     */
    public function permanentlyDeleteUnpaidOrder(): array
    {
        if (!$this->canBePermanentlyDeleted()) {
            throw new \DomainException('Bezahlte, versendete oder bereits abgerechnete Bestellungen dürfen gemäß GoBD nicht gelöscht werden (bitte stattdessen stornieren).');
        }

        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $itemConfigurations = $items->pluck('configuration')->all();

        $this->items()->delete();
        $this->forceDelete();

        return app(\App\Services\CartFileCleanupService::class)->cleanupOrderFilesAfterDeletion($itemConfigurations);
    }

    /**
     * Aggregiert die Manufaktur-Checkliste (1. Besondere Wünsche, 2. Geschenkpapier + Modus, 3. Edle Schmuckbox, 4. Gravur)
     * über alle Bestellpositionen hinweg und ermittelt den autonomen 3-Schritte-Fortschritt.
     */
    public function getManufacturingOverview(): array
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items.product');
        }

        $totalItems = $this->items->count();
        $completedItems = 0;
        $hasNotes = false;
        $notesDone = true;
        $notesTexts = [];

        $hasGiftWrap = false;
        $giftWrapModes = [];
        $giftWrapDone = true;

        $hasGiftBox = false;
        $giftBoxCount = 0;
        $giftBoxDone = true;

        $openActionCount = 0;
        $requiresFragileSticker = false;
        $fragileItemNames = [];

        foreach ($this->items as $item) {
            if ($item->is_completed) {
                $completedItems++;
            }

            $product = $item->product;
            $prodConf = $product
                ? (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : ($product->configurator_settings ?? []))
                : [];
            $haystack = mb_strtolower(implode(' ', [
                (string) ($item->product_name ?? ''),
                (string) ($product->name ?? ''),
                (string) ($prodConf['material_type'] ?? ''),
                (string) ($prodConf['xtool_material'] ?? ''),
            ]));
            foreach (['kristall', 'glas', 'glass', 'trophäe', 'trophaee', 'schiefer', 'keramik', 'porzellan', 'spiegel'] as $fragileKeyword) {
                if (str_contains($haystack, $fragileKeyword)) {
                    $requiresFragileSticker = true;
                    $fragileItemNames[] = (string) ($item->product_name ?: ($product->name ?? 'Artikel'));
                    break;
                }
            }

            $checklist = $item->resolveManufacturingChecklist();
            $openActionCount += (int) ($checklist['open_action_count'] ?? 0);

            if (!empty($checklist['notes']['has_note'])) {
                $hasNotes = true;
                $notesTexts[] = $checklist['notes']['text'];
                if (empty($checklist['notes']['checked'])) {
                    $notesDone = false;
                }
            }

            if (!empty($checklist['gift_wrap']['selected'])) {
                $hasGiftWrap = true;
                $giftWrapModes[] = $checklist['gift_wrap']['mode'];
                if (empty($checklist['gift_wrap']['checked'])) {
                    $giftWrapDone = false;
                }
            }

            if (!empty($checklist['gift_box']['selected'])) {
                $hasGiftBox = true;
                $giftBoxCount += max(1, (int) $item->quantity);
                if (empty($checklist['gift_box']['checked'])) {
                    $giftBoxDone = false;
                }
            }
        }

        $orderNote = trim((string) ($this->notes ?? ''));
        if ($orderNote !== '' && !in_array($orderNote, $notesTexts, true)) {
            $hasNotes = true;
            $notesTexts[] = $orderNote;
        }

        $uniqueWrapModes = array_values(array_unique($giftWrapModes));
        $giftWrapSummaryLabel = 'Nein';
        if ($hasGiftWrap) {
            if (in_array('wrapped', $uniqueWrapModes, true) && in_array('loose', $uniqueWrapModes, true)) {
                $giftWrapSummaryLabel = 'Ja (Gemischt: Verpacken & Dazu legen)';
            } elseif (in_array('loose', $uniqueWrapModes, true)) {
                $giftWrapSummaryLabel = 'Ja (Nur als Bogen dazu legen)';
            } else {
                $giftWrapSummaryLabel = 'Ja (Fertig als Geschenk verpacken)';
            }
        }

        $giftBoxSummaryLabel = $hasGiftBox
            ? ('Ja (' . $giftBoxCount . 'x Edle Schmuckbox)')
            : 'Nein';

        $notesSummaryLabel = $hasNotes
            ? ('Ja: ' . implode(' | ', array_unique($notesTexts)))
            : 'Keine besonderen Wünsche';

        $allStepOneTasksCompleted = $totalItems > 0 && $completedItems >= $totalItems && $openActionCount === 0;

        $firstItemConf = ($this->items->first() && is_array($this->items->first()->configuration))
            ? $this->items->first()->configuration
            : [];
        $legacyPackingChecks = is_array($firstItemConf['order_packing_checks'] ?? null)
            ? $firstItemConf['order_packing_checks']
            : [];
        $shippingChecklistRaw = is_array($firstItemConf['shipping_checklist'] ?? null)
            ? $firstItemConf['shipping_checklist']
            : [];
        $packingChecks = array_merge($legacyPackingChecks, $shippingChecklistRaw);

        $isAlreadyShipped = in_array((string) $this->status, ['shipped', 'completed'], true);
        $cleanedChecked = (bool) ($packingChecks['cleaned'] ?? $isAlreadyShipped);
        $thankYouCardChecked = (bool) ($packingChecks['thank_you_card'] ?? $isAlreadyShipped);
        $fragileStickerChecked = (bool) ($packingChecks['fragile_sticker'] ?? $isAlreadyShipped);
        $giftBoxPackingChecked = (bool) ($packingChecks['gift_box'] ?? $isAlreadyShipped);
        $giftWrapPackingChecked = (bool) ($packingChecks['gift_wrap'] ?? $isAlreadyShipped);

        $shippingChecklist = [
            'cleaned' => $cleanedChecked,
            'gift_box' => $giftBoxPackingChecked,
            'gift_wrap' => $giftWrapPackingChecked,
            'thank_you_card' => $thankYouCardChecked,
            'fragile_sticker' => $fragileStickerChecked,
        ];

        $packingChecklistItems = [];
        if (!$this->isOnlyDigital() && $totalItems > 0) {
            $packingChecklistItems[] = [
                'key' => 'cleaned',
                'label' => 'Reinigen · Isopropanol & Tuch',
                'checked' => $cleanedChecked,
            ];

            if ($hasGiftBox) {
                $packingChecklistItems[] = [
                    'key' => 'gift_box',
                    'label' => $giftBoxCount > 1 ? ($giftBoxCount . '× Schmuckbox') : 'Schmuckbox',
                    'checked' => $giftBoxPackingChecked,
                ];
            }

            if ($hasGiftWrap) {
                if (in_array('loose', $uniqueWrapModes, true) && !in_array('wrapped', $uniqueWrapModes, true)) {
                    $wrapChecklistLabel = 'Geschenkpapier · Beilegen';
                } elseif (in_array('loose', $uniqueWrapModes, true)) {
                    $wrapChecklistLabel = 'Geschenkpapier · Einpacken & Beilegen';
                } else {
                    $wrapChecklistLabel = 'Geschenkpapier · Einpacken';
                }

                $packingChecklistItems[] = [
                    'key' => 'gift_wrap',
                    'label' => $wrapChecklistLabel,
                    'checked' => $giftWrapPackingChecked,
                ];
            }

            $packingChecklistItems[] = [
                'key' => 'thank_you_card',
                'label' => 'Dankeskarte',
                'checked' => $thankYouCardChecked,
            ];

            if ($requiresFragileSticker) {
                $packingChecklistItems[] = [
                    'key' => 'fragile_sticker',
                    'label' => 'Aufkleber Zerbrechlich',
                    'checked' => $fragileStickerChecked,
                ];
            }
        }

        $autonomousStep = match ((string) $this->status) {
            'pending' => 0, // Wartend -> Start-Screen ("Jetzt starten")
            'processing' => $allStepOneTasksCompleted ? 2 : 1,
            'shipped' => 3,
            'completed' => 3,
            default => 1,
        };

        return [
            'autonomous_step' => $autonomousStep,
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'open_action_count' => $openActionCount,
            'all_step_one_done' => $allStepOneTasksCompleted,
            'has_notes' => $hasNotes,
            'notes_done' => $notesDone,
            'notes_summary' => $notesSummaryLabel,
            'has_gift_wrap' => $hasGiftWrap,
            'gift_wrap_modes' => $uniqueWrapModes,
            'gift_wrap_done' => $giftWrapDone,
            'gift_wrap_summary' => $giftWrapSummaryLabel,
            'has_gift_box' => $hasGiftBox,
            'gift_box_count' => $giftBoxCount,
            'gift_box_done' => $giftBoxDone,
            'gift_box_summary' => $giftBoxSummaryLabel,
            'cleaned_checked' => $cleanedChecked,
            'thank_you_card_checked' => $thankYouCardChecked,
            'requires_fragile_sticker' => $requiresFragileSticker,
            'fragile_items' => array_values(array_unique($fragileItemNames)),
            'fragile_sticker_checked' => $fragileStickerChecked,
            'gift_box_packing_checked' => $giftBoxPackingChecked,
            'gift_wrap_packing_checked' => $giftWrapPackingChecked,
            'shipping_checklist' => $shippingChecklist,
            'packing_checklist_items' => $packingChecklistItems,
        ];
    }

    /**
     * Liefert die gespeicherten Versand-/Pack-Haken als assoziatives Array.
     */
    public function getShippingChecklistAttribute(): array
    {
        return $this->getManufacturingOverview()['shipping_checklist'];
    }

    /**
     * Liefert die chronologische Pack-Checkliste (nur zutreffende Punkte):
     * 1. Reinigen · Isopropanol & Tuch -> 2. Schmuckbox -> 3. Geschenkpapier -> 4. Dankeskarte -> 5. Aufkleber Zerbrechlich.
     *
     * @return array<int, array{key: string, label: string, checked: bool}>
     */
    public function resolvePackingChecklistItems(): array
    {
        return $this->getManufacturingOverview()['packing_checklist_items'];
    }

    /**
     * Schaltet einen Packtisch-Pruefpunkt ('cleaned', 'gift_box', 'gift_wrap', 'thank_you_card', 'fragile_sticker') um.
     */
    public function togglePackingCheck(string $checkKey, ?bool $forceState = null): void
    {
        if (!in_array($checkKey, ['cleaned', 'thank_you_card', 'fragile_sticker', 'gift_box', 'gift_wrap'], true)) {
            return;
        }

        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }

        $firstItem = $this->items->first();
        if (!$firstItem) {
            return;
        }

        $conf = is_array($firstItem->configuration) ? $firstItem->configuration : [];
        $legacyChecks = is_array($conf['order_packing_checks'] ?? null) ? $conf['order_packing_checks'] : [];
        $shippingChecks = is_array($conf['shipping_checklist'] ?? null) ? $conf['shipping_checklist'] : [];
        $packingChecks = array_merge($legacyChecks, $shippingChecks);

        $current = (bool) ($packingChecks[$checkKey] ?? false);
        $nextValue = $forceState !== null ? $forceState : !$current;
        $packingChecks[$checkKey] = $nextValue;

        $conf['order_packing_checks'] = $packingChecks;
        $conf['shipping_checklist'] = $packingChecks;

        $firstItem->configuration = $conf;
        $firstItem->save();
    }

    /**
     * Hakt alle fuer diese Bestellung zutreffenden Pack-Checklistenpunkte ab und gibt die aktualisierte Liste zurueck.
     *
     * @return array<int, array{key: string, label: string, checked: bool}>
     */
    public function markAllPackingChecks(bool $checked = true): array
    {
        $items = $this->resolvePackingChecklistItems();
        if (empty($items)) {
            return [];
        }

        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }

        $firstItem = $this->items->first();
        if ($firstItem) {
            $conf = is_array($firstItem->configuration) ? $firstItem->configuration : [];
            $legacyChecks = is_array($conf['order_packing_checks'] ?? null) ? $conf['order_packing_checks'] : [];
            $shippingChecks = is_array($conf['shipping_checklist'] ?? null) ? $conf['shipping_checklist'] : [];
            $packingChecks = array_merge($legacyChecks, $shippingChecks);

            foreach ($items as $entry) {
                $packingChecks[$entry['key']] = $checked;
            }

            $conf['order_packing_checks'] = $packingChecks;
            $conf['shipping_checklist'] = $packingChecks;
            $firstItem->configuration = $conf;
            $firstItem->save();
        }

        $this->load('items.product');
        return $this->resolvePackingChecklistItems();
    }

    /**
     * Berechnet den optimalen Versandkarton fuer die gesamte Bestellung anhand der echten Seelenfunke-Kartons:
     * Luftpolstertasche -> Buchverpackung (Trophäe) -> Normaler Karton -> Mittlerer Karton -> Großer Karton.
     *
     * @return array{sku: string, box_name: string, box_dimensions: string, package_count: int}
     */
    public function resolveRecommendedShippingBox(): array
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items.product');
        }

        if ($this->items->isEmpty() || $this->isOnlyDigital()) {
            return [
                'sku' => '',
                'box_name' => 'Digitaler Versand',
                'box_dimensions' => '',
                'package_count' => 0,
            ];
        }

        $totalPhysicalQty = 0;
        $voucherPostQty = 0;
        $trophyOrSlateQty = 0;
        $glassOrTallQty = 0;
        $giftBoxCount = 0;
        $totalWeightGrams = 0;

        foreach ($this->items as $item) {
            $product = $item->product;
            $conf = is_array($item->configuration) ? $item->configuration : [];
            $isVoucherPost = !empty($conf['is_gift_voucher']) && ($conf['delivery_method'] ?? 'email') === 'post';
            $isDigital = !empty($conf['is_digital'])
                || ($product && $product->type === 'digital' && !$isVoucherPost);
            $isService = $product && $product->type === 'service';

            if ($isDigital || $isService) {
                continue;
            }

            $qty = max(1, (int) $item->quantity);
            $totalPhysicalQty += $qty;
            if ($isVoucherPost) {
                $voucherPostQty += $qty;
            }

            $checklist = $item->resolveManufacturingChecklist();
            $slugLower = mb_strtolower((string) ($product->slug ?? ''));
            $nameLower = mb_strtolower(((string) ($item->product_name ?? '')) . ' ' . ((string) ($product->name ?? '')));
            $haystack = $slugLower . ' ' . $nameLower;

            if (!empty($checklist['gift_box']['selected']) || str_contains($slugLower, 'echtgold')) {
                $giftBoxCount += $qty;
            }

            $itemWeight = ($product && $product->weight > 0) ? (int) $product->weight : 100;
            $totalWeightGrams += ($itemWeight * $qty);

            if (
                str_contains($haystack, 'kristall')
                || str_contains($haystack, 'schiefer')
                || str_contains($haystack, 'trophäe')
                || str_contains($haystack, 'trophaee')
            ) {
                $trophyOrSlateQty += $qty;
            } elseif (
                str_contains($haystack, 'weizenglas')
                || str_contains($haystack, 'glas')
                || str_contains($haystack, 'tasse')
                || str_contains($haystack, 'becher')
                || str_contains($haystack, 'flasche')
            ) {
                $glassOrTallQty += $qty;
            }
        }

        if ($totalPhysicalQty === 0) {
            return [
                'sku' => '',
                'box_name' => 'Digitaler Versand',
                'box_dimensions' => '',
                'package_count' => 0,
            ];
        }

        $totalWeightGrams += ($giftBoxCount * 20);

        if (
            $totalWeightGrams > 9000
            || $trophyOrSlateQty >= 10
            || $glassOrTallQty >= 12
            || $totalPhysicalQty >= 25
            || $giftBoxCount >= 16
        ) {
            $sku = 'PKG-BOX-LARGE-069';
            $fallbackName = 'Großer Karton';
            $fallbackDims = '600 × 400 × 400 mm';
        } elseif (
            $totalWeightGrams > 2800
            || $trophyOrSlateQty >= 4
            || $glassOrTallQty >= 5
            || $totalPhysicalQty >= 10
            || $giftBoxCount >= 6
        ) {
            $sku = 'PKG-BOX-MEDIUM-165';
            $fallbackName = 'Mittlerer Karton';
            $fallbackDims = '600 × 400 × 200 mm';
        } elseif (
            $trophyOrSlateQty >= 2
            || ($trophyOrSlateQty === 1 && $totalPhysicalQty >= 2)
            || $glassOrTallQty >= 1
            || $giftBoxCount >= 2
            || $totalPhysicalQty >= 3
            || ($totalPhysicalQty >= 2 && $giftBoxCount >= 1)
            || $totalWeightGrams > 1200
        ) {
            $sku = 'PKG-BOX-NORMAL-2633';
            $fallbackName = 'Normaler Karton';
            $fallbackDims = '300 × 230 × 150 mm';
        } elseif ($trophyOrSlateQty === 1) {
            $sku = 'PKG-BOX-CRYSTAL';
            $fallbackName = 'Buchverpackung (Trophäe)';
            $fallbackDims = '284 × 244 × 70 mm';
        } elseif ($voucherPostQty > 0 && $voucherPostQty === $totalPhysicalQty) {
            $sku = 'PKG-ENV-VOUCHER';
            $fallbackName = 'Vollpapp-Versandtasche DIN A5';
            $fallbackDims = '250 × 175 × 5 mm';
        } else {
            $sku = 'PKG-ENV-A1';
            $fallbackName = 'Luftpolstertasche';
            $fallbackDims = '260 × 180 × 10 mm';
        }

        $boxName = $fallbackName;
        $boxDims = $fallbackDims;

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('product_packaging_items')) {
                $catalogItem = \App\Models\Product\ProductPackagingItem::where('sku', $sku)->first();
                if ($catalogItem) {
                    $boxName = (string) $catalogItem->name;
                    $boxDims = (string) $catalogItem->formatted_dimensions;
                }
            }
        } catch (\Throwable $e) {
            // Fallback auf Standardwerte in isolierten Unit-Tests
        }

        $packageCount = $totalWeightGrams > 22000
            ? max(2, min(30, (int) ceil($totalWeightGrams / 20000)))
            : 1;

        return [
            'sku' => $sku,
            'box_name' => $boxName,
            'box_dimensions' => $boxDims,
            'package_count' => $packageCount,
        ];
    }

    /**
     * Erstellt den detaillierten Packtisch-Plan fuer Schritt 2 (Versand):
     * Nutzt die klaren Karton-Namen (Luftpolstertasche, Buchverpackung (Trophäe), Normaler Karton, Mittlerer Karton, Großer Karton).
     */
    public function getPackingStationPlan(): array
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items.product');
        }

        $dhlDetails = !$this->isOnlyDigital()
            ? $this->getDhlWeightDetails(1, 'box')
            : null;

        $suggestedType = (string) ($dhlDetails['suggested_packaging_type'] ?? 'box');
        if ($suggestedType === 'envelope' && !$this->isOnlyDigital()) {
            $dhlDetails = $this->getDhlWeightDetails(1, 'envelope');
        }

        $recommendedBox = $this->resolveRecommendedShippingBox();
        $itemRows = [];
        $physicalProductLabels = [];

        foreach ($this->items as $item) {
            $product = $item->product;
            $qty = max(1, (int) $item->quantity);
            $checklist = $item->resolveManufacturingChecklist();
            $conf = is_array($item->configuration) ? $item->configuration : [];
            $isDigital = !empty($conf['is_digital'])
                || ($product && $product->type === 'digital' && !(!empty($conf['is_gift_voucher']) && ($conf['delivery_method'] ?? 'email') === 'post'));
            $isService = $product && $product->type === 'service';

            if ($isDigital || $isService) {
                $itemRows[] = [
                    'item_id' => $item->id,
                    'product_name' => (string) ($item->product_name ?: ($product->name ?? 'Artikel')),
                    'quantity' => $qty,
                    'is_physical' => false,
                    'box_label' => $isDigital ? 'Digitaler Versand (E-Mail / Kundenportal)' : 'Service-Position (Kein physischer Karton)',
                    'box_dimensions' => '',
                    'packing_instructions' => ['Kein physischer Versandkarton erforderlich'],
                ];
                continue;
            }

            $itemLabel = (string) ($item->product_name ?: ($product->name ?? 'Artikel'));
            $physicalProductLabels[] = $qty . '× ' . $itemLabel;

            $instructions = [];
            $slugLower = mb_strtolower((string) ($product->slug ?? ''));
            $nameLower = mb_strtolower($itemLabel);
            $isVoucherPost = !empty($conf['is_gift_voucher']) || str_contains($slugLower, 'gutschein') || str_contains($nameLower, 'gutschein');
            $isHeavyOrBoxed = str_contains($slugLower, 'kristall')
                || str_contains($slugLower, 'schiefer')
                || str_contains($slugLower, 'weizenglas')
                || str_contains($nameLower, 'kristall')
                || str_contains($nameLower, 'schiefer')
                || str_contains($nameLower, 'glas');

            if (!empty($checklist['gift_box']['selected']) || str_contains($slugLower, 'echtgold')) {
                $instructions[] = 'Schmuckbox (86 × 86 × 17 mm) · flach mit Goldband sichern';
            } elseif (!$isVoucherPost && !$isHeavyOrBoxed) {
                if (str_contains($slugLower, 'armband') || str_contains($nameLower, 'armband')) {
                    $instructions[] = 'Schwarzer Papierflachbeutel (13 × 16,5 cm)';
                } else {
                    $instructions[] = 'Schwarze Kraftpapier-Schmucktüte (10 × 6 cm)';
                }
            }

            if (!empty($checklist['gift_wrap']['selected'])) {
                if (($checklist['gift_wrap']['mode'] ?? 'wrapped') === 'loose') {
                    $instructions[] = 'Geschenkpapier · Beilegen';
                } else {
                    $instructions[] = 'Geschenkpapier · Einpacken';
                }
            }

            $itemRows[] = [
                'item_id' => $item->id,
                'product_name' => $itemLabel,
                'variant_name' => (string) ($conf['variant_name'] ?? ''),
                'quantity' => $qty,
                'is_physical' => true,
                'box_label' => $recommendedBox['box_name'],
                'box_dimensions' => $recommendedBox['box_dimensions'],
                'packing_instructions' => $instructions,
            ];
        }

        $cartons = [];
        if (!empty($physicalProductLabels) && $recommendedBox['package_count'] > 0) {
            $cartons[] = [
                'key' => $recommendedBox['sku'] ?: $recommendedBox['box_name'],
                'label' => $recommendedBox['box_name'],
                'dimensions' => $recommendedBox['box_dimensions'],
                'count' => $recommendedBox['package_count'],
                'products' => $physicalProductLabels,
            ];
        }

        return [
            'outer_package_count' => max((int) ($dhlDetails['recommended_package_count'] ?? 1), (int) $recommendedBox['package_count']),
            'outer_box_hint' => $recommendedBox['box_dimensions'] !== ''
                ? ($recommendedBox['box_name'] . ' (' . $recommendedBox['box_dimensions'] . ')')
                : (string) ($dhlDetails['recommended_box_hint'] ?? 'Normaler Karton'),
            'box_name' => $recommendedBox['box_name'],
            'box_dimensions' => $recommendedBox['box_dimensions'],
            'dhl_class' => (string) ($dhlDetails['dhl_class'] ?? 'Paket bis 2 kg'),
            'total_weight_kg' => (float) ($dhlDetails['recommended_weight_per_package'] ?? 0.5),
            'is_bulk_order' => (bool) ($dhlDetails['is_bulk_order'] ?? ($recommendedBox['package_count'] > 1)),
            'cartons' => $cartons,
            'items' => $itemRows,
        ];
    }

    /**
     * Erzeugt und speichert autonom die aktuelle Werkstatt-Status-Zusammenfassung für Funkira,
     * sodass das manuelle Feld "Werkstatt-Stand" komplett entfällt.
     */
    public function syncAutonomousWorkshopSummary(): string
    {
        $overview = $this->getManufacturingOverview();

        if ($this->status === 'pending') {
            $summary = 'Wartend auf Produktionsstart (Schritt 0)';
        } elseif ($this->status === 'completed') {
            $summary = 'Schritt 3 erledigt: Bestellung komplett abgeschlossen';
        } elseif ($this->status === 'shipped') {
            $tracking = $this->tracking_number ? (' (DHL: ' . $this->tracking_number . ')') : '';
            $summary = 'Schritt 2 erledigt: Versendet' . $tracking . ' · Bereit für Schritt 3 (Abschluss)';
        } elseif ($overview['all_step_one_done']) {
            $extras = [];
            if ($overview['has_gift_box']) {
                $extras[] = 'Schmuckbox erledigt';
            }
            if ($overview['has_gift_wrap']) {
                $extras[] = $overview['gift_wrap_summary'] . ' erledigt';
            }
            if ($overview['has_notes']) {
                $extras[] = 'Manufaktur-Wunsch umgesetzt';
            }
            $extrasSuffix = !empty($extras) ? (' (' . implode(', ', $extras) . ')') : '';
            $summary = 'Schritt 1 abgeschlossen: Alle ' . $overview['total_items'] . ' Positionen gefertigt' . $extrasSuffix . ' · Jetzt Schritt 2: DHL-Versand';
        } else {
            $openParts = [];
            $openItems = max(0, $overview['total_items'] - $overview['completed_items']);
            if ($openItems > 0) {
                $openParts[] = $openItems . '/' . $overview['total_items'] . ' Gravur(en) offen';
            }
            if ($overview['has_notes'] && !$overview['notes_done']) {
                $openParts[] = 'Kundenwunsch beachten';
            }
            if ($overview['has_gift_box'] && !$overview['gift_box_done']) {
                $openParts[] = 'Edle Schmuckbox einlegen';
            }
            if ($overview['has_gift_wrap'] && !$overview['gift_wrap_done']) {
                $openParts[] = $overview['gift_wrap_summary'];
            }
            $summary = 'Schritt 1 (In Bearbeitung): ' . (!empty($openParts) ? implode(' · ', $openParts) : 'Fertigung läuft');
        }

        $this->workshop_note = mb_substr($summary, 0, 250);
        $this->workshop_note_updated_at = now();
        $this->saveQuietly();

        return $this->workshop_note;
    }
}



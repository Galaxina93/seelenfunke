<?php

namespace App\Livewire\Shop\Order;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

#[Layout('components.layouts.backend_layout')]
class OrderQuoteRequests extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Bestellungen';

    use WithPagination;

    public $search = '';
    public $filterStatus = '';

    // Detail View State
    #[\Livewire\Attributes\Locked]
    public $selectedQuoteId = null;
    #[\Livewire\Attributes\Locked]
    public $selectedQuoteItemId = null; // NEU: Für die Vorschau rechts

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function render()
    {
        if ($this->selectedQuoteId) {
            return view('livewire.shop.order.order-quote-requests-detail', [
                'quote' => OrderQuoteRequest::with(['items.product', 'customer'])->find($this->selectedQuoteId)
            ]);
        }

        $query = OrderQuoteRequest::query()->latest();

        if ($this->search) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function($q) use ($escapedSearch) {
                $q->where('quote_number', 'like', '%'.$escapedSearch.'%')
                    ->orWhere('email', 'like', '%'.$escapedSearch.'%')
                    ->orWhere('last_name', 'like', '%'.$escapedSearch.'%')
                    ->orWhere('company', 'like', '%'.$escapedSearch.'%');
            });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return view('livewire.shop.order.order-quote-requests', [
            'quotes' => $query->paginate(10)
        ]);
    }

    // --- ACTIONS ---

    public function selectQuote($id)
    {
        $this->selectedQuoteId = $id;

        // NEU: Automatisch erstes Item für Vorschau wählen
        $quote = OrderQuoteRequest::with('items')->find($id);
        if ($quote && $quote->items->isNotEmpty()) {
            $this->selectedQuoteItemId = $quote->items->first()->id;
        }
    }

    // NEU: Item wechseln für rechte Spalte
    public function selectItemForPreview($itemId)
    {
        $this->selectedQuoteItemId = $itemId;
    }

    // NEU: Property für die View (strikt an das ausgewählte Angebot gebunden)
    public function getPreviewItemProperty()
    {
        if (!$this->selectedQuoteId || !$this->selectedQuoteItemId) return null;
        return OrderQuoteRequestItem::where('quote_request_id', $this->selectedQuoteId)
            ->with('product')
            ->find($this->selectedQuoteItemId);
    }

    public function closeDetail()
    {
        $this->selectedQuoteId = null;
        $this->selectedQuoteItemId = null;
    }

    /**
     * Neue Signatur: Wir übergeben den gewünschten Typ direkt beim Klick.
     * $type kann sein: 'invoice' oder 'stripe_link'
     */
    /**
     * Neue Signatur: Wir übergeben den gewünschten Typ direkt beim Klick.
     * $type kann sein: 'invoice' oder 'stripe_link'
     */
    public function convertToOrder($quoteId, $type = 'invoice')
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $order = \Illuminate\Support\Facades\DB::transaction(function () use ($quoteId, $type, &$quote) {
            $quote = OrderQuoteRequest::with('items')->lockForUpdate()->find($quoteId);

            // Sicherheitscheck: Existiert das Angebot und ist es nicht schon umgewandelt?
            if (!$quote || $quote->status === 'converted') {
                return null;
            }

            $isSmallBusiness = (bool) shop_setting('is_small_business', false);

            // ---------------------------------------------------------
            // 1. KUNDEN ERSTELLEN ODER FINDEN
            // ---------------------------------------------------------
            $customer = Customer::withTrashed()->firstOrCreate(
                ['email' => $quote->email],
                [
                    'first_name' => $quote->first_name,
                    'last_name' => $quote->last_name,
                    'password' => Hash::make(Str::random(16)), // Zufallspasswort
                ]
            );
            if ($customer->trashed()) {
                $customer->restore();
            }

            // Kundendaten im Profil nur bei Neukunden-Anlage oder fehlendem Profil setzen (Schutz bestehender Kundenprofile vor Überschreibung durch Gast-Anfragen)
            if ($customer->wasRecentlyCreated || !$customer->profile) {
                $customer->profile()->updateOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'phone_number' => $quote->phone,
                        'street' => $quote->street ?? '',
                        'house_number' => $quote->house_number ?? '',
                        'postal' => $quote->postal,
                        'city' => $quote->city,
                        'country' => $quote->country ?? 'DE',
                    ]
                );
            }

            // ---------------------------------------------------------
            // 2. ORDER ERSTELLEN
            // ---------------------------------------------------------
            // Wir speichern in der DB, was gewählt wurde
            $methodDbString = ($type === 'stripe_link') ? 'stripe_link' : 'invoice';

            $profile = $customer->profile;
            $resolvedStreet = trim(($quote->street ?? ($profile->street ?? '')) . ' ' . ($quote->house_number ?? ($profile->house_number ?? '')));
            $resolvedPostal = $quote->postal ?: ($profile->postal ?? '');
            $resolvedCity = $quote->city ?: ($profile->city ?? '');
            $resolvedCountry = $quote->country ?: ($profile->country ?? 'DE');

            $order = OrderOrder::create([
                'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
                'customer_id' => $customer->id,
                'email' => $quote->email,
                'status' => 'pending',
                'payment_status' => 'unpaid',

                // Wir setzen 'stripe_link', damit wir wissen: Hier wurde ein Link gesendet
                'payment_method' => $methodDbString,

                // WICHTIG: Express Daten & Versandkosten übernehmen!
                'is_express' => $quote->is_express,
                'express_price' => $quote->express_price ?? 0,
                'deadline' => $quote->deadline,
                'shipping_price' => $quote->shipping_price ?? 0,
                'is_small_business' => $isSmallBusiness,

                'billing_address' => [
                    'first_name' => $quote->first_name ?: ($customer->first_name ?? ''),
                    'last_name' => $quote->last_name ?: ($customer->last_name ?? ''),
                    'company' => $quote->company,
                    'address' => $resolvedStreet,
                    'postal_code' => $resolvedPostal,
                    'city' => $resolvedCity,
                    'country' => $resolvedCountry,
                ],
                // Lieferadresse initial identisch zur Rechnungsadresse
                'shipping_address' => [
                    'first_name' => $quote->first_name ?: ($customer->first_name ?? ''),
                    'last_name' => $quote->last_name ?: ($customer->last_name ?? ''),
                    'company' => $quote->company,
                    'address' => $resolvedStreet,
                    'postal_code' => $resolvedPostal,
                    'city' => $resolvedCity,
                    'country' => $resolvedCountry,
                ],

                // Rabatte exakt aus dem Angebot übernehmen
                'volume_discount' => $quote->volume_discount ?? 0,
                'discount_amount' => $quote->discount_amount ?? 0,
                'coupon_code'     => $quote->coupon_code ?? null,

                'subtotal_price' => max(0, (int) $quote->gross_total + (int) ($quote->discount_amount ?? 0) - (int) ($quote->shipping_price ?? 0) - (int) ($quote->express_price ?? 0)),
                'tax_amount' => $isSmallBusiness ? 0 : $quote->tax_total,
                'total_price' => $quote->gross_total,
                'notes' => 'Aus Angebot ' . $quote->quote_number . ' generiert. ' . $quote->admin_notes,
            ]);

            // ---------------------------------------------------------
            // 3. ITEMS ÜBERTRAGEN
            // ---------------------------------------------------------
            $cartService = app(\App\Services\CartService::class);
            foreach($quote->items as $qItem) {
                $safeConfig = is_array($qItem->configuration) ? $qItem->configuration : null;
                if ($qItem->product && $safeConfig !== null) {
                    $safeConfig = $cartService->sanitizeConfiguration($qItem->product, $safeConfig);
                }
                $order->items()->create([
                    'product_id' => $qItem->product_id,
                    'product_name' => $qItem->product_name,
                    'quantity' => $qItem->quantity,
                    'unit_price' => $qItem->unit_price,
                    'tax_rate' => $isSmallBusiness ? 0.0 : ($qItem->tax_rate !== null ? $qItem->tax_rate : ($qItem->product->tax_rate ?? shop_setting('default_tax_rate', 19.0))),
                    'total_price' => $qItem->total_price,
                    'configuration' => $safeConfig,
                ]);
            }

            // ---------------------------------------------------------
            // 5. STATUS DES ANGEBOTS AKTUALISIEREN
            // ---------------------------------------------------------
            $quote->update([
                'status' => 'converted',
                'converted_order_id' => $order->id
            ]);

            return $order;
        });

        if (!$order) {
            return;
        }

        // ---------------------------------------------------------
        // 4. STRIPE PAYMENT LINK GENERIEREN
        // ---------------------------------------------------------
        $paymentUrl = null;
        try {
            $stripeSecret = config('services.stripe.secret') ?: shop_setting('stripe_secret_key');
            if($stripeSecret && $type === 'stripe_link') {
                Stripe::setApiKey($stripeSecret);

                $session = StripeSession::create([
                    'automatic_payment_methods' => ['enabled' => true],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => [
                                'name' => 'Bestellung ' . $order->order_number,
                                'description' => 'Zahlung für Ihr angenommenes Angebot',
                            ],
                            'unit_amount' => $order->total_price, // Betrag in Cent
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('shop'),
                    'customer_email' => $order->email,
                    'metadata' => [
                        'order_id' => $order->id,
                        'quote_number' => $quote->quote_number
                    ],
                    'payment_intent_data' => [
                        'metadata' => [
                            'order_id' => $order->id,
                            'quote_number' => $quote->quote_number
                        ]
                    ],
                ]);

                $paymentUrl = $session->url;
                $stripeUpdate = ['payment_url' => $paymentUrl];
                if (!empty($session->payment_intent) && is_string($session->payment_intent)) {
                    $stripeUpdate['stripe_payment_intent_id'] = $session->payment_intent;
                }

                // Link in der Order speichern (Der Job zieht sich den später da raus)
                $order->update($stripeUpdate);
            }
        } catch (\Exception $e) {
            Log::error("Stripe Link Fehler bei Umwandlung: " . $e->getMessage());
        }

        // ---------------------------------------------------------
        // 6. JOB FÜR DOKUMENTE & MAILS ANSTOSSEN (NEU!)
        // ---------------------------------------------------------
        // Anstatt hier alles blockierend selbst zu bauen, übergeben wir
        // die frische Order an den Background-Job.
        \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);

        session()->flash('success', 'Angebot erfolgreich in Bestellung umgewandelt! Dokumente und E-Mails werden im Hintergrund versendet. ✨');

        $this->closeDetail();
    }

    public function markAsRejected($id) {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
        OrderQuoteRequest::where('id', $id)->where('status', '!=', 'converted')->update(['status' => 'rejected']);
        $this->closeDetail();
    }

    public function markAsOpen($id) {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
        $quote = OrderQuoteRequest::find($id);
        if ($quote && $quote->status === 'rejected') {
            $quote->update(['status' => 'open']);
            session()->flash('success', 'Anfrage wurde wieder geöffnet.');
        }
    }

    public function downloadLaserFile($itemId, $side, \App\Services\Export\FileDownloadService $exportService)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
        return $exportService->downloadLaserSvg($itemId, $side);
    }
}

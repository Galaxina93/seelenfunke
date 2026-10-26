<?php

namespace App\Livewire\Employee\Influencer;

use App\Models\Marketing\MarketingInfluencer;
use App\Models\Marketing\MarketingTrackingLink;
use App\Models\Product\Product;
use App\Services\Marketing\InfluencerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.backend_layout', ['guard' => 'employee'])]
class InfluencerDashboard extends Component
{
    public string $activeTab = 'overview'; // overview, payouts, settings

    // Deep-Link Generator State
    public string $newLinkTitle = '';
    public string $newLinkTargetProductSlug = '';

    // Tax & Payout Settings State
    public string $companyName = '';
    public string $street = '';
    public string $houseNumber = '';
    public string $postal = '';
    public string $city = '';
    public string $country = 'DE';
    public string $taxStatus = 'small_business';
    public string $taxNumber = '';
    public bool $selfEmployedConfirmed = false;
    public string $payoutMethod = 'bank_transfer';
    public string $accountHolder = '';
    public string $iban = '';
    public string $bic = '';
    public string $paypalEmail = '';

    public function boot(): void
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee || !$employee->isInfluencer()) {
            abort(403, 'Zugriff nur für registrierte Creator.');
        }
    }

    public function mount(): void
    {
        $influencer = $this->getInfluencer();
        $profile = $influencer->employee?->profile;

        $this->companyName = (string) ($profile?->company_name ?? '');
        $this->street = (string) ($profile?->street ?? '');
        $this->houseNumber = (string) ($profile?->house_number ?? '');
        $this->postal = (string) ($profile?->postal ?? '');
        $this->city = (string) ($profile?->city ?? '');
        $this->country = (string) ($profile?->country ?: 'DE');

        $this->taxStatus = (string) ($influencer->tax_status ?: 'small_business');
        $this->taxNumber = (string) ($influencer->tax_number ?? '');
        $this->selfEmployedConfirmed = (bool) $influencer->self_employed_confirmed;
        $this->payoutMethod = (string) ($influencer->payout_method ?: 'bank_transfer');
        $this->accountHolder = (string) ($influencer->account_holder ?: trim(($influencer->employee?->first_name ?? '') . ' ' . ($influencer->employee?->last_name ?? '')));
        $this->iban = (string) ($influencer->iban ?? '');
        $this->bic = (string) ($influencer->bic ?? '');
        $this->paypalEmail = (string) ($influencer->paypal_email ?? '');
    }

    protected function getInfluencer(): MarketingInfluencer
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee) {
            abort(403);
        }

        $influencer = MarketingInfluencer::with([
            'employee.profile',
            'defaultVoucher',
            'defaultTrackingLink',
            'trackingLinks',
            'commissions',
            'payouts.giftVoucher',
        ])->where('employee_id', $employee->id)->first();

        if (!$influencer) {
            abort(403, 'Kein verknüpftes Creator-Profil gefunden.');
        }

        return $influencer;
    }

    public function createProductTrackingLink(InfluencerService $service): void
    {
        $influencer = $this->getInfluencer();

        if (!$influencer->is_active || $influencer->is_archived) {
            session()->flash('creator_error', 'Dein Creator-Konto ist aktuell pausiert. Neue Tracking-Kurzlinks können derzeit nicht angelegt werden.');
            return;
        }

        $this->validate([
            'newLinkTitle' => 'required|string|max:80',
            'newLinkTargetProductSlug' => 'nullable|string|max:150',
        ], [
            'newLinkTitle.required' => 'Bitte gib eine kurze Bezeichnung ein (z.B. Instagram Story Kristall).',
        ]);

        $product = null;
        if ($this->newLinkTargetProductSlug !== '') {
            $product = Product::where('slug', $this->newLinkTargetProductSlug)->where('status', 'active')->first();
            if (!$product || !$product->isPromotableByInfluencer()) {
                session()->flash('creator_error', 'Dieses Produkt ist aktuell nicht für Creator-Kampagnen verfügbar (ausverkauft oder Wertgutschein).');
                return;
            }
        }

        $link = $service->createOrGetProductTrackingLink($influencer, $product, trim($this->newLinkTitle));

        $this->newLinkTitle = '';
        $this->newLinkTargetProductSlug = '';
        session()->flash('creator_success', "Neuer Kurzlink (/t/{$link->slug}) erfolgreich erstellt!");
    }

    public function quickCreateProductLink(string $productSlug, InfluencerService $service): void
    {
        $influencer = $this->getInfluencer();

        if (!$influencer->is_active || $influencer->is_archived) {
            session()->flash('creator_error', 'Dein Creator-Konto ist aktuell pausiert. Neue Tracking-Kurzlinks können derzeit nicht angelegt werden.');
            return;
        }

        $product = Product::where('slug', $productSlug)->where('status', 'active')->first();
        if (!$product || !$product->isPromotableByInfluencer()) {
            session()->flash('creator_error', 'Dieses Produkt ist aktuell nicht bewerbbar.');
            return;
        }

        $link = $service->createOrGetProductTrackingLink($influencer, $product);
        session()->flash('creator_success', "Produkt-Deep-Link (/t/{$link->slug}) für „{$product->name}“ ist bereit zum Kopieren!");
    }

    public function savePayoutAndTaxSettings(): void
    {
        $influencer = $this->getInfluencer();

        $rules = [
            'companyName' => 'nullable|string|max:191',
            'street' => 'required|string|max:191',
            'houseNumber' => 'required|string|max:30',
            'postal' => 'required|string|max:20',
            'city' => 'required|string|max:120',
            'country' => 'required|string|size:2',
            'taxStatus' => 'required|in:small_business,standard_tax,reverse_charge',
            'taxNumber' => 'required|string|max:100',
            'selfEmployedConfirmed' => 'accepted',
            'payoutMethod' => 'required|in:bank_transfer,paypal,shop_voucher',
        ];

        if ($this->payoutMethod === 'bank_transfer') {
            $rules['accountHolder'] = 'required|string|max:191';
            $rules['iban'] = ['required', 'string', 'min:15', 'max:34'];
            $rules['bic'] = 'nullable|string|max:30';
        } elseif ($this->payoutMethod === 'paypal') {
            $rules['paypalEmail'] = 'required|email|max:191';
        }

        $this->validate($rules, [
            'street.required' => 'Bitte gib deine Straße für die Gutschrifts-Belege an.',
            'houseNumber.required' => 'Hausnummer fehlt.',
            'postal.required' => 'PLZ fehlt.',
            'city.required' => 'Ort fehlt.',
            'taxNumber.required' => 'Bitte gib deine Steuernummer oder USt-IdNr. für das Gutschriftsverfahren an.',
            'selfEmployedConfirmed.accepted' => 'Bitte bestätige, dass du auf eigene Rechnung handelst.',
            'accountHolder.required' => 'Bitte gib den Kontoinhaber an.',
            'iban.required' => 'Bitte gib deine IBAN für die Überweisung an.',
            'paypalEmail.required' => 'Bitte gib deine PayPal-E-Mail-Adresse an.',
        ]);

        $profile = $influencer->employee->profile()->firstOrCreate([]);
        $profile->update([
            'company_name' => trim($this->companyName) ?: null,
            'is_business' => true,
            'vat_id' => trim($this->taxNumber),
            'street' => trim($this->street),
            'house_number' => trim($this->houseNumber),
            'postal' => trim($this->postal),
            'city' => trim($this->city),
            'country' => strtoupper(trim($this->country)),
        ]);

        $cleanIban = strtoupper(preg_replace('/\s+/', '', $this->iban));

        $influencer->update([
            'tax_status' => $this->taxStatus,
            'tax_number' => trim($this->taxNumber),
            'self_employed_confirmed' => true,
            'self_employed_confirmed_at' => $influencer->self_employed_confirmed_at ?: now(),
            'payout_method' => $this->payoutMethod,
            'account_holder' => trim($this->accountHolder) ?: null,
            'iban' => $cleanIban !== '' ? $cleanIban : null,
            'bic' => strtoupper(trim($this->bic)) ?: null,
            'paypal_email' => strtolower(trim($this->paypalEmail)) ?: null,
        ]);

        session()->flash('creator_success', 'Deine Steuer- & Auszahlungsdaten wurden sicher verschlüsselt gespeichert!');
    }

    public function requestPayoutNow(string $chosenMethod, InfluencerService $service): void
    {
        $influencer = $this->getInfluencer();

        if (!$influencer->isReadyForPayout()) {
            $this->activeTab = 'settings';
            session()->flash('creator_error', 'Bitte vervollständige zuerst deine Steuer- und Auszahlungsdaten, bevor du eine Gutschrift anforderst.');
            return;
        }

        try {
            $payout = $service->requestPayout($influencer, $chosenMethod, true);
            $this->activeTab = 'payouts';
            session()->flash(
                'creator_success',
                "Gutschrift {$payout->credit_note_number} über " . number_format($payout->gross_amount_cents / 100, 2, ',', '.') . " € wurde erstellt und zur Auszahlung eingereicht!"
            );
        } catch (\Throwable $e) {
            session()->flash('creator_error', $e->getMessage());
        }
    }

    public function render(InfluencerService $service)
    {
        $influencer = $this->getInfluencer();
        $mainShortUrl = $influencer->defaultTrackingLink?->short_url ?? url('/t/' . $influencer->slug);
        $qrCodeSvg = $service->generateQrCodeSvg($mainShortUrl, 150);

        $totalClicks = (int) $influencer->trackingLinks->sum('clicks_count');
        $totalUniqueClicks = (int) $influencer->trackingLinks->sum('unique_clicks_count');

        $validCommissions = $influencer->commissions->whereIn('status', ['pending', 'approved', 'paid']);
        $salesCount = $validCommissions->count();
        $conversionRate = $totalUniqueClicks > 0 ? round(($salesCount / $totalUniqueClicks) * 100, 1) : 0.0;

        $pendingCents = (int) $influencer->commissions->where('status', 'pending')->whereNull('payout_id')->sum('commission_amount_cents');
        $approvedCents = (int) $influencer->commissions->where('status', 'approved')->whereNull('payout_id')->sum('commission_amount_cents');
        $paidCents = (int) $influencer->commissions->where('status', 'paid')->sum('commission_amount_cents');

        $promotableProducts = $service->getPromotableProductsForInfluencer($influencer);

        $activeProducts = Schema::hasTable('products')
            ? Product::where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'sku', 'type', 'status', 'track_quantity', 'quantity', 'continue_selling_when_out_of_stock'])
                ->filter(fn (Product $product) => $product->isPromotableByInfluencer())
                ->values()
            : collect();

        return view('livewire.employee.influencer.influencer-dashboard', [
            'influencer' => $influencer,
            'mainShortUrl' => $mainShortUrl,
            'qrCodeSvg' => $qrCodeSvg,
            'totalClicks' => $totalClicks,
            'totalUniqueClicks' => $totalUniqueClicks,
            'salesCount' => $salesCount,
            'conversionRate' => $conversionRate,
            'pendingCents' => $pendingCents,
            'approvedCents' => $approvedCents,
            'paidCents' => $paidCents,
            'minPayoutCents' => InfluencerService::MIN_PAYOUT_CENTS,
            'activeProducts' => $activeProducts,
            'promotableProducts' => $promotableProducts,
            'defaultTaxRate' => (float) shop_setting('default_tax_rate', 19.0),
        ]);
    }
}

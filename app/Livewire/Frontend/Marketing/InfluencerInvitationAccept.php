<?php

namespace App\Livewire\Frontend\Marketing;

use App\Models\Marketing\MarketingInfluencer;
use App\Services\Marketing\InfluencerService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.frontend_layout', [
    'title' => 'Creator-Registrierung & Verifizierung | Mein Seelenfunke',
    'noindex' => true,
])]
class InfluencerInvitationAccept extends Component
{
    use WithRateLimiting;

    #[Locked]
    public string $token = '';

    #[Locked]
    public bool $isValidToken = false;

    #[Locked]
    public bool $isCodeVerified = false;

    #[Locked]
    public ?string $influencerId = null;

    #[Locked]
    public ?string $existingEmployeeId = null;

    #[Locked]
    public int $commissionPercent = 15;

    #[Locked]
    public int $followerDiscountPercent = 10;

    public int $step = 1;

    // Schritt 1: Verifizierungscode
    public string $verificationCode = '';

    // Schritt 2: Persönliche Daten, Login & Creator-Wunschcodes
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $primaryChannel = 'instagram';
    public string $handle = '';
    public string $slug = '';
    public string $customVoucherCode = '';
    public string $profileUrl = '';

    // Schritt 3: Anschrift, Steuerstatus & Auszahlungsweg (für § 14 UStG Gutschriften)
    public string $street = '';
    public string $houseNumber = '';
    public string $postal = '';
    public string $city = '';
    public string $country = 'DE';
    public string $taxStatus = 'small_business';
    public string $taxNumber = '';
    public string $payoutMethod = 'bank_transfer';
    public string $accountHolder = '';
    public string $iban = '';
    public string $bic = '';
    public string $paypalEmail = '';
    public bool $selfEmployedConfirmed = false;
    public bool $termsAccepted = false;

    public function mount(string $token): void
    {
        $this->token = trim($token);

        if (strlen($this->token) < 20 || !Schema::hasTable('marketing_influencers')) {
            $this->isValidToken = false;
            return;
        }

        $hash = hash('sha256', $this->token);
        $influencer = MarketingInfluencer::with(['employee.profile', 'defaultVoucher', 'defaultTrackingLink'])
            ->where('invitation_token_hash', $hash)
            ->first();

        if (
            !$influencer
            || !$influencer->is_active
            || $influencer->is_archived
            || $influencer->invitation_accepted_at !== null
            || ($influencer->invitation_expires_at && $influencer->invitation_expires_at->isPast())
        ) {
            $this->isValidToken = false;
            return;
        }

        $this->isValidToken = true;
        $this->influencerId = $influencer->id;
        $this->existingEmployeeId = $influencer->employee_id;
        $this->commissionPercent = (int) $influencer->commission_value;
        $this->followerDiscountPercent = (int) $influencer->follower_discount_percent;
        $this->primaryChannel = $influencer->primary_channel ?: 'instagram';

        if ($influencer->employee) {
            $this->firstName = (string) ($influencer->employee->first_name ?? '');
            $this->lastName = (string) ($influencer->employee->last_name ?? '');
            $this->email = (string) ($influencer->employee->email ?? '');
        }

        if ($influencer->handle) {
            $this->handle = (string) $influencer->handle;
        }
        if ($influencer->slug) {
            $this->slug = (string) $influencer->slug;
        }
        if ($influencer->defaultVoucher) {
            $this->customVoucherCode = (string) $influencer->defaultVoucher->code;
        }
    }

    public function updatedHandle(string $value): void
    {
        $clean = ltrim(trim($value), '@');
        if ($this->slug === '') {
            $this->slug = Str::slug($clean);
        }
        if ($this->customVoucherCode === '' && $clean !== '') {
            $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $clean));
            if ($prefix !== '') {
                $this->customVoucherCode = substr($prefix, 0, 12) . $this->followerDiscountPercent;
            }
        }
    }

    public function verifyCode(InfluencerService $service): void
    {
        try {
            $this->rateLimit(8);
        } catch (TooManyRequestsException $e) {
            throw ValidationException::withMessages([
                'verificationCode' => "Zu viele Versuche. Bitte warte {$e->secondsUntilAvailable} Sekunden.",
            ]);
        }

        if (!$this->isValidToken) {
            throw ValidationException::withMessages([
                'verificationCode' => 'Dieser Einladungslink ist nicht mehr gültig.',
            ]);
        }

        $this->validate([
            'verificationCode' => 'required|string|min:4|max:20',
        ], [
            'verificationCode.required' => 'Bitte gib deinen 6-stelligen Verifizierungscode ein.',
        ]);

        $influencer = $service->verifyInvitationCode($this->token, $this->verificationCode);
        if (!$influencer) {
            throw ValidationException::withMessages([
                'verificationCode' => 'Der eingegebene Sicherheitscode ist ungültig.',
            ]);
        }

        $this->isCodeVerified = true;
        $this->step = 2;
    }

    public function nextToStepThree(): void
    {
        if (!$this->isCodeVerified) {
            $this->step = 1;
            return;
        }

        $this->validateStepTwo();

        if ($this->accountHolder === '') {
            $this->accountHolder = trim($this->firstName . ' ' . $this->lastName);
        }

        $this->step = 3;
    }

    public function previousStep(): void
    {
        if ($this->step > 2) {
            $this->step--;
        }
    }

    protected function validateStepTwo(): void
    {
        $cleanSlug = Str::slug($this->slug ?: ltrim($this->handle, '@'));
        $this->slug = $cleanSlug;

        $this->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('employees', 'email')->ignore($this->existingEmployeeId),
            ],
            'password' => 'required|string|min:8|confirmed',
            'primaryChannel' => 'required|in:instagram,tiktok,youtube,pinterest,blog',
            'handle' => 'required|string|max:100',
            'slug' => [
                'required',
                'string',
                'max:80',
                Rule::unique('marketing_influencers', 'slug')->ignore($this->influencerId),
            ],
            'customVoucherCode' => 'nullable|string|max:40',
            'profileUrl' => 'nullable|url|max:255',
        ], [
            'firstName.required' => 'Bitte gib deinen Vornamen ein.',
            'lastName.required' => 'Bitte gib deinen Nachnamen ein.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse für den Login ein.',
            'email.unique' => 'Mit dieser E-Mail-Adresse existiert bereits ein Konto.',
            'password.required' => 'Bitte wähle ein sicheres Passwort (mind. 8 Zeichen).',
            'password.min' => 'Dein Passwort muss mindestens 8 Zeichen lang sein.',
            'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
            'handle.required' => 'Bitte gib deinen Social-Media-Handle an (z. B. @lara.living).',
            'slug.required' => 'Bitte wähle deinen Wunsch-Kurzlink.',
            'slug.unique' => 'Dieser Kurzlink ist bereits vergeben. Bitte wähle eine andere Variante.',
        ]);
    }

    public function completeRegistration(InfluencerService $service)
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $e) {
            throw ValidationException::withMessages([
                'taxNumber' => "Zu viele Versuche. Bitte warte {$e->secondsUntilAvailable} Sekunden.",
            ]);
        }

        if (!$this->isValidToken || !$this->isCodeVerified) {
            $this->step = 1;
            throw ValidationException::withMessages([
                'verificationCode' => 'Bitte bestätige zuerst deinen Sicherheitscode.',
            ]);
        }

        $this->validateStepTwo();

        $rules = [
            'street' => 'required|string|max:150',
            'houseNumber' => 'required|string|max:30',
            'postal' => 'required|string|max:20',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:5',
            'taxStatus' => 'required|in:small_business,standard_tax',
            'taxNumber' => 'required|string|min:6|max:80',
            'payoutMethod' => 'required|in:bank_transfer,paypal,shop_voucher',
            'selfEmployedConfirmed' => 'accepted',
            'termsAccepted' => 'accepted',
        ];

        if ($this->payoutMethod === 'bank_transfer') {
            $rules['accountHolder'] = 'required|string|max:150';
            $rules['iban'] = 'required|string|min:14|max:34';
            $rules['bic'] = 'nullable|string|max:20';
        } elseif ($this->payoutMethod === 'paypal') {
            $rules['paypalEmail'] = 'required|email|max:150';
        }

        $this->validate($rules, [
            'street.required' => 'Bitte gib deine Straße für die Gutschrifts-Belege an.',
            'houseNumber.required' => 'Bitte Hausnummer angeben.',
            'postal.required' => 'Bitte PLZ angeben.',
            'city.required' => 'Bitte Ort angeben.',
            'taxNumber.required' => 'Bitte gib deine Steuernummer oder USt-IdNr. an (§ 14 UStG Pflichtangabe).',
            'accountHolder.required' => 'Bitte Kontoinhaber für SEPA-Auszahlungen angeben.',
            'iban.required' => 'Bitte gib deine IBAN für Provisions-Auszahlungen an.',
            'paypalEmail.required' => 'Bitte gib deine PayPal-E-Mail-Adresse an.',
            'selfEmployedConfirmed.accepted' => 'Bitte bestätige deine selbstständige Tätigkeit auf eigene Rechnung.',
            'termsAccepted.accepted' => 'Bitte akzeptiere die Partnerprogramm-Bedingungen.',
        ]);

        $influencer = $service->completeSelfRegistration($this->token, $this->verificationCode, [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'password' => $this->password,
            'primary_channel' => $this->primaryChannel,
            'handle' => $this->handle,
            'slug' => $this->slug,
            'custom_voucher_code' => $this->customVoucherCode,
            'profile_url' => $this->profileUrl,
            'street' => $this->street,
            'house_number' => $this->houseNumber,
            'postal' => $this->postal,
            'city' => $this->city,
            'country' => $this->country,
            'tax_status' => $this->taxStatus,
            'tax_number' => $this->taxNumber,
            'payout_method' => $this->payoutMethod,
            'account_holder' => $this->accountHolder,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'paypal_email' => $this->paypalEmail,
            'self_employed_confirmed' => $this->selfEmployedConfirmed,
        ]);

        if (!$influencer || !$influencer->employee) {
            $this->isValidToken = false;
            throw ValidationException::withMessages([
                'verificationCode' => 'Die Einladung ist abgelaufen oder wurde bereits eingelöst.',
            ]);
        }

        foreach (['customer', 'admin'] as $otherGuard) {
            if (Auth::guard($otherGuard)->check()) {
                Auth::guard($otherGuard)->logout();
            }
        }

        Auth::guard('employee')->login($influencer->employee);
        session()->regenerate();

        return redirect()->route('employee.influencer.dashboard');
    }

    public function render()
    {
        return view('livewire.frontend.marketing.influencer-invitation-accept');
    }
}

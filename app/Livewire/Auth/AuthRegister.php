<?php

namespace App\Livewire\Auth;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use App\Models\System\SystemBlockedIp;
use App\Models\System\SystemUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\Registered;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AuthRegister extends Component
{
    #[Locked]
    public ?string $sessionToken = null;

    public string $company_hp = ''; // Honeypot für Spam-Bots

    public function mount(?string $sessionToken = null)
    {
        if (SystemBlockedIp::isBlocked(request()->ip())) {
            abort(403, 'Zugriff verweigert.');
        }

        $this->sessionToken = $sessionToken ?? request()->cookie('sf_chat_uuid');
        $this->ai_data_consent = (bool) session('ai_chat_privacy_accepted', false);
    }

    public $firstname = '';
    public $lastname = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    public $street = '';
    public $house_number = '';
    public $postal = '';
    public $city = '';
    public $country = 'DE';

    public $is_business = 0;
    public $birthday = '';

    public $terms = false;
    public $ai_data_consent = false;

    #[Locked]
    public $passwordRules = [
        'min' => false,
        'number' => false,
        'upper' => false,
        'match' => false,
    ];

    protected function rules()
    {
        return [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:customers',
                'indisposable',
                function ($attribute, $value, $fail) {
                    if (SystemUser::isEmailTakenAcrossGuards((string) $value)) {
                        $fail('Diese E-Mail-Adresse wird bereits verwendet.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'regex:/[0-9]/', 'regex:/[A-Z]/', 'same:password_confirmation'],
            'street' => 'required|string|max:255',
            'house_number' => 'required|string|max:20',
            'postal' => 'required|string|max:10',
            'city' => 'required|string|max:255',
            'country' => 'required|string|size:2',
            'is_business' => 'required|boolean',
            'birthday' => 'nullable|date',
            'terms' => 'accepted',
            'ai_data_consent' => 'boolean',
        ];
    }

    protected $messages = [
        'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
        'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        'email.indisposable' => 'Temporäre E-Mail-Adressen (Wegwerf-E-Mails) sind nicht erlaubt.',
        'terms.accepted' => 'Bitte akzeptieren Sie die AGB und Datenschutzbestimmungen.',
        'required' => 'Dieses Feld ist erforderlich.',
    ];

    public function updatedPassword()
    {
        $this->validatePasswordRules();
    }

    public function updatedPasswordConfirmation()
    {
        $this->validatePasswordRules();
    }

    private function validatePasswordRules()
    {
        $this->passwordRules['min'] = strlen($this->password) >= 8;
        $this->passwordRules['number'] = (bool) preg_match('/[0-9]/', $this->password);
        $this->passwordRules['upper'] = (bool) preg_match('/[A-Z]/', $this->password);
        $this->passwordRules['match'] = !empty($this->password) && ($this->password === $this->password_confirmation);
    }

    public function getCanRegisterProperty()
    {
        $pwOk = $this->passwordRules['min'] && $this->passwordRules['number'] && $this->passwordRules['upper'] && $this->passwordRules['match'];
        $fieldsOk = !empty($this->firstname) && !empty($this->lastname) && !empty($this->email)
            && !empty($this->street) && !empty($this->house_number) && !empty($this->postal)
            && !empty($this->city) && !empty($this->country)
            && $this->is_business !== null;

        return $pwOk && $fieldsOk && $this->terms;
    }

    public function register()
    {
        $ip = request()->ip();

        if (SystemBlockedIp::isBlocked($ip)) {
            abort(403, 'Zugriff verweigert.');
        }

        // 1. Honeypot Check (Bots füllen unsichtbare Felder aus)
        if (!empty($this->company_hp)) {
            \Illuminate\Support\Facades\Log::warning("Spam-Bot Registrierung blockiert (Honeypot ausgelöst) von IP: {$ip}");
            \App\Services\System\SystemSecurityService::logEvent(
                'security:honeypot_register',
                'Bot-Angriff bei Registrierung abgewehrt',
                "Honeypot-Feld bei Registrierung ausgelöst (IP: {$ip}, E-Mail: {$this->email}).",
                'warning',
                ['ip_address' => $ip, 'target_email' => $this->email]
            );
            // Stillschweigende Umleitung ohne Speicherung
            session()->flash('status', 'Willkommen bei Mein Seelenfunke! Wir haben dir eine E-Mail gesendet. Bitte bestätige deine Adresse, um dich einzuloggen.');
            return redirect()->route('login');
        }

        // 2. Rate-Limiting gegen Registrierungs-Flooding
        $throttleKey = 'register_attempt_' . $ip;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);
            \App\Services\System\SystemSecurityService::logEvent(
                'security:rate_limit_register',
                'Registrierungs-Flooding blockiert',
                "Rate-Limit für Registrierungen von IP {$ip} überschritten.",
                'warning',
                ['ip_address' => $ip, 'target_email' => $this->email]
            );
            $this->addError('email', "Zu viele Registrierungsversuche von diesem Anschluss. Bitte warte {$minutes} Minute(n).");
            return;
        }

        $this->email = mb_strtolower(trim((string) $this->email));
        $this->validatePasswordRules();
        $this->validate();

        if (!$this->canRegister) {
            return;
        }

        RateLimiter::hit($throttleKey, 900); // 15 Minuten Throttling

        $customer = Customer::create([
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        $hasAiConsent = (bool) $this->ai_data_consent;

        $customer->profile()->update([
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal' => $this->postal,
            'city' => $this->city,
            'country' => $this->country,
            'is_business' => $this->is_business,
            'birthday' => $this->birthday ?: null,
            'ai_data_consent' => $hasAiConsent,
            'ai_data_consent_at' => $hasAiConsent ? now() : null,
        ]);

        if ($hasAiConsent) {
            session()->put('ai_chat_privacy_accepted', true);
            $customer->updateAiDataConsent(true, 'registration');
        }

        // Chat Session Guest-Ascension Migration
        if (is_string($this->sessionToken) && strlen(trim($this->sessionToken)) >= 16) {
            $chatClaimQuery = \App\Models\Support\SupportCustomerChat::where('session_token', trim($this->sessionToken))
                ->whereNull('customer_id');
            if (session()->has('current_chat_id')) {
                $chatClaimQuery->where('id', session('current_chat_id'));
            }
            $chatClaimQuery->update(['customer_id' => $customer->id]);
        }

        // Send verification manually to circumvent Laravel 11 auto-discovery bug causing duplicate event dispatches.
        $customer->sendEmailVerificationNotification();

        session()->flash('status', 'Willkommen bei Mein Seelenfunke! Wir haben dir eine E-Mail gesendet. Bitte bestätige deine Adresse, um dich einzuloggen.');

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.auth-register', [
            'activeCountries' => shop_setting('active_countries', ['DE' => 'Deutschland'])
        ])->layout('components.layouts.frontend_layout');
    }
}

<?php

namespace App\Livewire\Frontend\Employee;

use App\Models\Employee\EmployeeContract;
use App\Models\System\SystemUser;
use App\Services\Employee\EmployeeHrService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.frontend_layout', [
    'title' => 'Mitarbeiter-Onboarding & Digitaler Arbeitsvertrag | Mein Seelenfunke',
    'noindex' => true,
])]
class EmployeeInvitationAccept extends Component
{
    use WithRateLimiting;

    #[Locked]
    public string $token = '';

    #[Locked]
    public bool $isValidToken = false;

    #[Locked]
    public bool $isCodeVerified = false;

    #[Locked]
    public ?string $contractId = null;

    #[Locked]
    public ?string $existingEmployeeId = null;

    #[Locked]
    public string $jobTitle = '';

    #[Locked]
    public string $employmentTypeLabel = '';

    #[Locked]
    public int $hourlyRateCents = 1400;

    #[Locked]
    public float $weeklyHours = 9.0;

    #[Locked]
    public int $vacationDays = 20;

    #[Locked]
    public bool $isFixedTerm = false;

    public int $step = 1;

    // Schritt 1: 6-stelliger Sicherheitscode
    public string $verificationCode = '';

    // Schritt 2: Persönliche Daten, Login & Anschrift
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $birthDate = '';
    public string $birthPlace = '';
    public string $nationality = 'Deutsch';
    public string $phoneNumber = '';
    public string $street = '';
    public string $houseNumber = '';
    public string $postal = '';
    public string $city = '';
    public string $country = 'DE';

    // Schritt 3: ELStAM / Sozialversicherung, Bankverbindung & Digitale Unterschrift
    public string $taxId = '';
    public string $taxClass = 'I';
    public bool $churchTax = false;
    public string $socialSecurityNumber = '';
    public string $healthInsuranceName = '';
    public string $healthInsuranceType = 'statutory';
    public bool $rvExemptionRequested = false;
    public bool $hasOtherEmployment = false;
    public string $accountHolder = '';
    public string $iban = '';
    public string $bic = '';
    public string $bankName = '';
    public string $signatureName = '';
    public bool $contractAccepted = false;
    public bool $truthConfirmed = false;

    public function mount(string $token): void
    {
        $this->token = trim($token);

        if (strlen($this->token) < 20 || !Schema::hasTable('employee_contracts')) {
            $this->isValidToken = false;
            return;
        }

        $hash = hash('sha256', $this->token);
        $contract = EmployeeContract::with('employee.profile')
            ->where('invitation_token_hash', $hash)
            ->first();

        if (
            !$contract
            || !$contract->is_active
            || $contract->is_archived
            || $contract->invitation_accepted_at !== null
            || ($contract->invitation_expires_at && $contract->invitation_expires_at->isPast())
        ) {
            $this->isValidToken = false;
            return;
        }

        $this->isValidToken = true;
        $this->contractId = $contract->id;
        $this->existingEmployeeId = $contract->employee_id;
        $this->jobTitle = (string) $contract->job_title;
        $this->employmentTypeLabel = (string) $contract->employment_type_label;
        $this->hourlyRateCents = (int) $contract->hourly_rate_cents;
        $this->weeklyHours = (float) $contract->weekly_hours;
        $this->vacationDays = (int) $contract->vacation_days_per_year;
        $this->isFixedTerm = (bool) $contract->is_fixed_term;

        if ($contract->employee) {
            $this->firstName = (string) ($contract->employee->first_name ?? '');
            $this->lastName = (string) ($contract->employee->last_name ?? '');
            $this->email = (string) ($contract->employee->email ?? '');
        }
    }

    public function verifyCode(EmployeeHrService $service): void
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
            'verificationCode.required' => 'Bitte gib deinen 6-stelligen Sicherheitscode ein.',
        ]);

        $contract = $service->verifyInvitationCode($this->token, $this->verificationCode);
        if (!$contract) {
            throw ValidationException::withMessages([
                'verificationCode' => 'Der eingegebene Sicherheitscode ist ungültig.',
            ]);
        }

        $this->isCodeVerified = true;
        $this->step = 2;
    }

    public function nextToStepThree(): void
    {
        try {
            $this->rateLimit(6);
        } catch (TooManyRequestsException $e) {
            throw ValidationException::withMessages([
                'email' => "Zu viele Prüfversuche. Bitte warte {$e->secondsUntilAvailable} Sekunden.",
            ]);
        }

        if (!$this->isCodeVerified) {
            $this->step = 1;
            return;
        }

        $this->validateStepTwo();

        if ($this->accountHolder === '') {
            $this->accountHolder = trim($this->firstName . ' ' . $this->lastName);
        }
        if ($this->signatureName === '') {
            $this->signatureName = trim($this->firstName . ' ' . $this->lastName);
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
        $this->email = mb_strtolower(trim($this->email));
        $neutralEmailError = 'Diese E-Mail-Adresse kann für die Mitarbeiter-Registrierung nicht verwendet werden. Bitte nutze eine andere E-Mail-Adresse.';

        $this->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('employees', 'email')->ignore($this->existingEmployeeId),
                function ($attribute, $value, $fail) use ($neutralEmailError) {
                    if (SystemUser::isEmailTakenAcrossGuards((string) $value, 'employee', $this->existingEmployeeId)) {
                        $fail($neutralEmailError);
                    }
                },
            ],
            'password' => 'required|string|min:8|confirmed',
            'birthDate' => 'required|date',
            'street' => 'required|string|max:150',
            'houseNumber' => 'required|string|max:30',
            'postal' => 'required|string|max:20',
            'city' => 'required|string|max:100',
        ], [
            'firstName.required' => 'Bitte gib deinen Vornamen ein.',
            'lastName.required' => 'Bitte gib deinen Nachnamen ein.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse für den Login ein.',
            'email.unique' => $neutralEmailError,
            'password.required' => 'Bitte wähle ein sicheres Passwort (mind. 8 Zeichen).',
            'password.min' => 'Dein Passwort muss mindestens 8 Zeichen lang sein.',
            'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
            'birthDate.required' => 'Bitte gib dein Geburtsdatum für die Sozialversicherung an.',
            'street.required' => 'Bitte Straße angeben.',
            'houseNumber.required' => 'Bitte Hausnummer angeben.',
            'postal.required' => 'Bitte PLZ angeben.',
            'city.required' => 'Bitte Wohnort angeben.',
        ]);
    }

    public function completeOnboarding(EmployeeHrService $service)
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $e) {
            throw ValidationException::withMessages([
                'taxId' => "Zu viele Versuche. Bitte warte {$e->secondsUntilAvailable} Sekunden.",
            ]);
        }

        if (!$this->isValidToken || !$this->isCodeVerified) {
            $this->step = 1;
            throw ValidationException::withMessages([
                'verificationCode' => 'Bitte bestätige zuerst deinen Sicherheitscode.',
            ]);
        }

        $this->validateStepTwo();

        $this->validate([
            'taxId' => 'required|string|min:8|max:30',
            'taxClass' => 'required|in:I,II,III,IV,V,VI,Pauschal',
            'healthInsuranceName' => 'required|string|max:150',
            'accountHolder' => 'required|string|max:150',
            'iban' => 'required|string|min:14|max:34',
            'signatureName' => 'required|string|min:3|max:150',
            'contractAccepted' => 'accepted',
            'truthConfirmed' => 'accepted',
        ], [
            'taxId.required' => 'Bitte gib deine 11-stellige Steuer-Identifikationsnummer an.',
            'healthInsuranceName.required' => 'Bitte gib deine Krankenkasse an.',
            'accountHolder.required' => 'Bitte gib den Kontoinhaber für die Gehaltsüberweisung an.',
            'iban.required' => 'Bitte gib deine IBAN für die Gehaltsüberweisung an.',
            'signatureName.required' => 'Bitte unterschreibe digital mit deinem Vor- und Nachnamen.',
            'contractAccepted.accepted' => 'Bitte bestätige und akzeptiere den Arbeitsvertrag.',
            'truthConfirmed.accepted' => 'Bitte bestätige die Richtigkeit deiner Angaben für das Lohnbüro.',
        ]);

        $contract = $service->completeSelfRegistration($this->token, $this->verificationCode, [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'password' => $this->password,
            'birth_date' => $this->birthDate,
            'birth_place' => $this->birthPlace,
            'nationality' => $this->nationality,
            'phone_number' => $this->phoneNumber,
            'street' => $this->street,
            'house_number' => $this->houseNumber,
            'postal' => $this->postal,
            'city' => $this->city,
            'country' => $this->country,
            'tax_id' => $this->taxId,
            'tax_class' => $this->taxClass,
            'church_tax' => $this->churchTax,
            'social_security_number' => $this->socialSecurityNumber,
            'health_insurance_name' => $this->healthInsuranceName,
            'health_insurance_type' => $this->healthInsuranceType,
            'rv_exemption_requested' => $this->rvExemptionRequested,
            'has_other_employment' => $this->hasOtherEmployment,
            'account_holder' => $this->accountHolder,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'bank_name' => $this->bankName,
            'signature_name' => $this->signatureName,
        ]);

        if (!$contract || !$contract->employee) {
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

        Auth::guard('employee')->login($contract->employee);
        session()->regenerate();

        return redirect()->route('employee.dashboard');
    }

    public function render()
    {
        return view('livewire.frontend.employee.employee-invitation-accept');
    }
}

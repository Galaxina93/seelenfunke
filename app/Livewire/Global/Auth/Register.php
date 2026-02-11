<?php

namespace App\Livewire\Global\Auth;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Register extends Component
{
    // --- KUNDEN DATEN (Tabelle: customers) ---
    public $firstname = '';
    public $lastname = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    // --- PROFIL DATEN (Tabelle: customer_profiles) ---
    public $street = '';
    public $house_number = '';
    public $postal = '';
    public $city = '';
    public $country = 'DE'; // Standardmäßig Deutschland

    // --- RECHTLICHES ---
    public $terms = false;

    // --- UI STATUS (Passwort Live-Check) ---
    public $passwordRules = [
        'min' => false,    // min 8 Zeichen
        'number' => false, // enthält Zahl
        'upper' => false,  // enthält Großbuchstabe
        'match' => false,  // Passwörter stimmen überein
    ];

    // --- VALIDIERUNGS REGELN ---
    protected function rules()
    {
        return [
            // Customer Tabelle
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:customers',
            'password' => ['required', 'string', 'min:8'],

            // Profile Tabelle
            'street' => 'required|string|max:255',
            'house_number' => 'required|string|max:20',
            'postal' => 'required|string|max:10',
            'city' => 'required|string|max:255',
            'country' => 'required|string|size:2', // Validierung für Länder-Code (z.B. DE)

            // Checkbox
            'terms' => 'accepted'
        ];
    }

    protected $messages = [
        'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
        'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
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
        $this->passwordRules['number'] = (bool)preg_match('/[0-9]/', $this->password);
        $this->passwordRules['upper'] = (bool)preg_match('/[A-Z]/', $this->password);
        $this->passwordRules['match'] = !empty($this->password) && ($this->password === $this->password_confirmation);
    }

    // --- COMPUTED PROPERTY: BUTTON STATUS ---
    public function getCanRegisterProperty()
    {
        $pwOk = $this->passwordRules['min']
            && $this->passwordRules['number']
            && $this->passwordRules['upper']
            && $this->passwordRules['match'];

        $fieldsOk = !empty($this->firstname)
            && !empty($this->lastname)
            && !empty($this->email)
            && !empty($this->street)
            && !empty($this->house_number)
            && !empty($this->postal)
            && !empty($this->city)
            && !empty($this->country);

        return $pwOk && $fieldsOk && $this->terms;
    }

    public function register()
    {
        $this->validate();

        if (!$this->canRegister) {
            return;
        }

        // 1. Kunde erstellen
        $customer = Customer::create([
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        // 2. Profil erstellen mit Land
        CustomerProfile::create([
            'customer_id' => $customer->id,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal' => $this->postal,
            'city' => $this->city,
            'country' => $this->country, // NEU: Speicherung des gewählten Landes
        ]);

        // 3. Login & Redirect
        auth()->guard('customer')->login($customer);

        session()->flash('status', 'Willkommen bei Mein Seelenfunke! Ihr Konto wurde erfolgreich erstellt.');
        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.register', [
            // Wir laden nur die Länder, die du in der Shop-Konfiguration aktiviert hast
            'activeCountries' => shop_setting('active_countries', ['DE' => 'Deutschland'])
        ]);
    }
}

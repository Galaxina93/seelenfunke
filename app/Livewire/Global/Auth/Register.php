<?php

namespace App\Livewire\Global\Auth;

use App\Models\Customer;
use App\Models\CustomerProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
            'password' => ['required', 'string', 'min:8'], // Detail-Check macht die UI manuell

            // Profile Tabelle
            'street' => 'required|string|max:255',
            'house_number' => 'required|string|max:20',
            'postal' => 'required|string|max:10',
            'city' => 'required|string|max:255',

            // Checkbox
            'terms' => 'accepted'
        ];
    }

    protected $messages = [
        'email.unique' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        'terms.accepted' => 'Bitte akzeptieren Sie die AGB und Datenschutzbestimmungen.',
        'required' => 'Dieses Feld ist erforderlich.',
    ];

    // --- LIVE UPDATES FÜR PASSWORT CHECK ---

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
        $this->passwordRules['number'] = preg_match('/[0-9]/', $this->password);
        $this->passwordRules['upper'] = preg_match('/[A-Z]/', $this->password);
        $this->passwordRules['match'] = !empty($this->password) && ($this->password === $this->password_confirmation);
    }

    // --- COMPUTED PROPERTY: BUTTON STATUS ---
    // Prüft, ob ALLES korrekt ausgefüllt ist. Nur dann wird der Button aktiv.
    public function getCanRegisterProperty()
    {
        // 1. Passwort Regeln
        $pwOk = $this->passwordRules['min']
            && $this->passwordRules['number']
            && $this->passwordRules['upper']
            && $this->passwordRules['match'];

        // 2. Pflichtfelder nicht leer
        $fieldsOk = !empty($this->firstname)
            && !empty($this->lastname)
            && !empty($this->email)
            && !empty($this->street)
            && !empty($this->house_number)
            && !empty($this->postal)
            && !empty($this->city);

        // 3. AGB akzeptiert
        return $pwOk && $fieldsOk && $this->terms;
    }

    // --- REGISTRIERUNG DURCHFÜHREN ---

    public function register()
    {
        $this->validate();

        // Zusätzlicher Sicherheitscheck serverseitig
        if (!$this->canRegister) {
            return;
        }

        // 1. Kunde erstellen (Basisdaten)
        $customer = Customer::create([
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        // 2. Profil erstellen (Adressdaten) & Verknüpfen
        CustomerProfile::create([
            'customer_id' => $customer->id, // WICHTIG: Hier wird die Relation gesetzt
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal' => $this->postal,
            'city' => $this->city,
            // Weitere Felder aus Migration sind nullable und bleiben erst mal leer
        ]);

        // 3. Automatisch einloggen
        auth()->guard('customer')->login($customer);

        // 4. Weiterleitung
        session()->flash('status', 'Registrierung erfolgreich! Bitte melden Sie sich jetzt an.'); // 'status' wird oft von Login-Blades abgefangen
        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}

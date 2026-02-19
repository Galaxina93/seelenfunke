<?php

namespace App\Livewire\Global\Auth;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Livewire\Component;

class Register extends Component
{
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
    public $terms = false;

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
            'email' => 'required|string|email|max:255|unique:customers',
            'password' => ['required', 'string', 'min:8'],
            'street' => 'required|string|max:255',
            'house_number' => 'required|string|max:20',
            'postal' => 'required|string|max:10',
            'city' => 'required|string|max:255',
            'country' => 'required|string|size:2',
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

        $customer = Customer::create([
            'first_name' => $this->firstname,
            'last_name' => $this->lastname,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        $customer->profile()->update([
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal' => $this->postal,
            'city' => $this->city,
            'country' => $this->country,
        ]);

        event(new Registered($customer));

        session()->flash('status', 'Willkommen bei Mein Seelenfunke! Wir haben dir eine E-Mail gesendet. Bitte bestätige deine Adresse, um dich einzuloggen.');

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'activeCountries' => shop_setting('active_countries', ['DE' => 'Deutschland'])
        ]);
    }
}

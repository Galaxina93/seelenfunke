<?php

namespace App\Livewire\Global\Profile;

use App\Models\NewsletterSubscriber;
use App\Traits\handleProfilesTrait;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Profile extends Component
{
    use handleProfilesTrait;

    public $photo;
    public object $user;

    public string $guard;

    public string $firstName;
    public string $lastName;
    public string $email;
    public string $phoneNumber;

    public string $about;
    public string $url;

    public string $street;
    public string $houseNumber;
    public string $postal;
    public string $city;
    public string $country; // NEU: Feld für das Land

    public string $newPassword;
    public string $currentPassword;
    public string $repeatNewPassword;

    public function mount(): void
    {
        $this->user = Auth::user();

        // Mount User Data (füllt firstName, lastName, email, street, etc.)
        $this->mountUserProfileData();

        /** * NEU: Land aus dem Profil laden.
         * Falls kein Profil oder kein Land existiert, nehmen wir DE als Default.
         */
        $this->country = $this->user->profile->country ?? 'DE';

        // Setzt den User Guard
        $this->guard = (new \App\Models\User)->getGuard();
    }

    public function render()
    {
        return view('livewire.profile.profile', [
            // Wir übergeben die aktiven Lieferländer an die View für das Dropdown
            'activeCountries' => shop_setting('active_countries', ['DE' => 'Deutschland'])
        ]);
    }

    // Profilfoto
    public function updatedPhoto(): void
    {
        $this->updateUserProfilePhoto();
    }

    public function deletePhoto(): void
    {
        $this->deleteUserProfilePhoto();
    }

    // Profil Daten
    public function saveProfile(): void
    {
        $data = [
            'first_name'   => $this->firstName,
            'last_name'    => $this->lastName,
            'email'        => $this->email,
            'phone_number' => $this->phoneNumber,
            'about'        => $this->about,
            'url'          => $this->url,
            'street'       => $this->street,
            'house_number' => $this->houseNumber,
            'postal'       => $this->postal,
            'city'         => $this->city,
            'country'      => $this->country, // NEU: Land wird mitgespeichert
        ];

        /**
         * Hinweis: Stelle sicher, dass dein handleProfilesTrait
         * das Feld 'country' auch in der Validierung und im Update-Prozess erlaubt.
         */
        $this->saveUserProfileData($data);
    }

    // Passwort aktualisieren
    public function updatePassword(): void
    {
        $this->updateUserPassword($this->user);
    }

    // Session
    public function deleteOtherSessions(): void
    {
        $this->removeOtherSessions();
    }

    // Account löschen
    public function deleteAccount(): void
    {
        $this->deleteUserAccount();
    }
}

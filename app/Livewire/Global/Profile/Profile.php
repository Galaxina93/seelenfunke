<?php

namespace App\Livewire\Global\Profile;

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

    public string $newPassword;
    public string $currentPassword;
    public string $repeatNewPassword;

    public function mount(): void
    {
        // handleProfilesTrait
        $this->user = Auth::user();

        // Mount User Data
        $this->mountUserProfileData();

        // Setzt den User Guard
        $this->guard = (new \App\Models\User)->getGuard();
    }
    public function render()
    {
        return view('livewire.profile.profile');
    }

    // Profilfoto
    public function updatedPhoto(): void
    {
        // handleProfilesTrait
        $this->updateUserProfilePhoto();
    }
    public function deletePhoto (): void
    {
        // handleProfilesTrait
        $this->deleteUserProfilePhoto();
    }

    // Profil Daten
    public function saveProfile(): void
    {
        $data = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone_number' => $this->phoneNumber,
            'about' => $this->about,
            'url' => $this->url,
            'street' => $this->street,
            'house_number' => $this->houseNumber,
            'postal' => $this->postal,
            'city' => $this->city,
        ];

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
        // handleProfilesTrait
        $this->removeOtherSessions();
    }


    // Account löschen
    public function  deleteAccount(): void
    {
        $this->deleteUserAccount();
    }
}

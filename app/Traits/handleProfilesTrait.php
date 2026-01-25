<?php

namespace App\Traits;

use App\Models\Session;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

trait handleProfilesTrait
{
    use WithFileUploads;

    // Profilfoto aktualisieren - Section 1
    public function updateUserProfilePhoto(): void
    {
        $this->validate([
            'photo' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ], [
            'photo.required' => 'Ein Foto ist erforderlich.',
            'photo.image' => 'Die Datei muss ein Bild sein.',
            'photo.mimes' => 'Nur PNG, JPG und JPEG-Bilder sind erlaubt.',
            'photo.max' => 'Die Dateigröße darf 2 MB nicht überschreiten.',
        ]);

        $photo = Image::make($this->photo)->fit(400, 400);

        $photoPath = 'public/user/' . $this->guard . '/' . auth()->id() . '/profile-photo/' . strtolower($this->photo->hashName() );

        Storage::put($photoPath, (string) $photo->encode());

        // Altes Bild löschen, falls vorhanden
        if ($this->user->profile->photo_path) {
            Storage::delete($this->user->profile->photo_path);
        }

        $this->user->profile->photo_path = $photoPath;
        $this->user->profile->save();
    }
    public function deleteUserProfilePhoto(): void
    {
        if ($this->user->profile->photo_path) {
            Storage::delete($this->user->profile->photo_path);
            $this->user->profile->photo_path = null;
            $this->user->profile->save();
        }
    }

    // Profil Datenverwaltung - Section 2
    public function mountUserProfileData(): void
    {
        $this->firstName = $this->user->first_name;
        $this->lastName = $this->user->last_name;
        $this->email = $this->user->email;
        $this->phoneNumber = $this->user->profile->phone_number ?? '';
        $this->about = $this->user->profile->about ?? '';
        $this->url = $this->user->profile->url ?? '';
        $this->street = $this->user->profile->street ?? '';
        $this->houseNumber = $this->user->profile->house_number ?? '';
        $this->postal = $this->user->profile->postal ?? '';
        $this->city = $this->user->profile->city ?? '';
    }
    public function saveUserProfileData($data): void
    {
        try {
            // Validate the input data
            $validatedData = $this->validate([
                'firstName' => 'required|string',
                'lastName' => 'required|string',
                'email' => 'required|email',
                'phoneNumber' => 'string',

                'about' => 'string',
                'url' => 'string',
                'street' => 'string',
                'houseNumber' => 'string',
                'postal' => 'string',
                'city' => 'string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            dd($e->errors()); // Fügen Sie dies hier ein, um die Fehlermeldungen auszugeben
        }

        // Update the user data
        $this->user->first_name = $validatedData['firstName'];
        $this->user->last_name = $validatedData['lastName'];
        $this->user->email = $validatedData['email'];
        $this->user->save();

        // Update the user's profile data
        $this->user->profile->phone_number = $validatedData['phoneNumber'];
        $this->user->profile->about = $validatedData['about'];
        $this->user->profile->url = $validatedData['url'];
        $this->user->profile->street = $validatedData['street'];
        $this->user->profile->house_number = $validatedData['houseNumber'];
        $this->user->profile->postal = $validatedData['postal'];
        $this->user->profile->city = $validatedData['city'];
        $this->user->profile->save();

        session()->flash('message', 'Profil erfolgreich aktualisiert.');
    }

    // Passwort aktualisieren - Section 3
    public function updateUserPassword(): void
    {
        $this->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required|min:8|different:currentPassword',
            'repeatNewPassword' => 'required|same:newPassword',
        ], [
            'currentPassword.required' => 'Aktuelles Passwort ist erforderlich.',
            'newPassword.required' => 'Neues Passwort ist erforderlich.',
            'newPassword.min' => 'Neues Passwort muss mindestens 8 Zeichen lang sein.',
            'newPassword.different' => 'Neues Passwort darf nicht mit dem aktuellen Passwort übereinstimmen.',
            'repeatNewPassword.required' => 'Neues Passwort muss bestätigt werden.',
            'repeatNewPassword.same' => 'Die Passwortbestätigung stimmt nicht mit dem neuen Passwort überein.',
        ]);

        if (!Hash::check($this->currentPassword, $this->user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['Das aktuelle Passwort ist nicht korrekt.'],
            ]);
        }

        $this->user->password = Hash::make($this->newPassword);
        $this->user->save();

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->repeatNewPassword = '';

        session()->flash('password-updated', 'Passwort erfolgreich aktualisiert.');
    }

    // Sitzungen verwalten - Section 4
    public function getBrowserSessions($user)
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get();
    }
    public function removeOtherSessions(): void
    {
        $currentSessionId = session()->getId(); // Die aktuelle Session-ID abrufen
        $userId = $this->user->id; // Die ID des angemeldeten Benutzers abrufen

        // Alle Sessions des Benutzers außer der aktuellen Session löschen und die Anzahl der gelöschten Sessions zurückgeben
        $deletedSessionsCount = Session::where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        // Überprüfen, ob andere Sitzungen gelöscht wurden
        if ($deletedSessionsCount > 0) {
            session()->flash('success', 'Alle anderen Sitzungen wurden erfolgreich abgemeldet.');
        } else {
            session()->flash('info', 'Es gibt keine anderen Sitzungen, die abgemeldet werden können.');
        }
    }

    // Account löschen - Section 5
    public function deleteUserAccount()
    {
        // Logout vor dem Löschen
        Auth::guard((new \App\Models\User)->getGuard())->logout();

        // Session invalidieren
        session()->invalidate();
        session()->regenerateToken();

        // Profil und Benutzer löschen
        $this->user->profile->delete();
        $this->user->delete();

        return redirect('/');
    }

}

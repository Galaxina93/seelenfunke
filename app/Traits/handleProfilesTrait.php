<?php

namespace App\Traits;

use App\Models\NewsletterSubscriber;
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
        $filename = strtolower($this->photo->hashName());
        $folderPath = 'user/' . $this->guard . '/' . auth()->id() . '/profile-photo/';
        $fullPath = $folderPath . $filename;

        Storage::disk('public')->put($fullPath, (string) $photo->encode());

        if ($this->user->profile->photo_path) {
            Storage::disk('public')->delete($this->user->profile->photo_path);
        }

        $this->user->profile->photo_path = $fullPath;
        $this->user->profile->save();

        $this->photo = null;
    }

    public function deleteUserProfilePhoto(): void
    {
        if ($this->user->profile->photo_path) {
            Storage::disk('public')->delete($this->user->profile->photo_path);
            $this->user->profile->photo_path = null;
            $this->user->profile->save();
        }
    }

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
        $this->country = $this->user->profile->country ?? 'DE';

        if ($this->guard === 'customer') {
            $this->isBusiness = $this->user->profile->is_business ?? 0;
            $this->birthday = $this->user->profile->birthday ? \Carbon\Carbon::parse($this->user->profile->birthday)->format('Y-m-d') : '';
        }
    }

    public function saveUserProfileData($data): void
    {
        $rules = [
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'email' => 'required|email',
            'phoneNumber' => 'nullable|string',
            'about' => 'nullable|string',
            'url' => 'nullable|string',
            'street' => 'nullable|string',
            'houseNumber' => 'nullable|string',
            'postal' => 'nullable|string',
            'city' => 'nullable|string',
            'country' => 'nullable|string|max:2',
        ];

        if ($this->guard === 'customer') {
            $rules['isBusiness'] = 'required|boolean';
            $rules['birthday'] = 'required|date';
        }

        try {
            $validatedData = $this->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            dd($e->errors());
        }

        $this->user->first_name = $validatedData['firstName'];
        $this->user->last_name = $validatedData['lastName'];
        $this->user->email = $validatedData['email'];
        $this->user->save();

        $this->user->profile->phone_number = $validatedData['phoneNumber'];
        $this->user->profile->about = $validatedData['about'];
        $this->user->profile->url = $validatedData['url'];
        $this->user->profile->street = $validatedData['street'];
        $this->user->profile->house_number = $validatedData['houseNumber'];
        $this->user->profile->postal = $validatedData['postal'];
        $this->user->profile->city = $validatedData['city'];
        $this->user->profile->country = $validatedData['country'];

        if ($this->guard === 'customer') {
            $this->user->profile->is_business = $validatedData['isBusiness'];
            $this->user->profile->birthday = $validatedData['birthday'];
        }

        $this->user->profile->save();
        session()->flash('message', 'Profil erfolgreich aktualisiert.');
    }

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

    public function getBrowserSessions($user)
    {
        return DB::table('sessions')->where('user_id', $user->id)->orderBy('last_activity', 'desc')->get();
    }

    public function removeOtherSessions(): void
    {
        $currentSessionId = session()->getId();
        $userId = $this->user->id;

        $deletedSessionsCount = Session::where('user_id', $userId)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        if ($deletedSessionsCount > 0) {
            session()->flash('success', 'Alle anderen Sitzungen wurden erfolgreich abgemeldet.');
        } else {
            session()->flash('info', 'Es gibt keine anderen Sitzungen, die abgemeldet werden können.');
        }
    }

    public function deleteUserAccount()
    {
        $user = Auth::guard($this->guard)->user();
        if (!$user) return;

        if ($user->email) {
            NewsletterSubscriber::where('email', $user->email)->delete();
        }

        $user->delete();
        Auth::guard($this->guard)->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }
}

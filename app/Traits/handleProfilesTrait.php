<?php

namespace App\Traits;

use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;
use Livewire\WithFileUploads;

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

        // Erfasse den Zustand VOR dem Update
        $oldData = [
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'phone_number' => $this->user->profile->phone_number,
            'about' => $this->user->profile->about,
            'url' => $this->user->profile->url,
            'street' => $this->user->profile->street,
            'house_number' => $this->user->profile->house_number,
            'postal' => $this->user->profile->postal,
            'city' => $this->user->profile->city,
            'country' => $this->user->profile->country,
        ];
        if ($this->guard === 'customer') {
            $oldData['is_business'] = $this->user->profile->is_business;
            $oldData['birthday'] = $this->user->profile->birthday;
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

        // Erfasse den Zustand NACH dem Update für das Delta
        $newData = [
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'phone_number' => $this->user->profile->phone_number,
            'about' => $this->user->profile->about,
            'url' => $this->user->profile->url,
            'street' => $this->user->profile->street,
            'house_number' => $this->user->profile->house_number,
            'postal' => $this->user->profile->postal,
            'city' => $this->user->profile->city,
            'country' => $this->user->profile->country,
        ];
        if ($this->guard === 'customer') {
            $newData['is_business'] = $this->user->profile->is_business;
            $newData['birthday'] = $this->user->profile->birthday;
        }

        $changes = [];
        foreach ($oldData as $key => $oldVal) {
            if ($oldVal != $newData[$key]) { // Benutze != statt !== wegen Typkonvertierungen z.B. 1 vs "1"
                $changes[$key] = ['old' => $oldVal, 'new' => $newData[$key]];
            }
        }

        // Schreibe Frontend-Audit-Log falls es eine Änderung gab
        if (count($changes) > 0) {
            \App\Models\System\SystemLog::create([
                'type' => 'system',
                'action_id' => 'user:profile_updated_frontend',
                'title' => 'Benutzerprofil selbstständig aktualisiert',
                'message' => "Der Nutzer '{$this->user->email}' hat " . count($changes) . " Profil-Feld(er) im Frontend geändert.",
                'status' => 'success',
                'payload' => [
                    'actor' => 'Kunde (im Frontend)',
                    'target_user' => $this->user->email,
                    'changes' => $changes,
                    'ip' => request()->ip()
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

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

        \App\Models\System\SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:security_update',
            'title' => 'Sicherheits-Update: Passwort geändert',
            'message' => "Der Nutzer '{$this->user->email}' hat sein Zugangspasswort im Frontend neu gesetzt.",
            'status' => 'success',
            'payload' => [
                'actor' => 'Kunde (im Frontend)',
                'target_user' => $this->user->email,
                'changes' => [
                    'password' => ['old' => '***', 'new' => 'wurde geändert']
                ],
                'ip' => request()->ip()
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

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

        $deletedSessionsCount = DB::table('sessions')->where('user_id', $userId)
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
            MarketingNewsletterSubscriber::where('email', $user->email)->delete();
        }

        $user->delete();
        Auth::guard($this->guard)->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $this->redirect('/', navigate: true);
    }
}

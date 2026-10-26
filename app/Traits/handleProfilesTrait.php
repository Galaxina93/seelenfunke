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

    protected function updateUserProfilePhoto(): void
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
        $folderPath = 'dashboard/user/' . $this->guard . '/' . $this->user->id . '/profile-photo/';
        $fullPath = $folderPath . $filename;

        Storage::disk('public')->put($fullPath, (string) $photo->encode());

        if ($this->user->profile->photo_path) {
            if (str_starts_with($this->user->profile->photo_path, 'dashboard/user/') && !str_contains($this->user->profile->photo_path, '..')) {
                Storage::disk('public')->delete($this->user->profile->photo_path);
            }
        }

        $this->user->profile->photo_path = $fullPath;
        $this->user->profile->save();

        $this->photo = null;
    }

    protected function deleteUserProfilePhoto(): void
    {
        if ($this->user->profile->photo_path) {
            if (str_starts_with($this->user->profile->photo_path, 'dashboard/user/') && !str_contains($this->user->profile->photo_path, '..')) {
                Storage::disk('public')->delete($this->user->profile->photo_path);
            }
            $this->user->profile->photo_path = null;
            $this->user->profile->save();
        }
    }

    protected function mountUserProfileData(): void
    {
        $this->firstName = $this->user->first_name;
        $this->lastName = $this->user->last_name;
        $this->email = $this->user->email;
        $this->phoneNumber = $this->user->profile->phone_number ?? '';
        $this->about = $this->user->profile->about ?? '';
        $this->url = $this->user->profile->url ?? '';

        $streetVal = $this->user->profile->street ?? '';
        $houseNumVal = $this->user->profile->house_number ?? '';

        if (empty($houseNumVal) && !empty($streetVal)) {
            if (preg_match('#^(.*?)\s*(\d+[a-zA-Z]?(?:\s*[-/]\s*\d+[a-zA-Z]?)?)$#', trim($streetVal), $matches)) {
                $streetVal = trim($matches[1]);
                $houseNumVal = trim($matches[2]);
            }
        }

        $this->street = $streetVal;
        $this->houseNumber = $houseNumVal;
        $this->postal = $this->user->profile->postal ?? '';
        $this->city = $this->user->profile->city ?? '';
        $this->country = $this->user->profile->country ?? 'DE';

        if ($this->guard === 'customer') {
            $this->isBusiness = $this->user->profile->is_business ?? 0;
            $this->birthday = $this->user->profile->birthday ? \Carbon\Carbon::parse($this->user->profile->birthday)->format('Y-m-d') : '';
        }

        $this->aiDataConsent = (bool) ($this->user->profile->ai_data_consent ?? false);
        $this->aiDataConsentAt = !empty($this->user->profile->ai_data_consent_at)
            ? \Carbon\Carbon::parse($this->user->profile->ai_data_consent_at)->format('d.m.Y H:i')
            : null;
    }

    protected function saveUserProfileData($data): void
    {
        if ($this->guard === 'customer' && ($this->user->needs_password_change ?? false)) {
            abort(403, 'Bitte ändere zuerst dein temporäres Passwort.');
        }

        $this->email = mb_strtolower(trim((string) $this->email));

        $table = match($this->guard) {
            'admin' => 'admins',
            'employee' => 'employees',
            default => 'customers',
        };

        $currentGuard = $this->guard;
        $currentUserId = (string) $this->user->id;

        $rules = [
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:191',
                'unique:' . $table . ',email,' . $this->user->id,
                function ($attribute, $value, $fail) use ($currentGuard, $currentUserId) {
                    if (\App\Models\System\SystemUser::isEmailTakenAcrossGuards((string) $value, $currentGuard, $currentUserId)) {
                        $fail('Diese E-Mail-Adresse ist bereits einem bestehenden Konto zugeordnet.');
                    }
                },
            ],
            'phoneNumber' => 'nullable|string|max:50',
            'about' => 'nullable|string|max:1000',
            'url' => ['nullable', 'string', 'max:255', 'not_regex:/^\s*(javascript|data|vbscript):/i'],
            'street' => 'nullable|string|max:150',
            'houseNumber' => 'nullable|string|max:20',
            'postal' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'aiDataConsent' => 'boolean',
        ];

        if ($this->guard === 'customer') {
            $rules['isBusiness'] = 'required|boolean';
            $rules['birthday'] = 'nullable|date';
        }

        $validatedData = $this->validate($rules);

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
        $oldData['ai_data_consent'] = (bool) ($this->user->profile->ai_data_consent ?? false);

        $emailChanged = $this->user->email !== $validatedData['email'];
        $this->user->first_name = $validatedData['firstName'];
        $this->user->last_name = $validatedData['lastName'];
        $this->user->email = $validatedData['email'];

        // Sicherheitsmaßnahme: Bei Änderung der Kunden-E-Mail Verifizierung zurücksetzen und neuen Bestätigungslink senden
        if ($emailChanged && $this->guard === 'customer') {
            $this->user->email_verified_at = null;
            if ($this->user->profile) {
                $this->user->profile->email_verified_at = null;
                $this->user->profile->save();
            }
            $this->user->save();
            try {
                if (method_exists($this->user, 'sendEmailVerificationNotification')) {
                    $this->user->sendEmailVerificationNotification();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Konnte Verifizierungsmail nach E-Mail-Änderung nicht senden: " . $e->getMessage());
            }
        } else {
            $this->user->save();
        }

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
            $this->user->profile->birthday = $validatedData['birthday'] ?: null;
        }

        $newAiConsent = (bool) ($this->aiDataConsent ?? false);
        if ((bool) ($this->user->profile->ai_data_consent ?? false) !== $newAiConsent) {
            $this->user->profile->ai_data_consent = $newAiConsent;
            $this->user->profile->ai_data_consent_at = $newAiConsent ? now() : null;
        }

        $this->user->profile->save();

        if ($newAiConsent) {
            session()->put('ai_chat_privacy_accepted', true);
            $this->aiDataConsentAt = $this->user->profile->ai_data_consent_at
                ? \Carbon\Carbon::parse($this->user->profile->ai_data_consent_at)->format('d.m.Y H:i')
                : now()->format('d.m.Y H:i');
        } else {
            session()->forget('ai_chat_privacy_accepted');
            $this->aiDataConsentAt = null;
        }

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
        $newData['ai_data_consent'] = (bool) $this->user->profile->ai_data_consent;

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

    protected function updateUserPassword(): void
    {
        $this->validate([
            'currentPassword' => 'required',
            'newPassword' => ['required', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'different:currentPassword'],
            'repeatNewPassword' => 'required|same:newPassword',
        ], [
            'currentPassword.required' => 'Aktuelles Passwort ist erforderlich.',
            'newPassword.required' => 'Neues Passwort ist erforderlich.',
            'newPassword.min' => 'Neues Passwort muss mindestens 8 Zeichen lang sein.',
            'newPassword.regex' => 'Das neue Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben und eine Zahl enthalten.',
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
        if ($this->guard === 'customer') {
            $this->user->needs_password_change = false;
            $this->user->temporary_password = null;
        }
        $this->user->setRememberToken(\Illuminate\Support\Str::random(60));
        $this->user->save();

        if (method_exists($this->user, 'tokens')) {
            $this->user->tokens()->delete();
        }

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->user->id)
                ->where('id', '!=', session()->getId())
                ->delete();
        }

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

    public function getBrowserSessions($user = null)
    {
        $targetUserId = $this->user?->id;
        if (!$targetUserId) {
            return collect();
        }

        return DB::table('sessions')->where('user_id', $targetUserId)->orderBy('last_activity', 'desc')->get();
    }

    protected function removeOtherSessions(): void
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

    protected function deleteUserAccount()
    {
        $user = Auth::guard($this->guard)->user();
        if (!$user) return;

        // Schutz vor Lock-Out: Der letzte Administrator darf niemals gelöscht werden
        if ($this->guard === 'admin') {
            $adminCount = \App\Models\Admin\Admin::count();
            if ($adminCount <= 1) {
                session()->flash('error', 'Der letzte Administrator-Account kann aus Sicherheitsgründen nicht gelöscht werden.');
                return;
            }
        }

        $userEmail = $user->email;
        $userId = $user->id;

        // Newsletter austragen
        if ($userEmail) {
            MarketingNewsletterSubscriber::where('email', $userEmail)->delete();
        }

        // Aktive Warenkörbe vom gelöschten Kunden trennen
        if ($this->guard === 'customer') {
            \App\Models\Cart\Cart::where('customer_id', $userId)->update(['customer_id' => null]);
        }

        // Audit-Log für Nachvollziehbarkeit & GoBD/DSGVO
        if (class_exists(\App\Models\System\SystemLog::class)) {
            \App\Models\System\SystemLog::create([
                'type' => 'system',
                'action_id' => 'user:account_deleted',
                'title' => 'Account gelöscht (archiviert)',
                'message' => "Der Nutzer '{$userEmail}' ({$this->guard}) hat seinen Account selbstständig gelöscht. Der Account wurde archiviert (SoftDelete).",
                'status' => 'warning',
                'payload' => [
                    'actor' => 'Kunde (im Frontend)',
                    'user_id' => $userId,
                    'email' => $userEmail,
                    'guard' => $this->guard,
                    'ip' => request()->ip()
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->delete();
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

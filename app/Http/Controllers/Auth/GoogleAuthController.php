<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Session as SessionModel;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;

// WICHTIG: Diese Zeilen haben gefehlt oder waren falsch:
use App\Models\User;
use App\Models\Customer;
use App\Models\Admin;     // <--- Fix für deinen Fehler
use App\Models\Employee;  // <--- Wird für Mitarbeiter benötigt

class GoogleAuthController extends Controller
{
    public function redirectToGoogle($guard = 'customer')
    {
        // Guard speichern (wird aber durch intelligente Erkennung oft überschrieben)
        Session::put('auth_guard', $guard);
        Session::save();
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            // Google User abrufen (stateless)
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = null;
            $guard = null;

            // ---------------------------------------------------------
            // 1. INTELLIGENTE BENUTZER-ERKENNUNG
            // Wir prüfen der Reihe nach: Admin -> Employee -> Customer
            // ---------------------------------------------------------

            // A) Check: Ist es ein Admin?
            // Jetzt findet er die Klasse, da "use App\Models\Admin;" oben steht
            $admin = Admin::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if ($admin) {
                $user = $admin;
                $guard = 'admin';
            }

            // B) Check: Ist es ein Mitarbeiter? (Nur wenn kein Admin gefunden)
            if (!$user) {
                $employee = Employee::where('google_id', $googleUser->id)
                    ->orWhere('email', $googleUser->email)
                    ->first();

                if ($employee) {
                    $user = $employee;
                    $guard = 'employee';
                }
            }

            // C) Check: Ist es ein Kunde? (Nur wenn bisher nichts gefunden)
            if (!$user) {
                $customer = Customer::where('google_id', $googleUser->id)
                    ->orWhere('email', $googleUser->email)
                    ->first();

                if ($customer) {
                    $user = $customer;
                    $guard = 'customer';
                }
            }

            // ---------------------------------------------------------
            // 2. NEU-REGISTRIERUNG (NUR KUNDE)
            // ---------------------------------------------------------

            // Wenn gar kein User gefunden wurde -> Neuen Kunden anlegen
            if (!$user) {
                $guard = 'customer'; // Default für neue User

                // Wir müssen Vor- und Nachnamen trennen
                $nameParts = explode(' ', $googleUser->name, 2);
                $firstName = $nameParts[0] ?? 'Kunde';
                $lastName = $nameParts[1] ?? '';

                // Neuen Kunden anlegen
                $user = Customer::create([
                    'email' => $googleUser->email,
                    'first_name' => $googleUser->user['given_name'] ?? $firstName,
                    'last_name' => $googleUser->user['family_name'] ?? $lastName,
                    'password' => Hash::make(Str::random(24)), // Zufälliges, starkes Passwort
                    'google_id' => $googleUser->id,
                ]);

                // WICHTIG: Model neu laden für Relationen
                $user->refresh();

                Log::info('Neuer Kunde via Google registriert: ' . $googleUser->email);
            }

            // ---------------------------------------------------------
            // 3. DATEN AKTUALISIEREN & LOGIK
            // ---------------------------------------------------------

            // Google ID nachtragen, falls sie fehlte (Account Linking)
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->id]);
            }

            // PROFILBILD LOGIK
            $avatarUrl = $googleUser->avatar_original ?? $googleUser->avatar;

            // Zugriff auf Profil prüfen
            if ($avatarUrl && $user->profile && empty($user->profile->photo_path)) {
                try {
                    $fileContents = @file_get_contents($avatarUrl);

                    if ($fileContents) {
                        $image = Image::make($fileContents)->fit(400, 400);
                        $filename = Str::random(40) . '.jpg';

                        // Pfad dynamisch mit dem ermittelten $guard
                        $photoPath = 'public/user/' . $guard . '/' . $user->id . '/profile-photo/' . $filename;

                        Storage::put($photoPath, (string) $image->encode('jpg', 90));

                        $user->profile->update([
                            'photo_path' => $photoPath
                        ]);

                        Log::info('Google Profilbild für User ' . $user->id . ' (' . $guard . ') gespeichert.');
                    }
                } catch (\Exception $e) {
                    Log::error('Fehler beim Laden des Google Profilbilds: ' . $e->getMessage());
                }
            }

            // ---------------------------------------------------------
            // 4. LOGIN DURCHFÜHREN
            // ---------------------------------------------------------

            Auth::guard($guard)->login($user, true);

            // Permissions laden
            $permissions = [];
            if(method_exists($user, 'roles')) {
                foreach ($user->roles as $role) {
                    foreach ($role->permissions as $permission) {
                        $permissions[$permission->name] = $permission->name;
                    }
                }
            }
            session(["permissions" => $permissions]);

            // Browser Session speichern
            $this->setBrowserSession($user);

            // Redirect zum Dashboard des ermittelten Guards
            return redirect()->route($guard . '.dashboard');

        } catch (\Exception $e) {
            Log::error('Google Login Error: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Login fehlgeschlagen. Bitte versuche es erneut.');
        }
    }

    protected function setBrowserSession($user)
    {
        $sessionId = Session::getId();
        $payload = base64_encode(serialize(Session::all()));

        $sessionData = [
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent(), 0, 255),
            'payload' => $payload,
            'device_type' => 'Desktop',
            'last_activity' => time(),
        ];

        SessionModel::updateOrInsert(
            ['user_id' => $user->id, 'ip_address' => request()->ip()],
            $sessionData
        );
    }
}

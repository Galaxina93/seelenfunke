<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Laravel\Socialite\Facades\Socialite;

// WICHTIG: Diese Zeilen haben gefehlt oder waren falsch:
// <--- Fix für deinen Fehler
// <--- Wird für Mitarbeiter benötigt

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
            if (\App\Models\System\SystemBlockedIp::isBlocked(request()->ip())) {
                return redirect()->route('login')->with('error', 'Deine IP-Adresse wurde aufgrund verdächtiger Aktivitäten gesperrt.');
            }

            // Google User abrufen (stateless)
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Kritische Validierung: Google-ID und E-Mail dürfen niemals leer/null sein (verhindert WHERE google_id IS NULL Auth-Bypass)
            if (empty($googleUser->id) || empty($googleUser->email) || !filter_var($googleUser->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Google OAuth Login abgelehnt: Fehlende Google-ID oder ungültige E-Mail-Adresse.');
                return redirect()->route('login')->with('error', 'Google-Anmeldung fehlgeschlagen: Ungültige Kontodaten übermittelt.');
            }

            // Sicherheitsprüfung: Unverifizierte Google-Accounts dürfen nicht gelinkt werden (Fail-Closed)
            $emailVerified = $googleUser->user['email_verified'] ?? ($googleUser->user['verified_email'] ?? null);
            if (!in_array($emailVerified, [true, 'true', 1, '1'], true)) {
                Log::warning('Google OAuth Login abgelehnt: E-Mail-Adresse nicht bei Google verifiziert.', ['email' => $googleUser->email]);
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:oauth_unverified',
                    'Unverifizierter Google-OAuth Login blockiert',
                    "Google-Login für '{$googleUser->email}' abgelehnt (E-Mail nicht verifiziert).",
                    'warning',
                    ['target_email' => $googleUser->email]
                );
                return redirect()->route('login')->with('error', 'Google-Konto wurde abgelehnt, da die E-Mail-Adresse nicht verifiziert ist.');
            }

            $user = null;
            $guard = null;

            // ---------------------------------------------------------
            // 1. INTELLIGENTE BENUTZER-ERKENNUNG
            // Wir prüfen der Reihe nach: Admin -> Employee -> Customer
            // ---------------------------------------------------------

            // A) Check: Ist es ein Admin? (Exakte google_id hat Vorrang vor E-Mail-Fallback)
            $admin = Admin::withTrashed()->where('google_id', $googleUser->id)->first()
                ?? Admin::withTrashed()->where('email', $googleUser->email)->first();

            if ($admin) {
                $user = $admin;
                $guard = 'admin';
            }

            // B) Check: Ist es ein Mitarbeiter? (Nur wenn kein Admin gefunden)
            if (!$user) {
                $employee = Employee::withTrashed()->where('google_id', $googleUser->id)->first()
                    ?? Employee::withTrashed()->where('email', $googleUser->email)->first();

                if ($employee) {
                    $user = $employee;
                    $guard = 'employee';
                }
            }

            // C) Check: Ist es ein Kunde? (Nur wenn bisher nichts gefunden)
            if (!$user) {
                $customer = Customer::withTrashed()->where('google_id', $googleUser->id)->first()
                    ?? Customer::withTrashed()->where('email', $googleUser->email)->first();

                if ($customer) {
                    $user = $customer;
                    $guard = 'customer';
                }
            }

            if ($user && method_exists($user, 'trashed') && $user->trashed()) {
                \App\Services\System\SystemSecurityService::recordLoginAttempt($user->email, request()->ip(), false, 'google_oauth');
                return redirect()->route('login')->with('error', 'Dieser Account wurde deaktiviert.');
            }

            // Sicherheitscheck gegen Account-Hijacking: Falls bereits eine abweichende Google-ID verknüpft ist, ablehnen
            if ($user && !empty($user->google_id) && (string) $user->google_id !== (string) $googleUser->id) {
                \App\Services\System\SystemSecurityService::recordLoginAttempt($user->email, request()->ip(), false, 'google_oauth_mismatch');
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:oauth_id_mismatch',
                    'Google-ID Konflikt beim Login blockiert',
                    "Abweichende Google-ID beim Login für '{$user->email}' ({$guard}) abgelehnt.",
                    'error',
                    ['target_email' => $user->email, 'guard' => $guard]
                );
                return redirect()->route('login')->with('error', 'Dieses Konto ist bereits mit einem anderen Google-Account verknüpft.');
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
                    'email_verified_at' => now(),
                ]);

                // WICHTIG: Model neu laden für Relationen
                $user->refresh();

                # Log::info('Neuer Kunde via Google registriert: ' . $googleUser->email);
            }

            // ---------------------------------------------------------
            // 3. DATEN AKTUALISIEREN & LOGIK
            // ---------------------------------------------------------

            // Google ID nachtragen, falls sie fehlte (Account Linking)
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->id]);
            }

            // Da Google die E-Mail-Adresse verifiziert hat, Verifizierungsstatus im Profil synchronisieren
            if ($user->profile && $user->profile->email_verified_at === null) {
                $user->profile->forceFill(['email_verified_at' => now()])->save();
            }

            // PROFILBILD LOGIK
            $avatarUrl = $googleUser->avatar_original ?? $googleUser->avatar;

            // Zugriff auf Profil prüfen
            if ($avatarUrl && $user->profile && empty($user->profile->photo_path)) {
                try {
                    $parsedAvatar = parse_url($avatarUrl);
                    $avatarHost = strtolower($parsedAvatar['host'] ?? '');
                    $avatarScheme = strtolower($parsedAvatar['scheme'] ?? '');

                    // Nur sichere HTTPS-Verbindungen zu offiziellen Google-Domains zulassen (ohne Redirect-Verfolgung)
                    if ($avatarScheme === 'https' && (str_ends_with($avatarHost, '.googleusercontent.com') || $avatarHost === 'googleusercontent.com')) {
                        $resp = \Illuminate\Support\Facades\Http::timeout(5)->withoutRedirecting()->get($avatarUrl);

                        if ($resp->successful() && !empty($resp->body()) && strlen($resp->body()) <= 5 * 1024 * 1024) {
                            $fileContents = $resp->body();
                            $image = Image::make($fileContents)->fit(400, 400);
                            $filename = Str::random(40) . '.jpg';

                            // 1. Physischer Pfad (wohin gespeichert wird)
                            // Wir nutzen den Disk 'public', der zeigt automatisch auf storage/app/public
                            $folderPath = 'dashboard/user/' . $guard . '/' . $user->id . '/profile-photo/';

                            // 2. Speichern über den 'public' Disk
                            Storage::disk('public')->put($folderPath . $filename, (string) $image->encode('jpg', 90));

                            // 3. In Datenbank speichern (OHNE 'public/' am Anfang, da wir den Disk nutzen)
                            $user->profile->update([
                                'photo_path' => $folderPath . $filename
                            ]);

                            # Log::info('Google Profilbild für User ' . $user->id . ' (' . $guard . ') gespeichert.');
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Fehler beim Laden des Google Profilbilds: ' . $e->getMessage());
                }
            }

            // ---------------------------------------------------------
            // 4. LOGIN DURCHFÜHREN (INKL. 2FA-ENFORCEMENT)
            // ---------------------------------------------------------

            \App\Services\System\SystemSecurityService::recordLoginAttempt($user->email, request()->ip(), true, 'google_oauth');

            // Wenn 2FA für das Konto aktiviert ist, darf Google OAuth 2FA NICHT überspringen!
            if ($user->profile && $user->profile->two_factor_is_active) {
                session()->regenerate();
                session(['2fa_user_id' => $user->id, 'guard' => $guard]);
                return redirect()->route('login');
            }

            session()->regenerate();
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

            if ($guard === 'customer' && !empty($user->needs_password_change)) {
                session(['force_password_change_customer_id' => $user->id]);
                return redirect()->route('customer.password-change-force');
            }

            // Redirect zum Dashboard des ermittelten Guards
            return redirect()->route($guard . '.dashboard');

        } catch (\Exception $e) {
            Log::error('Google Login Error: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Login fehlgeschlagen. Bitte versuche es erneut.');
        }
    }


}

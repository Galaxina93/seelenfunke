<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Session as SessionModel; // Alias um Namenskonflikte zu vermeiden
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\User; // Dein Haupt-User Model für die Helper Funktion

class GoogleAuthController extends Controller
{
    // 1. Weiterleitung zu Google
    public function redirectToGoogle($guard)
    {
        // Wir speichern den Guard in der Session, damit wir ihn beim Callback noch kennen
        Session::put('auth_guard', $guard);

        return Socialite::driver('google')->redirect();
    }

    // 2. Rückkehr von Google
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Welcher Guard war ausgewählt?
            $guard = Session::get('auth_guard', 'customer'); // Fallback auf customer

            // Das richtige Model holen (Deine Logik aus dem Livewire Component)
            $modelClass = (new User)->getUserModelByGuard($guard);

            // Benutzer suchen (per Google ID oder E-Mail)
            $user = $modelClass::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if ($user) {
                // Google ID speichern, falls noch nicht vorhanden (Account Linking)
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->id]);
                }

                // Einloggen
                Auth::guard($guard)->login($user);

                // Permissions in Session laden (Deine Logik)
                $permissions = [];
                if(method_exists($user, 'roles')) { // Sicherheitscheck
                    foreach ($user->roles as $role) {
                        foreach ($role->permissions as $permission) {
                            $permissions[$permission->name] = $permission->name;
                        }
                    }
                }
                session(["permissions" => $permissions]);

                // Browser Session Logik (Kopie aus deiner Livewire Component)
                $this->setBrowserSession($user);

                return redirect()->route($guard . '.dashboard');
            } else {
                return redirect()->route('login')->with('error', 'Kein Benutzerkonto mit dieser E-Mail gefunden.');
            }

        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Login mit Google fehlgeschlagen: ' . $e->getMessage());
        }
    }

    // Hilfsfunktion: Browser Session (1:1 Kopie aus deinem Livewire Code)
    protected function setBrowserSession($user)
    {
        $sessionId = Session::getId();
        $payload = base64_encode(serialize(Session::all()));
        list($shortenedUserAgent, $deviceType) = $this->getShortenedUserAgent(request()->userAgent());

        $sessionData = [
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => $shortenedUserAgent,
            'payload' => $payload,
            'device_type' => $deviceType,
            'last_activity' => time(),
        ];

        // Beachte: Hier muss das Model passen. Wenn SessionModel polymorph ist,
        // musst du evtl. auch 'user_type' speichern, da IDs über Tabellen hinweg doppelt sein können.
        // Für dieses Beispiel nutze ich deine Logik:
        SessionModel::updateOrInsert(
            ['user_id' => $user->id, 'ip_address' => request()->ip()],
            $sessionData
        );
    }

    protected function getShortenedUserAgent($userAgent): array
    {
        // (Hier deine exakte getShortenedUserAgent Funktion aus der Livewire Component einfügen)
        // ... (Kürzung der Übersichtlichkeit halber) ...
        return ['Browser', 'Desktop']; // Platzhalter, bitte deine Logik einfügen
    }
}

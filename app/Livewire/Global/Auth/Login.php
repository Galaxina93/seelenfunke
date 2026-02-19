<?php

namespace App\Livewire\Global\Auth;

use App\Models\LoginAttempt;
use App\Models\Session;
use App\Traits\handleMailsTrait;
use App\Traits\handlePasswordResetTrait;
use App\Traits\handleTwoFactorTrait;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Login extends Component
{
    use handleMailsTrait;
    use handleTwoFactorTrait;
    use handlePasswordResetTrait;
    use WithRateLimiting;

    #[Rule('required|email', message: 'Bitte gib eine gültige E-Mail-Adresse ein.')]
    public string $email = '';

    #[Rule('required', message: 'Bitte gib dein Passwort ein.')]
    public string $password = '';

    public bool $remember = false;

    public $user;
    public string $guard = 'customer';
    public string $activeView = 'login';

    public function mount()
    {
        if (session()->has('2fa_user_id') && session()->has('guard')) {
            $this->guard = session('guard');
            $userModel = (new \App\Models\User)->getUserModelByGuard($this->guard);
            $this->user = $userModel::find(session('2fa_user_id'));

            if($this->user) {
                $this->activeView = 'twoFactor';
            }
        }
    }

    public function render()
    {
        return view('livewire.auth.login');
    }

    public function login()
    {
        try {
            $this->rateLimit(6);
        } catch (TooManyRequestsException $exception) {
            throw ValidationException::withMessages([
                'email' => "Langsam! Bitte warten Sie noch {$exception->secondsUntilAvailable} Sekunden.",
            ]);
        }

        $this->validate();

        $guardsToCheck = ['admin', 'employee', 'customer'];
        $foundGuard = null;
        $foundUser = null;

        foreach ($guardsToCheck as $guard) {
            if (Auth::guard($guard)->validate([
                'email' => $this->email,
                'password' => $this->password
            ])) {
                $userModelClass = (new \App\Models\User)->getUserModelByGuard($guard);
                $candidate = $userModelClass::withTrashed()->where('email', $this->email)->first();

                if ($candidate) {
                    $foundGuard = $guard;
                    $foundUser = $candidate;
                    break;
                }
            }
        }

        if (!$foundUser || !$foundGuard) {
            $this->logLoginAttempt($this->email, false);
            throw ValidationException::withMessages([
                'email' => 'Die eingegebenen Anmeldedaten sind ungültig.',
            ]);
        }

        $this->guard = $foundGuard;
        $this->user = $foundUser;

        if ($this->user->trashed()) {
            $this->logLoginAttempt($this->email, false);
            throw ValidationException::withMessages([
                'email' => 'Dieser Account wurde deaktiviert.',
            ]);
        }

        // HIER WIRD DIE METHODE GENUTZT:
        // Wir prüfen, ob der Kunde seine E-Mail bereits verifiziert hat
        if ($this->guard === 'customer' && !$this->user->hasVerifiedEmail()) {
            $this->logLoginAttempt($this->email, false);
            throw ValidationException::withMessages([
                'email' => 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in deinem Postfach.',
            ]);
        }

        $this->logLoginAttempt($this->email, true);

        if ($this->user->profile && $this->user->profile->two_factor_is_active) {
            session(['2fa_user_id' => $this->user->id, 'guard' => $this->guard]);
            $this->activeView = 'twoFactor';
            return;
        }

        if (Auth::guard($this->guard)->attempt([
            'email' => $this->email,
            'password' => $this->password,
        ], $this->remember)) {
            $loggedInUser = Auth::guard($this->guard)->user();

            $permissions = [];
            if(method_exists($loggedInUser, 'roles')) {
                $permissions = $loggedInUser->roles->flatMap(fn ($role) => $role->permissions)
                    ->pluck('name', 'name')
                    ->all();
            }
            session(['permissions' => $permissions]);

            $this->setBrowserSession($loggedInUser);
            return redirect()->to(route($this->guard . '.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'Ein unbekannter Fehler ist beim Login aufgetreten.',
        ]);
    }

    protected function logLoginAttempt(string $email, bool $success): void
    {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => request()->ip(),
            'success' => $success,
            'attempted_at' => now(),
        ]);
    }

    public function setBrowserSession($user)
    {
        $sessionId = session()->getId();
        $payload = base64_encode(serialize(session()->all()));

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

        Session::updateOrInsert(
            ['user_id' => $user->id, 'ip_address' => request()->ip()],
            $sessionData
        );
    }

    public function getShortenedUserAgent($userAgent): array
    {
        $browser = '';
        $os = '';
        $device = '';

        if (preg_match('/(Windows|Mac|Linux)/i', $userAgent, $osMatches)) {
            $os = $osMatches[1];
            $device = 'Desktop';
        } elseif (preg_match('/(Android|iPhone)/i', $userAgent, $osMatches)) {
            $os = $osMatches[1];
            $device = 'Mobile';
        }

        if (preg_match('/(Chrome|Firefox|Safari|Opera|MSIE|Edg|Trident)/i', $userAgent, $browserMatches)) {
            $browser = $browserMatches[1];
        }

        if ($browser == 'MSIE' || $browser == 'Trident') {
            $browser = 'Internet Explorer';
        } elseif ($browser == 'Edg') {
            $browser = 'Edge';
        }

        return [$os . ' - ' . $browser, $device];
    }

    public function setPasswordResetView(): void
    {
        $this->activeView = 'passwordReset';
    }

    public function setLoginView(): void
    {
        $this->activeView = 'login';
    }
}

<?php

namespace App\Livewire\Global\Auth;

use App\Traits\handleProfilesTrait;
use App\Traits\handleTwoFactorTrait;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuth extends Component
{
    use handleProfilesTrait;
    use handleTwoFactorTrait;

    public $user;

    public $code;
    public $qrCodeSvg;
    public $secretKey;

    public $twoFactorActive;

    public string $password;

    public bool $confirmPasswordOpener;

    public function mount()
    {
        $guard = (new \App\Models\User)->getGuard();
        $this->user = Auth::guard($guard)->user();

        $this->twoFactorActive = $this->user->profile->two_factor_is_active;

        // SecretKey initialisieren
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $this->secretKey = $google2fa->generateSecretKey();
    }

    public function render()
    {
        return view('livewire.profile.two-factor-auth');
    }

    public function confirmPassword()
    {
        $this->confirmPasswordOpener = true;
    }

    public function activate(): void
    {
        $this->validate([
            'password' => 'required',
        ], [
            'password.required' => 'Aktuelles Passwort ist erforderlich.',
        ]);

        if (!Hash::check($this->password, $this->user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Das Passwort ist nicht korrekt.'],
            ]);
        }

        $this->generateQrCode();
        $this->generateRecoveryCodes();

        $this->password = "";

        $this->user->profile->two_factor_secret = $this->secretKey;

        $this->user->profile->two_factor_is_active = true;
        $this->user->profile->save();

        // Blade zum Neurendern zwingen
        $this->twoFactorActive = true;
        $this->confirmPasswordOpener = false;

    }
    public function deActivate()
    {
        if (session()->has('2fa_user_id')) {
            session()->forget('2fa_user_id');
        }
        $this->user->profile->two_factor_is_active = false;
        $this->user->profile->two_factor_secret = null;
        $this->user->profile->two_factor_recovery_codes = null;
        $this->confirmPasswordOpener = false;
        $this->user->profile->save();

        // Blade zum Neurendern zwingen
        $this->twoFactorActive = false;
        $this->confirmPasswordOpener = false;
    }

    public function generateQrCode(): void
    {
        $qrCodeUrl = (new Google2FA())->getQRCodeUrl(
            config('app.name'),
            $this->user->email,
            $this->secretKey
        );

        $this->qrCodeSvg = $this->generateSvg($qrCodeUrl);
    }
    public function generateSvg(string $data, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($data);
    }

    public function generateRecoveryCodes()
    {
        $recoveryCodes = [];

        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = $this->generateRandomString() . '-' . $this->generateRandomString();
        }

        $this->user->profile->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $this->user->profile->save();

    }
    private function generateRandomString(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }


}

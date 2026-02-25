<?php

namespace App\Livewire\Global\Profile;

use App\Models\User as UserHelper;
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
use Livewire\WithFileUploads;
use PragmaRX\Google2FA\Google2FA;

class ProfileDropdown extends Component
{
    use WithFileUploads;
    use handleProfilesTrait;
    use handleTwoFactorTrait;

    public $photo;
    public object $user;
    public string $guard;

    // Profilfelder
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $phoneNumber = '';
    public string $about = '';
    public string $url = '';
    public string $street = '';
    public string $houseNumber = '';
    public string $postal = '';
    public string $city = '';
    public string $country = 'DE';
    public $isBusiness = 0;
    public string $birthday = '';

    // Passwortfelder
    public string $newPassword = '';
    public string $currentPassword = '';
    public string $repeatNewPassword = '';

    // 2FA Felder
    public $twoFactorActive = false;
    public $qrCodeSvg = null;
    public $secretKey = null;
    public $confirmPasswordOpener = false;
    public $password = '';

    public function mount()
    {
        $this->guard = (new UserHelper)->getGuard();
        $this->user = Auth::guard($this->guard)->user();

        if ($this->user) {
            $this->mountUserProfileData();
            $this->country = $this->user->profile->country ?? 'DE';
            $this->twoFactorActive = $this->user->profile->two_factor_is_active ?? false;

            if (!$this->twoFactorActive) {
                $google2fa = new Google2FA();
                $this->secretKey = $google2fa->generateSecretKey();
            }
        }
    }

    public function updatedPhoto(): void
    {
        $this->updateUserProfilePhoto();
    }

    public function deletePhoto(): void
    {
        $this->deleteUserProfilePhoto();
    }

    public function saveProfile(): void
    {
        $this->saveUserProfileData([]);
        $this->dispatch('saved');
    }

    public function updatePassword(): void
    {
        $this->updateUserPassword();
        $this->dispatch('password-updated');
    }

    public function deleteOtherSessions(): void
    {
        $this->removeOtherSessions();
        $this->dispatch('loggedOut');
    }

    public function deleteAccount(): void
    {
        $this->deleteUserAccount();
    }

    public function logout()
    {
        Auth::guard($this->guard)->logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect('/');
    }

    public function optOut(\App\Services\Gamification\GamificationService $gameService)
    {
        if ($this->guard === 'customer' && $this->user) {
            $profile = $gameService->getProfile($this->user);
            if ($profile) {
                $profile->is_active = false;
                $profile->save();
            }
            return redirect()->route('customer.dashboard');
        }
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
        $this->twoFactorActive = true;
        $this->confirmPasswordOpener = false;
        $this->dispatch('saved');
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
        $this->twoFactorActive = false;
        $this->dispatch('saved');
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
        $this->dispatch('saved');
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

    public function render()
    {
        return view('livewire.profile.profile-dropdown', [
            'activeCountries' => shop_setting('active_countries', ['DE' => 'Deutschland'])
        ]);
    }
}

<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\AuthProfileDropdown;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Support\Str;

class AuthProfileDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();



        $this->user = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'test@kunde.de',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => Hash::make('password123')
        ]);

        // Update associated profile (auto-created by Customer::created event)
        $this->user->profile()->update([
            'phone_number' => '123456789',
            'about' => 'Test User',
            'street' => 'Teststraße',
            'house_number' => '1',
            'postal' => '12345',
            'city' => 'Musterstadt',
            'country' => 'DE',
            'is_business' => 0,
            'birthday' => '1990-01-01',
            'two_factor_is_active' => false,
        ]);

        $this->actingAs($this->user->fresh(), 'customer');
    }

    public function test_component_mounts_correctly_and_loads_data()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->assertSet('firstName', 'Max')
            ->assertSet('lastName', 'Mustermann')
            ->assertSet('email', 'test@kunde.de')
            ->assertSet('city', 'Musterstadt')
            ->assertSet('country', 'DE')
            ->assertSet('twoFactorActive', false);
    }

    public function test_profile_can_be_updated()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->set('firstName', 'Moritz')
            ->set('lastName', 'Müller')
            ->set('city', 'Berlin')
            ->set('isBusiness', false)
            ->set('birthday', '1990-01-01')
            ->call('saveProfile')
            ->assertDispatched('saved')
            ->assertDispatched('profile-updated');

        $this->user->refresh();
        
        $this->assertEquals('Moritz', $this->user->first_name);
        $this->assertEquals('Müller', $this->user->last_name);
        $this->assertEquals('Berlin', $this->user->profile->city);
    }

    public function test_profile_birthday_can_be_set_to_null()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->set('firstName', 'Moritz')
            ->set('lastName', 'Müller')
            ->set('city', 'Berlin')
            ->set('isBusiness', false)
            ->set('birthday', '')
            ->call('saveProfile')
            ->assertDispatched('saved')
            ->assertDispatched('profile-updated');

        $this->user->refresh();
        
        $this->assertNull($this->user->profile->birthday);
    }

    public function test_password_can_be_updated()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->set('currentPassword', 'password123')
            ->set('newPassword', 'NewPassword123')
            ->set('repeatNewPassword', 'NewPassword123')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertDispatched('password-updated');

        $this->user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $this->user->password));
    }

    public function test_password_update_fails_with_incorrect_current_password()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->set('currentPassword', 'wrongpassword')
            ->set('newPassword', 'NewPassword123')
            ->set('repeatNewPassword', 'NewPassword123')
            ->call('updatePassword')
            ->assertHasErrors(['currentPassword']);
    }

    public function test_two_factor_authentication_can_be_activated()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->set('password', 'password123')
            ->call('activate')
            ->assertHasNoErrors()
            ->assertSet('twoFactorActive', true)
            ->assertDispatched('saved');

        $this->user->profile->refresh();
        $this->assertTrue((bool) $this->user->profile->two_factor_is_active);
        $this->assertNotNull($this->user->profile->two_factor_secret);
        $this->assertNotNull($this->user->profile->two_factor_recovery_codes);
    }

    public function test_two_factor_authentication_can_be_deactivated()
    {
        $this->user->profile->two_factor_is_active = true;
        $this->user->profile->two_factor_secret = Crypt::encrypt('dummysecret');
        $this->user->profile->two_factor_recovery_codes = Crypt::encrypt(json_encode(['code1', 'code2', 'code3']));
        $this->user->profile->save();

        Livewire::test(AuthProfileDropdown::class)
            ->call('deActivate')
            ->assertSet('twoFactorActive', false)
            ->assertDispatched('saved');

        $this->user->profile->refresh();
        $this->assertFalse((bool) $this->user->profile->two_factor_is_active);
        $this->assertNull($this->user->profile->two_factor_secret);
        $this->assertNull($this->user->profile->two_factor_recovery_codes);
    }

    public function test_can_upload_profile_photo()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg');

        Livewire::test(AuthProfileDropdown::class)
            ->set('photo', $file)
            ->assertHasNoErrors(['photo']);

        $this->user->profile->refresh();
        $this->assertNotNull($this->user->profile->photo_path);
        Storage::disk('public')->assertExists($this->user->profile->photo_path);
    }

    public function test_can_delete_profile_photo()
    {
        Storage::fake('public');
        $path = 'dashboard/user/customer/' . $this->user->id . '/profile-photo/avatar.jpg';
        Storage::disk('public')->put($path, 'dummy content');

        $this->user->profile->photo_path = $path;
        $this->user->profile->save();

        Livewire::test(AuthProfileDropdown::class)
            ->call('deletePhoto');

        $this->user->profile->refresh();
        $this->assertNull($this->user->profile->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_can_delete_other_browser_sessions()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->call('deleteOtherSessions')
            ->assertDispatched('loggedOut');
    }

    public function test_user_can_delete_account()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->call('deleteAccount')
            ->assertRedirect('/');

        $this->assertSoftDeleted('customers', [
            'id' => $this->user->id,
        ]);
    }

    public function test_user_can_logout()
    {
        Livewire::test(AuthProfileDropdown::class)
            ->call('logout')
            ->assertRedirect('/');

        $this->assertGuest('customer');
    }

    public function test_customer_can_toggle_marketing_consent_and_email_change_requires_fresh_double_opt_in()
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->user->forceFill(['email_verified_at' => now()])->save();
        $this->user->profile()->update([
            'email_verified_at' => now(),
            'cart_reminder_unsubscribed_at' => now()->subDay(),
        ]);
        $this->actingAs($this->user->fresh(), 'customer');

        // 1. Im Profil aktivieren -> hebt alten Opt-Out auf und setzt verifiziertes Opt-In
        Livewire::test(AuthProfileDropdown::class)
            ->assertSet('marketingConsent', false)
            ->set('marketingConsent', true)
            ->assertSet('marketingConsent', true);

        $this->assertTrue($this->user->fresh()->hasMarketingConsent());
        $this->assertNull($this->user->fresh()->profile->cart_reminder_unsubscribed_at);

        // 2. E-Mail-Adresse im Profil ändern -> alte Adresse wird ausgetragen, neue wartet auf E-Mail-Bestätigung (DOI)
        Livewire::test(AuthProfileDropdown::class)
            ->set('email', 'neu@kunde.de')
            ->call('saveProfile')
            ->assertSet('marketingConsent', false);

        $this->assertDatabaseMissing('marketing_newsletter_subscribers', ['email' => 'test@kunde.de']);
        $this->assertDatabaseHas('marketing_newsletter_subscribers', [
            'email' => 'neu@kunde.de',
            'is_verified' => false,
            'privacy_accepted' => true,
        ]);
        $this->assertFalse($this->user->fresh()->hasMarketingConsent());

        // Nach Klick auf den Verifizierungslink der neuen E-Mail ist das Opt-In wieder aktiv
        $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'customer.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->user->id,
                'hash' => sha1('neu@kunde.de'),
            ]
        );
        $this->get($verifyUrl)->assertRedirect(route('login'));
        $this->assertTrue($this->user->fresh()->hasMarketingConsent());

        // 3. Im Profil wieder deaktivieren -> sofortiger Widerruf
        $this->actingAs($this->user->fresh(), 'customer');
        Livewire::test(AuthProfileDropdown::class)
            ->assertSet('marketingConsent', true)
            ->set('marketingConsent', false)
            ->assertSet('marketingConsent', false);

        $this->assertFalse($this->user->fresh()->hasMarketingConsent());
        $this->assertNotNull($this->user->fresh()->profile->cart_reminder_unsubscribed_at);
        $this->assertDatabaseMissing('marketing_newsletter_subscribers', ['email' => 'neu@kunde.de']);
    }
}

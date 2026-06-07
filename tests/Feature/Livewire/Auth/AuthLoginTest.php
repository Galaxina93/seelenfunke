<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\AuthLogin;
use App\Models\Customer\Customer;
use App\Models\System\SystemLoginAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Support\Str;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'test@kunde.de',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => Hash::make('password123'),
        ]);
        
        $this->user->profile->email_verified_at = now();
        $this->user->profile->save();
    }

    public function test_component_mounts_correctly()
    {
        Livewire::test(AuthLogin::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.auth.auth-login')
            ->assertSet('activeView', 'login');
    }

    public function test_login_fails_with_invalid_credentials()
    {
        Livewire::test(AuthLogin::class)
            ->set('email', 'test@kunde.de')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['email' => 'Die eingegebenen Anmeldedaten sind ungültig.']);

        $this->assertDatabaseHas('system_login_attempts', [
            'email' => 'test@kunde.de',
            'success' => false,
        ]);
    }

    public function test_login_succeeds_with_valid_credentials_and_redirects_to_dashboard()
    {
        Livewire::test(AuthLogin::class)
            ->set('email', 'test@kunde.de')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('customer.dashboard'));

        $this->assertDatabaseHas('system_login_attempts', [
            'email' => 'test@kunde.de',
            'success' => true,
        ]);

        $this->assertAuthenticatedAs($this->user, 'customer');
    }

    public function test_login_fails_if_email_is_unverified()
    {
        $unverifiedUser = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'unverified@kunde.de',
            'first_name' => 'Unverified',
            'last_name' => 'User',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        Livewire::test(AuthLogin::class)
            ->set('email', 'unverified@kunde.de')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email' => 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in deinem Postfach.']);
    }

    public function test_rate_limiting_prevents_too_many_attempts()
    {
        $component = Livewire::test(AuthLogin::class);

        for ($i = 0; $i < 6; $i++) {
            $component->set('email', "test{$i}@kunde.de")
                      ->set('password', 'wrong')
                      ->call('login');
        }

        $component->set('email', 'test7@kunde.de')
                  ->set('password', 'wrong')
                  ->call('login')
                  ->assertHasErrors(['email']);
                  
        $this->assertStringContainsString('Langsam!', collect($component->errors()->get('email'))->first());
    }

    public function test_login_lockout_after_three_failed_attempts_lasts_10_seconds()
    {
        // Simulate 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            SystemLoginAttempt::create([
                'email' => 'test@kunde.de',
                'ip_address' => '127.0.0.1',
                'success' => false,
                'created_at' => now(),
            ]);
        }

        $component = Livewire::test(AuthLogin::class)
            ->set('email', 'test@kunde.de')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email']);
            
        $this->assertStringContainsString('Zu viele fehlerhafte Login-Versuche', collect($component->errors()->get('email'))->first());
    }

    public function test_login_redirects_to_password_change_force_if_needed()
    {
        $this->user->update([
            'needs_password_change' => true,
            'temporary_password' => 'temp123',
        ]);

        Livewire::test(AuthLogin::class)
            ->set('email', 'test@kunde.de')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('customer.password-change-force'));
    }
}

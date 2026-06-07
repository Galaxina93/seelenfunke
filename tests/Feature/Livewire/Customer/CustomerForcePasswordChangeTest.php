<?php

namespace Tests\Feature\Livewire\Customer;

use App\Models\Customer\Customer;
use App\Livewire\Customer\CustomerForcePasswordChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Support\Str;

class CustomerForcePasswordChangeTest extends TestCase
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
            'password' => Hash::make('password123'),
            'needs_password_change' => true,
            'temporary_password' => 'temp123',
        ]);
        
        $this->user->profile->email_verified_at = now();
        $this->user->profile->save();
    }

    public function test_guest_is_redirected_to_login_if_not_authenticated()
    {
        $this->get(route('customer.password-change-force'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_redirects_to_password_change_force_if_needed()
    {
        $this->actingAs($this->user, 'customer');

        $this->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.password-change-force'));
    }

    public function test_component_mounts_correctly_for_authenticated_customer_who_needs_change()
    {
        $this->actingAs($this->user, 'customer');

        Livewire::test(CustomerForcePasswordChange::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.customer.customer-force-password-change');
    }

    public function test_component_redirects_to_dashboard_if_password_change_not_needed()
    {
        $this->user->update(['needs_password_change' => false]);
        $this->actingAs($this->user, 'customer');

        Livewire::test(CustomerForcePasswordChange::class)
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_changing_password_saves_new_password_and_clears_flag()
    {
        $this->actingAs($this->user, 'customer');

        Livewire::test(CustomerForcePasswordChange::class)
            ->set('password', 'newSecurePassword123')
            ->set('passwordConfirm', 'newSecurePassword123')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('customer.dashboard'));

        $this->user->refresh();
        $this->assertTrue(Hash::check('newSecurePassword123', $this->user->password));
        $this->assertFalse($this->user->needs_password_change);
        $this->assertNull($this->user->temporary_password);
    }

    public function test_changing_password_fails_if_passwords_do_not_match()
    {
        $this->actingAs($this->user, 'customer');

        Livewire::test(CustomerForcePasswordChange::class)
            ->set('password', 'newSecurePassword123')
            ->set('passwordConfirm', 'differentPassword')
            ->call('submit')
            ->assertHasErrors(['passwordConfirm']);

        $this->user->refresh();
        $this->assertFalse(Hash::check('newSecurePassword123', $this->user->password));
        $this->assertTrue($this->user->needs_password_change);
    }
}

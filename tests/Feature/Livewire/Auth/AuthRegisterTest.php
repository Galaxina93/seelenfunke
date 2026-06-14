<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\AuthRegister;
use App\Models\Customer\Customer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_correctly()
    {
        Livewire::test(AuthRegister::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.auth.auth-register');
    }

    public function test_password_rules_are_evaluated_dynamically()
    {
        Livewire::test(AuthRegister::class)
            ->set('password', 'short')
            ->assertSet('passwordRules.min', false)
            ->assertSet('passwordRules.number', false)
            ->assertSet('passwordRules.upper', false)
            
            ->set('password', 'Valid123')
            ->assertSet('passwordRules.min', true)
            ->assertSet('passwordRules.number', true)
            ->assertSet('passwordRules.upper', true)
            ->assertSet('passwordRules.match', false) // No confirmation yet

            ->set('password_confirmation', 'Valid123')
            ->assertSet('passwordRules.match', true)
            ->assertSet('canRegister', false); // false because strictly empty fields block DB payload
    }

    public function test_successful_registration_creates_customer_and_profile_and_redirects()
    {
        Mail::fake();
        $component = Livewire::test(AuthRegister::class)
            ->set('firstname', 'John')
            ->set('lastname', 'Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'Secret123')
            ->set('street', 'Main St')
            ->set('house_number', '12')
            ->set('postal', '10115')
            ->set('city', 'Berlin')
            ->set('country', 'DE')
            ->set('is_business', 0)
            ->set('birthday', '1990-01-01')
            ->set('terms', true)
            ->call('register');

        $component->assertRedirect(route('login'))
                  ->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $customer = Customer::where('email', 'john@example.com')->first();
        
        $this->assertDatabaseHas('customer_profiles', [
            'customer_id' => $customer->id,
            'street' => 'Main St',
            'city' => 'Berlin',
        ]);
    }

    public function test_registration_succeeds_without_birthday_and_saves_null()
    {
        Mail::fake();
        $component = Livewire::test(AuthRegister::class)
            ->set('firstname', 'John')
            ->set('lastname', 'Doe')
            ->set('email', 'john2@example.com')
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'Secret123')
            ->set('street', 'Main St')
            ->set('house_number', '12')
            ->set('postal', '10115')
            ->set('city', 'Berlin')
            ->set('country', 'DE')
            ->set('is_business', 0)
            ->set('birthday', '')
            ->set('terms', true)
            ->call('register');

        $component->assertRedirect(route('login'))
                  ->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'email' => 'john2@example.com',
        ]);

        $customer = Customer::where('email', 'john2@example.com')->first();
        
        $this->assertDatabaseHas('customer_profiles', [
            'customer_id' => $customer->id,
            'birthday' => null,
        ]);
    }

    public function test_guest_chat_session_is_migrated_to_customer_id_on_register()
    {
        Mail::fake();
        $uuid = \Illuminate\Support\Str::uuid()->toString();
        \App\Models\Support\SupportCustomerChat::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'session_token' => $uuid,
            'status' => 'open'
        ]);

        Livewire::test(AuthRegister::class, ['sessionToken' => $uuid])
            ->set('firstname', 'Jane')
            ->set('lastname', 'Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'Secret123')
            ->set('street', 'Main St')
            ->set('house_number', '12')
            ->set('postal', '10115')
            ->set('city', 'Berlin')
            ->set('country', 'DE')
            ->set('is_business', 0)
            ->set('birthday', '1990-01-01')
            ->set('terms', true)
            ->call('register');

        $customer = Customer::where('email', 'jane@example.com')->first();

        // Ensure sf_chat_uuid linkage occurred!
        $this->assertDatabaseHas('support_customer_chats', [
            'customer_id' => $customer->id,
            'session_token' => $uuid,
        ]);
    }

    public function test_cannot_register_with_disposable_email()
    {
        Mail::fake();
        Livewire::test(AuthRegister::class)
            ->set('firstname', 'John')
            ->set('lastname', 'Doe')
            ->set('email', 'john@mailinator.com')
            ->set('password', 'Secret123')
            ->set('password_confirmation', 'Secret123')
            ->set('street', 'Main St')
            ->set('house_number', '12')
            ->set('postal', '10115')
            ->set('city', 'Berlin')
            ->set('country', 'DE')
            ->set('is_business', 0)
            ->set('birthday', '1990-01-01')
            ->set('terms', true)
            ->call('register')
            ->assertHasErrors(['email' => 'indisposable']);

        $this->assertDatabaseMissing('customers', [
            'email' => 'john@mailinator.com',
        ]);
    }
}

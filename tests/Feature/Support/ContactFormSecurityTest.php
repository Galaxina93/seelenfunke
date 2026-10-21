<?php

namespace Tests\Feature\Support;

use App\Models\Support\SupportContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ContactFormSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_honeypot_silently_discards_bot_submissions(): void
    {
        Mail::fake();

        Livewire::test(\App\Livewire\Frontend\ContactForm::class)
            ->set('first_name', 'Bot')
            ->set('last_name', 'Spam')
            ->set('email', 'bot@spam.com')
            ->set('message', 'Buy cheap crypto now!')
            ->set('data_protection', true)
            ->set('honey_name', 'Hidden bot input filled')
            ->call('sending')
            ->assertHasNoErrors();

        // No database records should be created
        $this->assertEquals(0, SupportContactRequest::count());
        // No mail should be sent
        Mail::assertNothingSent();
    }

    public function test_contact_form_validates_and_stores_legitimate_inquiries(): void
    {
        Mail::fake();

        Livewire::test(\App\Livewire\Frontend\ContactForm::class)
            ->set('first_name', 'Erika')
            ->set('last_name', 'Musterfrau')
            ->set('email', 'erika@example.com')
            ->set('message', 'Ich habe eine Frage zu Ihren Produkten.')
            ->set('data_protection', true)
            ->call('sending')
            ->assertHasNoErrors();

        $this->assertEquals(1, SupportContactRequest::count());
        $this->assertDatabaseHas('support_contact_requests', [
            'email' => 'erika@example.com',
            'first_name' => 'Erika',
        ]);
    }
}

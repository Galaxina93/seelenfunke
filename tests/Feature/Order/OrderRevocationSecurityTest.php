<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderRevocationForm;
use App\Mail\RevocationMailToCustomer;
use App\Models\Order\OrderRevocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class OrderRevocationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('order-revocation:127.0.0.1');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_silently_drops_spambot_submissions_when_honeypot_is_filled(): void
    {
        Mail::fake();

        Livewire::test(OrderRevocationForm::class)
            ->set('name', 'Bot Spammer')
            ->set('email', 'bot@spam.com')
            ->set('order_number', 'ORD-SPAM-999')
            ->set('honey_name', 'I am a robot')
            ->call('submitRevocation')
            ->assertSet('isSubmitted', true);

        Mail::assertNothingQueued();
        $this->assertDatabaseMissing('order_revocations', [
            'email' => 'bot@spam.com',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_successfully_processes_legitimate_revocation(): void
    {
        Mail::fake();

        Livewire::test(OrderRevocationForm::class)
            ->set('name', 'Erika Mustermann')
            ->set('email', 'erika@example.com')
            ->set('order_number', 'ORD-2026-1234')
            ->set('honey_name', '')
            ->call('submitRevocation')
            ->assertHasNoErrors()
            ->assertSet('isSubmitted', true);

        Mail::assertQueued(RevocationMailToCustomer::class);
        $this->assertDatabaseHas('order_revocations', [
            'email' => 'erika@example.com',
            'order_number' => 'ORD-2026-1234',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rate_limits_rapid_revocation_submissions(): void
    {
        Mail::fake();

        $component = Livewire::test(OrderRevocationForm::class)
            ->set('name', 'Spam Sender')
            ->set('order_number', 'ORD-THROTTLE-1');

        for ($i = 0; $i < 5; $i++) {
            $component->set('email', "spam{$i}@example.com")
                ->call('submitRevocation')
                ->assertHasNoErrors();
        }

        // 6th attempt should be blocked by RateLimiter
        $component->set('email', 'spam6@example.com')
            ->call('submitRevocation')
            ->assertHasErrors(['email']);
    }
}

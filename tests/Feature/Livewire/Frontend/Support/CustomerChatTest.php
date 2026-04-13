<?php

namespace Tests\Feature\Livewire\Frontend\Support;

use App\Livewire\Frontend\Support\CustomerChat;
use App\Models\Customer\Customer;
use App\Models\Support\SupportCustomerChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Prevent real API calls to Gemini or Mittwald during tests
        Http::fake([
            '*' => Http::response(['message' => 'mocked'], 200)
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_the_chat_window_and_claims_it_for_logged_in_users()
    {
        // 1. Create a guest chat
        $chat = SupportCustomerChat::create([
            'status' => 'open',
            'session_token' => 'test-session-123'
        ]);
        Session::put('current_chat_id', $chat->id);

        $customer = Customer::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password')
        ]);

        // 2. Auth customer visits the chat
        $this->actingAs($customer, 'customer');

        Livewire::test(CustomerChat::class)
            ->assertSet('chatId', $chat->id)
            ->assertSet('isResolved', false);

        // 3. Verify it was claimed
        $this->assertEquals($customer->id, $chat->fresh()->customer_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_triggers_freemium_restriction_for_guests()
    {
        // Guest sends messages (max limit is usually around 5, but let's test if we hit it)
        // For testing we will just verify the property or method logic if applicable.
        $chat = SupportCustomerChat::create([
            'status' => 'open',
            'session_token' => 'test-limit'
        ]);
        
        // Simuliere 5 Nachrichten (Das Limit liegt im Controller bei 5)
        for ($i = 0; $i < 5; $i++) {
            \App\Models\Support\SupportCustomerChatMessage::create([
                'support_customer_chat_id' => $chat->id,
                'sender' => 'customer',
                'message' => "Message $i"
            ]);
        }

        Session::put('current_chat_id', $chat->id);

        Livewire::test(CustomerChat::class)
            ->set('message', 'This is message 6')
            ->call('sendMessage')
            ->assertSee('Sichere deinen Fortschritt!');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_rating_widget_if_chat_is_resolved_by_ai()
    {
        $chat = SupportCustomerChat::create(['status' => 'resolved']);
        Session::put('current_chat_id', $chat->id);

        Livewire::test(CustomerChat::class)
            ->assertSet('isResolved', true)
            ->assertSee('heute geholfen?');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_rating_widget_if_chat_is_resolved_by_admin()
    {
        $chat = SupportCustomerChat::create(['status' => 'resolved_admin']);
        Session::put('current_chat_id', $chat->id);

        Livewire::test(CustomerChat::class)
            ->assertSet('isResolved', true)
            ->assertSee('heute geholfen?');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_rating_widget_if_chat_is_resolved_via_auto_timeout()
    {
        $chat = SupportCustomerChat::create(['status' => 'resolved_auto']);
        Session::put('current_chat_id', $chat->id);

        Livewire::test(CustomerChat::class)
            ->assertSet('isResolved', true)
            ->assertSee('heute geholfen?');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_submits_rating_properly()
    {
        $chat = SupportCustomerChat::create(['status' => 'resolved']);
        Session::put('current_chat_id', $chat->id);

        Livewire::test(CustomerChat::class)
            ->set('rating', 5)
            ->set('feedbackText', 'Great bot!')
            ->call('submitRating')
            ->assertSet('ratingSubmitted', true);

        $freshChat = $chat->fresh();
        $this->assertEquals(5, $freshChat->rating);
        $this->assertEquals('Great bot!', $freshChat->feedback_text);
    }
}

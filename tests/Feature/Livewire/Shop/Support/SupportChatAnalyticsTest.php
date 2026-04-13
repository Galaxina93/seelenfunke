<?php

namespace Tests\Feature\Livewire\Shop\Support;

use App\Livewire\Shop\Support\SupportChatAnalytics;
use App\Models\Support\SupportCustomerChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupportChatAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_the_analytics_view_and_computes_kpis_correctly()
    {
        SupportCustomerChat::create(['status' => 'open']);
        SupportCustomerChat::create(['status' => 'open']);
        SupportCustomerChat::create(['status' => 'needs_employee']);
        SupportCustomerChat::create(['status' => 'resolved']);
        SupportCustomerChat::create(['status' => 'resolved_admin']);
        SupportCustomerChat::create(['status' => 'resolved_admin']);
        SupportCustomerChat::create(['status' => 'resolved_auto']);

        Livewire::test(SupportChatAnalytics::class)
            ->assertStatus(200)
            ->assertViewHas('openCount', 2)
            ->assertViewHas('needsEmployeeCount', 1)
            ->assertViewHas('resolvedCount', 1)
            ->assertViewHas('resolvedAdminCount', 2)
            ->assertViewHas('resolvedAutoCount', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_mark_a_chat_as_resolved_by_admin()
    {
        $chat = SupportCustomerChat::create([
            'status' => 'open'
        ]);

        Livewire::test(SupportChatAnalytics::class)
            ->call('markAsResolved', $chat->id)
            ->assertOk();

        $this->assertEquals('resolved_admin', $chat->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_chats_by_status()
    {
        $openChat = SupportCustomerChat::create(['status' => 'open', 'ai_summary' => 'Open chat summary']);
        $autoChat = SupportCustomerChat::create(['status' => 'resolved_auto', 'ai_summary' => 'Auto chat summary']);

        Livewire::test(SupportChatAnalytics::class)
            ->set('statusFilter', 'resolved_auto')
            ->assertSee('Auto chat summary')
            ->assertDontSee('Open chat summary');
    }
}

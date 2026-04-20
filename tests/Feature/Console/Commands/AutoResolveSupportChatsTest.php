<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Support\SupportCustomerChat;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoResolveSupportChatsTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_open_chats_older_than_12_hours()
    {
        $customer = Customer::factory()->create();

        // 13 Stunden alter offener Chat
        $oldChat = SupportCustomerChat::create([
            'customer_id' => $customer->id,
            'status' => 'open',
            'updated_at' => now()->subHours(13),
            'created_at' => now()->subHours(13),
        ]);

        // 13 Stunden alter eskalierter Chat
        $needsEmployeeChat = SupportCustomerChat::create([
            'customer_id' => $customer->id,
            'status' => 'needs_employee',
            'updated_at' => now()->subHours(13),
            'created_at' => now()->subHours(13),
        ]);

        $this->artisan('support:auto-resolve-chats')
            ->expectsOutputToContain('Auto-resolved 2 inactive chats.')
            ->assertExitCode(0);

        $this->assertEquals('resolved_auto', $oldChat->fresh()->status);
        $this->assertEquals('resolved_auto', $needsEmployeeChat->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_resolve_active_chats_newer_than_12_hours()
    {
        $customer = Customer::factory()->create();

        $recentChat = SupportCustomerChat::create([
            'customer_id' => $customer->id,
            'status' => 'open',
            'updated_at' => now()->subHours(11), // Nur 11h alt
            'created_at' => now()->subHours(11),
        ]);

        $this->artisan('support:auto-resolve-chats')
            ->expectsOutputToContain('Auto-resolved 0 inactive chats.')
            ->assertExitCode(0);

        $this->assertEquals('open', $recentChat->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_leaves_already_resolved_chats_untouched()
    {
        $customer = Customer::factory()->create();

        $resolvedChat = SupportCustomerChat::create([
            'customer_id' => $customer->id,
            'status' => 'resolved',
            'updated_at' => now()->subHours(15), 
            'created_at' => now()->subHours(15),
        ]);

        $resolvedAdminChat = SupportCustomerChat::create([
            'customer_id' => $customer->id,
            'status' => 'resolved_admin',
            'updated_at' => now()->subHours(15),
            'created_at' => now()->subHours(15),
        ]);

        $this->artisan('support:auto-resolve-chats')
            ->expectsOutputToContain('Auto-resolved 0 inactive chats.')
            ->assertExitCode(0);

        $this->assertEquals('resolved', $resolvedChat->fresh()->status);
        $this->assertEquals('resolved_admin', $resolvedAdminChat->fresh()->status);
    }
}

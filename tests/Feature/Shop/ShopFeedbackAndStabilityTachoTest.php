<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ShopFeedbackAndStabilityTachoTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_feedback_banner_renders_clean_light_mode_for_guests_and_authenticated_customers(): void
    {
        $guestHtml = Blade::render('<x-shop.customer-feedback-banner />');

        $this->assertStringContainsString('data-customer-feedback-banner="true"', $guestHtml);
        $this->assertStringContainsString('Dein ehrliches Feedback macht uns jeden Tag besser', $guestHtml);
        $this->assertStringContainsString('Feedback oder Idee senden', $guestHtml);
        $this->assertStringNotContainsString(" \u{2013} ", $guestHtml);
        $this->assertStringNotContainsString('bg-amber', $guestHtml);

        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($customer, 'customer');

        $customerHtml = Blade::render('<x-shop.customer-feedback-banner />');
        $this->assertStringContainsString('Feedback &amp; Erfahrung teilen', $customerHtml);
        $this->assertStringContainsString(route('customer.reviews'), $customerHtml);
    }

    public function test_stability_tacho_enforces_strict_under_100_percent_invariant_whenever_items_are_open(): void
    {
        // 0 offene Punkte -> exakt 100 % Stabil
        $greenHtml = Blade::render('<x-backend.stability-tacho :score="100" :openCount="0" />');
        $this->assertStringContainsString('data-stability-score="100"', $greenHtml);
        $this->assertStringContainsString('100 % Stabil', $greenHtml);

        // Selbst wenn Roh-Score rechnerisch 100 übergeben würde, deckelt openCount > 0 hart auf <= 99 %!
        $attentionHtml = Blade::render('<x-backend.stability-tacho :score="100" :openCount="1" :pillars="[[\'label\' => \'Service\', \'count\' => 1]]" />');
        $this->assertStringContainsString('data-stability-score="99"', $attentionHtml);
        $this->assertStringContainsString('1 Punkt offen', $attentionHtml);
        $this->assertStringContainsString('Service:', $attentionHtml);
    }

    public function test_master_analytics_syncs_waiting_for_alina_universe_tickets_into_operative_health_checks(): void
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-tacho-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);

        AiUniverseTicket::create([
            'ticket_number' => 'SF-9501',
            'title' => '[Konzept] Offene Freigabe für Testlauf',
            'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            'priority' => 'high',
            'ticket_type' => 'antigravity_dev',
            'source_channel' => 'web_portal',
            'environment_tier' => 'LOCAL',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($admin, 'admin');

        $component = Livewire::test(MasterAnalytics::class);
        $checks = $component->get('healthChecks');

        $this->assertArrayHasKey('universe_waiting_alina', $checks);
        $this->assertSame(1, $checks['universe_waiting_alina']['count']);
        $this->assertSame('warning', $checks['universe_waiting_alina']['status']);
    }
}

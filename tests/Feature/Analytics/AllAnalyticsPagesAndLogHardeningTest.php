<?php

namespace Tests\Feature\Analytics;

use App\Livewire\Shop\Ai\AiAnalytics;
use App\Livewire\Shop\Marketing\MarketingAnalytics;
use App\Livewire\Shop\Order\OrderAnalytics;
use App\Livewire\Shop\Product\ProductAnalytics;
use App\Livewire\Shop\Support\SupportAnalytics;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiMetric;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Marketing\MarketingLinktreeVisit;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderShipment;
use App\Models\Product\Product;
use App\Models\Product\ProductAnalyticsEvent;
use App\Models\Support\SupportCustomerChat;
use App\Models\Support\SupportTicket;
use App\Models\Tracking\PageVisit;
use App\Services\System\LogArchiveService;
use App\Services\System\SystemErrorLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AllAnalyticsPagesAndLogHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_five_admin_analytics_http_routes_render_cleanly_with_balanced_dom(): void
    {
        $admin = Admin::factory()->create();

        $urls = [
            '/admin/support/analytics',
            '/admin/product-analytics',
            '/admin/marketing/analytics',
            '/admin/orders/analytics',
            '/admin/ai/analytics',
        ];

        foreach ($urls as $url) {
            $res = $this->actingAs($admin, 'admin')->followingRedirects()->get($url);
            $res->assertStatus(200);
            $html = $res->getContent();
            $this->assertStringNotContainsString('solar-wallet-money', $html, "Broken solar icon found on {$url}");
            $this->assertStringNotContainsString('fa-solid ', $html, "Broken fa-solid icon found on {$url}");
            $this->assertStringNotContainsString('bi bi-', $html, "Broken bootstrap icon found on {$url}");
        }
    }

    public function test_external_influencer_and_customer_are_blocked_from_all_analytics_components(): void
    {
        $customer = Customer::factory()->create();
        $influencer = Employee::create([
            'first_name' => 'Creator',
            'last_name' => 'Test',
            'email' => 'creator-sec@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'influencer',
        ]);

        $components = [
            SupportAnalytics::class,
            ProductAnalytics::class,
            MarketingAnalytics::class,
            OrderAnalytics::class,
            AiAnalytics::class,
        ];

        foreach ($components as $comp) {
            Livewire::actingAs($customer, 'customer')->test($comp)->assertStatus(403);
            Livewire::actingAs($influencer, 'employee')->test($comp)->assertStatus(403);
        }
    }

    public function test_marketing_analytics_counts_landing_page_visits_without_leading_slash_and_sanitizes_date_range(): void
    {
        $admin = Admin::factory()->create();

        MarketingLandingPage::create([
            'title' => 'Muttertag Kristall-Aktion',
            'slug' => 'muttertag-kristall',
            'status' => 'published',
        ]);

        // Laravel $request->path() speichert ohne führenden '/' -> 'l/muttertag-kristall'
        PageVisit::create([
            'session_id' => 'sess_v1',
            'ip_hash' => hash('sha256', 'ip_v1'),
            'url' => 'http://127.0.0.1/l/muttertag-kristall',
            'path' => 'l/muttertag-kristall',
            'method' => 'GET',
            'created_at' => now()->subDay(),
        ]);
        PageVisit::create([
            'session_id' => 'sess_v2',
            'ip_hash' => hash('sha256', 'ip_v2'),
            'url' => 'http://127.0.0.1/l/muttertag-kristall',
            'path' => '/l/muttertag-kristall',
            'method' => 'GET',
            'created_at' => now()->subHours(3),
        ]);

        MarketingLinktreeVisit::create([
            'ip_hash' => hash('sha256', 'lt1'),
            'device_type' => 'mobile',
        ]);

        MarketingNewsletterSubscriber::create([
            'email' => 'sub@example.com',
            'is_verified' => true,
        ]);

        MarketingVoucher::create([
            'title' => 'Frühlingsfunken',
            'code' => 'FUNKE10',
            'type' => 'percent',
            'value' => 10,
            'mode' => 'manual',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->withQueryParams(['dateRange' => 'malicious_sql'])
            ->test(MarketingAnalytics::class)
            ->assertSet('dateRange', '30')
            ->assertSet('kpiVerifiedSubscribers', 1)
            ->assertSet('kpiActiveVouchers', 1)
            ->assertSet('kpiCampaignVisits', 3); // 2 Landingpage + 1 Linktree

        $topPages = $component->get('topLandingPages');
        $this->assertCount(1, $topPages);
        $this->assertSame('Muttertag Kristall-Aktion', $topPages[0]['title']);
        $this->assertSame('/l/muttertag-kristall', $topPages[0]['path']);
        $this->assertSame(2, $topPages[0]['views']);

        $component->set('dateRange', '365')->assertSet('dateRange', '365');
    }

    public function test_support_analytics_translates_statuses_and_computes_fractional_resolution_hours(): void
    {
        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create();

        $createdAt = now()->subHours(3);
        $closedAt = $createdAt->copy()->addMinutes(90); // 1.5 Stunden

        $ticket = SupportTicket::create([
            'ticket_number' => 'MSF-ANL-100',
            'customer_id' => $customer->id,
            'subject' => 'Gravur Rückfrage',
            'category' => 'support',
            'status' => 'closed',
            'priority' => 'normal',
            'rating' => 5,
        ]);
        SupportTicket::whereKey($ticket->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $closedAt,
        ]);

        SupportTicket::create([
            'ticket_number' => 'MSF-ANL-101',
            'customer_id' => $customer->id,
            'subject' => 'Offene Rückfrage',
            'category' => 'support',
            'status' => 'in_progress',
            'priority' => 'normal',
        ]);

        SupportCustomerChat::create([
            'session_token' => Str::random(32),
            'status' => 'resolved_auto',
            'rating' => 4,
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(SupportAnalytics::class)
            ->assertSet('kpiTicketsOpen', 1)
            ->assertSet('kpiTicketsClosed', 1)
            ->assertSet('kpiAvgResolutionHours', 1.5)
            ->assertSet('kpiAvgTicketRating', 5.0)
            ->assertSet('kpiAvgChatRating', 4.0);

        $this->assertContains('In Bearbeitung', $component->get('ticketStatusData')['labels']);
        $this->assertContains('Geschlossen', $component->get('ticketStatusData')['labels']);
        $this->assertContains('KI gelöst', $component->get('chatStatusData')['labels']);
    }

    public function test_order_analytics_excludes_cancelled_from_paid_metrics_and_tracks_returning_customers_without_n_plus_one(): void
    {
        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create(['email' => 'stammkunde@example.com']);

        // Frühere Bestellung vor 45 Tagen (macht die heutige Bestellung zum Wiederkauf!)
        $oldOrder = OrderOrder::create([
            'order_number' => 'ORD-OLD-001',
            'customer_id' => $customer->id,
            'email' => 'stammkunde@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Anna', 'last_name' => 'Funken', 'email' => 'stammkunde@example.com', 'company' => 'Funken GmbH'],
            'shipping_address' => ['first_name' => 'Anna', 'last_name' => 'Funken'],
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'total_price' => 4990,
        ]);
        OrderOrder::whereKey($oldOrder->id)->update(['created_at' => now()->subDays(45)]);

        // Aktuelle bezahlte B2B-Bestellung im 30-Tage-Fenster
        $recentCreated = now()->subHours(6);
        $recentOrder = OrderOrder::create([
            'order_number' => 'ORD-NEW-002',
            'customer_id' => $customer->id,
            'email' => 'stammkunde@example.com',
            'status' => 'shipped',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Anna', 'last_name' => 'Funken', 'email' => 'stammkunde@example.com', 'company' => 'Funken GmbH'],
            'shipping_address' => ['first_name' => 'Anna', 'last_name' => 'Funken'],
            'subtotal_price' => 8000,
            'tax_amount' => 1597,
            'total_price' => 10000,
        ]);
        OrderOrder::whereKey($recentOrder->id)->update(['created_at' => $recentCreated, 'updated_at' => now()]);

        $bestsellerProd = Product::create([
            'name' => 'Sonderanfertigung Kristallherz',
            'slug' => 'sonder-kristallherz-' . Str::random(5),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
        ]);

        OrderOrderItem::create([
            'order_id' => $recentOrder->id,
            'product_id' => $bestsellerProd->id,
            'product_name' => 'Sonderanfertigung Kristallherz',
            'quantity' => 3,
            'unit_price' => 3000,
            'total_price' => 9000,
        ]);

        $shipment = OrderShipment::create([
            'order_id' => $recentOrder->id,
            'tracking_number' => 'DHL123456',
            'carrier' => 'DHL',
            'status' => 'shipped',
        ]);
        OrderShipment::whereKey($shipment->id)->update(['created_at' => $recentCreated->copy()->addHours(2)]);

        // Stornierte Bestellung (trotz payment_status='paid' darf sie NICHT in bezahlte Umsätze/B2B einfließen!)
        OrderOrder::create([
            'order_number' => 'ORD-CANCEL-003',
            'email' => 'storno@example.com',
            'status' => 'cancelled',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Storno', 'email' => 'storno@example.com'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Storno'],
            'subtotal_price' => 5000,
            'tax_amount' => 956,
            'total_price' => 5990,
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(OrderAnalytics::class)
            ->assertSet('kpiPaidOrdersCount', 1)
            ->assertSet('kpiGrossRevenueEuro', 100.0)
            ->assertSet('kpiAvgOrderValueEuro', 100.0)
            ->assertSet('kpiCancelRevokeRate', 50.0);

        $this->assertSame([0, 1], $component->get('b2bChartData')['data']); // 0 B2C, 1 B2B
        $this->assertSame([1, 0], $component->get('retentionData')['data']); // 1 Wiederkehrend, 0 Neukunde
        $this->assertSame([2.0], $component->get('processingTimeData')['data']); // Exakt 2.0h bis DHL-Shipment!
        $this->assertContains(3, $component->get('bestsellerData')['data']);
    }

    public function test_product_analytics_uses_real_booked_express_revenue_instead_of_toggle_spam(): void
    {
        $admin = Admin::factory()->create();

        // Besucher klickt 5x auf den Express-Schalter hin und her, kauft aber nur 1x für 9,90 €
        for ($i = 0; $i < 5; $i++) {
            ProductAnalyticsEvent::create([
                'session_hash' => hash('sha256', 'spam_toggle'),
                'source' => 'configurator',
                'event_name' => 'express_toggled',
                'item_identifier' => 'enabled',
                'value_cents' => 990,
                'created_at' => now(),
            ]);
        }

        OrderOrder::create([
            'order_number' => 'ORD-EXP-001',
            'email' => 'express@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'is_express' => true,
            'express_price' => 990,
            'billing_address' => ['first_name' => 'Eilig', 'last_name' => 'Kunde'],
            'shipping_address' => ['first_name' => 'Eilig', 'last_name' => 'Kunde'],
            'subtotal_price' => 2990,
            'tax_amount' => 635,
            'total_price' => 3980,
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(ProductAnalytics::class)
            ->assertOk();

        $expressStats = $component->get('expressStats');
        $this->assertSame(5, $expressStats['enabled_clicks']);
        $this->assertSame(9.90, $expressStats['revenue_euros']); // Real 9,90 €, NICHT 49,50 €!
    }

    public function test_ai_analytics_date_range_filter_updates_telemetry_window(): void
    {
        $admin = Admin::factory()->create();

        $agent = AiAgent::create([
            'name' => 'Zeitraum Test Agent',
            'provider' => 'google',
            'model' => 'gemini-2.5-flash',
            'system_prompt' => 'Test',
        ]);

        $oldMetric = AiMetric::create([
            'id' => Str::uuid()->toString(),
            'ai_agent_id' => $agent->id,
            'type' => 'inference',
            'input_tokens' => 400,
            'output_tokens' => 200,
            'total_time_ms' => 900,
            'is_success' => true,
        ]);
        AiMetric::whereKey($oldMetric->id)->update(['created_at' => now()->subDays(20)]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAnalytics::class)
            ->assertSet('dateRange', '30')
            ->assertSee('Zeitraum Test Agent')
            ->set('dateRange', '7')
            ->assertDontSee('Zeitraum Test Agent')
            ->assertDispatched('ai-analytics-updated');
    }

    public function test_laser_cockpit_partial_and_log_hardening_do_not_crash_outside_livewire_context(): void
    {
        $product = Product::create([
            'name' => 'Kristallglas Test',
            'slug' => 'kristallglas-partial-' . Str::random(5),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-PARTIAL-1',
            'email' => 'partial@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Test', 'last_name' => 'User'],
            'shipping_address' => ['first_name' => 'Test', 'last_name' => 'User'],
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'total_price' => 2990,
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2990,
            'total_price' => 2990,
        ]);

        // Rendern ohne Livewire-$this-Kontext warf zuvor "Using $this when not in object context"!
        $html = view('livewire.shop.order.order-offer-detail-partials.laser-cockpit-card', [
            'previewItem' => $item,
            'isDigitalItem' => false,
            'isService' => false,
        ])->render();
        $this->assertIsString($html);

        // LogArchiveService ignoriert einmalige CLI-Stdin-Aufrufe ("Standard input code" / "Command line code")
        $stdinLine = '[2026-11-22 12:00:00] local.ERROR: Undefined array key "appconnect_time" {"exception":"[object] (ErrorException(code: 0): Undefined array key \"appconnect_time\" at Standard input code:45)"}';
        $this->assertNull(LogArchiveService::extractLogErrorLine('laravel.log', $stdinLine));

        // Echte Anwendungsfehler werden weiterhin zuverlässig erkannt!
        $realLine = '[2026-11-22 12:00:01] local.ERROR: Division by zero {"exception":"[object] (DivisionByZeroError(code: 0): Division by zero at /var/www/html/app/Services/PriceCalcService.php:42)"}';
        $this->assertNotNull(LogArchiveService::extractLogErrorLine('laravel.log', $realLine));

        $diag = SystemErrorLogger::diagnose('Undefined array key "appconnect_time"', 'ErrorException', 'Standard input code');
        $this->assertSame('cli_stdin_eval_error', $diag['category']);
    }
}

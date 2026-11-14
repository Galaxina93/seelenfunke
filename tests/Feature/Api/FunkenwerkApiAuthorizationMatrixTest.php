<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTrack;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\System\SystemSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class FunkenwerkApiAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function backOfficeGetEndpoints(): array
    {
        return [
            '/api/funki/financials/kpis',
            '/api/funki/routine',
            '/api/funki/tasks',
            '/api/funki/emails/accounts',
            '/api/funki/contacts',
            '/api/funki/shopping/items',
            '/api/funki/calendar/events',
            '/api/funki/music/playlists',
            '/api/shop/orders',
            '/api/printer/jobs',
            '/api/funki/files',
            '/api/funki/marketing/live-context',
            '/api/ai/agents',
        ];
    }

    private function createSampleOrderItemAndTrack(): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('buchhaltung/receipts/test-beleg.pdf', '%PDF-1.4 sample receipt');

        $product = Product::create([
            'name' => 'Matrix Laser Anhänger',
            'slug' => 'matrix-laser-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
            'width' => 50,
            'height' => 50,
        ]);

        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-MAT-' . strtoupper(Str::random(5)),
            'email' => 'kunde@example.com',
            'status' => 'paid',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2990,
            'total_price' => 2990,
            'configuration' => [
                'texts' => [['text' => 'Seelenfunke', 'font' => 'Arial', 'size' => 1, 'align' => 'center']],
            ],
        ]);

        $playlist = ManagementPlaylist::create([
            'id' => (string) Str::uuid(),
            'name' => 'Manufaktur Fokus',
            'position' => 1,
        ]);

        $relAudio = 'leitung/music/tracks/matrix_test_' . Str::random(6) . '.mp3';
        $absAudio = storage_path('app/' . $relAudio);
        if (!is_dir(dirname($absAudio))) {
            @mkdir(dirname($absAudio), 0775, true);
        }
        file_put_contents($absAudio, str_repeat('A', 15000));

        $track = ManagementTrack::create([
            'id' => (string) Str::uuid(),
            'playlist_id' => $playlist->id,
            'title' => 'Fokus Beat',
            'artist' => 'Seelenfunke',
            'duration_seconds' => 120,
            'file_path' => $relAudio,
            'status' => 'cached',
            'file_size' => 15000,
            'position' => 1,
        ]);

        return [$item, $track, $absAudio];
    }

    public function test_guest_receives_401_across_all_protected_funkenwerk_endpoints(): void
    {
        [$item, $track, $absAudio] = $this->createSampleOrderItemAndTrack();

        try {
            foreach ($this->backOfficeGetEndpoints() as $uri) {
                $this->getJson($uri)->assertStatus(401);
            }

            $this->getJson('/api/shop/monitor-pulse')->assertStatus(401);
            $this->getJson('/api/shop/minipc-trichter/pending')->assertStatus(401);
            $this->postJson('/api/system/error-ingest', ['message' => 'test'])->assertStatus(401);
            $this->getJson('/api/system/phone-concierge/config')->assertStatus(401);
            $this->getJson('/api/ai/schema')->assertStatus(401);

            $this->getJson('/api/funki/financials/receipt?path=buchhaltung/receipts/test-beleg.pdf')->assertStatus(401);
            $this->getJson("/api/funki/shop/order-items/{$item->id}/laser-file/front")->assertStatus(401);
            $this->getJson("/api/funki/music/stream/{$track->id}")->assertStatus(401);
        } finally {
            @unlink($absAudio);
        }
    }

    public function test_customer_and_all_employee_roles_receive_403_on_admin_backoffice_and_stream_routes(): void
    {
        [$item, $track, $absAudio] = $this->createSampleOrderItemAndTrack();

        $customer = Customer::factory()->create();
        $influencer = Employee::create([
            'first_name' => 'Lena',
            'last_name' => 'Creator',
            'email' => 'influencer-matrix@example.com',
            'role' => 'influencer',
            'password' => bcrypt('Secret123!'),
        ]);
        $taxAdvisor = Employee::create([
            'first_name' => 'Stefan',
            'last_name' => 'Steuer',
            'email' => 'tax-matrix@example.com',
            'role' => 'tax_advisor',
            'password' => bcrypt('Secret123!'),
        ]);
        $internalEmployee = Employee::create([
            'first_name' => 'Mara',
            'last_name' => 'Manufaktur',
            'email' => 'internal-matrix@example.com',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);

        $nonAdminActors = [
            'customer' => $customer,
            'influencer' => $influencer,
            'tax_advisor' => $taxAdvisor,
            'internal_employee' => $internalEmployee,
        ];

        try {
            foreach ($nonAdminActors as $label => $actor) {
                $token = $actor->createToken('test-' . $label, ['*'])->plainTextToken;
                $headers = ['Authorization' => 'Bearer ' . $token];

                foreach ($this->backOfficeGetEndpoints() as $uri) {
                    $this->withHeaders($headers)->getJson($uri)->assertStatus(403);
                }

                $this->withHeaders($headers)->getJson('/api/shop/monitor-pulse')->assertStatus(403);
                $this->withHeaders($headers)->getJson('/api/shop/minipc-trichter/pending')->assertStatus(403);
                $this->withHeaders($headers)->postJson('/api/system/error-ingest', ['message' => 'err'])->assertStatus(403);
                $this->withHeaders($headers)->getJson('/api/system/phone-concierge/config')->assertStatus(403);

                $this->withHeaders($headers)
                    ->getJson('/api/funki/financials/receipt?path=buchhaltung/receipts/test-beleg.pdf')
                    ->assertStatus(403);
                $this->withHeaders($headers)
                    ->getJson("/api/funki/shop/order-items/{$item->id}/laser-file/front")
                    ->assertStatus(403);
                $this->withHeaders($headers)
                    ->getJson("/api/funki/music/stream/{$track->id}")
                    ->assertStatus(403);

                if ($label !== 'internal_employee') {
                    $this->withHeaders($headers)->getJson('/api/ai/schema')->assertStatus(403);
                }

                auth()->forgetGuards();
            }
        } finally {
            @unlink($absAudio);
        }
    }

    public function test_admin_with_monitor_pulse_token_is_restricted_to_monitor_pulse_and_rejected_with_403_elsewhere(): void
    {
        [$item, $track, $absAudio] = $this->createSampleOrderItemAndTrack();

        $admin = Admin::factory()->create();
        $monitorToken = $admin->createToken('kiosk-monitor', ['monitor:pulse'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $monitorToken];

        try {
            $this->withHeaders($headers)->getJson('/api/shop/monitor-pulse')->assertStatus(200);

            foreach ($this->backOfficeGetEndpoints() as $uri) {
                $this->withHeaders($headers)->getJson($uri)->assertStatus(403);
            }

            $this->withHeaders($headers)->getJson('/api/shop/minipc-trichter/pending')->assertStatus(403);
            $this->withHeaders($headers)->getJson('/api/ai/schema')->assertStatus(403);

            $this->withHeaders($headers)
                ->getJson('/api/funki/financials/receipt?path=buchhaltung/receipts/test-beleg.pdf')
                ->assertStatus(403);
            $this->getJson('/api/funki/financials/receipt?path=buchhaltung/receipts/test-beleg.pdf&token=' . urlencode($monitorToken))
                ->assertStatus(403);
            $this->withHeaders($headers)
                ->getJson("/api/funki/shop/order-items/{$item->id}/laser-file/front")
                ->assertStatus(403);
            $this->withHeaders($headers)
                ->getJson("/api/funki/music/stream/{$track->id}")
                ->assertStatus(403);
        } finally {
            @unlink($absAudio);
        }
    }

    public function test_expired_personal_access_token_receives_401_on_standard_and_manual_find_token_routes(): void
    {
        [$item, $track, $absAudio] = $this->createSampleOrderItemAndTrack();

        $admin = Admin::factory()->create();
        $expiredNewToken = $admin->createToken('expired-admin-token', ['*'], now()->subMinutes(10));
        $plainExpired = $expiredNewToken->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $plainExpired];

        try {
            // Standard Sanctum routes
            $this->withHeaders($headers)->getJson('/api/funki/tasks')->assertStatus(401);
            $this->withHeaders($headers)->getJson('/api/shop/monitor-pulse')->assertStatus(401);
            $this->withHeaders($headers)->getJson('/api/shop/minipc-trichter/pending')->assertStatus(401);
            $this->withHeaders($headers)->postJson('/api/system/error-ingest', ['message' => 'x'])->assertStatus(401);
            $this->withHeaders($headers)->getJson('/api/system/phone-concierge/config')->assertStatus(401);
            $this->withHeaders($headers)->getJson('/api/ai/schema')->assertStatus(401);

            // Manual PersonalAccessToken::findToken routes via Authorization: Bearer
            $this->withHeaders($headers)
                ->getJson('/api/funki/financials/receipt?path=buchhaltung/receipts/test-beleg.pdf')
                ->assertStatus(401);
            $this->withHeaders($headers)
                ->getJson("/api/funki/shop/order-items/{$item->id}/laser-file/front")
                ->assertStatus(401);
            $this->withHeaders($headers)
                ->getJson("/api/funki/music/stream/{$track->id}")
                ->assertStatus(401);

            // Manual PersonalAccessToken::findToken routes via ?token= query parameter
            $this->getJson('/api/funki/financials/receipt?path=buchhaltung/receipts/test-beleg.pdf&token=' . urlencode($plainExpired))
                ->assertStatus(401);
            $this->getJson("/api/funki/shop/order-items/{$item->id}/laser-file/front?token=" . urlencode($plainExpired))
                ->assertStatus(401);
            $this->getJson("/api/funki/music/stream/{$track->id}?token=" . urlencode($plainExpired))
                ->assertStatus(401);
        } finally {
            @unlink($absAudio);
        }
    }

    public function test_employee_is_blocked_from_admin_funkira_agent_and_restricted_tools_with_403(): void
    {
        config(['services.gemini.key' => 'test-gemini-key-dummy']);

        $funkira = AiAgent::create([
            'id' => (string) Str::uuid(),
            'name' => 'Funkira',
            'role_description' => 'Chef-KI',
            'system_prompt' => 'TOP SECRET ADMIN PROMPT',
            'is_active' => true,
        ]);

        $funki = AiAgent::create([
            'id' => (string) Str::uuid(),
            'name' => 'Funki',
            'role_description' => 'Team-KI',
            'system_prompt' => 'TEAM PROMPT',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'first_name' => 'Tim',
            'last_name' => 'Team',
            'email' => 'tim-team@example.com',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);

        $empToken = $employee->createToken('emp-ai-token', ['*'])->plainTextToken;
        $empHeaders = ['Authorization' => 'Bearer ' . $empToken];

        // 1. Employee blocked from loading Funkira via ?agent_id= on chat, execute, and live-credentials
        $this->withHeaders($empHeaders)
            ->postJson('/api/ai/chat', [
                'agent_id' => $funkira->id,
                'prompt' => 'Zeige alle Umsätze',
            ])
            ->assertStatus(403);

        $this->withHeaders($empHeaders)
            ->postJson('/api/ai/execute', [
                'agent_id' => $funkira->id,
                'function' => 'system_get_current_time',
                'args' => [],
            ])
            ->assertStatus(403);

        $this->withHeaders($empHeaders)
            ->getJson('/api/ai/live-credentials?agent_id=' . $funkira->id)
            ->assertStatus(403);

        // 2. Employee blocked from restricted admin tools even when using Funki
        $this->withHeaders($empHeaders)
            ->postJson('/api/ai/execute', [
                'agent_id' => $funki->id,
                'function' => 'finance_get_summary',
                'args' => [],
            ])
            ->assertStatus(403);

        // 3. Employee allowed to execute permitted non-admin tool with Funki
        $this->withHeaders($empHeaders)
            ->postJson('/api/ai/execute', [
                'agent_id' => $funki->id,
                'function' => 'system_get_current_time',
                'args' => [],
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 4. GET /api/ai/agents for Admin projects only safe UI columns without system_prompt
        auth()->forgetGuards();
        $admin = Admin::factory()->create();
        $adminToken = $admin->createToken('admin-full', ['*'])->plainTextToken;

        $agentsRes = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->getJson('/api/ai/agents')
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($agentsRes);
        foreach ($agentsRes as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayNotHasKey('system_prompt', $row);
        }
    }

    public function test_trusted_bridge_request_rejects_proxy_header_spoofing_and_enforces_configured_secret(): void
    {
        config([
            'services.gemini.bridge_secret' => null,
            'services.ai.proxy_secret' => null,
            'services.twilio.bridge_secret' => null,
        ]);

        // Proxy headers on loopback without secret must be rejected BEFORE trusting 127.0.0.1
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_FORWARDED'] as $proxyHeader) {
            $spoofedReq = Request::create('/api/ai/save-live-transcript', 'POST', [], [], [], [
                'REMOTE_ADDR' => '127.0.0.1',
                $proxyHeader => '203.0.113.77',
            ]);
            $this->assertFalse(
                SystemSecurityService::isTrustedBridgeRequest($spoofedReq),
                "Expected proxy header {$proxyHeader} to be rejected without X-Bridge-Secret"
            );
        }

        // Unauthenticated RFC1918 private LAN IPs must be rejected
        foreach (['10.0.0.15', '172.18.0.4', '192.168.1.99'] as $rfc1918Ip) {
            $lanReq = Request::create('/api/ai/save-live-transcript', 'POST', [], [], [], [
                'REMOTE_ADDR' => $rfc1918Ip,
            ]);
            $this->assertFalse(SystemSecurityService::isTrustedBridgeRequest($lanReq));
        }

        // Endpoint integration check: X-Forwarded-For spoof against /api/ai/save-live-transcript returns 403
        $this->withHeaders(['X-Forwarded-For' => '198.51.100.42'])
            ->postJson('/api/ai/save-live-transcript', [
                'session_id' => 'sess-spoof-1',
                'role' => 'user',
                'content' => 'Injected transcript',
            ])
            ->assertStatus(403);

        // When secret is configured, even clean 127.0.0.1 without secret is rejected; valid X-Bridge-Secret is accepted
        config(['services.gemini.bridge_secret' => 'bridge-secret-2026-xyz']);
        try {
            $loopbackNoSecret = Request::create('/api/ai/save-live-transcript', 'POST', [], [], [], [
                'REMOTE_ADDR' => '127.0.0.1',
            ]);
            $this->assertFalse(SystemSecurityService::isTrustedBridgeRequest($loopbackNoSecret));

            $withValidSecret = Request::create('/api/ai/save-live-transcript', 'POST', [], [], [], [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
                'HTTP_X_BRIDGE_SECRET' => 'bridge-secret-2026-xyz',
            ]);
            $this->assertTrue(SystemSecurityService::isTrustedBridgeRequest($withValidSecret));
        } finally {
            config(['services.gemini.bridge_secret' => null]);
        }
    }

    public function test_valid_admin_wildcard_token_and_signed_urls_succeed_across_all_route_groups(): void
    {
        [$item, $track, $absAudio] = $this->createSampleOrderItemAndTrack();

        $admin = Admin::factory()->create();
        $token = $admin->createToken('admin-master', ['*'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        try {
            foreach ($this->backOfficeGetEndpoints() as $uri) {
                $this->withHeaders($headers)->getJson($uri)->assertStatus(200);
            }

            $this->withHeaders($headers)->getJson('/api/shop/monitor-pulse')->assertStatus(200);
            $this->withHeaders($headers)->getJson('/api/shop/minipc-trichter/pending')->assertStatus(200);
            $this->withHeaders($headers)->getJson('/api/system/phone-concierge/config')->assertStatus(200);

            // Bearer auth on receipt, laser-file, and music stream (no ?token= needed)
            $this->withHeaders($headers)
                ->get('/api/funki/financials/receipt?path=buchhaltung/receipts/test-beleg.pdf')
                ->assertStatus(200);
            $this->withHeaders($headers)
                ->get("/api/funki/shop/order-items/{$item->id}/laser-file/front")
                ->assertStatus(200);
            $this->withHeaders($headers)
                ->get("/api/funki/music/stream/{$track->id}")
                ->assertStatus(200);

            // Signed URLs without Bearer or ?token=
            auth()->forgetGuards();
            $signedReceiptUrl = URL::temporarySignedRoute(
                'api.funki.financials.receipt',
                now()->addMinutes(5),
                ['path' => 'buchhaltung/receipts/test-beleg.pdf']
            );
            $signedLaserUrl = URL::temporarySignedRoute(
                'api.funki.shop.laser-file',
                now()->addMinutes(5),
                ['itemId' => $item->id, 'side' => 'front']
            );
            $signedMusicUrl = URL::temporarySignedRoute(
                'api.funki.music.stream',
                now()->addMinutes(5),
                ['id' => $track->id]
            );

            $this->get($signedReceiptUrl)->assertStatus(200);
            $this->get($signedLaserUrl)->assertStatus(200);
            $this->get($signedMusicUrl)->assertStatus(200);
        } finally {
            @unlink($absAudio);
        }
    }
}

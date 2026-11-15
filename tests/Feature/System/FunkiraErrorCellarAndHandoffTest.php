<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\System\SystemLog;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\FunkiraSkillPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FunkiraErrorCellarAndHandoffTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalHandoffContent = null;
    private string $handoffPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handoffPath = storage_path('app/private/antigravity_error_handoff.json');
        if (File::exists($this->handoffPath)) {
            $this->originalHandoffContent = File::get($this->handoffPath);
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalHandoffContent !== null) {
            File::ensureDirectoryExists(dirname($this->handoffPath));
            File::put($this->handoffPath, $this->originalHandoffContent);
        } elseif (File::exists($this->handoffPath)) {
            File::delete($this->handoffPath);
        }
        parent::tearDown();
    }

    public function test_cellar_snapshot_maps_error_core_file_neighbors_and_clusters(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['ai:funkira']);

        SystemLog::create([
            'type' => 'system',
            'action_id' => 'checkout:exception',
            'title' => 'Checkout Berechnung fehlgeschlagen',
            'message' => 'Call to a member function getPrice() on null in app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php:142',
            'status' => 'error',
            'payload' => [
                'file' => 'app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php',
                'line' => 142,
                'occurrences_count' => 4,
                'execution_context' => ['route' => '/kasse'],
            ],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(5),
        ]);

        $resp = $this->getJson('/api/ai/cellar-snapshot');
        $resp->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('has_errors', true)
            ->assertJsonPath('has_critical_errors', true);

        $clusters = $resp->json('department_clusters');
        $this->assertIsArray($clusters);
        $this->assertCount(10, $clusters);

        $items = $resp->json('items');
        $this->assertNotEmpty($items);

        $checkoutCore = collect($items)->firstWhere('culprit_file', 'app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php');
        $this->assertNotNull($checkoutCore);
        $this->assertSame('core:app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php', $checkoutCore['item_key']);
        $this->assertSame(142, $checkoutCore['line']);
        $this->assertSame(4, $checkoutCore['occurrences_count']);
        $this->assertSame('orders_logistics', $checkoutCore['department_key']);
        $this->assertNotEmpty($checkoutCore['outgoing_files'], 'Outgoing dependencies of OrderCheckout should be resolved dynamically');
    }

    public function test_cellar_handoff_writes_secret_scrubbed_dossier_and_tool_works_for_admin(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['ai:funkira']);

        SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'minipc:sentinel',
            'title' => 'API Verbindung unterbrochen',
            'message' => 'Fehler bei Request mit Authorization: Bearer sf_secret_token_999888777 und key=AIzaSyDummySecretKey123456789012345 in app/Services/AI/FunkiraSkillPoolService.php:88',
            'status' => 'error',
            'payload' => [
                'platform' => 'minipc_linux',
                'file' => 'app/Services/AI/FunkiraSkillPoolService.php',
                'line' => 88,
                'occurrences_count' => 2,
            ],
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(2),
        ]);

        $resp = $this->postJson('/api/ai/cellar-handoff', [
            'item_key' => 'core:app/Services/AI/FunkiraSkillPoolService.php',
            'alina_note' => 'Bitte mit Antigravity prüfen, Token Authorization: Bearer leaked_inline_secret_123 darf nicht im Klartext landen.',
        ]);

        $resp->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('dossier.status', 'ready_for_antigravity')
            ->assertJsonPath('dossier.culprit_file', 'app/Services/AI/FunkiraSkillPoolService.php')
            ->assertJsonPath('dossier.line', 88);

        $this->assertTrue(File::exists($this->handoffPath));
        $rawDiskJson = File::get($this->handoffPath);
        $this->assertStringNotContainsString('sf_secret_token_999888777', $rawDiskJson);
        $this->assertStringNotContainsString('AIzaSyDummySecretKey123456789012345', $rawDiskJson);
        $this->assertStringNotContainsString('leaked_inline_secret_123', $rawDiskJson);

        // Prüfe ebenso das registrierte Funkira Core-Tool
        $this->assertContains('system_prepare_antigravity_handoff', FunkiraSkillPoolService::getCoreToolIdentifiers());
        $detectedPools = FunkiraSkillPoolService::detectPoolsForRequest('Geh mit mir in den Geheimkeller und übergib den Fehler an Antigravity');
        $this->assertContains('system_global', $detectedPools);

        $this->actingAs($admin, 'admin');
        $toolResult = AIFunctionsRegistry::execute('system_prepare_antigravity_handoff', [
            'file_path' => 'app/Services/AI/FunkiraSkillPoolService.php',
            'alina_note' => 'Via Sprachbefehl im Keller bereitgestellt.',
        ]);
        $this->assertSame('success', $toolResult['status'] ?? null);
        $this->assertSame('app/Services/AI/FunkiraSkillPoolService.php', $toolResult['culprit_file'] ?? null);
    }

    public function test_cellar_endpoints_and_tool_block_customers_and_monitor_only_tokens(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->getJson('/api/ai/cellar-snapshot')
            ->assertStatus(401);

        $toolBlocked = AIFunctionsRegistry::execute('system_prepare_antigravity_handoff', []);
        $this->assertSame('error', $toolBlocked['status'] ?? null);
        $this->assertStringContainsString('ROLE VIOLATION', (string) ($toolBlocked['message'] ?? ''));

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['shop:monitor']);
        $this->getJson('/api/ai/cellar-snapshot')->assertStatus(403);
        $this->postJson('/api/ai/cellar-handoff', [])->assertStatus(403);
    }
}

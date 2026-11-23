<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiKnowledgeBase as AiKnowledgeBaseComponent;
use App\Models\Admin\Admin;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Employee\Employee;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiWidgetBrainMapAndTopSecretTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-ts@mein-seelenfunke.de',
            'password' => Hash::make('SecretPassword123!'),
        ]);
    }

    private function createInternalEmployee(): Employee
    {
        return Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mitarbeiter',
            'email' => 'mitarbeiter-ts@mein-seelenfunke.de',
            'password' => Hash::make('EmployeePass123!'),
            'role' => 'production',
            'is_active' => true,
        ]);
    }

    public function test_top_secret_vault_encrypts_at_rest_and_blocks_employees_and_guests(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createInternalEmployee();

        // 1. Guest blocked
        $this->getJson('/api/ai/top-secret/vault')->assertStatus(401);

        // 2. Internal employee strictly blocked (403)
        $this->actingAs($employee, 'employee')
            ->getJson('/api/ai/top-secret/vault')
            ->assertStatus(403);

        // 3. Admin saves Top Secret Intel & Relation via Vault API
        $this->actingAs($admin, 'admin')
            ->postJson('/api/ai/top-secret/intel', [
                'title' => 'Projekt Sternenstaub',
                'content' => 'Streng vertrauliche Lieferantenkondition 42 Prozent.',
                'tags' => 'vertrag,geheim',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($admin, 'admin')
            ->postJson('/api/ai/top-secret/relation', [
                'source' => 'Projekt Sternenstaub',
                'target' => 'Manufaktur Nord',
                'relationship' => 'Exklusivpartner',
                'evidence' => 'Rahmenvertrag 2026',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // 4. Verify AES-256 encryption at rest + VISIBILITY_ADMIN_EXECUTIVE in raw DB
        $intelRow = AiKnowledgeBase::where('title', 'Projekt Sternenstaub')->firstOrFail();
        $this->assertSame(AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE, $intelRow->visibility_scope);
        $this->assertStringStartsWith('[TS_ENC_V1]:', (string) $intelRow->content);
        $this->assertStringNotContainsString('Lieferantenkondition', (string) $intelRow->content);

        // 5. Admin receives decrypted Vault data with no-store header
        $vaultRes = $this->actingAs($admin, 'admin')
            ->getJson('/api/ai/top-secret/vault')
            ->assertOk()
            ->assertJsonPath('cipher', 'AES-256-CBC');

        $this->assertStringContainsString('no-store', (string) $vaultRes->headers->get('Cache-Control'));
        $this->assertSame('Projekt Sternenstaub', $vaultRes->json('intel.0.title'));
        $this->assertSame('Streng vertrauliche Lieferantenkondition 42 Prozent.', $vaultRes->json('intel.0.content'));
        $this->assertSame('Exklusivpartner', $vaultRes->json('relations.0.relationship'));

        // 6. Employee cannot see osint-intel or osint-relation in AiKnowledgeBase Livewire component or Brain Search
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        \Illuminate\Support\Facades\Auth::shouldUse('employee');
        $this->actingAs($employee, 'employee');
        Livewire::test(AiKnowledgeBaseComponent::class)
            ->assertDontSee('Projekt Sternenstaub')
            ->assertDontSee('OSINT Intel');

        $brainForEmployee = AIFunctionsRegistry::executeSearchBrain(['query' => 'Projekt Sternenstaub']);
        $this->assertEmpty($brainForEmployee['knowledge_base'] ?? []);

        // 7. Admin CAN find & transparently decrypt via Brain Search
        \Illuminate\Support\Facades\Auth::guard('employee')->logout();
        \Illuminate\Support\Facades\Auth::shouldUse('admin');
        $this->actingAs($admin, 'admin');
        $brainForAdmin = AIFunctionsRegistry::executeSearchBrain(['query' => 'Projekt Sternenstaub']);
        $this->assertNotEmpty($brainForAdmin['knowledge_base'] ?? []);
        $this->assertStringContainsString('Lieferantenkondition 42 Prozent', $brainForAdmin['knowledge_base'][0]['content']);
    }

    public function test_top_secret_private_file_route_enforces_admin_only_and_blocks_traversal(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createInternalEmployee();

        $dir = storage_path('app/private/top_secret/dossiers');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $filename = 'test_dossier_secure.pdf';
        file_put_contents($dir . '/' . $filename, "%PDF-1.4\n%Test Top Secret PDF");

        try {
            $this->actingAs($employee, 'employee')
                ->get('/api/ai/top-secret/file/dossiers/' . $filename)
                ->assertStatus(403);

            $res = $this->actingAs($admin, 'admin')
                ->get('/api/ai/top-secret/file/dossiers/' . $filename)
                ->assertOk();

            $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));

            // Path traversal / invalid characters rejected
            $this->actingAs($admin, 'admin')
                ->get('/api/ai/top-secret/file/dossiers/..%2F.env')
                ->assertStatus(403);
        } finally {
            @unlink($dir . '/' . $filename);
        }
    }

    public function test_map_quick_search_and_map_control_caching_and_flights_auth(): void
    {
        Cache::flush();
        config(['services.mapbox.token' => 'pk.test_mapbox_token_123']);

        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'features' => [
                    [
                        'text' => 'Gifhorn',
                        'place_name' => 'Gifhorn, Niedersachsen, Deutschland',
                        'place_type' => ['place'],
                        'center' => [10.5467, 52.4787],
                    ],
                ],
            ], 200),
            'de.wikipedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        '100' => [
                            'title' => 'Schloss Gifhorn',
                            'extract' => 'Weserrenaissance-Schloss in Gifhorn.',
                        ],
                    ],
                ],
            ], 200),
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'name' => 'Juwelier Nord',
                    'display_name' => 'Juwelier Nord, Gifhorn',
                    'lon' => '10.5400',
                    'lat' => '52.4750',
                ],
                [
                    'name' => 'Juwelier Süd',
                    'display_name' => 'Juwelier Süd, Gifhorn',
                    'lon' => '10.5550',
                    'lat' => '52.4820',
                ],
            ], 200),
            'opensky-network.org/*' => Http::response([
                'states' => [
                    ['abc123', 'DLH123  ', 'Germany', 0, 0, 10.5, 52.5, 10200, false, 230, 90, 0, null, 10450],
                ],
            ], 200),
        ]);

        // Unauthenticated /api/flights must be rejected
        $this->getJson('/api/flights')->assertStatus(403);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        // Authenticated /api/flights with bounding box succeeds
        $this->getJson('/api/flights?lamin=50.0&lamax=54.0&lomin=8.0&lomax=13.0')
            ->assertOk()
            ->assertJsonCount(1, 'states');

        // Quick search endpoint returns instant structured coordinates
        $this->getJson('/api/ai/map-quick-search?q=Gifhorn')
            ->assertOk()
            ->assertJsonPath('results.0.title', 'Gifhorn')
            ->assertJsonPath('results.0.zoom', 12);

        // Map search & fly uses config('services.mapbox.token') and caches result
        $flyRes = AIFunctionsRegistry::executeMapSearchAndFly(['query' => 'Gifhorn']);
        $this->assertSame('success', $flyRes['status']);
        $this->assertSame('Gifhorn', $flyRes['_frontend_events'][0]['detail']['markerText']);

        // Mark places computes bounding box for fitBounds
        $placesRes = AIFunctionsRegistry::executeMapSearchAndMarkPlaces(['query' => 'Juwelier Gifhorn']);
        $this->assertSame('success', $placesRes['status']);
        $this->assertSame([10.54, 52.475, 10.555, 52.482], $placesRes['bbox']);
    }
}

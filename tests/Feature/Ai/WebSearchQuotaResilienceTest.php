<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\Functions\AiSystemFuncs;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\AI\WebSearchCascadeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebSearchQuotaResilienceTest extends TestCase
{
    use DatabaseTransactions;

    private function buildInlineServiceAccountJson(): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $privateKeyPem = '';
        openssl_pkey_export($res, $privateKeyPem);

        return (string) json_encode([
            'type' => 'service_account',
            'project_id' => 'gen-lang-client-0670786367',
            'private_key_id' => 'quota-resilience-test-key',
            'private_key' => $privateKeyPem,
            'client_email' => 'laravel-backend@gen-lang-client-0670786367.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }

    public function test_tier1_primary_uses_vertex_ai_postpay_grounding_and_retries_across_regions_on_429(): void
    {
        Cache::flush();
        config([
            'services.vertex.enabled' => true,
            'services.vertex.allow_in_tests' => true,
            'services.vertex.project_id' => 'gen-lang-client-0670786367',
            'services.vertex.grounding_location' => 'europe-west4',
            'services.vertex.grounding_model' => 'gemini-3.5-flash',
            'services.vertex.credentials_json' => $this->buildInlineServiceAccountJson(),
            'services.vertex.credentials_file' => '/nonexistent/vertex-sa.json',
        ]);

        $calledUrls = [];

        Http::fake(function ($request) use (&$calledUrls) {
            $url = $request->url();
            $calledUrls[] = $url;

            if ($url === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'ya29.MockPostpayVertexToken',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ], 200);
            }

            if (str_contains($url, 'europe-west4-aiplatform.googleapis.com')) {
                return Http::response([
                    'error' => [
                        'code' => 429,
                        'status' => 'RESOURCE_EXHAUSTED',
                        'message' => 'Quota exceeded for quota metric google_search in europe-west4',
                    ],
                ], 429);
            }

            if (str_contains($url, 'us-central1-aiplatform.googleapis.com')) {
                $body = $request->data();
                $this->assertTrue($request->hasHeader('Authorization', 'Bearer ya29.MockPostpayVertexToken'));
                $this->assertArrayHasKey('google_search', $body['tools'][0] ?? []);

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'role' => 'model',
                                'parts' => [
                                    ['text' => "Aktueller UV-Laserbericht: Der xTool F2 Ultra UV arbeitet mit 355 nm Wellenlaenge \u{2013} hochpraezise fuer Glasgravuren."],
                                ],
                            ],
                            'groundingMetadata' => [
                                'groundingChunks' => [
                                    [
                                        'web' => [
                                            'title' => 'xTool Laser Fachportal',
                                            'uri' => 'https://www.xtool.eu/pages/f2-ultra-uv',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => ['message' => 'Unexpected endpoint ' . $url]], 500);
        });

        $result = AiSystemFuncs::executeSearchWeb([
            'query' => 'xTool F2 Ultra UV Glasgravur Parameter',
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertSame('vertex_grounding', $result['provider']);
        $this->assertSame(1, $result['tier']);
        $this->assertSame('us-central1', $result['location']);
        $this->assertSame('gemini-2.5-flash', $result['model']);
        $this->assertStringContainsString('355 nm', $result['content']);
        $this->assertStringContainsString('xTool Laser Fachportal', $result['content']);
        $this->assertStringNotContainsString(" \u{2013} ", $result['content']);
        $this->assertTrue(
            collect($calledUrls)->contains(fn (string $u) => str_contains($u, 'europe-west4-aiplatform.googleapis.com'))
        );
        $this->assertTrue(
            collect($calledUrls)->contains(fn (string $u) => str_contains($u, 'us-central1-aiplatform.googleapis.com'))
        );
    }

    public function test_tier2_realtime_web_fallback_succeeds_seamlessly_when_all_tier1_endpoints_return_429_quota_exceeded(): void
    {
        Cache::flush();
        config([
            'services.vertex.enabled' => true,
            'services.vertex.allow_in_tests' => true,
            'services.vertex.project_id' => 'gen-lang-client-0670786367',
            'services.vertex.grounding_location' => 'global',
            'services.vertex.grounding_model' => 'gemini-3.5-flash',
            'services.vertex.credentials_json' => $this->buildInlineServiceAccountJson(),
            'services.gemini.key' => 'AIzaSyMockFallbackKey',
        ]);

    $enDash = "\u{2013}";
        $ddgHtml = <<<HTML
<!DOCTYPE html>
<html>
<body>
  <div class="result">
    <a class="result__a" href="//duckduckgo.com/l/?uddg=https%3A%2F%2Fwww.bundesfinanzministerium.de%2Fust-aktuell">BMF Umsatzsteuer Leitfaden 2026</a>
    <a class="result__snippet">Regulaerer Steuersatz 19 Prozent fuer veredelte Geschenkartikel {$enDash} Vorsteuerabzug bei Gewerbestart.</a>
  </div>
  <div class="result">
    <a class="result__a" href="//duckduckgo.com/l/?uddg=http%3A%2F%2F169.254.169.254%2Flatest%2Fmeta-data">Malicious Cloud Metadata</a>
    <a class="result__snippet">Internal SSRF bait that must be stripped.</a>
  </div>
  <div class="result">
    <a class="result__a" href="http://127.0.0.1:8080/internal-secret">Loopback Bait</a>
    <a class="result__snippet">Loopback SSRF bait.</a>
  </div>
</body>
</html>
HTML;

        Http::fake(function ($request) use ($ddgHtml) {
            $url = $request->url();

            if ($url === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'ya29.MockExhaustedToken',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, 'aiplatform.googleapis.com') || str_contains($url, 'generativelanguage.googleapis.com')) {
                return Http::response([
                    'error' => [
                        'code' => 429,
                        'status' => 'RESOURCE_EXHAUSTED',
                        'message' => 'Quota exceeded for quota metric GenerateContent requests per minute',
                    ],
                ], 429);
            }

            if ($url === 'https://html.duckduckgo.com/html/') {
                return Http::response($ddgHtml, 200);
            }

            if (str_starts_with($url, 'https://de.wikipedia.org/w/api.php')) {
                return Http::response([
                    'query' => [
                        'search' => [
                            [
                                'title' => 'Umsatzsteuergesetz (Deutschland)',
                                'snippet' => 'Das <span class="searchmatch">Umsatzsteuergesetz</span> regelt die Besteuerung von Lieferungen und sonstigen Leistungen.',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response('', 404);
        });

        $result = AiSystemFuncs::executeSearchWeb([
            'query' => 'Umsatzsteuer Geschenkartikel Deutschland 2026',
        ]);

        $this->assertSame('success', $result['status']);
        $this->assertSame('realtime_web_fallback', $result['provider']);
        $this->assertSame(2, $result['tier']);
        $this->assertNotEmpty($result['results']);
        $this->assertNotEmpty($result['sources']);
        $this->assertStringContainsString('BMF Umsatzsteuer Leitfaden 2026', $result['content']);
        $this->assertStringContainsString('https://www.bundesfinanzministerium.de/ust-aktuell', $result['content']);
        $this->assertStringContainsString('Umsatzsteuergesetz (Deutschland)', $result['content']);
        $this->assertStringContainsString('(Quellen:', $result['content']);

        // Niemals Quota-Fehlertext oder KI-Gedankenstriche oder SSRF-Ziele in der Antwort
        $this->assertStringNotContainsString('Quota exceeded', $result['content']);
        $this->assertStringNotContainsString('RESOURCE_EXHAUSTED', $result['content']);
        $this->assertStringNotContainsString(" \u{2013} ", $result['content']);
        $this->assertStringNotContainsString('169.254.169.254', $result['content']);
        $this->assertStringNotContainsString('127.0.0.1', $result['content']);

        foreach ($result['results'] as $item) {
            $this->assertTrue(WebSearchCascadeService::isSafeExternalResultUrl($item['url']));
        }
    }

    public function test_tier2_fallback_also_works_without_any_vertex_or_gemini_credentials(): void
    {
        config([
            'services.vertex.enabled' => false,
            'services.vertex.allow_in_tests' => false,
            'services.gemini.key' => '',
            'services.gemini.api_key' => '',
        ]);

        Http::fake([
            'https://html.duckduckgo.com/html/*' => Http::response(
                '<a class="result__a" href="https://example.org/edelstahl-pflege">Edelstahl Schmuck Pflegehinweise</a>'
                . '<a class="result__snippet">Ion-Plating schuetzt 14 Karat vergoldeten Edelstahl dauerhaft.</a>',
                200
            ),
            'https://de.wikipedia.org/w/api.php*' => Http::response(['query' => ['search' => []]], 200),
            'https://en.wikipedia.org/w/api.php*' => Http::response(['query' => ['search' => []]], 200),
        ]);

        $result = AiSystemFuncs::executeSearchWeb(['query' => 'Ion-Plating Edelstahl Pflege']);

        $this->assertSame('success', $result['status']);
        $this->assertSame('realtime_web_fallback', $result['provider']);
        $this->assertSame(2, $result['tier']);
        $this->assertStringContainsString('Edelstahl Schmuck Pflegehinweise', $result['content']);
    }

    public function test_tier3_voice_live_credentials_and_role_isolation_enable_falcone_and_funkira_while_blocking_funki_and_non_admins(): void
    {
        $this->assertContains('system_search_web', FunkiraSkillPoolService::getCoreToolIdentifiers());
        $this->assertTrue(AIFunctionsRegistry::isToolRestrictedForEmployee('system_search_web'));
        $this->assertTrue(AIFunctionsRegistry::isToolRestrictedForEmployee('system_read_web_url'));

        config([
            'services.gemini.key' => 'AIzaSyVoiceCredentialsTestKey',
            'services.vertex.enabled' => false,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded'],
            ], 429),
            'https://html.duckduckgo.com/html/*' => Http::response(
                '<a class="result__a" href="https://www.dhl.de/de/geschaeftskunden.html">DHL Geschaeftskunden Tarife</a>'
                . '<a class="result__snippet">Aktuelle Konditionen fuer den Paketversand in Deutschland.</a>',
                200
            ),
            'https://de.wikipedia.org/w/api.php*' => Http::response(['query' => ['search' => []]], 200),
            'https://en.wikipedia.org/w/api.php*' => Http::response(['query' => ['search' => []]], 200),
        ]);

        $falcone = AiAgent::where('name', 'Falcone')->first() ?: AiAgent::create([
            'name' => 'Falcone',
            'wake_word' => 'falcone',
            'role_description' => 'Chef-Dirigent',
            'system_prompt' => 'Du bist Falcone.',
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);
        $funkira = AiAgent::where('name', 'Funkira')->first() ?: AiAgent::create([
            'name' => 'Funkira',
            'wake_word' => 'funkira',
            'role_description' => 'Executive Assistentin',
            'system_prompt' => 'Du bist Funkira.',
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);
        $funki = AiAgent::where('name', 'Funki')->first() ?: AiAgent::create([
            'name' => 'Funki',
            'wake_word' => 'funki',
            'role_description' => 'Kunden-Support',
            'system_prompt' => 'Du bist Funki.',
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);

        $admin = Admin::first() ?: Admin::factory()->create();
        $adminToken = $admin->createToken('android-voice-admin', ['*'])->plainTextToken;

        // 1. Falcone & Funkira erhalten system_search_web in liveCredentials (Voice-Modus)
        foreach ([$falcone, $funkira] as $execAgent) {
            $liveRes = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
                ->getJson('/api/ai/live-credentials?agent_id=' . $execAgent->id)
                ->assertStatus(200);

            $liveToken = (string) $liveRes->json('token');
            $cached = Cache::get('gemini_live_token_' . $liveToken);
            $this->assertIsArray($cached);

            $declaredNames = collect($cached['tools'][0]['functionDeclarations'] ?? [])
                ->map(fn ($fn) => is_object($fn) ? ($fn->name ?? null) : ($fn['name'] ?? null))
                ->filter()
                ->values()
                ->all();

            $this->assertContains('system_search_web', $declaredNames, "system_search_web muss fuer {$execAgent->name} im Sprachmodus aktiv sein.");
        }

        // 2. Admin kann system_search_web ueber POST /api/ai/execute (auch bei 429 auf Stufe 1 dank Stufe 2!) ausfuehren
        $execRes = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->postJson('/api/ai/execute', [
                'agent_name' => 'Falcone',
                'function' => 'system_search_web',
                'args' => ['query' => 'DHL Geschaeftskunden Tarife'],
            ])
            ->assertStatus(200);

        $this->assertSame('success', $execRes->json('result.status'));
        $this->assertSame('realtime_web_fallback', $execRes->json('result.provider'));
        $this->assertStringContainsString('DHL Geschaeftskunden Tarife', (string) $execRes->json('result.content'));

        // 3. Air-Gapped Funki erhaelt system_search_web NIEMALS (weder in liveCredentials noch in AIFunctionsRegistry::execute)
        $funkiLiveRes = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->getJson('/api/ai/live-credentials?agent_id=' . $funki->id)
            ->assertStatus(200);

        $funkiCached = Cache::get('gemini_live_token_' . $funkiLiveRes->json('token'));
        $funkiDeclared = collect($funkiCached['tools'][0]['functionDeclarations'] ?? [])
            ->map(fn ($fn) => is_object($fn) ? ($fn->name ?? null) : ($fn['name'] ?? null))
            ->filter()
            ->values()
            ->all();
        $this->assertNotContains('system_search_web', $funkiDeclared);

        $this->actingAs($admin, 'admin');
        $funkiExec = AIFunctionsRegistry::execute('system_search_web', ['query' => 'Interna'], $funki);
        $this->assertTrue((bool) ($funkiExec['error'] ?? false));
        $this->assertStringContainsString('SANDBOX VIOLATION', (string) ($funkiExec['message'] ?? ''));

        // 4. Nicht-Admins (Mitarbeiter, Influencer, Steuerberater, Kunde, Gast) sind strikt blockiert
        auth()->forgetGuards();
        $this->flushHeaders();

        $guestStatus = $this->postJson('/api/ai/execute', [
            'function' => 'system_search_web',
            'args' => ['query' => 'Test'],
        ])->status();
        $this->assertContains($guestStatus, [401, 403]);

        $internalEmployee = Employee::create([
            'first_name' => 'Tim',
            'last_name' => 'Team',
            'email' => 'tim-websearch-' . uniqid() . '@example.com',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);
        $empToken = $internalEmployee->createToken('emp-test', ['*'])->plainTextToken;

        $empLiveRes = $this->withHeaders(['Authorization' => 'Bearer ' . $empToken])
            ->getJson('/api/ai/live-credentials?agent_id=' . $funki->id)
            ->assertStatus(200);
        $empCached = Cache::get('gemini_live_token_' . $empLiveRes->json('token'));
        $empDeclared = collect($empCached['tools'][0]['functionDeclarations'] ?? [])
            ->map(fn ($fn) => is_object($fn) ? ($fn->name ?? null) : ($fn['name'] ?? null))
            ->filter()
            ->values()
            ->all();
        $this->assertNotContains('system_search_web', $empDeclared);

        $this->withHeaders(['Authorization' => 'Bearer ' . $empToken])
            ->postJson('/api/ai/execute', [
                'agent_id' => $funki->id,
                'function' => 'system_search_web',
                'args' => ['query' => 'Test'],
            ])
            ->assertStatus(403);

        foreach (['influencer', 'tax_advisor'] as $partnerRole) {
            auth()->forgetGuards();
            $this->flushHeaders();
            $partner = Employee::create([
                'first_name' => 'Partner',
                'last_name' => ucfirst($partnerRole),
                'email' => $partnerRole . '-websearch-' . uniqid() . '@example.com',
                'role' => $partnerRole,
                'password' => bcrypt('Secret123!'),
            ]);
            $this->actingAs($partner, 'employee')
                ->postJson('/api/ai/execute', [
                    'function' => 'system_search_web',
                    'args' => ['query' => 'Test'],
                ])
                ->assertStatus(403);
        }

        auth()->forgetGuards();
        $this->flushHeaders();
        $customer = Customer::create([
            'first_name' => 'Klara',
            'last_name' => 'Kundin',
            'email' => 'klara-websearch-' . uniqid() . '@example.com',
            'password' => bcrypt('Secret123!'),
        ]);
        $customerHttpStatus = $this->actingAs($customer, 'customer')
            ->postJson('/api/ai/execute', [
                'function' => 'system_search_web',
                'args' => ['query' => 'Test'],
            ])
            ->status();
        $this->assertContains($customerHttpStatus, [401, 403]);

        $customerRegistryRes = AIFunctionsRegistry::execute('system_search_web', ['query' => 'Test'], $funki);
        $this->assertTrue((bool) ($customerRegistryRes['error'] ?? false));
    }
}

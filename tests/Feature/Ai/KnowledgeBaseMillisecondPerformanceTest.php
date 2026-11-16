<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Services\AI\AiPromptService;
use App\Services\AI\Functions\AiBrainFuncs;
use App\Services\AI\Workspace\AbschlussberichteIndexerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class KnowledgeBaseMillisecondPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Als Admin authentifizieren, damit Brain-Funktionen uneingeschränkten Zugriff haben
        $admin = Admin::factory()->create();
        Auth::guard('admin')->login($admin);
        Auth::login($admin);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        Auth::guard('admin')->logout();
        parent::tearDown();
    }

    /**
     * Testet die blitzschnelle Indexierung und das Überspringen unveränderter Dateien.
     */
    public function test_sync_abschlussberichte_command_indexes_reports_and_skips_unchanged(): void
    {
        $indexer = app(AbschlussberichteIndexerService::class);

        // 1. Erstindexierung
        $firstRun = $indexer->syncAll(force: true);

        $this->assertGreaterThan(50, $firstRun['total']);
        $this->assertGreaterThan(50, $firstRun['created']);
        $this->assertEquals(0, $firstRun['skipped']);
        $this->assertGreaterThan(5, $firstRun['categories']);

        // Überprüfe Datenbank-Persistenz
        $this->assertDatabaseHas('ai_knowledge_base_categories', [
            'slug' => 'abschlussberichte-agenten',
        ]);
        $this->assertDatabaseHas('ai_knowledge_base_categories', [
            'slug' => 'abschlussberichte-system',
        ]);
        $this->assertTrue(
            AiKnowledgeBase::where('slug', 'like', 'abschlussbericht-%')->count() > 50
        );

        // 2. Zweitlauf ohne force: Muss alle unveränderten Dateien blitzschnell überspringen
        $secondRun = $indexer->syncAll(force: false);

        $this->assertEquals($firstRun['total'], $secondRun['total']);
        $this->assertEquals(0, $secondRun['created']);
        $this->assertEquals(0, $secondRun['updated']);
        $this->assertEquals($firstRun['total'], $secondRun['skipped']);
    }

    /**
     * Testet, dass die Brain Search Berichte per FULLTEXT in Millisekunden findet.
     */
    public function test_brain_search_finds_laser_reports_via_fulltext_in_milliseconds(): void
    {
        $indexer = app(AbschlussberichteIndexerService::class);
        $indexer->syncAll(force: true);

        $brainDummy = new class {
            use AiBrainFuncs;
        };

        $start = microtime(true);
        $result = $brainDummy::executeSearchBrain(['query' => 'Laser xTool F2 Ultra']);
        $durationMs = (microtime(true) - $start) * 1000;

        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['knowledge_base']);
        $this->assertLessThan(1000, $durationMs, "Brain Search dauerte {$durationMs} ms, erwartet < 1000 ms.");

        // Prüfe Relevanz: Mindestens ein Treffer muss Laser oder xTool enthalten
        $foundLaserTopic = false;
        foreach ($result['knowledge_base'] as $kb) {
            if (str_contains(mb_strtolower($kb['title']), 'laser') || str_contains(mb_strtolower($kb['content']), 'xtool')) {
                $foundLaserTopic = true;
                break;
            }
        }
        $this->assertTrue($foundLaserTopic, 'Die Suche hat keinen Laser- oder xTool-Bezug geliefert.');
    }

    /**
     * Testet, dass wiederholte Suchanfragen aus dem Cache mit Sub-Millisekunden Latenz kommen.
     */
    public function test_brain_search_uses_caching_for_instant_subsequent_queries(): void
    {
        $indexer = app(AbschlussberichteIndexerService::class);
        $indexer->syncAll(force: true);

        $brainDummy = new class {
            use AiBrainFuncs;
        };

        $query = 'Abschlussbericht System';
        $cacheKey = 'ai_brain_search_' . md5($query);

        Cache::forget($cacheKey);

        // Erstsuche: befüllt Cache
        $res1 = $brainDummy::executeSearchBrain(['query' => $query]);
        $this->assertEquals('success', $res1['status']);
        $this->assertTrue(Cache::has($cacheKey));

        // Zweitsuche: Kommt direkt aus dem Cache
        $start = microtime(true);
        $res2 = $brainDummy::executeSearchBrain(['query' => $query]);
        $cachedDurationMs = (microtime(true) - $start) * 1000;

        $this->assertEquals($res1, $res2);
        $this->assertLessThan(50, $cachedDurationMs, "Gecachte Suche dauerte {$cachedDurationMs} ms, erwartet < 50 ms.");
    }

    /**
     * Testet die automatische Cache-Invalidierung bei Model-Events (saved, deleted).
     */
    public function test_cache_invalidation_on_knowledge_base_update_and_delete(): void
    {
        $cat = AiKnowledgeBaseCategory::firstOrCreate(
            ['slug' => 'test-cat'],
            ['name' => 'Test Kategorie']
        );

        Cache::put('ai_kb_published_support', 'vorhandener-support-cache', 3600);
        $this->assertTrue(Cache::has('ai_kb_published_support'));

        // Save löst Cache-Invalidierung aus
        $kb = AiKnowledgeBase::create([
            'title' => 'Neuer Cache-Test Artikel',
            'slug' => 'neuer-cache-test-artikel',
            'ai_knowledge_base_category_id' => $cat->id,
            'content' => 'Inhalt zum Testen der Cache-Invalidierung.',
            'is_published' => true,
        ]);

        $this->assertFalse(
            Cache::has('ai_kb_published_support'),
            'Support-Cache wurde nach Model::create nicht invalidiert.'
        );

        // Delete löst ebenfalls Cache-Invalidierung aus
        Cache::put('ai_kb_published_support', 'vorhandener-support-cache-2', 3600);
        $this->assertTrue(Cache::has('ai_kb_published_support'));

        $kb->delete();

        $this->assertFalse(
            Cache::has('ai_kb_published_support'),
            'Support-Cache wurde nach Model::delete nicht invalidiert.'
        );
    }

    /**
     * Testet, dass AiPromptService kundenrelevantes Support-Wissen cacht.
     */
    public function test_prompt_service_caches_support_knowledge(): void
    {
        $cat = AiKnowledgeBaseCategory::firstOrCreate(
            ['slug' => 'unternehmen-kontakt'],
            ['name' => 'Unternehmen & Kontakt']
        );

        AiKnowledgeBase::firstOrCreate(
            ['slug' => 'kontakt-info-test'],
            [
                'title' => 'Kontakt & Anfahrt',
                'ai_knowledge_base_category_id' => $cat->id,
                'content' => 'Carl-Goerdeler-Ring 26, 38518 Gifhorn.',
                'is_published' => true,
            ]
        );

        Cache::forget('ai_kb_published_support');

        $agent = AiAgent::firstOrCreate(
            ['name' => 'Funki'],
            [
                'slug' => 'funki',
                'description' => 'Support Agent Funki',
                'system_prompt' => 'Du bist Funki.',
                'is_active' => true,
            ]
        );

        $prompt = AiPromptService::getRichPrompt($agent);

        $this->assertStringContainsString('Carl-Goerdeler-Ring 26', $prompt);
        $this->assertTrue(
            Cache::has('ai_kb_published_support'),
            'AiPromptService hat den ai_kb_published_support Cache nicht befüllt.'
        );
    }
}

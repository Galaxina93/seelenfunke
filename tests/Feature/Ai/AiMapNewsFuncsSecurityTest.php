<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\AI\Functions\AiMapNewsFuncs;

class AiMapNewsFuncsSecurityTest extends TestCase
{
    private $mockClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClass = new class {
            use AiMapNewsFuncs;
        };
    }

    public function test_map_search_and_mark_news_rejects_empty_query(): void
    {
        $res = $this->mockClass::executeMapSearchAndMarkNews(['query' => '   ']);
        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('darf nicht leer sein', $res['message']);
    }

    public function test_map_search_and_mark_news_handles_missing_keys_gracefully(): void
    {
        config(['services.newsapi.key' => null]);
        config(['openai.api_key' => null]);
        config(['services.openai.key' => null]);
        config(['services.mapbox.token' => null]);

        $origNews = $_ENV['NEWS_API_KEY'] ?? null;
        $origOpenAi = $_ENV['OPENAI_API_KEY'] ?? null;
        $origMapbox = $_ENV['MAPBOX_TOKEN'] ?? null;

        $_ENV['NEWS_API_KEY'] = '';
        $_ENV['OPENAI_API_KEY'] = '';
        $_ENV['MAPBOX_TOKEN'] = '';
        $_SERVER['NEWS_API_KEY'] = '';
        $_SERVER['OPENAI_API_KEY'] = '';
        $_SERVER['MAPBOX_TOKEN'] = '';
        putenv('NEWS_API_KEY=');
        putenv('OPENAI_API_KEY=');
        putenv('MAPBOX_TOKEN=');

        try {
            $res = $this->mockClass::executeMapSearchAndMarkNews(['query' => 'Berlin Vorfall']);
            $this->assertEquals('error', $res['status']);
            $this->assertStringContainsString('Es fehlen benötigte API-Schlüssel', $res['message']);
        } finally {
            if ($origNews !== null) { $_ENV['NEWS_API_KEY'] = $origNews; $_SERVER['NEWS_API_KEY'] = $origNews; putenv("NEWS_API_KEY={$origNews}"); }
            if ($origOpenAi !== null) { $_ENV['OPENAI_API_KEY'] = $origOpenAi; $_SERVER['OPENAI_API_KEY'] = $origOpenAi; putenv("OPENAI_API_KEY={$origOpenAi}"); }
            if ($origMapbox !== null) { $_ENV['MAPBOX_TOKEN'] = $origMapbox; $_SERVER['MAPBOX_TOKEN'] = $origMapbox; putenv("MAPBOX_TOKEN={$origMapbox}"); }
        }
    }

    public function test_map_search_and_mark_news_executes_with_mocks(): void
    {
        config([
            'services.newsapi.key' => 'mock_news_key',
            'openai.api_key' => 'mock_openai_key',
            'services.mapbox.token' => 'mock_mapbox_token'
        ]);

        Http::fake([
            'newsapi.org/*' => Http::response([
                'articles' => [
                    [
                        'title' => 'Ereignis in Berlin',
                        'description' => 'In Berlin ereignete sich ein Vorfall.',
                        'url' => 'https://example.com/news/1'
                    ]
                ]
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'markers' => [
                                    [
                                        'id' => 0,
                                        'location' => 'Berlin, Deutschland',
                                        'title' => 'Ereignis in Berlin'
                                    ]
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200),
            'api.mapbox.com/*' => Http::response([
                'features' => [
                    [
                        'center' => [13.4050, 52.5200]
                    ]
                ]
            ], 200),
        ]);

        $res = $this->mockClass::executeMapSearchAndMarkNews([
            'query' => 'Berlin Ereignis',
            'language' => 'invalid_lang_code' // Should fallback to de
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertNotEmpty($res['_frontend_events']);
        $this->assertEquals('Berlin, Deutschland', $res['_frontend_events'][0]['detail']['markers'][0]['location_name']);
    }
}

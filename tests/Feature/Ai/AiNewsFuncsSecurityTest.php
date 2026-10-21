<?php

namespace Tests\Feature\Ai;

use App\Services\AI\Functions\AiNewsFuncs;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiNewsFuncsSecurityTest extends TestCase
{
    public function test_search_global_news_validates_inputs_and_masks_errors()
    {
        $dummy = new class {
            use AiNewsFuncs;
        };

        // Missing query
        $resMissing = $dummy::searchGlobalNews([]);
        $this->assertEquals('error', $resMissing['status']);
        $this->assertStringContainsString('Suchbegriff für Nachrichten fehlt', $resMissing['message']);

        // Mock News API error response
        putenv('NEWS_API_KEY=test_api_key_123');
        Http::fake([
            'https://newsapi.org/*' => Http::response(['sensitive_internal_trace' => 'DB_FAIL'], 500)
        ]);

        $resApiFail = $dummy::searchGlobalNews([
            'query' => 'Handwerk',
            'sortBy' => 'invalid_injection', // should fallback to 'publishedAt'
        ]);

        $this->assertEquals('error', $resApiFail['status']);
        $this->assertStringNotContainsString('DB_FAIL', $resApiFail['message']);
        $this->assertStringContainsString('Status: 500', $resApiFail['message']);
    }

    public function test_search_youtube_video_validates_query_and_extracts_id()
    {
        $dummy = new class {
            use AiNewsFuncs;
        };

        // Missing query
        $resMissing = $dummy::searchYoutubeVideo([]);
        $this->assertEquals('error', $resMissing['status']);

        // Mock YouTube HTML response
        Http::fake([
            'https://www.youtube.com/*' => Http::response('var ytInitialData = {"videoId":"dQw4w9WgXcQ"};', 200)
        ]);

        $resSuccess = $dummy::searchYoutubeVideo([
            'query' => 'Never gonna give you up',
            'title' => 'Rick Astley',
        ]);

        $this->assertEquals('success', $resSuccess['status']);
        $this->assertStringContainsString('dQw4w9WgXcQ', $resSuccess['_frontend_events'][0]['detail']['payload']['articles'][0]['video']);
    }
}

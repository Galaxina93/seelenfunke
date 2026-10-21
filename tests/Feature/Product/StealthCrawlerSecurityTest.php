<?php

namespace Tests\Feature\Product;

use App\Services\StealthCrawlerService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StealthCrawlerSecurityTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function no_hardcoded_scraperapi_key_exists_in_service_file(): void
    {
        $code = file_get_contents(app_path('Services/StealthCrawlerService.php'));

        // Assert that the previously hardcoded key is completely removed
        $this->assertStringNotContainsString('707ccc851a9e7c4759106d2f6e6bf764', $code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function crawler_gracefully_handles_missing_scraper_api_key_without_crashing(): void
    {
        config(['services.scraperapi.key' => null]);

        $service = new StealthCrawlerService();
        $jobId = 'test_job_' . uniqid();

        // Should return cleanly without throwing an exception
        $service->crawlAmazon($jobId, 'test-item', 1);

        $jobState = Cache::get("crawler_job_{$jobId}");
        $this->assertNotNull($jobState);
        $this->assertFalse($jobState['is_running']);
        $this->assertStringContainsString('Kein SCRAPER_API_KEY', $jobState['status']);
    }
}

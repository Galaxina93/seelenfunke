<?php

namespace Tests\Feature\Livewire\Shop\Master;

use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\Tracking\PageVisit;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MasterTrafficSourcesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    #[Test]
    public function it_classifies_organic_search_traffic_correctly()
    {
        PageVisit::create([
            'session_id' => 'sess_google_1',
            'ip_hash' => 'hash1',
            'url' => 'https://seelenfunke.de/produkt/seelen-kristall',
            'path' => 'produkt/seelen-kristall',
            'method' => 'GET',
            'referer' => 'https://www.google.de/search?q=personalisierte+geschenke',
            'created_at' => now()->subHours(2),
        ]);

        $service = app(AnalyticsService::class);
        $result = $service->getTrafficSourcesAnalysis(now()->subDay(), now());

        $this->assertEquals(1, $result['total_sessions']);
        $this->assertEquals(1, $result['channels']['search']['count']);
        $this->assertEquals(100.0, $result['channels']['search']['pct']);

        $topSource = $result['top_sources'][0] ?? null;
        $this->assertNotNull($topSource);
        $this->assertEquals('Google Suche', $topSource['name']);
        $this->assertEquals('search', $topSource['channel']);
    }

    #[Test]
    public function it_classifies_social_media_and_link_shims()
    {
        PageVisit::create([
            'session_id' => 'sess_insta_1',
            'ip_hash' => 'hash2',
            'url' => 'https://seelenfunke.de/shop',
            'path' => 'shop',
            'method' => 'GET',
            'referer' => 'https://l.instagram.com/',
            'created_at' => now()->subHours(1),
        ]);

        PageVisit::create([
            'session_id' => 'sess_pin_1',
            'ip_hash' => 'hash3',
            'url' => 'https://seelenfunke.de/shop',
            'path' => 'shop',
            'method' => 'GET',
            'referer' => 'https://www.pinterest.de/',
            'created_at' => now()->subMinutes(30),
        ]);

        $service = app(AnalyticsService::class);
        $result = $service->getTrafficSourcesAnalysis(now()->subDay(), now());

        $this->assertEquals(2, $result['total_sessions']);
        $this->assertEquals(2, $result['channels']['social']['count']);
        $this->assertEquals(100.0, $result['channels']['social']['pct']);
    }

    #[Test]
    public function it_detects_utm_campaign_parameters_in_entry_urls()
    {
        PageVisit::create([
            'session_id' => 'sess_utm_1',
            'ip_hash' => 'hash4',
            'url' => 'https://seelenfunke.de/?utm_source=instagram&utm_medium=story&utm_campaign=herbst_sale',
            'path' => '/',
            'method' => 'GET',
            'referer' => null, // Mobile In-App browser stripped referer
            'created_at' => now()->subHours(3),
        ]);

        $service = app(AnalyticsService::class);
        $result = $service->getTrafficSourcesAnalysis(now()->subDay(), now());

        $this->assertEquals(1, $result['total_sessions']);
        $this->assertNotEmpty($result['top_campaigns']);
        $this->assertEquals('herbst_sale', $result['top_campaigns'][0]['campaign']);
        $this->assertEquals('instagram', $result['top_campaigns'][0]['source']);
    }

    #[Test]
    public function it_detects_freunde_werben_referral_links()
    {
        PageVisit::create([
            'session_id' => 'sess_ref_1',
            'ip_hash' => 'hash5',
            'url' => 'https://seelenfunke.de/?ref=FREUNDE2026',
            'path' => '/',
            'method' => 'GET',
            'referer' => null,
            'created_at' => now()->subHours(4),
        ]);

        $service = app(AnalyticsService::class);
        $result = $service->getTrafficSourcesAnalysis(now()->subDay(), now());

        $this->assertEquals(1, $result['total_sessions']);
        $this->assertEquals(1, $result['channels']['referral']['count']);
        $this->assertStringContainsString('FREUNDE2026', $result['top_sources'][0]['name']);
    }

    #[Test]
    public function it_filters_out_internal_shop_urls_and_treats_as_direct()
    {
        // Session starting from internal page (e.g. reload or admin link)
        PageVisit::create([
            'session_id' => 'sess_internal_1',
            'ip_hash' => 'hash6',
            'url' => 'https://seelenfunke.de/produkt/seelen-kristall',
            'path' => 'produkt/seelen-kristall',
            'method' => 'GET',
            'referer' => 'https://seelenfunke.de/shop',
            'created_at' => now()->subHours(2),
        ]);

        // Subsequent page click in same session
        PageVisit::create([
            'session_id' => 'sess_internal_1',
            'ip_hash' => 'hash6',
            'url' => 'https://seelenfunke.de/warenkorb',
            'path' => 'warenkorb',
            'method' => 'GET',
            'referer' => 'https://seelenfunke.de/produkt/seelen-kristall',
            'created_at' => now()->subHours(1),
        ]);

        $service = app(AnalyticsService::class);
        $result = $service->getTrafficSourcesAnalysis(now()->subDay(), now());

        $this->assertEquals(1, $result['total_sessions']);
        $this->assertEquals(1, $result['channels']['direct']['count']);
        $this->assertStringNotContainsString('seelenfunke.de', $result['top_sources'][0]['name']);
    }

    #[Test]
    public function master_analytics_renders_traffic_sources_widget()
    {
        $this->actingAs($this->admin, 'admin');

        PageVisit::create([
            'session_id' => 'sess_render_1',
            'ip_hash' => 'hash7',
            'url' => 'https://seelenfunke.de/?utm_source=google&utm_medium=cpc&utm_campaign=adwords_test',
            'path' => '/',
            'method' => 'GET',
            'referer' => 'https://www.google.de/',
            'created_at' => now()->subHours(1),
        ]);

        Livewire::test(MasterAnalytics::class)
            ->assertSee('Quellenanalyse', escape: false)
            ->assertSee('sourcesDoughnutChart', escape: false)
            ->assertSee('Organische Suche')
            ->assertSee('Social Media')
            ->assertSee('Kampagnen', escape: false);
    }
}

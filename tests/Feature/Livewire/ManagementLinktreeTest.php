<?php

namespace Tests\Feature\Livewire;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Management\ManagementLinktree;
use App\Models\Management\ManagementTrackingLink;
use App\Models\System\SystemSetting;
use App\Models\Admin\Admin;
use App\Livewire\Backend\Management\ManagementLinktreeManager;
use App\Livewire\Frontend\Management\ManagementLinktreePage;
use App\Services\AnalyticsService;

class ManagementLinktreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an admin user for backend tests
        $this->admin = Admin::factory()->create();
    }

    #[Test]
    public function backend_linktree_manager_renders_correctly()
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->assertStatus(200)
            ->assertSee('Linktree Verwaltung');
    }

    #[Test]
    public function admin_can_create_new_linktree_entry()
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->set('title', 'Test Link')
            ->set('url', 'https://example.com')
            ->set('icon', 'star')
            ->set('type', 'highlight')
            ->call('save');

        $this->assertDatabaseHas('management_linktrees', [
            'title' => 'Test Link',
            'url' => 'https://example.com',
            'icon' => 'star',
            'type' => 'highlight',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function admin_can_update_existing_linktree_entry()
    {
        $link = ManagementLinktree::create([
            'title' => 'Old Title',
            'url' => 'https://old.com',
            'type' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->call('edit', $link->id)
            ->set('title', 'New Title')
            ->set('url', 'https://new.com')
            ->call('save');

        $this->assertDatabaseHas('management_linktrees', [
            'id' => $link->id,
            'title' => 'New Title',
            'url' => 'https://new.com',
        ]);
    }

    #[Test]
    public function admin_can_delete_linktree_entry()
    {
        $link = ManagementLinktree::create([
            'title' => 'To Delete',
            'url' => 'https://delete.com',
            'type' => 'standard',
            'sort_order' => 1,
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->call('delete', $link->id);

        $this->assertDatabaseMissing('management_linktrees', [
            'id' => $link->id,
        ]);
    }

    #[Test]
    public function admin_can_save_design_settings()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('profile.jpg');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->set('themeColor', '#FF0000')
            ->set('profileImage', $file)
            ->call('saveSettings');

        $this->assertDatabaseHas('shop_settings', [
            'key' => 'linktree_theme_color',
            'value' => '#FF0000',
        ]);

        $this->assertDatabaseHas('shop_settings', [
            'key' => 'linktree_profile_image',
        ]);
    }

    #[Test]
    public function admin_can_delete_profile_image()
    {
        SystemSetting::create([
            'key' => 'linktree_profile_image',
            'value' => '/storage/test.jpg'
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->call('deleteProfileImage');

        $this->assertDatabaseMissing('shop_settings', [
            'key' => 'linktree_profile_image',
        ]);
    }

    #[Test]
    public function frontend_linktree_page_renders_correctly()
    {
        ManagementLinktree::create([
            'title' => 'Frontend Test',
            'url' => 'https://frontend.com',
            'type' => 'standard',
            'sort_order' => 1,
        ]);

        Livewire::test(ManagementLinktreePage::class)
            ->assertStatus(200)
            ->assertSee('Frontend Test');
    }

    #[Test]
    public function frontend_tracks_clicks()
    {
        $link = ManagementLinktree::create([
            'title' => 'Click Test',
            'url' => 'https://click.com',
            'type' => 'standard',
            'sort_order' => 1,
        ]);

        Livewire::test(ManagementLinktreePage::class)
            ->call('trackAndRedirect', $link->id, $link->url)
            ->assertRedirect('https://click.com');

        $this->assertDatabaseHas('management_linktree_clicks', [
            'link_id' => $link->id,
        ]);
    }

    #[Test]
    public function frontend_linktree_opens_in_new_tab_and_resolves_shop_url_across_environments()
    {
        $link = ManagementLinktree::create([
            'title' => 'Zum Shop',
            'url' => 'https://www.mein-seelenfunke.de/shop/',
            'type' => 'highlight',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertEquals(url('/shop'), $link->resolved_url);

        Livewire::test(ManagementLinktreePage::class)
            ->assertStatus(200)
            ->assertSee('Zum Shop')
            ->assertSee('target="_blank"', false)
            ->call('trackClick', $link->id);

        $this->assertDatabaseHas('management_linktree_clicks', [
            'link_id' => $link->id,
        ]);
    }

    #[Test]
    public function admin_can_create_tracking_link_and_redirect_tracks_analytics_channel_views()
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementLinktreeManager::class)
            ->assertSee('Tipp für Werbe- & Social-Media-Links', false)
            ->set('trackingName', 'WhatsApp')
            ->call('saveTrackingLink');

        $trackingLink = ManagementTrackingLink::first();
        $this->assertNotNull($trackingLink);
        $this->assertEquals('WhatsApp', $trackingLink->name);
        $this->assertEquals('whatsapp', $trackingLink->slug);
        $this->assertEquals('whatsapp', $trackingLink->utm_source);
        $this->assertEquals('social', $trackingLink->channel);

        $response = $this->get(route('frontend.tracking.redirect', ['slug' => $trackingLink->slug]));
        $response->assertRedirect($trackingLink->full_utm_url);

        $trackingLink->refresh();
        $this->assertEquals(1, $trackingLink->clicks_count);
        $this->assertEquals(1, $trackingLink->unique_clicks_count);

        $analytics = app(AnalyticsService::class)->getTrafficSourcesAnalysis(now()->subDays(30), now());
        $this->assertGreaterThanOrEqual(1, $analytics['total_views']);
        $this->assertGreaterThanOrEqual(1, $analytics['total_tracking_clicks']);

        $socialChannel = $analytics['channels']['social'] ?? null;
        $this->assertNotNull($socialChannel);
        $this->assertEquals('Social Media', $socialChannel['label']);
        $this->assertGreaterThanOrEqual(1, $socialChannel['views']);
    }
}

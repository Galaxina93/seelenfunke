<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Marketing\MarketingBlogPost;
use App\Services\AI\Functions\AiMarketingBlogFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AiMarketingBlogFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_post_uses_admin_uuid_and_resolves_slug_collisions()
    {
        $dummy = new class {
            use AiMarketingBlogFuncs;
        };

        $admin = Admin::factory()->create();
        Auth::login($admin);

        // 1. Create first blog post
        $res1 = $dummy::marketing_blog_create_post([
            'title' => 'Tipps für Lasergravuren auf Eichenholz',
            'content' => '<p>Eichenholz ist ein wunderbares Material...</p>',
            'excerpt' => 'Alles über Eichenholz Gravuren',
            'status' => 'invalid_status_xyz', // Should fallback to 'draft'
        ]);

        $this->assertEquals('success', $res1['status']);
        $post1 = MarketingBlogPost::find($res1['id']);
        $this->assertNotNull($post1);
        $this->assertEquals($admin->id, $post1->user_id);
        $this->assertEquals('tipps-fur-lasergravuren-auf-eichenholz', $post1->slug);
        $this->assertEquals('draft', $post1->status);

        // 2. Create second blog post with identical title -> should avoid duplicate key crash
        $res2 = $dummy::marketing_blog_create_post([
            'title' => 'Tipps für Lasergravuren auf Eichenholz',
            'content' => '<p>Zweiter Beitrag mit gleichem Titel...</p>',
            'excerpt' => 'Zweite Vorschau',
            'status' => 'published',
        ]);

        $this->assertEquals('success', $res2['status']);
        $post2 = MarketingBlogPost::find($res2['id']);
        $this->assertNotNull($post2);
        $this->assertEquals('tipps-fur-lasergravuren-auf-eichenholz-1', $post2->slug);
        $this->assertEquals('published', $post2->status);
        $this->assertNotNull($post2->published_at);
    }

    public function test_get_posts_escapes_sql_wildcards()
    {
        $dummy = new class {
            use AiMarketingBlogFuncs;
        };

        $admin = Admin::factory()->create();
        Auth::login($admin);

        MarketingBlogPost::create([
            'title' => 'Beitrag_Eins für Laser',
            'slug' => 'beitrag-eins',
            'content' => 'Inhalt',
            'user_id' => $admin->id,
            'status' => 'published',
        ]);

        // Search with wildcard "BeitragXEins" where "_" shouldn't match "X"
        $res = $dummy::marketing_blog_get_posts([
            'search' => 'BeitragXEins',
        ]);

        $this->assertEquals(0, $res['count']);

        // Exact substring matches
        $resExact = $dummy::marketing_blog_get_posts([
            'search' => 'Beitrag_Eins',
        ]);

        $this->assertEquals(1, $resExact['count']);
    }
}

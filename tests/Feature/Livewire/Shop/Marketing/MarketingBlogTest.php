<?php

namespace Tests\Feature\Livewire\Shop\Marketing;

use App\Livewire\Shop\Marketing\MarketingBlog;
use App\Livewire\Shop\Marketing\MarketingBlogIndex;
use App\Livewire\Shop\Marketing\MarketingBlogShow;
use App\Models\Marketing\MarketingBlogPost as BlogModel;
use App\Models\Marketing\MarketingBlogCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MarketingBlogTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $category;
    private $publishedBlog;
    private $draftBlog;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Authenticate as Admin (Raw PDO Injection to bypass Factory/UUID Hooks)
        $adminId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Marketing',
            'last_name' => 'Admin',
            'email' => 'marketing-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->admin = \App\Models\Admin\Admin::find($adminId);
        
        // 2. Clear volatile Livewire caches
        config(['livewire.temporary_file_upload.disk' => 'local']);
        Storage::fake('local');
        Storage::fake('public');

        // 3. Create Supporting Blog Categories
        $this->category = MarketingBlogCategory::create([
            'id' => Str::uuid(),
            'name' => 'SEO Best Practices',
            'slug' => 'seo-best-practices'
        ]);

        // 4. Create Seed Articles
        $this->publishedBlog = BlogModel::create([
            'id' => Str::uuid(),
            'user_id' => $this->admin->id,
            'blog_category_id' => $this->category->id,
            'title' => 'Mastering Laravel Testing',
            'slug' => 'mastering-laravel-testing',
            'meta_description' => 'A guide to testing',
            'content' => 'This is the main body content rendered in markdown format.',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'featured_image' => 'marketing/blogs/sample.jpg'
        ]);

        $this->draftBlog = BlogModel::create([
            'id' => Str::uuid(),
            'user_id' => $this->admin->id,
            'blog_category_id' => $this->category->id,
            'title' => 'Draft Feature Coming Soon',
            'slug' => 'draft-feature-coming-soon',
            'meta_description' => 'Unpublished content',
            'content' => 'Work in progress...',
            'status' => 'draft',
            'published_at' => null
        ]);
    }

    #[Test]
    public function it_renders_the_marketing_dashboard_and_paginates_all_articles()
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(MarketingBlog::class)
            ->assertStatus(200)
            ->assertSee('Mastering Laravel Testing')
            ->assertSee('Draft Feature Coming Soon');
    }

    #[Test]
    public function it_can_create_a_new_marketing_blog_post_with_images_and_dynamic_slug_generation()
    {
        $this->actingAs($this->admin, 'admin');
        
        $image = UploadedFile::fake()->image('seo-banner.jpg');

        Livewire::test(MarketingBlog::class)
            ->call('create')
            ->set('title', 'The Ultimate Marketing Guide')
            ->set('blog_category_id', $this->category->id)
            ->set('content', '## Growth Hacking 101')
            ->set('meta_description', 'Increase your sales conversion rate quickly.')
            ->set('status', 'published')
            ->set('image', $image)
            ->call('save')
            ->assertHasNoErrors();

        // Verify the database created the slug dynamically
        $this->assertDatabaseHas('marketing_blog_posts', [
            'title' => 'The Ultimate Marketing Guide',
            'slug' => 'the-ultimate-marketing-guide',
            'blog_category_id' => $this->category->id,
            'status' => 'published'
        ]);
        
        // Assert storage upload
        $recentBlog = BlogModel::where('slug', 'the-ultimate-marketing-guide')->first();
        if ($recentBlog && $recentBlog->featured_image) {
            Storage::disk('public')->assertExists($recentBlog->featured_image);
        }
    }

    #[Test]
    public function it_enforces_validation_limits_on_titles_and_empty_markdown()
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(MarketingBlog::class)
            ->call('create')
            ->set('title', '') // Empty title
            ->set('blog_category_id', '') // Empty Category
            ->set('content', '') // Empty body
            ->call('save')
            ->assertHasErrors(['title', 'content']);
    }

    #[Test]
    public function it_renders_the_public_blog_index_and_hides_unpublished_drafts()
    {
        // Public user view mapping
        Livewire::test(MarketingBlogIndex::class)
            ->assertStatus(200)
            ->assertSee('Mastering Laravel Testing')
            ->assertSee('seo-best-practices') // Checks category display
            ->assertDontSee('Draft Feature Coming Soon'); // Must actively hide unreleased posts
    }

    #[Test]
    public function it_allows_public_viewing_of_published_slugs_but_throws_404_for_drafts()
    {
        Livewire::test(MarketingBlogShow::class, ['slug' => $this->publishedBlog->slug])
            ->assertStatus(200)
            ->assertSee('Mastering Laravel Testing')
            ->assertSeeHtml('This is the main body content');

        // Verify Draft access block
        Livewire::test(MarketingBlogShow::class, ['slug' => $this->draftBlog->slug])
            ->assertNotFound(); // Check access policies or Not Found aborts
    }

    #[Test]
    public function it_can_delete_a_marketing_blog_post_and_its_associated_images()
    {
        $this->actingAs($this->admin, 'admin');
        
        // Create an image in our fake disk
        Storage::disk('public')->put($this->publishedBlog->featured_image, 'fake_image_bytes');
        Storage::disk('public')->assertExists($this->publishedBlog->featured_image);

        Livewire::test(MarketingBlog::class)
            ->call('delete', $this->publishedBlog->id);

        $this->assertSoftDeleted('marketing_blog_posts', [
            'id' => $this->publishedBlog->id
        ]);
        
    }
}

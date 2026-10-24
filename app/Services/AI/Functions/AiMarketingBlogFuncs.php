<?php

namespace App\Services\AI\Functions;

use App\Models\Marketing\MarketingBlogCategory;
use App\Models\Marketing\MarketingBlogPost;
use Illuminate\Support\Str;

trait AiMarketingBlogFuncs
{
    public static function getAiMarketingBlogFuncsSchema(): array
    {
        return [
            [
                'name' => 'marketing_blog_get_posts',
                'description' => 'Gibt alle Blogbeiträge zurück. Optional filterbar nach Suchbegriff oder Status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Ein optionaler Suchbegriff für den Titel.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Filter nach Status: "draft", "published", oder "scheduled".'
                        ]
                    ]
                ],
                'callable' => [self::class, 'marketing_blog_get_posts']
            ],
            [
                'name' => 'marketing_blog_create_post',
                'description' => 'Erstellt einen neuen Blogbeitrag.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel des Blogbeitrags.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der gesamte HTML-Inhalt des Beitrags.'
                        ],
                        'excerpt' => [
                            'type' => 'string',
                            'description' => 'Eine kurze Vorschau/Zusammenfassung für Listen.'
                        ],
                        'blog_category_id' => [
                            'type' => 'integer',
                            'description' => 'Optionale ID der Kategorie.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Der Veröffentlichungsstatus: "draft", "published", "scheduled". Standard ist "draft".'
                        ],
                        'meta_title' => [
                            'type' => 'string',
                            'description' => 'Spezieller SEO-Titel. Wenn leer, wird der normale Titel genutzt.'
                        ],
                        'meta_description' => [
                            'type' => 'string',
                            'description' => 'Spezielle SEO-Beschreibung.'
                        ]
                    ],
                    'required' => ['title', 'content', 'excerpt']
                ],
                'callable' => [self::class, 'marketing_blog_create_post']
            ],
            [
                'name' => 'marketing_blog_update_post',
                'description' => 'Aktualisiert einen bestehenden Blogbeitrag.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Die ID des zu aktualisierenden Blogbeitrags.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der neue Titel.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der neue Content.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Der neue Status ("draft", "published", "scheduled").'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'marketing_blog_update_post']
            ],
            [
                'name' => 'marketing_blog_delete_post',
                'description' => 'Löscht einen Blogbeitrag dauerhaft.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Die ID des Blogbeitrags.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'marketing_blog_delete_post']
            ],
            [
                'name' => 'marketing_blog_get_categories',
                'description' => 'Ruft eine Liste aller Blog-Kategorien ab.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'marketing_blog_get_categories']
            ],
            [
                'name' => 'marketing_blog_create_category',
                'description' => 'Legt eine neue Blog-Kategorie an.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Der Name der neuen Kategorie.'
                        ]
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'marketing_blog_create_category']
            ]
        ];
    }

    private static function assertBlogStaffAccess(): ?array
    {
        if (\App\Services\AI\AiAuthHelper::check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return ['status' => 'error', 'message' => 'Zugriff verweigert: Blog-Verwaltung ist nur für interne Mitarbeiter und Administratoren freigegeben.'];
        }
        return null;
    }

    public static function marketing_blog_get_posts(array $args)
    {
        if ($denied = self::assertBlogStaffAccess()) return $denied;

        $query = MarketingBlogPost::with('category');

        if (!empty($args['search'])) {
            $escaped = addcslashes(trim($args['search']), '%_\\');
            $query->where('title', 'like', '%' . $escaped . '%');
        }
        if (!empty($args['status'])) {
            $status = strtolower(trim($args['status']));
            if (in_array($status, ['draft', 'published', 'scheduled'], true)) {
                $query->where('status', $status);
            }
        }

        $posts = $query->orderByDesc('created_at')->limit(30)->get();

        return [
            'status' => 'success',
            'count' => $posts->count(),
            'posts' => $posts->toArray()
        ];
    }

    public static function marketing_blog_create_post(array $args)
    {
        if ($denied = self::assertBlogStaffAccess()) return $denied;

        $user = auth()->user();
        $admin = ($user instanceof \App\Models\Admin\Admin) ? $user : \App\Models\Admin\Admin::first();
        if (!$admin) {
            return ['status' => 'error', 'message' => 'Kein Admin-Benutzer im System gefunden.'];
        }

        $title = trim($args['title'] ?? '');
        if (empty($title)) {
            return ['status' => 'error', 'message' => 'Titel darf nicht leer sein.'];
        }

        $baseSlug = Str::slug($title) ?: 'blog-post';
        $slug = $baseSlug;
        $counter = 1;
        while (MarketingBlogPost::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $status = strtolower(trim($args['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'published', 'scheduled'], true)) {
            $status = 'draft';
        }

        $post = MarketingBlogPost::create([
            'title' => substr($title, 0, 255),
            'slug' => $slug,
            'content' => \App\Livewire\Shop\Marketing\MarketingBlog::sanitizeBlogHtml((string) ($args['content'] ?? '')),
            'excerpt' => isset($args['excerpt']) ? substr($args['excerpt'], 0, 1000) : null,
            'blog_category_id' => $args['blog_category_id'] ?? null,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'meta_title' => isset($args['meta_title']) ? substr($args['meta_title'], 0, 255) : null,
            'meta_description' => isset($args['meta_description']) ? substr($args['meta_description'], 0, 500) : null,
            'is_advertisement' => false,
            'contains_affiliate_links' => false,
            'user_id' => $admin->id
        ]);

        return ['status' => 'success', 'message' => 'Blogbeitrag erstellt.', 'id' => $post->id];
    }

    public static function marketing_blog_update_post(array $args)
    {
        if ($denied = self::assertBlogStaffAccess()) return $denied;

        $post = MarketingBlogPost::find($args['id']);
        if (!$post) {
            return ['status' => 'error', 'message' => 'Blogbeitrag nicht gefunden.'];
        }

        if (isset($args['title'])) {
            $title = trim($args['title']);
            $post->title = substr($title, 0, 255);
            $baseSlug = Str::slug($title) ?: 'blog-post';
            $slug = $baseSlug;
            $counter = 1;
            while (MarketingBlogPost::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $post->slug = $slug;
        }
        if (isset($args['content'])) {
            $post->content = \App\Livewire\Shop\Marketing\MarketingBlog::sanitizeBlogHtml((string) $args['content']);
        }
        if (isset($args['status'])) {
            $status = strtolower(trim($args['status']));
            if (in_array($status, ['draft', 'published', 'scheduled'], true)) {
                $post->status = $status;
                if ($status === 'published' && !$post->published_at) {
                    $post->published_at = now();
                }
            }
        }

        $post->save();

        return ['status' => 'success', 'message' => 'Blogbeitrag aktualisiert.', 'id' => $post->id];
    }

    public static function marketing_blog_delete_post(array $args)
    {
        if ($denied = self::assertBlogStaffAccess()) return $denied;

        $post = MarketingBlogPost::find($args['id']);
        if (!$post) return ['status' => 'error', 'message' => 'Blogbeitrag nicht gefunden.'];

        $post->delete();
        return ['status' => 'success', 'message' => 'Blogbeitrag entfernt.'];
    }

    public static function marketing_blog_get_categories(array $args)
    {
        if ($denied = self::assertBlogStaffAccess()) return $denied;

        $cats = MarketingBlogCategory::all();
        return [
            'status' => 'success',
            'product_categories' => $cats->toArray()
        ];
    }

    public static function marketing_blog_create_category(array $args)
    {
        if ($denied = self::assertBlogStaffAccess()) return $denied;

        $name = trim($args['name'] ?? '');
        if (empty($name)) {
            return ['status' => 'error', 'message' => 'Kategoriename darf nicht leer sein.'];
        }

        if (MarketingBlogCategory::where('name', $name)->exists()) {
            return ['status' => 'error', 'message' => 'Kategorie existiert bereits.'];
        }

        $baseSlug = Str::slug($name) ?: 'kategorie';
        $slug = $baseSlug;
        $counter = 1;
        while (MarketingBlogCategory::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $cat = MarketingBlogCategory::create([
            'name' => substr($name, 0, 255),
            'slug' => $slug
        ]);

        return ['status' => 'success', 'message' => 'Kategorie erstellt.', 'id' => $cat->id];
    }
}

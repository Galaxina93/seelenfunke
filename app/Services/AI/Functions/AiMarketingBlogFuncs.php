<?php

namespace App\Services\AI\Functions;

use App\Models\Marketing\Blog\BlogPost;
use App\Models\Marketing\Blog\BlogCategory;
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

    public static function marketing_blog_get_posts(array $args)
    {
        $query = BlogPost::with('category');
        
        if (!empty($args['search'])) {
            $query->where('title', 'like', '%' . $args['search'] . '%');
        }
        if (!empty($args['status'])) {
            $query->where('status', $args['status']);
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
        $post = BlogPost::create([
            'title' => $args['title'],
            'slug' => Str::slug($args['title']),
            'content' => $args['content'],
            'excerpt' => $args['excerpt'],
            'blog_category_id' => $args['blog_category_id'] ?? null,
            'status' => $args['status'] ?? 'draft',
            'published_at' => ($args['status'] ?? 'draft') === 'published' ? now() : null,
            'meta_title' => $args['meta_title'] ?? null,
            'meta_description' => $args['meta_description'] ?? null,
            'is_advertisement' => false,
            'contains_affiliate_links' => false,
            'user_id' => 1 // SysAdmin Fallback
        ]);

        return ['status' => 'success', 'message' => 'Blogbeitrag erstellt.', 'id' => $post->id];
    }

    public static function marketing_blog_update_post(array $args)
    {
        $post = BlogPost::find($args['id']);
        if (!$post) {
            return ['status' => 'error', 'message' => 'Blogbeitrag nicht gefunden.'];
        }

        if (isset($args['title'])) {
            $post->title = $args['title'];
            $post->slug = Str::slug($args['title']);
        }
        if (isset($args['content'])) $post->content = $args['content'];
        if (isset($args['status'])) {
            $post->status = $args['status'];
            if ($args['status'] === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        }
        
        $post->save();

        return ['status' => 'success', 'message' => 'Blogbeitrag aktualisiert.', 'id' => $post->id];
    }

    public static function marketing_blog_delete_post(array $args)
    {
        $post = BlogPost::find($args['id']);
        if (!$post) return ['status' => 'error', 'message' => 'Blogbeitrag nicht gefunden.'];

        $post->delete();
        return ['status' => 'success', 'message' => 'Blogbeitrag entfernt.'];
    }

    public static function marketing_blog_get_categories(array $args)
    {
        $cats = BlogCategory::all();
        return [
            'status' => 'success',
            'categories' => $cats->toArray()
        ];
    }

    public static function marketing_blog_create_category(array $args)
    {
        if (BlogCategory::where('name', $args['name'])->exists()) {
            return ['status' => 'error', 'message' => 'Kategorie existiert bereits.'];
        }

        $cat = BlogCategory::create([
            'name' => $args['name'],
            'slug' => Str::slug($args['name'])
        ]);

        return ['status' => 'success', 'message' => 'Kategorie erstellt.', 'id' => $cat->id];
    }
}

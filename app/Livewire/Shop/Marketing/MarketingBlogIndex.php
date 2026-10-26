<?php

namespace App\Livewire\Shop\Marketing;

use App\Models\Marketing\MarketingBlogCategory;
use App\Models\Marketing\MarketingBlogPost;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Magazin & Inspiration - Mein Seelenfunke')]
class MarketingBlogIndex extends Component
{
    use WithPagination;

    #[Url(as: 'suche')]
    public $search = '';

    #[Url(as: 'kategorie')]
    public $categorySlug = '';

    // Reset der Pagination bei neuer Suche
    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategorySlug()
    {
        $this->resetPage();
    }

    public function setCategory($slug)
    {
        $this->categorySlug = $slug === $this->categorySlug ? '' : $slug;
        $this->resetPage();
    }

    public function render()
    {
        $query = MarketingBlogPost::query()
            ->published() // Nutzt den Scope aus dem Model (nur veröffentlichte)
            ->with(['author', 'category']) // Eager Loading für Performance
            ->latest('published_at');

        // Suche
        if ($this->search) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function($q) use ($escapedSearch) {
                $q->where('title', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('content', 'like', '%' . $escapedSearch . '%');
            });
        }

        // Kategorie Filter
        if ($this->categorySlug) {
            $query->whereHas('category', function($q) {
                $q->where('slug', $this->categorySlug);
            });
        }

        $posts = $query->paginate(9);
        $categories = MarketingBlogCategory::whereIn('id', MarketingBlogPost::published()->whereNotNull('blog_category_id')->pluck('blog_category_id'))->orderBy('name')->get();

        $blogTitle = 'Magazin, Geschenk-Ratgeber & Lasergravur-Inspiration | Mein Seelenfunke';
        $blogMetaDesc = 'Inspiration, Geschenkideen, Ratgeber zur Lasergravur auf K9-Kristallglas & Edelstahl sowie Einblicke hinter die Kulissen der Manufaktur Mein Seelenfunke.';
        $canonicalUrl = route('marketing/marketing/blog');

        if (!empty($this->categorySlug)) {
            $activeCat = $categories->firstWhere('slug', $this->categorySlug);
            if ($activeCat) {
                $blogTitle = "{$activeCat->name} – Magazin & Ratgeber | Mein Seelenfunke";
                $blogMetaDesc = "Alle Beiträge, Ratgeber und Hintergrundberichte in der Kategorie {$activeCat->name} aus der Manufaktur Mein Seelenfunke in Gifhorn.";
                $canonicalUrl = route('marketing/marketing/blog', ['kategorie' => $activeCat->slug]);
            }
        }

        $blogPostItems = [];
        foreach ($posts->items() as $post) {
            $imgPath = $post->header_image ?: $post->featured_image;
            $blogPostItems[] = [
                '@type' => 'BlogPosting',
                'headline' => $post->title,
                'url' => route('blog.show', $post->slug),
                'datePublished' => optional($post->published_at ?? $post->created_at)->toAtomString(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $post->display_author ?: shop_setting('owner_proprietor', 'Alina Steinhauer'),
                ],
                'image' => $imgPath ? asset('storage/' . ltrim($imgPath, '/')) : asset('shop/projekt/logo/mein-seelenfunke-logo.png'),
            ];
        }

        $blogIndexSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $canonicalUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Startseite',
                            'item' => route('home'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Magazin & Ratgeber',
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
                [
                    '@type' => 'Blog',
                    '@id' => route('marketing/marketing/blog') . '#blog',
                    'url' => $canonicalUrl,
                    'name' => $blogTitle,
                    'description' => $blogMetaDesc,
                    'inLanguage' => 'de-DE',
                    'publisher' => [
                        '@id' => url('/#organization'),
                    ],
                    'blogPost' => $blogPostItems,
                ],
            ],
        ];

        return view('livewire.shop.marketing.marketing-blog-index', [
            'posts' => $posts,
            'categories' => $categories,
            'categorySlug' => $this->categorySlug,
            'blogIndexSchema' => $blogIndexSchema,
        ])
            ->title($blogTitle)
            ->layout('components.layouts.frontend_layout', [
                'title' => $blogTitle,
                'meta_description' => $blogMetaDesc,
                'og_title' => $blogTitle,
                'og_description' => $blogMetaDesc,
                'canonical' => $canonicalUrl,
            ]);
    }
}

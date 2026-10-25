<?php

namespace App\Livewire\Shop\Marketing;

use App\Models\Marketing\MarketingBlogPost;
use App\Models\Product\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

class MarketingBlogShow extends Component
{
    public $post;

    public function mount($slug)
    {
        // Wir suchen den Post anhand des Slugs.
        // WICHTIG: Wir nutzen auch hier ->published(), damit niemand unveröffentlichte Posts erraten kann.
        $this->post = MarketingBlogPost::published()
            ->where('slug', $slug)
            ->with(['author', 'category'])
            ->first();

        if (!$this->post) {
            abort(404);
        }
    }

    public function render()
    {
        $seoTitle = $this->post->seo_title . ' – Mein Seelenfunke Magazin';
        $metaDescription = $this->post->meta_description ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->post->excerpt ?: $this->post->content)))), 155);
        $canonicalUrl = route('blog.show', $this->post->slug);
        $imgPath = $this->post->header_image ?: $this->post->featured_image;
        $ogImage = $imgPath ? asset('storage/' . ltrim($imgPath, '/')) : asset('shop/projekt/logo/mein-seelenfunke-logo.png');

        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->post->content)));
        $wordCount = $plainText !== '' ? count(preg_split('/\s+/u', $plainText, -1, PREG_SPLIT_NO_EMPTY)) : 0;
        $readingTimeMinutes = max(1, (int) ceil($wordCount / 200));

        $recommendedProducts = Schema::hasTable('products')
            ? Product::where('status', 'active')
                ->where('slug', '!=', 'geschenkgutschein')
                ->with(['categories', 'templates' => fn ($q) => $q->where('is_active', true)])
                ->latest()
                ->take(3)
                ->get()
            : collect();

        $blogSchema = [
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
                            'name' => 'Magazin',
                            'item' => route('marketing/marketing/blog'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'name' => $this->post->title,
                            'item' => $canonicalUrl,
                        ],
                    ],
                ],
                [
                    '@type' => 'BlogPosting',
                    '@id' => $canonicalUrl . '#article',
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id' => $canonicalUrl,
                    ],
                    'headline' => $this->post->title,
                    'description' => $metaDescription,
                    'image' => [$ogImage],
                    'inLanguage' => 'de-DE',
                    'wordCount' => $wordCount,
                    'timeRequired' => 'PT' . $readingTimeMinutes . 'M',
                    'datePublished' => optional($this->post->published_at ?? $this->post->created_at)->toAtomString(),
                    'dateModified' => optional($this->post->updated_at ?? $this->post->published_at ?? $this->post->created_at)->toAtomString(),
                    'author' => [
                        '@type' => 'Person',
                        'name' => $this->post->display_author ?: shop_setting('owner_proprietor', 'Alina Steinhauer'),
                    ],
                    'publisher' => [
                        '@id' => url('/#organization'),
                    ],
                    'articleSection' => $this->post->category?->name ?? 'Magazin',
                ],
            ],
        ];

        return view('livewire.shop.marketing.marketing-blog-show', [
            'blogSchema' => $blogSchema,
            'readingTimeMinutes' => $readingTimeMinutes,
            'recommendedProducts' => $recommendedProducts,
        ])
            ->title($seoTitle)
            ->layout('components.layouts.frontend_layout', [
                'meta_description' => $metaDescription,
                'og_title' => $seoTitle,
                'og_description' => $metaDescription,
                'og_image' => $ogImage,
                'og_type' => 'article',
                'canonical' => $canonicalUrl,
            ]);
    }
}

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

        return view('livewire.shop.marketing.marketing-blog-index', [
            'posts' => $query->paginate(9),
            'categories' => MarketingBlogCategory::whereIn('id', MarketingBlogPost::published()->whereNotNull('blog_category_id')->pluck('blog_category_id'))->orderBy('name')->get(), // Nur Kategorien mit veröffentlichten Posts
            'categorySlug' => $this->categorySlug,
        ])->layout('components.layouts.frontend_layout', [
            'meta_description' => 'Inspiration, Geschenkideen, Ratgeber zur Lasergravur und spannende Einblicke hinter die Kulissen der Manufaktur Mein Seelenfunke.',
            'canonical' => route('marketing/marketing/blog'),
        ]);
    }
}

<?php

namespace App\Livewire\Shop\Marketing;

use App\Models\Marketing\Blog\BlogCategory;
use App\Models\Marketing\Blog\BlogPost;
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
        $query = BlogPost::query()
            ->published() // Nutzt den Scope aus dem Model (nur veröffentlichte)
            ->with(['author', 'category']) // Eager Loading für Performance
            ->latest('published_at');

        // Suche
        if ($this->search) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('content', 'like', '%' . $this->search . '%');
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
            'categories' => BlogCategory::has('posts')->orderBy('name')->get() // Nur Kategorien mit Posts
        ])->layout('components.layouts.frontend_layout');
    }
}

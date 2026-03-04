<?php

namespace App\Livewire\Admin;

use App\Models\FunkiKnowledgeBase as KB;
use Livewire\Component;

class FunkiKnowledgeBase extends Component
{
    public $search = '';
    public $selectedCategory = '';
    public $activeArticleId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => ''],
    ];

    public function selectArticle($id)
    {
        $this->activeArticleId = $id;
    }

    public function setCategory($category)
    {
        $this->selectedCategory = $category;
        $this->activeArticleId = null; // Reset article when category changes
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedCategory = '';
        $this->activeArticleId = null;
    }

    public function render()
    {
        $categories = KB::where('is_published', true)
            ->select('category')
            ->distinct()
            ->pluck('category');

        $query = KB::where('is_published', true);

        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('content', 'like', '%' . $this->search . '%')
                    ->orWhereJsonContains('tags', $this->search);
            });
        }

        $articles = $query->orderBy('title')->get();

        // Wenn kein Artikel ausgewählt ist, aber Artikel existieren, nimm den ersten
        if (!$this->activeArticleId && $articles->isNotEmpty()) {
            $this->activeArticleId = $articles->first()->id;
        }

        $activeArticle = $this->activeArticleId ? KB::find($this->activeArticleId) : null;

        return view('livewire.admin.funki-knowledge-base', [
            'categories' => $categories,
            'articles' => $articles,
            'activeArticle' => $activeArticle,
        ]);
    }
}

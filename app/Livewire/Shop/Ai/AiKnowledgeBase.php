<?php

namespace App\Livewire\Shop\Ai;

use App\Models\Ai\AiKnowledgeBase as KB;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class AiKnowledgeBase extends Component
{
    public string $themingDepartment = 'Agenten';
    use WithFileUploads;

    public $search = '';
    public $selectedCategoryId = null;
    public $activeArticleId = null;

    public $wikiFiles = []; // For dragging & dropping files

    public $uploadedWikiFiles = []; // List of existing files

    public $isEditing = false;
    public $editForm = ['id' => null, 'title' => '', 'ai_knowledge_base_category_id' => null, 'tags' => [], 'content' => ''];

    protected $rules = [
        'editForm.title' => 'required|string|max:255',
        'editForm.ai_knowledge_base_category_id' => 'required|exists:ai_knowledge_base_categories,id',
        'editForm.content' => 'required|string',
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategoryId' => ['except' => null],
    ];

    #[On('ai-knowledge-base-category-selected')]
    public function setFormCategory($id)
    {
        $this->editForm['ai_knowledge_base_category_id'] = $id;
    }

    #[On('ai-knowledge-base-tags-updated')]
    public function setFormTags($ids)
    {
        $this->editForm['tags'] = $ids;
    }

    public function selectArticle($id)
    {
        $this->activeArticleId = $id;
        $this->isEditing = false;
    }

    public function setCategory($categoryId)
    {
        $this->selectedCategoryId = $categoryId;
        $this->activeArticleId = null; // Reset article when category changes
        $this->isEditing = false;
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedCategoryId = null;
        $this->activeArticleId = null;
        $this->isEditing = false;
    }

    public function createNewArticle()
    {
        $this->activeArticleId = null;
        $this->isEditing = true;
        $this->editForm = [
            'id' => null,
            'title' => '',
            'ai_knowledge_base_category_id' => $this->selectedCategoryId,
            'tags' => [],
            'content' => ''
        ];
    }

    public function editArticle($id)
    {
        $article = KB::with('tags')->findOrFail($id);
        $this->activeArticleId = $article->id;
        $this->isEditing = true;

        $this->editForm = [
            'id' => $article->id,
            'title' => $article->title,
            'ai_knowledge_base_category_id' => $article->ai_knowledge_base_category_id,
            'tags' => $article->tags->pluck('id')->toArray(),
            'content' => $article->content
        ];
    }

    public function cancelEditing()
    {
        $this->isEditing = false;
    }

    public function saveArticle()
    {
        $this->validate();

        if ($this->editForm['id']) {
            $article = KB::findOrFail($this->editForm['id']);
            $article->update([
                'title' => $this->editForm['title'],
                'ai_knowledge_base_category_id' => $this->editForm['ai_knowledge_base_category_id'],
                'content' => $this->editForm['content'],
            ]);
            $article->tags()->sync($this->editForm['tags']);
            session()->flash('success', 'Eintrag erfolgreich aktualisiert.');
        } else {
            $article = KB::create([
                'title' => $this->editForm['title'],
                'slug' => \Illuminate\Support\Str::slug($this->editForm['title']) . '-' . rand(1000, 9999),
                'ai_knowledge_base_category_id' => $this->editForm['ai_knowledge_base_category_id'],
                'content' => $this->editForm['content'],
                'is_published' => true,
            ]);
            if (!empty($this->editForm['tags'])) {
                $article->tags()->attach($this->editForm['tags']);
            }
            $this->activeArticleId = $article->id;
            session()->flash('success', 'Neuer Eintrag erfolgreich angelegt.');
        }

        $this->isEditing = false;
    }

    public function deleteArticle($id)
    {
        $article = KB::findOrFail($id);
        $article->delete();

        if ($this->activeArticleId == $id) {
            $this->activeArticleId = null;
            $this->isEditing = false;
        }

        session()->flash('success', 'Eintrag wurde gelöscht.');
    }

    public function mount()
    {
        $this->loadUploadedFiles();
    }

    public function updatedWikiFiles()
    {
        $this->validate([
            'wikiFiles.*' => 'max:10240', // 10MB Max per file
        ]);

        foreach ($this->wikiFiles as $file) {
            $filename = $file->getClientOriginalName();
            $file->storeAs('public/wiki', $filename);
        }

        $this->wikiFiles = []; // Clear current selection
        $this->loadUploadedFiles();

        session()->flash('message', 'Datei(en) erfolgreich für die KI hochgeladen.');
    }

    public function deleteWikiFile($filename)
    {
        if (Storage::disk('public')->exists('wiki/' . $filename)) {
            Storage::disk('public')->delete('wiki/' . $filename);
            $this->loadUploadedFiles();
            session()->flash('message', 'Datei erfolgreich gelöscht.');
        }
    }

    public function loadUploadedFiles()
    {
        if (!Storage::disk('public')->exists('wiki')) {
            Storage::disk('public')->makeDirectory('wiki');
        }

        $files = Storage::disk('public')->files('wiki');
        $this->uploadedWikiFiles = array_map(function($path) {
            return [
                'name' => basename($path),
                'size' => Storage::disk('public')->size($path),
                'url' => Storage::url($path),
                'time' => Storage::disk('public')->lastModified($path) // To sort
            ];
        }, $files);

        // Sort newest first
        usort($this->uploadedWikiFiles, function($a, $b) {
            return $b['time'] <=> $a['time'];
        });
    }

    public function render()
    {
        $categories = AiKnowledgeBaseCategory::orderBy('name')->get();

        $query = KB::with(['category', 'tags'])->where('is_published', true);

        if ($this->selectedCategoryId) {
            $query->where('ai_knowledge_base_category_id', $this->selectedCategoryId);
        }

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('content', 'like', '%' . $this->search . '%')
                    ->orWhereHas('tags', function ($t) {
                        $t->where('name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        $articles = $query->orderBy('title')->get();

        // Wenn kein Artikel ausgewählt ist, aber Artikel existieren, nimm den ersten
        if (!$this->activeArticleId && $articles->isNotEmpty()) {
            $this->activeArticleId = $articles->first()->id;
        }

        $activeArticle = $this->activeArticleId ? KB::with(['category', 'tags'])->find($this->activeArticleId) : null;

        return view('livewire.shop.ai.ai-knowledge-base', [
            'categories' => $categories,
            'articles' => $articles,
            'activeArticle' => $activeArticle,
        ]);
    }
}

<?php

namespace App\Livewire\Global\Widgets;

use App\Models\KnowledgeBase as KB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class KnowledgeBase extends Component
{
    use WithFileUploads;

    public $search = '';
    public $selectedCategory = '';
    public $activeArticleId = null;

    public $wikiFiles = []; // For dragging & dropping files
    
    public $uploadedWikiFiles = []; // List of existing files

    public $isEditing = false;
    public $editForm = ['id' => null, 'title' => '', 'category' => '', 'tags' => '', 'content' => ''];

    protected $rules = [
        'editForm.title' => 'required|string|max:255',
        'editForm.category' => 'required|string|max:100',
        'editForm.content' => 'required|string',
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => ''],
    ];

    public function selectArticle($id)
    {
        $this->activeArticleId = $id;
        $this->isEditing = false;
    }

    public function setCategory($category)
    {
        $this->selectedCategory = $category;
        $this->activeArticleId = null; // Reset article when category changes
        $this->isEditing = false;
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedCategory = '';
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
            'category' => $this->selectedCategory ?: 'Allgemein',
            'tags' => '',
            'content' => ''
        ];
    }

    public function editArticle($id)
    {
        $article = KB::findOrFail($id);
        $this->activeArticleId = $article->id;
        $this->isEditing = true;
        
        $this->editForm = [
            'id' => $article->id,
            'title' => $article->title,
            'category' => $article->category,
            'tags' => $article->tags ? implode(', ', $article->tags) : '',
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

        $tagsArray = array_filter(array_map('trim', explode(',', $this->editForm['tags'])));

        if ($this->editForm['id']) {
            $article = KB::findOrFail($this->editForm['id']);
            $article->update([
                'title' => $this->editForm['title'],
                'category' => $this->editForm['category'],
                'tags' => $tagsArray,
                'content' => $this->editForm['content'],
            ]);
            session()->flash('success', 'Eintrag erfolgreich aktualisiert.');
        } else {
            $article = KB::create([
                'title' => $this->editForm['title'],
                'slug' => \Illuminate\Support\Str::slug($this->editForm['title']) . '-' . rand(1000, 9999),
                'category' => $this->editForm['category'],
                'tags' => $tagsArray,
                'content' => $this->editForm['content'],
                'is_published' => true,
            ]);
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

        return view('livewire.admin.knowledge-base', [
            'categories' => $categories,
            'articles' => $articles,
            'activeArticle' => $activeArticle,
        ]);
    }
}

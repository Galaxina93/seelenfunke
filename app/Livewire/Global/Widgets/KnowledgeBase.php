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

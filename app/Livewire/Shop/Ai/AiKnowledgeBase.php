<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use App\Models\Ai\AiKnowledgeBase as KB;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class AiKnowledgeBase extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    use WithFileUploads;

    public $search = '';
    public $selectedCategoryId = null;
    #[\Livewire\Attributes\Locked]
    public $activeArticleId = null;

    public $wikiFiles = []; // For dragging & dropping files

    public $uploadedWikiFiles = []; // List of existing files

    public $isEditing = false;
    public $editForm = ['id' => null, 'title' => '', 'ai_knowledge_base_category_id' => null, 'tags' => [], 'content' => ''];

    protected $rules = [
        'editForm.title' => 'required|string|max:255',
        'editForm.ai_knowledge_base_category_id' => 'required|exists:ai_knowledge_base_categories,id',
        'editForm.content' => 'required|string|max:100000',
        'editForm.tags' => 'nullable|array|max:30',
        'editForm.tags.*' => 'integer|exists:ai_knowledge_base_tags,id',
    ];

    public const ADMIN_ONLY_CATEGORY_SLUGS = [
        'ai-memory',
        'gesundheit-sozialrecht-bkk-firmus',
        'existenzgruendung-arbeitsagentur',
        'steuern-buchhaltung',
        'workspace-dokumentenkatalog',
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategoryId' => ['except' => null],
    ];

    protected function isRestrictedCategory(?string $slug): bool
    {
        if (Auth::guard('admin')->check()) {
            return false;
        }

        return $slug !== null && in_array($slug, self::ADMIN_ONLY_CATEGORY_SLUGS, true);
    }

    protected function assertCanAccessArticle(KB $article): void
    {
        $article->loadMissing('category');
        if ($this->isRestrictedCategory($article->category?->slug)) {
            abort(403);
        }
    }

    protected function assertCanAccessCategory($categoryId): void
    {
        if (!$categoryId || Auth::guard('admin')->check()) {
            return;
        }

        $category = AiKnowledgeBaseCategory::find($categoryId);
        if ($category && $this->isRestrictedCategory($category->slug)) {
            abort(403);
        }
    }

    public static function sanitizeKnowledgeHtml(string $html): string
    {
        $clean = preg_replace('/<\s*(script|iframe|object|embed|applet|meta|base|form)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $clean = preg_replace('/<\s*\/?\s*(script|iframe|object|embed|applet|meta|base|form)\b[^>]*>/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\b(href|src|action|formaction|xlink:href)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data\s*:\s*text\/html)[^"\'>\s]*\2/i', '$1="#"', $clean) ?? $clean;

        return $clean;
    }

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $this->loadUploadedFiles();
    }

    #[On('ai-knowledge-base-category-selected')]
    public function setFormCategory($id)
    {
        $this->assertCanAccessCategory($id);
        $this->editForm['ai_knowledge_base_category_id'] = $id;
    }

    #[On('ai-knowledge-base-tags-updated')]
    public function setFormTags($ids)
    {
        if (!is_array($ids)) {
            $this->editForm['tags'] = [];
            return;
        }
        $cleanIds = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, 30);
        $this->editForm['tags'] = AiKnowledgeBaseTag::whereIn('id', $cleanIds)->pluck('id')->toArray();
    }

    public function selectArticle($id)
    {
        $article = KB::with('category')->findOrFail($id);
        $this->assertCanAccessArticle($article);
        $this->activeArticleId = $article->id;
        $this->isEditing = false;
    }

    public function setCategory($categoryId)
    {
        $this->assertCanAccessCategory($categoryId);
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
        $article = KB::with(['tags', 'category'])->findOrFail($id);
        $this->assertCanAccessArticle($article);
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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $this->validate();
        $this->assertCanAccessCategory($this->editForm['ai_knowledge_base_category_id']);

        $sanitizedContent = self::sanitizeKnowledgeHtml((string) $this->editForm['content']);
        $this->editForm['content'] = $sanitizedContent;

        if ($this->editForm['id']) {
            $article = KB::with('category')->findOrFail($this->editForm['id']);
            $this->assertCanAccessArticle($article);
            $article->update([
                'title' => $this->editForm['title'],
                'ai_knowledge_base_category_id' => $this->editForm['ai_knowledge_base_category_id'],
                'content' => $sanitizedContent,
            ]);
            $article->tags()->sync($this->editForm['tags']);
            session()->flash('success', 'Eintrag erfolgreich aktualisiert.');
        } else {
            $article = KB::create([
                'title' => $this->editForm['title'],
                'slug' => \Illuminate\Support\Str::slug($this->editForm['title']) . '-' . rand(1000, 9999),
                'ai_knowledge_base_category_id' => $this->editForm['ai_knowledge_base_category_id'],
                'content' => $sanitizedContent,
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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $article = KB::with('category')->findOrFail($id);
        $this->assertCanAccessArticle($article);
        $article->delete();

        if ($this->activeArticleId == $id) {
            $this->activeArticleId = null;
            $this->isEditing = false;
        }

        session()->flash('success', 'Eintrag wurde gelöscht.');
    }

    public function updatedWikiFiles()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $this->validate([
            'wikiFiles' => 'required|array|min:1|max:20',
            'wikiFiles.*' => 'required|file|mimes:pdf,txt,md,docx,png,jpg,jpeg|max:10240',
        ]);

        $allowedWikiExts = ['pdf', 'txt', 'md', 'docx', 'png', 'jpg', 'jpeg'];
        $allowedWikiMimes = [
            'application/pdf',
            'text/plain',
            'text/markdown',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/png',
            'image/jpeg',
        ];
        foreach ($this->wikiFiles as $file) {
            $realPath = $file->getRealPath();
            if ($realPath && is_file($realPath)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo ? finfo_file($finfo, $realPath) : false;
                if ($finfo) {
                    finfo_close($finfo);
                }
                if (!in_array($detectedMime, $allowedWikiMimes, true)) {
                    continue;
                }
            }
            $cleanName = basename((string) $file->getClientOriginalName());
            $ext = strtolower(pathinfo($cleanName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedWikiExts, true)) {
                $guessed = strtolower((string) $file->guessExtension());
                $ext = in_array($guessed, $allowedWikiExts, true) ? $guessed : 'txt';
            }
            $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) pathinfo($cleanName, PATHINFO_FILENAME));
            $base = trim((string) $base, '._-') ?: ('wiki_' . time());
            $safeName = $base . '.' . $ext;
            $file->storeAs('support/wiki', $safeName, 'public');
        }

        $this->wikiFiles = []; // Clear current selection
        $this->loadUploadedFiles();

        session()->flash('message', 'Datei(en) erfolgreich für die KI hochgeladen.');
    }

    public function deleteWikiFile($filename)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $cleanFilename = basename($filename);
        if (Storage::disk('public')->exists('support/wiki/' . $cleanFilename)) {
            Storage::disk('public')->delete('support/wiki/' . $cleanFilename);
            $this->loadUploadedFiles();
            session()->flash('message', 'Datei erfolgreich gelöscht.');
        }
    }

    public function loadUploadedFiles()
    {
        if (!Storage::disk('public')->exists('support/wiki')) {
            Storage::disk('public')->makeDirectory('support/wiki');
        }

        $files = Storage::disk('public')->files('support/wiki');
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
        $categoriesQuery = AiKnowledgeBaseCategory::orderBy('name');
        if (!Auth::guard('admin')->check()) {
            $categoriesQuery->whereNotIn('slug', self::ADMIN_ONLY_CATEGORY_SLUGS);
        }
        $categories = $categoriesQuery->get();

        $query = KB::with(['category', 'tags'])->where('is_published', true);

        if (!Auth::guard('admin')->check()) {
            $query->whereDoesntHave('category', function ($q) {
                $q->whereIn('slug', self::ADMIN_ONLY_CATEGORY_SLUGS);
            });
        }

        if ($this->selectedCategoryId) {
            $this->assertCanAccessCategory($this->selectedCategoryId);
            $query->where('ai_knowledge_base_category_id', $this->selectedCategoryId);
        }

        if (!empty($this->search)) {
            $escaped = addcslashes((string) $this->search, '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('title', 'like', '%' . $escaped . '%')
                    ->orWhere('content', 'like', '%' . $escaped . '%')
                    ->orWhereHas('tags', function ($t) use ($escaped) {
                        $t->where('name', 'like', '%' . $escaped . '%');
                    });
            });
        }

        $articles = $query->orderBy('title')->get();

        // Wenn kein Artikel ausgewählt ist, aber Artikel existieren, nimm den ersten
        if (!$this->activeArticleId && $articles->isNotEmpty()) {
            $this->activeArticleId = $articles->first()->id;
        }

        $activeArticle = $this->activeArticleId ? KB::with(['category', 'tags'])->find($this->activeArticleId) : null;
        if ($activeArticle && $this->isRestrictedCategory($activeArticle->category?->slug)) {
            $activeArticle = null;
            $this->activeArticleId = null;
        }

        return view('livewire.shop.ai.ai-knowledge-base', [
            'categories' => $categories,
            'articles' => $articles,
            'activeArticle' => $activeArticle,
        ]);
    }
}

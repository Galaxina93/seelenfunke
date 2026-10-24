<?php

namespace App\Livewire\Shop\Ai;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class AiKnowledgeBaseCategories extends Component
{
    public string $themingDepartment = 'Agenten';
    public $categories = [];
    public $search = '';
    public $newCategoryName = '';
    #[\Livewire\Attributes\Locked]
    public $editingCategoryId = null;
    public $editingCategoryName = '';
    public $selectedCategoryId = null;
    public $isManaging = false;

    protected function isRestrictedSlug(?string $slug): bool
    {
        if (Auth::guard('admin')->check()) {
            return false;
        }

        return $slug !== null && in_array($slug, \App\Livewire\Shop\Ai\AiKnowledgeBase::ADMIN_ONLY_CATEGORY_SLUGS, true);
    }

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }
    }

    public function mount($selectedCategoryId = null)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $this->selectedCategoryId = $selectedCategoryId;
    }

    public function toggleManageMode()
    {
        $this->isManaging = !$this->isManaging;
    }

    public function loadCategories()
    {
        $query = \App\Models\Ai\AiKnowledgeBaseCategory::orderBy('name');
        if (!Auth::guard('admin')->check()) {
            $query->whereNotIn('slug', \App\Livewire\Shop\Ai\AiKnowledgeBase::ADMIN_ONLY_CATEGORY_SLUGS);
        }

        if (!empty($this->search)) {
            $escaped = addcslashes((string) $this->search, '%_\\');
            $query->where('name', 'like', '%' . $escaped . '%');
        }

        $this->categories = $query->get()->toArray();
    }

    public function createCategory()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $this->validate(['newCategoryName' => 'required|string|max:100|unique:ai_knowledge_base_categories,name']);
        $targetSlug = \Illuminate\Support\Str::slug($this->newCategoryName);
        if ($this->isRestrictedSlug($targetSlug)) {
            abort(403);
        }

        $cat = \App\Models\Ai\AiKnowledgeBaseCategory::create([
            'name' => $this->newCategoryName,
            'slug' => $targetSlug
        ]);

        $this->newCategoryName = '';
        $this->loadCategories();
        $this->selectCategory($cat->id);
    }

    public function startEditing($id, $name)
    {
        $cat = \App\Models\Ai\AiKnowledgeBaseCategory::find($id);
        if (!$cat || $this->isRestrictedSlug($cat->slug)) {
            abort(403);
        }
        $this->editingCategoryId = $cat->id;
        $this->editingCategoryName = $cat->name;
    }

    public function cancelEditing()
    {
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
    }

    public function updateCategory()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $cat = \App\Models\Ai\AiKnowledgeBaseCategory::find($this->editingCategoryId);
        if (!$cat || $this->isRestrictedSlug($cat->slug)) {
            abort(403);
        }

        $this->validate([
            'editingCategoryName' => [
                'required',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('ai_knowledge_base_categories', 'name')->ignore($cat->id),
            ],
        ]);

        $newSlug = \Illuminate\Support\Str::slug($this->editingCategoryName);
        if ($this->isRestrictedSlug($newSlug) || in_array($cat->slug, \App\Livewire\Shop\Ai\AiKnowledgeBase::ADMIN_ONLY_CATEGORY_SLUGS, true)) {
            if (!Auth::guard('admin')->check()) {
                abort(403);
            }
            // Keep canonical admin-only slug intact even when display name is adjusted
            $newSlug = $cat->slug;
        }

        $cat->update([
            'name' => $this->editingCategoryName,
            'slug' => $newSlug
        ]);
        $this->cancelEditing();
        $this->loadCategories();
    }

    public function deleteCategory($id)
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $cat = \App\Models\Ai\AiKnowledgeBaseCategory::find($id);
        if ($cat) {
            $cat->delete();
        }
        if ($this->selectedCategoryId == $id) {
            $this->selectCategory(null);
        }
        $this->loadCategories();
    }

    public function selectCategory($id)
    {
        if ($id) {
            $cat = \App\Models\Ai\AiKnowledgeBaseCategory::find($id);
            if ($cat && $this->isRestrictedSlug($cat->slug)) {
                abort(403);
            }
        }
        $this->selectedCategoryId = $id;
        $this->dispatch('ai-knowledge-base-category-selected', id: $id);
    }

    public function render()
    {
        $this->loadCategories();
        return view('livewire.shop.ai.ai-knowledge-base-categories');
    }
}

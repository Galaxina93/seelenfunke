<?php

namespace App\Livewire\Shop\Ai;

use Livewire\Component;

class AiKnowledgeBaseCategories extends Component
{
    public string $themingDepartment = 'Agenten';
    public $categories = [];
    public $search = '';
    public $newCategoryName = '';
    public $editingCategoryId = null;
    public $editingCategoryName = '';
    public $selectedCategoryId = null;
    public $isManaging = false;

    public function mount($selectedCategoryId = null)
    {
        $this->selectedCategoryId = $selectedCategoryId;
    }

    public function toggleManageMode()
    {
        $this->isManaging = !$this->isManaging;
    }

    public function loadCategories()
    {
        if (empty($this->search)) {
            $this->categories = \App\Models\Ai\AiKnowledgeBaseCategory::orderBy('name')->get()->toArray();
        } else {
            $this->categories = \App\Models\Ai\AiKnowledgeBaseCategory::where('name', 'like', '%' . $this->search . '%')
                ->orderBy('name')->get()->toArray();
        }
    }

    public function createCategory()
    {
        $this->validate(['newCategoryName' => 'required|string|max:100|unique:ai_knowledge_base_categories,name']);

        $cat = \App\Models\Ai\AiKnowledgeBaseCategory::create([
            'name' => $this->newCategoryName,
            'slug' => \Illuminate\Support\Str::slug($this->newCategoryName)
        ]);

        $this->newCategoryName = '';
        $this->loadCategories();
        $this->selectCategory($cat->id);
    }

    public function startEditing($id, $name)
    {
        $this->editingCategoryId = $id;
        $this->editingCategoryName = $name;
    }

    public function cancelEditing()
    {
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
    }

    public function updateCategory()
    {
        $this->validate(['editingCategoryName' => 'required|string|max:100|unique:ai_knowledge_base_categories,name,' . $this->editingCategoryId]);

        $cat = \App\Models\Ai\AiKnowledgeBaseCategory::find($this->editingCategoryId);
        if ($cat) {
            $cat->update([
                'name' => $this->editingCategoryName,
                'slug' => \Illuminate\Support\Str::slug($this->editingCategoryName)
            ]);
        }
        $this->cancelEditing();
        $this->loadCategories();
    }

    public function deleteCategory($id)
    {
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
        $this->selectedCategoryId = $id;
        $this->dispatch('ai-knowledge-base-category-selected', id: $id);
    }

    public function render()
    {
        $this->loadCategories();
        return view('livewire.shop.ai.ai-knowledge-base-categories');
    }
}

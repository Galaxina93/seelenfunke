<?php

namespace App\Livewire\Shop\Ai;

use Livewire\Component;

class AiKnowledgeBaseTags extends Component
{
    public $tags = [];
    public $search = '';
    public $newTagName = '';
    public $editingTagId = null;
    public $editingTagName = '';
    public $selectedTagIds = [];
    public $isManaging = false;

    public function mount($selectedTagIds = null)
    {
        $this->selectedTagIds = is_array($selectedTagIds) ? $selectedTagIds : (!empty($selectedTagIds) ? [$selectedTagIds] : []);
    }

    public function toggleManageMode()
    {
        $this->isManaging = !$this->isManaging;
    }

    public function loadTags()
    {
        if (empty($this->search)) {
            $this->tags = \App\Models\AiKnowledgeBaseTag::orderBy('name')->get()->toArray();
        } else {
            $this->tags = \App\Models\AiKnowledgeBaseTag::where('name', 'like', '%' . $this->search . '%')
                ->orderBy('name')->get()->toArray();
        }
    }

    public function createTag()
    {
        $this->validate(['newTagName' => 'required|string|max:100|unique:ai_knowledge_base_tags,name']);
        
        $tag = \App\Models\AiKnowledgeBaseTag::create([
            'name' => $this->newTagName,
            'slug' => \Illuminate\Support\Str::slug($this->newTagName)
        ]);
        
        $this->newTagName = '';
        $this->loadTags();
        $this->toggleTag($tag->id);
    }

    public function startEditing($id, $name)
    {
        $this->editingTagId = $id;
        $this->editingTagName = $name;
    }

    public function cancelEditing()
    {
        $this->editingTagId = null;
        $this->editingTagName = '';
    }

    public function updateTag()
    {
        $this->validate(['editingTagName' => 'required|string|max:100|unique:ai_knowledge_base_tags,name,' . $this->editingTagId]);
        
        $tag = \App\Models\AiKnowledgeBaseTag::find($this->editingTagId);
        if ($tag) {
            $tag->update([
                'name' => $this->editingTagName,
                'slug' => \Illuminate\Support\Str::slug($this->editingTagName)
            ]);
        }
        $this->cancelEditing();
        $this->loadTags();
    }

    public function deleteTag($id)
    {
        $tag = \App\Models\AiKnowledgeBaseTag::find($id);
        if ($tag) {
            $tag->delete();
        }
        if (in_array($id, $this->selectedTagIds)) {
            $this->selectedTagIds = array_diff($this->selectedTagIds, [$id]);
            $this->dispatch('ai-knowledge-base-tags-updated', ids: $this->selectedTagIds);
        }
        $this->loadTags();
    }

    public function toggleTag($id)
    {
        if (in_array($id, $this->selectedTagIds)) {
            $this->selectedTagIds = array_diff($this->selectedTagIds, [$id]);
        } else {
            $this->selectedTagIds[] = $id;
        }
        
        $this->selectedTagIds = array_values($this->selectedTagIds);
        $this->dispatch('ai-knowledge-base-tags-updated', ids: $this->selectedTagIds);
    }

    public function render()
    {
        $this->loadTags();
        return view('livewire.shop.ai.ai-knowledge-base-tags');
    }
}

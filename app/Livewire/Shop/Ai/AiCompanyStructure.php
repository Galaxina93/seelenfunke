<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\Attributes\Layout;

use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class AiCompanyStructure extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    public $departments = [];
    public $staffAgents = [];
    public $viewMode = 'tree'; 
    public $editingId = null;

    // Form: Department
    public $deptName = '';
    public $deptDescription = '';
    public $deptIcon = 'building-office';
    public $deptColor = 'emerald-500';

    public $availableColors = [
        'cyan-500', 'emerald-500', 'blue-500', 'indigo-500', 
        'purple-500', 'pink-500', 'rose-500', 'red-500', 
        'orange-500', 'amber-500', 'yellow-500', 'green-500',
        'sky-500', 'primary'
    ];

    public $availableIcons = [
        'sparkles', 'cpu-chip', 'bug-ant', 'bolt', 'beaker', 
        'code-bracket-square', 'command-line', 'cube-transparent', 
        'shield-check', 'server', 'rocket-launch', 'paint-brush',
        'magnifying-glass', 'globe-europe-africa', 'fire', 'face-smile',
        'academic-cap', 'adjustments-horizontal', 'bell', 'briefcase',
        'camera', 'chat-bubble-left-ellipsis', 'cloud', 'cog-6-tooth',
        'document-text', 'envelope', 'heart', 'key', 'light-bulb',
        'lock-closed', 'map-pin', 'megaphone', 'moon', 'paper-airplane',
        'phone', 'photo', 'puzzle-piece', 'shopping-cart', 'star',
        'sun', 'trophy', 'user', 'video-camera', 'wrench-screwdriver'
    ];

    public function mount()
    {
        $this->loadStructure();
    }

    public function loadStructure()
    {
        $this->departments = AiDepartment::with(['agents.role'])->orderBy('order_index')->get();
        $this->staffAgents = AiAgent::whereNull('ai_department_id')->with('role')->orderBy('name')->get();
    }

    public function showSuccess()
    {
        $this->dispatch('structure-updated');
    }

    public function closeEditor()
    {
        $this->viewMode = 'tree';
        $this->editingId = null;
        $this->loadStructure();
    }

    // --- Departments ---
    public function createDepartment()
    {
        $this->reset(['deptName', 'deptDescription']);
        $this->deptIcon = 'building-office';
        $this->deptColor = 'emerald-500';
        $this->editingId = null;
        $this->viewMode = 'edit-dept';
    }

    public function editDepartment($id)
    {
        $dept = AiDepartment::findOrFail($id);
        $this->editingId = $id;
        $this->deptName = $dept->name;
        $this->deptDescription = $dept->description;
        $this->deptIcon = $dept->icon;
        $this->deptColor = $dept->color;
        $this->viewMode = 'edit-dept';
    }

    public function saveDepartment($close = true)
    {
        $this->validate(['deptName' => 'required|string|max:255']);
        $dept = AiDepartment::updateOrCreate(['id' => $this->editingId], [
            'name' => $this->deptName,
            'description' => $this->deptDescription,
            'icon' => $this->deptIcon,
            'color' => $this->deptColor,
            'order_index' => $this->editingId ? AiDepartment::find($this->editingId)->order_index : AiDepartment::max('order_index') + 1,
        ]);
        
        $this->editingId = $dept->id;
        
        // Cache invalidation for UI Theming
        \Illuminate\Support\Facades\Cache::forget(strtolower($dept->name) . '_dept_color');
        \Illuminate\Support\Facades\Cache::forget(strtolower($dept->name) . '_dept_class');
        \Illuminate\Support\Facades\Cache::forget(strtolower($dept->name) . '_nav_color');

        $this->dispatch('department-saved', id: $dept->id);

        if ($close) {
            $this->closeEditor();
        }
    }

    public function deleteDepartment($id)
    {
        $dept = AiDepartment::findOrFail($id);
        
        // Agenten in die Stabsstelle verschieben (unassigned)
        AiAgent::where('ai_department_id', $id)->update(['ai_department_id' => null]);
        
        $dept->delete();
        
        $this->closeEditor();
        $this->dispatch('structure-updated');
    }

    public function updated($propertyName)
    {
        if ($this->viewMode === 'edit-dept' && in_array($propertyName, ['deptName', 'deptDescription', 'deptIcon', 'deptColor'])) {
            if (!empty($this->deptName)) {
                $this->saveDepartment(false);
            }
        }
    }



    // ============================================
    // EDIT AGENT -> Redirects to details editor
    // ============================================

    public function editAgent($id)
    {
        return redirect()->route('admin.ai-agents.editor', ['id' => $id]);
    }
    // DRAG & DROP HANDLERS
    // ============================================

    public function updateDepartmentOrder($orderedIds)
    {
        foreach ($orderedIds as $index => $id) {
            AiDepartment::where('id', $id)->update(['order_index' => $index]);
        }
        $this->loadStructure();
    }

    public function moveAgent($agentId, $targetDeptId)
    {
        $deptId = $targetDeptId === 'unassigned' ? null : $targetDeptId;
        AiAgent::where('id', $agentId)->update(['ai_department_id' => $deptId]);
        $this->loadStructure();
        $this->dispatch('structure-updated');
    }

    // ============================================

    public function editFullAgentDetails($id)
    {
        return redirect()->route('admin.ai-agents.editor', ['id' => $id]);
    }

    public function render()
    {
        $freeAgents = AiAgent::whereNull('ai_department_id')->with('role')->orderBy('name')->get();

        return view('livewire.shop.ai.ai-company-structure', [
            'freeAgents' => $freeAgents
        ]);
    }
}

<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.backend_layout')]
class AiCompanyStructure extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    public $departments = [];
    public $viewMode = 'tree'; 
    #[\Livewire\Attributes\Locked]
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

        $this->loadStructure();
    }

    #[On('structure-updated')]
    public function loadStructure()
    {
        $this->departments = AiDepartment::with(['agents.role'])->orderBy('order_index')->get();
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

    protected function assertAdminAccess(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen die KI-Unternehmensstruktur bearbeiten.');
        }
    }

    // --- Departments ---
    public function createDepartment()
    {
        $this->assertAdminAccess();
        $this->reset(['deptName', 'deptDescription']);
        $this->deptIcon = 'building-office';
        $this->deptColor = 'emerald-500';
        $this->editingId = null;
        $this->viewMode = 'edit-dept';
    }

    public function editDepartment($id)
    {
        $this->assertAdminAccess();
        $dept = AiDepartment::findOrFail($id);
        $this->editingId = $dept->id;
        $this->deptName = $dept->name;
        $this->deptDescription = $dept->description;
        $this->deptIcon = $dept->icon;
        $this->deptColor = $dept->color;
        $this->viewMode = 'edit-dept';
    }

    public function saveDepartment($close = true)
    {
        $this->assertAdminAccess();

        $allowedIcons = array_merge($this->availableIcons, ['building-office']);
        $this->validate([
            'deptName' => 'required|string|max:255',
            'deptDescription' => 'nullable|string|max:1000',
            'deptIcon' => ['required', 'string', \Illuminate\Validation\Rule::in($allowedIcons)],
            'deptColor' => ['required', 'string', \Illuminate\Validation\Rule::in($this->availableColors)],
        ]);

        $existingOrder = $this->editingId ? AiDepartment::where('id', $this->editingId)->value('order_index') : null;
        $dept = AiDepartment::updateOrCreate(['id' => $this->editingId], [
            'name' => $this->deptName,
            'description' => $this->deptDescription,
            'icon' => $this->deptIcon,
            'color' => $this->deptColor,
            'order_index' => $existingOrder !== null ? $existingOrder : ((int) AiDepartment::max('order_index') + 1),
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
        $this->assertAdminAccess();

        $dept = AiDepartment::findOrFail($id);
        
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
        $this->assertAdminAccess();

        return redirect()->route('admin.ai-agents.editor', ['id' => $id]);
    }
    // DRAG & DROP HANDLERS
    // ============================================

    public function updateDepartmentOrder($orderedIds)
    {
        $this->assertAdminAccess();

        if (!is_array($orderedIds)) {
            return;
        }

        foreach (array_slice(array_values($orderedIds), 0, 100) as $index => $id) {
            AiDepartment::where('id', $id)->update(['order_index' => (int) $index]);
        }
        $this->loadStructure();
    }

    public function moveAgent($agentId, $targetDeptId)
    {
        $this->assertAdminAccess();

        if ($targetDeptId !== null && $targetDeptId !== '' && !AiDepartment::where('id', $targetDeptId)->exists()) {
            return;
        }

        AiAgent::where('id', $agentId)->update(['ai_department_id' => $targetDeptId ?: null]);
        $this->loadStructure();
        $this->dispatch('structure-updated');
    }

    // ============================================

    public function editFullAgentDetails($id)
    {
        $this->assertAdminAccess();
        return redirect()->route('admin.ai-agents.editor', ['id' => $id]);
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-company-structure');
    }
}

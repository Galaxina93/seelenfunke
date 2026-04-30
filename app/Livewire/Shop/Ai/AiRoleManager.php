<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use Livewire\Component;

use Livewire\Attributes\Layout;

#[Layout('components.layouts.backend_layout')]
class AiRoleManager extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    public $roles;
    
    // Inline Edit State
    public $editingRoleId = null;
    public $isCreating = false;

    // Formular Daten
    public $name = '';
    public $description = '';
    public $selectedTools = [];
    public $searchTool = '';

    public $topTools = [];

    public function mount()
    {
        $this->loadRoles();
        $this->loadTopTools();
    }

    #[\Livewire\Attributes\On('edit-role')]
    public function openEditFromWorkspace($roleId = null)
    {
        if ($roleId) {
            $this->edit($roleId);
        } else {
            $this->resetInputFields();
        }
    }

    public function loadTopTools()
    {
        $this->topTools = \App\Models\Ai\AiToolUsage::select('tool_name', \Illuminate\Support\Facades\DB::raw('COUNT(*) as usage_count'))
            ->groupBy('tool_name')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function loadRoles()
    {
        $this->roles = AiRole::withCount('tools')->orderByDesc('tools_count')->orderBy('name')->get();
    }

    public function create()
    {
        $this->resetInputFields();
        $this->isCreating = true;
    }

    public function edit($id)
    {
        $this->resetInputFields();
        $role = AiRole::findOrFail($id);
        $this->editingRoleId = $id;
        $this->name = $role->name;
        $this->description = $role->description;
        // Tool-IDs als String-Array
        $this->selectedTools = $role->tools->pluck('id')->map(fn($tId) => (string)$tId)->toArray();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'selectedTools' => 'array',
        ]);

        $roleIdToSave = $this->isCreating ? null : $this->editingRoleId;

        $role = AiRole::updateOrCreate(['id' => $roleIdToSave], [
            'name' => $this->name,
            'description' => $this->description,
        ]);

        // Null und leere Werte rausfiltern
        $toolIds = array_filter($this->selectedTools);
        $role->tools()->sync($toolIds);

        session()->flash('message', $roleIdToSave ? 'Rolle erfolgreich aktualisiert.' : 'Rolle erfolgreich erstellt.');

        $this->cancel();
        $this->loadRoles();
    }

    public function cancel()
    {
        $this->resetInputFields();
    }

    public function delete($id)
    {
        AiRole::findOrFail($id)->delete();
        session()->flash('message', 'Rolle erfolgreich gelöscht.');
        $this->loadRoles();
    }

    public function resetInputFields()
    {
        $this->name = '';
        $this->description = '';
        $this->editingRoleId = null;
        $this->isCreating = false;
        $this->selectedTools = [];
        $this->searchTool = '';
    }

    public function render()
    {
        // Lädt alle verfügbaren Tools anhand der Searchbar
        $query = AiTool::query();
        if (!empty($this->searchTool)) {
            $query->where('name', 'like', '%' . $this->searchTool . '%')
                  ->orWhere('identifier', 'like', '%' . $this->searchTool . '%')
                  ->orWhere('description', 'like', '%' . $this->searchTool . '%');
        }
        $allTools = $query->orderBy('name')->get();

        // Gruppierung basierend auf der Registry und verknüpft mit den live Abteilungsnamen (via UUID)
        $depts = \App\Models\Ai\AiDepartment::pluck('name', 'id')->toArray();
        $nameCeo = $depts['019d0000-0000-0000-0000-000000000000'] ?? 'Leitung';
        $nameSupport = $depts['019d6666-6666-6666-6666-666666666666'] ?? 'Support';
        $nameProd = $depts['019d1111-1111-1111-1111-111111111111'] ?? 'Produkte';
        $nameMark = $depts['019d2222-2222-2222-2222-222222222222'] ?? 'Marketing';
        $nameOrd = $depts['019d3333-3333-3333-3333-333333333333'] ?? 'Bestellungen';
        $nameFin = $depts['019d4444-4444-4444-4444-444444444444'] ?? 'Buchhaltung';
        $nameAgents = $depts['019daaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'] ?? 'Agenten';
        $nameSystem = $depts['019d5555-5555-5555-5555-555555555555'] ?? 'System';

        // Strikte Array-Map Reihenfolge vom CEO gefordert:
        $categoryMap = [
            $nameCeo => array_merge(
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiHealthFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiTaskFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiRoutineFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiCalendarFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiBrainFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiMailFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiContactFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiMasterFuncsSchema(), 'name')
            ),
            $nameSupport => array_merge(
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiSupportFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiTelefonyFuncsSchema(), 'name')
            ),
            $nameProd => array_merge(
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductAnalyticsFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductFractureFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductCreateFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiSuppliersFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductTemplatesFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductControlReviewsFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductNicheScannerFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductPackagingConfiguratorFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiScoutFuncsSchema(), 'name')
            ),
            $nameMark => array_merge(
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiMarketingFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiMarketingNewsletterFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiMarketingVoucherFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiMarketingBlogFuncsSchema(), 'name')
            ),
            $nameOrd => array_column(\App\Services\AI\AIFunctionsRegistry::getAiSalesFuncsSchema(), 'name'),
            $nameFin => array_column(\App\Services\AI\AIFunctionsRegistry::getAiFinanceFuncsSchema(), 'name'),
            $nameAgents => array_column(\App\Services\AI\AIFunctionsRegistry::getAiAgentsFuncsSchema(), 'name'),
            $nameSystem => array_column(\App\Services\AI\AIFunctionsRegistry::getAiSystemFuncsSchema(), 'name'),
        ];

        // Initiiere das leere Array mit der exakten geforderten Reihenfolge
        $groupedTools = [];
        foreach (array_keys($categoryMap) as $catName) {
            $groupedTools[$catName] = [];
        }
        $groupedTools['Andere'] = []; // Fallback

        foreach ($allTools as $tool) {
            $matchedCategory = 'Andere';
            foreach ($categoryMap as $category => $identifiers) {
                if (in_array($tool->identifier, $identifiers)) {
                    $matchedCategory = $category;
                    break;
                }
            }
            $groupedTools[$matchedCategory][] = $tool;
        }

        // Leere Kategorien rausfiltern, die gar keine Tools besitzen
        $groupedTools = array_filter($groupedTools, fn($tools) => count($tools) > 0);

        // Berechne Active Count pro Gruppe
        $groupedToolsCollection = collect($groupedTools)->map(function ($toolsList, $category) {
            $tools = collect($toolsList);
            $activeCount = $tools->whereIn('id', $this->selectedTools)->count();
            return [
                'tools' => $tools,
                'active_count' => $activeCount,
                'total_count' => $tools->count(),
            ];
        });

        return view('livewire.shop.ai.ai-role-manager', [
            'groupedTools' => $groupedToolsCollection,
            'totalToolsCount' => $allTools->count()
        ]);
    }
}

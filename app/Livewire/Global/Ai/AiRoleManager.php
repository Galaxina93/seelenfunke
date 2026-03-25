<?php

namespace App\Livewire\Global\Ai;

use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use Livewire\Component;

class AiRoleManager extends Component
{
    public $roles;
    
    // Inline Edit State
    public $editingRoleId = null;
    public $isCreating = false;

    // Formular Daten
    public $name = '';
    public $description = '';
    public $selectedTools = [];
    public $searchTool = '';

    public function mount()
    {
        $this->loadRoles();
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
        $nameCeo = $depts['019d0000-0000-0000-0000-000000000000'] ?? 'Firmenleitung';
        $nameProd = $depts['019d1111-1111-1111-1111-111111111111'] ?? 'Produkte';
        $nameMark = $depts['019d2222-2222-2222-2222-222222222222'] ?? 'Marketing';
        $nameOrd = $depts['019d3333-3333-3333-3333-333333333333'] ?? 'Bestellungen';
        $nameFin = $depts['019d4444-4444-4444-4444-444444444444'] ?? 'Buchhaltung';

        $categoryMap = [
            $nameFin => array_column(\App\Services\AI\AIFunctionsRegistry::getAiFinanceFuncsSchema(), 'name'),
            $nameMark => array_column(\App\Services\AI\AIFunctionsRegistry::getAiMarketingFuncsSchema(), 'name'),
            $nameOrd => array_merge(
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiSalesFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiSupportFuncsSchema(), 'name')
            ),
            $nameProd => array_column(\App\Services\AI\AIFunctionsRegistry::getAiScoutFuncsSchema(), 'name'),
            $nameCeo => array_column(\App\Services\AI\AIFunctionsRegistry::getAiSystemFuncsSchema(), 'name'),
        ];

        $groupedTools = [];
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

        return view('livewire.global.ai.ai-role-manager', [
            'groupedTools' => $groupedToolsCollection,
            'totalToolsCount' => $allTools->count()
        ])->layout('components.layouts.backend_layout', ['guard' => 'admin']);
    }
}

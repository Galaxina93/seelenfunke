<?php
namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceCostItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class FinancialContractsGroups extends Component
{
    use WithFileUploads;

    #[Url]
    public $selectedYear;
    #[Url]
    public $selectedMonth;

    public ?string $activeGroupId = null;

    public ?string $editingGroupId = null;
    #[Rule('required|min:3')]
    public $tempGroupName = '';

    public $showAddItemFormForGroup = null;

    public $newGroupName = '';
    public $newGroupType = 'expense';
    public $isCreatingGroup = false;

    public ?string $uploadingMissingItemId = null;
    #[Rule('required|file|max:10240')]
    public $quickUploadFile;

    #[Rule('required', message: 'Bitte geben Sie einen Namen an.')]
    public $itemName = '';

    #[Rule('required', message: 'Bitte geben Sie einen gültigen Betrag an.')]
    public $itemAmount = '';

    public $itemInterval = 1;

    #[Rule('required', message: 'Bitte wählen Sie ein Startdatum.')]
    public $itemDate;

    public $itemDescription = '';
    public $itemFile;
    public $itemIsBusiness = false;
    public ?string $itemExistingFile = null;

    public ?string $editingItemId = null;
    public ?string $addingToGroupId = null;
    public ?string $targetGroupId = null;

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Finanzen.');
        }

        $this->selectedYear = $this->selectedYear ?? date('Y');
        $this->selectedMonth = $this->selectedMonth ?? date('n');
        $this->itemDate = date('Y-m-d');
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    public function getMissingContractsProperty()
    {
        return FinanceCostItem::whereHas('group', function($q) {
            $q->where('admin_id', $this->getAdminId());
        })
            ->whereNull('contract_file_path')
            ->with('group')
            ->orderBy('name')
            ->get();
    }

    public function startQuickUpload($itemId)
    {
        $this->uploadingMissingItemId = $itemId;
        $this->reset('quickUploadFile');
    }

    public function cancelQuickUpload()
    {
        $this->uploadingMissingItemId = null;
        $this->reset('quickUploadFile');
    }

    public function saveQuickUpload()
    {
        $this->validate(['quickUploadFile' => 'required|file|max:10240']);
        $item = FinanceCostItem::findOrFail($this->uploadingMissingItemId);

        if ($item->group->admin_id !== $this->getAdminId()) {
            abort(403);
        }

        $path = $this->quickUploadFile->store('contracts', 'public');
        $item->update(['contract_file_path' => $path]);

        $this->uploadingMissingItemId = null;
        $this->reset('quickUploadFile');
        session()->flash('success', 'Datei erfolgreich hochgeladen.');
    }

    public function createGroup()
    {
        $this->validate(['newGroupName' => 'required|min:3']);

        $position = FinanceGroup::where('admin_id', $this->getAdminId())->max('position') + 1;

        FinanceGroup::create([
            'admin_id' => $this->getAdminId(),
            'name' => $this->newGroupName,
            'type' => $this->newGroupType,
            'position' => $position
        ]);

        $this->reset('newGroupName', 'newGroupType', 'isCreatingGroup');
        $this->dispatchChartUpdate();
        session()->flash('success', 'Gruppe erstellt.');
    }

    public function editGroup($id)
    {
        $group = FinanceGroup::where('id', $id)->where('admin_id', $this->getAdminId())->firstOrFail();
        $this->editingGroupId = $id;
        $this->tempGroupName = $group->name;
    }

    public function updateGroup()
    {
        $this->validate(['tempGroupName' => 'required|min:3']);

        $group = FinanceGroup::where('id', $this->editingGroupId)->where('admin_id', $this->getAdminId())->firstOrFail();
        $group->update(['name' => $this->tempGroupName]);

        $this->editingGroupId = null;
        $this->tempGroupName = '';
        $this->dispatchChartUpdate();
        session()->flash('success', 'Gruppenname aktualisiert.');
    }

    public function cancelEditGroup()
    {
        $this->editingGroupId = null;
        $this->tempGroupName = '';
    }

    public function deleteGroup($id)
    {
        $group = FinanceGroup::where('id', $id)->where('admin_id', $this->getAdminId())->firstOrFail();

        if ($group->items()->count() > 0) {
            session()->flash('error', 'Gruppe kann nicht gelöscht werden! Bitte verschieben oder löschen Sie zuerst alle enthaltenen Verträge.');
            return;
        }

        $group->delete();
        $this->dispatchChartUpdate();
        session()->flash('success', 'Gruppe erfolgreich gelöscht.');
    }

    public function toggleGroup($id)
    {
        if ($this->activeGroupId === $id) {
            $this->activeGroupId = null;
        } else {
            $this->activeGroupId = $id;
        }
    }

    // NEU: Gruppen Positionen nach Drag & Drop speichern
    public function updateGroupOrder($orderedIds)
    {
        foreach ($orderedIds as $index => $id) {
            FinanceGroup::where('id', $id)
                ->where('admin_id', $this->getAdminId())
                ->update(['position' => $index]);
        }
        $this->dispatchChartUpdate();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Gruppen-Reihenfolge gespeichert.']);
    }

    public function moveCostItem($itemId, $targetGroupId)
    {
        $item = FinanceCostItem::findOrFail($itemId);
        $targetGroup = FinanceGroup::where('id', $targetGroupId)->where('admin_id', $this->getAdminId())->first();

        if ($item && $targetGroup) {
            if($item->group->admin_id !== $this->getAdminId()) abort(403);

            $item->update(['finance_group_id' => $targetGroup->id]);

            $this->activeGroupId = $targetGroup->id;
            $this->dispatchChartUpdate();
            session()->flash('success', 'Vertrag verschoben.');
        }
    }

    public function toggleAddItemForm($groupId)
    {
        if ($this->showAddItemFormForGroup !== $groupId) {
            $this->resetItemForm();
            $this->showAddItemFormForGroup = $groupId;
            $this->addingToGroupId = $groupId;
            $this->editingItemId = null;
        } else {
            $this->showAddItemFormForGroup = null;
            $this->addingToGroupId = null;
        }
    }

    public function openItemForm($groupId, $itemId = null)
    {
        $this->resetItemForm();
        $this->showAddItemFormForGroup = null;
        $this->addingToGroupId = $groupId;

        if ($itemId) {
            $this->editingItemId = $itemId;
            $item = FinanceCostItem::findOrFail($itemId);

            if($item->group->admin_id !== $this->getAdminId()) {
                abort(403);
            }

            $this->itemName = $item->name;
            $this->itemAmount = $item->amount;
            $this->itemInterval = $item->interval_months;
            $this->itemDate = $item->first_payment_date->format('Y-m-d');
            $this->itemDescription = $item->description;
            $this->itemIsBusiness = (bool) $item->is_business;
            $this->itemExistingFile = $item->contract_file_path;
            $this->targetGroupId = $item->finance_group_id;
        }
    }

    public function saveNewItem($groupId)
    {
        $this->addingToGroupId = $groupId;
        $this->saveItem();
    }

    public function saveItem()
    {
        if($this->itemAmount) {
            $this->itemAmount = str_replace(',', '.', $this->itemAmount);
        }

        $this->validate([
            'itemName' => 'required',
            'itemAmount' => 'required|numeric',
            'itemDate' => 'required|date',
        ]);

        $data = [
            'name' => $this->itemName,
            'amount' => $this->itemAmount,
            'interval_months' => $this->itemInterval,
            'first_payment_date' => $this->itemDate,
            'description' => $this->itemDescription,
            'is_business' => $this->itemIsBusiness ? 1 : 0,
        ];

        if ($this->itemFile) {
            $path = $this->itemFile->store('contracts', 'public');
            $data['contract_file_path'] = $path;
        }

        if ($this->editingItemId) {
            $item = FinanceCostItem::findOrFail($this->editingItemId);
            if($item->group->admin_id !== $this->getAdminId()) abort(403);

            if ($this->targetGroupId && $this->targetGroupId !== $item->finance_group_id) {
                $targetGroup = FinanceGroup::where('id', $this->targetGroupId)->where('admin_id', $this->getAdminId())->first();
                if($targetGroup) {
                    $data['finance_group_id'] = $this->targetGroupId;
                }
            }

            $item->update($data);
            $this->editingItemId = null;
            session()->flash('success', 'Kostenstelle aktualisiert.');
        } else {
            if(!$this->addingToGroupId) {
                session()->flash('error', 'Fehler: Keine Zielgruppe gefunden.');
                return;
            }

            $group = FinanceGroup::findOrFail($this->addingToGroupId);
            if($group->admin_id !== $this->getAdminId()) abort(403);

            FinanceCostItem::create(array_merge($data, [
                'finance_group_id' => $this->addingToGroupId
            ]));

            $this->showAddItemFormForGroup = null;
            session()->flash('success', 'Kostenstelle erstellt.');
        }

        $this->dispatchChartUpdate();
        $this->resetItemForm();
    }

    public function removeFileFromItem($itemId)
    {
        $item = FinanceCostItem::findOrFail($itemId);
        if($item->group->admin_id !== $this->getAdminId()) abort(403);

        if ($item->contract_file_path) {
            $item->update(['contract_file_path' => null]);
            $this->itemExistingFile = null;
            session()->flash('success', 'Datei entfernt.');
        }
    }

    public function cancelItemEdit()
    {
        $this->editingItemId = null;
        $this->showAddItemFormForGroup = null;
        $this->resetItemForm();
    }

    public function deleteItem($id)
    {
        $item = FinanceCostItem::findOrFail($id);
        if($item->group->admin_id !== $this->getAdminId()) abort(403);

        $item->delete();
        $this->dispatchChartUpdate();
        session()->flash('success', 'Kostenstelle gelöscht.');
    }

    public function resetItemForm()
    {
        $this->reset(['itemName', 'itemAmount', 'itemInterval', 'itemDate', 'itemDescription', 'itemFile', 'addingToGroupId', 'itemIsBusiness', 'editingItemId', 'itemExistingFile', 'targetGroupId', 'quickUploadFile', 'uploadingMissingItemId']);
        $this->itemDate = date('Y-m-d');
    }

    private function dispatchChartUpdate()
    {
        $groups = FinanceGroup::with('items')
            ->where('admin_id', $this->getAdminId())
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();

        $chartLabels = [];
        $chartData = [];
        $chartColors = [];

        foreach($groups as $group) {
            $monthlySum = 0;
            foreach($group->items as $item) {
                $monthlySum += abs($item->amount) / $item->interval_months;
            }

            if($monthlySum > 0) {
                $chartLabels[] = $group->name;
                $chartData[] = round($monthlySum, 2);
                if ($group->type === 'income') {
                    $chartColors[] = '#10b981';
                } else {
                    $chartColors[] = '#ef4444';
                }
            }
        }

        $this->dispatch('update-groups-chart', labels: $chartLabels, data: $chartData, colors: $chartColors);
    }

    public function render()
    {
        // WICHTIG: Sortierung nach `position`!
        $groups = FinanceGroup::with('items')
            ->where('admin_id', $this->getAdminId())
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();

        $chartLabels = [];
        $chartData = [];
        $chartColors = [];

        foreach($groups as $group) {
            $monthlySum = 0;
            foreach($group->items as $item) {
                $monthlySum += abs($item->amount) / $item->interval_months;
            }

            if($monthlySum > 0) {
                $chartLabels[] = $group->name;
                $chartData[] = round($monthlySum, 2);
                if ($group->type === 'income') $chartColors[] = '#10b981';
                else $chartColors[] = '#ef4444';
            }
        }

        return view('livewire.shop.financial.financial-contracts-groups.financial-contracts-groups', [
            'groups' => $groups,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'chartColors' => $chartColors,
            'missingContracts' => $this->missingContracts
        ]);
    }
}

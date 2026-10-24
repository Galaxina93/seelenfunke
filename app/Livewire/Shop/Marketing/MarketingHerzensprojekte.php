<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Marketing\MarketingHerzensprojektAction;
use App\Models\Marketing\MarketingHerzensprojektTransaction;
use App\Services\HerzensprojektService;

#[Layout('components.layouts.backend_layout')]
class MarketingHerzensprojekte extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    // Filters & Tabs
    public string $activeTab = 'actions'; // 'actions', 'ledger', 'settings'
    public string $statusFilter = 'all';

    // Modal state for Action (Create / Edit)
    public bool $showActionModal = false;
    #[\Livewire\Attributes\Locked]
    public ?string $editingActionId = null;
    public string $actionTitle = '';
    public string $actionRecipient = '';
    public string $actionDescription = '';
    public string $actionAmountEuro = '0';
    public string $actionStatus = 'planned';
    public string $actionPublicStory = '';
    public bool $actionIsPublic = true;

    // Modal state for Manual Deposit
    public bool $showDepositModal = false;
    public string $depositAmountEuro = '50';
    public string $depositNote = 'Zusätzlicher Gründer-Beitrag in den Herzens-Topf';

    // Settings
    public string $centsPerOrder = '200';

    protected function rules(): array
    {
        return [
            'actionTitle' => 'required|string|max:255',
            'actionRecipient' => 'required|string|max:255',
            'actionDescription' => 'nullable|string|max:2000',
            'actionAmountEuro' => 'required|numeric|min:0|max:100000',
            'actionStatus' => 'required|in:planned,in_progress,completed',
            'actionPublicStory' => 'nullable|string|max:3000',
            'actionIsPublic' => 'boolean',
        ];
    }

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
        $this->centsPerOrder = (string) shop_setting('herzensprojekt_cents_per_order', 200);
    }

    public function openCreateActionModal()
    {
        $this->resetValidation();
        $this->editingActionId = null;
        $this->actionTitle = '';
        $this->actionRecipient = '';
        $this->actionDescription = '';
        $this->actionAmountEuro = '0';
        $this->actionStatus = 'planned';
        $this->actionPublicStory = '';
        $this->actionIsPublic = true;
        $this->showActionModal = true;
    }

    public function openEditActionModal($actionId)
    {
        $this->resetValidation();
        $action = MarketingHerzensprojektAction::findOrFail($actionId);
        $this->editingActionId = $action->id;
        $this->actionTitle = $action->title;
        $this->actionRecipient = $action->target_recipient;
        $this->actionDescription = $action->description ?? '';
        $this->actionAmountEuro = (string) ($action->allocated_amount_cents / 100);
        $this->actionStatus = $action->status;
        $this->actionPublicStory = $action->public_story ?? '';
        $this->actionIsPublic = (bool) $action->is_public;
        $this->showActionModal = true;
    }

    public function closeActionModal()
    {
        $this->showActionModal = false;
        $this->editingActionId = null;
        $this->resetValidation();
    }

    public function saveAction()
    {
        $this->actionAmountEuro = str_replace(',', '.', trim((string) $this->actionAmountEuro));
        $this->validate();

        $cents = max(0, (int) round(((float) $this->actionAmountEuro) * 100));

        if ($this->editingActionId) {
            $action = MarketingHerzensprojektAction::findOrFail($this->editingActionId);
            $hasDisbursement = MarketingHerzensprojektTransaction::where('action_id', $action->id)
                ->where('type', 'disbursement')
                ->exists();

            $action->update([
                'title' => $this->actionTitle,
                'target_recipient' => $this->actionRecipient,
                'description' => $this->actionDescription,
                'allocated_amount_cents' => $hasDisbursement ? $action->allocated_amount_cents : $cents,
                'status' => $hasDisbursement ? 'completed' : $this->actionStatus,
                'public_story' => $this->actionPublicStory,
                'is_public' => $this->actionIsPublic,
                'completed_at' => ($this->actionStatus === 'completed' && !$action->completed_at) ? now() : $action->completed_at,
            ]);
            $msg = 'Aktion erfolgreich aktualisiert!';
        } else {
            MarketingHerzensprojektAction::create([
                'title' => $this->actionTitle,
                'target_recipient' => $this->actionRecipient,
                'description' => $this->actionDescription,
                'allocated_amount_cents' => $cents,
                'status' => $this->actionStatus,
                'public_story' => $this->actionPublicStory,
                'is_public' => $this->actionIsPublic,
                'completed_at' => $this->actionStatus === 'completed' ? now() : null,
            ]);
            $msg = 'Neue Herzensprojekt-Aktion angelegt!';
        }

        $this->closeActionModal();
        $this->dispatch('notify', ['type' => 'success', 'message' => $msg]);
    }

    public function deleteAction($actionId)
    {
        $action = MarketingHerzensprojektAction::findOrFail($actionId);
        if (MarketingHerzensprojektTransaction::where('action_id', $action->id)->exists()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Aktionen mit bereits verbuchten Auszahlungen dürfen aus Revisionsgründen nicht gelöscht werden.']);
            return;
        }
        $action->delete();

        $this->dispatch('notify', ['type' => 'warning', 'message' => 'Aktion wurde entfernt.']);
    }

    public function disburseForAction($actionId)
    {
        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($actionId) {
            $action = MarketingHerzensprojektAction::where('id', $actionId)->lockForUpdate()->firstOrFail();

            // Check if already disbursed
            $hasDisbursement = MarketingHerzensprojektTransaction::where('action_id', $action->id)
                ->where('type', 'disbursement')
                ->exists();

            if ($hasDisbursement) {
                return ['type' => 'error', 'message' => 'Für diese Aktion wurde bereits eine Auszahlung verbucht!'];
            }

            if ($action->allocated_amount_cents <= 0) {
                return ['type' => 'error', 'message' => 'Die Aktion hat keinen Betrag hinterlegt.'];
            }

            $currentPotCents = (int) MarketingHerzensprojektTransaction::lockForUpdate()->sum('amount_cents');
            if ($currentPotCents < $action->allocated_amount_cents) {
                return ['type' => 'error', 'message' => 'Nicht genügend Guthaben im Herzens-Topf für diese Auszahlung vorhanden.'];
            }

            // Negative transaction to reduce pot
            MarketingHerzensprojektTransaction::create([
                'action_id' => $action->id,
                'type' => 'disbursement',
                'amount_cents' => -$action->allocated_amount_cents,
                'description' => 'Auszahlung / Übergabe: ' . $action->title . ' (' . $action->target_recipient . ')',
            ]);

            $action->update([
                'status' => 'completed',
                'completed_at' => $action->completed_at ?? now(),
            ]);

            return [
                'type' => 'success',
                'message' => 'Betrag von ' . $action->formatted_amount . ' erfolgreich aus dem Herzens-Topf abgebucht!'
            ];
        });

        $this->dispatch('notify', $result);
    }

    public function openDepositModal()
    {
        $this->depositAmountEuro = '50';
        $this->depositNote = 'Manueller Einzahlung in den Herzens-Topf';
        $this->showDepositModal = true;
    }

    public function closeDepositModal()
    {
        $this->showDepositModal = false;
    }

    public function saveDeposit()
    {
        $this->validate([
            'depositAmountEuro' => 'required|numeric|min:0.01|max:100000',
            'depositNote' => 'nullable|string|max:255',
        ]);

        $cents = (int) round(((float) str_replace(',', '.', $this->depositAmountEuro)) * 100);
        if ($cents <= 0) {
            return;
        }

        MarketingHerzensprojektTransaction::create([
            'type' => 'manual_deposit',
            'amount_cents' => $cents,
            'description' => $this->depositNote ?: 'Manuelle Einzahlung',
        ]);

        $this->closeDepositModal();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => number_format($cents / 100, 2, ',', '.') . ' € erfolgreich dem Herzens-Topf gutgeschrieben!'
        ]);
    }

    public function saveSettings()
    {
        $this->validate([
            'centsPerOrder' => 'required|integer|min:0|max:100000',
        ]);

        $cents = (int) $this->centsPerOrder;

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'herzensprojekt_cents_per_order'],
            ['value' => (string) $cents]
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Spendenbetrag pro Bestellung auf ' . number_format($cents / 100, 2, ',', '.') . ' € gesetzt.'
        ]);
    }

    public function render()
    {
        $stats = HerzensprojektService::getDashboardStats();

        $actionsQuery = MarketingHerzensprojektAction::query();
        if ($this->statusFilter !== 'all') {
            $actionsQuery->where('status', $this->statusFilter);
        }
        $actions = $actionsQuery->orderBy('created_at', 'desc')->get();

        $transactions = MarketingHerzensprojektTransaction::with('order', 'action')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return view('livewire.shop.marketing.marketing-herzensprojekte', [
            'stats' => $stats,
            'actions' => $actions,
            'transactions' => $transactions,
        ]);
    }
}

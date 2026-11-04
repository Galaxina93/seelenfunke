<?php

namespace App\Livewire\Customer;

use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.customer_layout')]
class CustomerFunkiComponent extends CustomerDashboardComponent
{
    public string $initialTab = 'vouchers';

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $requestedTab = (string) request()->query('tab', 'vouchers');
        if (in_array($requestedTab, ['vouchers', 'levels', 'titles', 'hall_of_fame'], true)) {
            $this->initialTab = $requestedTab;
        }

        $this->loadDashboardData($user);

        if (!$this->isGameModeActive) {
            return redirect()->route('customer.dashboard');
        }
    }

    public function deactivateSeelenfunke(GamificationService $gameService): void
    {
        parent::deactivateSeelenfunke($gameService);
        $this->redirectRoute('customer.dashboard');
    }

    public function updatedRankingOptIn(): void
    {
        if ($this->rankingOptIn && !$this->useRealName && trim((string) $this->playerName) === '') {
            $this->rankingOptIn = false;
            $msg = 'Bitte lege zuerst deinen Spielernamen fest und klicke auf Speichern (oder wähle Echtname), bevor du die Ranglisten-Anzeige aktivierst.';
            $this->playerNameError = $msg;
            $this->addError('playerName', $msg);
            return;
        }

        parent::updatedRankingOptIn();
    }

    public function render()
    {
        return view('livewire.customer.customer-funki-component');
    }
}

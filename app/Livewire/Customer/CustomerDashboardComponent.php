<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

#[Layout('components.layouts.customer_layout')]
class CustomerDashboardComponent extends Component
{
    public $profileSteps = [];

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $this->checkProfileSteps($user);
    }

    #[On('profile-updated')]
    public function refreshData()
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $user->refresh();
            $user->load('profile');
            $this->checkProfileSteps($user);
        }
    }

    private function checkProfileSteps($user)
    {
        $this->profileSteps = [];
        if (!$user->profile) return;
        $p = $user->profile;

        $needsProfileInfo = empty($user->first_name) || empty($user->last_name) ||
            empty($p->street) || empty($p->city) || empty($p->house_number) || empty($p->postal) || empty($p->birthday);

        if ($needsProfileInfo) {
            $this->profileSteps[] = ['label' => 'Profil Informationen', 'action' => "\$dispatch('open-profile-modal', {tab: 'profile'})"];
        }
    }

    public function render()
    {
        return view('livewire.customer.customer-dashboard-component');
    }
}

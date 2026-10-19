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
    public $recentOrders = [];
    public bool $isGameModeActive = false;
    public $activeGiftVouchers = [];
    public string $referralCode = '';
    public bool $hasCompletedOrder = false;
    public int $successfulReferralsCount = 0;
    public int $totalReferralEarnedCents = 0;

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $this->loadDashboardData($user);
    }

    #[On('profile-updated')]
    public function refreshData()
    {
        $user = Auth::guard('customer')->user();
        if ($user) {
            $user->refresh();
            $user->load('profile');
            $this->loadDashboardData($user);
        }
    }

    private function loadDashboardData($user)
    {
        $this->checkProfileSteps($user);
        
        // Load recent orders
        $this->recentOrders = \App\Models\Order\OrderOrder::where('customer_id', $user->id)
            ->latest()
            ->take(3)
            ->get();
            
        // Check if game mode is active
        $this->isGameModeActive = (bool) \App\Models\Customer\CustomerGamification::where('customer_id', $user->id)
            ->value('is_active');

        // Load active store credit / gift vouchers
        $this->activeGiftVouchers = \App\Models\Marketing\MarketingGiftVoucher::where('recipient_email', $user->email)
            ->where('is_active', true)
            ->where('current_balance', '>', 0)
            ->get();

        // Check if customer has at least 1 paid order
        $this->hasCompletedOrder = \App\Models\Order\OrderOrder::where('customer_id', $user->id)
            ->where('payment_status', 'paid')
            ->exists();

        // Resolve or create persistent personal referral code ONLY if customer has purchased
        if ($this->hasCompletedOrder) {
            try {
                $ref = app(\App\Services\ReferralService::class)->getOrCreateReferralForCustomer(
                    $user->email,
                    $user->first_name,
                    $user->last_name,
                    $user->id
                );
                $this->referralCode = $ref->code;
                $this->successfulReferralsCount = (int) $ref->successful_referrals_count;
                $this->totalReferralEarnedCents = $this->successfulReferralsCount * (int) ($ref->reward_cents ?? 500);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Dashboard referral resolution error: ' . $e->getMessage());
                $this->referralCode = 'FREUNDE10';
                $this->successfulReferralsCount = 0;
                $this->totalReferralEarnedCents = 0;
            }
        }
    }

    private function checkProfileSteps($user)
    {
        $this->profileSteps = [];
        if (!$user->profile) return;
        $p = $user->profile;

        $needsProfileInfo = empty($user->first_name) || empty($user->last_name) ||
            empty($p->street) || empty($p->city) || empty($p->house_number) || empty($p->postal);

        if ($needsProfileInfo) {
            $this->profileSteps[] = ['label' => 'Profil Informationen', 'action' => "\$dispatch('open-profile-modal', {tab: 'profile'})"];
        }
    }

    public function render()
    {
        return view('livewire.customer.customer-dashboard-component');
    }
}

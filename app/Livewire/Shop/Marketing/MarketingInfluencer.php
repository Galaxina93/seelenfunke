<?php

namespace App\Livewire\Shop\Marketing;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Marketing\MarketingInfluencer as MarketingInfluencerModel;
use App\Models\Marketing\MarketingInfluencerCommission;
use App\Models\Marketing\MarketingInfluencerPayout;
use App\Services\Marketing\InfluencerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class MarketingInfluencer extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    public string $activeTab = 'influencers'; // influencers, payouts
    public string $statusFilter = 'active'; // active (non-archived), archived, all
    public string $search = '';

    // 1-Click Invitation Modal State
    public bool $showInviteModal = false;
    #[Locked]
    public ?string $activeInviteId = null;
    public ?string $generatedInviteUrl = null;
    public ?string $generatedInviteCode = null;
    public string $inviteLabel = '';
    public int $inviteCommissionValue = 15;
    public int $inviteFollowerDiscount = 10;

    // Configuration Modal State (Edit / Configure Influencer)
    public bool $showConfigModal = false;
    #[Locked]
    public ?string $editingInfluencerId = null;

    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $handle = '';
    public string $slug = '';
    public string $primaryChannel = 'instagram';
    public string $profileUrl = '';
    public string $commissionType = 'percent';
    public int $commissionValue = 15;
    public int $followerDiscountPercent = 10;
    public int $cookieDays = 30;
    public string $customVoucherCode = '';
    public bool $autoCreateTrackingLink = true;
    public bool $autoCreateVoucher = true;
    public bool $isActive = true;
    public string $internalNotes = '';

    // Payout / GiroCode Modal State
    public bool $showPayoutModal = false;
    #[Locked]
    public ?string $selectedPayoutId = null;
    public ?array $selectedPayoutData = null;
    public ?string $giroCodeSvg = null;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Influencer-Management.');
        }
    }

    public function mount(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
    }

    /**
     * Erstellt mit 1 Klick sofort einen neuen Einladungslink + 6-stelligen Verifizierungscode
     * und öffnet das Kopier-Modal.
     */
    public function createInvitation(InfluencerService $service): void
    {
        if (!Schema::hasTable('marketing_influencers')) {
            return;
        }

        $slot = $service->createInvitationSlot([
            'invitation_label' => $this->inviteLabel !== '' ? $this->inviteLabel : null,
            'commission_value' => $this->inviteCommissionValue,
            'follower_discount_percent' => $this->inviteFollowerDiscount,
        ]);

        $this->openInviteModal($slot->id);
        session()->flash('influencer_saved', 'Einladungslink & 6-stelliger Sicherheitscode wurden generiert!');
    }

    public function openInviteModal(string $id): void
    {
        $slot = MarketingInfluencerModel::findOrFail($id);

        $this->activeInviteId = $slot->id;
        $this->generatedInviteUrl = $slot->invitation_url;
        $this->generatedInviteCode = $slot->verification_code;
        $this->inviteLabel = (string) ($slot->invitation_label ?? '');
        $this->inviteCommissionValue = (int) $slot->commission_value;
        $this->inviteFollowerDiscount = (int) $slot->follower_discount_percent;
        $this->showInviteModal = true;
    }

    public function updateInviteSlotSettings(): void
    {
        if (!$this->activeInviteId) {
            return;
        }

        $this->validate([
            'inviteLabel' => 'nullable|string|max:120',
            'inviteCommissionValue' => 'required|integer|min:1|max:80',
            'inviteFollowerDiscount' => 'required|integer|min:0|max:50',
        ]);

        $slot = MarketingInfluencerModel::findOrFail($this->activeInviteId);
        $slot->update([
            'invitation_label' => trim($this->inviteLabel) !== '' ? trim($this->inviteLabel) : $slot->invitation_label,
            'commission_value' => max(1, min(80, (int) $this->inviteCommissionValue)),
            'follower_discount_percent' => max(0, min(50, (int) $this->inviteFollowerDiscount)),
        ]);

        session()->flash('influencer_saved', 'Konditionen der Einladung wurden aktualisiert.');
    }

    public function closeInviteModal(): void
    {
        $this->showInviteModal = false;
        $this->activeInviteId = null;
        $this->generatedInviteUrl = null;
        $this->generatedInviteCode = null;
        $this->inviteLabel = '';
    }

    public function resetForm(): void
    {
        $this->resetValidation();
        $this->showConfigModal = false;
        $this->editingInfluencerId = null;
        $this->firstName = '';
        $this->lastName = '';
        $this->email = '';
        $this->handle = '';
        $this->slug = '';
        $this->primaryChannel = 'instagram';
        $this->profileUrl = '';
        $this->commissionType = 'percent';
        $this->commissionValue = 15;
        $this->followerDiscountPercent = 10;
        $this->cookieDays = 30;
        $this->customVoucherCode = '';
        $this->autoCreateTrackingLink = true;
        $this->autoCreateVoucher = true;
        $this->isActive = true;
        $this->internalNotes = '';
    }

    public function editInfluencer(string $id): void
    {
        $this->resetValidation();
        $inf = MarketingInfluencerModel::with(['employee', 'defaultVoucher'])->findOrFail($id);

        $this->editingInfluencerId = $inf->id;
        $this->firstName = (string) ($inf->employee?->first_name ?? '');
        $this->lastName = (string) ($inf->employee?->last_name ?? '');
        $this->email = (string) ($inf->employee?->email ?? '');
        $this->handle = (string) ($inf->handle ?? '');
        $this->slug = (string) ($inf->slug ?? '');
        $this->primaryChannel = (string) ($inf->primary_channel ?: 'instagram');
        $this->profileUrl = (string) ($inf->profile_url ?? '');
        $this->commissionType = (string) $inf->commission_type;
        $this->commissionValue = (int) $inf->commission_value;
        $this->followerDiscountPercent = (int) $inf->follower_discount_percent;
        $this->cookieDays = (int) $inf->cookie_days;
        $this->customVoucherCode = (string) ($inf->defaultVoucher?->code ?? '');
        $this->isActive = (bool) $inf->is_active;
        $this->internalNotes = (string) ($inf->internal_notes ?? '');
        $this->showConfigModal = true;
    }

    public function closeConfigModal(): void
    {
        $this->resetForm();
    }

    public function saveInfluencer(InfluencerService $service): void
    {
        if (!Schema::hasTable('marketing_influencers')) {
            return;
        }

        $cleanHandle = ltrim(trim($this->handle), '@');
        $cleanSlug = Str::slug($this->slug ?: $cleanHandle ?: $this->firstName);
        if ($cleanSlug !== '') {
            $this->slug = $cleanSlug;
        }

        $existingEmployeeId = null;
        $isPendingSlot = false;
        if ($this->editingInfluencerId) {
            $existingInf = MarketingInfluencerModel::find($this->editingInfluencerId);
            $existingEmployeeId = $existingInf?->employee_id;
            $isPendingSlot = $existingInf && $existingInf->is_pending_invitation;
        }

        $maxCommission = $this->commissionType === 'fixed_cents' ? 50000 : 80;
        $rules = [
            'primaryChannel' => 'required|in:instagram,tiktok,youtube,pinterest,blog',
            'profileUrl' => 'nullable|url|max:500',
            'commissionType' => 'required|in:percent,fixed_cents',
            'commissionValue' => 'required|integer|min:1|max:' . $maxCommission,
            'followerDiscountPercent' => 'required|integer|min:0|max:50',
            'cookieDays' => 'required|integer|min:1|max:90',
        ];

        if (!$isPendingSlot) {
            $this->email = mb_strtolower(trim($this->email));
            $rules['firstName'] = 'required|string|max:120';
            $rules['lastName'] = 'required|string|max:120';
            $rules['email'] = [
                'required',
                'email',
                'max:191',
                Rule::unique('employees', 'email')->ignore($existingEmployeeId),
                function ($attribute, $value, $fail) use ($existingEmployeeId) {
                    if (\App\Models\System\SystemUser::isEmailTakenAcrossGuards((string) $value, 'employee', $existingEmployeeId)) {
                        $fail('Diese E-Mail-Adresse ist bereits einem bestehenden Konto (Kunde, Mitarbeiter oder Admin) zugeordnet.');
                    }
                },
            ];
            $rules['handle'] = 'required|string|max:100';
            $rules['slug'] = [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('marketing_influencers', 'slug')->ignore($this->editingInfluencerId),
            ];
        }

        $this->validate($rules);

        $wasEditing = (bool) $this->editingInfluencerId;

        $service->createOrUpdateInfluencer([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'handle' => $this->handle,
            'slug' => $this->slug,
            'primary_channel' => $this->primaryChannel,
            'profile_url' => $this->profileUrl,
            'commission_type' => $this->commissionType,
            'commission_value' => $this->commissionValue,
            'follower_discount_percent' => $this->followerDiscountPercent,
            'cookie_days' => $this->cookieDays,
            'custom_voucher_code' => $this->customVoucherCode,
            'auto_create_tracking_link' => $this->autoCreateTrackingLink,
            'auto_create_voucher' => $this->autoCreateVoucher,
            'is_active' => $this->isActive,
            'internal_notes' => $this->internalNotes,
        ], $this->editingInfluencerId);

        $this->resetForm();
        session()->flash(
            'influencer_saved',
            $wasEditing
                ? 'Influencer-Konfiguration erfolgreich gespeichert!'
                : 'Influencer erfolgreich angelegt!'
        );
    }

    public function regenerateInvitationLink(string $id, InfluencerService $service): void
    {
        $inf = MarketingInfluencerModel::findOrFail($id);
        $service->regenerateInvitationToken($inf);
        $this->openInviteModal($inf->id);
        session()->flash('influencer_saved', 'Neuer 14-Tage Einladungs-Link & Sicherheitscode generiert!');
    }

    public function toggleActive(string $id, InfluencerService $service): void
    {
        $inf = MarketingInfluencerModel::with(['employee', 'defaultVoucher', 'trackingLinks'])->findOrFail($id);
        $newState = !$inf->is_active;
        $inf->update(['is_active' => $newState]);

        if (!$newState && $inf->employee) {
            $inf->employee->revokeActiveSessionsAndTokens();
        }

        $service->syncInfluencerActiveState($inf->fresh());
    }

    public function toggleArchive(string $id, InfluencerService $service): void
    {
        $inf = MarketingInfluencerModel::with(['employee', 'defaultVoucher', 'trackingLinks'])->findOrFail($id);
        $newArchiveState = !$inf->is_archived;
        $inf->update([
            'is_archived' => $newArchiveState,
            'is_active' => $newArchiveState ? false : true,
        ]);

        if ($newArchiveState && $inf->employee) {
            $inf->employee->revokeActiveSessionsAndTokens();
        }

        $service->syncInfluencerActiveState($inf->fresh());

        session()->flash(
            'influencer_saved',
            $newArchiveState
                ? 'Influencer wurde archiviert und alle Codes/Links pausiert.'
                : 'Influencer wurde aus dem Archiv wiederhergestellt.'
        );
    }

    public function deleteInfluencer(string $id, InfluencerService $service): void
    {
        $inf = MarketingInfluencerModel::with(['employee', 'defaultVoucher', 'trackingLinks'])->findOrFail($id);
        $result = $service->deleteOrArchiveInfluencer($inf);

        if ($this->editingInfluencerId === $id) {
            $this->resetForm();
        }
        if ($this->activeInviteId === $id) {
            $this->closeInviteModal();
        }

        session()->flash('influencer_saved', $result['message']);
    }

    public function approveCommissionsForInfluencer(string $id, InfluencerService $service): void
    {
        $inf = MarketingInfluencerModel::findOrFail($id);
        $approvedCount = $service->approveEligibleCommissions($inf);

        if ($approvedCount > 0) {
            session()->flash('influencer_saved', "{$approvedCount} vorgemerkte Provision(en) für {$inf->handle} zur Auszahlung freigegeben.");
        } else {
            session()->flash('influencer_error', 'Keine bezahlten, offenen Provisionen zur Freigabe gefunden.');
        }
    }

    public function createPayoutForInfluencer(string $id, InfluencerService $service): void
    {
        $inf = MarketingInfluencerModel::with('employee.profile')->findOrFail($id);

        try {
            $payout = $service->requestPayout($inf);
            $this->openPayoutModal($payout->id, $service);
            session()->flash('influencer_saved', "Gutschrift {$payout->credit_note_number} erfolgreich erstellt!");
        } catch (\Throwable $e) {
            session()->flash('influencer_error', $e->getMessage());
        }
    }

    public function openPayoutModal(string $payoutId, InfluencerService $service): void
    {
        $payout = MarketingInfluencerPayout::with(['influencer.employee.profile', 'commissions', 'giftVoucher'])
            ->findOrFail($payoutId);

        $this->selectedPayoutId = $payout->id;
        $this->giroCodeSvg = null;

        $inf = $payout->influencer;
        if ($payout->payout_method === 'bank_transfer' && $inf) {
            $payload = $inf->buildEpcGiroCodePayload($payout->gross_amount_cents, "Gutschrift {$payout->credit_note_number} Mein Seelenfunke");
            if ($payload) {
                $this->giroCodeSvg = $service->generateQrCodeSvg($payload, 190);
            }
        }

        $this->selectedPayoutData = [
            'id' => $payout->id,
            'credit_note_number' => $payout->credit_note_number,
            'status' => $payout->status,
            'payout_method' => $payout->payout_method,
            'net_formatted' => number_format($payout->net_amount_cents / 100, 2, ',', '.') . ' €',
            'tax_rate' => $payout->tax_rate,
            'tax_formatted' => number_format($payout->tax_amount_cents / 100, 2, ',', '.') . ' €',
            'bonus_formatted' => $payout->bonus_amount_cents > 0 ? number_format($payout->bonus_amount_cents / 100, 2, ',', '.') . ' €' : null,
            'gross_formatted' => number_format($payout->gross_amount_cents / 100, 2, ',', '.') . ' €',
            'handle' => $inf?->handle ?? '-',
            'full_name' => trim(($inf?->employee?->first_name ?? '') . ' ' . ($inf?->employee?->last_name ?? '')),
            'account_holder' => $inf?->account_holder,
            'iban' => $inf?->iban,
            'masked_iban' => $inf?->masked_iban,
            'bic' => $inf?->bic,
            'paypal_email' => $inf?->paypal_email,
            'tax_number' => $inf?->tax_number,
            'pdf_path' => $payout->pdf_path,
            'gift_voucher_code' => $payout->giftVoucher?->code,
            'commissions_count' => $payout->commissions->count(),
        ];

        $this->showPayoutModal = true;
    }

    public function closePayoutModal(): void
    {
        $this->showPayoutModal = false;
        $this->selectedPayoutId = null;
        $this->selectedPayoutData = null;
        $this->giroCodeSvg = null;
    }

    public function confirmPayoutPaid(string $payoutId, InfluencerService $service): void
    {
        $payout = MarketingInfluencerPayout::findOrFail($payoutId);
        $updated = $service->markPayoutAsPaid($payout, (string) Auth::guard('admin')->id());

        if ($this->showPayoutModal && $this->selectedPayoutId === $payoutId) {
            $this->openPayoutModal($updated->id, $service);
        }

        session()->flash(
            'influencer_saved',
            "Gutschrift {$updated->credit_note_number} als bezahlt markiert und inkl. PDF-Beleg in der Buchhaltung verbucht!"
        );
    }

    public function render()
    {
        if (!Schema::hasTable('marketing_influencers')) {
            return view('livewire.shop.marketing.marketing-influencer', [
                'influencers' => collect(),
                'payouts' => collect(),
                'stats' => [
                    'active_count' => 0,
                    'pending_invitations' => 0,
                    'archived_count' => 0,
                    'total_clicks' => 0,
                    'total_unique_clicks' => 0,
                    'total_orders' => 0,
                    'total_revenue_cents' => 0,
                    'open_commission_cents' => 0,
                ],
            ]);
        }

        $query = MarketingInfluencerModel::with([
            'employee.profile',
            'defaultVoucher',
            'defaultTrackingLink',
            'trackingLinks',
            'commissions',
        ])->orderByDesc('created_at');

        if ($this->statusFilter === 'archived') {
            $query->where('is_archived', true);
        } elseif ($this->statusFilter === 'pending') {
            $query->where('is_archived', false)->whereNull('invitation_accepted_at');
        } elseif ($this->statusFilter === 'active') {
            $query->where('is_archived', false);
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('handle', 'like', $term)
                  ->orWhere('slug', 'like', $term)
                  ->orWhere('invitation_label', 'like', $term)
                  ->orWhereHas('employee', function ($eq) use ($term) {
                      $eq->where('first_name', 'like', $term)
                         ->orWhere('last_name', 'like', $term)
                         ->orWhere('email', 'like', $term);
                  });
            });
        }

        $influencers = $query->get();

        $allInfluencers = MarketingInfluencerModel::with(['trackingLinks', 'commissions'])->get();
        $totalClicks = $allInfluencers->sum(fn($i) => $i->trackingLinks->sum('clicks_count'));
        $totalUniqueClicks = $allInfluencers->sum(fn($i) => $i->trackingLinks->sum('unique_clicks_count'));

        $validCommissions = MarketingInfluencerCommission::whereIn('status', ['pending', 'approved', 'paid'])->get();
        $openCommissionCents = MarketingInfluencerCommission::whereIn('status', ['pending', 'approved'])
            ->whereNull('payout_id')
            ->sum('commission_amount_cents');

        $payouts = MarketingInfluencerPayout::with(['influencer.employee', 'giftVoucher', 'accountingSpecialIssue'])
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.shop.marketing.marketing-influencer', [
            'influencers' => $influencers,
            'payouts' => $payouts,
            'stats' => [
                'active_count' => $allInfluencers->where('is_archived', false)->whereNotNull('invitation_accepted_at')->where('is_active', true)->count(),
                'pending_invitations' => $allInfluencers->where('is_archived', false)->whereNull('invitation_accepted_at')->count(),
                'archived_count' => $allInfluencers->where('is_archived', true)->count(),
                'total_clicks' => (int) $totalClicks,
                'total_unique_clicks' => (int) $totalUniqueClicks,
                'total_orders' => $validCommissions->count(),
                'total_revenue_cents' => (int) $validCommissions->sum('order_net_amount_cents'),
                'open_commission_cents' => (int) $openCommissionCents,
            ],
        ]);
    }
}

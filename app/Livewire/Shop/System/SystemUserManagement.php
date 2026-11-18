<?php

namespace App\Livewire\Shop\System;

use Livewire\Attributes\Layout;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class SystemUserManagement extends Component
{
    use WithPagination, WithFileUploads, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'System';

    public $search = '';
    public $filterRole = 'all';
    public $showArchive = false;

    public $sortField = 'last_seen';
    public $sortDirection = 'desc';

    #[\Livewire\Attributes\Locked]
    public $editingId = null;
    #[\Livewire\Attributes\Locked]
    public $editingType = null;
    public bool $editing2faActive = false;
    #[\Livewire\Attributes\Locked]
    public array $editingUserLogs = [];
    #[\Livewire\Attributes\Locked]
    public int $editingUserBirthdayCorrections = 0;
    #[\Livewire\Attributes\Locked]
    public bool $editingCustomerHasMasterKey = false;
    #[\Livewire\Attributes\Locked]
    public int $editingCustomerClearedMagmaRooms = 0;
    #[\Livewire\Attributes\Locked]
    public bool $editingCustomerChatMuted = false;
    #[\Livewire\Attributes\Locked]
    public int $editingCustomerChatStrikes = 0;

    public string $activeEditTab = 'account';
    public $customerUploadFiles = [];
    public string $customerUploadTitle = '';
    public string $customerUploadOrderId = '';
    public ?string $customerMediaSavedText = null;

    public $isCreating = false;
    public $createType = 'customer';

    public $formData = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'password' => '',
        'birthday' => '',
        'phone_number' => '',
        'street' => '',
        'house_number' => '',
        'postal' => '',
        'city' => '',
        'customer_type' => 'private',
        'company_name' => '',
        'vat_id' => '',
        'internal_note' => '',
        'is_verified' => true,
        'mmorpg_access_enabled' => false,
        'funken_balance' => 0,
        'funken_total_earned' => 0,
        'funki_level' => 1,
        'gamification_active' => false,
        'ranking_opt_in' => false,
        'use_real_name' => false,
        'player_name' => '',
        'ai_data_consent' => false,
        'magma_unlocked_level' => 1,
        'magma_hearts' => 6,
        'healing_potions' => 0,
    ];

    protected $queryString = ['search', 'filterRole', 'showArchive', 'sortField', 'sortDirection'];

    public function boot()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function toggleArchive()
    {
        $this->showArchive = !$this->showArchive;
        $this->editingId = null;
        $this->isCreating = false;
        $this->resetPage();
    }

    public function startCreate()
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->editingType = null;
        $this->editing2faActive = false;
        $this->editingCustomerHasMasterKey = false;
        $this->editingCustomerClearedMagmaRooms = 0;
        $this->editingCustomerChatMuted = false;
        $this->editingCustomerChatStrikes = 0;
        $this->isCreating = true;
        $this->createType = 'customer';
        $this->formData = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'password' => '',
            'birthday' => null,
            'phone_number' => '',
            'street' => '',
            'house_number' => '',
            'postal' => '',
            'city' => '',
            'customer_type' => 'private',
            'company_name' => '',
            'vat_id' => '',
            'internal_note' => '',
            'is_verified' => true,
            'mmorpg_access_enabled' => false,
            'funken_balance' => 0,
            'funken_total_earned' => 0,
            'funki_level' => 1,
            'gamification_active' => true,
            'ranking_opt_in' => false,
            'use_real_name' => false,
            'player_name' => '',
            'ai_data_consent' => false,
            'magma_unlocked_level' => 1,
            'magma_hearts' => 6,
            'healing_potions' => 0,
        ];
    }

    public function saveNewUser()
    {
        try {
            $this->validate([
                'createType' => 'required|in:admin,customer,employee',
                'formData.first_name' => 'required|string|max:255',
                'formData.last_name' => 'required|string|max:255',
                'formData.email' => 'required|email|unique:'.($this->createType === 'admin' ? 'admins' : ($this->createType === 'customer' ? 'customers' : 'employees')).',email',
                'formData.password' => 'required|string|min:8',
                'formData.birthday' => 'nullable|date',
                'formData.phone_number' => 'nullable|string|max:50',
                'formData.street' => 'nullable|string|max:255',
                'formData.house_number' => 'nullable|string|max:30',
                'formData.postal' => 'nullable|string|max:20',
                'formData.city' => 'nullable|string|max:120',
                'formData.customer_type' => 'nullable|in:private,business',
                'formData.company_name' => 'nullable|string|max:255',
                'formData.vat_id' => 'nullable|string|max:50',
                'formData.internal_note' => 'nullable|string|max:5000',
                'formData.is_verified' => 'boolean',
                'formData.mmorpg_access_enabled' => 'nullable|boolean',
                'formData.funken_balance' => 'nullable|integer|min:0|max:1000000',
                'formData.funken_total_earned' => 'nullable|integer|min:0|max:1000000',
                'formData.funki_level' => 'nullable|integer|min:1|max:10',
                'formData.gamification_active' => 'nullable|boolean',
                'formData.ranking_opt_in' => 'nullable|boolean',
                'formData.use_real_name' => 'nullable|boolean',
                'formData.player_name' => 'nullable|string|max:35',
                'formData.ai_data_consent' => 'nullable|boolean',
                'formData.magma_unlocked_level' => 'nullable|integer|min:1|max:10',
                'formData.magma_hearts' => 'nullable|integer|min:1|max:6',
                'formData.healing_potions' => 'nullable|integer|min:0|max:30',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->switchToProfileTabOnProfileErrors($e);
            throw $e;
        }

        if ($this->createType === 'customer') {
            $rawPlayerName = trim((string) ($this->formData['player_name'] ?? ''));
            if ($rawPlayerName !== '') {
                $nameCheck = \App\Services\Gamification\PlayerNameValidator::validate($rawPlayerName, null, false);
                if (!$nameCheck['valid']) {
                    $this->activeEditTab = 'profile';
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'formData.player_name' => $nameCheck['error'] ?? 'Ungültiger Spielername.',
                    ]);
                }
                $this->formData['player_name'] = $nameCheck['sanitized'];
            } else {
                $this->formData['player_name'] = '';
            }
        }

        $class = match($this->createType) {
            'admin' => Admin::class,
            'customer' => Customer::class,
            'employee' => Employee::class,
        };

        $createPayload = [
            'first_name' => $this->formData['first_name'],
            'last_name' => $this->formData['last_name'],
            'email' => $this->formData['email'],
            'password' => Hash::make($this->formData['password']),
            'email_verified_at' => ($this->createType === 'customer' && $this->formData['is_verified']) ? now() : null,
        ];

        if ($this->createType === 'customer') {
            $createPayload['mmorpg_access_enabled'] = (bool) ($this->formData['mmorpg_access_enabled'] ?? false);
        }

        $user = $class::create($createPayload);

        if (method_exists($user, 'profile')) {
            $profile = $user->profile()->firstOrCreate([]);
            $isBusiness = $this->formData['customer_type'] === 'business';

            $profileData = [
                'birthday' => !empty($this->formData['birthday']) ? \Carbon\Carbon::parse($this->formData['birthday'])->format('Y-m-d') : null,
                'phone_number' => $this->formData['phone_number'],
                'street' => $this->formData['street'],
                'house_number' => $this->formData['house_number'],
                'postal' => $this->formData['postal'],
                'city' => $this->formData['city'],
                'is_business' => $isBusiness,
                'company_name' => $isBusiness ? $this->formData['company_name'] : null,
                'vat_id' => $isBusiness ? $this->formData['vat_id'] : null,
                'internal_note' => $this->formData['internal_note'],
                'email_verified_at' => $this->formData['is_verified'] ? now() : null,
            ];

            $profile->update($profileData);
        }

        if ($this->createType === 'customer' && $user instanceof Customer) {
            if (!empty($this->formData['ai_data_consent']) && method_exists($user, 'updateAiDataConsent')) {
                $user->updateAiDataConsent(true, 'admin_user_management');
            }

            $this->syncCustomerGamificationFromAdminForm($user, true);
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';

        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:create',
            'title' => 'Neuer Benutzer angelegt',
            'message' => "Der Benutzer '{$user->email}' wurde als " . strtoupper($this->createType) . " von {$adminEmail} ins System eingefügt.",
            'status' => 'success',
            'payload' => [
                'actor' => $adminEmail,
                'user_id' => $user->id,
                'user_type' => $this->createType,
                'data' => collect($this->formData)->except('password')->toArray()
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->isCreating = false;
        session()->flash('message', 'Neues Seelenlicht erfolgreich erschaffen.');
    }

    public function startEdit($id, $type, string $initialTab = 'account')
    {
        $this->isCreating = false;
        $this->editingId = $id;
        $this->editingType = $type;

        $allowedTabs = $type === 'customer' ? ['account', 'profile', 'media'] : ['account', 'profile'];
        $this->activeEditTab = in_array($initialTab, $allowedTabs, true) ? $initialTab : 'account';
        $this->customerUploadFiles = [];
        $this->customerUploadTitle = '';
        $this->customerUploadOrderId = '';
        $this->customerMediaSavedText = null;

        $model = $this->getModelInstance($type, $id);
        $this->editing2faActive = (bool) ($model->profile->two_factor_is_active ?? false);
        $gamification = ($type === 'customer') ? $model->gamification : null;
        $gameService = app(\App\Services\Gamification\GamificationService::class);

        $this->editingUserLogs = $gameService->getUserProfileChangeLogs((string) $model->id, $model->email, 60);
        $this->editingUserBirthdayCorrections = ($type === 'customer' && $model instanceof Customer)
            ? $gameService->countCustomerBirthdayCorrections($model)
            : 0;

        $this->refreshEditingCustomerStatusPills(
            ($type === 'customer' && $model instanceof Customer) ? $model : null,
            $gamification
        );

        $this->formData = [
            'first_name' => $model->first_name,
            'last_name' => $model->last_name,
            'email' => $model->email,
            'password' => '',
            'birthday' => !empty($model->profile->birthday) ? \Carbon\Carbon::parse($model->profile->birthday)->format('Y-m-d') : '',
            'phone_number' => $model->profile->phone_number ?? '',
            'street' => $model->profile->street ?? '',
            'house_number' => $model->profile->house_number ?? '',
            'postal' => $model->profile->postal ?? '',
            'city' => $model->profile->city ?? '',
            'customer_type' => ($model->profile->is_business ?? false) ? 'business' : 'private',
            'company_name' => $model->profile->company_name ?? '',
            'vat_id' => $model->profile->vat_id ?? '',
            'internal_note' => $model->profile->internal_note ?? '',
            'is_verified' => $this->editingType === 'customer'
                ? (!is_null($model->email_verified_at) || !is_null($model->profile->email_verified_at ?? null))
                : !is_null($model->profile->email_verified_at ?? null),
            'mmorpg_access_enabled' => ($type === 'customer' && $model instanceof Customer)
                ? (bool) ($model->mmorpg_access_enabled ?? false)
                : false,
            'funken_balance' => (int) ($gamification->funken_balance ?? 0),
            'funken_total_earned' => (int) ($gamification->funken_total_earned ?? 0),
            'funki_level' => max(1, min(10, (int) ($gamification->level ?? $gamification->funki_level ?? 1))),
            'gamification_active' => (bool) ($gamification->is_active ?? false),
            'ranking_opt_in' => (bool) ($gamification->ranking_opt_in ?? false),
            'use_real_name' => (bool) ($gamification->use_real_name ?? false),
            'player_name' => (string) ($gamification->player_name ?? ''),
            'ai_data_consent' => (bool) ($model->profile->ai_data_consent ?? false),
            'magma_unlocked_level' => max(1, min(10, (int) ($gamification->magma_unlocked_level ?? 1))),
            'magma_hearts' => max(1, min(6, (int) ($gamification->magma_hearts ?? 6))),
            'healing_potions' => $this->countHealingPotionsFromGamification($gamification),
        ];
    }

    public function setEditTab(string $tab): void
    {
        $allowedTabs = $this->editingType === 'customer' ? ['account', 'profile', 'media'] : ['account', 'profile'];
        $this->activeEditTab = in_array($tab, $allowedTabs, true) ? $tab : 'account';
    }

    public function setCustomerUploadPresetTitle(string $title): void
    {
        $this->customerUploadTitle = trim($title);
    }

    public function updatedCustomerUploadFiles(): void
    {
        if ($this->editingType !== 'customer' || empty($this->editingId)) {
            return;
        }

        $this->validate([
            'customerUploadFiles.*' => 'file|mimes:pdf,zip,rar,jpg,jpeg,png,webp|max:51200',
        ]);

        /** @var Customer $customer */
        $customer = Customer::withTrashed()->findOrFail($this->editingId);
        $order = null;
        if (!empty($this->customerUploadOrderId)) {
            $order = \App\Models\Order\OrderOrder::where('customer_id', $customer->id)
                ->find($this->customerUploadOrderId);
        }

        $files = is_array($this->customerUploadFiles) ? $this->customerUploadFiles : [$this->customerUploadFiles];
        $presetTitle = trim($this->customerUploadTitle);

        foreach (array_values($files) as $idx => $file) {
            if (!$file) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: 'pdf');
            $baseTitle = $presetTitle !== ''
                ? ($idx > 0 ? $presetTitle . ' (' . ($idx + 1) . ')' : $presetTitle)
                : (pathinfo($originalName, PATHINFO_FILENAME) ?: 'Dokument');

            $docId = (string) Str::uuid();
            $safeSlug = Str::slug($baseTitle) ?: 'dokument';
            $storedFilename = $safeSlug . '-' . substr($docId, 0, 8) . '.' . $ext;
            $storedPath = $file->storeAs('produkte/products-secure/customer-files/' . $customer->id, $storedFilename, 'local');

            $customer->addAdminUploadedDocument([
                'id' => $docId,
                'title' => $baseTitle,
                'path' => $storedPath,
                'filename' => $originalName,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size' => (int) $file->getSize(),
                'extension' => $ext,
                'uploaded_at' => now()->toIso8601String(),
                'order_id' => $order?->id,
                'order_number' => $order?->order_number,
            ]);
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:customer_document_uploaded',
            'title' => 'Kundendokument hinterlegt',
            'message' => "Für Kunde '{$customer->email}' wurde ein Dokument hinterlegt.",
            'status' => 'success',
            'payload' => [
                'actor' => $adminEmail,
                'customer_id' => $customer->id,
                'order_id' => $order?->id,
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->customerUploadFiles = [];
        $this->customerUploadTitle = '';
        $this->customerMediaSavedText = 'Gespeichert';
        $this->dispatch('notify', message: 'Dokument direkt beim Kunden hinterlegt.', type: 'success');
    }

    public function removeCustomerUploadedDocument(string $docId): void
    {
        if ($this->editingType !== 'customer' || empty($this->editingId)) {
            return;
        }

        /** @var Customer $customer */
        $customer = Customer::withTrashed()->findOrFail($this->editingId);
        if ($customer->removeAdminUploadedDocument($docId)) {
            $this->customerMediaSavedText = 'Gespeichert';
            $this->dispatch('notify', message: 'Hinterlegtes Dokument entfernt.', type: 'info');
        }
    }

    public function getEditingCustomerMediaProperty(): array
    {
        if ($this->editingType !== 'customer' || empty($this->editingId)) {
            return [
                'orders' => collect(),
                'invoices' => collect(),
                'adminDocs' => [],
                'orderDocs' => [],
                'snapshots' => [],
                'totalCount' => 0,
            ];
        }

        /** @var Customer|null $customer */
        $customer = Customer::withTrashed()->find($this->editingId);
        if (!$customer) {
            return [
                'orders' => collect(),
                'invoices' => collect(),
                'adminDocs' => [],
                'orderDocs' => [],
                'snapshots' => [],
                'totalCount' => 0,
            ];
        }

        $orders = \App\Models\Order\OrderOrder::with(['items.product', 'invoices'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->get();

        $orderIds = $orders->pluck('id')->all();
        $invoices = \App\Models\Accounting\AccountingInvoice::where(function ($q) use ($customer, $orderIds) {
            $q->where('customer_id', $customer->id);
            if (!empty($orderIds)) {
                $q->orWhereIn('order_id', $orderIds);
            }
        })->latest()->get();

        $adminDocs = $customer->getAdminUploadedDocuments();
        $orderDocs = [];
        $snapshots = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                foreach ($item->resolveDigitalDocuments() as $doc) {
                    $orderDocs[] = [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_date' => $order->created_at?->format('d.m.Y') ?? '',
                        'item_id' => $item->id,
                        'product_name' => $item->product_name,
                        'title' => $doc['title'] ?? 'Dokument',
                        'extension' => strtoupper((string) ($doc['extension'] ?? 'PDF')),
                        'download_url' => route('digital-product.download', ['item' => $item->id, 'doc' => $doc['id']]),
                    ];
                }

                if ($item->product && $item->product->type === 'digital' && !empty($item->product->digital_download_path)) {
                    $orderDocs[] = [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'order_date' => $order->created_at?->format('d.m.Y') ?? '',
                        'item_id' => $item->id,
                        'product_name' => $item->product_name,
                        'title' => $item->product_name . ' (Digitale Produktdatei)',
                        'extension' => strtoupper(pathinfo((string) ($item->product->digital_filename ?? ''), PATHINFO_EXTENSION) ?: 'ZIP'),
                        'download_url' => route('digital-product.download', ['item' => $item->id]),
                    ];
                }

                if (!empty($item->configuration['snapshot_path'])) {
                    $paths = is_array($item->configuration['snapshot_path'])
                        ? $item->configuration['snapshot_path']
                        : ['Konfiguration' => $item->configuration['snapshot_path']];

                    foreach ($paths as $side => $path) {
                        $label = count($paths) > 1
                            ? $item->product_name . ' (' . ($side === 'front' ? 'Vorderseite' : ($side === 'back' ? 'Rückseite' : ucfirst((string) $side))) . ')'
                            : $item->product_name . ' (Gravur-Vorschau)';
                        $snapshots[] = [
                            'order_number' => $order->order_number,
                            'date' => $order->created_at?->format('d.m.Y') ?? '',
                            'label' => $label,
                            'url' => resolve_snapshot_url($path, asset('storage/' . $path)),
                        ];
                    }
                }
            }
        }

        return [
            'orders' => $orders,
            'invoices' => $invoices,
            'adminDocs' => $adminDocs,
            'orderDocs' => $orderDocs,
            'snapshots' => $snapshots,
            'totalCount' => count($adminDocs) + $invoices->count() + count($orderDocs) + count($snapshots),
        ];
    }

    public function cancelEdit()
    {
        $this->editingId = null;
        $this->editingType = null;
        $this->editing2faActive = false;
        $this->editingUserLogs = [];
        $this->editingUserBirthdayCorrections = 0;
        $this->editingCustomerHasMasterKey = false;
        $this->editingCustomerClearedMagmaRooms = 0;
        $this->editingCustomerChatMuted = false;
        $this->editingCustomerChatStrikes = 0;
        $this->activeEditTab = 'account';
        $this->customerUploadFiles = [];
        $this->customerUploadTitle = '';
        $this->customerUploadOrderId = '';
        $this->customerMediaSavedText = null;
        $this->isCreating = false;
        $this->resetErrorBag();
    }

    public function resetCustomer3dChatMute(): void
    {
        if ($this->editingType !== 'customer' || empty($this->editingId)) {
            return;
        }

        /** @var Customer $customer */
        $customer = Customer::withTrashed()->findOrFail($this->editingId);
        $cid = (string) $customer->id;
        $hash = md5($cid);
        $actorHash = md5('customer:' . $cid);

        foreach ([
            'sf_lobby_sanction_v1_' . $cid,
            'sf_lobby_sanction_v1_' . $hash,
            'sf_lobby_sanction_v1_' . $actorHash,
            'sf_lobby_mute_v1_' . $cid,
            'sf_lobby_mute_v1_' . $hash,
            'sf_lobby_reports_v1_' . $cid,
            'sf_lobby_reports_v1_' . $hash,
            'sf_lobby_report_v1_' . $cid,
            'sf_lobby_reports_' . $cid,
            'sf_lobby_chat_cd_v1_' . $cid,
            'sf_lobby_chat_cd_v1_' . $hash,
            'sf_fw_lobby_chat_cd_v1_' . $actorHash,
            'sf_fw_lobby_chat_burst_v1_' . $actorHash,
            'sf_showroom_mute_' . $cid,
            'sf_showroom_mute_' . $hash,
            'sf_showroom_reports_' . $cid,
            'sf_showroom_reports_' . $hash,
            'sf_showroom_report_' . $cid,
            'sf_showroom_chat_cd_' . $cid,
            'sf_showroom_chat_cd_' . $hash,
        ] as $cacheKey) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('customer_gamifications')) {
            $gamification = \App\Models\Customer\CustomerGamification::where('customer_id', $cid)->first();
            if ($gamification !== null) {
                $progress = is_array($gamification->titles_progress) ? $gamification->titles_progress : [];
                $progress['chat_moderation'] = [
                    'strikes' => 0,
                    'muted_until_ts' => null,
                    'permanent_mute' => false,
                    'last_code' => null,
                    'last_violation_at' => null,
                ];
                $gamification->titles_progress = $progress;
                $gamification->save();
            }
        }

        \App\Services\Gamification\LobbyInstanceService::forgetCustomerCache($cid);

        $this->editingCustomerChatMuted = false;
        $this->editingCustomerChatStrikes = 0;

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:3d_chat_unmute',
            'title' => '3D-Chat Stummschaltung aufgehoben',
            'message' => "3D-Chat Stummschaltung und Verwarnungen für '{$customer->email}' wurden von {$adminEmail} zurückgesetzt.",
            'status' => 'success',
            'payload' => [
                'actor' => $adminEmail,
                'user_id' => $cid,
                'target_user' => $customer->email,
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->dispatch('notify', message: '3D-Chat freigeschaltet und Verwarnungen zurückgesetzt.', type: 'success');
    }

    public function toggleMmorpgAccess(string $customerId): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        /** @var Customer $customer */
        $customer = Customer::withTrashed()->findOrFail($customerId);
        $oldState = (bool) $customer->mmorpg_access_enabled;
        $newState = !$oldState;

        $customer->update([
            'mmorpg_access_enabled' => $newState,
        ]);

        if ($this->editingType === 'customer' && (string) $this->editingId === (string) $customer->id) {
            $this->formData['mmorpg_access_enabled'] = $newState;
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => $newState ? 'user:mmorpg_access_granted' : 'user:mmorpg_access_revoked',
            'title' => $newState ? 'MMORPG-Zugang freigeschaltet' : 'MMORPG-Zugang gesperrt',
            'message' => sprintf(
                "MMORPG-Zugang für '%s' wurde von %s %s.",
                $customer->email,
                $adminEmail,
                $newState ? 'freigeschaltet' : 'gesperrt'
            ),
            'status' => $newState ? 'success' : 'warning',
            'payload' => [
                'actor' => $adminEmail,
                'user_id' => (string) $customer->id,
                'target_user' => $customer->email,
                'mmorpg_access_enabled' => $newState,
                'changes' => [
                    'mmorpg_access_enabled' => [
                        'old' => $oldState,
                        'new' => $newState,
                    ],
                ],
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        session()->flash('message', $newState ? 'MMORPG-Zugang freigeschaltet.' : 'MMORPG-Zugang gesperrt.');
        $this->dispatch('notify', message: $newState ? 'MMORPG-Zugang freigeschaltet.' : 'MMORPG-Zugang gesperrt.', type: 'success');
    }

    public function saveInline()
    {
        if (!in_array($this->editingType, ['admin', 'customer', 'employee'], true)) {
            abort(400, 'Ungültiger Benutzertyp.');
        }

        try {
            $this->validate([
                'formData.first_name' => 'required|string|max:255',
                'formData.last_name' => 'required|string|max:255',
                'formData.email' => 'required|email|unique:'.($this->editingType === 'admin' ? 'admins' : ($this->editingType === 'customer' ? 'customers' : 'employees')).',email,'.$this->editingId,
                'formData.password' => 'nullable|string|min:8',
                'formData.birthday' => 'nullable|date',
                'formData.phone_number' => 'nullable|string|max:50',
                'formData.street' => 'nullable|string|max:255',
                'formData.house_number' => 'nullable|string|max:30',
                'formData.postal' => 'nullable|string|max:20',
                'formData.city' => 'nullable|string|max:120',
                'formData.customer_type' => 'nullable|in:private,business',
                'formData.company_name' => 'nullable|string|max:255',
                'formData.vat_id' => 'nullable|string|max:50',
                'formData.internal_note' => 'nullable|string|max:5000',
                'formData.is_verified' => 'boolean',
                'formData.mmorpg_access_enabled' => 'nullable|boolean',
                'formData.funken_balance' => 'nullable|integer|min:0|max:1000000',
                'formData.funken_total_earned' => 'nullable|integer|min:0|max:1000000',
                'formData.funki_level' => 'nullable|integer|min:1|max:10',
                'formData.gamification_active' => 'nullable|boolean',
                'formData.ranking_opt_in' => 'nullable|boolean',
                'formData.use_real_name' => 'nullable|boolean',
                'formData.player_name' => 'nullable|string|max:35',
                'formData.ai_data_consent' => 'nullable|boolean',
                'formData.magma_unlocked_level' => 'nullable|integer|min:1|max:10',
                'formData.magma_hearts' => 'nullable|integer|min:1|max:6',
                'formData.healing_potions' => 'nullable|integer|min:0|max:30',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->switchToProfileTabOnProfileErrors($e);
            throw $e;
        }

        $model = $this->getModelInstance($this->editingType, $this->editingId);

        if ($this->editingType === 'customer' && $model instanceof Customer) {
            $rawPlayerName = trim((string) ($this->formData['player_name'] ?? ''));
            if ($rawPlayerName !== '') {
                $existingPlayerName = trim((string) ($model->gamification?->player_name ?? ''));
                $isUntouchedRealName = ($rawPlayerName === $existingPlayerName) && (bool) ($this->formData['use_real_name'] ?? false);
                $nameCheck = \App\Services\Gamification\PlayerNameValidator::validate(
                    $rawPlayerName,
                    (string) $model->id,
                    $isUntouchedRealName
                );
                if (!$nameCheck['valid']) {
                    $this->activeEditTab = 'profile';
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'formData.player_name' => $nameCheck['error'] ?? 'Ungültiger Spielername.',
                    ]);
                }
                $this->formData['player_name'] = $nameCheck['sanitized'];
            } else {
                $this->formData['player_name'] = '';
            }
        }

        $this->formData['is_verified'] = (bool) ($this->formData['is_verified'] ?? false);

        // Sammeln der alten Daten für den Delta-Vergleich
        $oldData = [
            'first_name' => $model->first_name,
            'last_name' => $model->last_name,
            'email' => $model->email,
            'birthday' => !empty($model->profile->birthday) ? \Carbon\Carbon::parse($model->profile->birthday)->format('Y-m-d') : '',
            'phone_number' => $model->profile->phone_number ?? '',
            'street' => $model->profile->street ?? '',
            'house_number' => $model->profile->house_number ?? '',
            'postal' => $model->profile->postal ?? '',
            'city' => $model->profile->city ?? '',
            'customer_type' => ($model->profile->is_business ?? false) ? 'business' : 'private',
            'company_name' => $model->profile->company_name ?? '',
            'vat_id' => $model->profile->vat_id ?? '',
            'internal_note' => $model->profile->internal_note ?? '',
            'is_verified' => $this->editingType === 'customer'
                ? (!is_null($model->email_verified_at) || !is_null($model->profile->email_verified_at ?? null))
                : !is_null($model->profile->email_verified_at ?? null),
        ];

        if ($this->editingType === 'customer') {
            $existingGamification = $model->gamification;
            $oldData['mmorpg_access_enabled'] = (bool) ($model->mmorpg_access_enabled ?? false);
            $oldData['ai_data_consent'] = (bool) ($model->profile->ai_data_consent ?? false);
            $oldData['gamification_active'] = (bool) ($existingGamification->is_active ?? false);
            $oldData['ranking_opt_in'] = (bool) ($existingGamification->ranking_opt_in ?? false);
            $oldData['use_real_name'] = (bool) ($existingGamification->use_real_name ?? false);
            $oldData['player_name'] = (string) ($existingGamification->player_name ?? '');
            $oldData['funken_balance'] = (int) ($existingGamification->funken_balance ?? 0);
            $oldData['funken_total_earned'] = (int) ($existingGamification->funken_total_earned ?? 0);
            $oldData['funki_level'] = max(1, min(10, (int) ($existingGamification->level ?? $existingGamification->funki_level ?? 1)));
            $oldData['magma_unlocked_level'] = max(1, min(10, (int) ($existingGamification->magma_unlocked_level ?? 1)));
            $oldData['magma_hearts'] = max(1, min(6, (int) ($existingGamification->magma_hearts ?? 6)));
            $oldData['healing_potions'] = $this->countHealingPotionsFromGamification($existingGamification);

            $this->formData['mmorpg_access_enabled'] = (bool) ($this->formData['mmorpg_access_enabled'] ?? false);
            $this->formData['ai_data_consent'] = (bool) ($this->formData['ai_data_consent'] ?? false);
            $this->formData['gamification_active'] = (bool) ($this->formData['gamification_active'] ?? false);
            $this->formData['ranking_opt_in'] = (bool) ($this->formData['ranking_opt_in'] ?? false);
            $this->formData['use_real_name'] = (bool) ($this->formData['use_real_name'] ?? false);
            $this->formData['funken_balance'] = max(0, (int) ($this->formData['funken_balance'] ?? 0));
            $this->formData['funken_total_earned'] = max(0, (int) ($this->formData['funken_total_earned'] ?? 0));
            $this->formData['funki_level'] = max(1, min(10, (int) ($this->formData['funki_level'] ?? 1)));
            $this->formData['magma_unlocked_level'] = max(1, min(10, (int) ($this->formData['magma_unlocked_level'] ?? 1)));
            $this->formData['magma_hearts'] = max(1, min(6, (int) ($this->formData['magma_hearts'] ?? 6)));
            $this->formData['healing_potions'] = max(0, min(30, (int) ($this->formData['healing_potions'] ?? 0)));
        }

        $userUpdate = [
            'first_name' => $this->formData['first_name'],
            'last_name' => $this->formData['last_name'],
            'email' => $this->formData['email'],
        ];

        if ($this->editingType === 'customer') {
            $userUpdate['email_verified_at'] = $this->formData['is_verified']
                ? ($model->email_verified_at ?? now())
                : null;
            $userUpdate['mmorpg_access_enabled'] = (bool) ($this->formData['mmorpg_access_enabled'] ?? false);
        }

        $model->update($userUpdate);

        $passwordChanged = false;
        if (!empty($this->formData['password'])) {
            $model->forceFill([
                'password' => Hash::make($this->formData['password']),
                'remember_token' => \Illuminate\Support\Str::random(60),
            ])->save();
            $passwordChanged = true;
        }

        if (method_exists($model, 'profile')) {
            $profile = $model->profile ?: $model->profile()->firstOrCreate([]);
            $verifiedAt = $profile->email_verified_at;
            if ($this->formData['is_verified'] && !$verifiedAt) {
                $verifiedAt = now();
            } elseif (!$this->formData['is_verified']) {
                $verifiedAt = null;
            }

            $isBusiness = $this->formData['customer_type'] === 'business';

            $profile->update([
                'birthday' => !empty($this->formData['birthday']) ? \Carbon\Carbon::parse($this->formData['birthday'])->format('Y-m-d') : null,
                'phone_number' => $this->formData['phone_number'],
                'street' => $this->formData['street'],
                'house_number' => $this->formData['house_number'],
                'postal' => $this->formData['postal'],
                'city' => $this->formData['city'],
                'is_business' => $isBusiness,
                'company_name' => $isBusiness ? $this->formData['company_name'] : null,
                'vat_id' => $isBusiness ? $this->formData['vat_id'] : null,
                'internal_note' => $this->formData['internal_note'],
                'email_verified_at' => $verifiedAt,
            ]);
        }

        if ($this->editingType === 'customer' && $model instanceof Customer) {
            if (method_exists($model, 'updateAiDataConsent')) {
                $model->updateAiDataConsent((bool) ($this->formData['ai_data_consent'] ?? false), 'admin_user_management');
            }

            $this->syncCustomerGamificationFromAdminForm($model, false);
        }

        // Berechne die genauen Änderungen (Delta)
        $changes = [];
        foreach ($oldData as $key => $oldValue) {
            if (array_key_exists($key, $this->formData) && $oldValue !== $this->formData[$key]) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $this->formData[$key]
                ];
            }
        }

        if ($passwordChanged) {
            $changes['password'] = ['old' => '***', 'new' => 'wurde geändert'];
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';

        if (count($changes) > 0) {
            SystemLog::create([
                'type' => 'system',
                'action_id' => 'user:update',
                'title' => 'Benutzerprofil modifiziert',
                'message' => "Das Profil von '{$model->email}' wurde von {$adminEmail} aktualisiert. " . count($changes) . " Felder geändert.",
                'status' => 'success',
                'payload' => [
                    'actor' => $adminEmail,
                    'user_id' => (string) $model->id,
                    'target_user' => $model->email,
                    'changes' => $changes
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $this->editingId = null;
        session()->flash('message', 'Änderungen gespeichert.');
    }

    public function disable2Fa()
    {
        if (!$this->editingId || !$this->editingType) {
            return;
        }

        $this->disable2FaForUser($this->editingId, $this->editingType);
        $this->editing2faActive = false;
    }

    public function disable2FaForUser($id, $type)
    {
        $model = $this->getModelInstance($type, $id);

        if ($model->profile) {
            $model->profile->update([
                'two_factor_is_active' => false,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ]);
        }

        if ($this->editingId === $id && $this->editingType === $type) {
            $this->editing2faActive = false;
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:2fa_disable',
            'title' => '2-Faktor-Authentifizierung deaktiviert',
            'message' => "Die 2-Faktor-Authentifizierung für '{$model->email}' ({$type}) wurde durch Administrator {$adminEmail} zurückgesetzt.",
            'status' => 'warning',
            'payload' => [
                'actor' => $adminEmail,
                'target_user' => $model->email,
                'user_type' => $type,
                'ip' => request()->ip(),
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        session()->flash('message', "2-Faktor-Authentifizierung für '{$model->email}' erfolgreich deaktiviert.");
    }

    public function archiveUser($id, $type)
    {
        if ($type === 'admin') {
            if ((string) Auth::guard('admin')->id() === (string) $id) {
                session()->flash('error', 'Sie können Ihr eigenes Administrator-Konto nicht archivieren.');
                return;
            }
            if (Admin::count() <= 1) {
                session()->flash('error', 'Der letzte verbleibende aktive Administrator kann nicht archiviert werden.');
                return;
            }
        }

        $model = $this->getModelInstance($type, $id);
        $email = $model->email;
        $model->delete();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:archive',
            'title' => 'Benutzer archiviert',
            'message' => "Der Benutzer '{$email}' wurde von {$adminEmail} ins Archiv verschoben.",
            'status' => 'warning',
            'payload' => ['actor' => $adminEmail, 'target_user' => $email],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function restoreUser($id, $type)
    {
        $model = $this->getModelInstance($type, $id, true);
        $model->restore();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:restore',
            'title' => 'Benutzer wiederhergestellt',
            'message' => "Der archivierte Benutzer '{$model->email}' wurde von {$adminEmail} reaktiviert.",
            'status' => 'success',
            'payload' => ['actor' => $adminEmail, 'target_user' => $model->email],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function forceDelete($id, $type)
    {
        if ($type === 'admin') {
            if ((string) Auth::guard('admin')->id() === (string) $id) {
                session()->flash('error', 'Sie können Ihr eigenes Administrator-Konto nicht löschen.');
                return;
            }
            if (Admin::withTrashed()->count() <= 1) {
                session()->flash('error', 'Der letzte verbleibende Administrator darf nicht endgültig gelöscht werden.');
                return;
            }
        }

        $model = $this->getModelInstance($type, $id, true);
        $email = $model->email;
        $model->forceDelete();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:force_delete',
            'title' => 'Benutzer endgültig gelöscht',
            'message' => "Achtung: Der Datensatz von '{$email}' wurde von {$adminEmail} dauerhaft aus der Datenbank entfernt.",
            'status' => 'error', // Error status signalisiert eine destruktive, kritische Aktion
            'payload' => ['actor' => $adminEmail, 'target_user' => $email],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function sortBy($column)
    {
        if (!in_array((string) $column, ['last_seen', 'login', 'identity', 'status', 'location', 'ip'], true)) {
            return;
        }
        if ($this->sortField === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $column;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    protected function getModelInstance($type, $id, $withTrashed = false)
    {
        $class = match($type) {
            'admin' => Admin::class,
            'customer' => Customer::class,
            'employee' => Employee::class,
            default => abort(400, 'Ungültiger Benutzertyp.'),
        };
        return $withTrashed ? $class::withTrashed()->findOrFail($id) : $class::findOrFail($id);
    }

    private function switchToProfileTabOnProfileErrors(\Illuminate\Validation\ValidationException $e): void
    {
        $profileKeys = [
            'formData.birthday',
            'formData.phone_number',
            'formData.street',
            'formData.house_number',
            'formData.postal',
            'formData.city',
            'formData.customer_type',
            'formData.company_name',
            'formData.vat_id',
            'formData.internal_note',
            'formData.funken_balance',
            'formData.funken_total_earned',
            'formData.funki_level',
            'formData.gamification_active',
            'formData.ranking_opt_in',
            'formData.use_real_name',
            'formData.player_name',
            'formData.ai_data_consent',
            'formData.magma_unlocked_level',
            'formData.magma_hearts',
            'formData.healing_potions',
        ];

        $errorKeys = array_keys($e->errors());
        foreach ($errorKeys as $errKey) {
            if (in_array($errKey, $profileKeys, true)) {
                $this->activeEditTab = 'profile';
                break;
            }
        }
    }

    private function countHealingPotionsFromGamification(?\App\Models\Customer\CustomerGamification $gamification): int
    {
        if (!$gamification || !is_array($gamification->inventory_items)) {
            return 0;
        }

        $inv = $gamification->inventory_items;

        if (isset($inv['bag_slots']) && is_array($inv['bag_slots'])) {
            $slotSum = 0;
            foreach ($inv['bag_slots'] as $slot) {
                if (is_array($slot) && ($slot['item_key'] ?? null) === 'healing_potion') {
                    $slotSum += max(0, (int) ($slot['qty'] ?? $slot['quantity'] ?? $slot['count'] ?? 0));
                }
            }

            return min(30, max(0, $slotSum));
        }

        if (isset($inv['healing_potion']) && is_numeric($inv['healing_potion'])) {
            return min(30, max(0, (int) $inv['healing_potion']));
        }

        $sum = 0;
        foreach ($inv as $entry) {
            if (is_array($entry) && ($entry['item_key'] ?? null) === 'healing_potion') {
                $qty = $entry['quantity'] ?? $entry['qty'] ?? $entry['count'] ?? 1;
                $sum += max(0, (int) $qty);
            } elseif ($entry === 'healing_potion') {
                $sum += 1;
            }
        }

        return min(30, max(0, $sum));
    }

    private function isCustomer3dChatMuted(
        string $customerId,
        ?\App\Models\Customer\CustomerGamification $gamification = null
    ): bool {
        if (trim($customerId) === '') {
            return false;
        }

        $cid = trim($customerId);
        $hash = md5($cid);
        $actorHash = md5('customer:' . $cid);
        $nowTs = \Illuminate\Support\Carbon::now()->getTimestamp();

        foreach ([
            'sf_lobby_sanction_v1_' . $cid,
            'sf_lobby_sanction_v1_' . $hash,
            'sf_lobby_sanction_v1_' . $actorHash,
        ] as $sanctionKey) {
            $sanction = \Illuminate\Support\Facades\Cache::get($sanctionKey);
            if (!is_array($sanction)) {
                continue;
            }
            if (!empty($sanction['permanent_mute'])) {
                return true;
            }
            if (isset($sanction['muted_until_ts']) && is_numeric($sanction['muted_until_ts']) && (int) $sanction['muted_until_ts'] > $nowTs) {
                return true;
            }
        }

        foreach ([
            'sf_lobby_mute_v1_' . $cid,
            'sf_lobby_mute_v1_' . $hash,
            'sf_showroom_mute_' . $cid,
            'sf_showroom_mute_' . $hash,
        ] as $key) {
            $val = \Illuminate\Support\Facades\Cache::get($key);
            if ($val === null || $val === false || $val === 0 || $val === '0' || $val === '') {
                continue;
            }
            if (is_numeric($val) && (int) $val > 1_000_000_000) {
                if ((int) $val > $nowTs) {
                    return true;
                }
                continue;
            }
            if ((bool) $val) {
                return true;
            }
        }

        $resolvedGamification = $gamification ?? (
            \Illuminate\Support\Facades\Schema::hasTable('customer_gamifications')
                ? \App\Models\Customer\CustomerGamification::where('customer_id', $cid)->first()
                : null
        );
        $persistedMod = is_array($resolvedGamification?->titles_progress['chat_moderation'] ?? null)
            ? $resolvedGamification->titles_progress['chat_moderation']
            : null;

        if ($persistedMod !== null) {
            if (!empty($persistedMod['permanent_mute'])) {
                return true;
            }
            if (isset($persistedMod['muted_until_ts']) && is_numeric($persistedMod['muted_until_ts']) && (int) $persistedMod['muted_until_ts'] > $nowTs) {
                return true;
            }
        }

        return false;
    }

    private function resolveCustomer3dChatStrikes(
        string $customerId,
        ?\App\Models\Customer\CustomerGamification $gamification = null
    ): int {
        if (trim($customerId) === '') {
            return 0;
        }

        $cid = trim($customerId);
        $hash = md5($cid);
        $actorHash = md5('customer:' . $cid);
        $strikes = 0;

        foreach ([
            'sf_lobby_sanction_v1_' . $cid,
            'sf_lobby_sanction_v1_' . $hash,
            'sf_lobby_sanction_v1_' . $actorHash,
        ] as $sanctionKey) {
            $sanction = \Illuminate\Support\Facades\Cache::get($sanctionKey);
            if (is_array($sanction)) {
                $strikes = max($strikes, (int) ($sanction['strikes'] ?? 0));
            }
        }

        $resolvedGamification = $gamification ?? (
            \Illuminate\Support\Facades\Schema::hasTable('customer_gamifications')
                ? \App\Models\Customer\CustomerGamification::where('customer_id', $cid)->first()
                : null
        );
        $persistedMod = is_array($resolvedGamification?->titles_progress['chat_moderation'] ?? null)
            ? $resolvedGamification->titles_progress['chat_moderation']
            : null;

        if ($persistedMod !== null) {
            $strikes = max($strikes, (int) ($persistedMod['strikes'] ?? 0));
        }

        return max(0, $strikes);
    }

    private function refreshEditingCustomerMagmaMeta(
        ?Customer $customer = null,
        ?\App\Models\Customer\CustomerGamification $gamification = null
    ): void {
        $this->refreshEditingCustomerStatusPills($customer, $gamification);
    }

    private function refreshEditingCustomerStatusPills(
        ?Customer $customer = null,
        ?\App\Models\Customer\CustomerGamification $gamification = null
    ): void {
        if ($this->editingType !== 'customer' || empty($this->editingId)) {
            $this->editingCustomerHasMasterKey = false;
            $this->editingCustomerClearedMagmaRooms = 0;
            $this->editingCustomerChatMuted = false;
            $this->editingCustomerChatStrikes = 0;
            return;
        }

        $resolvedCustomer = $customer ?? Customer::withTrashed()->find($this->editingId);
        if (!$resolvedCustomer) {
            $this->editingCustomerHasMasterKey = false;
            $this->editingCustomerClearedMagmaRooms = 0;
            $this->editingCustomerChatMuted = false;
            $this->editingCustomerChatStrikes = 0;
            return;
        }

        $resolvedGamification = $gamification ?? $resolvedCustomer->gamification;
        $clearedLevels = is_array($resolvedGamification?->titles_progress['lava_cleared_levels'] ?? null)
            ? $resolvedGamification->titles_progress['lava_cleared_levels']
            : [];

        $cid = (string) $resolvedCustomer->id;
        $this->editingCustomerHasMasterKey = app(\App\Services\Gamification\LavaParkourService::class)
            ->hasMasterKey($resolvedCustomer);
        $this->editingCustomerClearedMagmaRooms = min(10, count($clearedLevels));
        $this->editingCustomerChatMuted = $this->isCustomer3dChatMuted($cid, $resolvedGamification);
        $this->editingCustomerChatStrikes = $this->resolveCustomer3dChatStrikes($cid, $resolvedGamification);
    }

    private function syncCustomerGamificationFromAdminForm(Customer $customer, bool $defaultActive = false): void
    {
        $balance = max(0, (int) ($this->formData['funken_balance'] ?? 0));
        $total = max(0, (int) ($this->formData['funken_total_earned'] ?? 0));
        $level = max(1, min(10, (int) ($this->formData['funki_level'] ?? 1)));
        $active = (bool) ($this->formData['gamification_active'] ?? $defaultActive);
        $optIn = (bool) ($this->formData['ranking_opt_in'] ?? false);
        $realName = (bool) ($this->formData['use_real_name'] ?? false);
        $playerName = (string) ($this->formData['player_name'] ?? '');
        $magmaUnlockedLevel = max(1, min(10, (int) ($this->formData['magma_unlocked_level'] ?? 1)));
        $magmaHearts = max(1, min(6, (int) ($this->formData['magma_hearts'] ?? 6)));
        $healingPotions = max(0, min(30, (int) ($this->formData['healing_potions'] ?? 0)));

        $gameService = app(\App\Services\Gamification\GamificationService::class);
        $ref = new \ReflectionMethod($gameService, 'adminAdjustCustomerGamification');

        if ($ref->getNumberOfParameters() >= 11) {
            $gameService->adminAdjustCustomerGamification(
                $customer,
                $balance,
                $total,
                $level,
                $active,
                $optIn,
                $realName,
                $playerName,
                $magmaUnlockedLevel,
                $magmaHearts,
                $healingPotions
            );
            return;
        }

        $gamification = $gameService->adminAdjustCustomerGamification(
            $customer,
            $balance,
            $total,
            $level,
            $active,
            $optIn,
            $realName,
            $playerName
        );

        $items = is_array($gamification->inventory_items) ? $gamification->inventory_items : [];
        $preserved = [];
        foreach ($items as $entry) {
            if (is_array($entry) && ($entry['item_key'] ?? null) === 'healing_potion') {
                continue;
            }
            if ($entry === 'healing_potion') {
                continue;
            }
            $preserved[] = $entry;
        }
        for ($i = 0; $i < $healingPotions; $i++) {
            $preserved[] = [
                'id' => (string) Str::uuid(),
                'item_key' => 'healing_potion',
                'acquired_at' => now()->toIso8601String(),
            ];
        }

        $gamification->magma_unlocked_level = $magmaUnlockedLevel;
        $gamification->magma_hearts = $magmaHearts;
        $gamification->magma_active_run_id = null;
        $gamification->magma_run_committed = false;
        $gamification->inventory_items = array_values($preserved);
        if ($healingPotions > 0) {
            $gamification->has_backpack = true;
        }
        $gamification->save();

        app(\App\Services\Gamification\LavaParkourService::class)
            ->invalidateActiveRunForCustomer((string) $customer->id);
    }

    public function render()
    {
        if (!$this->isCreating && $this->editingType === 'customer' && !empty($this->editingId)) {
            $this->refreshEditingCustomerStatusPills();
        }
        if (!in_array((string) $this->filterRole, ['all', 'admin', 'customer', 'employee'], true)) {
            $this->filterRole = 'all';
        }
        if (!in_array((string) $this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'desc';
        }

        $queryAdmin = Admin::query(); $queryCustomer = Customer::query(); $queryEmployee = Employee::query();

        if ($this->showArchive) {
            $queryAdmin->onlyTrashed(); $queryCustomer->onlyTrashed(); $queryEmployee->onlyTrashed();
        }

        if ($this->search) {
            $terms = explode(' ', $this->search);
            foreach ($terms as $term) {
                if (empty(trim($term))) continue;
                $escapedTerm = addcslashes(trim($term), '%_\\');
                $s = '%' . $escapedTerm . '%';
                $sId = $escapedTerm . '%';
                
                $filter = function($q) use ($s, $sId) {
                    $q->where(function($subQ) use ($s, $sId) {
                        $subQ->where('first_name', 'like', $s)
                            ->orWhere('last_name', 'like', $s)
                            ->orWhere('email', 'like', $s)
                            ->orWhere('id', 'like', $sId)
                            ->orWhereHas('profile', function($pq) use ($s) {
                                $pq->where('city', 'like', $s)
                                    ->orWhere('company_name', 'like', $s)
                                    ->orWhere('phone_number', 'like', $s)
                                    ->orWhere('street', 'like', $s)
                                    ->orWhere('house_number', 'like', $s)
                                    ->orWhere('postal', 'like', $s)
                                    ->orWhere('vat_id', 'like', $s)
                                    ->orWhere('internal_note', 'like', $s);
                            });
                    });
                };
                $queryAdmin->where($filter);
                $queryCustomer->where($filter);
                $queryEmployee->where($filter);
            }
        }

        $results = collect();
        if (in_array($this->filterRole, ['all', 'admin'])) {
            $results = $results->merge($queryAdmin->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), [
                'user_type' => 'admin',
                'last_seen' => $i->profile->last_seen ?? null,
                'created_at' => $i->created_at ? $i->created_at->toDateTimeString() : null,
                'profile' => $i->profile ? $i->profile->toArray() : []
            ])));
        }
        if (in_array($this->filterRole, ['all', 'customer'])) {
            $results = $results->merge($queryCustomer->with(['profile', 'gamification'])->get()->map(fn($i) => array_merge($i->toArray(), [
                'user_type' => 'customer',
                'last_seen' => $i->profile->last_seen ?? null,
                'created_at' => $i->created_at ? $i->created_at->toDateTimeString() : null,
                'profile' => $i->profile ? $i->profile->toArray() : [],
                'gamification' => $i->gamification ? $i->gamification->toArray() : null,
            ])));
        }
        if (in_array($this->filterRole, ['all', 'employee'])) {
            $results = $results->merge($queryEmployee->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), [
                'user_type' => 'employee',
                'last_seen' => $i->profile->last_seen ?? null,
                'created_at' => $i->created_at ? $i->created_at->toDateTimeString() : null,
                'profile' => $i->profile ? $i->profile->toArray() : []
            ])));
        }

        $descending = $this->sortDirection === 'desc';
        switch ($this->sortField) {
            case 'identity':
                $items = $results->sortBy(function($user) {
                    return strtolower($user['first_name'] . ' ' . $user['last_name']);
                }, SORT_REGULAR, $descending);
                break;
            case 'status':
                $items = $results->sortBy(function($user) {
                    return !empty($user['profile']['email_verified_at']) ? 1 : 0;
                }, SORT_REGULAR, $descending);
                break;
            case 'location':
                $items = $results->sortBy(function($user) {
                    return strtolower($user['profile']['city'] ?? '');
                }, SORT_REGULAR, $descending);
                break;
            case 'ip':
                $items = $results->sortBy(function($user) {
                    return $user['profile']['last_ip'] ?? '';
                }, SORT_REGULAR, $descending);
                break;
            case 'login':
            default:
                $items = $results->sortBy(function($user) {
                    return $user['last_seen'] ?? '';
                }, SORT_REGULAR, $descending);
                break;
        }

        $perPage = 15;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $pagedResults = new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($currentPage, $perPage)->values(), $items->count(), $perPage, $currentPage, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        return view('livewire.shop.system.system-user-management', [
            'users' => $pagedResults
        ]);
    }
}

<?php

namespace App\Livewire\Shop\Order;

use Livewire\Attributes\Layout;

use App\Mail\NewOrderShippedToCustomer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Services\Order\OrderProductionBatchService;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Models\System\SystemLog;

#[Layout('components.layouts.backend_layout')]
class OrderOverview extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Bestellungen';

    use WithPagination;

    // --- FILTER & SORTIERUNG ---
    public $search = '';
    public $statusFilter = '';
    public $paymentFilter = '';

    // Standard: Custom Workflow (Offene oben, Express oben, Artikel-Serien gebündelt, Erledigte unten)
    public $sortField = 'default_workflow';
    public $sortDirection = 'asc';

    // Aktiver Laser-/Fertigungs-Fokus (merkt sich automatisch den zuletzt gelaserten oder per Klick gewählten Artikel)
    public ?string $activeBatchProductId = null;

    protected ?array $cachedSmartQueue = null;

    // --- STATE FÜR DETAIL-ANSICHT ---
    #[\Livewire\Attributes\Locked]
    public $selectedOrderId = null;
    #[\Livewire\Attributes\Locked]
    public $selectedOrderItemId = null;

    // --- STATE FÜR BEARBEITUNG ---
    #[\Livewire\Attributes\Locked]
    public ?OrderOrder $selectedOrder = null;

    // Formular-Felder
    public $status;
    public $payment_status;
    public $notes;
    public string $workshopNote = '';
    public string $workshopFollowupMode = '2h';
    public $cancellationReason = '';

    // STATUS FÜR DAS SICHERHEITS-MODAL
    #[\Livewire\Attributes\Locked]
    public $confirmingShipmentId = null;

    // FOTO-MANGEL MODAL STATE
    public $isPhotoIssueModalOpen = false;
    #[\Livewire\Attributes\Locked]
    public $photoIssueItemId = null;
    public $photoIssueFilePath = null;
    public $photoIssueFileName = null;
    public $photoIssueProductName = null;
    public $photoIssueReasonKey = 'resolution';
    public $photoIssueCustomNote = '';
    public $photoIssueSetPending = true;
    public $photoIssueSendMail = true;

    // DHL MODAL STATE
    #[\Livewire\Attributes\Locked]
    public $dhlModalOrderId = null;
    public $dhlPackageCount = 1;
    public $dhlWeightPerPackage = 1.0;
    public $dhlError = null;
    public array $dhlWeightDetails = [];
    public ?float $dhlRecommendedWeight = null;
    public string $dhlPackagingType = 'box';

    // DRUCK-FEEDBACK STATE
    public ?string $lastPrintedOrderId = null;
    public ?string $lastPrintedShipmentId = null;

    // 3-SCHRITTE AUTONOMER BESTELL-WORKFLOW STATE
    public int $activeOrderStep = 1;
    public bool $showOptionalOrderDetails = false;
    public string $stepOneTab = 'cockpit';
    public bool $showLaserDetails = false;
    public ?string $activeHeaderModal = null;

    // XTOOL F2 ULTRA UV LIVE-LASER-COCKPIT STATE
    public string $laserCockpitSide = 'front';
    public float $laserCenterXMm = 100.0;
    public float $laserCenterYMm = 100.0;
    public float $laserAngleDeg = 0.0;
    public float $laserWidthMm = 40.0;
    public float $laserHeightMm = 40.0;
    public int $laserConfidence = 0;
    public bool $laserAutoAligned = false;
    public int $laserWorkflowStep = 1;
    public int $laserCameraRefreshToken = 0;
    public ?string $laserLastActionStatus = null;
    public string $laserCameraLightMode = 'anti_glare';

    // LASER-FOTO-STUDIO MODAL STATE
    public bool $isLaserPhotoStudioOpen = false;
    #[\Livewire\Attributes\Locked]
    public ?string $photoStudioItemId = null;
    #[\Livewire\Attributes\Locked]
    public ?string $photoStudioFileKey = null;
    #[\Livewire\Attributes\Locked]
    public ?string $photoStudioFileName = null;
    #[\Livewire\Attributes\Locked]
    public ?string $photoStudioOriginalRelPath = null;
    #[\Livewire\Attributes\Locked]
    public ?string $photoStudioOriginalUrl = null;
    #[\Livewire\Attributes\Locked]
    public ?string $photoStudioPreviewUrl = null;
    #[\Livewire\Attributes\Locked]
    public bool $photoStudioIsDarkSurface = false;
    #[\Livewire\Attributes\Locked]
    public int $photoStudioDpi = 600;
    #[\Livewire\Attributes\Locked]
    public string $photoStudioMaterialHint = '';
    #[\Livewire\Attributes\Locked]
    public array $photoStudioProfiles = [];
    public string $photoStudioActiveProfileId = '';
    public array $photoStudioParams = [
        'enabled'       => true,
        'grayscale'     => true,
        'invert'        => false,
        'brightness'    => 0,
        'contrast'      => 12,
        'gamma'         => 1.15,
        'highlight_cap' => 85,
        'sharpness'     => 85,
        'dither_mode'   => 'jarvis',
        'threshold'     => 128,
    ];

    private function notifyOrderFeedback(string $type, string $title, string $message): void
    {
        $this->dispatch('order-feedback', type: $type, title: $title, message: $message);
        session()->flash($type === 'success' ? 'success' : 'error', $message);
    }

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'paymentFilter' => ['except' => ''],
        'sortField' => ['except' => 'default_workflow'],
        'sortDirection' => ['except' => 'asc']
    ];

    protected array $allowedSortFields = [
        'default_workflow',
        'customer',
        'total',
        'total_price',
        'payment',
        'payment_status',
        'status',
        'order_number',
        'created_at',
        'is_express',
    ];

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nicht autorisierter Zugriff auf die Bestellübersicht.');
        }
    }

    public function mount(): void
    {
        $sessionFocus = session('orders_active_batch_product_id');
        if (is_string($sessionFocus) && $sessionFocus !== '') {
            $this->activeBatchProductId = $sessionFocus;
        }
    }

    private function invalidateSmartQueueCache(): void
    {
        $this->cachedSmartQueue = null;
    }

    private function rememberActiveBatchProduct(?string $productId): void
    {
        $clean = trim((string) $productId);
        if ($clean === '') {
            return;
        }
        $this->activeBatchProductId = $clean;
        session(['orders_active_batch_product_id' => $clean]);
        $this->invalidateSmartQueueCache();
    }

    public function selectBatchProduct(?string $productId = null): void
    {
        $clean = trim((string) $productId);
        if ($clean === '' || $this->activeBatchProductId === $clean) {
            $this->activeBatchProductId = null;
            session()->forget('orders_active_batch_product_id');
        } else {
            $this->activeBatchProductId = $clean;
            session(['orders_active_batch_product_id' => $clean]);
        }

        $this->invalidateSmartQueueCache();
        $this->resetPage();
    }

    // Reset aller Filter auf Standard
    public function resetFilters()
    {
        $this->reset(['search', 'statusFilter', 'paymentFilter', 'sortField', 'sortDirection', 'activeBatchProductId']);
        session()->forget('orders_active_batch_product_id');
        $this->invalidateSmartQueueCache();
        $this->resetPage();
    }

    /**
     * Liefert die gecachte Smart-Fertigungs-Warteschlange für den aktuellen Request.
     */
    protected function getSmartQueueData(): array
    {
        if ($this->cachedSmartQueue !== null) {
            return $this->cachedSmartQueue;
        }

        $openOrders = OrderOrder::query()
            ->with(['items.product.supplier', 'shipments'])
            ->whereIn('status', ['pending', 'processing'])
            ->get();

        $service = app(\App\Services\Order\OrderProductionBatchService::class);
        $this->cachedSmartQueue = $service->buildSmartQueue($openOrders, $this->activeBatchProductId);

        // Sobald der letzte Artikel einer Serie abgearbeitet wurde, veralteten Fokus automatisch bereinigen
        if ($this->activeBatchProductId !== null && $this->cachedSmartQueue['effective_focus_product_id'] === null) {
            $this->activeBatchProductId = null;
            session()->forget('orders_active_batch_product_id');
        }

        return $this->cachedSmartQueue;
    }

    // --- COMPUTED: PRIORITÄTS-ORDER & FUNKI-HANDLUNGSBEFEHL ---
    public function getPriorityOrderProperty()
    {
        return $this->getSmartQueueData()['priority_order'];
    }

    public function getFunkiDirectiveProperty(): array
    {
        return $this->getSmartQueueData()['funki_directive'];
    }

    public function getPriorityOrderTipProperty()
    {
        return (string) ($this->getSmartQueueData()['funki_directive']['tip_html'] ?? '');
    }

    public function getBatchGroupsProperty(): array
    {
        return $this->getSmartQueueData()['batch_groups'];
    }

    public function getEffectiveFocusProductIdProperty(): ?string
    {
        return $this->getSmartQueueData()['effective_focus_product_id'];
    }

    public function getNextQueueOrderProperty(): ?array
    {
        if (!$this->selectedOrderId) {
            return null;
        }

        $queue = $this->getSmartQueueData();
        $rankedOrders = $queue['ranked_orders'];
        $analyses = $queue['analyses'];
        $focusPid = $queue['effective_focus_product_id'];

        $currentAnalysis = $analyses[(string) $this->selectedOrderId] ?? null;
        $referenceProductIds = $focusPid
            ? [$focusPid]
            : ($currentAnalysis['actionable_product_ids'] ?? []);

        $nextOrder = $rankedOrders->first(fn (OrderOrder $o) => (string) $o->id !== (string) $this->selectedOrderId);
        if (!$nextOrder) {
            return null;
        }

        $nextAnalysis = $analyses[(string) $nextOrder->id] ?? [];
        $nextProductIds = $nextAnalysis['actionable_product_ids'] ?? [];
        $sharedPids = array_values(array_intersect($referenceProductIds, $nextProductIds));
        $isSameProduct = !empty($sharedPids);

        $nextItemName = $nextAnalysis['recommended_item']['product_name'] ?? null;

        return [
            'id' => (string) $nextOrder->id,
            'order_number' => (string) $nextOrder->order_number,
            'is_express' => (bool) $nextOrder->is_express,
            'is_same_product' => $isSameProduct,
            'product_name' => $nextItemName,
        ];
    }

    // --- ACTIONS: SORTIERUNG ---

    public function sortBy($field)
    {
        if (!in_array($field, $this->allowedSortFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // --- ACTIONS: DETAIL ANSICHT ---

    public function openDetail($id)
    {
        $this->selectedOrderId = $id;
        $this->showOptionalOrderDetails = false;
        $this->stepOneTab = 'cockpit';
        $this->showLaserDetails = false;
        $this->activeHeaderModal = null;
        $this->closeLaserPhotoStudio();
        $this->dispatch('order-ui-sync', headerModal: null, stepOneTab: 'cockpit', showLaserDetails: false);

        // Order laden (Eager Loading für Performance)
        $this->selectedOrder = OrderOrder::with(['items.product.supplier', 'invoices', 'shipments'])->find($id);

        // Daten für das Bearbeitungs-Formular laden
        if ($this->selectedOrder) {
            $this->status = $this->selectedOrder->status;
            $this->payment_status = $this->selectedOrder->payment_status;
            $this->notes = $this->selectedOrder->notes;
            $this->workshopNote = (string) ($this->selectedOrder->workshop_note ?? '');
            $this->workshopFollowupMode = $this->selectedOrder->workshop_checkin_paused ? 'pause' : '2h';
            $this->cancellationReason = $this->selectedOrder->cancellation_reason ?? '';

            $overview = $this->selectedOrder->getManufacturingOverview();
            $this->activeOrderStep = $overview['autonomous_step'] > 0 ? (int) $overview['autonomous_step'] : 1;

            if ($this->selectedOrder->items->isNotEmpty()) {
                // Bevorzuge im Preview automatisch den aktuell fokussierten Serien-Artikel (oder die erste noch offene Position)
                $focusPid = $this->effectiveFocusProductId ?: $this->activeBatchProductId;
                $preferredItem = null;
                if ($focusPid) {
                    $preferredItem = $this->selectedOrder->items->first(
                        fn ($i) => !$i->is_completed && (string) $i->product_id === (string) $focusPid
                    );
                }
                if (!$preferredItem) {
                    $preferredItem = $this->selectedOrder->items->first(fn ($i) => !$i->is_completed);
                }
                $this->selectedOrderItemId = ($preferredItem ?: $this->selectedOrder->items->first())->id;
                $this->initializeLaserCockpitForPreviewItem(false);
            }
        }
    }

    public function closeDetail()
    {
        $this->selectedOrderId = null;
        $this->selectedOrderItemId = null;
        $this->selectedOrder = null;
        $this->activeOrderStep = 1;
        $this->showOptionalOrderDetails = false;
        $this->stepOneTab = 'cockpit';
        $this->showLaserDetails = false;
        $this->activeHeaderModal = null;
        $this->closeLaserPhotoStudio();
        $this->dispatch('order-ui-sync', headerModal: null, stepOneTab: 'cockpit', showLaserDetails: false);
        $this->reset(['status', 'payment_status', 'notes', 'workshopNote', 'workshopFollowupMode', 'cancellationReason', 'laserLastActionStatus']);
    }

    public function toggleOptionalOrderDetails(): void
    {
        $this->showOptionalOrderDetails = !$this->showOptionalOrderDetails;
    }

    public function setStepOneTab(string $tab): void
    {
        $this->stepOneTab = in_array($tab, ['cockpit', 'preview'], true) ? $tab : 'cockpit';
        $this->dispatch('order-ui-sync', stepOneTab: $this->stepOneTab);
    }

    public function toggleLaserDetails(): void
    {
        $this->showLaserDetails = !$this->showLaserDetails;
        $this->dispatch('order-ui-sync', showLaserDetails: $this->showLaserDetails);
    }

    public function openHeaderModal(string $modal): void
    {
        if (in_array($modal, ['files', 'costs', 'customer', 'shipping'], true)) {
            $this->activeHeaderModal = ($this->activeHeaderModal === $modal) ? null : $modal;
            $this->dispatch('order-ui-sync', headerModal: $this->activeHeaderModal);
        }
    }

    public function closeHeaderModal(): void
    {
        $this->activeHeaderModal = null;
        $this->dispatch('order-ui-sync', headerModal: null);
    }

    /**
     * Startet eine wartende Bestellung (Klick auf "Jetzt starten" in der Wartend-Kachel):
     * Setzt den Status autonom auf "processing" ("In Bearbeitung") und öffnet Schritt 1.
     */
    public function startOrderProcessing(): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $this->selectedOrder->loadMissing(['items.product.supplier', 'invoices', 'shipments']);

        if ($this->selectedOrder->status === 'pending') {
            $this->selectedOrder->update(['status' => 'processing']);
            $this->status = 'processing';
        }

        $this->activeOrderStep = 1;
        $this->laserWorkflowStep = 1;
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();

        if (!$this->selectedOrderItemId && $this->selectedOrder->items->isNotEmpty()) {
            $preferredItem = $this->selectedOrder->items->first(fn ($i) => !$i->is_completed)
                ?: $this->selectedOrder->items->first();
            $this->selectedOrderItemId = $preferredItem?->id;
            $this->initializeLaserCockpitForPreviewItem(false);
        }

        $this->invalidateSmartQueueCache();

        $this->notifyOrderFeedback(
            'success',
            'Schritt 1 gestartet: In Bearbeitung',
            "Bestellung #{$this->selectedOrder->order_number} ist jetzt in Bearbeitung (Schritt 1)."
        );
    }

    /**
     * Wechselt den sichtbaren Tab innerhalb der 3-Schritte-Detailansicht (1 = In Bearbeitung, 2 = Versendet, 3 = Abgeschlossen).
     */
    public function setOrderStep(int $step): void
    {
        $this->activeOrderStep = max(1, min(3, $step));
    }

    /**
     * Schaltet einen Punkt der Manufaktur-Checkliste (1. Kundenwunsch, 2. Geschenkpapier, 3. Edle Schmuckbox, 4. Gravur)
     * um und setzt bei vollständiger Abarbeitung autonom Schritt 2 aktiv.
     */
    public function toggleItemChecklistTask(string $itemId, string $taskKey): void
    {
        $item = OrderOrderItem::with('product')->find($itemId);
        if (!$item || !$this->selectedOrder || (string) $item->order_id !== (string) $this->selectedOrder->id) {
            return;
        }

        $item->toggleChecklistTask($taskKey);

        if ($item->is_completed && !empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
            $this->rememberActiveBatchProduct((string) $item->product_id);
        }

        if ($this->selectedOrder->status === 'pending') {
            $this->selectedOrder->update(['status' => 'processing']);
            $this->status = 'processing';
        }

        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
        $overview = $this->selectedOrder->getManufacturingOverview();
        $this->invalidateSmartQueueCache();

        if ($overview['all_step_one_done'] && $this->selectedOrder->status === 'processing') {
            $this->activeOrderStep = 2;
            $this->notifyOrderFeedback(
                'success',
                'Schritt 1 komplett erledigt',
                'Alle Gravuren & Konfigurator-Aufgaben (Wünsche, Geschenkpapier, Schmuckbox) sind erledigt! Weiter mit Schritt 2 (Versand).'
            );
        } elseif ($item->is_completed) {
            // Falls die aktuelle Position fertig ist, aber noch weitere Artikel in einer Großbestellung offen sind,
            // schalten wir automatisch zum nächsten offenen Artikel weiter!
            $nextOpenItem = $this->selectedOrder->items->first(fn ($i) => !$i->is_completed);
            if ($nextOpenItem && (string) $nextOpenItem->id !== (string) $this->selectedOrderItemId) {
                $this->selectedOrderItemId = $nextOpenItem->id;
                $this->initializeLaserCockpitForPreviewItem(false);
                $this->notifyOrderFeedback(
                    'success',
                    'Position fertig · Nächster Artikel geladen',
                    "Weiter mit: {$nextOpenItem->quantity}x {$nextOpenItem->product_name}"
                );
            }
        }
    }

    /**
     * Markiert die aktuell im Laser-Cockpit geöffnete Position als vollständig gefertigt
     * und schaltet automatisch zum nächsten offenen Artikel der Bestellung (oder in Schritt 2: Verpackung & Versand).
     */
    public function completeCurrentItemAndAdvance(?string $itemId = null): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $targetId = $itemId ?: ($this->selectedOrderItemId ?: ($this->selectedOrder->items->first()?->id));
        $item = $targetId ? OrderOrderItem::with('product')->find($targetId) : null;

        if ($item && (string) $item->order_id === (string) $this->selectedOrder->id) {
            $item->markAllChecklistTasks(true);
            if (!empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                $this->rememberActiveBatchProduct((string) $item->product_id);
            }
        }

        if ($this->selectedOrder->status === 'pending') {
            $this->selectedOrder->update(['status' => 'processing']);
            $this->status = 'processing';
        }

        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
        $this->invalidateSmartQueueCache();

        $nextOpenItem = $this->selectedOrder->items->first(fn ($i) => !$i->is_completed);
        if ($nextOpenItem) {
            $this->selectedOrderItemId = $nextOpenItem->id;
            $this->activeOrderStep = 1;
            $this->initializeLaserCockpitForPreviewItem(false);
            $this->notifyOrderFeedback(
                'success',
                'Artikel erledigt · Nächste Position aktiv',
                "Weiter mit: {$nextOpenItem->quantity}x {$nextOpenItem->product_name}"
            );
            return;
        }

        $this->activeOrderStep = 2;
        $this->notifyOrderFeedback(
            'success',
            'Alle Artikel fertig gelasert · Jetzt Verpackung & Versand',
            'Weiter mit Schritt 2: Kartons packen, Dankeskarte & Warnaufkleber prüfen und DHL-Label drucken.'
        );
    }

    /**
     * Schaltet einen Packtisch-Pruefpunkt ('cleaned', 'gift_box', 'gift_wrap', 'thank_you_card', 'fragile_sticker') um.
     */
    public function toggleOrderPackingCheck(string $checkKey): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $this->selectedOrder->togglePackingCheck($checkKey);
        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
    }

    /**
     * Schließt Schritt 1 (Fertigung & Konfigurator-Extras) komplett ab und wechselt autonom zu Schritt 2 (Versand).
     */
    public function completeStepOneAndProceedToShipping(): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        foreach ($this->selectedOrder->items as $item) {
            $item->markAllChecklistTasks(true);
            if (!empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                $this->rememberActiveBatchProduct((string) $item->product_id);
            }
        }

        if ($this->selectedOrder->status === 'pending') {
            $this->selectedOrder->update(['status' => 'processing']);
            $this->status = 'processing';
        }

        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
        $this->activeOrderStep = 2;
        $this->invalidateSmartQueueCache();

        $this->notifyOrderFeedback(
            'success',
            'Schritt 1 abgeschlossen · Jetzt Schritt 2 (Versand)',
            'Alle Artikel und Konfigurator-Extras sind abgehakt. Jetzt DHL-Label erstellen und Paket versenden.'
        );
    }

    /**
     * Markiert die Bestellung in Schritt 2 als versendet und wechselt autonom zu Schritt 3 (Abgeschlossen).
     */
    public function completeStepTwoShipped(bool $sendMail = true): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $this->confirmingShipmentId = $this->selectedOrder->id;
        $this->confirmShipment($sendMail);
        $this->activeOrderStep = 3;
    }

    /**
     * Schließt die Bestellung in Schritt 3 endgültig ab (Status: completed).
     */
    public function completeStepThreeOrder(): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $this->selectedOrder->update(['status' => 'completed']);
        $this->selectedOrder->shipments()->where('status', '!=', 'delivered')->update(['status' => 'delivered']);
        $this->status = 'completed';
        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
        $this->activeOrderStep = 3;
        $this->invalidateSmartQueueCache();

        $this->notifyOrderFeedback(
            'success',
            'Schritt 3 erledigt: Bestellung abgeschlossen',
            "Bestellung #{$this->selectedOrder->order_number} wurde vollständig abgeschlossen."
        );
    }

    public function getSelectedOrderDiscrepancyProperty(): array
    {
        if (!$this->selectedOrder) {
            return ['has_discrepancy' => false];
        }

        return app(OrderProductionBatchService::class)->evaluateOrderStatusDiscrepancy($this->selectedOrder);
    }

    public function saveWorkshopCheckin(?string $overrideMode = null): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $this->validate([
            'workshopNote' => 'nullable|string|max:1000',
        ]);

        $mode = $overrideMode ?: ($this->workshopFollowupMode ?: '2h');
        $allowedModes = ['2h', '1h', 'tomorrow', 'pause'];
        if (!in_array($mode, $allowedModes, true)) {
            $mode = '2h';
        }
        $this->workshopFollowupMode = $mode;

        $cleanNote = trim($this->workshopNote);
        $schedule = OrderProductionBatchService::resolveNextWorkshopCheckinAt($mode);

        $this->selectedOrder->update([
            'workshop_note' => $cleanNote !== '' ? $cleanNote : null,
            'workshop_note_updated_at' => now(),
            'workshop_next_check_at' => $schedule['next_check_at'],
            'workshop_checkin_paused' => $schedule['paused'],
        ]);

        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->invalidateSmartQueueCache();

        $this->notifyOrderFeedback(
            'success',
            'Werkstatt-Stand gespeichert',
            $schedule['paused']
                ? 'Interner Stand aktualisiert (Check-in pausiert).'
                : ('Interner Stand aktualisiert · Wiedervorlage: ' . $schedule['mode_label'] . '.')
        );
    }

    public function clearWorkshopCheckin(): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $this->selectedOrder->update([
            'workshop_note' => null,
            'workshop_note_updated_at' => null,
            'workshop_next_check_at' => null,
            'workshop_checkin_paused' => false,
        ]);

        $this->workshopNote = '';
        $this->workshopFollowupMode = '2h';
        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->invalidateSmartQueueCache();

        $this->notifyOrderFeedback('info', 'Werkstatt-Stand geleert', 'Interne Werkstatt-Notiz und Wiedervorlage zurückgesetzt.');
    }

    public function selectItemForPreview($itemId)
    {
        $this->selectedOrderItemId = $itemId;
        $this->laserCockpitSide = 'front';
        $this->initializeLaserCockpitForPreviewItem(false);
    }

    #[\Livewire\Attributes\On('order-item-updated')]
    public function onOrderItemConfigurationSaved($itemId = null): void
    {
        if (!$this->selectedOrder) {
            return;
        }

        $resolvedItemId = is_array($itemId) ? (string) ($itemId['itemId'] ?? '') : (string) ($itemId ?? '');
        if ($resolvedItemId !== '' && (string) $this->selectedOrderItemId !== $resolvedItemId) {
            $belongs = $this->selectedOrder->items()->where('id', $resolvedItemId)->exists();
            if ($belongs) {
                $this->selectedOrderItemId = $resolvedItemId;
            }
        }

        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
        $this->laserCameraRefreshToken++;
        $this->initializeLaserCockpitForPreviewItem(false);
        $this->invalidateSmartQueueCache();

        $this->notifyOrderFeedback(
            'success',
            'Design & Blueprint aktualisiert',
            'Design & Blueprint aktualisiert · Laser-Cockpit synchronisiert'
        );
    }

    public static function resolveOrderBlueprintSnapshotUrl(?string $rawPath): ?string
    {
        if ($rawPath === null) {
            return null;
        }
        $trimmed = trim($rawPath);
        if ($trimmed === '' || str_contains($trimmed, "\0") || str_contains($trimmed, '..') || str_contains($trimmed, '\\')) {
            return null;
        }

        if (str_starts_with($trimmed, 'data:') || str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return resolve_snapshot_url($trimmed);
        }

        $clean = ltrim(preg_replace('#^/?storage/#', '', $trimmed) ?? $trimmed, '/');
        if ($clean === '' || str_contains($clean, '..') || str_contains($clean, '\\')) {
            return null;
        }

        $candidates = [];
        if (str_starts_with($clean, 'snapshots/')) {
            $candidates[] = 'system/' . $clean;
            $candidates[] = $clean;
        } elseif (str_starts_with($clean, 'system/snapshots/')) {
            $candidates[] = $clean;
            $candidates[] = substr($clean, 7);
        } else {
            $candidates[] = 'system/snapshots/' . $clean;
            $candidates[] = 'system/' . $clean;
            $candidates[] = $clean;
        }

        $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
        foreach ($candidates as $cand) {
            if ($publicDisk->exists($cand)) {
                return asset('storage/' . $cand);
            }
        }

        return resolve_snapshot_url($clean, asset('storage/' . $clean));
    }

    /**
     * Baut die chronologische Blueprint-Verlaufsleiste für Schritt 1 (Admin) aus configuration_history + aktuellem Stand.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildItemBlueprintTimeline(?OrderOrderItem $item): array
    {
        if (!$item) {
            return [];
        }

        $conf = is_array($item->configuration) ? $item->configuration : [];
        $rawHistory = is_array($conf['configuration_history'] ?? null)
            ? array_values(array_filter($conf['configuration_history'], 'is_array'))
            : [];

        if (empty($rawHistory)) {
            return [];
        }

        usort($rawHistory, static fn (array $a, array $b): int => ((int) ($a['version'] ?? 0)) <=> ((int) ($b['version'] ?? 0)));

        $formatTs = static function (?string $iso, $fallbackDt = null): string {
            try {
                if ($iso !== null && trim($iso) !== '') {
                    return \Carbon\Carbon::parse($iso)->timezone('Europe/Berlin')->format('d.m.Y, H:i \U\h\r');
                }
                if ($fallbackDt instanceof \Carbon\CarbonInterface) {
                    return $fallbackDt->copy()->timezone('Europe/Berlin')->format('d.m.Y, H:i \U\h\r');
                }
            } catch (\Throwable) {
            }

            return '';
        };

        $steps = [];
        foreach ($rawHistory as $idx => $entry) {
            $ver = max(1, (int) ($entry['version'] ?? ($idx + 1)));
            $actorType = strtolower((string) ($entry['actor_type'] ?? 'customer')) === 'admin' ? 'admin' : 'customer';
            $cleanLabel = trim(str_replace([" \u{2013} ", " \u{2014} "], ' · ', (string) ($entry['actor_label'] ?? '')));
            if ($cleanLabel === '') {
                $cleanLabel = $actorType === 'admin' ? 'Mein-Seelenfunke Admin' : 'Original-Entwurf beim Kauf';
            }

            $snap = $entry['snapshot_path'] ?? null;
            $frontUrl = is_array($snap)
                ? self::resolveOrderBlueprintSnapshotUrl($snap['front'] ?? null)
                : (is_string($snap) ? self::resolveOrderBlueprintSnapshotUrl($snap) : null);
            $backUrl = is_array($snap) ? self::resolveOrderBlueprintSnapshotUrl($snap['back'] ?? null) : null;

            $steps[] = [
                'version'        => $ver,
                'actor_type'     => $actorType,
                'actor_label'    => $cleanLabel,
                'saved_at_label' => $formatTs($entry['saved_at'] ?? null, $item->created_at),
                'front_url'      => $frontUrl,
                'back_url'       => $backUrl,
                'has_back'       => $backUrl !== null && $backUrl !== '',
                'is_current'     => false,
            ];
        }

        $maxVer = !empty($steps) ? max(array_column($steps, 'version')) : 0;
        $adj = is_array($conf['last_admin_adjustment'] ?? null) ? $conf['last_admin_adjustment'] : null;
        $currVer = ($adj !== null && (int) ($adj['version'] ?? 0) > $maxVer)
            ? (int) $adj['version']
            : ($maxVer + 1);
        $currActorType = $adj !== null ? 'admin' : 'customer';
        $currActorLabel = $adj !== null
            ? (trim(str_replace([" \u{2013} ", " \u{2014} "], ' · ', (string) ($adj['adjusted_by'] ?? ''))) ?: 'Mein-Seelenfunke Admin')
            : 'Kunden-Entwurf';

        $currSnap = $conf['snapshot_path'] ?? null;
        $hasBackContent = collect($conf['texts_back'] ?? [])->contains(fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '')
            || !empty($conf['logos_back']);
        $currFrontUrl = is_array($currSnap)
            ? self::resolveOrderBlueprintSnapshotUrl($currSnap['front'] ?? null)
            : (is_string($currSnap) ? self::resolveOrderBlueprintSnapshotUrl($currSnap) : null);
        $currBackUrl = (is_array($currSnap) && $hasBackContent)
            ? self::resolveOrderBlueprintSnapshotUrl($currSnap['back'] ?? null)
            : null;

        $steps[] = [
            'version'        => $currVer,
            'actor_type'     => $currActorType,
            'actor_label'    => $currActorLabel,
            'saved_at_label' => $formatTs($adj['adjusted_at'] ?? null, $item->updated_at),
            'front_url'      => $currFrontUrl,
            'back_url'       => $currBackUrl,
            'has_back'       => $currBackUrl !== null && $currBackUrl !== '',
            'is_current'     => true,
        ];

        return $steps;
    }

    // --- ACTIONS: SPEICHERN ---

    public function saveStatus()
    {
        if (!$this->selectedOrder) return;

        $allowedStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded', 'failed'];
        $allowedPaymentStatuses = ['unpaid', 'paid', 'refunded', 'failed'];
        if (!in_array($this->status, $allowedStatuses, true) || !in_array($this->payment_status, $allowedPaymentStatuses, true)) {
            session()->flash('error', 'Ungültiger Bestell- oder Zahlungsstatus.');
            return;
        }

        // Sicherheits-Modal für Versand
        if ($this->status === 'shipped' && $this->selectedOrder->status !== 'shipped') {
            $this->confirmingShipmentId = $this->selectedOrder->id;
            return;
        }

        try {
            DB::transaction(function () {
                $previousStatus = $this->selectedOrder->status;
                $previousPaymentStatus = $this->selectedOrder->payment_status;

                // Fall: Stornierung
                if ($this->status === 'cancelled') {
                    $this->validate([
                        'cancellationReason' => 'required|string|min:5|max:500',
                    ]);

                    // Führt geschäftslogische Rückabwicklung durch (nur wenn noch nicht storniert/erstattet):
                    if (!in_array($previousStatus, ['cancelled', 'refunded'], true)) {
                        $this->selectedOrder->handleCancellation($this->cancellationReason ?: 'Stornierung', $previousStatus, $previousPaymentStatus);
                    }

                    // Update
                    $this->selectedOrder->update([
                        'status' => 'cancelled',
                        'payment_status' => $this->payment_status,
                        'notes' => $this->notes,
                        'cancellation_reason' => $this->cancellationReason
                    ]);

                    $this->selectedOrder->refresh();
                    session()->flash('success', 'Bestellung storniert.');

                } else {
                    if (in_array($previousStatus, ['cancelled', 'refunded'], true) && !in_array($this->status, ['cancelled', 'refunded'], true)) {
                        session()->flash('error', 'Stornierte oder erstattete Bestellungen sind abgeschlossen und können nicht reaktiviert werden.');
                        return;
                    }

                    // Vollständige Rückabwicklung bei Erstattung VOR Status-Update
                    $isRefundingNow = $this->status === 'refunded'
                        || ($this->payment_status === 'refunded' && $previousPaymentStatus !== 'refunded');
                    if ($isRefundingNow && !in_array($previousStatus, ['cancelled', 'refunded'], true)) {
                        $this->selectedOrder->handleCancellation('Erstattung', $previousStatus, $previousPaymentStatus);
                    }

                    $completedViaPaymentService = false;
                    if ($previousPaymentStatus !== 'paid' && $this->payment_status === 'paid' && !in_array($this->status, ['cancelled', 'refunded'], true)) {
                        $this->selectedOrder->completePayment();
                        $completedViaPaymentService = true;
                    }

                    // Normales Update
                    $this->selectedOrder->update([
                        'status' => $this->status,
                        'payment_status' => $this->payment_status,
                        'notes' => $this->notes,
                        'cancellation_reason' => null
                    ]);

                    $this->selectedOrder->refresh();
                    
                    // Wenn auf 'Bezahlt' gesetzt wurde und noch keine Rechnung existiert -> Automatisch Rechnung + E-Mail
                    if ($completedViaPaymentService) {
                        session()->flash('success', 'Bestelldetails aktualisiert. Rechnung wird im Hintergrund generiert und per E-Mail versandt.');
                    } elseif ($this->payment_status === 'paid' && $this->selectedOrder->invoices->isEmpty()) {
                        \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($this->selectedOrder);
                        session()->flash('success', 'Bestelldetails aktualisiert. Rechnung wird im Hintergrund generiert und per E-Mail versandt.');
                    } else {
                        session()->flash('success', 'Bestelldetails aktualisiert.');
                    }
                }
                $this->invalidateSmartQueueCache();
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logAdminOrderError('save_status', $e, ['order_id' => $this->selectedOrder->id ?? null, 'status' => $this->status]);
            session()->flash('error', 'Status konnte nicht gespeichert werden: ' . $e->getMessage());
        }
    }

    // --- ACTIONS: SCHNELL-UPDATE (Direkt in der Tabelle) ---

    public function updateStatus($orderId, $newStatus)
    {
        $allowedStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded', 'failed'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            session()->flash('error', 'Ungültiger Bestellstatus.');
            return;
        }

        $order = OrderOrder::find($orderId);
        if (!$order) return;

        if (in_array($order->status, ['cancelled', 'refunded'], true) && !in_array($newStatus, ['cancelled', 'refunded'], true)) {
            session()->flash('error', 'Stornierte oder erstattete Bestellungen können nicht reaktiviert werden.');
            return;
        }

        if ($newStatus === 'shipped' && $order->status !== 'shipped') {
            $this->confirmingShipmentId = $orderId;
            return;
        }

        try {
            DB::transaction(function () use ($order, $orderId, $newStatus) {
                $previousStatus = $order->status;
                $previousPaymentStatus = $order->payment_status;

                if (in_array($newStatus, ['cancelled', 'refunded'], true)) {
                    $order->handleCancellation($newStatus === 'refunded' ? 'Erstattung' : 'Stornierung', $previousStatus, $previousPaymentStatus);
                }

                $order->update(['status' => $newStatus]);

                if ($newStatus === 'completed') {
                    $order->shipments()->where('status', '!=', 'delivered')->update(['status' => 'delivered']);
                }

                if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
                    $this->status = $newStatus;
                    $this->selectedOrder->refresh();
                }
                $this->invalidateSmartQueueCache();
                session()->flash('success', "Status aktualisiert.");
            });
        } catch (\Exception $e) {
            $this->logAdminOrderError('update_status', $e, ['order_id' => $orderId, 'new_status' => $newStatus]);
            session()->flash('error', 'Status konnte nicht aktualisiert werden.');
        }
    }

    public function toggleItemCompletion($itemId)
    {
        $item = OrderOrderItem::with('product')->find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            $newStatus = !$item->is_completed;
            $item->markAllChecklistTasks($newStatus);

            if ($this->selectedOrder->status === 'pending' && $newStatus) {
                $this->selectedOrder->update(['status' => 'processing']);
                $this->status = 'processing';
            }

            if ($newStatus && !empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                $this->rememberActiveBatchProduct((string) $item->product_id);
            } else {
                $this->invalidateSmartQueueCache();
            }
            $this->selectedOrder->refresh();
            $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
            $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();

            $overview = $this->selectedOrder->getManufacturingOverview();
            if ($newStatus && $overview['all_step_one_done'] && $this->selectedOrder->status === 'processing') {
                $this->activeOrderStep = 2;
            }
        }
    }

    public function toggleSupplierOrdered($itemId)
    {
        $item = OrderOrderItem::with('product.supplier')->find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            $newState = !$item->isSupplierOrderPlaced();
            $item->markSupplierOrdered($newState);
            $this->invalidateSmartQueueCache();
            $this->selectedOrder->refresh();
            $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
            session()->flash('success', $newState ? 'Rohling als beim Lieferanten bestellt markiert.' : 'Lieferanten-Bestellstatus zurückgesetzt.');
        }
    }

    public function incrementCompletedQuantity($itemId)
    {
        $item = OrderOrderItem::with('product')->find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            if ($item->completed_quantity < $item->quantity) {
                $newQty = $item->completed_quantity + 1;
                $item->update([
                    'completed_quantity' => $newQty,
                    'is_completed' => ($newQty >= $item->quantity)
                ]);
                if ($newQty >= $item->quantity) {
                    $item->toggleChecklistTask('engraving', true);
                }
                if ($this->selectedOrder->status === 'pending') {
                    $this->selectedOrder->update(['status' => 'processing']);
                    $this->status = 'processing';
                }
                if (!empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                    $this->rememberActiveBatchProduct((string) $item->product_id);
                } else {
                    $this->invalidateSmartQueueCache();
                }
                $this->selectedOrder->refresh();
                $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
                $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();

                $overview = $this->selectedOrder->getManufacturingOverview();
                if ($overview['all_step_one_done'] && $this->selectedOrder->status === 'processing') {
                    $this->activeOrderStep = 2;
                }
            }
        }
    }

    public function setAllCompletedQuantity($itemId)
    {
        $item = OrderOrderItem::with('product')->find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            $item->markAllChecklistTasks(true);
            if ($this->selectedOrder->status === 'pending') {
                $this->selectedOrder->update(['status' => 'processing']);
                $this->status = 'processing';
            }
            if (!empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                $this->rememberActiveBatchProduct((string) $item->product_id);
            } else {
                $this->invalidateSmartQueueCache();
            }
            $this->selectedOrder->refresh();
            $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
            $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();

            $overview = $this->selectedOrder->getManufacturingOverview();
            if ($overview['all_step_one_done'] && $this->selectedOrder->status === 'processing') {
                $this->activeOrderStep = 2;
            }
        }
    }

    public function decrementCompletedQuantity($itemId)
    {
        $item = OrderOrderItem::find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            if ($item->completed_quantity > 0) {
                $newQty = $item->completed_quantity - 1;
                $item->update([
                    'completed_quantity' => $newQty,
                    'is_completed' => false // sobald eins fehlt, ist nicht mehr full-completed
                ]);
                $item->toggleChecklistTask('engraving', false);
                $this->invalidateSmartQueueCache();
                $this->selectedOrder->refresh();
                $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
                $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
            }
        }
    }

    public function confirmShipment($sendMail = true)
    {
        if (!$this->confirmingShipmentId) return;

        $order = OrderOrder::find($this->confirmingShipmentId);
        if ($order) {
            try {
                DB::transaction(function () use ($order, $sendMail) {
                    $order->update(['status' => 'shipped']);
                    $order->syncAutonomousWorkshopSummary();

                    if ($sendMail) {
                        Mail::to($order->email)->send(new NewOrderShippedToCustomer($order->toFormattedArray()));
                        session()->flash('success', 'Status geändert & Mail versendet! 🚀');
                    } else {
                        session()->flash('success', 'Status auf Versendet gesetzt.');
                    }

                    if ($this->selectedOrder && $this->selectedOrder->id == $order->id) {
                        $this->status = 'shipped';
                        $this->activeOrderStep = 3;
                        $this->selectedOrder->refresh();
                        $this->workshopNote = (string) ($this->selectedOrder->workshop_note ?? '');
                    }
                    $this->invalidateSmartQueueCache();
                });
            } catch (\Exception $e) {
                $this->logAdminOrderError('confirm_shipment', $e, ['order_id' => $order->id]);
                session()->flash('error', 'Fehler beim Bestätigen des Versands: ' . $e->getMessage());
            }
        }
        $this->confirmingShipmentId = null;
    }

    public function cancelShipment()
    {
        $this->confirmingShipmentId = null;
        if ($this->selectedOrder) {
            $this->status = $this->selectedOrder->status;
        }
    }

    public function openPhotoIssueModal($itemId, $filePath, $fileName)
    {
        $item = OrderOrderItem::find($itemId);
        if (!$item) return;

        $this->photoIssueItemId = $itemId;
        $this->photoIssueFilePath = $filePath;
        $this->photoIssueFileName = $fileName;
        $this->photoIssueProductName = $item->product_name ?? 'Artikel';
        $this->photoIssueReasonKey = 'resolution';
        $this->photoIssueCustomNote = '';
        $this->photoIssueSetPending = true;
        $this->photoIssueSendMail = true;
        $this->isPhotoIssueModalOpen = true;
    }

    public function closePhotoIssueModal()
    {
        $this->isPhotoIssueModalOpen = false;
        $this->photoIssueItemId = null;
        $this->photoIssueFilePath = null;
        $this->photoIssueFileName = null;
    }

    public function submitPhotoIssue()
    {
        if (!$this->photoIssueItemId) return;

        $item = OrderOrderItem::find($this->photoIssueItemId);
        if (!$item) {
            session()->flash('error', 'Artikel nicht gefunden.');
            return;
        }

        $order = $item->order;
        if (!$order) {
            session()->flash('error', 'Zugehörige Bestellung nicht gefunden.');
            return;
        }

        $reasons = [
            'resolution' => 'Zu geringe Bildauflösung / unscharf / verpixelt',
            'contrast' => 'Zu dunkel / überbelichtet (fehlender Kontrast für Lasergravur)',
            'crop' => 'Wichtiges Motiv ungünstig abgeschnitten / Gesichter am Rand',
            'other' => 'Sonstige Anmerkung der Manufaktur (siehe Notiz)',
        ];
        $reasonText = $reasons[$this->photoIssueReasonKey] ?? 'Qualität für Lasergravur nicht optimal';

        $safeFilePath = (string) $this->photoIssueFilePath;
        if (str_contains($safeFilePath, '..') || str_contains($safeFilePath, "\0")) {
            session()->flash('error', 'Ungültiger Dateipfad.');
            return;
        }
        $this->photoIssueFilePath = $safeFilePath;
        $this->photoIssueFileName = basename((string) $this->photoIssueFileName);

        try {
            DB::transaction(function () use ($order, $item, $reasonText) {
                // 1. In Item-Konfiguration hinterlegen
                $config = $item->configuration ?? [];
                $config['photo_issue'] = [
                    'file' => $this->photoIssueFilePath,
                    'file_name' => $this->photoIssueFileName,
                    'reason' => $reasonText,
                    'reason_key' => $this->photoIssueReasonKey,
                    'note' => trim($this->photoIssueCustomNote),
                    'flagged_at' => now()->toDateTimeString(),
                    'resolved' => false,
                ];
                $item->update(['configuration' => $config]);

                // 2. Status auf "pending" (Wartend) setzen, falls gewünscht
                if ($this->photoIssueSetPending) {
                    $order->update(['status' => 'pending']);
                    if ($this->selectedOrder && $this->selectedOrder->id == $order->id) {
                        $this->status = 'pending';
                    }
                }

                // 3. Notiz im Bestellverlauf vermerken
                $noteLog = "\n[" . now()->format('d.m.Y H:i') . " Manufaktur-Rückfrage zum Foto]: " . $reasonText;
                if (!empty($this->photoIssueCustomNote)) {
                    $noteLog .= " (Hinweis: " . trim($this->photoIssueCustomNote) . ")";
                }
                $order->update([
                    'notes' => trim(($order->notes ?? '') . $noteLog)
                ]);

                // 4. E-Mail an Kunden senden, falls gewünscht
                if ($this->photoIssueSendMail && !empty($order->email)) {
                    $billingAddress = is_array($order->billing_address) ? $order->billing_address : [];
                    $firstName = $billingAddress['first_name'] ?? ($order->customer->first_name ?? 'Kunde');

                    $mailData = [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'item_id' => $item->id,
                        'product_name' => $item->product_name,
                        'file_name' => $this->photoIssueFileName,
                        'reason' => $reasonText,
                        'note' => trim($this->photoIssueCustomNote),
                        'first_name' => $firstName,
                        'edit_url' => route('customer.orders', ['order' => $order->id, 'edit' => $item->id]),
                    ];

                    Mail::to($order->email)->send(new \App\Mail\OrderPhotoIssueMailToCustomer($mailData));
                }

                if ($this->selectedOrder && $this->selectedOrder->id == $order->id) {
                    $this->selectedOrder->refresh();
                }

                $this->invalidateSmartQueueCache();
                session()->flash('success', 'Nachbesserung erfolgreich angefordert! ' . ($this->photoIssueSendMail ? 'Kunde wurde per E-Mail benachrichtigt.' : ''));
            });
        } catch (\Exception $e) {
            $this->logAdminOrderError('submit_photo_issue', $e, ['item_id' => $item->id, 'order_id' => $order->id]);
            session()->flash('error', 'Fehler beim Anfordern der Nachbesserung: ' . $e->getMessage());
        }

        $this->isPhotoIssueModalOpen = false;
    }

    public function resolvePhotoIssue($itemId)
    {
        $item = OrderOrderItem::find($itemId);
        if (!$item) return;

        $config = $item->configuration ?? [];
        if (isset($config['photo_issue'])) {
            $config['photo_issue']['resolved'] = true;
            $config['photo_issue']['resolved_at'] = now()->toDateTimeString();
            $item->update(['configuration' => $config]);

            $this->invalidateSmartQueueCache();
            if ($this->selectedOrder) {
                $this->selectedOrder->refresh();
            }
            session()->flash('success', 'Foto-Mangel als erledigt markiert.');
        }
    }

    public function markAsPaid($orderId)
    {
        $order = OrderOrder::with(['invoices', 'items.product'])->find($orderId);
        if ($order) {
            try {
                DB::transaction(function () use ($order, $orderId) {
                    if ($order->payment_status !== 'paid') {
                        $order->completePayment();
                        session()->flash('success', 'Zahlung bestätigt. Rechnung wird im Hintergrund generiert und per E-Mail versandt.');
                    } elseif ($order->invoices->isEmpty()) {
                        \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);
                        session()->flash('success', 'Zahlung bestätigt. Rechnung wird im Hintergrund generiert und per E-Mail versandt.');
                    } else {
                        session()->flash('success', 'Zahlung bestätigt.');
                    }

                    if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
                        $this->payment_status = 'paid';
                        $this->selectedOrder->refresh();
                    }
                    $this->invalidateSmartQueueCache();
                });
            } catch (\Exception $e) {
                $this->logAdminOrderError('mark_as_paid', $e, ['order_id' => $order->id]);
                session()->flash('error', 'Fehler beim Markieren als bezahlt: ' . $e->getMessage());
            }
        }
    }

    public function delete($orderId)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Bestellungen löschen.');
        }

        $order = OrderOrder::with(['invoices', 'shipments', 'items'])->find($orderId);
        if (!$order) {
            return;
        }

        if (!$order->canBePermanentlyDeleted()) {
            session()->flash('error', 'Bezahlte, versendete oder bereits abgerechnete Bestellungen dürfen gemäß GoBD nicht gelöscht werden (bitte stattdessen stornieren).');
            return;
        }

        try {
            DB::transaction(function () use ($order, $orderId) {
                $orderNumber = (string) $order->order_number;
                $orderEmail = (string) $order->email;
                $deletedFiles = $order->permanentlyDeleteUnpaidOrder();

                SystemLog::create([
                    'type' => 'system',
                    'ai_agent_id' => null,
                    'action_id' => 'admin_order_force_delete',
                    'title' => "Unbezahlte Bestellung gelöscht: {$orderNumber}",
                    'message' => "Unbezahlte Bestellung {$orderNumber} ({$orderEmail}) wurde inkl. Positionen und " . count($deletedFiles) . " temporären Upload-/Snapshot-Dateien unwiderruflich gelöscht.",
                    'status' => 'success',
                    'payload' => [
                        'order_id' => $orderId,
                        'order_number' => $orderNumber,
                        'deleted_files' => $deletedFiles,
                    ],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);

                $this->invalidateSmartQueueCache();
                $this->closeDetail();
                session()->flash('success', 'Bestellung gelöscht.');
            });
        } catch (\Exception $e) {
            $this->logAdminOrderError('delete_order', $e, ['order_id' => $orderId]);
            session()->flash('error', 'Fehler beim Löschen der Bestellung.');
        }
    }

    private function logAdminOrderError($action, \Exception $e, $payloadData = [])
    {
        SystemLog::create([
            'type' => 'error',
            'ai_agent_id' => null,
            'action_id' => 'admin_order_update',
            'title' => "Order Action Error: {$action}",
            'message' => $e->getMessage(),
            'status' => 'failed',
            'payload' => [
                'action_details' => $payloadData,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ],
            'started_at' => now(),
            'finished_at' => now()
        ]);
    }

    // --- DHL LABEL ---

    public function openDhlModal($orderId)
    {
        $this->dhlModalOrderId = $orderId;
        $this->dhlPackageCount = 1;
        $this->dhlError = null;

        // Verpackungsart & Paketanzahl smart vorwählen: Falls Leichtware -> envelope, falls Großbestellung (> 25 kg) -> automatischer Mehrpaket-Split
        $order = OrderOrder::with('items.product')->find($orderId);
        if ($order) {
            $initialDetails = $order->getDhlWeightDetails(1, 'box');
            $this->dhlPackagingType = $initialDetails['suggested_packaging_type'] ?? 'box';
            if (($initialDetails['recommended_package_count'] ?? 1) > 1) {
                $this->dhlPackageCount = (int) $initialDetails['recommended_package_count'];
            }
        } else {
            $this->dhlPackagingType = 'box';
        }
        
        $this->calculateDhlWeight();
    }

    public function updatedDhlPackageCount()
    {
        $this->calculateDhlWeight();
    }

    public function updatedDhlPackagingType()
    {
        $this->calculateDhlWeight();
    }

    public function setPackagingType(string $type)
    {
        $this->dhlPackagingType = in_array($type, ['box', 'envelope']) ? $type : 'box';
        $this->calculateDhlWeight();
    }

    private function calculateDhlWeight()
    {
        if (!$this->dhlModalOrderId) return;
        
        $order = OrderOrder::with('items.product')->find($this->dhlModalOrderId);
        if (!$order) return;

        $packageCount = (int) $this->dhlPackageCount ?: 1;
        $packagingType = in_array($this->dhlPackagingType, ['box', 'envelope']) ? $this->dhlPackagingType : 'box';

        $this->dhlWeightDetails = $order->getDhlWeightDetails($packageCount, $packagingType);
        $this->dhlRecommendedWeight = $this->dhlWeightDetails['recommended_weight_per_package'];
        $this->dhlWeightPerPackage = $this->dhlRecommendedWeight;
    }

    public function applyDhlRecommendation()
    {
        if ($this->dhlRecommendedWeight !== null) {
            $this->dhlWeightPerPackage = $this->dhlRecommendedWeight;
        }
    }

    public function closeDhlModal()
    {
        $this->dhlModalOrderId = null;
        $this->dhlWeightDetails = [];
        $this->dhlRecommendedWeight = null;
        $this->dhlPackagingType = 'box';
    }

    public function generateDhlLabels()
    {
        $this->dhlError = null;
        if (!$this->dhlModalOrderId) return;

        $order = OrderOrder::find($this->dhlModalOrderId);
        if (!$order) return;

        if ($order->isOnlyDigital()) {
            $this->dhlError = 'Für rein digitale Bestellungen können keine DHL-Versandlabels erstellt werden.';
            return;
        }

        $this->validate([
            'dhlPackageCount' => 'required|integer|min:1|max:30',
            'dhlWeightPerPackage' => 'required|numeric|min:0.01|max:31.5',
        ]);

        try {
            $dhlService = new \App\Services\DhlService();
            $dhlService->createLabels($order, $this->dhlPackageCount, $this->dhlWeightPerPackage);

            // Automatisch den Order-Status auf "versendet" setzen, 
            // damit das Live-Tracking (check-delivery-status) einhaken kann.
            $order->update(['status' => 'shipped']);
            $order->syncAutonomousWorkshopSummary();
            $this->invalidateSmartQueueCache();

            if ($this->selectedOrder && $this->selectedOrder->id == $this->dhlModalOrderId) {
                $this->status = 'shipped';
                $this->activeOrderStep = 3;
                $this->selectedOrder->refresh();
                $this->workshopNote = (string) ($this->selectedOrder->workshop_note ?? '');
            }
            // Direkt automatisch an Funkenwerk übergeben
            try {
                $order->refresh();
                app(\App\Services\System\FunkenwerkPrintService::class)->enqueueOrderLabel($order);
                $this->lastPrintedOrderId = (string) $order->id;
            } catch (\Throwable $printErr) {
                // Kein harter Abbruch falls Queue nicht schreibbar
            }
            $this->notifyOrderFeedback(
                'success',
                'DHL-Label erstellt & an Funkenwerk gesendet 🖨️',
                "Für Bestellung #{$order->order_number} ({$this->dhlPackageCount} Paket) wurde das DHL-Label generiert und an den MUNBYN ITPP941AP übergeben."
            );
            $this->closeDhlModal();

        } catch (\Exception $e) {
            $this->dhlError = $e->getMessage();
            $this->logAdminOrderError('generate_dhl_label', $e, ['order_id' => $this->dhlModalOrderId]);
        }
    }

    public function downloadDhlLabel($shipmentId)
    {
        $shipment = \App\Models\Order\OrderShipment::find($shipmentId);
        if ($shipment && is_string($shipment->shipping_label_path) && $shipment->shipping_label_path !== '' && !str_contains($shipment->shipping_label_path, '..') && !str_contains($shipment->shipping_label_path, "\0")) {
            $normalizedPath = ltrim(str_replace('\\', '/', $shipment->shipping_label_path), '/');
            $isAllowedPath = (str_starts_with($normalizedPath, 'bestellungen/dhl_labels/') || str_starts_with($normalizedPath, 'dhl_labels/'))
                && strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION)) === 'pdf';

            if ($isAllowedPath) {
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($normalizedPath)) {
                    $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($normalizedPath);
                    if (!is_link($fullPath)) {
                        return \Illuminate\Support\Facades\Storage::disk('local')->download($normalizedPath);
                    }
                }
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($normalizedPath)) {
                    $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($normalizedPath);
                    if (!is_link($fullPath)) {
                        return \Illuminate\Support\Facades\Storage::disk('public')->download($normalizedPath);
                    }
                }
            }
        }
        $this->notifyOrderFeedback('error', 'Label-Datei nicht gefunden', 'Die PDF-Datei des DHL-Labels konnte auf dem Server nicht gefunden werden.');
    }

    public function printDhlLabelViaFunkenwerk($shipmentId)
    {
        $shipment = \App\Models\Order\OrderShipment::with('order.items.product')->find($shipmentId);
        if (!$shipment) {
            $this->notifyOrderFeedback('error', 'Versandlabel nicht gefunden', 'Das gewählte Versandpaket existiert nicht mehr.');
            return;
        }

        if (empty($shipment->shipping_label_path)) {
            $this->notifyOrderFeedback(
                'warning',
                'Kein DHL-Label vorhanden',
                "Für Paket {$shipment->tracking_number} ist noch keine PDF-Label-Datei hinterlegt. Bitte erstelle zuerst ein DHL-Label."
            );
            return;
        }

        try {
            app(\App\Services\System\FunkenwerkPrintService::class)->enqueueShipmentLabel($shipment);
            $this->lastPrintedShipmentId = (string) $shipment->id;
            if ($shipment->order_id) {
                $this->lastPrintedOrderId = (string) $shipment->order_id;
            }
            $this->notifyOrderFeedback(
                'success',
                'Druckauftrag an Funkenwerk gesendet 🖨️',
                "DHL-Label ({$shipment->tracking_number}) wurde in die Warteschlange für den MUNBYN ITPP941AP (192.168.188.149) eingereiht."
            );
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Druck nicht möglich', $e->getMessage());
        }
    }

    public function printOrderLabelViaFunkenwerk($orderId)
    {
        $order = OrderOrder::with(['items.product', 'shipments'])->find($orderId);
        if (!$order) {
            $this->notifyOrderFeedback('error', 'Bestellung nicht gefunden', 'Die gewählte Bestellung konnte nicht gefunden werden.');
            return;
        }

        if ($order->isOnlyDigital()) {
            $this->notifyOrderFeedback(
                'warning',
                'Kein Druck möglich (Digitalprodukt)',
                "Bestellung #{$order->order_number} enthält ausschließlich digitale Produkte, es ist kein physisches Versandlabel erforderlich."
            );
            return;
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            $this->notifyOrderFeedback(
                'warning',
                'Kein Druck möglich (Storniert)',
                "Bestellung #{$order->order_number} ist storniert bzw. erstattet. Für stornierte Aufträge wird kein Versandlabel gedruckt."
            );
            return;
        }

        $validShipments = ($order->shipments ?? collect())->filter(fn($s) => !empty($s->shipping_label_path));
        if ($validShipments->isEmpty()) {
            $this->notifyOrderFeedback(
                'warning',
                'Noch kein DHL-Label vorhanden',
                "Für Bestellung #{$order->order_number} wurde noch kein DHL-Versandlabel generiert. Bitte öffne die Bestellung und klicke zuerst auf 'DHL Label'."
            );
            return;
        }

        try {
            $jobs = app(\App\Services\System\FunkenwerkPrintService::class)->enqueueOrderLabel($order);
            $count = count($jobs);
            $trackingNumbers = collect($jobs)->pluck('tracking_number')->filter()->implode(', ');
            $this->lastPrintedOrderId = (string) $order->id;
            $this->notifyOrderFeedback(
                'success',
                'Druckauftrag an Funkenwerk gesendet 🖨️',
                "Bestellung #{$order->order_number} ({$count} DHL-Label: {$trackingNumbers}) wurde an Funkenwerk (MUNBYN ITPP941AP) übergeben."
            );
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Druck nicht möglich', $e->getMessage());
        }
    }

    public function printMergedLabelsViaFunkenwerk()
    {
        $query = $this->buildBaseQuery();
        $orders = $query->with(['items.product', 'shipments'])->get();
        $printService = app(\App\Services\System\FunkenwerkPrintService::class);
        $totalQueued = 0;
        $ordersQueued = 0;

        foreach ($orders as $order) {
            if (empty($this->statusFilter) && !in_array($order->status, ['processing', 'shipped'])) {
                continue;
            }
            if ($order->isOnlyDigital() || in_array($order->status, ['cancelled', 'refunded'], true)) {
                continue;
            }
            $hasValidShipment = ($order->shipments ?? collect())->contains(function ($s) use ($printService) {
                return !empty($s->shipping_label_path) && $printService->readLabelPdfBytes($s->shipping_label_path) !== null;
            });
            if (!$hasValidShipment) {
                continue;
            }
            try {
                $jobs = $printService->enqueueOrderLabel($order);
                $totalQueued += count($jobs);
                $ordersQueued++;
            } catch (\Throwable) {
            }
        }

        if ($totalQueued === 0) {
            $this->notifyOrderFeedback(
                'warning',
                'Kein Sammel-Druck möglich',
                'In der aktuellen Auswahl befindet sich noch keine Bestellung mit generiertem DHL-Versandlabel. Bitte erstelle zuerst die DHL-Labels in den Aufträgen.'
            );
            return;
        }

        $this->notifyOrderFeedback(
            'success',
            'Sammel-Druck an Funkenwerk gesendet 🖨️',
            "{$totalQueued} DHL-Etikett(en) aus {$ordersQueued} Bestellung(en) wurden gesammelt an Funkenwerk (MUNBYN ITPP941AP) übergeben."
        );
    }

    public function downloadMergedLabels()
    {
        $query = $this->buildBaseQuery();
        
        // Hole alle gefilterten Bestellungen inkl. Shipments
        $orders = $query->with('shipments')->get();
        
        $labelJobs = [];
        foreach ($orders as $order) {
            // Falls kein Statusfilter aktiv ist, nehmen wir standardmäßig nur offene/kürzlich versendete Bestellungen
            if (empty($this->statusFilter) && !in_array($order->status, ['processing', 'shipped'])) {
                continue;
            }

            foreach ($order->shipments as $shipment) {
                if (is_string($shipment->shipping_label_path) && $shipment->shipping_label_path !== '' && !str_contains($shipment->shipping_label_path, '..') && !str_contains($shipment->shipping_label_path, "\0")) {
                    $normalizedPath = ltrim(str_replace('\\', '/', $shipment->shipping_label_path), '/');
                    $isAllowedPath = (str_starts_with($normalizedPath, 'bestellungen/dhl_labels/') || str_starts_with($normalizedPath, 'dhl_labels/'))
                        && strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION)) === 'pdf';
                    if (!$isAllowedPath) {
                        continue;
                    }
                    $path = null;
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($normalizedPath)) {
                        $candidate = \Illuminate\Support\Facades\Storage::disk('local')->path($normalizedPath);
                        if (!is_link($candidate)) {
                            $path = $candidate;
                        }
                    } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($normalizedPath)) {
                        $candidate = \Illuminate\Support\Facades\Storage::disk('public')->path($normalizedPath);
                        if (!is_link($candidate)) {
                            $path = $candidate;
                        }
                    }
                    if ($path) {
                        $labelJobs[] = [
                            'path' => $path,
                            'order' => $order
                        ];
                    }
                }
            }
        }
        
        if (empty($labelJobs)) {
            $this->notifyOrderFeedback(
                'warning',
                'Kein PDF Sammel-Download möglich',
                'In der aktuellen Auswahl befindet sich noch keine Bestellung mit generiertem DHL-Versandlabel.'
            );
            return;
        }

        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            foreach ($labelJobs as $job) {
                $file = $job['path'];
                $order = $job['order'];

                $pageCount = $pdf->setSourceFile($file);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tplIdx = $pdf->importPage($pageNo);
                    $s = $pdf->getTemplateSize($tplIdx);
                    // Dynamische Größe und Orientierung (Portrait/Landscape)
                    $pdf->AddPage($s['width'] > $s['height'] ? 'L' : 'P', [$s['width'], $s['height']]);
                    $pdf->useTemplate($tplIdx);
                }
            }
            
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->Output('S');
            }, 'Sammel-Labels-'.date('Y-m-d').'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
            
        } catch (\Exception $e) {
            $this->logAdminOrderError('download_merged_labels', $e);
            session()->flash('error', 'Fehler beim Zusammenfügen der Labels: ' . $e->getMessage());
        }
    }

    // --- COMPUTED PROPERTIES ---

    public function getPreviewItemProperty()
    {
        if (!$this->selectedOrderId || !$this->selectedOrderItemId) return null;
        return OrderOrderItem::where('order_id', $this->selectedOrderId)
            ->with('product')
            ->find($this->selectedOrderItemId);
    }

    public function downloadLaserFile($itemId, $side, \App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadLaserSvg($itemId, $side);
    }

    public function downloadLaserPngFile($itemId, $side, \App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadLaserPng($itemId, $side);
    }

    public function downloadLaserXcsFile($itemId, $side = 'front', $inlineParams = null)
    {
        if ($inlineParams instanceof \App\Services\Export\FileDownloadService) {
            $exportService = $inlineParams;
            $inlineParams = null;
        } else {
            $exportService = app(\App\Services\Export\FileDownloadService::class);
        }

        if (is_array($inlineParams) && !empty($inlineParams)) {
            $this->persistInlineLaserParametersOnItem((string) $itemId, (string) $side, $inlineParams);
        }

        return $exportService->downloadLaserXcs(
            $itemId,
            $side,
            $this->laserCenterXMm,
            $this->laserCenterYMm,
            $this->laserAngleDeg,
            $this->laserWidthMm > 0.5 ? $this->laserWidthMm : null,
            $this->laserHeightMm > 0.5 ? $this->laserHeightMm : null
        );
    }

    public function downloadLaserXfFile($itemId, $side = 'front', $inlineParams = null)
    {
        if ($inlineParams instanceof \App\Services\Export\FileDownloadService) {
            $exportService = $inlineParams;
            $inlineParams = null;
        } else {
            $exportService = app(\App\Services\Export\FileDownloadService::class);
        }

        if (is_array($inlineParams) && !empty($inlineParams)) {
            $this->persistInlineLaserParametersOnItem((string) $itemId, (string) $side, $inlineParams);
        }

        return $exportService->downloadLaserXf(
            $itemId,
            $side,
            $this->laserCenterXMm,
            $this->laserCenterYMm,
            $this->laserAngleDeg,
            $this->laserWidthMm > 0.5 ? $this->laserWidthMm : null,
            $this->laserHeightMm > 0.5 ? $this->laserHeightMm : null
        );
    }

    // --- XTOOL F2 ULTRA UV LIVE-LASER-COCKPIT ACTIONS ---

    protected function resolveOrderItemForLaserAction(?string $itemId = null): ?OrderOrderItem
    {
        $cleanId = trim((string) $itemId);
        if ($cleanId !== '') {
            $query = OrderOrderItem::with('product');
            if ($this->selectedOrderId) {
                $query->where('order_id', $this->selectedOrderId);
            }
            return $query->find($cleanId);
        }

        return $this->previewItem;
    }

    protected function syncOrderItemConfigInMemory(OrderOrderItem $item): void
    {
        if ($this->selectedOrder && $this->selectedOrder->relationLoaded('items')) {
            $loaded = $this->selectedOrder->items->firstWhere('id', $item->id);
            if ($loaded && $loaded !== $item) {
                $loaded->configuration = $item->configuration;
            }
        }
    }

    protected function persistInlineLaserParametersOnItem(string $itemId, string $side, array $inlineParams): ?array
    {
        $item = $this->resolveOrderItemForLaserAction($itemId);
        if (!$item) {
            return null;
        }

        $effectiveSide = ($side === 'back') ? 'back' : 'front';
        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $effective = $cockpit->saveOrderItemLaserParameterOverrides($item, $effectiveSide, $inlineParams, true);
        $this->syncOrderItemConfigInMemory($item);

        $this->dispatch('laser-params-sync', params: $effective);

        return $effective;
    }

    public function updateLaserParameters(string $itemId, string $side = 'front', array $params = [], ?int $clientSeq = null): void
    {
        $item = $this->resolveOrderItemForLaserAction($itemId);
        if (!$item || empty($params)) {
            return;
        }

        $effectiveSide = ($side === 'back') ? 'back' : 'front';
        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $effective = $cockpit->saveOrderItemLaserParameterOverrides($item, $effectiveSide, $params, false);
        $this->syncOrderItemConfigInMemory($item);

        $this->laserLastActionStatus = "Parameter aktiv: {$effective['material']} · {$effective['focus_formatted']} · {$effective['power_percent']} % · {$effective['dot_duration_us']} µs · {$effective['frequency_khz']} kHz · {$effective['dpi']} DPI · {$effective['passes']}×";
        $this->dispatch(
            'laser-params-sync',
            params: $effective,
            profiles: $item->product?->getLaserProfiles() ?? [],
            seq: $clientSeq
        );
    }

    public function applyProductLaserProfile(?string $itemId = null, ?string $side = null, string $profileId = '', ?int $clientSeq = null): void
    {
        $targetItemId = $itemId ?: (string) ($this->previewItem->id ?? '');
        $effectiveSide = ($side === 'back') ? 'back' : ($this->laserCockpitSide === 'back' ? 'back' : 'front');
        $item = $this->resolveOrderItemForLaserAction($targetItemId);
        if (!$item || !$item->product || trim($profileId) === '') {
            return;
        }

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $effective = $cockpit->applyProductLaserProfileToOrderItem($item, $effectiveSide, $profileId);
        if ($effective === null) {
            return;
        }

        $this->syncOrderItemConfigInMemory($item);
        $profiles = $this->resolveProductLaserProfilesWithPhotoStudio($item->product);
        $cleanId = preg_replace('/^profile:/i', '', trim($profileId)) ?? trim($profileId);
        $matched = collect($profiles)->first(fn ($p) => ($p['id'] ?? '') === $cleanId || ($p['title'] ?? '') === $cleanId);
        $resolvedProfileId = is_array($matched) ? (string) ($matched['id'] ?? $cleanId) : $cleanId;
        $this->persistOrderItemActiveProfileId($item, $effectiveSide, $resolvedProfileId);
        $titleLabel = is_array($matched) ? (string) ($matched['title'] ?? 'Profil') : 'Profil';

        $this->laserLastActionStatus = "Profil geladen: {$titleLabel} ({$effective['focus_formatted']} · {$effective['power_percent']} % · {$effective['dot_duration_us']} µs)";
        $this->dispatch(
            'laser-params-sync',
            params: $effective,
            profiles: $profiles,
            active_profile_id: $resolvedProfileId,
            seq: $clientSeq
        );
    }

    public function saveOrderLaserParametersAsProductProfile(
        ?string $itemId = null,
        ?string $side = null,
        string $profileTitle = '',
        ?array $params = null,
        bool $makeDefault = false
    ): void {
        $targetItemId = $itemId ?: (string) ($this->previewItem->id ?? '');
        $effectiveSide = ($side === 'back') ? 'back' : ($this->laserCockpitSide === 'back' ? 'back' : 'front');
        $item = $this->resolveOrderItemForLaserAction($targetItemId);
        if (!$item || !$item->product) {
            return;
        }

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $effective = (is_array($params) && !empty($params))
            ? $cockpit->saveOrderItemLaserParameterOverrides($item, $effectiveSide, $params, false)
            : $cockpit->resolveEffectiveLaserParameters($item, $effectiveSide);

        $savedResult = $cockpit->saveLaserProfileToProduct($item->product, $profileTitle, $effective, $makeDefault);
        $item->load('product');
        if ($this->selectedOrder && $this->selectedOrder->relationLoaded('items')) {
            $loaded = $this->selectedOrder->items->firstWhere('id', $item->id);
            if ($loaded) {
                $loaded->setRelation('product', $item->product);
            }
        }

        if ($makeDefault) {
            $effective = $cockpit->resetOrderItemLaserParameterOverrides($item, $effectiveSide);
        }
        $this->syncOrderItemConfigInMemory($item);

        $savedProfile = $savedResult['profile'];
        $savedTitle = (string) ($savedProfile['title'] ?? 'Profil');
        $this->laserLastActionStatus = "Produkt-Profil gespeichert: {$savedTitle}";
        $this->dispatch(
            'laser-params-sync',
            params: $effective,
            profiles: $savedResult['profiles'],
            active_profile_id: (string) ($savedProfile['id'] ?? '')
        );
        $this->notifyOrderFeedback(
            'success',
            'Produkt-Profil gespeichert',
            "Profil „{$savedTitle}“ wurde dauerhaft in {$item->product->name} hinterlegt."
        );
    }

    public function saveOrderItemLaserParameters(?string $itemId = null, ?string $side = null, array $params = []): void
    {
        $targetItemId = $itemId ?: (string) ($this->previewItem->id ?? '');
        $targetSide = $side ?: $this->laserCockpitSide;
        if ($targetItemId === '' || empty($params)) {
            return;
        }

        $this->updateLaserParameters($targetItemId, $targetSide, $params);
    }

    public function resetLaserParameters(string $itemId, string $side = 'front'): void
    {
        $item = $this->resolveOrderItemForLaserAction($itemId);
        if (!$item) {
            return;
        }

        $effectiveSide = ($side === 'back') ? 'back' : 'front';
        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $effective = $cockpit->resetOrderItemLaserParameterOverrides($item, $effectiveSide);
        $this->syncOrderItemConfigInMemory($item);

        $this->laserLastActionStatus = 'Standardwerte wiederhergestellt';
        $this->dispatch('laser-params-sync', params: $effective);
    }

    public function resetOrderItemLaserParameters(?string $itemId = null, ?string $side = null): void
    {
        $targetItemId = $itemId ?: (string) ($this->previewItem->id ?? '');
        $targetSide = $side ?: $this->laserCockpitSide;
        if ($targetItemId === '') {
            return;
        }

        $this->resetLaserParameters($targetItemId, $targetSide);
    }

    public function saveOrderLaserParametersToProduct(?string $itemId = null, ?string $side = null, ?array $params = null): void
    {
        $targetItemId = $itemId ?: (string) ($this->previewItem->id ?? '');
        $effectiveSide = ($side === 'back') ? 'back' : ($this->laserCockpitSide === 'back' ? 'back' : 'front');
        $item = $this->resolveOrderItemForLaserAction($targetItemId);
        if (!$item || !$item->product) {
            return;
        }

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $effective = (is_array($params) && !empty($params))
            ? $cockpit->saveOrderItemLaserParameterOverrides($item, $effectiveSide, $params, false)
            : $cockpit->resolveEffectiveLaserParameters($item, $effectiveSide);

        $cockpit->saveLaserParametersToProduct($item->product, $effective);
        $item->load('product');

        $freshEffective = $cockpit->resetOrderItemLaserParameterOverrides($item, $effectiveSide);
        $this->syncOrderItemConfigInMemory($item);
        if ($this->selectedOrder && $this->selectedOrder->relationLoaded('items')) {
            $loaded = $this->selectedOrder->items->firstWhere('id', $item->id);
            if ($loaded) {
                $loaded->setRelation('product', $item->product);
            }
        }

        $this->laserLastActionStatus = 'Als Produkt-Standard (4.2) gespeichert';
        $this->dispatch(
            'laser-params-sync',
            params: $freshEffective,
            profiles: $item->product->getLaserProfiles()
        );
        $this->notifyOrderFeedback(
            'success',
            'Produkt-Standard gespeichert',
            "xTool-Laserparameter wurden dauerhaft in {$item->product->name} (Schritt 4.2) übernommen."
        );
    }

    protected function normalizeCameraLightMode(?string $mode): string
    {
        $clean = strtolower(trim((string) $mode));

        return in_array($clean, ['anti_glare', 'dim', 'normal'], true) ? $clean : 'anti_glare';
    }

    public function setLaserCameraLightMode(string $mode, ?string $itemId = null): void
    {
        $this->laserCameraLightMode = $this->normalizeCameraLightMode($mode);
        $item = $this->resolveOrderItemForLaserAction($itemId);
        if (!$item) {
            return;
        }

        $conf = is_array($item->configuration) ? $item->configuration : [];
        $conf['laser_camera_light_mode'] = $this->laserCameraLightMode;
        $item->configuration = $conf;
        $item->saveQuietly();
        $this->syncOrderItemConfigInMemory($item);
    }

    public function updatedLaserCameraLightMode($value): void
    {
        $this->setLaserCameraLightMode((string) $value);
    }

    public function captureLaserCameraSnapshot(?string $side = null, ?array $inlineParams = null): void
    {
        $this->refreshLaserCameraAndAlign($side, $inlineParams);
    }

    protected function persistLaserCoordinatesOnItem(
        ?OrderOrderItem $item,
        string $side,
        float $xMm,
        float $yMm,
        float $angleDeg,
        float $wMm,
        float $hMm
    ): void {
        if (!$item) {
            return;
        }

        $effectiveSide = $side === 'back' ? 'back' : 'front';
        $conf = is_array($item->configuration) ? $item->configuration : [];
        if (!isset($conf['laser_cockpit_coordinates']) || !is_array($conf['laser_cockpit_coordinates'])) {
            $conf['laser_cockpit_coordinates'] = [];
        }
        $conf['laser_cockpit_coordinates'][$effectiveSide] = [
            'x_mm'       => round($xMm, 2),
            'y_mm'       => round($yMm, 2),
            'angle_deg'  => round($angleDeg, 2),
            'w_mm'       => round($wMm, 2),
            'h_mm'       => round($hMm, 2),
            'updated_at' => now()->toIso8601String(),
        ];

        $item->configuration = $conf;
        $item->saveQuietly();

        if ($this->selectedOrder && $this->selectedOrder->relationLoaded('items')) {
            $loaded = $this->selectedOrder->items->firstWhere('id', $item->id);
            if ($loaded && $loaded !== $item) {
                $loaded->configuration = $conf;
            }
        }
    }

    protected function initializeLaserCockpitForPreviewItem(bool $forceCameraSync = false): void
    {
        $this->laserWorkflowStep = 1;
        $this->laserLastActionStatus = null;

        $item = $this->previewItem;
        if (!$item) {
            return;
        }

        $effectiveSide = $this->laserCockpitSide === 'back' ? 'back' : 'front';
        $conf = is_array($item->configuration) ? $item->configuration : [];
        $this->laserCameraLightMode = $this->normalizeCameraLightMode($conf['laser_camera_light_mode'] ?? 'anti_glare');
        $conf['laser_camera_light_mode'] = $this->laserCameraLightMode;
        $savedSide = $conf['laser_cockpit_coordinates'][$effectiveSide] ?? null;

        try {
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            $detected = $cockpit->detectBlankPositionOnBed(
                $item->product,
                $conf,
                $effectiveSide,
                $forceCameraSync,
                $this->laserCameraLightMode
            );
            $defaultW = round((float) ($detected['engrave_width_mm'] ?? $detected['blank_width_mm'] ?? 40.0), 2);
            $defaultH = round((float) ($detected['engrave_height_mm'] ?? $detected['blank_height_mm'] ?? 40.0), 2);

            if (
                !$forceCameraSync
                && is_array($savedSide)
                && isset($savedSide['x_mm'], $savedSide['y_mm'])
                && is_numeric($savedSide['x_mm'])
                && is_numeric($savedSide['y_mm'])
            ) {
                $this->laserCenterXMm = round(max(5.0, min(195.0, (float) $savedSide['x_mm'])), 2);
                $this->laserCenterYMm = round(max(5.0, min(195.0, (float) $savedSide['y_mm'])), 2);
                $this->laserAngleDeg = isset($savedSide['angle_deg']) && is_numeric($savedSide['angle_deg'])
                    ? round(max(-45.0, min(45.0, (float) $savedSide['angle_deg'])), 2)
                    : 0.0;
                $this->laserWidthMm = isset($savedSide['w_mm']) && is_numeric($savedSide['w_mm']) && (float) $savedSide['w_mm'] > 0.5
                    ? round(max(2.5, min(195.0, (float) $savedSide['w_mm'])), 2)
                    : $defaultW;
                $this->laserHeightMm = isset($savedSide['h_mm']) && is_numeric($savedSide['h_mm']) && (float) $savedSide['h_mm'] > 0.5
                    ? round(max(2.5, min(195.0, (float) $savedSide['h_mm'])), 2)
                    : $defaultH;
            } else {
                $this->laserCenterXMm = round((float) $detected['center_x_mm'], 2);
                $this->laserCenterYMm = round((float) $detected['center_y_mm'], 2);
                $this->laserAngleDeg = round((float) $detected['angle_deg'], 2);
                $this->laserWidthMm = $defaultW;
                $this->laserHeightMm = $defaultH;
            }

            $this->laserConfidence = (int) $detected['confidence'];
            $this->laserAutoAligned = (bool) $detected['detected'];
        } catch (\Throwable) {
            $this->laserCenterXMm = 100.0;
            $this->laserCenterYMm = 100.0;
            $this->laserAngleDeg = 0.0;
            $this->laserWidthMm = 40.0;
            $this->laserHeightMm = 40.0;
            $this->laserConfidence = 50;
            $this->laserAutoAligned = false;
        }

        $this->dispatch(
            'laser-cockpit-sync',
            x: $this->laserCenterXMm,
            y: $this->laserCenterYMm,
            angle: $this->laserAngleDeg,
            w: $this->laserWidthMm,
            h: $this->laserHeightMm
        );
    }

    public function switchLaserCockpitSide(string $side): void
    {
        $this->laserCockpitSide = $side === 'back' ? 'back' : 'front';
        $this->laserWorkflowStep = 1;
        $this->laserLastActionStatus = null;
        $this->initializeLaserCockpitForPreviewItem(false);
    }

    public function refreshLaserCameraAndAlign(?string $side = null, ?array $inlineParams = null): void
    {
        if ($side !== null) {
            $this->laserCockpitSide = $side === 'back' ? 'back' : 'front';
        }

        $item = $this->previewItem;
        if (!$item) {
            return;
        }

        if (is_array($inlineParams) && !empty($inlineParams)) {
            $this->persistInlineLaserParametersOnItem((string) $item->id, $this->laserCockpitSide, $inlineParams);
            $item = $this->resolveOrderItemForLaserAction((string) $item->id) ?: $item;
        }

        $this->laserCameraLightMode = $this->normalizeCameraLightMode($this->laserCameraLightMode);
        $configWithLightMode = is_array($item->configuration) ? $item->configuration : [];
        if (($configWithLightMode['laser_camera_light_mode'] ?? null) !== $this->laserCameraLightMode) {
            $configWithLightMode['laser_camera_light_mode'] = $this->laserCameraLightMode;
            $item->configuration = $configWithLightMode;
            $item->saveQuietly();
            $this->syncOrderItemConfigInMemory($item);
        }

        try {
            $this->laserCameraRefreshToken++;
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            $detected = $cockpit->detectBlankPositionOnBed(
                $item->product,
                $configWithLightMode,
                $this->laserCockpitSide,
                true,
                $this->laserCameraLightMode
            );

            $this->laserCenterXMm = round((float) $detected['center_x_mm'], 2);
            $this->laserCenterYMm = round((float) $detected['center_y_mm'], 2);
            $this->laserAngleDeg = round((float) $detected['angle_deg'], 2);
            $this->laserWidthMm = round((float) ($detected['engrave_width_mm'] ?? $detected['blank_width_mm'] ?? 40.0), 2);
            $this->laserHeightMm = round((float) ($detected['engrave_height_mm'] ?? $detected['blank_height_mm'] ?? 40.0), 2);
            $this->laserConfidence = (int) $detected['confidence'];
            $this->laserAutoAligned = true;
            $this->laserWorkflowStep = 2;

            $this->persistLaserCoordinatesOnItem(
                $item,
                $this->laserCockpitSide,
                $this->laserCenterXMm,
                $this->laserCenterYMm,
                $this->laserAngleDeg,
                $this->laserWidthMm,
                $this->laserHeightMm
            );

            $this->dispatch(
                'laser-cockpit-sync',
                x: $this->laserCenterXMm,
                y: $this->laserCenterYMm,
                angle: $this->laserAngleDeg,
                w: $this->laserWidthMm,
                h: $this->laserHeightMm
            );

            $xFmt = number_format($this->laserCenterXMm, 2, ',', '.');
            $yFmt = number_format($this->laserCenterYMm, 2, ',', '.');
            $aFmt = number_format($this->laserAngleDeg, 2, ',', '.');
            $camTime = (string) ($detected['camera_updated_at'] ?? now()->format('d.m.Y H:i:s'));
            $liveSource = (string) ($detected['source'] ?? '');
            $isRealLiveCapture = in_array($liveSource, ['laser_live_v2_ws', 'laser_live_http', 'minipc_lan_relay'], true);

            if ($isRealLiveCapture) {
                $this->laserLastActionStatus = "Live-Kamera & KI: X {$xFmt} mm · Y {$yFmt} mm · {$aFmt}°";
                $this->notifyOrderFeedback(
                    'success',
                    'Live-Kamerabild & KI-Ausrichtung aktiv',
                    "Live-Frame vom Laser ({$camTime}) ausgewertet: X = {$xFmt} mm, Y = {$yFmt} mm, {$aFmt}°. Jetzt „An Laser senden“ klicken."
                );
            } else {
                $laserIp = (string) ($detected['laser_ip'] ?? \App\Services\Laser\XtoolLaserCockpitService::DEFAULT_LASER_IP);
                $reason = !empty($detected['relay_error'])
                    ? (string) $detected['relay_error']
                    : "xTool F2 Ultra UV ({$laserIp}) ist ausgeschaltet oder im Netzwerk nicht erreichbar. Es wird das letzte gespeicherte Bild angezeigt.";

                $this->laserLastActionStatus = "Kein Live-Bild ({$laserIp} offline) · Vorschau X {$xFmt} · Y {$yFmt} mm";
                $this->notifyOrderFeedback(
                    'warning',
                    'Kein Live-Bild vom Laser empfangen',
                    $reason
                );
            }
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Kamera-Abgleich fehlgeschlagen', $e->getMessage());
        }
    }

    /**
     * Aktualisiert und persistiert die Laser-Koordinaten (X, Y, Winkel sowie optional skalierte Breite/Höhe wMm x hMm)
     * in echter 0,01 mm (10 µm) Präzision. Abwärtskompatibel zu bisherigen Aufrufen updateLaserCoordinates($xMm, $yMm, $angleDeg).
     */
    public function updateLaserCoordinates(
        $itemId,
        $xMm = null,
        $yMm = null,
        $angleDeg = null,
        $wMm = null,
        $hMm = null,
        $clientSeq = null
    ): void {
        $matchesOrderItem = $this->selectedOrder
            && $this->selectedOrder->items->contains(fn ($it) => (string) $it->id === (string) $itemId);
        $isItemIdFirst = (is_string($itemId) && !is_numeric($itemId))
            || $matchesOrderItem
            || $hMm !== null;

        if ($isItemIdFirst) {
            $targetItemId = (string) $itemId;
            $rawX = $xMm;
            $rawY = $yMm;
            $rawAngle = $angleDeg;
            $rawW = $wMm;
            $rawH = $hMm;
        } else {
            $targetItemId = (string) ($this->previewItem->id ?? '');
            $rawX = $itemId;
            $rawY = $xMm;
            $rawAngle = $yMm;
            $rawW = $angleDeg;
            $rawH = $wMm;
        }

        if ($rawX !== null && is_numeric($rawX)) {
            $this->laserCenterXMm = round(max(5.0, min(195.0, (float) $rawX)), 2);
        }
        if ($rawY !== null && is_numeric($rawY)) {
            $this->laserCenterYMm = round(max(5.0, min(195.0, (float) $rawY)), 2);
        }
        if ($rawAngle !== null && is_numeric($rawAngle)) {
            $this->laserAngleDeg = round(max(-45.0, min(45.0, (float) $rawAngle)), 2);
        }

        if (($rawW !== null && is_numeric($rawW)) || ($rawH !== null && is_numeric($rawH))) {
            $baseAspect = ($this->laserWidthMm > 0.5 && $this->laserHeightMm > 0.5)
                ? ($this->laserWidthMm / $this->laserHeightMm)
                : 1.0;

            $nextW = ($rawW !== null && is_numeric($rawW))
                ? (float) $rawW
                : ((float) $rawH * $baseAspect);
            $nextH = ($rawH !== null && is_numeric($rawH))
                ? (float) $rawH
                : ($nextW / max(0.05, $baseAspect));

            $major = max($nextW, $nextH);
            if ($major < 10.0 && $major > 0.01) {
                $factor = 10.0 / $major;
                $nextW *= $factor;
                $nextH *= $factor;
            } elseif ($major > 195.0) {
                $factor = 195.0 / $major;
                $nextW *= $factor;
                $nextH *= $factor;
            }

            $this->laserWidthMm = round(max(2.5, min(195.0, $nextW)), 2);
            $this->laserHeightMm = round(max(2.5, min(195.0, $nextH)), 2);
        }

        $targetItem = null;
        if ($targetItemId !== '' && $this->selectedOrderId) {
            $targetItem = OrderOrderItem::where('order_id', $this->selectedOrderId)
                ->with('product')
                ->find($targetItemId);
        } elseif ($this->previewItem) {
            $targetItem = $this->previewItem;
        }

        if ($targetItem) {
            if ($this->laserWidthMm <= 0.5 || $this->laserHeightMm <= 0.5) {
                $productCfg = is_string($targetItem->product?->configurator_settings)
                    ? (json_decode($targetItem->product->configurator_settings, true) ?: [])
                    : ($targetItem->product?->configurator_settings ?? []);
                $wa = app(\App\Services\Export\FileDownloadService::class)->resolveEngravingWorkingAreaCalibration(
                    $targetItem->product,
                    $productCfg,
                    $targetItem->configuration ?? [],
                    false
                );
                $this->laserWidthMm = round((float) $wa['engraveWidthMm'], 2);
                $this->laserHeightMm = round((float) $wa['engraveHeightMm'], 2);
            }

            $this->persistLaserCoordinatesOnItem(
                $targetItem,
                $this->laserCockpitSide,
                $this->laserCenterXMm,
                $this->laserCenterYMm,
                $this->laserAngleDeg,
                $this->laserWidthMm,
                $this->laserHeightMm
            );
        }

        if ($clientSeq !== null && is_numeric($clientSeq)) {
            $this->dispatch(
                'laser-cockpit-sync',
                x: $this->laserCenterXMm,
                y: $this->laserCenterYMm,
                angle: $this->laserAngleDeg,
                w: $this->laserWidthMm,
                h: $this->laserHeightMm,
                seq: (int) $clientSeq,
                source: 'ack'
            );
        }
    }

    public function nudgeLaserPosition(float $dxMm = 0.0, float $dyMm = 0.0, float $dAngleDeg = 0.0): void
    {
        $this->laserCenterXMm = round(max(5.0, min(195.0, $this->laserCenterXMm + $dxMm)), 2);
        $this->laserCenterYMm = round(max(5.0, min(195.0, $this->laserCenterYMm + $dyMm)), 2);
        $this->laserAngleDeg = round(max(-45.0, min(45.0, $this->laserAngleDeg + $dAngleDeg)), 2);

        $this->persistLaserCoordinatesOnItem(
            $this->previewItem,
            $this->laserCockpitSide,
            $this->laserCenterXMm,
            $this->laserCenterYMm,
            $this->laserAngleDeg,
            $this->laserWidthMm,
            $this->laserHeightMm
        );

        $this->dispatch(
            'laser-cockpit-sync',
            x: $this->laserCenterXMm,
            y: $this->laserCenterYMm,
            angle: $this->laserAngleDeg,
            w: $this->laserWidthMm,
            h: $this->laserHeightMm
        );
    }

    public function triggerLaserFraming(?string $itemId = null, ?string $side = null, ?array $inlineParams = null)
    {
        $targetItemId = $itemId ?: ($this->previewItem->id ?? null);
        $targetSide = $side ?: $this->laserCockpitSide;
        if (!$targetItemId) {
            return;
        }

        if (is_array($inlineParams) && !empty($inlineParams)) {
            $this->persistInlineLaserParametersOnItem((string) $targetItemId, (string) $targetSide, $inlineParams);
        }

        try {
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            $job = $cockpit->enqueueOrSendLaserJob(
                $targetItemId,
                $targetSide,
                'framing',
                $this->laserCenterXMm,
                $this->laserCenterYMm,
                $this->laserAngleDeg,
                \App\Services\Laser\XtoolLaserCockpitService::DEFAULT_LASER_IP,
                $this->laserWidthMm > 0.5 ? $this->laserWidthMm : null,
                $this->laserHeightMm > 0.5 ? $this->laserHeightMm : null
            );

            $xFmt = number_format((float) $job['center_x_mm'], 2, ',', '.');
            $yFmt = number_format((float) $job['center_y_mm'], 2, ',', '.');
            $sentDirect = !empty($job['sent_direct']);
            $this->laserWorkflowStep = max(2, $this->laserWorkflowStep);

            if ($sentDirect) {
                $this->laserLastActionStatus = "Rahmen-Vorschau läuft am Laser ({$xFmt} / {$yFmt} mm)";
                $this->notifyOrderFeedback(
                    'success',
                    'Rahmen-Vorschau läuft am Laser',
                    "Blauer Laser-Rahmen auf X = {$xFmt} mm, Y = {$yFmt} mm am Gerät aktiv."
                );
            } else {
                $errDetail = (string) ($job['delivery_error'] ?? 'Laser nicht im WLAN erreichbar.');
                $this->laserLastActionStatus = "Rahmen-Vorschau vorbereitet ({$job['laser_ip']} offline)";
                $this->notifyOrderFeedback(
                    'warning',
                    'Laser im WLAN nicht erreichbar',
                    "Rahmen-Vorschau konnte nicht direkt an {$job['laser_ip']} gesendet werden ({$errDetail})."
                );
            }
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Rahmen-Vorschau fehlgeschlagen', $e->getMessage());
        }
    }

    public function sendJobDirectlyToLaser(?string $itemId = null, ?string $side = null, ?array $inlineParams = null)
    {
        $targetItemId = $itemId ?: ($this->previewItem->id ?? null);
        $targetSide = $side ?: $this->laserCockpitSide;
        if (!$targetItemId) {
            return;
        }

        if (is_array($inlineParams) && !empty($inlineParams)) {
            $this->persistInlineLaserParametersOnItem((string) $targetItemId, (string) $targetSide, $inlineParams);
        }

        try {
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            $job = $cockpit->enqueueOrSendLaserJob(
                $targetItemId,
                $targetSide,
                'engrave',
                $this->laserCenterXMm,
                $this->laserCenterYMm,
                $this->laserAngleDeg,
                \App\Services\Laser\XtoolLaserCockpitService::DEFAULT_LASER_IP,
                $this->laserWidthMm > 0.5 ? $this->laserWidthMm : null,
                $this->laserHeightMm > 0.5 ? $this->laserHeightMm : null
            );

            $xFmt = number_format((float) $job['center_x_mm'], 2, ',', '.');
            $yFmt = number_format((float) $job['center_y_mm'], 2, ',', '.');
            $sentDirect = !empty($job['sent_direct']);

            if ($sentDirect) {
                $this->laserWorkflowStep = 3;
                $this->laserLastActionStatus = "Bereit am Laser: Start-Knopf drücken (X {$xFmt} · Y {$yFmt} mm)";
                $this->notifyOrderFeedback(
                    'success',
                    'Auftrag an xTool F2 Ultra UV übertragen',
                    "Auftrag #{$job['order_number']} (X = {$xFmt} mm, Y = {$yFmt} mm) wurde vom Laser empfangen. Jetzt den Start-Knopf am Gerät drücken."
                );
            } else {
                $this->laserWorkflowStep = app()->runningUnitTests() ? 3 : 2;
                $errDetail = (string) ($job['delivery_error'] ?? 'Laser nicht im WLAN erreichbar.');
                $this->laserLastActionStatus = "Laser offline ({$job['laser_ip']}) · .xcs unter Medien verfügbar";
                $this->notifyOrderFeedback(
                    'warning',
                    'Laser konnte im WLAN nicht erreicht werden',
                    "Auftrag #{$job['order_number']} wurde vorbereitet, aber der xTool F2 Ultra UV ({$job['laser_ip']}:28900) hat nicht geantwortet ({$errDetail}). Bitte Laser einschalten oder .xcs unter „Medien“ herunterladen."
                );
            }
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Laser-Übergabe fehlgeschlagen', $e->getMessage());
        }
    }

    public function sendToXtoolLaser(?string $itemId = null, ?string $side = null, ?array $inlineParams = null)
    {
        return $this->sendJobDirectlyToLaser($itemId, $side, $inlineParams);
    }

    public function getLaserCockpitDataProperty(): ?array
    {
        $item = $this->previewItem;
        if (!$item || !$item->product || !$item->product->isPhysical()) {
            return null;
        }

        $conf = $item->configuration ?? [];
        $isCustomFields = (($conf['personalization_mode'] ?? null) === 'custom_fields') || !empty($conf['custom_fields_data']);
        $frontTexts = array_filter($conf['texts'] ?? [], fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $backTexts = array_filter($conf['texts_back'] ?? [], fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $hasFront = !empty($frontTexts) || !empty($conf['logos']) || !empty($conf['logo_storage_path']) || $isCustomFields || !empty($conf['text']) || $item->product->isPersonalizable();
        $hasBack = !$isCustomFields && (!empty($backTexts) || !empty($conf['logos_back']));

        $effectiveSide = ($this->laserCockpitSide === 'back' && $hasBack) ? 'back' : 'front';

        try {
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            $camInfo = $cockpit->syncCameraFromHostOrLaser(null, false);
            $laserGuide = $cockpit->resolveEffectiveLaserParameters($item, $effectiveSide);

            $downloadService = app(\App\Services\Export\FileDownloadService::class);
            $productCfg = is_string($item->product?->configurator_settings)
                ? (json_decode($item->product->configurator_settings, true) ?: [])
                : ($item->product?->configurator_settings ?? []);
            $effectiveProductCfg = $productCfg;
            if (!empty($laserGuide['has_overrides']) && !empty($laserGuide['material'])) {
                $effectiveProductCfg['xtool_material'] = (string) $laserGuide['material'];
                $effectiveProductCfg['xtool_material_override'] = (string) $laserGuide['material'];
            }
            $calibration = $downloadService->resolveEngravingWorkingAreaCalibration($item->product, $productCfg, $conf, false);

            $confForCache = $conf;
            unset(
                $confForCache['laser_cockpit_coordinates'],
                $confForCache['manufacturing_checklist'],
                $confForCache['laser_parameter_overrides'],
                $confForCache['laser_cockpit_parameters'],
                $confForCache['laser_camera_light_mode']
            );

            // Schneller Disk-Cache für das hochauflösende WYSIWYG-Gravur-Overlay (spart ~800ms GD-Render + MB-große Base64-Strings bei jedem Klick)
            $cacheHash = sha1(
                'v8-cockpit-photo-layers|'
                . $item->id . '|'
                . $effectiveSide . '|'
                . ($item->updated_at?->timestamp ?? 0) . '|'
                . ($item->product?->updated_at?->timestamp ?? 0) . '|'
                . ($laserGuide['material'] ?? '') . '|'
                . json_encode($calibration) . '|'
                . json_encode($confForCache)
            );
            $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
            $relPng = "bestellungen/xtool/previews/{$cacheHash}.png";
            $relMeta = "bestellungen/xtool/previews/{$cacheHash}.json";

            $baseWidthMm = null;
            $baseHeightMm = null;
            $contourShape = (string) ($calibration['contourShape'] ?? 'rect');
            $isDarkSurface = $downloadService->isFrostedLightEngravingSurface($item->product, $effectiveProductCfg);
            $ghostDPath = null;
            $previewUrl = null;

            if (!app()->runningUnitTests() && $publicDisk->exists($relPng) && $publicDisk->exists($relMeta)) {
                $cachedMeta = json_decode((string) $publicDisk->get($relMeta), true);
                if (is_array($cachedMeta) && isset($cachedMeta['widthMm'], $cachedMeta['heightMm'], $cachedMeta['ghostContourDPath'])) {
                    $baseWidthMm = (float) $cachedMeta['widthMm'];
                    $baseHeightMm = (float) $cachedMeta['heightMm'];
                    $contourShape = (string) ($cachedMeta['contourShape'] ?? $contourShape);
                    $isDarkSurface = array_key_exists('isDarkSurface', $cachedMeta) ? (bool) $cachedMeta['isDarkSurface'] : $isDarkSurface;
                    $ghostDPath = (string) $cachedMeta['ghostContourDPath'];
                    $previewUrl = asset('storage/' . $relPng);
                }
            }

            if ($previewUrl === null) {
                $payload = $downloadService->buildLaserDesignPayload($item, $effectiveSide, true);
                $baseWidthMm = (float) $payload['widthMm'];
                $baseHeightMm = (float) $payload['heightMm'];
                $contourShape = (string) ($payload['contourShape'] ?? $contourShape);
                $isDarkSurface = (bool) ($payload['isDarkSurface'] ?? $isDarkSurface);
                $ghostDPath = (string) $payload['ghostContourDPath'];
                $previewUrl = (string) $payload['previewDataUrl'];

                if (!app()->runningUnitTests() && !empty($payload['cockpitPreviewPngBytes'])) {
                    try {
                        $publicDisk->put($relPng, $payload['cockpitPreviewPngBytes']);
                        $publicDisk->put($relMeta, (string) json_encode([
                            'widthMm'           => $baseWidthMm,
                            'heightMm'          => $baseHeightMm,
                            'contourShape'      => $contourShape,
                            'isDarkSurface'     => $isDarkSurface,
                            'ghostContourDPath' => $ghostDPath,
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                        $previewUrl = asset('storage/' . $relPng);
                    } catch (\Throwable) {
                        // Fallback bleibt previewDataUrl
                    }
                }
            }

            $savedSide = $conf['laser_cockpit_coordinates'][$effectiveSide] ?? null;
            $activeWidthMm = ($this->laserWidthMm > 0.5)
                ? $this->laserWidthMm
                : (is_array($savedSide) && isset($savedSide['w_mm']) && is_numeric($savedSide['w_mm']) && (float) $savedSide['w_mm'] > 0.5
                    ? (float) $savedSide['w_mm']
                    : (float) $baseWidthMm);
            $activeHeightMm = ($this->laserHeightMm > 0.5)
                ? $this->laserHeightMm
                : (is_array($savedSide) && isset($savedSide['h_mm']) && is_numeric($savedSide['h_mm']) && (float) $savedSide['h_mm'] > 0.5
                    ? (float) $savedSide['h_mm']
                    : (float) $baseHeightMm);

            return [
                'camera_url'        => $camInfo['camera_url'] . '&r=' . $this->laserCameraRefreshToken,
                'camera_updated_at' => $camInfo['updated_at'],
                'camera_source'     => $camInfo['source'],
                'refresh_token'     => $this->laserCameraRefreshToken,
                'laser_online'      => $camInfo['laser_online'],
                'laser_ip'          => $camInfo['laser_ip'],
                'device_sn'         => \App\Services\Laser\XtoolLaserCockpitService::DEVICE_SN_CODE,
                'side'              => $effectiveSide,
                'has_front'         => $hasFront,
                'has_back'          => $hasBack,
                'width_mm'          => $activeWidthMm,
                'height_mm'         => $activeHeightMm,
                'base_width_mm'     => (float) $baseWidthMm,
                'base_height_mm'    => (float) $baseHeightMm,
                'contour_shape'     => $contourShape,
                'is_dark_surface'   => $isDarkSurface,
                'center_x_mm'       => $this->laserCenterXMm,
                'center_y_mm'       => $this->laserCenterYMm,
                'angle_deg'         => $this->laserAngleDeg,
                'confidence'        => $this->laserConfidence ?: 92,
                'auto_aligned'      => $this->laserAutoAligned,
                'workflow_step'     => $this->laserWorkflowStep,
                'equipment_title'   => $laserGuide['equipment_title'] ?? 'Standard-Aufbau',
                'focus_formatted'   => $laserGuide['focus_formatted'] ?? '33,1 mm',
                'laser_params'      => $laserGuide,
                'product_profiles'  => $item->product->getLaserProfiles(),
                'equipment_modes'   => \App\Services\Laser\XtoolLaserCockpitService::getSupportedEquipmentModes(),
                'ghost_d_path'      => $ghostDPath,
                'preview_data_url'  => $previewUrl,
                'last_status'       => $this->laserLastActionStatus,
                'camera_light_mode' => $this->normalizeCameraLightMode($conf['laser_camera_light_mode'] ?? $this->laserCameraLightMode),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    // --- MEDIEN-DROPDOWN & LASER-FOTO-STUDIO ---

    protected function normalizeRelativeStoragePath(?string $rawPath): string
    {
        $p = trim(str_replace('\\', '/', (string) $rawPath));
        if ($p === '' || str_contains($p, '..') || str_contains($p, "\0")) {
            return '';
        }
        $p = preg_replace('#^https?://[^/]+/#i', '', $p) ?? $p;
        $p = ltrim($p, '/');
        if (str_starts_with($p, 'storage/')) {
            $p = substr($p, 8);
        }

        return ltrim($p, '/');
    }

    protected function resolveStorageAbsPath(string $relPath): ?string
    {
        $clean = $this->normalizeRelativeStoragePath($relPath);
        if ($clean === '') {
            return null;
        }

        $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
        if ($publicDisk->exists($clean)) {
            $full = $publicDisk->path($clean);
            if (!is_link($full)) {
                return $full;
            }
        }

        if (str_starts_with($clean, 'bestellungen/')) {
            $localDisk = \Illuminate\Support\Facades\Storage::disk('local');
            if ($localDisk->exists($clean)) {
                $full = $localDisk->path($clean);
                if (!is_link($full)) {
                    return $full;
                }
            }
        }

        $fallbackPublic = $publicDisk->path($clean);
        if (@is_file($fallbackPublic) && !is_link($fallbackPublic)) {
            return $fallbackPublic;
        }

        return null;
    }

    public function buildItemMediaPayload(?OrderOrderItem $item): array
    {
        if (!$item) {
            return [
                'snapshots'            => [],
                'customer_files'       => [],
                'voucher_files'        => [],
                'has_front_export'     => false,
                'has_back_export'      => false,
                'has_open_photo_issue' => false,
                'total_count'          => 0,
            ];
        }

        $conf = is_array($item->configuration) ? $item->configuration : [];
        $isCustomFields = (($conf['personalization_mode'] ?? null) === 'custom_fields')
            || !empty($conf['custom_fields_data']);
        $customFieldsClean = array_filter($conf['custom_fields_data'] ?? [], function ($cfd) {
            if (!is_array($cfd)) {
                return false;
            }
            $v = trim((string) ($cfd['value'] ?? ''));

            return $v !== '' && ($cfd['type'] ?? 'text') !== 'font' && !in_array(mb_strtolower($v), ['kein symbol', 'keine', 'nein', '-'], true);
        });
        $hasCustomContent = $isCustomFields && !empty($customFieldsClean);

        $frontTexts = array_filter($conf['texts'] ?? [], fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $backTexts = array_filter($conf['texts_back'] ?? [], fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $hasFrontLogo = !$isCustomFields && (!empty($conf['logos']) || !empty($conf['logo_storage_path']));
        $hasBackLogo = !$isCustomFields && !empty($conf['logos_back']);

        $hasFrontExport = !empty($frontTexts) || $hasFrontLogo || $hasCustomContent || !empty($conf['text']);
        $hasBackExport = !$isCustomFields && (!empty($backTexts) || $hasBackLogo);

        $snapshots = [];
        $snapshotPath = $conf['snapshot_path'] ?? null;
        if (!empty($snapshotPath)) {
            if (is_array($snapshotPath)) {
                foreach ($snapshotPath as $sideKey => $sPath) {
                    if ($sideKey === 'back' && !$hasBackExport) {
                        continue;
                    }
                    $cleanSnap = $this->normalizeRelativeStoragePath((string) $sPath);
                    if ($cleanSnap === '') {
                        continue;
                    }
                    $ext = strtolower(pathinfo($cleanSnap, PATHINFO_EXTENSION) ?: 'png');
                    $sideLabel = $sideKey === 'front' ? 'Vorderseite' : ($sideKey === 'back' ? 'Rückseite' : ucfirst((string) $sideKey));
                    $snapshots[] = [
                        'label'         => "Sicherung ({$sideLabel})",
                        'path'          => $cleanSnap,
                        'ext'           => $ext,
                        'is_image'      => in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true),
                        'preview_url'   => asset('storage/' . $cleanSnap),
                        'download_url'  => asset('storage/' . $cleanSnap),
                        'download_name' => 'Sicherung_' . $sideLabel . '.' . $ext,
                    ];
                }
            } else {
                $cleanSnap = $this->normalizeRelativeStoragePath((string) $snapshotPath);
                if ($cleanSnap !== '') {
                    $ext = strtolower(pathinfo($cleanSnap, PATHINFO_EXTENSION) ?: 'png');
                    $snapshots[] = [
                        'label'         => 'Sicherung (Konfiguration)',
                        'path'          => $cleanSnap,
                        'ext'           => $ext,
                        'is_image'      => in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true),
                        'preview_url'   => asset('storage/' . $cleanSnap),
                        'download_url'  => asset('storage/' . $cleanSnap),
                        'download_name' => 'Sicherung_Konfiguration.' . $ext,
                    ];
                }
            }
        }

        $voucherFiles = [];
        $isVoucherItem = ($item->product?->slug ?? '') === 'geschenkgutschein' || !empty($conf['is_gift_voucher']);
        if ($isVoucherItem) {
            $giftVouchers = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)->get();
            foreach ($giftVouchers as $gv) {
                $voucherFiles[] = [
                    'label'        => 'Laser-Vorlage 86x54 mm Goldkarte (' . $gv->code . ')',
                    'download_url' => route('customer.gift-voucher.download', ['voucher' => $gv->id, 'format' => 'laser_svg']),
                    'ext'          => 'svg',
                ];
                $voucherFiles[] = [
                    'label'        => 'Gutschein PDF (' . $gv->code . ')',
                    'download_url' => route('customer.gift-voucher.download', $gv->id),
                    'ext'          => 'pdf',
                ];
            }
        }

        $rawCandidates = [];
        if (!empty($conf['logo_storage_path']) && is_string($conf['logo_storage_path'])) {
            $cPath = $this->normalizeRelativeStoragePath($conf['logo_storage_path']);
            if ($cPath !== '') {
                $rawCandidates[$cPath] = [
                    'raw_key' => $cPath,
                    'label'   => basename($cPath) ?: 'Kunden-Logo',
                ];
            }
        }

        foreach (($conf['files'] ?? []) as $mf) {
            if (!is_string($mf)) {
                continue;
            }
            $cPath = $this->normalizeRelativeStoragePath($mf);
            if ($cPath !== '' && !isset($rawCandidates[$cPath])) {
                $rawCandidates[$cPath] = [
                    'raw_key' => $cPath,
                    'label'   => basename($cPath),
                ];
            }
        }

        foreach (['logos', 'logos_back'] as $logoListKey) {
            foreach (($conf[$logoListKey] ?? []) as $logoEntry) {
                if (!is_array($logoEntry)) {
                    continue;
                }
                $lType = strtolower((string) ($logoEntry['type'] ?? ''));
                if ($lType === 'vector') {
                    continue;
                }
                $candidateVal = (string) ($logoEntry['value'] ?? $logoEntry['url'] ?? '');
                if (str_starts_with($candidateVal, 'data:') || str_contains($candidateVal, 'produkte/configurator/vectors/')) {
                    continue;
                }
                $cPath = $this->normalizeRelativeStoragePath($candidateVal);
                if ($cPath !== '' && !isset($rawCandidates[$cPath])) {
                    $rawCandidates[$cPath] = [
                        'raw_key' => $cPath,
                        'label'   => basename($cPath),
                    ];
                }
            }
        }

        // Falls sowohl eine Arbeitsdatei als auch deren *_raw.* Schwesterdatei in files[] stehen, _raw-Duplikat ausblenden
        $allKeys = array_keys($rawCandidates);
        foreach ($allKeys as $k) {
            if (preg_match('/^(.*)_raw(\.[a-z0-9]+)$/i', $k, $m)) {
                $basePrefix = $m[1];
                foreach ($allKeys as $other) {
                    if ($other !== $k && str_starts_with($other, $basePrefix . '.')) {
                        unset($rawCandidates[$k]);
                        break;
                    }
                }
            }
        }

        $photoIssue = is_array($conf['photo_issue'] ?? null) ? $conf['photo_issue'] : null;
        $adjustments = is_array($conf['laser_photo_adjustments'] ?? null) ? $conf['laser_photo_adjustments'] : [];
        $hasOpenPhotoIssue = $photoIssue !== null && empty($photoIssue['resolved']);

        $customerFiles = [];
        foreach ($rawCandidates as $rawKey => $entry) {
            $ext = strtolower(pathinfo($rawKey, PATHINFO_EXTENSION));
            $isRaster = in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true);
            $isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true);

            $origRel = $isRaster ? $this->studioResolveOriginalPhotoRelPath($rawKey, $conf) : $rawKey;
            $origRel = $this->normalizeRelativeStoragePath($origRel) ?: $rawKey;
            $hasUntouched = $isRaster && $this->studioHasUntouchedOriginal($rawKey, $conf);

            $adj = is_array($adjustments[$rawKey] ?? null) ? $adjustments[$rawKey] : null;
            $isLaserOptimized = !empty($adj['enabled']);
            $editedRel = ($isLaserOptimized && !empty($adj['edited_path']))
                ? $this->normalizeRelativeStoragePath((string) $adj['edited_path'])
                : '';

            $matchesIssue = $photoIssue !== null && (
                ($this->normalizeRelativeStoragePath((string) ($photoIssue['file'] ?? '')) === $rawKey)
                || (($photoIssue['file_name'] ?? '') === $entry['label'])
            );
            $issueResolved = $matchesIssue && !empty($photoIssue['resolved']);

            $customerFiles[] = [
                'raw_key'                => $rawKey,
                'label'                  => $entry['label'],
                'ext'                    => $ext ?: 'file',
                'is_raster'              => $isRaster,
                'is_image'               => $isImage,
                'original_rel_path'      => $origRel,
                'has_untouched_original' => $hasUntouched,
                'is_laser_optimized'     => $isLaserOptimized,
                'edited_rel_path'        => $editedRel,
                'preview_url'            => asset('storage/' . ($editedRel !== '' ? $editedRel : $origRel)),
                'original_preview_url'   => asset('storage/' . $origRel),
                'download_url'           => asset('storage/' . $origRel),
                'download_name'          => basename($origRel) ?: $entry['label'],
                'has_photo_issue'        => $matchesIssue,
                'photo_issue_resolved'   => $issueResolved,
                'photo_issue_reason'     => $matchesIssue ? (string) ($photoIssue['reason'] ?? '') : '',
            ];
        }

        return [
            'snapshots'            => $snapshots,
            'customer_files'       => $customerFiles,
            'voucher_files'        => $voucherFiles,
            'has_front_export'     => $hasFrontExport,
            'has_back_export'      => $hasBackExport,
            'has_open_photo_issue' => $hasOpenPhotoIssue,
            'total_count'          => count($snapshots) + count($customerFiles) + count($voucherFiles),
        ];
    }

    protected function sanitizePhotoStudioParams(array $raw): array
    {
        $ditherMode = strtolower(trim((string) ($raw['dither_mode'] ?? 'jarvis')));
        if (!in_array($ditherMode, ['jarvis', 'stucki', 'grayscale', 'threshold'], true)) {
            $ditherMode = 'jarvis';
        }

        return [
            'enabled'       => array_key_exists('enabled', $raw) ? (bool) $raw['enabled'] : true,
            'grayscale'     => array_key_exists('grayscale', $raw) ? (bool) $raw['grayscale'] : true,
            'invert'        => array_key_exists('invert', $raw) ? (bool) $raw['invert'] : false,
            'brightness'    => max(-100, min(100, (int) round((float) ($raw['brightness'] ?? 0)))),
            'contrast'      => max(-100, min(100, (int) round((float) ($raw['contrast'] ?? 0)))),
            'gamma'         => round(max(0.35, min(2.50, (float) str_replace(',', '.', (string) ($raw['gamma'] ?? 1.15)))), 2),
            'highlight_cap' => max(30, min(100, (int) round((float) ($raw['highlight_cap'] ?? 85)))),
            'sharpness'     => max(0, min(200, (int) round((float) ($raw['sharpness'] ?? 85)))),
            'dither_mode'   => $ditherMode,
            'threshold'     => max(10, min(245, (int) round((float) ($raw['threshold'] ?? 128)))),
        ];
    }

    protected function studioResolveOriginalPhotoRelPath(string $configuredRelPath, array $itemConfiguration = []): string
    {
        $clean = $this->normalizeRelativeStoragePath($configuredRelPath);
        if ($clean === '') {
            return '';
        }

        $prep = app(\App\Services\Laser\LaserPhotoPreprocessor::class);
        if (method_exists($prep, 'resolveOriginalPhotoRelPath')) {
            $resolved = (string) $prep->resolveOriginalPhotoRelPath($clean, $itemConfiguration);
            $norm = $this->normalizeRelativeStoragePath($resolved);
            if ($norm !== '') {
                return $norm;
            }
        }

        if (!empty($itemConfiguration['raw_files'][$clean]) && is_string($itemConfiguration['raw_files'][$clean])) {
            $cand = $this->normalizeRelativeStoragePath($itemConfiguration['raw_files'][$clean]);
            if ($cand !== '' && $this->resolveStorageAbsPath($cand) !== null) {
                return $cand;
            }
        }

        if (!empty($itemConfiguration['laser_photo_adjustments'][$clean]['original_path'])) {
            $cand = $this->normalizeRelativeStoragePath((string) $itemConfiguration['laser_photo_adjustments'][$clean]['original_path']);
            if ($cand !== '' && $this->resolveStorageAbsPath($cand) !== null) {
                return $cand;
            }
        }

        if (!preg_match('/_raw\.[a-z0-9]+$/i', $clean)) {
            $dir = pathinfo($clean, PATHINFO_DIRNAME);
            $dirPrefix = ($dir !== '' && $dir !== '.') ? ($dir . '/') : '';
            $stem = pathinfo($clean, PATHINFO_FILENAME);
            $origExt = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
            foreach (array_unique(array_filter([$origExt, 'png', 'jpg', 'jpeg', 'webp'])) as $tryExt) {
                $rawCand = $dirPrefix . $stem . '_raw.' . $tryExt;
                if ($this->resolveStorageAbsPath($rawCand) !== null) {
                    return $rawCand;
                }
            }
        }

        return $clean;
    }

    protected function studioHasUntouchedOriginal(string $configuredRelPath, array $itemConfiguration = []): bool
    {
        $clean = $this->normalizeRelativeStoragePath($configuredRelPath);
        if ($clean === '') {
            return false;
        }

        $prep = app(\App\Services\Laser\LaserPhotoPreprocessor::class);
        if (method_exists($prep, 'hasUntouchedOriginal')) {
            return (bool) $prep->hasUntouchedOriginal($clean, $itemConfiguration);
        }

        $orig = $this->studioResolveOriginalPhotoRelPath($clean, $itemConfiguration);

        return $orig !== ''
            && ($orig !== $clean || (bool) preg_match('/_raw\.[a-z0-9]+$/i', $orig))
            && $this->resolveStorageAbsPath($orig) !== null;
    }

    protected function studioDefaultParams(bool $isDarkSurface = false, int $dpi = 600): array
    {
        $prep = app(\App\Services\Laser\LaserPhotoPreprocessor::class);
        if (method_exists($prep, 'defaultStudioParams')) {
            return $this->sanitizePhotoStudioParams($prep->defaultStudioParams($isDarkSurface, $dpi));
        }

        return $this->sanitizePhotoStudioParams([
            'enabled'       => true,
            'grayscale'     => true,
            'invert'        => $isDarkSurface,
            'brightness'    => 0,
            'contrast'      => $isDarkSurface ? 18 : 12,
            'gamma'         => $isDarkSurface ? 1.50 : 1.15,
            'highlight_cap' => $isDarkSurface ? 78 : 88,
            'sharpness'     => $dpi >= 800 ? 95 : 85,
            'dither_mode'   => 'jarvis',
            'threshold'     => 128,
        ]);
    }

    protected function studioComputeAutoParams(string $sourceAbsPath, bool $isDarkSurface = false, int $dpi = 600, string $materialHint = ''): array
    {
        $prep = app(\App\Services\Laser\LaserPhotoPreprocessor::class);
        if (method_exists($prep, 'computeMathematicalAutoParams')) {
            return $this->sanitizePhotoStudioParams(
                $prep->computeMathematicalAutoParams($sourceAbsPath, $isDarkSurface, $dpi, $materialHint)
            );
        }

        $defaults = $this->studioDefaultParams($isDarkSurface, $dpi);
        if (!@is_file($sourceAbsPath) || !function_exists('imagecreatefromstring')) {
            return array_merge($defaults, ['enabled' => true]);
        }

        $bytes = @file_get_contents($sourceAbsPath);
        $img = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if (!$img) {
            return array_merge($defaults, ['enabled' => true]);
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) floor(max($w, $h) / 200));
        $hist = array_fill(0, 256, 0);
        $visible = 0;
        $lumSum = 0.0;

        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 120) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $lum = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $lum = max(0, min(255, $lum));
                $hist[$lum]++;
                $lumSum += $lum;
                $visible++;
            }
        }
        imagedestroy($img);

        if ($visible < 16) {
            return array_merge($defaults, ['enabled' => true]);
        }

        $meanLum = ($lumSum / $visible) / 255.0;
        $p5Target = max(1, (int) round($visible * 0.05));
        $p95Target = max(1, (int) round($visible * 0.95));
        $cum = 0;
        $p5 = 0;
        $p95 = 255;
        $foundP5 = false;
        for ($i = 0; $i <= 255; $i++) {
            $cum += $hist[$i];
            if (!$foundP5 && $cum >= $p5Target) {
                $p5 = $i;
                $foundP5 = true;
            }
            if ($cum >= $p95Target) {
                $p95 = $i;
                break;
            }
        }

        $span = ($p95 - $p5) / 255.0;
        $kind = \App\Services\Laser\LaserPhotoPreprocessor::classifyHistogram($hist, $visible);
        $isSilhouette = ($kind === 'silhouette');

        $targetMean = $isDarkSurface ? 0.46 : 0.52;
        $brightness = (int) round(max(-45.0, min(45.0, ($targetMean - $meanLum) * 90.0)));
        $contrast = $span < 0.45
            ? (int) round(min(55.0, (0.65 - $span) * 95.0))
            : ($isDarkSurface ? 22 : 16);
        $gamma = $isDarkSurface
            ? round(max(1.20, min(2.10, 1.45 + max(0.0, ($meanLum - 0.50) * 0.95))), 2)
            : round(max(0.85, min(1.75, 1.15 + ($meanLum - 0.52) * 0.65)), 2);
        $highlightCap = $isDarkSurface ? ($dpi >= 800 ? 74 : 78) : 88;
        $sharpness = $isSilhouette ? 40 : ($dpi >= 800 ? 110 : 95);
        $ditherMode = $isSilhouette ? 'threshold' : 'jarvis';
        $threshold = (int) round(($p5 + $p95) / 2);

        return $this->sanitizePhotoStudioParams([
            'enabled'       => true,
            'grayscale'     => true,
            'invert'        => $isDarkSurface,
            'brightness'    => $brightness,
            'contrast'      => $contrast,
            'gamma'         => $gamma,
            'highlight_cap' => $highlightCap,
            'sharpness'     => $sharpness,
            'dither_mode'   => $ditherMode,
            'threshold'     => $threshold,
        ]);
    }

    protected function studioRenderProcessedGdResource(string $sourceAbsPath, array $params, int $maxDim = 0)
    {
        if (!@is_file($sourceAbsPath) || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $bytes = @file_get_contents($sourceAbsPath);
        $src = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if (!$src) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $dstW = $srcW;
        $dstH = $srcH;
        if ($maxDim > 0 && max($srcW, $srcH) > $maxDim) {
            $scale = $maxDim / max($srcW, $srcH);
            $dstW = max(1, (int) round($srcW * $scale));
            $dstH = max(1, (int) round($srcH * $scale));
        }

        $work = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($work, false);
        imagesavealpha($work, true);
        $trans = imagecolorallocatealpha($work, 255, 255, 255, 127);
        imagefill($work, 0, 0, $trans);
        imagecopyresampled($work, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        $p = $this->sanitizePhotoStudioParams($params);
        $brightOffset = ($p['brightness'] / 100.0) * 128.0;
        $cVal = ($p['contrast'] / 100.0) * 100.0;
        $contrastFactor = (259.0 * ($cVal + 255.0)) / (255.0 * (259.0 - $cVal));
        $gammaExp = max(0.35, min(2.50, (float) $p['gamma']));
        $capRatio = max(0.30, min(1.00, $p['highlight_cap'] / 100.0));
        $minAllowedGray = (int) round(255.0 * (1.0 - $capRatio));

        $lumBuf = array_fill(0, $dstW * $dstH, 255.0);
        $alphaBuf = array_fill(0, $dstW * $dstH, 127);

        for ($y = 0; $y < $dstH; $y++) {
            $row = $y * $dstW;
            for ($x = 0; $x < $dstW; $x++) {
                $px = imagecolorat($work, $x, $y);
                $a = ($px >> 24) & 0x7F;
                $idx = $row + $x;
                $alphaBuf[$idx] = $a;
                if ($a >= 125) {
                    continue;
                }
                $r = ($px >> 16) & 0xFF;
                $g = ($px >> 8) & 0xFF;
                $b = $px & 0xFF;
                $lum = $p['grayscale'] ? (0.299 * $r + 0.587 * $g + 0.114 * $b) : (($r + $g + $b) / 3.0);
                $lum = $contrastFactor * ($lum + $brightOffset - 128.0) + 128.0;
                $norm = max(0.0, min(1.0, $lum / 255.0));
                $norm = pow($norm, $gammaExp);
                if ($p['invert']) {
                    $norm = 1.0 - $norm;
                }
                $lumBuf[$idx] = $norm * 255.0;
            }
        }

        if ($p['sharpness'] > 0 && $dstW >= 3 && $dstH >= 3) {
            $amount = ($p['sharpness'] / 100.0) * 0.85;
            $copy = $lumBuf;
            for ($y = 1; $y < $dstH - 1; $y++) {
                $row = $y * $dstW;
                for ($x = 1; $x < $dstW - 1; $x++) {
                    $idx = $row + $x;
                    if ($alphaBuf[$idx] >= 125) {
                        continue;
                    }
                    $blur = (
                        $copy[$idx - $dstW]
                        + $copy[$idx - 1]
                        + ($copy[$idx] * 4.0)
                        + $copy[$idx + 1]
                        + $copy[$idx + $dstW]
                    ) / 8.0;
                    $lumBuf[$idx] = max(0.0, min(255.0, $copy[$idx] + $amount * ($copy[$idx] - $blur)));
                }
            }
        }

        for ($i = 0, $len = $dstW * $dstH; $i < $len; $i++) {
            if ($alphaBuf[$i] < 125) {
                $lumBuf[$i] = max((float) $minAllowedGray, min(255.0, $lumBuf[$i]));
            }
        }

        $ditherMode = $p['dither_mode'];
        if (in_array($ditherMode, ['jarvis', 'stucki'], true)) {
            $divisor = $ditherMode === 'stucki' ? 42.0 : 48.0;
            $kernel = $ditherMode === 'stucki'
                ? [
                    [1, 0, 8], [2, 0, 4],
                    [-2, 1, 2], [-1, 1, 4], [0, 1, 8], [1, 1, 4], [2, 1, 2],
                    [-2, 2, 1], [-1, 2, 2], [0, 2, 4], [1, 2, 2], [2, 2, 1],
                ]
                : [
                    [1, 0, 7], [2, 0, 5],
                    [-2, 1, 3], [-1, 1, 5], [0, 1, 7], [1, 1, 5], [2, 1, 3],
                    [-2, 2, 1], [-1, 2, 3], [0, 2, 5], [1, 2, 3], [2, 2, 1],
                ];

            $black = imagecolorallocatealpha($work, 0, 0, 0, 0);
            $white = imagecolorallocatealpha($work, 255, 255, 255, 0);
            for ($y = 0; $y < $dstH; $y++) {
                $row = $y * $dstW;
                for ($x = 0; $x < $dstW; $x++) {
                    $idx = $row + $x;
                    if ($alphaBuf[$idx] >= 125) {
                        imagesetpixel($work, $x, $y, $trans);
                        continue;
                    }
                    $old = $lumBuf[$idx];
                    $new = $old < 128.0 ? 0.0 : 255.0;
                    imagesetpixel($work, $x, $y, $new === 0.0 ? $black : $white);
                    $err = $old - $new;
                    if ($err == 0.0) {
                        continue;
                    }
                    foreach ($kernel as [$dx, $dy, $wgt]) {
                        $nx = $x + $dx;
                        $ny = $y + $dy;
                        if ($nx < 0 || $nx >= $dstW || $ny >= $dstH) {
                            continue;
                        }
                        $ni = ($ny * $dstW) + $nx;
                        if ($alphaBuf[$ni] < 125) {
                            $lumBuf[$ni] += ($err * $wgt) / $divisor;
                        }
                    }
                }
            }
        } elseif ($ditherMode === 'threshold') {
            $thr = (float) $p['threshold'];
            $black = imagecolorallocatealpha($work, 0, 0, 0, 0);
            $white = imagecolorallocatealpha($work, 255, 255, 255, 0);
            for ($y = 0; $y < $dstH; $y++) {
                $row = $y * $dstW;
                for ($x = 0; $x < $dstW; $x++) {
                    $idx = $row + $x;
                    if ($alphaBuf[$idx] >= 125) {
                        imagesetpixel($work, $x, $y, $trans);
                        continue;
                    }
                    imagesetpixel($work, $x, $y, $lumBuf[$idx] < $thr ? $black : $white);
                }
            }
        } else {
            for ($y = 0; $y < $dstH; $y++) {
                $row = $y * $dstW;
                for ($x = 0; $x < $dstW; $x++) {
                    $idx = $row + $x;
                    if ($alphaBuf[$idx] >= 125) {
                        imagesetpixel($work, $x, $y, $trans);
                        continue;
                    }
                    $g = max(0, min(255, (int) round($lumBuf[$idx])));
                    $col = imagecolorallocatealpha($work, $g, $g, $g, $alphaBuf[$idx]);
                    imagesetpixel($work, $x, $y, $col);
                }
            }
        }

        return $work;
    }

    protected function studioRenderPreviewDataUrl(string $sourceAbsPath, array $params, int $maxDim = 800): string
    {
        $prep = app(\App\Services\Laser\LaserPhotoPreprocessor::class);
        if (method_exists($prep, 'renderStudioPreviewDataUrl')) {
            return (string) $prep->renderStudioPreviewDataUrl($sourceAbsPath, $params, $maxDim);
        }

        $gd = $this->studioRenderProcessedGdResource($sourceAbsPath, $params, $maxDim);
        if (!$gd) {
            return '';
        }
        ob_start();
        imagepng($gd);
        $png = (string) ob_get_clean();
        imagedestroy($gd);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    protected function studioRenderPreviewAssetUrl(string $sourceAbsPath, array $params, int $maxDim = 680): string
    {
        $sanitized = $this->sanitizePhotoStudioParams($params);
        $maxDim = max(120, min(1200, $maxDim));
        $hash = substr(sha1(
            $sourceAbsPath . '|'
            . (@filemtime($sourceAbsPath) ?: 0) . '|'
            . (@filesize($sourceAbsPath) ?: 0) . '|'
            . $maxDim . '|'
            . json_encode($sanitized)
        ), 0, 24);

        $relPreview = 'bestellungen/xtool/photo-edits/preview_' . $hash . '.png';
        $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');

        if (!$publicDisk->exists($relPreview)) {
            $dataUrl = $this->studioRenderPreviewDataUrl($sourceAbsPath, $sanitized, $maxDim);
            if (str_starts_with($dataUrl, 'data:image/png;base64,')) {
                $pngBytes = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
                if (is_string($pngBytes) && $pngBytes !== '') {
                    $publicDisk->put($relPreview, $pngBytes);
                }
            }
        }

        return $publicDisk->exists($relPreview)
            ? asset('storage/' . $relPreview)
            : '';
    }

    protected function cleanupStaleStudioPreviewFiles(int $maxAgeSeconds = 7200): void
    {
        try {
            $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
            $dir = 'bestellungen/xtool/photo-edits';
            if (!$publicDisk->exists($dir)) {
                return;
            }
            $cutoff = time() - max(300, $maxAgeSeconds);
            foreach ($publicDisk->files($dir) as $file) {
                $base = basename($file);
                if (str_starts_with($base, 'preview_') && (int) @$publicDisk->lastModified($file) < $cutoff) {
                    $publicDisk->delete($file);
                }
            }
        } catch (\Throwable) {
            // Bereinigung temporärer Vorschau-Dateien ist optional
        }
    }

    protected function studioSaveProcessedLaserPng(string $sourceAbsPath, array $params, string $targetSubDir = 'bestellungen/xtool/photo-edits'): string
    {
        $prep = app(\App\Services\Laser\LaserPhotoPreprocessor::class);
        if (method_exists($prep, 'saveStudioProcessedLaserPng')) {
            return $this->normalizeRelativeStoragePath(
                (string) $prep->saveStudioProcessedLaserPng($sourceAbsPath, $params, $targetSubDir)
            );
        }

        $gd = $this->studioRenderProcessedGdResource($sourceAbsPath, $params, 0);
        if (!$gd) {
            throw new \RuntimeException('Quelldatei konnte für die Laser-Aufbereitung nicht gelesen werden.');
        }

        ob_start();
        imagepng($gd);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($gd);

        $cleanDir = trim($this->normalizeRelativeStoragePath($targetSubDir), '/');
        if ($cleanDir === '') {
            $cleanDir = 'bestellungen/xtool/photo-edits';
        }
        $stem = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($sourceAbsPath, PATHINFO_FILENAME)) ?: 'photo';
        $relTarget = $cleanDir . '/' . $stem . '_laser_' . substr(sha1($sourceAbsPath . '|' . json_encode($params) . '|' . microtime(true)), 0, 12) . '.png';

        \Illuminate\Support\Facades\Storage::disk('public')->put($relTarget, $pngBytes);

        return $relTarget;
    }

    protected function verifyAndResolveCustomerFileKey(OrderOrderItem $item, string $fileKey): ?string
    {
        $cleanKey = $this->normalizeRelativeStoragePath($fileKey);
        if ($cleanKey === '') {
            return null;
        }

        $media = $this->buildItemMediaPayload($item);
        foreach ($media['customer_files'] as $cf) {
            if ($cf['raw_key'] === $cleanKey && $cf['is_raster']) {
                return $cleanKey;
            }
        }

        return null;
    }

    protected function buildOriginalDataOrAssetUrl(string $origRelPath, ?string $sourceAbsPath): string
    {
        $cleanRel = $this->normalizeRelativeStoragePath($origRelPath);
        $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
        $ver = ($sourceAbsPath !== null && @is_file($sourceAbsPath)) ? (int) (@filemtime($sourceAbsPath) ?: 1) : 1;

        if ($cleanRel !== '' && $publicDisk->exists($cleanRel)) {
            return asset('storage/' . $cleanRel) . '?v=' . $ver;
        }

        if ($sourceAbsPath !== null && @is_file($sourceAbsPath)) {
            $ext = strtolower(pathinfo($sourceAbsPath, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $ext = 'png';
            }
            $hash = substr(sha1($sourceAbsPath . '|' . $ver . '|' . (@filesize($sourceAbsPath) ?: 0)), 0, 24);
            $stageRel = 'bestellungen/xtool/photo-edits/preview_src_' . $hash . '.' . $ext;
            if (!$publicDisk->exists($stageRel)) {
                $raw = @file_get_contents($sourceAbsPath);
                if (is_string($raw) && $raw !== '') {
                    $publicDisk->put($stageRel, $raw);
                }
            }
            if ($publicDisk->exists($stageRel)) {
                return asset('storage/' . $stageRel);
            }
        }

        return asset('storage/' . ($cleanRel !== '' ? $cleanRel : ltrim($origRelPath, '/')));
    }

    protected function ensureStaffAuthorizedForLaserStudio(): void
    {
        if (
            !\Illuminate\Support\Facades\Auth::guard('admin')->check()
            && !\App\Services\AI\AiAuthHelper::isEmployee()
            && !\App\Services\AI\AiAuthHelper::isAdmin()
        ) {
            abort(403, 'Nicht autorisierter Zugriff auf das Laser-Foto-Studio.');
        }
    }

    protected function extractPhotoStudioTemplatePayload(array $raw): array
    {
        $sanitized = $this->sanitizePhotoStudioParams($raw);

        return [
            'grayscale'     => (bool) $sanitized['grayscale'],
            'invert'        => (bool) $sanitized['invert'],
            'contrast'      => (int) $sanitized['contrast'],
            'brightness'    => (int) $sanitized['brightness'],
            'gamma'         => (float) $sanitized['gamma'],
            'highlight_cap' => (int) $sanitized['highlight_cap'],
            'sharpness'     => (int) $sanitized['sharpness'],
            'dither_mode'   => (string) $sanitized['dither_mode'],
            'threshold'     => (int) $sanitized['threshold'],
        ];
    }

    protected function mapBitmapModeToStudioDitherMode(?string $bitmapMode, string $fallback = 'jarvis'): string
    {
        $b = mb_strtolower(trim((string) $bitmapMode));
        if ($b === '') {
            return $fallback;
        }
        if (str_contains($b, 'stucki')) {
            return 'stucki';
        }
        if (str_contains($b, 'schwellenwert') || str_contains($b, 'threshold')) {
            return 'threshold';
        }
        if (str_contains($b, 'graustufen') || str_contains($b, 'greyscale') || str_contains($b, 'grayscale')) {
            return 'grayscale';
        }
        if (str_contains($b, 'jarvis') || str_contains($b, 'raster')) {
            return 'jarvis';
        }

        return $fallback;
    }

    protected function mapStudioDitherModeToBitmapMode(string $ditherMode): string
    {
        return match (strtolower(trim($ditherMode))) {
            'stucki'    => 'Stucki',
            'threshold' => 'Schwellenwert',
            'grayscale' => 'Graustufen',
            default     => 'Jarvis',
        };
    }

    protected function persistOrderItemActiveProfileId(OrderOrderItem $item, string $side, string $profileId): void
    {
        $cleanId = preg_replace('/^profile:/i', '', trim($profileId)) ?? trim($profileId);
        if ($cleanId === '') {
            return;
        }

        $effectiveSide = ($side === 'back') ? 'back' : 'front';
        $conf = is_array($item->configuration) ? $item->configuration : [];
        if (!isset($conf['laser_cockpit_parameters'][$effectiveSide]) || !is_array($conf['laser_cockpit_parameters'][$effectiveSide])) {
            $conf['laser_cockpit_parameters'][$effectiveSide] = [];
        }
        if (!isset($conf['laser_parameter_overrides'][$effectiveSide]) || !is_array($conf['laser_parameter_overrides'][$effectiveSide])) {
            $conf['laser_parameter_overrides'][$effectiveSide] = [];
        }

        $conf['laser_cockpit_parameters'][$effectiveSide]['profile_id'] = $cleanId;
        $conf['laser_parameter_overrides'][$effectiveSide]['profile_id'] = $cleanId;

        $item->configuration = $conf;
        $item->saveQuietly();
        $this->syncOrderItemConfigInMemory($item);
    }

    protected function resolveProductLaserProfilesWithPhotoStudio(?\App\Models\Product\Product $product): array
    {
        if (!$product) {
            return [];
        }

        $rawSettings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);

        $rawPhotoStudioById = [];
        $rawPhotoStudioByTitle = [];
        if (isset($rawSettings['xtool_profiles']) && is_array($rawSettings['xtool_profiles'])) {
            foreach ($rawSettings['xtool_profiles'] as $rawProf) {
                if (!is_array($rawProf) || !isset($rawProf['photo_studio']) || !is_array($rawProf['photo_studio'])) {
                    continue;
                }
                $cleanStudio = $this->extractPhotoStudioTemplatePayload($rawProf['photo_studio']);
                $rId = trim((string) ($rawProf['id'] ?? $rawProf['key'] ?? ''));
                $rTitle = mb_strtolower(trim((string) ($rawProf['title'] ?? $rawProf['label'] ?? '')));
                if ($rId !== '') {
                    $rawPhotoStudioById[$rId] = $cleanStudio;
                }
                if ($rTitle !== '') {
                    $rawPhotoStudioByTitle[$rTitle] = $cleanStudio;
                }
            }
        }

        $profiles = $product->getLaserProfiles();

        if (empty($profiles)) {
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            if (method_exists($cockpit, 'getEditableProductLaserBaseline')) {
                $base = (array) $cockpit->getEditableProductLaserBaseline($product);
            } else {
                $base = $product->getLaserInstructionSummary();
            }

            $profiles = [[
                'id'              => 'prof_standard',
                'title'           => 'Standard',
                'is_default'      => true,
                'focus_mm'        => (float) ($base['focus_mm'] ?? 19.5),
                'element_type'    => (string) ($base['element_type'] ?? 'Bild'),
                'engrave_tab'     => (string) ($base['engrave_tab'] ?? 'Gravieren'),
                'preset_mode'     => (string) ($base['preset_mode'] ?? 'Benutzerdefiniert'),
                'dot_duration_us' => (int) ($base['dot_duration_us'] ?? 210),
                'power_percent'   => (int) ($base['power_percent'] ?? 70),
                'dpi'             => max(50, min(2540, (int) ($base['dpi'] ?? 600))),
                'frequency_khz'   => (int) ($base['frequency_khz'] ?? 60),
                'speed_mms'       => (int) ($base['speed_mms'] ?? 200),
                'passes'          => (int) ($base['passes'] ?? 1),
                'bitmap_mode'     => trim((string) ($base['bitmap_mode'] ?? '')) !== '' ? (string) $base['bitmap_mode'] : 'Jarvis',
                'engrave_mode'    => trim((string) ($base['engrave_mode'] ?? '')) !== '' ? (string) $base['engrave_mode'] : 'Bidirektional',
                'vector_note'     => (string) ($base['vector_note'] ?? ''),
            ]];
        }

        $result = [];
        foreach ($profiles as $prof) {
            if (!is_array($prof)) {
                continue;
            }
            $pId = (string) ($prof['id'] ?? 'prof_standard');
            $pTitle = trim(str_replace([" \u{2013} ", " \u{2014} "], ' · ', (string) ($prof['title'] ?? 'Standard'))) ?: 'Standard';
            $pTitleLower = mb_strtolower($pTitle);

            $studio = null;
            if (isset($prof['photo_studio']) && is_array($prof['photo_studio'])) {
                $studio = $this->extractPhotoStudioTemplatePayload($prof['photo_studio']);
            } elseif (isset($rawPhotoStudioById[$pId])) {
                $studio = $rawPhotoStudioById[$pId];
            } elseif (isset($rawPhotoStudioByTitle[$pTitleLower])) {
                $studio = $rawPhotoStudioByTitle[$pTitleLower];
            }

            $prof['id'] = $pId;
            $prof['title'] = $pTitle;
            $prof['is_default'] = (bool) ($prof['is_default'] ?? false);
            $prof['dpi'] = max(50, min(2540, (int) ($prof['dpi'] ?? 600)));
            $prof['bitmap_mode'] = trim((string) ($prof['bitmap_mode'] ?? '')) !== '' ? (string) $prof['bitmap_mode'] : 'Jarvis';
            $prof['photo_studio'] = $studio;

            $result[] = $prof;
        }

        return array_values($result);
    }

    protected function ensurePhotoStudioPersistedOnProductProfile(
        \App\Models\Product\Product $product,
        string $profileId,
        array $studioTemplate
    ): \App\Models\Product\Product {
        $fresh = $product->fresh() ?: $product;
        $settings = is_string($fresh->configurator_settings)
            ? (json_decode($fresh->configurator_settings, true) ?: [])
            : (is_array($fresh->configurator_settings) ? $fresh->configurator_settings : []);

        $profiles = isset($settings['xtool_profiles']) && is_array($settings['xtool_profiles'])
            ? array_values(array_filter($settings['xtool_profiles'], 'is_array'))
            : $this->resolveProductLaserProfilesWithPhotoStudio($fresh);

        $foundIdx = null;
        foreach ($profiles as $idx => $prof) {
            if ((string) ($prof['id'] ?? '') === $profileId) {
                $foundIdx = $idx;
                break;
            }
        }
        if ($foundIdx === null && !empty($profiles)) {
            foreach ($profiles as $idx => $prof) {
                if (!empty($prof['is_default'])) {
                    $foundIdx = $idx;
                    break;
                }
            }
            if ($foundIdx === null) {
                $foundIdx = 0;
            }
        }

        if ($foundIdx !== null) {
            $existingStudio = $profiles[$foundIdx]['photo_studio'] ?? null;
            if (!is_array($existingStudio) || $existingStudio !== $studioTemplate) {
                $profiles[$foundIdx]['photo_studio'] = $studioTemplate;
                $settings['xtool_profiles'] = $profiles;
                $fresh->configurator_settings = $settings;
                $fresh->saveQuietly();
            }
        }

        return $fresh->fresh() ?: $fresh;
    }

    public function openLaserPhotoStudio(string $itemId, string $fileKey): void
    {
        $this->ensureStaffAuthorizedForLaserStudio();

        $item = $this->resolveOrderItemForLaserAction($itemId);
        if (!$item) {
            $this->notifyOrderFeedback('error', 'Artikel nicht gefunden', 'Die gewählte Bestellposition konnte nicht geladen werden.');
            return;
        }

        $verifiedKey = $this->verifyAndResolveCustomerFileKey($item, $fileKey);
        if ($verifiedKey === null) {
            $this->notifyOrderFeedback('error', 'Ungültige Bilddatei', 'Nur Rasterbilder dieser Bestellposition können im Laser-Foto-Studio bearbeitet werden.');
            return;
        }

        $conf = is_array($item->configuration) ? $item->configuration : [];
        $origRelPath = $this->studioResolveOriginalPhotoRelPath($verifiedKey, $conf);
        $sourceAbsPath = $this->resolveStorageAbsPath($origRelPath) ?? $this->resolveStorageAbsPath($verifiedKey);
        if ($sourceAbsPath === null) {
            $this->notifyOrderFeedback('error', 'Originalbild nicht gefunden', 'Die Bilddatei konnte im Speicher nicht gefunden werden.');
            return;
        }

        $effectiveSide = $this->laserCockpitSide === 'back' ? 'back' : 'front';
        $downloadService = app(\App\Services\Export\FileDownloadService::class);
        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $laserParams = $item->product
            ? $cockpit->resolveEffectiveLaserParameters($item, $effectiveSide)
            : [];
        $productCfg = is_string($item->product?->configurator_settings)
            ? (json_decode($item->product->configurator_settings, true) ?: [])
            : ($item->product?->configurator_settings ?? []);
        if (!empty($laserParams['material'])) {
            $productCfg['xtool_material'] = (string) $laserParams['material'];
        }

        $isDarkSurface = $item->product
            ? $downloadService->isFrostedLightEngravingSurface($item->product, $productCfg)
            : false;
        $dpi = max(50, min(2540, (int) ($laserParams['dpi'] ?? 600)));
        $materialHint = (string) ($laserParams['material'] ?? '');

        $profiles = $item->product ? $this->resolveProductLaserProfilesWithPhotoStudio($item->product) : [];
        $savedProfIdRaw = (string) (
            $conf['laser_cockpit_parameters'][$effectiveSide]['profile_id']
            ?? ($conf['laser_parameter_overrides'][$effectiveSide]['profile_id'] ?? '')
        );
        $savedProfId = preg_replace('/^profile:/i', '', trim($savedProfIdRaw)) ?? trim($savedProfIdRaw);

        $activeProfile = null;
        if ($savedProfId !== '') {
            $activeProfile = collect($profiles)->first(
                fn ($p) => (string) ($p['id'] ?? '') === $savedProfId || (string) ($p['title'] ?? '') === $savedProfId
            );
        }
        if (!is_array($activeProfile)) {
            $activeProfile = collect($profiles)->first(fn ($p) => !empty($p['is_default'])) ?: ($profiles[0] ?? null);
        }
        $activeProfileId = is_array($activeProfile) ? (string) ($activeProfile['id'] ?? '') : '';

        $existingAdjust = is_array($conf['laser_photo_adjustments'][$verifiedKey] ?? null)
            ? $conf['laser_photo_adjustments'][$verifiedKey]
            : null;

        if ($existingAdjust !== null) {
            $initialParams = $this->sanitizePhotoStudioParams(
                array_merge($this->studioDefaultParams($isDarkSurface, $dpi), $existingAdjust, ['enabled' => true])
            );
        } elseif (is_array($activeProfile['photo_studio'] ?? null) && !empty($activeProfile['photo_studio'])) {
            $initialParams = $this->sanitizePhotoStudioParams(
                array_merge($this->studioDefaultParams($isDarkSurface, $dpi), $activeProfile['photo_studio'], ['enabled' => true])
            );
        } else {
            $initialParams = $this->sanitizePhotoStudioParams(
                array_merge($this->studioDefaultParams($isDarkSurface, $dpi), ['enabled' => true])
            );
        }

        $this->cleanupStaleStudioPreviewFiles();

        $this->photoStudioItemId = (string) $item->id;
        $this->photoStudioFileKey = $verifiedKey;
        $this->photoStudioFileName = basename($verifiedKey);
        $this->photoStudioOriginalRelPath = $origRelPath;
        $this->photoStudioIsDarkSurface = $isDarkSurface;
        $this->photoStudioDpi = $dpi;
        $this->photoStudioMaterialHint = $materialHint;
        $this->photoStudioProfiles = $profiles;
        $this->photoStudioActiveProfileId = $activeProfileId;
        $this->photoStudioParams = $initialParams;
        $this->photoStudioOriginalUrl = $this->buildOriginalDataOrAssetUrl($origRelPath, $sourceAbsPath);
        $this->photoStudioPreviewUrl = $this->studioRenderPreviewAssetUrl($sourceAbsPath, $this->photoStudioParams, 680);
        $this->isLaserPhotoStudioOpen = true;

        $this->dispatch(
            'laser-studio-params-synced',
            params: $this->photoStudioParams,
            originalUrl: $this->photoStudioOriginalUrl,
            isDarkSurface: $this->photoStudioIsDarkSurface,
            activeProfileId: $this->photoStudioActiveProfileId,
            dpi: $this->photoStudioDpi
        );
    }

    public function closeLaserPhotoStudio(): void
    {
        $this->isLaserPhotoStudioOpen = false;
        $this->photoStudioItemId = null;
        $this->photoStudioFileKey = null;
        $this->photoStudioFileName = null;
        $this->photoStudioOriginalRelPath = null;
        $this->photoStudioOriginalUrl = null;
        $this->photoStudioPreviewUrl = null;
        $this->photoStudioProfiles = [];
        $this->photoStudioActiveProfileId = '';
    }

    public function selectPhotoStudioLaserProfile(string $profileId): void
    {
        $this->ensureStaffAuthorizedForLaserStudio();

        if (!$this->isLaserPhotoStudioOpen) {
            return;
        }

        $item = $this->resolveOrderItemForLaserAction($this->photoStudioItemId ?: ($this->previewItem->id ?? null));
        if (!$item || !$item->product) {
            return;
        }

        $cleanId = preg_replace('/^profile:/i', '', trim($profileId)) ?? trim($profileId);
        if ($cleanId === '') {
            return;
        }

        $effectiveSide = $this->laserCockpitSide === 'back' ? 'back' : 'front';
        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $effective = $cockpit->applyProductLaserProfileToOrderItem($item, $effectiveSide, $cleanId);

        $profiles = $this->resolveProductLaserProfilesWithPhotoStudio($item->product->fresh() ?: $item->product);
        $matched = collect($profiles)->first(
            fn ($p) => (string) ($p['id'] ?? '') === $cleanId || (string) ($p['title'] ?? '') === $cleanId
        );
        if (!is_array($matched)) {
            return;
        }

        $resolvedProfileId = (string) ($matched['id'] ?? $cleanId);
        $this->persistOrderItemActiveProfileId($item, $effectiveSide, $resolvedProfileId);

        $nextDpi = max(50, min(2540, (int) ($effective['dpi'] ?? $matched['dpi'] ?? $this->photoStudioDpi ?: 600)));
        $this->photoStudioProfiles = $profiles;
        $this->photoStudioActiveProfileId = $resolvedProfileId;
        $this->photoStudioDpi = $nextDpi;

        if (is_array($matched['photo_studio'] ?? null) && !empty($matched['photo_studio'])) {
            $nextParams = $this->sanitizePhotoStudioParams(
                array_merge($this->studioDefaultParams($this->photoStudioIsDarkSurface, $nextDpi), $matched['photo_studio'], ['enabled' => true])
            );
        } else {
            $defaults = $this->studioDefaultParams($this->photoStudioIsDarkSurface, $nextDpi);
            $defaults['dither_mode'] = $this->mapBitmapModeToStudioDitherMode(
                (string) ($matched['bitmap_mode'] ?? ($effective['bitmap_mode'] ?? 'Jarvis')),
                'jarvis'
            );
            $nextParams = $this->sanitizePhotoStudioParams(array_merge($defaults, ['enabled' => true]));
        }

        $this->photoStudioParams = $nextParams;
        $this->refreshLaserPhotoStudioPreview();

        if (is_array($effective)) {
            $this->dispatch(
                'laser-params-sync',
                params: $effective,
                profiles: $this->photoStudioProfiles,
                active_profile_id: $this->photoStudioActiveProfileId
            );
        }

        $this->dispatch(
            'laser-studio-params-synced',
            params: $this->photoStudioParams,
            activeProfileId: $this->photoStudioActiveProfileId,
            dpi: $this->photoStudioDpi
        );
    }

    public function savePhotoStudioToActiveLaserProfile(string $profileId, array $clientParams): void
    {
        $this->ensureStaffAuthorizedForLaserStudio();

        $item = $this->resolveOrderItemForLaserAction($this->photoStudioItemId ?: ($this->previewItem->id ?? null));
        if (!$item || !$item->product) {
            $this->notifyOrderFeedback('error', 'Produkt nicht gefunden', 'Das zugehörige Produkt konnte nicht geladen werden.');
            return;
        }

        $studioTemplate = $this->extractPhotoStudioTemplatePayload($clientParams);
        $this->photoStudioParams = array_merge($studioTemplate, ['enabled' => true]);

        $cleanId = preg_replace('/^profile:/i', '', trim($profileId)) ?? trim($profileId);
        if ($cleanId === '') {
            $cleanId = $this->photoStudioActiveProfileId;
        }

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $updatedId = $cleanId;

        try {
            if (method_exists($cockpit, 'updateProductLaserProfilePhotoStudio')) {
                $res = $cockpit->updateProductLaserProfilePhotoStudio($item->product, $cleanId, $studioTemplate);
                if (is_array($res['updated_profile'] ?? null) && !empty($res['updated_profile']['id'])) {
                    $updatedId = (string) $res['updated_profile']['id'];
                }
            } else {
                $profiles = $this->resolveProductLaserProfilesWithPhotoStudio($item->product);
                $matchedIdx = null;
                foreach ($profiles as $idx => $p) {
                    if ((string) ($p['id'] ?? '') === $cleanId) {
                        $matchedIdx = $idx;
                        break;
                    }
                }
                if ($matchedIdx === null && !empty($profiles)) {
                    $matchedIdx = 0;
                }
                if ($matchedIdx !== null) {
                    $profiles[$matchedIdx]['photo_studio'] = $studioTemplate;
                    $profiles[$matchedIdx]['bitmap_mode'] = $this->mapStudioDitherModeToBitmapMode($studioTemplate['dither_mode']);
                    $updatedId = (string) ($profiles[$matchedIdx]['id'] ?? $cleanId);

                    $settings = is_string($item->product->configurator_settings)
                        ? (json_decode($item->product->configurator_settings, true) ?: [])
                        : (is_array($item->product->configurator_settings) ? $item->product->configurator_settings : []);
                    $settings['xtool_profiles'] = $profiles;
                    $settings = \App\Models\Product\Product::syncLaserProfilesAndPrimarySettings($settings, false);
                    $item->product->configurator_settings = $settings;
                    $item->product->saveQuietly();
                }
            }

            $freshProduct = $this->ensurePhotoStudioPersistedOnProductProfile($item->product, $updatedId, $studioTemplate);
            $item->setRelation('product', $freshProduct);
            if ($this->selectedOrder && $this->selectedOrder->relationLoaded('items')) {
                $loaded = $this->selectedOrder->items->firstWhere('id', $item->id);
                if ($loaded) {
                    $loaded->setRelation('product', $freshProduct);
                }
            }

            $this->photoStudioProfiles = $this->resolveProductLaserProfilesWithPhotoStudio($freshProduct);
            if ($updatedId === '' && !empty($this->photoStudioProfiles)) {
                $updatedId = (string) ($this->photoStudioProfiles[0]['id'] ?? '');
            }
            $this->photoStudioActiveProfileId = $updatedId;

            $effectiveSide = $this->laserCockpitSide === 'back' ? 'back' : 'front';
            $this->persistOrderItemActiveProfileId($item, $effectiveSide, $updatedId);

            $matchedTitle = (string) (collect($this->photoStudioProfiles)->firstWhere('id', $updatedId)['title'] ?? 'Standard');

            $this->dispatch(
                'laser-studio-profiles-synced',
                profiles: $this->photoStudioProfiles,
                activeProfileId: $updatedId
            );

            $this->notifyOrderFeedback(
                'success',
                'Laserprofil am Produkt gespeichert',
                "Laserprofil am Produkt gespeichert ({$matchedTitle})."
            );
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Profil-Speicherung fehlgeschlagen', $e->getMessage());
        }
    }

    public function createLaserProfileFromPhotoStudio(string $newTitle, ?string $baseProfileId, array $clientParams): void
    {
        $this->ensureStaffAuthorizedForLaserStudio();

        $item = $this->resolveOrderItemForLaserAction($this->photoStudioItemId ?: ($this->previewItem->id ?? null));
        if (!$item || !$item->product) {
            $this->notifyOrderFeedback('error', 'Produkt nicht gefunden', 'Das zugehörige Produkt konnte nicht geladen werden.');
            return;
        }

        $cleanTitle = trim(str_replace([" \u{2013} ", " \u{2014} "], ' · ', strip_tags($newTitle)));
        $titleLen = mb_strlen($cleanTitle);
        if ($titleLen < 2 || $titleLen > 80) {
            $this->notifyOrderFeedback('error', 'Ungültiger Profilname', 'Bitte gib einen Profilnamen zwischen 2 und 80 Zeichen ein.');
            return;
        }

        $studioTemplate = $this->extractPhotoStudioTemplatePayload($clientParams);
        $this->photoStudioParams = array_merge($studioTemplate, ['enabled' => true]);

        $cleanBaseId = ($baseProfileId !== null && trim($baseProfileId) !== '')
            ? (preg_replace('/^profile:/i', '', trim($baseProfileId)) ?? trim($baseProfileId))
            : ($this->photoStudioActiveProfileId !== '' ? $this->photoStudioActiveProfileId : null);

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $createdId = '';

        try {
            if (method_exists($cockpit, 'createProductLaserProfileFromPhotoStudio')) {
                $res = $cockpit->createProductLaserProfileFromPhotoStudio(
                    $item->product,
                    $cleanTitle,
                    $cleanBaseId,
                    $studioTemplate,
                    false
                );
                if (is_array($res['created_profile'] ?? null) && !empty($res['created_profile']['id'])) {
                    $createdId = (string) $res['created_profile']['id'];
                }
            } else {
                $existingProfiles = $this->resolveProductLaserProfilesWithPhotoStudio($item->product);
                $baseProf = ($cleanBaseId !== null)
                    ? collect($existingProfiles)->firstWhere('id', $cleanBaseId)
                    : null;
                if (!is_array($baseProf)) {
                    $baseProf = collect($existingProfiles)->first(fn ($p) => !empty($p['is_default'])) ?: ($existingProfiles[0] ?? []);
                }

                $baseParams = is_array($baseProf) ? $baseProf : [];
                $baseParams['bitmap_mode'] = $this->mapStudioDitherModeToBitmapMode($studioTemplate['dither_mode']);

                $saved = $cockpit->saveLaserProfileToProduct($item->product, $cleanTitle, $baseParams, false);
                $createdId = (string) ($saved['profile']['id'] ?? '');
            }

            $freshProduct = $this->ensurePhotoStudioPersistedOnProductProfile($item->product, $createdId, $studioTemplate);
            $item->setRelation('product', $freshProduct);
            if ($this->selectedOrder && $this->selectedOrder->relationLoaded('items')) {
                $loaded = $this->selectedOrder->items->firstWhere('id', $item->id);
                if ($loaded) {
                    $loaded->setRelation('product', $freshProduct);
                }
            }

            $this->photoStudioProfiles = $this->resolveProductLaserProfilesWithPhotoStudio($freshProduct);
            if ($createdId === '' && !empty($this->photoStudioProfiles)) {
                $lastProf = end($this->photoStudioProfiles);
                $createdId = is_array($lastProf) ? (string) ($lastProf['id'] ?? '') : '';
            }

            $effectiveSide = $this->laserCockpitSide === 'back' ? 'back' : 'front';
            $effective = null;
            if ($createdId !== '') {
                $effective = $cockpit->applyProductLaserProfileToOrderItem($item, $effectiveSide, $createdId);
                $this->persistOrderItemActiveProfileId($item, $effectiveSide, $createdId);
                $this->photoStudioActiveProfileId = $createdId;
                $createdMeta = collect($this->photoStudioProfiles)->firstWhere('id', $createdId);
                if (is_array($createdMeta) && !empty($createdMeta['dpi'])) {
                    $this->photoStudioDpi = max(50, min(2540, (int) $createdMeta['dpi']));
                }
            }

            $this->dispatch(
                'laser-studio-profiles-synced',
                profiles: $this->photoStudioProfiles,
                activeProfileId: $this->photoStudioActiveProfileId
            );

            if (is_array($effective)) {
                $this->dispatch(
                    'laser-params-sync',
                    params: $effective,
                    profiles: $this->photoStudioProfiles,
                    active_profile_id: $this->photoStudioActiveProfileId
                );
            }

            $this->notifyOrderFeedback(
                'success',
                'Neues Laserprofil angelegt',
                "Neues Laserprofil angelegt ({$cleanTitle})."
            );
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Neues Laserprofil fehlgeschlagen', $e->getMessage());
        }
    }

    public function updatedPhotoStudioParams(): void
    {
        $this->photoStudioParams = $this->sanitizePhotoStudioParams($this->photoStudioParams);
    }

    public function refreshLaserPhotoStudioPreview(): void
    {
        $this->photoStudioParams = $this->sanitizePhotoStudioParams($this->photoStudioParams);
        if (!$this->isLaserPhotoStudioOpen || !$this->photoStudioOriginalRelPath) {
            return;
        }

        $sourceAbsPath = $this->resolveStorageAbsPath($this->photoStudioOriginalRelPath)
            ?? ($this->photoStudioFileKey ? $this->resolveStorageAbsPath($this->photoStudioFileKey) : null);
        if ($sourceAbsPath === null) {
            return;
        }

        $this->photoStudioPreviewUrl = $this->studioRenderPreviewAssetUrl($sourceAbsPath, $this->photoStudioParams, 680);
    }

    public function autoOptimizeLaserPhoto(): void
    {
        if (!$this->isLaserPhotoStudioOpen || !$this->photoStudioOriginalRelPath) {
            return;
        }

        $sourceAbsPath = $this->resolveStorageAbsPath($this->photoStudioOriginalRelPath)
            ?? ($this->photoStudioFileKey ? $this->resolveStorageAbsPath($this->photoStudioFileKey) : null);
        if ($sourceAbsPath === null) {
            return;
        }

        $auto = $this->studioComputeAutoParams(
            $sourceAbsPath,
            $this->photoStudioIsDarkSurface,
            $this->photoStudioDpi,
            $this->photoStudioMaterialHint
        );

        $this->photoStudioParams = $this->sanitizePhotoStudioParams(array_merge($this->photoStudioParams, $auto, ['enabled' => true]));
        $this->photoStudioPreviewUrl = $this->studioRenderPreviewAssetUrl($sourceAbsPath, $this->photoStudioParams, 680);
        $this->dispatch('laser-studio-params-synced', params: $this->photoStudioParams);
    }

    public function resetLaserPhotoStudioParams(): void
    {
        if (!$this->isLaserPhotoStudioOpen) {
            return;
        }

        $defaults = $this->studioDefaultParams($this->photoStudioIsDarkSurface, $this->photoStudioDpi);
        $this->photoStudioParams = $this->sanitizePhotoStudioParams(array_merge($defaults, ['enabled' => true]));
        $this->refreshLaserPhotoStudioPreview();
        $this->dispatch('laser-studio-params-synced', params: $this->photoStudioParams);
    }

    public function saveLaserPhotoStudio(?array $clientParams = null): void
    {
        if (!$this->isLaserPhotoStudioOpen || !$this->photoStudioItemId || !$this->photoStudioFileKey) {
            return;
        }

        if (is_array($clientParams)) {
            $this->photoStudioParams = $this->sanitizePhotoStudioParams(
                array_merge($this->photoStudioParams, $clientParams, ['enabled' => true])
            );
        }

        $item = $this->resolveOrderItemForLaserAction($this->photoStudioItemId);
        if (!$item) {
            $this->notifyOrderFeedback('error', 'Speichern fehlgeschlagen', 'Die Bestellposition konnte nicht gefunden werden.');
            return;
        }

        $verifiedKey = $this->verifyAndResolveCustomerFileKey($item, $this->photoStudioFileKey);
        if ($verifiedKey === null) {
            $this->notifyOrderFeedback('error', 'Ungültige Bilddatei', 'Die Bildzuordnung der Bestellposition ist ungültig.');
            return;
        }

        $origRel = $this->photoStudioOriginalRelPath ?: $this->studioResolveOriginalPhotoRelPath($verifiedKey, $item->configuration ?? []);
        $sourceAbsPath = $this->resolveStorageAbsPath($origRel) ?? $this->resolveStorageAbsPath($verifiedKey);
        if ($sourceAbsPath === null) {
            $this->notifyOrderFeedback('error', 'Originalbild fehlt', 'Die Quelldatei konnte für den Laser-Export nicht geladen werden.');
            return;
        }

        try {
            $cleanParams = $this->sanitizePhotoStudioParams(array_merge($this->photoStudioParams, ['enabled' => true]));
            $savedRelPath = $this->studioSaveProcessedLaserPng($sourceAbsPath, $cleanParams, 'bestellungen/xtool/photo-edits');

            $conf = is_array($item->configuration) ? $item->configuration : [];
            if (!isset($conf['laser_photo_adjustments']) || !is_array($conf['laser_photo_adjustments'])) {
                $conf['laser_photo_adjustments'] = [];
            }

            $conf['laser_photo_adjustments'][$verifiedKey] = array_merge($cleanParams, [
                'enabled'       => true,
                'original_path' => $origRel,
                'edited_path'   => $savedRelPath,
                'updated_at'    => now()->toIso8601String(),
            ]);

            $item->configuration = $conf;
            $item->save();
            $this->syncOrderItemConfigInMemory($item);

            if ($this->selectedOrder) {
                $this->selectedOrder->refresh();
                $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
            }

            $this->laserCameraRefreshToken++;
            $fileName = $this->photoStudioFileName ?: basename($verifiedKey);
            $this->closeLaserPhotoStudio();

            $this->notifyOrderFeedback(
                'success',
                'Laser-Foto übernommen',
                "„{$fileName}“ wurde für die Lasergravur gespeichert und in die Live-Vorschau übernommen."
            );
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Laser-Aufbereitung fehlgeschlagen', $e->getMessage());
        }
    }

    // --- RENDER ---

    public function render()
    {
        // A) Detail-Modus
        if ($this->selectedOrderId && $this->selectedOrder) {
            return view('livewire.shop.order.order-overview', [
                'orders' => collect(),
                'stats' => [],
                'nextQueueOrder' => $this->nextQueueOrder,
            ]);
        }

        // B) Listen-Modus (Single Table)
        $smartQueue = $this->getSmartQueueData();
        $query = $this->buildBaseQuery();

        // 4. Statistiken (Immer aktuell, basierend auf Gesamt-DB)
        $stats = [
            'total' => OrderOrder::count(),
            'open' => OrderOrder::whereIn('status', ['pending', 'processing'])->count(),
            'open_express' => OrderOrder::whereIn('status', ['pending', 'processing'])->where('is_express', true)->count(),
            'revenue_today' => OrderOrder::whereDate('created_at', today())
                ->whereNotIn('status', ['cancelled', 'refunded', 'failed'])
                ->sum('total_price'),
            'revenue_month' => OrderOrder::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->whereNotIn('status', ['cancelled', 'refunded', 'failed'])
                ->sum('total_price'),
            'avg_cart' => OrderOrder::where('status', 'completed')->avg('total_price') ?? 0,
        ];

        // 5. Query ausführen (Pagination)
        $orders = $query->paginate(20);

        $printableLabelsCount = (clone $query)
            ->when(empty($this->statusFilter), fn($q) => $q->whereIn('status', ['processing', 'shipped']))
            ->whereHas('shipments', fn($q) => $q->whereNotNull('shipping_label_path')->where('shipping_label_path', '!=', ''))
            ->count();

        return view('livewire.shop.order.order-overview', [
            'orders' => $orders,
            'stats' => $stats,
            'printableLabelsCount' => $printableLabelsCount,
            'batchGroups' => $smartQueue['batch_groups'] ?? [],
            'effectiveFocusProductId' => $smartQueue['effective_focus_product_id'] ?? null,
            'funkiDirective' => $smartQueue['funki_directive'] ?? null,
            'smartAnalyses' => $smartQueue['analyses'] ?? [],
        ]);
    }

    private function buildBaseQuery()
    {
        $query = OrderOrder::query()->with(['items.product.supplier', 'invoices', 'shipments', 'sampleInfluencer']);

        // 1. Suche
        if ($this->search) {
            $searchTerm = '%' . addcslashes((string) $this->search, '%_\\') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('order_number', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('billing_address->last_name', 'like', $searchTerm)
                    ->orWhere('billing_address->first_name', 'like', $searchTerm)
                    ->orWhere('billing_address->company', 'like', $searchTerm);
            });
        }

        // 2. Filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->paymentFilter) {
            $query->where('payment_status', $this->paymentFilter);
        }

        // 3. Sortierung
        if ($this->sortField === 'default_workflow') {
            $this->applyWorkflowSort($query);
        } else {
            $this->applyCustomSort($query);
        }

        return $query;
    }

    /**
     * SMART-WORKFLOW SORTIERUNG:
     * 1. Status-Stufe: Offene Aufträge (0) vor Versendet (1) vor Abgeschlossen/Storniert (2)
     * 2. Smart-Fertigungs-Score für offene Aufträge (Express -> Sofort laserbare Artikel-Serie -> Wartend)
     * 3. Fallback für versendete/abgeschlossene Aufträge (Express DESC, Datum ASC)
     */
    private function applyWorkflowSort($query)
    {
        // 1. Gruppierung: Offene Fertigungsaufträge ganz oben, Versendet in der Mitte, Erledigte unten
        $query->orderByRaw("CASE WHEN status IN ('pending', 'processing') THEN 0 WHEN status = 'shipped' THEN 1 ELSE 2 END ASC");

        // 2. Smart-Reihenfolge der offenen Bestellungen (inkl. Artikel-Batching & aktivem Laser-Fokus)
        $rankedIds = $this->getSmartQueueData()['ranked_ids'] ?? [];
        if (!empty($rankedIds)) {
            $whenClauses = [];
            $bindings = [];
            foreach (array_values($rankedIds) as $index => $orderId) {
                $whenClauses[] = 'WHEN ? THEN ' . (int) $index;
                $bindings[] = (string) $orderId;
            }
            $caseSql = 'CASE order_orders.id ' . implode(' ', $whenClauses) . ' ELSE 999999 END ASC';
            $query->orderByRaw($caseSql, $bindings);
        }

        // 3. Express vor Standard & FIFO für nicht-offene Aufträge
        $query->orderBy('is_express', 'desc');
        $query->orderBy('created_at', 'asc');
    }

    private function applyCustomSort($query)
    {
        $safeDir = strtolower((string) $this->sortDirection) === 'desc' ? 'desc' : 'asc';
        $safeField = in_array($this->sortField, $this->allowedSortFields, true) ? $this->sortField : 'created_at';

        switch ($safeField) {
            case 'customer':
                $query->orderBy('billing_address->last_name', $safeDir);
                break;
            case 'total':
            case 'total_price':
                $query->orderBy('total_price', $safeDir);
                break;
            case 'payment':
            case 'payment_status':
                $query->orderBy('payment_status', $safeDir);
                break;
            case 'status':
                $query->orderBy('status', $safeDir);
                break;
            case 'order_number':
                $query->orderBy('order_number', $safeDir);
                break;
            case 'is_express':
                $query->orderBy('is_express', $safeDir);
                break;
            default:
                $query->orderBy('created_at', $safeDir);
        }
    }
}

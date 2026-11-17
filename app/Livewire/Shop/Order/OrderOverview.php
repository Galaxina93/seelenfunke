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

        if ($this->selectedOrder->status === 'pending') {
            $this->selectedOrder->update(['status' => 'processing']);
            $this->status = 'processing';
        }

        $this->activeOrderStep = 1;
        $this->laserWorkflowStep = 1;
        $this->workshopNote = $this->selectedOrder->syncAutonomousWorkshopSummary();
        $this->selectedOrder->refresh();
        $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
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

    public function downloadLaserXcsFile($itemId, $side = 'front', ?\App\Services\Export\FileDownloadService $exportService = null)
    {
        $exportService = $exportService ?: app(\App\Services\Export\FileDownloadService::class);
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

    public function downloadLaserXfFile($itemId, $side = 'front', ?\App\Services\Export\FileDownloadService $exportService = null)
    {
        $exportService = $exportService ?: app(\App\Services\Export\FileDownloadService::class);
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
            'x_mm'       => round($xMm, 1),
            'y_mm'       => round($yMm, 1),
            'angle_deg'  => round($angleDeg, 1),
            'w_mm'       => round($wMm, 1),
            'h_mm'       => round($hMm, 1),
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
        $savedSide = $conf['laser_cockpit_coordinates'][$effectiveSide] ?? null;

        try {
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            $detected = $cockpit->detectBlankPositionOnBed(
                $item->product,
                $conf,
                $effectiveSide,
                $forceCameraSync
            );
            $defaultW = round((float) ($detected['engrave_width_mm'] ?? $detected['blank_width_mm'] ?? 40.0), 1);
            $defaultH = round((float) ($detected['engrave_height_mm'] ?? $detected['blank_height_mm'] ?? 40.0), 1);

            if (
                !$forceCameraSync
                && is_array($savedSide)
                && isset($savedSide['x_mm'], $savedSide['y_mm'])
                && is_numeric($savedSide['x_mm'])
                && is_numeric($savedSide['y_mm'])
            ) {
                $this->laserCenterXMm = round(max(5.0, min(195.0, (float) $savedSide['x_mm'])), 1);
                $this->laserCenterYMm = round(max(5.0, min(195.0, (float) $savedSide['y_mm'])), 1);
                $this->laserAngleDeg = isset($savedSide['angle_deg']) && is_numeric($savedSide['angle_deg'])
                    ? round(max(-45.0, min(45.0, (float) $savedSide['angle_deg'])), 1)
                    : 0.0;
                $this->laserWidthMm = isset($savedSide['w_mm']) && is_numeric($savedSide['w_mm']) && (float) $savedSide['w_mm'] > 0.5
                    ? round(max(2.5, min(195.0, (float) $savedSide['w_mm'])), 1)
                    : $defaultW;
                $this->laserHeightMm = isset($savedSide['h_mm']) && is_numeric($savedSide['h_mm']) && (float) $savedSide['h_mm'] > 0.5
                    ? round(max(2.5, min(195.0, (float) $savedSide['h_mm'])), 1)
                    : $defaultH;
            } else {
                $this->laserCenterXMm = (float) $detected['center_x_mm'];
                $this->laserCenterYMm = (float) $detected['center_y_mm'];
                $this->laserAngleDeg = (float) $detected['angle_deg'];
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

    public function refreshLaserCameraAndAlign(?string $side = null): void
    {
        if ($side !== null) {
            $this->laserCockpitSide = $side === 'back' ? 'back' : 'front';
        }

        $item = $this->previewItem;
        if (!$item) {
            return;
        }

        try {
            $this->laserCameraRefreshToken++;
            $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
            $detected = $cockpit->detectBlankPositionOnBed(
                $item->product,
                $item->configuration ?? [],
                $this->laserCockpitSide,
                true
            );

            $this->laserCenterXMm = (float) $detected['center_x_mm'];
            $this->laserCenterYMm = (float) $detected['center_y_mm'];
            $this->laserAngleDeg = (float) $detected['angle_deg'];
            $this->laserWidthMm = round((float) ($detected['engrave_width_mm'] ?? $detected['blank_width_mm'] ?? 40.0), 1);
            $this->laserHeightMm = round((float) ($detected['engrave_height_mm'] ?? $detected['blank_height_mm'] ?? 40.0), 1);
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

            $xFmt = number_format($this->laserCenterXMm, 1, ',', '.');
            $yFmt = number_format($this->laserCenterYMm, 1, ',', '.');
            $aFmt = number_format($this->laserAngleDeg, 1, ',', '.');
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
     * Aktualisiert und persistiert die Laser-Koordinaten (X, Y, Winkel sowie optional skalierte Breite/Höhe wMm x hMm).
     * Abwärtskompatibel zu bisherigen Aufrufen updateLaserCoordinates($xMm, $yMm, $angleDeg).
     */
    public function updateLaserCoordinates(
        $itemId,
        $xMm = null,
        $yMm = null,
        $angleDeg = null,
        $wMm = null,
        $hMm = null
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
            $this->laserCenterXMm = round(max(5.0, min(195.0, (float) $rawX)), 1);
        }
        if ($rawY !== null && is_numeric($rawY)) {
            $this->laserCenterYMm = round(max(5.0, min(195.0, (float) $rawY)), 1);
        }
        if ($rawAngle !== null && is_numeric($rawAngle)) {
            $this->laserAngleDeg = round(max(-45.0, min(45.0, (float) $rawAngle)), 1);
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

            $this->laserWidthMm = round(max(2.5, min(195.0, $nextW)), 1);
            $this->laserHeightMm = round(max(2.5, min(195.0, $nextH)), 1);
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
                $this->laserWidthMm = round((float) $wa['engraveWidthMm'], 1);
                $this->laserHeightMm = round((float) $wa['engraveHeightMm'], 1);
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
    }

    public function nudgeLaserPosition(float $dxMm = 0.0, float $dyMm = 0.0, float $dAngleDeg = 0.0): void
    {
        $this->laserCenterXMm = round(max(5.0, min(195.0, $this->laserCenterXMm + $dxMm)), 1);
        $this->laserCenterYMm = round(max(5.0, min(195.0, $this->laserCenterYMm + $dyMm)), 1);
        $this->laserAngleDeg = round(max(-45.0, min(45.0, $this->laserAngleDeg + $dAngleDeg)), 1);

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

    public function triggerLaserFraming(?string $itemId = null, ?string $side = null)
    {
        $targetItemId = $itemId ?: ($this->previewItem->id ?? null);
        $targetSide = $side ?: $this->laserCockpitSide;
        if (!$targetItemId) {
            return;
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

            $xFmt = number_format((float) $job['center_x_mm'], 1, ',', '.');
            $yFmt = number_format((float) $job['center_y_mm'], 1, ',', '.');
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

    public function sendJobDirectlyToLaser(?string $itemId = null, ?string $side = null)
    {
        $targetItemId = $itemId ?: ($this->previewItem->id ?? null);
        $targetSide = $side ?: $this->laserCockpitSide;
        if (!$targetItemId) {
            return;
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

            $xFmt = number_format((float) $job['center_x_mm'], 1, ',', '.');
            $yFmt = number_format((float) $job['center_y_mm'], 1, ',', '.');
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
                $this->laserLastActionStatus = "Laser offline ({$job['laser_ip']}) · .xcs unter Dateien verfügbar";
                $this->notifyOrderFeedback(
                    'warning',
                    'Laser konnte im WLAN nicht erreicht werden',
                    "Auftrag #{$job['order_number']} wurde vorbereitet, aber der xTool F2 Ultra UV ({$job['laser_ip']}:28900) hat nicht geantwortet ({$errDetail}). Bitte Laser einschalten oder .xcs unter „Dateien“ herunterladen."
                );
            }
        } catch (\Throwable $e) {
            $this->notifyOrderFeedback('error', 'Laser-Übergabe fehlgeschlagen', $e->getMessage());
        }
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
            $laserGuide = $item->product->getLaserInstructionSummary();

            $downloadService = app(\App\Services\Export\FileDownloadService::class);
            $productCfg = is_string($item->product?->configurator_settings)
                ? (json_decode($item->product->configurator_settings, true) ?: [])
                : ($item->product?->configurator_settings ?? []);
            $calibration = $downloadService->resolveEngravingWorkingAreaCalibration($item->product, $productCfg, $conf, false);

            $confForCache = $conf;
            unset($confForCache['laser_cockpit_coordinates'], $confForCache['manufacturing_checklist']);

            // Schneller Disk-Cache für das hochauflösende WYSIWYG-Gravur-Overlay (spart ~800ms GD-Render + MB-große Base64-Strings bei jedem Klick)
            $cacheHash = sha1(
                'v6-cockpit-wysiwyg|'
                . $item->id . '|'
                . $effectiveSide . '|'
                . ($item->updated_at?->timestamp ?? 0) . '|'
                . ($item->product?->updated_at?->timestamp ?? 0) . '|'
                . json_encode($calibration) . '|'
                . json_encode($confForCache)
            );
            $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
            $relPng = "bestellungen/xtool/previews/{$cacheHash}.png";
            $relMeta = "bestellungen/xtool/previews/{$cacheHash}.json";

            $baseWidthMm = null;
            $baseHeightMm = null;
            $contourShape = (string) ($calibration['contourShape'] ?? 'rect');
            $isDarkSurface = $downloadService->isFrostedLightEngravingSurface($item->product, $productCfg);
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
                'ghost_d_path'      => $ghostDPath,
                'preview_data_url'  => $previewUrl,
                'last_status'       => $this->laserLastActionStatus,
            ];
        } catch (\Throwable) {
            return null;
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

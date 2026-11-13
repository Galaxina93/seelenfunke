<?php

namespace App\Livewire\Shop\Order;

use Livewire\Attributes\Layout;

use App\Mail\NewOrderShippedToCustomer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
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

        // Order laden (Eager Loading für Performance)
        $this->selectedOrder = OrderOrder::with(['items.product.supplier', 'invoices', 'shipments'])->find($id);

        // Daten für das Bearbeitungs-Formular laden
        if ($this->selectedOrder) {
            $this->status = $this->selectedOrder->status;
            $this->payment_status = $this->selectedOrder->payment_status;
            $this->notes = $this->selectedOrder->notes;
            $this->cancellationReason = $this->selectedOrder->cancellation_reason ?? '';

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
            }
        }
    }

    public function closeDetail()
    {
        $this->selectedOrderId = null;
        $this->selectedOrderItemId = null;
        $this->selectedOrder = null;
        $this->reset(['status', 'payment_status', 'notes', 'cancellationReason']);
    }

    public function selectItemForPreview($itemId)
    {
        $this->selectedOrderItemId = $itemId;
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
            $item->update([
                'is_completed' => $newStatus,
                'completed_quantity' => $newStatus ? $item->quantity : 0
            ]);
            if ($newStatus && !empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                $this->rememberActiveBatchProduct((string) $item->product_id);
            } else {
                $this->invalidateSmartQueueCache();
            }
            $this->selectedOrder->refresh();
            $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
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
                if (!empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                    $this->rememberActiveBatchProduct((string) $item->product_id);
                } else {
                    $this->invalidateSmartQueueCache();
                }
                $this->selectedOrder->refresh();
                $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
            }
        }
    }

    public function setAllCompletedQuantity($itemId)
    {
        $item = OrderOrderItem::with('product')->find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            $item->update([
                'completed_quantity' => $item->quantity,
                'is_completed' => true
            ]);
            if (!empty($item->product_id) && ($item->product?->type ?? 'physical') !== 'digital') {
                $this->rememberActiveBatchProduct((string) $item->product_id);
            } else {
                $this->invalidateSmartQueueCache();
            }
            $this->selectedOrder->refresh();
            $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
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
                $this->invalidateSmartQueueCache();
                $this->selectedOrder->refresh();
                $this->selectedOrder->load(['items.product.supplier', 'invoices', 'shipments']);
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

                    if ($sendMail) {
                        Mail::to($order->email)->send(new NewOrderShippedToCustomer($order->toFormattedArray()));
                        session()->flash('success', 'Status geändert & Mail versendet! 🚀');
                    } else {
                        session()->flash('success', 'Status auf Versendet gesetzt.');
                    }

                    if ($this->selectedOrder && $this->selectedOrder->id == $order->id) {
                        $this->status = 'shipped';
                        $this->selectedOrder->refresh();
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
            $this->invalidateSmartQueueCache();

            if ($this->selectedOrder && $this->selectedOrder->id == $this->dhlModalOrderId) {
                $this->status = 'shipped';
                $this->selectedOrder->refresh();
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
                "Bestellung #{$order->order_number} enthält ausschließlich digitale Produkte – es ist kein physisches Versandlabel erforderlich."
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

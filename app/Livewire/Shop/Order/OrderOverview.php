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

    // Standard: Custom Workflow (Offene oben, Express oben, Erledigte unten)
    public $sortField = 'default_workflow';
    public $sortDirection = 'asc';

    // --- STATE FÜR DETAIL-ANSICHT ---
    public $selectedOrderId = null;
    public $selectedOrderItemId = null;

    // --- STATE FÜR BEARBEITUNG ---
    public ?OrderOrder $selectedOrder = null;

    // Formular-Felder
    public $status;
    public $payment_status;
    public $notes;
    public $cancellationReason = '';

    // STATUS FÜR DAS SICHERHEITS-MODAL
    public $confirmingShipmentId = null;

    // FOTO-MANGEL MODAL STATE
    public $isPhotoIssueModalOpen = false;
    public $photoIssueItemId = null;
    public $photoIssueFilePath = null;
    public $photoIssueFileName = null;
    public $photoIssueProductName = null;
    public $photoIssueReasonKey = 'resolution';
    public $photoIssueCustomNote = '';
    public $photoIssueSetPending = true;
    public $photoIssueSendMail = true;

    // DHL MODAL STATE
    public $dhlModalOrderId = null;
    public $dhlPackageCount = 1;
    public $dhlWeightPerPackage = 1.0;
    public $dhlError = null;
    public array $dhlWeightDetails = [];
    public ?float $dhlRecommendedWeight = null;
    public string $dhlPackagingType = 'box';

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
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check()) {
            abort(403, 'Nicht autorisierter Zugriff auf die Bestellübersicht.');
        }
    }

    // Reset aller Filter auf Standard
    public function resetFilters()
    {
        $this->reset(['search', 'statusFilter', 'paymentFilter', 'sortField', 'sortDirection']);
        $this->resetPage();
    }

    // --- COMPUTED: PRIORITÄTS-ORDER (Für Funki Header) ---
    public function getPriorityOrderProperty()
    {
        // Holt genau EINE Order, die am wichtigsten ist
        return OrderOrder::query()
            ->whereIn('status', ['pending', 'processing']) // Nur offene
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC")
            ->orderBy('is_express', 'desc') // Express zuerst

            ->orderBy('created_at', 'asc') // Älteste zuerst (FIFO)
            ->first();
    }

    public function getPriorityOrderTipProperty()
    {
        $prio = $this->priority_order;
        if (!$prio) return '';

        $missingItems = [];
        $isOnlyStandard = true;

        foreach ($prio->items as $item) {
            if ($item->product) {
                if ($item->product->isPersonalizable()) {
                    $isOnlyStandard = false;
                }
                
                if ($prio->is_express && $item->product->track_quantity) {
                    if ($item->quantity > $item->product->quantity && !$item->product->continue_selling_when_out_of_stock) {
                        $missingItems[] = $item->product->name;
                    }
                }
            }
        }

        $standardMessage = '';
        if ($prio->isOnlyDigital()) {
            $standardMessage = '<div class="mt-3 bg-blue-950 p-3 rounded-xl border border-blue-800 shadow-inner"><span class="text-blue-400 font-black text-sm tracking-widest block mb-1">⚡ DIGITALE BEREITSTELLUNG:</span> <span class="text-white font-bold block">Ausschließlich digitale Produkte! Die Auslieferung erfolgt vollautomatisch nach Zahlungseingang über das Kundenkonto. Keine physische Bearbeitung nötig.</span></div>';
        } elseif ($isOnlyStandard) {
            $standardMessage = '<div class="mt-3 bg-[var(--theme-color-10)] p-3 rounded-xl border border-[var(--theme-color-30)] shadow-inner"><span class="text-[var(--theme-color)] font-black text-sm tracking-widest block mb-1">⚡ SCHNELLE NUMMER:</span> <span class="text-white font-bold block">Ausschließlich Lagerware! Keine Personalisierung/Laser-Arbeit nötig. Einfach aus dem Regal nehmen, verpacken, Label drauf und ab die Post!</span></div>';
        }

        if ($prio->is_express) {
            if (count($missingItems) > 0) {
                return '<span class="text-red-400 font-bold">Lagerbestand Kritisch!</span> ' . count($missingItems) . ' Artikel für diesen Express-Versand fehlen physisch. Bitte sofort prüfen!' . $standardMessage;
            }

            return '<span class="text-emerald-400 font-bold">Lagerbestand gesichert!</span> Dieser Express-Versand ist komplett auf Lager und kann sofort abgewickelt werden.' . $standardMessage;
        }

        return 'Dies ist der älteste offene Auftrag im System. Arbeite ihn zügig ab, um die Wartezeiten gering zu halten.' . $standardMessage;
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
        $this->selectedOrder = OrderOrder::with(['items.product', 'invoices', 'shipments'])->find($id);

        // Daten für das Bearbeitungs-Formular laden
        if ($this->selectedOrder) {
            $this->status = $this->selectedOrder->status;
            $this->payment_status = $this->selectedOrder->payment_status;
            $this->notes = $this->selectedOrder->notes;
            $this->cancellationReason = $this->selectedOrder->cancellation_reason ?? '';

            if ($this->selectedOrder->items->isNotEmpty()) {
                $this->selectedOrderItemId = $this->selectedOrder->items->first()->id;
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

        // Sicherheits-Modal für Versand
        if ($this->status === 'shipped' && $this->selectedOrder->status !== 'shipped') {
            $this->confirmingShipmentId = $this->selectedOrder->id;
            return;
        }

        try {
            DB::transaction(function () {
                // Fall: Stornierung
                if ($this->status === 'cancelled') {
                    $this->validate([
                        'cancellationReason' => 'required|string|min:5|max:500',
                    ]);

                    // Führt geschäftslogische Rückabwicklung durch:
                    // 1. Lagerbestand: Nur für Standardware zurückbuchen, personalisierte Ware verfällt als Ausschuss
                    // 2. Herzensprojekt: Spendenbeitrag stornieren
                    // 3. Gekaufte Wertgutscheine: Deaktivieren
                    // 4. Eingelöste Gutscheine: Saldo wiederherstellen
                    // 5. Freunde-Werben: Prämie stornieren
                    $this->selectedOrder->handleCancellation($this->cancellationReason ?: 'Stornierung');

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
                    // Normales Update
                    $this->selectedOrder->update([
                        'status' => $this->status,
                        'payment_status' => $this->payment_status,
                        'notes' => $this->notes,
                        'cancellation_reason' => null
                    ]);

                    // Vollständige Rückabwicklung bei Erstattung
                    if ($this->status === 'refunded') {
                        $this->selectedOrder->handleCancellation('Erstattung');
                    }

                    $this->selectedOrder->refresh();
                    
                    // Wenn auf 'Bezahlt' gesetzt wurde und noch keine Rechnung existiert -> Automatisch Rechnung + E-Mail
                    if ($this->payment_status === 'paid' && $this->selectedOrder->invoices->isEmpty()) {
                        \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($this->selectedOrder);
                        session()->flash('success', 'Bestelldetails aktualisiert. Rechnung wird im Hintergrund generiert und per E-Mail versandt.');
                    } else {
                        session()->flash('success', 'Bestelldetails aktualisiert.');
                    }
                }
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
        $order = OrderOrder::find($orderId);
        if (!$order) return;

        if ($newStatus === 'shipped' && $order->status !== 'shipped') {
            $this->confirmingShipmentId = $orderId;
            return;
        }

        try {
            DB::transaction(function () use ($order, $orderId, $newStatus) {
                $order->update(['status' => $newStatus]);

                if ($newStatus === 'completed') {
                    $order->shipments()->where('status', '!=', 'delivered')->update(['status' => 'delivered']);
                }

                if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
                    $this->status = $newStatus;
                    $this->selectedOrder->refresh();
                }
                session()->flash('success', "Status aktualisiert.");
            });
        } catch (\Exception $e) {
            $this->logAdminOrderError('update_status', $e, ['order_id' => $orderId, 'new_status' => $newStatus]);
            session()->flash('error', 'Status konnte nicht aktualisiert werden.');
        }
    }

    public function toggleItemCompletion($itemId)
    {
        $item = OrderOrderItem::find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            $newStatus = !$item->is_completed;
            $item->update([
                'is_completed' => $newStatus,
                'completed_quantity' => $newStatus ? $item->quantity : 0
            ]);
            $this->selectedOrder->refresh();
        }
    }

    public function incrementCompletedQuantity($itemId)
    {
        $item = OrderOrderItem::find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            if ($item->completed_quantity < $item->quantity) {
                $newQty = $item->completed_quantity + 1;
                $item->update([
                    'completed_quantity' => $newQty,
                    'is_completed' => ($newQty >= $item->quantity)
                ]);
                $this->selectedOrder->refresh();
            }
        }
    }

    public function setAllCompletedQuantity($itemId)
    {
        $item = OrderOrderItem::find($itemId);
        if ($item && $this->selectedOrder && $item->order_id == $this->selectedOrder->id) {
            $item->update([
                'completed_quantity' => $item->quantity,
                'is_completed' => true
            ]);
            $this->selectedOrder->refresh();
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
                $this->selectedOrder->refresh();
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

            if ($this->selectedOrder) {
                $this->selectedOrder->refresh();
            }
            session()->flash('success', 'Foto-Mangel als erledigt markiert.');
        }
    }

    public function markAsPaid($orderId)
    {
        $order = OrderOrder::with('invoices')->find($orderId);
        if ($order) {
            try {
                DB::transaction(function () use ($order, $orderId) {
                    $order->update(['payment_status' => 'paid']);
                    
                    // Wenn die Bestellung noch keine Rechnung hat, generiere sie jetzt automatisch + E-Mail
                    if ($order->invoices->isEmpty()) {
                        \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);
                        session()->flash('success', 'Zahlung bestätigt. Rechnung wird im Hintergrund generiert und per E-Mail versandt.');
                    } else {
                        session()->flash('success', 'Zahlung bestätigt.');
                    }

                    if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
                        $this->payment_status = 'paid';
                        $this->selectedOrder->refresh();
                    }
                });
            } catch (\Exception $e) {
                $this->logAdminOrderError('mark_as_paid', $e, ['order_id' => $order->id]);
                session()->flash('error', 'Fehler beim Markieren als bezahlt: ' . $e->getMessage());
            }
        }
    }

    public function delete($orderId)
    {
        $order = OrderOrder::find($orderId);
        if ($order) {
            try {
                DB::transaction(function () use ($order) {
                    $order->delete();
                    $this->closeDetail();
                    session()->flash('success', 'Bestellung gelöscht.');
                });
            } catch (\Exception $e) {
                $this->logAdminOrderError('delete_order', $e, ['order_id' => $orderId]);
                session()->flash('error', 'Fehler beim Löschen der Bestellung.');
            }
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

        // Verpackungsart smart vorwählen: Falls es sich um kleine, leichte Schmuckstücke handelt -> envelope, sonst box
        $order = OrderOrder::with('items.product')->find($orderId);
        if ($order) {
            $initialDetails = $order->getDhlWeightDetails(1, 'box');
            $this->dhlPackagingType = $initialDetails['suggested_packaging_type'] ?? 'box';
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

            if ($this->selectedOrder && $this->selectedOrder->id == $this->dhlModalOrderId) {
                $this->selectedOrder->refresh();
            }
            session()->flash('success', "DHL Labels ($this->dhlPackageCount) erfolgreich generiert! 🚀");
            $this->closeDhlModal();

        } catch (\Exception $e) {
            $this->dhlError = $e->getMessage();
            $this->logAdminOrderError('generate_dhl_label', $e, ['order_id' => $this->dhlModalOrderId]);
        }
    }

    public function downloadDhlLabel($shipmentId)
    {
        $shipment = \App\Models\Order\OrderShipment::find($shipmentId);
        if ($shipment && $shipment->shipping_label_path) {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($shipment->shipping_label_path)) {
                return \Illuminate\Support\Facades\Storage::disk('local')->download($shipment->shipping_label_path);
            }
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($shipment->shipping_label_path)) {
                return \Illuminate\Support\Facades\Storage::disk('public')->download($shipment->shipping_label_path);
            }
        }
        session()->flash('error', 'Label nicht gefunden.');
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
                if ($shipment->shipping_label_path) {
                    $path = null;
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($shipment->shipping_label_path)) {
                        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($shipment->shipping_label_path);
                    } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($shipment->shipping_label_path)) {
                        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($shipment->shipping_label_path);
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
            session()->flash('error', 'Keine DHL Labels in der aktuellen Auswahl gefunden.');
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
        return OrderOrderItem::with('product')->find($this->selectedOrderItemId);
    }

    public function downloadLaserFile($itemId, $side, \App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadLaserSvg($itemId, $side);
    }

    // --- RENDER ---

    public function render()
    {
        // A) Detail-Modus
        if ($this->selectedOrderId && $this->selectedOrder) {
            return view('livewire.shop.order.order-overview', [
                'orders' => collect(),
                'stats' => []
            ]);
        }

        // B) Listen-Modus (Single Table)
        $query = $this->buildBaseQuery();

        // 4. Statistiken (Immer aktuell, basierend auf Gesamt-DB)
        $stats = [
            'total' => OrderOrder::count(),
            'open' => OrderOrder::whereIn('status', ['pending', 'processing'])->count(),
            'open_express' => OrderOrder::whereIn('status', ['pending', 'processing'])->where('is_express', true)->count(),
            'revenue_today' => OrderOrder::whereDate('created_at', today())->sum('total_price'),
            'revenue_month' => OrderOrder::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_price'),
            'avg_cart' => OrderOrder::where('status', 'completed')->avg('total_price') ?? 0,
        ];

        // 5. Query ausführen (Pagination)
        $orders = $query->paginate(20);

        return view('livewire.shop.order.order-overview', [
            'orders' => $orders,
            'stats' => $stats
        ]);
    }

    private function buildBaseQuery()
    {
        $query = OrderOrder::query();

        // 1. Suche
        if ($this->search) {
            $searchTerm = '%' . $this->search . '%';
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
     * WORKFLOW SORTIERUNG:
     * 1. Status: Offene zuerst (0), Erledigte unten (1)
     * 2. Typ: Express zuerst (DESC)
     * 3. Alter: Älteste Aufträge zuerst (FIFO, ASC)
     */
    private function applyWorkflowSort($query)
    {
        // 1. Gruppierung: Wichtiges oben (Pending/Processing/Shipped), Rest unten
        $query->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC");

        // 2. Express vor Standard
        $query->orderBy('is_express', 'desc');



        // 4. Datum: Älteste zuerst (FIFO)
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

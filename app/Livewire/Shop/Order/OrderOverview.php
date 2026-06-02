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

    // DHL MODAL STATE
    public $dhlModalOrderId = null;
    public $dhlPackageCount = 1;
    public $dhlWeightPerPackage = 1.0;
    public $dhlError = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'paymentFilter' => ['except' => ''],
        'sortField' => ['except' => 'default_workflow'],
        'sortDirection' => ['except' => 'asc']
    ];

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
                    // Bestand zurückbuchen, wenn die Order vorher nur "eingegangen" war
                    if ($this->selectedOrder->status !== 'cancelled' && $this->selectedOrder->status === 'pending') {
                        foreach ($this->selectedOrder->items as $item) {
                            if ($item->product) $item->product->restoreStock($item->quantity);
                        }
                        session()->flash('info', 'Bestand wurde zurückgebucht.');
                    }

                    $this->validate([
                        'cancellationReason' => 'required|string|min:5|max:500',
                    ]);

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
        
        $this->calculateDhlWeight();
    }

    public function updatedDhlPackageCount()
    {
        $this->calculateDhlWeight();
    }

    private function calculateDhlWeight()
    {
        if (!$this->dhlModalOrderId) return;
        
        $order = OrderOrder::with('items.product')->find($this->dhlModalOrderId);
        if (!$order) return;

        $existingLabelsCount = \App\Models\Order\OrderShipment::where('order_id', $order->id)->count();

        $totalProductWeightGrams = 0;
        $remainingProductWeightGrams = 0;
        $maxTaraWeight = 0;
        $totalItemsCount = 0;

        foreach ($order->items as $item) {
            if ($item->product) {
                // Fallback: 100g pro Artikel annehmen, falls kein Gewicht in der DB gepflegt ist, damit die Rechenlogik immer greift
                $itemWeight = $item->product->weight > 0 ? $item->product->weight : 100;
                
                $totalProductWeightGrams += ($itemWeight * $item->quantity);
                
                $remainingQty = max(0, $item->quantity - $item->completed_quantity);
                $remainingProductWeightGrams += ($itemWeight * $remainingQty);
                
                if ($item->product->packaging_weight && $item->product->packaging_weight > $maxTaraWeight) {
                    $maxTaraWeight = $item->product->packaging_weight;
                }
                
                $totalItemsCount += $item->quantity;
            }
        }

        // Intelligente Gewichtswahl:
        // Wenn noch Artikel "offen" (unverpackt) sind, nehme deren Gewicht.
        // Wurde die Bestellung *komplett verpackt* (remaining = 0), nehmen wir natürlich das Gesamtgewicht der Order!
        $weightToUse = $remainingProductWeightGrams > 0 ? $remainingProductWeightGrams : $totalProductWeightGrams;

        // Sicherheits-Fallback
        if ($weightToUse == 0 && $totalItemsCount > 0) {
            $weightToUse = $totalItemsCount * 100;
        }

        // Standard-Verpackungsgewicht (Kartonage + Füllmaterial) pro Paket
        $packagingWeightGrams = $maxTaraWeight > 0 ? $maxTaraWeight : (int)shop_setting('packaging_weight_grams', 350);  

        $packageCount = (int) $this->dhlPackageCount ?: 1;
        $totalGrams = $weightToUse + ($packageCount * $packagingWeightGrams);
        
        // In Kg umrechnen und durch Paketanzahl teilen
        $weightPerPackage = ($totalGrams / 1000) / max(1, $packageCount);
        
        // Minimalwert für DHL ist oft 0.1kg. Wir runden auf 2 Nachkommastellen (z.B. "2.45")
        $this->dhlWeightPerPackage = max(0.1, round($weightPerPackage, 2));
    }

    public function closeDhlModal()
    {
        $this->dhlModalOrderId = null;
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
            'dhlWeightPerPackage' => 'required|numeric|min:0.1|max:31.5',
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
        if ($shipment && $shipment->shipping_label_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($shipment->shipping_label_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->download($shipment->shipping_label_path);
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
                if ($shipment->shipping_label_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($shipment->shipping_label_path)) {
                    $labelJobs[] = [
                        'path' => \Illuminate\Support\Facades\Storage::disk('public')->path($shipment->shipping_label_path),
                        'order' => $order
                    ];
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
        switch ($this->sortField) {
            case 'customer':
                $query->orderBy('billing_address->last_name', $this->sortDirection);
                break;
            case 'total':
                $query->orderBy('total_price', $this->sortDirection);
                break;
            case 'payment':
                $query->orderBy('payment_status', $this->sortDirection);
                break;
            case 'status':
                $query->orderBy('status', $this->sortDirection);
                break;
            case 'order_number':
                $query->orderBy('order_number', $this->sortDirection);
                break;
            default:
                $query->orderBy($this->sortField, $this->sortDirection);
        }
    }
}

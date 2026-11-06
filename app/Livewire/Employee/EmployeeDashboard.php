<?php

namespace App\Livewire\Employee;

use App\Mail\NewOrderShippedToCustomer;
use App\Mail\SupportTicketUpdateMailToCustomer;
use App\Models\Customer\CustomerGamification;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Employee\EmployeeTimeEntry;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderShipment;
use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\Employee\EmployeeHrService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.backend_layout', ['guard' => 'employee'])]
class EmployeeDashboard extends Component
{
    public string $activeTab = 'production'; // production, support, inventory, time, payslips

    public int $selectedYear;
    public int $selectedMonth;

    // Stempeluhr & Manuelle Arbeitszeiterfassung
    public bool $showManualEntryForm = false;
    public string $manualWorkDate = '';
    public string $manualStartTime = '09:00';
    public string $manualEndTime = '13:00';
    public string $manualBreakMinutes = '';
    public string $manualActivityNote = 'Manufaktur & Versand';

    // Backwards-compatible aliases for manual time entry
    public string $entryDate = '';
    public string $entryStartTime = '09:00';
    public string $entryEndTime = '13:00';
    public string|int $entryBreakMinutes = 0;
    public string $entryActivityNote = '';

    // Urlaub / Krankmeldung
    public bool $showLeaveForm = false;
    public string $leaveType = 'vacation'; // vacation, sick
    public string $leaveStartDate = '';
    public string $leaveEndDate = '';
    public string $leaveNote = '';

    // Packtisch & Laser
    public array $trackingNumbers = [];

    // Support
    #[Locked]
    public ?string $activeTicketId = null;
    public string $ticketReplyMessage = '';

    // Lager & Bruch melden
    public string $lossProductId = '';
    public int $lossQuantity = 1;
    public string $lossReason = '';

    public function boot(): void
    {
        /** @var Employee|null $user */
        $user = Auth::guard('employee')->user();
        if (!$user || !$user->isAccountActive() || $user->isInfluencer() || $user->isTaxAdvisor()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(EmployeeHrService $service): void
    {
        /** @var Employee|null $user */
        $user = Auth::guard('employee')->user();
        if (!$user || !$user->isAccountActive() || $user->isInfluencer() || $user->isTaxAdvisor()) {
            abort(403, 'Zugriff verweigert.');
        }

        $service->ensureTablesExist();

        $this->selectedYear = (int) now()->year;
        $this->selectedMonth = (int) now()->month;
        $this->manualWorkDate = now()->format('Y-m-d');
        $this->entryDate = $this->manualWorkDate;
        $this->leaveStartDate = now()->format('Y-m-d');
        $this->leaveEndDate = now()->format('Y-m-d');

        $contract = $this->getContract();
        if ($contract) {
            if ($contract->can_access_production) {
                $this->activeTab = 'production';
            } elseif ($contract->can_access_support) {
                $this->activeTab = 'support';
            } elseif ($contract->can_access_inventory) {
                $this->activeTab = 'inventory';
            } else {
                $this->activeTab = 'time';
            }
        } else {
            $this->activeTab = 'time';
        }
    }

    protected function getContract(): ?EmployeeContract
    {
        /** @var Employee|null $employee */
        $employee = Auth::guard('employee')->user();
        if (!$employee) {
            return null;
        }

        return EmployeeContract::with('payslips')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->latest('created_at')
            ->first();
    }

    public function switchTab(string $tab): void
    {
        $contract = $this->getContract();
        if ($tab === 'production' && (!$contract || !$contract->can_access_production)) {
            return;
        }
        if ($tab === 'support' && (!$contract || !$contract->can_access_support)) {
            return;
        }
        if ($tab === 'inventory' && (!$contract || !$contract->can_access_inventory)) {
            return;
        }
        if (in_array($tab, ['production', 'support', 'inventory', 'time', 'payslips'], true)) {
            $this->activeTab = $tab;
        }
    }

    // --- 1. STEMPELUHR & ZEITEN / URLAUB ---

    public function clockIn(EmployeeHrService $service): void
    {
        $contract = $this->getContract();
        if (!$contract) {
            return;
        }

        try {
            $service->clockIn($contract, $this->manualActivityNote ?: 'Manufaktur & Versand');
            session()->flash('success', 'Eingestempelt.');
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function clockOut(EmployeeHrService $service): void
    {
        $contract = $this->getContract();
        if (!$contract) {
            return;
        }

        $running = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->where('status', 'running')
            ->first();

        if (!$running) {
            return;
        }

        $entry = $service->clockOut($running, null, $this->manualActivityNote ?: 'Manufaktur & Versand');
        session()->flash('success', "Ausgestempelt ({$entry->formatted_hours}).");
    }

    public function saveTimeEntry(EmployeeHrService $service): void
    {
        $this->manualWorkDate = $this->entryDate ?: $this->manualWorkDate;
        $this->manualStartTime = $this->entryStartTime ?: $this->manualStartTime;
        $this->manualEndTime = $this->entryEndTime ?: $this->manualEndTime;
        $this->manualBreakMinutes = $this->entryBreakMinutes ?: $this->manualBreakMinutes;
        $this->manualActivityNote = $this->entryActivityNote !== '' ? $this->entryActivityNote : $this->manualActivityNote;

        $this->saveManualTimeEntry($service);
        $this->entryActivityNote = '';
    }

    public function saveManualTimeEntry(EmployeeHrService $service): void
    {
        $contract = $this->getContract();
        if (!$contract) {
            return;
        }

        $this->validate([
            'manualWorkDate' => 'required|date',
            'manualStartTime' => 'required|string|max:10',
            'manualEndTime' => 'required|string|max:10',
        ]);

        try {
            $entry = $service->recordTimeEntry($contract, [
                'work_date' => $this->manualWorkDate,
                'start_time' => $this->manualStartTime,
                'end_time' => $this->manualEndTime,
                'break_minutes' => $this->manualBreakMinutes,
                'activity_note' => $this->manualActivityNote,
                'auto_enforce_arbzg' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addError('manualWorkDate', $e->getMessage());
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->showManualEntryForm = false;
        $this->manualBreakMinutes = '';
        session()->flash('success', "Arbeitszeit gespeichert ({$entry->formatted_hours}).");
    }

    public function saveLeaveEntry(EmployeeHrService $service): void
    {
        $contract = $this->getContract();
        if (!$contract) {
            return;
        }

        $this->validate([
            'leaveType' => 'required|in:vacation,sick',
            'leaveStartDate' => 'required|date',
            'leaveEndDate' => 'nullable|date',
        ]);

        try {
            $created = $service->recordLeaveEntries($contract, [
                'entry_type' => $this->leaveType,
                'start_date' => $this->leaveStartDate,
                'end_date' => $this->leaveEndDate ?: $this->leaveStartDate,
                'activity_note' => $this->leaveNote,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addError('leaveStartDate', $e->getMessage());
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->showLeaveForm = false;
        $this->leaveNote = '';
        $count = count($created);
        session()->flash('success', $count > 0 ? "{$count} Tag(e) eingetragen." : 'Für diesen Zeitraum liegt bereits ein Eintrag vor.');
    }

    public function deleteTimeEntry(string $entryId, EmployeeHrService $service): void
    {
        $contract = $this->getContract();
        if (!$contract) {
            return;
        }

        $entry = EmployeeTimeEntry::where('id', $entryId)
            ->where('contract_id', $contract->id)
            ->first();

        if ($entry && $entry->status !== 'approved') {
            if ($entry->work_date && $service->isPeriodLockedByPaidPayslip((string) $contract->id, (int) $entry->work_date->year, (int) $entry->work_date->month)) {
                session()->flash('error', 'Dieser Abrechnungsmonat wurde bereits endgültig abgerechnet und überwiesen (GoBD-Sperre).');
                return;
            }
            $entry->delete();
            session()->flash('success', 'Eintrag gelöscht.');
        }
    }

    // --- 2. PACKTISCH & LASER ---

    public function toggleOrderItemCompleted(string $itemId): void
    {
        $contract = $this->getContract();
        if (!$contract || !$contract->can_access_production) {
            abort(403);
        }

        $item = OrderOrderItem::findOrFail($itemId);
        $order = OrderOrder::with('items')->find($item->order_id);
        if (!$order || in_array($order->status, ['cancelled', 'refunded', 'shipped', 'completed'], true) || in_array($order->payment_status, ['failed', 'refunded', 'cancelled'], true)) {
            return;
        }

        $item->update([
            'is_completed' => !$item->is_completed,
        ]);

        if ($order->status === 'pending' && $item->is_completed) {
            $order->update(['status' => 'processing']);
        }
    }

    public function markOrderShipped(string $orderId): void
    {
        $contract = $this->getContract();
        if (!$contract || !$contract->can_access_production) {
            abort(403);
        }

        $order = OrderOrder::with(['items.product', 'shipments'])->findOrFail($orderId);
        if (in_array($order->status, ['cancelled', 'refunded', 'shipped', 'completed'], true) || in_array($order->payment_status, ['failed', 'refunded', 'cancelled'], true)) {
            return;
        }

        $tracking = isset($this->trackingNumbers[$orderId]) ? trim((string) $this->trackingNumbers[$orderId]) : '';
        if ($tracking !== '') {
            $exists = $order->shipments()->where('tracking_number', $tracking)->exists();
            if (!$exists) {
                OrderShipment::create([
                    'order_id' => $order->id,
                    'tracking_number' => $tracking,
                    'carrier' => 'DHL',
                    'status' => 'shipped',
                ]);
            }
        }

        foreach ($order->items as $item) {
            if (!$item->is_completed) {
                $item->update(['is_completed' => true]);
            }
        }

        $order->update(['status' => 'shipped']);

        try {
            $order->refresh();
            $mailData = $order->toFormattedArray();
            $mailData['tracking_numbers'] = $order->shipments()->pluck('tracking_number')->filter()->values()->toArray();
            if (!empty($order->email)) {
                Mail::to($order->email)->send(new NewOrderShippedToCustomer($mailData));
            }
        } catch (\Throwable $e) {
            Log::error('Employee markOrderShipped mail error: ' . $e->getMessage());
        }

        unset($this->trackingNumbers[$orderId]);
        session()->flash('success', "Bestellung #{$order->order_number} als versendet abgeschlossen.");
    }

    // --- 3. KUNDEN-SUPPORT ---

    public function selectSupportTicket(string $ticketId): void
    {
        $contract = $this->getContract();
        if (!$contract || !$contract->can_access_support) {
            abort(403);
        }

        $ticket = SupportTicket::find($ticketId);
        if (!$ticket) {
            return;
        }

        $this->activeTicketId = $ticket->id;
        $this->ticketReplyMessage = '';

        SupportTicketMessage::where('support_ticket_id', $ticket->id)
            ->where('sender_type', 'customer')
            ->update(['is_read_by_admin' => true]);
    }

    public function sendSupportReply(): void
    {
        $contract = $this->getContract();
        if (!$contract || !$contract->can_access_support || !$this->activeTicketId) {
            abort(403);
        }

        $this->validate([
            'ticketReplyMessage' => 'required|string|min:2|max:10000',
        ]);

        $ticket = SupportTicket::with('customer')->findOrFail($this->activeTicketId);
        if ($ticket->status === 'closed') {
            return;
        }

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => trim($this->ticketReplyMessage),
            'is_read_by_admin' => true,
            'is_read_by_customer' => false,
        ]);

        $ticket->update(['status' => 'answered']);

        $isOnline = $ticket->customer_id && Cache::has('is_online' . $ticket->customer_id);
        if (!$isOnline && $ticket->customer) {
            $gamification = CustomerGamification::where('customer_id', $ticket->customer_id)->first();
            if (!$gamification || $gamification->ticket_emails_enabled) {
                try {
                    Mail::to($ticket->customer->email)->send(new SupportTicketUpdateMailToCustomer($ticket->customer, $ticket));
                } catch (\Throwable $e) {
                    Log::error('Employee Support Reply Mail Error: ' . $e->getMessage());
                }
            }
        }

        $this->ticketReplyMessage = '';
        session()->flash('success', 'Antwort gesendet.');
    }

    public function closeSupportTicket(string $ticketId): void
    {
        $contract = $this->getContract();
        if (!$contract || !$contract->can_access_support) {
            abort(403);
        }

        $ticket = SupportTicket::findOrFail($ticketId);
        $ticket->update(['status' => 'closed']);
        if ($this->activeTicketId === $ticket->id) {
            $this->activeTicketId = null;
        }
        session()->flash('success', 'Ticket erledigt.');
    }

    // --- 4. LAGER & BRUCH MELDEN ---

    public function saveFracture(): void
    {
        $contract = $this->getContract();
        if (!$contract || !$contract->can_access_inventory) {
            abort(403);
        }

        $this->validate([
            'lossProductId' => 'required',
            'lossQuantity' => 'required|integer|min:1|max:10000',
            'lossReason' => 'required|string|min:2|max:500',
        ]);

        $saved = DB::transaction(function () {
            $product = Product::where('id', $this->lossProductId)
                ->where('type', 'physical')
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->track_quantity && $product->quantity < $this->lossQuantity) {
                $this->addError('lossQuantity', 'Nicht genug Bestand vorhanden.');
                return false;
            }

            $costValue = ($product->purchase_price ?? 0) * $this->lossQuantity;

            ProductLoss::create([
                'product_id' => $product->id,
                'product_supplier_id' => $product->supplier_id ?? null,
                'quantity' => $this->lossQuantity,
                'cost_value' => $costValue,
                'reason' => trim($this->lossReason),
                'recorded_by' => null,
            ]);

            $product->reduceStock($this->lossQuantity);
            return true;
        });

        if (!$saved) {
            return;
        }

        $this->lossProductId = '';
        $this->lossQuantity = 1;
        $this->lossReason = '';
        session()->flash('success', 'Bruch gespeichert und Bestand angepasst.');
    }

    public function render(EmployeeHrService $service)
    {
        /** @var Employee $employee */
        $employee = Auth::guard('employee')->user();
        $contract = $this->getContract();

        $runningEntry = null;
        $stats = null;
        $payslips = collect();

        if ($contract) {
            $runningEntry = EmployeeTimeEntry::where('contract_id', $contract->id)
                ->where('status', 'running')
                ->first();

            $stats = $service->getMonthlyStats($contract, $this->selectedYear, $this->selectedMonth);
            $payslips = $contract->payslips()
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->get();
        }

        $openOrders = collect();
        if ($contract && $contract->can_access_production) {
            $openOrders = OrderOrder::with(['items.product', 'shipments'])
                ->whereIn('status', ['pending', 'processing'])
                ->orderByDesc('is_express')
                ->orderBy('created_at', 'asc')
                ->limit(50)
                ->get();
        }

        $openTickets = collect();
        $activeTicket = null;
        if ($contract && $contract->can_access_support) {
            $openTickets = SupportTicket::with('customer')
                ->whereIn('status', ['open', 'answered'])
                ->orderByDesc('updated_at')
                ->limit(40)
                ->get();

            if ($this->activeTicketId) {
                $activeTicket = SupportTicket::with(['messages', 'customer', 'order'])->find($this->activeTicketId);
            } elseif ($openTickets->isNotEmpty()) {
                $first = $openTickets->first();
                $this->activeTicketId = $first->id;
                $activeTicket = SupportTicket::with(['messages', 'customer', 'order'])->find($first->id);
            }
        }

        $physicalProducts = collect();
        $recentLosses = collect();
        if ($contract && $contract->can_access_inventory) {
            $physicalProducts = Product::where('status', 'active')
                ->where('type', 'physical')
                ->orderBy('name')
                ->get();
            $recentLosses = ProductLoss::with('product')
                ->latest()
                ->limit(5)
                ->get();
        }

        return view('livewire.employee.employee-dashboard', [
            'employee' => $employee,
            'contract' => $contract,
            'runningEntry' => $runningEntry,
            'stats' => $stats,
            'payslips' => $payslips,
            'openOrders' => $openOrders,
            'openTickets' => $openTickets,
            'activeTicket' => $activeTicket,
            'physicalProducts' => $physicalProducts,
            'recentLosses' => $recentLosses,
        ]);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Tracking\PageVisit; // Geändert auf deinen neuen Namespace!
use App\Models\System\SystemLoginAttempt;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Product\Product;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Accounting\AccountingCostItem;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

class AnalyticsService
{
    public function getHealthChecks(): array
    {
        if (!\App\Services\AI\AiAuthHelper::isAdmin()) return [];

        return array_filter([
            'inventory' => $this->checkInventory(),
            'special_issues' => $this->checkSpecialIssues(),
            'leitung/contracts' => $this->checkContracts(),
            'open_tickets' => $this->checkTickets(),
            'product_reviews' => $this->checkReviews(),
            'open_orders' => $this->checkOrders(),
            'open_credits' => $this->checkCredits(),
            'unassigned_tx' => $this->checkUnassignedTx(),
            'open_tasks' => $this->checkTasks(),
            'open_quotes' => $this->checkQuotes(),
            'open_revocations' => $this->checkRevocations(),
            'open_losses' => $this->checkLosses(),
            'open_chats' => $this->checkChats(),
            'open_contact_requests' => $this->checkContactRequests(),
            'open_mails' => $this->checkMails(),
            'storage' => $this->checkStorage(),
            'open_abandoned_carts' => $this->checkAbandonedCarts(),
            'system_logs' => $this->checkSystemLogs(),
        ]);
    }

    private function checkSystemLogs(): ?array
    {
        if (!class_exists(\App\Models\System\SystemLog::class)) return null;
        $totalLogs = \App\Models\System\SystemLog::count();
        $unresolvedLogsQuery = \App\Models\System\SystemLog::where('status', 'error');
        $unresolvedLogsCount = $unresolvedLogsQuery->count();
        
        $status = $unresolvedLogsCount > 0 ? 'error' : 'success';
        $msg = $unresolvedLogsCount > 0 ? $unresolvedLogsCount . ' ungelöste Fehler' : 'Alles fehlerfrei';
        if ($totalLogs === 0) { $msg = 'Keine System-Logs vorhanden'; $status = 'success'; }

        return [
            'status' => $status,
            'icon' => 'command-line',
            'title' => 'Log',
            'message' => $msg,
            'count' => $unresolvedLogsCount,
            'data' => $unresolvedLogsQuery->latest()->take(5)->get()->map(function($log) {
                return [
                    'id' => $log->id,
                    'title' => $log->title,
                    'type' => $log->type,
                    'created_at' => $log->created_at->format('d.m. H:i')
                ];
            })->toArray()
        ];
    }

    private function checkAbandonedCarts(): ?array
    {
        if (!class_exists(\App\Models\Cart\Cart::class)) return null;
        $totalCarts = \App\Models\Cart\Cart::count();
        $redLimit = (int) shop_setting('cart_abandoned_red_hours', 24);

        $abandonedCarts = \App\Models\Cart\Cart::where('updated_at', '<', now()->subHours($redLimit))
            ->whereNotNull('customer_id')
            ->whereHas('items')
            ->count();

        $status = $abandonedCarts > 0 ? 'warning' : 'success';
        $msg = $abandonedCarts > 0 ? $abandonedCarts . ' Körbe unberührt' : 'Alles aktuell';
        if ($totalCarts === 0) { $msg = 'Keine aktiven Körbe'; $status = 'success'; }

        return [
            'status' => $status, 
            'icon' => 'shopping-cart', 
            'title' => 'Warenkörbe', 
            'message' => $msg, 
            'count' => $abandonedCarts, 
            'data' => []
        ];
    }

    private function checkInventory(): array
    {
        $threshold = shop_setting('inventory_low_stock_threshold', 5);
        $lowStockProducts = Product::where('type', 'physical')
            ->where('track_quantity', true)
            ->where('quantity', '<', $threshold)
            ->where('status', 'active')
            ->get();

        return [
            'title' => 'Lagerbestände',
            'status' => $lowStockProducts->count() > 0 ? 'warning' : 'success',
            'message' => $lowStockProducts->count() > 0 ? $lowStockProducts->count() . " Artikel unter Limit!" : "Lagerbestände optimal.",
            'icon' => 'cube',
            'count' => $lowStockProducts->count(),
            'data' => $lowStockProducts
        ];
    }

    private function checkStorage(): ?array
    {
        $freeSpace = \disk_free_space(base_path());
        $totalSpace = \disk_total_space(base_path());
        if ($totalSpace > 0) {
            $percentUsed = 100 - round(($freeSpace / $totalSpace) * 100);
            $threshold1 = (int) \Illuminate\Support\Facades\Cache::get('storage_capacity_threshold_1', \App\Models\System\SystemSetting::where('key', 'storage_capacity_threshold_1')->value('value') ?? 70);
            
            $needsAction = $percentUsed >= $threshold1;
            
            return [
                'status' => $needsAction ? 'warning' : 'success',
                'icon' => 'server',
                'title' => 'Speicher',
                'description' => 'Serverfestplatte',
                'message' => $needsAction ? "Mahnung 1 ({$percentUsed}% voll)" : 'Ausreichend Platz',
                'count' => $needsAction ? 1 : 0,
                'data' => []
            ];
        }
        return null;
    }

    private function checkSpecialIssues(): array
    {
        $adminId = \App\Services\AI\AiAuthHelper::getAdminId();
        $missing = AccountingSpecialIssue::when($adminId, fn($q) => $q->where('admin_id', $adminId))
            ->where('is_business', true)
            ->where(function ($query) {
                $query->whereNull('file_paths')
                    ->orWhere('file_paths', '[]')
                    ->orWhere('file_paths', '');
            })->orderBy('execution_date', 'desc')->get();

        return [
            'title' => 'Belege',
            'status' => $missing->count() > 0 ? 'todo' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Positionen ohne Beleg." : "Alle Ausgaben belegt.",
            'icon' => 'banknotes',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    private function checkContracts(): array
    {
        $adminId = \App\Services\AI\AiAuthHelper::getAdminId();
        $missing = AccountingCostItem::when($adminId, fn($q) => $q->whereHas('group', fn($gq) => $gq->where('admin_id', $adminId)))
            ->where('requires_contract', true)
            ->whereNull('contract_file_path')
            ->with('group')
            ->get();

        return [
            'title' => 'Verträge',
            'status' => $missing->count() > 0 ? 'todo' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Unterlagen fehlen." : "Dokumente vollständig.",
            'icon' => 'document-text',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    private function checkTickets(): ?array
    {
        if (!class_exists(\App\Models\Support\SupportTicket::class)) return null;
        $totalTickets = \App\Models\Support\SupportTicket::count();
        $openTickets = \App\Models\Support\SupportTicket::where('status', 'open')->with('customer')->get();
        $tCount = $openTickets->count();
        $status = $tCount > 0 ? 'todo' : 'success';
        $msg = $tCount > 0 ? $tCount . ' Kundenanfragen warten' : 'Alles beantwortet';
        if ($totalTickets === 0) { $msg = 'Keine Tickets vorhanden'; $status = 'success'; }
        return [
            'status' => $status,
            'icon' => 'ticket',
            'title' => 'Tickets',
            'message' => $msg,
            'count' => $tCount,
            'data' => $openTickets->map(function($t) {
                return [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'subject' => $t->subject,
                    'customer_name' => $t->customer ? $t->customer->first_name : 'Kunde'
                ];
            })->values()->toArray()
        ];
    }

    private function checkChats(): ?array
    {
        if (!class_exists(\App\Models\Support\SupportCustomerChat::class)) return null;
        $totalChats = \App\Models\Support\SupportCustomerChat::count();
        $openChats = \App\Models\Support\SupportCustomerChat::where('status', 'open')->get();
        $cCount = $openChats->count();
        $status = $cCount > 0 ? 'warning' : 'success';
        $msg = $cCount > 0 ? $cCount . ' Live-Chats offen' : 'Alles beantwortet';
        if ($totalChats === 0) { $msg = 'Keine Chats vorhanden'; $status = 'success'; }
        return [
            'status' => $status,
            'icon' => 'chat-bubble-oval-left-ellipsis',
            'title' => 'Chats',
            'message' => $msg,
            'count' => $cCount,
            'data' => []
        ];
    }

    private function checkMails(): ?array
    {
        if (!class_exists(\App\Models\Management\Mail\MailMessage::class)) return null;
        $totalMails = \App\Models\Management\Mail\MailMessage::count();
        $openMails = \App\Models\Management\Mail\MailMessage::where('is_read', false)
            ->whereNotIn('folder', ['Trash', 'Sent', 'Drafts', 'Junk', 'Archiv', 'Archive', 'Gesendet', 'Entwürfe', 'Papierkorb'])
            ->count();

        $status = $openMails > 0 ? 'todo' : 'success';
        $msg = $openMails > 0 ? $openMails . ' ungelesen' : 'Alles gelesen';
        if ($totalMails === 0) { $msg = 'Keine Mails vorhanden'; $status = 'success'; }

        return [
            'status' => $status,
            'icon' => 'envelope-open',
            'title' => 'Mails',
            'message' => $msg,
            'count' => $openMails,
            'data' => []
        ];
    }

    private function checkContactRequests(): ?array
    {
        if (!class_exists(\App\Models\Support\SupportContactRequest::class)) return null;
        $totalReqs = \App\Models\Support\SupportContactRequest::count();
        $openReqs = \App\Models\Support\SupportContactRequest::where('status', '!=', 'resolved')->get();
        $rCount = $openReqs->count();
        $status = $rCount > 0 ? 'todo' : 'success';
        $msg = $rCount > 0 ? $rCount . ' Kontaktanfragen offen' : 'Alles beantwortet';
        if ($totalReqs === 0) { $msg = 'Keine Kontaktanfragen vorhanden'; $status = 'success'; }
        return [
            'status' => $status,
            'icon' => 'envelope',
            'title' => 'Anfragen',
            'message' => $msg,
            'count' => $rCount,
            'data' => []
        ];
    }

    private function checkReviews(): ?array
    {
        if (!class_exists(\App\Models\Product\ProductReview::class)) return null;
        $totalReviews = \App\Models\Product\ProductReview::count();
        $pendingReviews = \App\Models\Product\ProductReview::where('status', 'pending')->with('product')->get();
        $rCount = $pendingReviews->count();
        $status = $rCount > 0 ? 'todo' : 'success';
        $msg = $rCount > 0 ? $rCount . ' Bewertungen prüfen' : 'Alle geprüft';
        if ($totalReviews === 0) { $msg = 'Noch keine Bewertungen'; $status = 'success'; }
        return [
            'status' => $status,
            'icon' => 'star',
            'title' => 'Bewertungen',
            'message' => $msg,
            'count' => $rCount,
            'data' => $pendingReviews->map(function($r) {
                return [
                    'id' => $r->id,
                    'product_name' => $r->product ? $r->product->name : 'Produkt',
                    'rating' => $r->rating
                ];
            })->values()->toArray()
        ];
    }

    private function checkOrders(): ?array
    {
        if (!class_exists(\App\Models\Order\OrderOrder::class)) return null;
        $totalOrders = \App\Models\Order\OrderOrder::count();
        $oCount = \App\Models\Order\OrderOrder::whereIn('status', ['pending', 'processing'])->count();
        $status = $oCount > 0 ? 'todo' : 'success';
        $msg = $oCount > 0 ? $oCount . ' unversendet' : 'Alles versendet';
        if ($totalOrders === 0) { $msg = 'Noch keine Bestellungen'; $status = 'success'; }
        return ['status' => $status, 'icon' => 'shopping-cart', 'title' => 'Bestellungen', 'message' => $msg, 'count' => $oCount, 'data' => []];
    }

    private function checkCredits(): ?array
    {
        if (!class_exists(\App\Models\Accounting\AccountingInvoice::class)) return null;
        $totalCredits = \App\Models\Accounting\AccountingInvoice::whereIn('type', ['credit_note', 'cancellation'])->count();
        $cCount = \App\Models\Accounting\AccountingInvoice::whereIn('type', ['credit_note', 'cancellation'])->whereNull('email_sent_at')->count();
        $status = $cCount > 0 ? 'warning' : 'success';
        $msg = $cCount > 0 ? $cCount . ' unversendet' : 'Alle versendet';
        if ($totalCredits === 0) { $msg = 'Keine Gutschriften vorhanden'; $status = 'success'; }
        return ['status' => $status, 'icon' => 'document-minus', 'title' => 'Gutschriften', 'message' => $msg, 'count' => $cCount, 'data' => []];
    }

    private function checkUnassignedTx(): ?array
    {
        if (!class_exists(\App\Models\Accounting\AccountingBankTransaction::class) || !\App\Services\AI\AiAuthHelper::isAdmin()) return null;
        $totalTx = \App\Models\Accounting\AccountingBankTransaction::whereHas('account', function($q) { $q->where('is_active_for_analysis', true)->where('admin_id', \App\Services\AI\AiAuthHelper::getAdminId()); })->count();
        $unassignedTx = \App\Models\Accounting\AccountingBankTransaction::whereHas('account', function($q) { $q->where('is_active_for_analysis', true)->where('admin_id', \App\Services\AI\AiAuthHelper::getAdminId()); })->whereNull('assigned_by_type')->count();
        $status = $unassignedTx > 0 ? 'todo' : 'success';
        $msg = $unassignedTx > 0 ? $unassignedTx . ' unzugeordnet' : 'Alle sortiert';
        if ($totalTx === 0) { $msg = 'Keine Transaktionen gefunden'; $status = 'success'; }
        return ['status' => $status, 'icon' => 'banknotes', 'title' => 'Bank Umsätze', 'message' => $msg, 'count' => $unassignedTx, 'data' => []];
    }

    private function checkTasks(): ?array
    {
        if (!class_exists(\App\Models\Management\ManagementTask::class)) return null;
        $totalTasks = \App\Models\Management\ManagementTask::count();
        $openTasks = \App\Models\Management\ManagementTask::where('is_completed', false)->count();
        $status = $openTasks > 0 ? 'todo' : 'success';
        $msg = $openTasks > 0 ? $openTasks . ' Todos offen' : 'Alles erledigt';
        if ($totalTasks === 0) { $msg = 'Keine Aufgaben vorhanden'; $status = 'success'; }
        return ['status' => $status, 'icon' => 'check-circle', 'title' => 'Aufgaben', 'message' => $msg, 'count' => $openTasks, 'data' => []];
    }

    private function checkQuotes(): ?array
    {
        if (!class_exists(\App\Models\Order\OrderQuoteRequest::class)) return null;
        $totalQuotes = \App\Models\Order\OrderQuoteRequest::count();
        $openQuotes = \App\Models\Order\OrderQuoteRequest::where('status', 'open')->count();
        $status = $openQuotes > 0 ? 'todo' : 'success';
        $msg = $openQuotes > 0 ? $openQuotes . ' Angebote offen' : 'Alles aktuell';
        if ($totalQuotes === 0) { $msg = 'Keine Angebote vorhanden'; $status = 'success'; }
        return ['status' => $status, 'icon' => 'clipboard-document-list', 'title' => 'Angebote', 'message' => $msg, 'count' => $openQuotes, 'data' => []];
    }

    private function checkRevocations(): ?array
    {
        if (!class_exists(\App\Models\Order\OrderRevocation::class)) return null;
        $totalRevs = \App\Models\Order\OrderRevocation::count();
        $oldRevs = \App\Models\Order\OrderRevocation::whereNotIn('status', ['processed', 'declined'])->where('created_at', '<', now()->subDays(2))->count();
        $status = $oldRevs > 0 ? 'warning' : 'success';
        $msg = $oldRevs > 0 ? $oldRevs . ' älter als 2 Tage' : 'Alles aktuell';
        if ($totalRevs === 0) { $msg = 'Keine Widerrufe vorhanden'; $status = 'success'; }
        return ['status' => $status, 'icon' => 'archive-box-x-mark', 'title' => 'Widerrufe', 'message' => $msg, 'count' => $oldRevs, 'data' => []];
    }

    private function checkLosses(): ?array
    {
        if (!class_exists(\App\Models\Product\ProductLoss::class)) return null;
        $totalLosses = \App\Models\Product\ProductLoss::count();
        $openLosses = \App\Models\Product\ProductLoss::whereNull('refund_received_at')->count();
        $status = $openLosses > 0 ? 'warning' : 'success';
        $msg = $openLosses > 0 ? $openLosses . ' ungelöste Fälle' : 'Alles erledigt';
        if ($totalLosses === 0) { $msg = 'Keine Schäden erfasst'; $status = 'success'; }
        return ['status' => $status, 'icon' => 'exclamation-triangle', 'title' => 'Schwund & Bruch', 'message' => $msg, 'count' => $openLosses, 'data' => []];
    }

    public function getStats($dateStart, $dateEnd, $filterType = 'all', $lastLogins = [], $systemHealth = [])
    {
        if (!in_array($filterType, ['all', 'business', 'private'], true)) {
            $filterType = 'all';
        }

        $start = Carbon::parse($dateStart)->startOfDay();
        $end = Carbon::parse($dateEnd)->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }
        if ($start->diffInDays($end) > 3660) {
            $start = $end->copy()->subDays(3660)->startOfDay();
        }
        $diffInDays = $start->diffInDays($end);

        // ==========================================
        // TRAFFIC & ANALYTICS (Eigene Datenbank)
        // ==========================================
        $visitsQuery = PageVisit::whereBetween('created_at', [$start, $end]);

        $totalPageViews = (clone $visitsQuery)->count();
        $uniqueVisitors = (clone $visitsQuery)->distinct('session_id')->count('session_id');

        // Geräte Analyse (Desktop vs Mobile)
        $mobileVisits = (clone $visitsQuery)->where(function($q) {
            $q->where('user_agent', 'LIKE', '%Mobile%')
                ->orWhere('user_agent', 'LIKE', '%Android%')
                ->orWhere('user_agent', 'LIKE', '%iPhone%')
                ->orWhere('user_agent', 'LIKE', '%iPad%');
        })->count();
        $desktopVisits = $totalPageViews - $mobileVisits;

        // Top Seiten
        $topPages = (clone $visitsQuery)
            ->select('path', DB::raw('count(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        // Professionelle Quellenanalyse (First-Touch Session-Attribution, Kanäle & UTM)
        $trafficSources = $this->getTrafficSourcesAnalysis($start, $end);

        // Kompatible Top-Referrer für bisherige Widgets
        $topReferrers = collect($trafficSources['top_sources'])
            ->where('channel', '!=', 'direct')
            ->pluck('count', 'name')
            ->take(5);

        if ($topReferrers->isEmpty() && !empty($trafficSources['top_sources'])) {
            $topReferrers = collect($trafficSources['top_sources'])->pluck('count', 'name')->take(5);
        }

        // Traffic Chart Datenstruktur
        $visitLabels = [];
        $visitCounts = [];
        $uniqueCounts = [];

        if ($diffInDays <= 31) {
            // Gruppierung nach TAGEN
            $visitsByDay = (clone $visitsQuery)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'), DB::raw('count(distinct session_id) as unique_total'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $periodTraffic = CarbonPeriod::create($start, $end);
            foreach ($periodTraffic as $date) {
                $dateString = $date->format('Y-m-d');
                $visitLabels[] = $date->format('d.m.');
                $visitCounts[] = $visitsByDay->has($dateString) ? $visitsByDay[$dateString]->total : 0;
                $uniqueCounts[] = $visitsByDay->has($dateString) ? $visitsByDay[$dateString]->unique_total : 0;
            }
        } else {
            // Gruppierung nach MONATEN
            $visitsByMonth = (clone $visitsQuery)
                ->select(DB::raw('YEAR(created_at) as year'), DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as total'), DB::raw('count(distinct session_id) as unique_total'))
                ->groupBy('year', 'month')
                ->get();

            $currentDate = $start->copy()->startOfMonth();
            $finalDate = $end->copy()->endOfMonth();

            while ($currentDate <= $finalDate) {
                $y = $currentDate->year;
                $m = $currentDate->month;
                $match = $visitsByMonth->first(fn($v) => $v->year == $y && $v->month == $m);

                $visitLabels[] = $currentDate->locale('de')->shortMonthName . ' ' . $currentDate->format('y');
                $visitCounts[] = $match ? $match->total : 0;
                $uniqueCounts[] = $match ? $match->unique_total : 0;

                $currentDate->addMonth();
            }
        }

        // ==========================================
        // KUNDENGEWINNUNG (Customer Acquisition)
        // ==========================================
        $customerQuery = Customer::whereBetween('created_at', [$start, $end])->whereHas('profile', function ($query) {
            $query->whereNotNull('email_verified_at');
        });
        $totalCustomers = (clone $customerQuery)->count();

        $latestCustomers = (clone $customerQuery)
            ->orderByDesc('created_at')
            ->take(5)
            ->get(['first_name', 'last_name', 'email', 'created_at']);

        $customerLabels = [];
        $customerCounts = [];

        if ($diffInDays <= 31) {
            $customersByDay = (clone $customerQuery)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $periodCustomers = CarbonPeriod::create($start, $end);
            foreach ($periodCustomers as $date) {
                $dateString = $date->format('Y-m-d');
                $customerLabels[] = $date->format('d.m.');

                $customerCounts[] = $customersByDay->has($dateString) ? $customersByDay[$dateString]->total : 0;
            }
        } else {
            $customersByMonth = (clone $customerQuery)
                ->select(DB::raw('YEAR(created_at) as year'), DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as total'))
                ->groupBy('year', 'month')
                ->get();

            $currentDate = $start->copy()->startOfMonth();
            $finalDate = $end->copy()->endOfMonth();

            while ($currentDate <= $finalDate) {
                $y = $currentDate->year;
                $m = $currentDate->month;

                $cMatch = $customersByMonth->first(fn($v) => $v->year == $y && $v->month == $m);

                $customerLabels[] = $currentDate->locale('de')->shortMonthName . ' ' . $currentDate->format('y');
                $customerCounts[] = $cMatch ? $cMatch->total : 0;

                $currentDate->addMonth();
            }
        }

        // ==========================================
        // FINANZEN & SHOP (Dein bestehender Code)
        // ==========================================
        $chartData = ['labels' => [], 'revenue' => [], 'expenses' => [], 'profit' => []];

        $costItemsQuery = AccountingCostItem::with('group');
        if ($filterType === 'business') $costItemsQuery->where('is_business', true);
        if ($filterType === 'private') $costItemsQuery->where('is_business', false);
        $allCostItems = $costItemsQuery->get();

        $isFixedIncome = function ($item): bool {
            if ($item->amount < 0) {
                return false;
            }
            if ($item->relationLoaded('group') && $item->group) {
                return $item->group->type === 'income';
            }
            return $item->amount > 0;
        };

        $isActiveInRange = function ($item, $rangeStart, $rangeEnd): bool {
            if ($item->first_payment_date && $item->first_payment_date->copy()->startOfMonth()->gt($rangeEnd)) {
                return false;
            }
            if ($item->last_payment_date && $item->last_payment_date->copy()->endOfMonth()->lt($rangeStart)) {
                return false;
            }
            return true;
        };

        if ($diffInDays <= 31) {
            $period = CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $fixedIncomeDay = 0;
                $fixedExpenseDay = 0;

                foreach ($allCostItems as $item) {
                    if (!$isActiveInRange($item, $dayStart, $dayEnd)) {
                        continue;
                    }
                    $dailyAmount = (abs($item->amount) / ($item->interval_months ?: 1)) / 30.42;
                    if ($isFixedIncome($item)) $fixedIncomeDay += $dailyAmount;
                    else $fixedExpenseDay += $dailyAmount;
                }

                $specials = AccountingSpecialIssue::whereBetween('execution_date', [$dayStart, $dayEnd])
                    ->when($filterType === 'business', fn($q) => $q->where('is_business', true))
                    ->when($filterType === 'private', fn($q) => $q->where('is_business', false))
                    ->get();

                $specialInc = $specials->where('amount', '>=', 0)->sum('amount');
                $specialExp = abs($specials->where('amount', '<', 0)->sum('amount'));

                $shopRev = 0;
                if ($filterType !== 'private') {
                    $shopRev = OrderOrder::whereBetween('created_at', [$dayStart, $dayEnd])
                            ->where('payment_status', 'paid')
                            ->whereNotIn('status', ['cancelled', 'refunded'])
                            ->sum('total_price') / 100;
                }

                $rev = $shopRev + $specialInc;
                $exp = $specialExp + $fixedExpenseDay;

                $chartData['labels'][] = $date->format('d.m.');
                $chartData['revenue'][] = round($rev, 2);
                $chartData['expenses'][] = round($exp, 2);
                $chartData['profit'][] = round(($shopRev + $specialInc + $fixedIncomeDay) - $exp, 2);
            }
        } else {
            $currentDate = $start->copy()->startOfMonth();
            $finalDate = $end->copy()->endOfMonth();

            while ($currentDate <= $finalDate) {
                $mStart = $currentDate->copy()->startOfMonth();
                $mEnd = $currentDate->copy()->endOfMonth();

                if ($mStart < $start) $mStart = $start;
                if ($mEnd > $end) $mEnd = $end;

                $fixedIncomeMonth = 0;
                $fixedExpenseMonth = 0;
                $factor = ($mStart->diffInDays($mEnd) + 1) / $currentDate->daysInMonth;

                foreach ($allCostItems as $item) {
                    if (!$isActiveInRange($item, $mStart, $mEnd)) {
                        continue;
                    }
                    $monthlyAmount = abs($item->amount) / ($item->interval_months ?: 1);
                    $periodAmount = $monthlyAmount * $factor;
                    if ($isFixedIncome($item)) $fixedIncomeMonth += $periodAmount;
                    else $fixedExpenseMonth += $periodAmount;
                }

                $specials = AccountingSpecialIssue::whereBetween('execution_date', [$mStart, $mEnd])
                    ->when($filterType === 'business', fn($q) => $q->where('is_business', true))
                    ->when($filterType === 'private', fn($q) => $q->where('is_business', false))
                    ->get();

                $specialIncome = $specials->where('amount', '>=', 0)->sum('amount');
                $specialExpenses = abs($specials->where('amount', '<', 0)->sum('amount'));

                $shopRevenue = ($filterType !== 'private') ? OrderOrder::whereBetween('created_at', [$mStart, $mEnd])
                    ->where('payment_status', 'paid')
                    ->whereNotIn('status', ['cancelled', 'refunded'])
                    ->sum('total_price') / 100 : 0;

                $revenue = $shopRevenue + $specialIncome;
                $expenses = $specialExpenses + $fixedExpenseMonth;

                $chartData['labels'][] = $currentDate->locale('de')->shortMonthName . ' ' . $currentDate->format('y');
                $chartData['revenue'][] = round($revenue, 2);
                $chartData['expenses'][] = round($expenses, 2);
                $chartData['profit'][] = round(($shopRevenue + $specialIncome + $fixedIncomeMonth) - $expenses, 2);

                $currentDate->addMonth();
            }
        }

        $topExpenses = AccountingSpecialIssue::whereBetween('execution_date', [$start, $end])
            ->where('amount', '<', 0)
            ->when($filterType !== 'all', fn($q) => $q->where('is_business', $filterType === 'business'))
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $totalRevenuePeriod = array_sum($chartData['revenue']);
        $totalExpensesPeriod = array_sum($chartData['expenses']);
        $totalProfitPeriod = array_sum($chartData['profit']);

        $durationInDays = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $prevStart = $start->copy()->subDays($durationInDays);
        $prevEnd = $start->copy()->subDay();

        $prevRevenue = $this->calculateRevenueForPeriod($prevStart, $prevEnd, $filterType);
        $revenueGrowth = $prevRevenue > 0 ? (($totalRevenuePeriod - $prevRevenue) / $prevRevenue) * 100 : 0;

        $monthsCount = max(1, $durationInDays / 30.42);
        $avgProfit = $totalProfitPeriod / $monthsCount;
        $projectedProfit = $avgProfit * 12;

        $activePeriodCostItems = $allCostItems->filter(fn($i) => $isActiveInRange($i, $start, $end));

        $fixedIncomeTotal = $activePeriodCostItems->filter(fn($i) => $isFixedIncome($i))->sum(fn($i) => abs($i->amount) / ($i->interval_months ?: 1)) * ($durationInDays / 30.42);
        $totalIncomePeriod = $totalRevenuePeriod + $fixedIncomeTotal;
        $margin = $totalIncomePeriod > 0 ? ($totalProfitPeriod / $totalIncomePeriod) * 100 : 0;

        $fixGewerbe = $activePeriodCostItems->where('is_business', true)->filter(fn($i) => !$isFixedIncome($i))->sum(fn($i) => abs($i->amount) / ($i->interval_months ?: 1)) * ($durationInDays / 30.42);
        $fixPrivat = $activePeriodCostItems->where('is_business', false)->filter(fn($i) => !$isFixedIncome($i))->sum(fn($i) => abs($i->amount) / ($i->interval_months ?: 1)) * ($durationInDays / 30.42);

        $variableExpensesTotal = AccountingSpecialIssue::whereBetween('execution_date', [$start, $end])
            ->where('amount', '<', 0)
            ->when($filterType !== 'all', fn($q) => $q->where('is_business', $filterType === 'business'))
            ->sum(DB::raw('ABS(amount)'));

        $productStatsQuery = OrderOrderItem::whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])
                ->where('payment_status', 'paid')
                ->whereNotIn('status', ['cancelled', 'refunded']))
            ->select('product_name', DB::raw('SUM(total_price)/100 as total'))
            ->groupBy('product_name')
            ->orderByDesc('total');

        $highRevenueProd = $productStatsQuery->first();
        $lowRevenueProd = $productStatsQuery->clone()->orderBy('total', 'asc')->first();

        $breakEvenValue = ($fixGewerbe + $fixPrivat) / max(1, ($durationInDays / 30.42));
        $qualityScore = $this->calculateShopQualityScore($margin, $revenueGrowth, $totalProfitPeriod, $totalRevenuePeriod, $breakEvenValue * ($durationInDays / 30.42));

        // Vorberechnung für Health Score & Return Array
        $pendingInvoicesCount = AccountingInvoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->count();
        $pendingInvoicesSum = AccountingInvoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->sum('total') / 100;

        // ==========================================
        // SHOP HEALTH SCORE (0 - 100)
        // ==========================================
        $healthScore = 0;

        // 1. Break-Even (+30)
        $totalIncome = $totalRevenuePeriod + $fixedIncomeTotal;
        $totalCosts = $fixPrivat + $fixGewerbe + $variableExpensesTotal;
        if ($totalIncome > 0 && $totalIncome >= $totalCosts) {
            $healthScore += 30;
        } elseif ($totalIncome > 0 && $totalIncome >= ($totalCosts * 0.8)) {
            $healthScore += 15; // Fast erreicht
        }

        // 2. Netto-Ziel (+20)
        if ($avgProfit >= 1600) {
            $healthScore += 20;
        } elseif ($avgProfit >= 1000) {
            $healthScore += 10;
        }

        // 3. Gewinn-Marge (+25)
        if ($margin >= 30) {
            $healthScore += 25;
        } elseif ($margin >= 15) {
            $healthScore += 12;
        }

        // 4. Umsatz-Trend (+15)
        if ($revenueGrowth > 0) {
            $healthScore += 15;
        } elseif ($revenueGrowth == 0) {
            $healthScore += 5;
        }

        // 5. Offene Posten (+10)
        if ($pendingInvoicesSum == 0) {
            $healthScore += 10;
        } else {
            $deduction = min(10, floor($pendingInvoicesSum / 100));
            $healthScore += max(0, 10 - $deduction);
        }

        // 6. OPERATIVER INTEGRATION SCORE (Abzüge)
        // Die Abzüge werden subtrahiert, falls offene Todos und Systemstörungen existieren
        $opChecks = $this->getHealthChecks();
        $opErrors = collect($opChecks)->where('status', 'error')->count();
        $opWarnings = collect($opChecks)->where('status', 'warning')->count();
        $opScorePunishment = ($opErrors * 5) + ($opWarnings * 1);
        $healthScore -= $opScorePunishment;

        // 7. INFRASTRUKTUR INTEGRATION SCORE (Abzüge)
        // Systemfehler werden hart vom Master-Score abgezogen
        if (!empty($systemHealth)) {
            $sysErrors = collect($systemHealth)->where('status', 'error')->count();
            $sysWarnings = collect($systemHealth)->where('status', 'warning')->count();
            $sysScorePunishment = ($sysErrors * 10) + ($sysWarnings * 2);
            $healthScore -= $sysScorePunishment;
        }

        $healthScore = max(0, min(100, $healthScore));

        // ==========================================
        // ABANDONED CARTS
        // ==========================================
        $abandonedCartsQuery = \App\Models\Cart\Cart::where('updated_at', '<', now()->subHours(1))
            ->whereBetween('updated_at', [$start, $end])
            ->with(['customer', 'items']);
        $abandonedCartsData = $abandonedCartsQuery->get();

        $compiledCartsList = [];
        $potentialCartRev = 0;

        foreach ($abandonedCartsData as $cart) {
            $sum = 0;
            $itemsCount = 0;
            foreach ($cart->items as $item) {
                $sum += ($item->unit_price * $item->quantity);
                $itemsCount += $item->quantity;
            }

            if ($sum > 0) {
                $potentialCartRev += ($sum / 100);

                $hours = $cart->updated_at->diffInHours(now());
                if ($hours < 3) {
                    $status = 'green';
                    $ageFormat = '< 3h';
                } elseif ($hours < 24) {
                    $status = 'yellow';
                    $ageFormat = '< 24h';
                } else {
                    $status = 'red';
                    $ageFormat = '> ' . floor($hours / 24) . 'd';
                }

                $compiledCartsList[] = [
                    'id' => $cart->id,
                    'customer' => $cart->customer ? $cart->customer->first_name . ' ' . $cart->customer->last_name : null,
                    'email' => $cart->customer ? $cart->customer->email : null,
                    'total' => $sum / 100,
                    'items_count' => $itemsCount,
                    'status' => $status,
                    'age' => $ageFormat,
                    'updated_at' => $cart->updated_at
                ];
            }
        }

        usort($compiledCartsList, function($a, $b) {
            return $b['updated_at'] <=> $a['updated_at'];
        });

        return [
            'abandoned_carts' => [
                'count' => count($compiledCartsList),
                'potential_revenue' => round($potentialCartRev, 2),
                'details' => $compiledCartsList
            ],
            'total_users' => Admin::count() + Customer::count() + Employee::count(),
            'active_users_today' => collect($lastLogins)->whereBetween('last_seen', [Carbon::today(), Carbon::now()])->count(),
            'new_registrations_week' => Customer::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count() + Admin::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count() + Employee::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'failed_logins' => SystemLoginAttempt::where('success', false)->count(),
            'active_sessions' => DB::table('sessions')->count(),
            'never_logged_in' => collect($lastLogins)->whereNull('last_seen')->count(),
            'inactive_30_days' => collect($lastLogins)->filter(fn($u) => $u['last_seen'] && Carbon::parse($u['last_seen'])->lt(now()->subDays(30)))->count(),

            // NEUE TRAFFIC DATEN
            'frontend_visits_total' => $totalPageViews,
            'frontend_unique_total' => $uniqueVisitors,
            'desktop_visits' => $desktopVisits,
            'mobile_visits' => $mobileVisits,
            'top_pages' => $topPages,
            'top_referrers' => $topReferrers,
            'traffic_sources' => $trafficSources,
            'visit_days' => $visitLabels,
            'visit_counts' => $visitCounts,
            'unique_counts' => $uniqueCounts,

            // KUNDENGEWINNUNG DATEN
            'total_new_customers_period' => $totalCustomers ?? 0,
            'customer_labels' => $customerLabels ?? [],
            'customer_counts' => $customerCounts ?? [],
            'latest_customers' => $latestCustomers ?? [],

            // SHOP DATEN
            'chart_data' => $chartData,
            'total_revenue' => $totalRevenuePeriod,
            'total_profit' => $totalProfitPeriod,
            'avg_revenue_monthly' => $totalRevenuePeriod / max(1, ($durationInDays / 30.42)),
            'revenue_growth' => round($revenueGrowth, 1),
            'avg_profit' => $avgProfit,
            'projected_year' => $projectedProfit,
            'margin' => round($margin, 1),
            'break_even_monthly' => ($fixGewerbe + $fixPrivat) / max(1, ($durationInDays / 30.42)),
            'break_even_period' => ($fixGewerbe + $fixPrivat),
            'break_even_chart_line' => ($diffInDays <= 31) ? (($fixGewerbe + $fixPrivat) / max(1, ($durationInDays / 30.42)) / 30.42) : (($fixGewerbe + $fixPrivat) / max(1, ($durationInDays / 30.42))),
            'shop_quality_score' => $qualityScore,
            'health_score' => $healthScore,
            'fixed_income_total' => $fixedIncomeTotal,
            'shop_revenue' => ($filterType !== 'private') ? OrderOrder::whereBetween('created_at', [$start, $end])
                ->where('payment_status', 'paid')
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->sum('total_price') / 100 : 0,
            'orders_total' => ($filterType !== 'private') ? OrderOrder::whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->count() : 0,
            'fixed_expenses_priv' => $fixPrivat,
            'fixed_expenses_gew' => $fixGewerbe,
            'variable_expenses' => $variableExpensesTotal,
            'pending_invoices' => [
                'count' => $pendingInvoicesCount,
                'sum' => $pendingInvoicesSum
            ],
            'top_expenses' => $topExpenses,
            'top_customers' => $filterType !== 'private' ? OrderOrder::whereBetween('created_at', [$start, $end])
                ->where('payment_status', 'paid')
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->select('email', DB::raw('SUM(total_price)/100 as total'))
                ->groupBy('email')
                ->orderByDesc('total')
                ->take(5)
                ->get()
                ->map(fn($o) => ['category' => $o->email, 'total' => $o->total]) : collect(),
            'high_revenue_prod' => $highRevenueProd,
            'low_revenue_prod' => $lowRevenueProd,
            'product_ranking' => $filterType !== 'private' ? OrderOrderItem::whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])
                ->where('payment_status', 'paid')
                ->whereNotIn('status', ['cancelled', 'refunded']))
                ->select('product_name', DB::raw('SUM(quantity) as qty'))
                ->groupBy('product_name')
                ->orderByDesc('qty')
                ->take(3)
                ->get() : collect(),
        ];
    }

    private function calculateShopQualityScore($margin, $growth, $profit, $revenue, $breakEvenTotal)
    {
        $score = 0;
        if ($profit > 0) $score += 20;
        if ($revenue > $breakEvenTotal && $breakEvenTotal > 0) $score += 20;
        $score += min(30, max(0, $margin));
        if ($growth > 0) $score += min(30, $growth * 1.5);
        else $score -= min(20, abs($growth));
        return max(0, min(100, round($score)));
    }

    private function calculateRevenueForPeriod($start, $end, $filterType)
    {
        $shop = ($filterType !== 'private') ? OrderOrder::whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total_price') / 100 : 0;
        $special = AccountingSpecialIssue::whereBetween('execution_date', [$start, $end])->where('amount', '>=', 0)->when($filterType !== 'all', fn($q) => $q->where('is_business', $filterType === 'business'))->sum('amount');
        return $shop + $special;
    }

    public function getAllLoginsCollection()
    {
        return Admin::with('profile')->get()->map(fn($u) => ['type' => 'Admin', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen])
            ->merge(Customer::with('profile')->get()->map(fn($u) => ['type' => 'Customer', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen]))
            ->merge(Employee::with('profile')->get()->map(fn($u) => ['type' => 'Employee', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen]));
    }

    public function generateDepartmentInsight(string $department, array $dataPayload, string $agentId): string
    {
        $agent = \App\Models\Ai\AiAgent::findOrFail($agentId);
        $prompt = "Du bist der leitende {$department}-Manager von Seelenfunke.\n";
        $prompt .= "Hier sind die aggregierten Systemdaten der angewählten Zeitspanne:\n\n";
        $prompt .= json_encode($dataPayload, JSON_UNESCAPED_UNICODE) . "\n\n";
        $prompt .= "Analysiere diese extrem detailliert. Formuliere strategische, sofort umsetzbare Management-Handlungsanweisungen bzgl. der Wachstumsraten, Task-Prioritäten und Kalenderdichte.";

        return \App\Services\AI\AiAgentFactory::processDirectPrompt($agent, $prompt);
    }

    /**
     * Professionelle Quellenanalyse (Traffic-Akquise, Tracking-Links & Referrer)
     * Ermittelt für jede Besuchersitzung (Session) den First-Touch-Einstiegskanal,
     * zählt zusätzlich alle tatsächlichen Seitenaufrufe (Aufrufzahlen / Views) sowie
     * die DSGVO-konformen Tracking-Link-Aufrufe und kategorisiert sie in die Kanal-Verteilung.
     */
    public function getTrafficSourcesAnalysis(Carbon $start, Carbon $end): array
    {
        // 1. Hole den ersten Seitenaufruf (First-Touch) + die tatsächliche Aufrufzahl (views_count) jeder Sitzung im Zeitraum
        $sessionAggregates = PageVisit::whereBetween('created_at', [$start, $end])
            ->select(
                'session_id',
                DB::raw('MIN(id) as first_id'),
                DB::raw('COUNT(*) as views_count')
            )
            ->groupBy('session_id')
            ->get();

        $firstVisitIds = $sessionAggregates->pluck('first_id')->filter();
        $sessionViewsByFirstId = $sessionAggregates->pluck('views_count', 'first_id');

        $entryVisits = $firstVisitIds->isNotEmpty()
            ? PageVisit::whereIn('id', $firstVisitIds)->get()
            : collect();

        // 2. Hole Tracking-Link-Aufrufe im gewählten Zeitraum
        $trackingLinksWithHits = collect();
        $hasTrackingTables = \Illuminate\Support\Facades\Schema::hasTable('management_tracking_links')
            && \Illuminate\Support\Facades\Schema::hasTable('management_tracking_link_hits');

        if ($hasTrackingTables) {
            $trackingLinksWithHits = \App\Models\Management\ManagementTrackingLink::query()
                ->withCount([
                    'hits as period_hits_count' => function ($q) use ($start, $end) {
                        $q->whereBetween('created_at', [$start, $end]);
                    },
                    'hits as period_unique_count' => function ($q) use ($start, $end) {
                        $q->whereBetween('created_at', [$start, $end])
                          ->select(DB::raw('count(distinct ip_hash)'));
                    },
                ])
                ->get();
        }

        $totalTrackingPeriodHits = (int) $trackingLinksWithHits->sum('period_hits_count');

        if ($entryVisits->isEmpty() && $totalTrackingPeriodHits === 0) {
            return $this->getEmptyTrafficSourcesData();
        }

        // Bekannte interne Hosts ermitteln
        $internalHosts = collect([
            request() ? request()->getHost() : null,
            parse_url((string) config('app.url'), PHP_URL_HOST),
            'mein-seelenfunke.de',
            'seelenfunke.de',
            'stage.mein-seelenfunke.de',
            'stage.seelenfunke.de',
            'localhost',
            '127.0.0.1',
        ])->filter()->map(fn($h) => strtolower(str_replace('www.', '', $h)))->unique()->toArray();

        $channels = [
            'search' => ['key' => 'search', 'label' => 'Organische Suche', 'icon' => 'solar-magnifer-bold-duotone', 'color' => '#3b82f6', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'social' => ['key' => 'social', 'label' => 'Social Media', 'icon' => 'solar-chat-round-line-bold-duotone', 'color' => '#ec4899', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'campaign' => ['key' => 'campaign', 'label' => 'Kampagnen & Ads', 'icon' => 'solar-tag-price-bold-duotone', 'color' => '#f59e0b', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'referral' => ['key' => 'referral', 'label' => 'Empfehlungen & Partner', 'icon' => 'solar-link-round-bold-duotone', 'color' => '#10b981', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'direct' => ['key' => 'direct', 'label' => 'Direktzugriffe', 'icon' => 'solar-cursor-bold-duotone', 'color' => '#64748b', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
        ];

        $sourcesMap = [];
        $campaignsMap = [];
        $attributedTrackingSlugs = [];

        foreach ($entryVisits as $visit) {
            $visitViews = max(1, (int) ($sessionViewsByFirstId[$visit->id] ?? 1));
            $parsedUrl = parse_url($visit->url ?? '');
            $queryParams = [];
            if (!empty($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
            }

            $utmSource = strtolower(trim($queryParams['utm_source'] ?? ''));
            $utmMedium = strtolower(trim($queryParams['utm_medium'] ?? ''));
            $utmCampaign = trim($queryParams['utm_campaign'] ?? '');
            $tid = trim($queryParams['tid'] ?? '');
            $refCode = trim($queryParams['ref'] ?? '');

            $referer = $visit->referer ? trim($visit->referer) : null;
            $refererHost = '';
            if ($referer) {
                $refHost = parse_url($referer, PHP_URL_HOST);
                if ($refHost) {
                    $refererHost = strtolower(str_replace('www.', '', $refHost));
                }
            }

            // Klassifizierung:
            $channelKey = 'direct';
            $sourceName = 'Direkte Eingabe / Lesezeichen';
            $sourceIcon = 'solar-cursor-bold-duotone';
            $sourceDomain = null;

            // 1. Hat expliziten UTM-Source oder Tracking-ID Parameter?
            if ($utmSource !== '' || $tid !== '') {
                $isPaid = in_array($utmMedium, ['cpc', 'ppc', 'paid', 'ad', 'ads', 'meta_ads', 'google_ads'], true)
                    || str_contains($utmSource, 'ad')
                    || str_contains($utmMedium, 'ad');

                if ($isPaid) {
                    $channelKey = 'campaign';
                    $sourceName = ucfirst($utmSource ?: 'Kampagne') . ' Ads';
                    $sourceIcon = 'solar-tag-price-bold-duotone';
                } elseif (in_array($utmSource, ['instagram', 'facebook', 'tiktok', 'pinterest', 'youtube', 'linkedin', 'twitter', 'x'], true)) {
                    $channelKey = 'social';
                    $sourceName = ucfirst($utmSource);
                    $sourceIcon = 'solar-chat-round-line-bold-duotone';
                } elseif (in_array($utmMedium, ['newsletter', 'email', 'mail'], true) || str_contains($utmSource, 'newsletter')) {
                    $channelKey = 'campaign';
                    $sourceName = 'Newsletter (' . ucfirst($utmSource ?: 'Mail') . ')';
                    $sourceIcon = 'solar-letter-bold-duotone';
                } elseif (in_array($utmSource, ['partner', 'referral', 'affiliate'], true) || $utmMedium === 'referral') {
                    $channelKey = 'referral';
                    $sourceName = 'Partner (' . ucfirst($utmSource) . ')';
                    $sourceIcon = 'solar-link-round-bold-duotone';
                } else {
                    $channelKey = 'campaign';
                    $sourceName = ucfirst($utmSource ?: $tid);
                    $sourceIcon = 'solar-tag-price-bold-duotone';
                }

                // Kampagne erfassen
                $campKey = $tid !== '' ? $tid : ($utmCampaign !== '' ? $utmCampaign : ($utmSource . ' / ' . ($utmMedium ?: 'k.A.')));
                if ($tid !== '') {
                    $attributedTrackingSlugs[$tid] = ($attributedTrackingSlugs[$tid] ?? 0) + 1;
                }
                if (!isset($campaignsMap[$campKey])) {
                    $campaignsMap[$campKey] = [
                        'name' => $utmCampaign ?: $campKey,
                        'slug' => $tid ?: null,
                        'source' => $utmSource ?: 'tracking',
                        'medium' => $utmMedium ?: '–',
                        'campaign' => $utmCampaign ?: ($tid ?: '–'),
                        'count' => 0, // Aufrufe
                        'unique_count' => 0, // Sitzungen
                        'is_tracking_link' => $tid !== '',
                    ];
                }
                $campaignsMap[$campKey]['count'] += $visitViews;
                $campaignsMap[$campKey]['unique_count']++;
            }
            // 2. Hat Freunde-Werben Referral-Code (?ref=...)
            elseif ($refCode !== '') {
                $channelKey = 'referral';
                $sourceName = 'Freunde-Werben (' . $refCode . ')';
                $sourceIcon = 'solar-users-group-rounded-bold-duotone';

                if (!isset($campaignsMap['ref:' . $refCode])) {
                    $campaignsMap['ref:' . $refCode] = [
                        'name' => 'Freunde-Werben: ' . $refCode,
                        'slug' => $refCode,
                        'source' => 'Referral',
                        'medium' => 'Empfehlungs-Link',
                        'campaign' => $refCode,
                        'count' => 0,
                        'unique_count' => 0,
                        'is_tracking_link' => false,
                    ];
                }
                $campaignsMap['ref:' . $refCode]['count'] += $visitViews;
                $campaignsMap['ref:' . $refCode]['unique_count']++;
            }
            // 3. Hat externen Referrer-Header
            elseif ($refererHost !== '' && !in_array($refererHost, $internalHosts, true)) {
                $sourceDomain = $refererHost;

                // Suchmaschinen
                if (str_contains($refererHost, 'google.')) {
                    $channelKey = 'search';
                    $sourceName = 'Google Suche';
                    $sourceIcon = 'solar-magnifer-bold-duotone';
                } elseif (str_contains($refererHost, 'bing.com')) {
                    $channelKey = 'search';
                    $sourceName = 'Bing Suche';
                    $sourceIcon = 'solar-magnifer-bold-duotone';
                } elseif (str_contains($refererHost, 'ecosia.org')) {
                    $channelKey = 'search';
                    $sourceName = 'Ecosia';
                    $sourceIcon = 'solar-leaf-bold-duotone';
                } elseif (str_contains($refererHost, 'duckduckgo.com')) {
                    $channelKey = 'search';
                    $sourceName = 'DuckDuckGo';
                    $sourceIcon = 'solar-shield-check-bold-duotone';
                } elseif (str_contains($refererHost, 'yahoo.')) {
                    $channelKey = 'search';
                    $sourceName = 'Yahoo';
                    $sourceIcon = 'solar-magnifer-bold-duotone';
                }
                // Social Media
                elseif (str_contains($refererHost, 'instagram.com')) {
                    $channelKey = 'social';
                    $sourceName = 'Instagram';
                    $sourceIcon = 'solar-chat-round-line-bold-duotone';
                } elseif (str_contains($refererHost, 'facebook.com') || str_contains($refererHost, 'fb.me')) {
                    $channelKey = 'social';
                    $sourceName = 'Facebook';
                    $sourceIcon = 'solar-chat-round-line-bold-duotone';
                } elseif (str_contains($refererHost, 'tiktok.com')) {
                    $channelKey = 'social';
                    $sourceName = 'TikTok';
                    $sourceIcon = 'solar-play-circle-bold-duotone';
                } elseif (str_contains($refererHost, 'pinterest.')) {
                    $channelKey = 'social';
                    $sourceName = 'Pinterest';
                    $sourceIcon = 'solar-gallery-wide-bold-duotone';
                } elseif (str_contains($refererHost, 'youtube.com') || str_contains($refererHost, 'youtu.be')) {
                    $channelKey = 'social';
                    $sourceName = 'YouTube';
                    $sourceIcon = 'solar-play-circle-bold-duotone';
                } elseif (str_contains($refererHost, 't.co') || str_contains($refererHost, 'twitter.com') || str_contains($refererHost, 'x.com')) {
                    $channelKey = 'social';
                    $sourceName = 'X (Twitter)';
                    $sourceIcon = 'solar-chat-round-line-bold-duotone';
                } elseif (str_contains($refererHost, 'linkedin.com') || str_contains($refererHost, 'lnkd.in')) {
                    $channelKey = 'social';
                    $sourceName = 'LinkedIn';
                    $sourceIcon = 'solar-user-id-bold-duotone';
                }
                // Sonstige externe Referrals
                else {
                    $channelKey = 'referral';
                    $sourceName = $refererHost;
                    $sourceIcon = 'solar-link-round-bold-duotone';
                }
            } else {
                $channelKey = 'direct';
                $sourceName = 'Direkte Eingabe / Lesezeichen';
                $sourceIcon = 'solar-cursor-bold-duotone';
            }

            // Channel count (Sitzungen) & views (tatsächliche Aufrufe) erhöhen
            $channels[$channelKey]['count']++;
            $channels[$channelKey]['views'] += $visitViews;

            // Source aggregation
            $sourceKey = $sourceName;
            if (!isset($sourcesMap[$sourceKey])) {
                $sourcesMap[$sourceKey] = [
                    'name' => $sourceName,
                    'channel' => $channelKey,
                    'channel_label' => $channels[$channelKey]['label'],
                    'icon' => $sourceIcon,
                    'domain' => $sourceDomain,
                    'count' => 0,
                    'views' => 0,
                    'pct' => 0,
                    'views_pct' => 0,
                ];
            }
            $sourcesMap[$sourceKey]['count']++;
            $sourcesMap[$sourceKey]['views'] += $visitViews;
        }

        // 3. Tracking-Links aus dem Linktree-Bereich in die Kanal-Verteilung, Quellen & Kampagnen einfließen lassen
        foreach ($trackingLinksWithHits as $trk) {
            $periodHits = (int) ($trk->period_hits_count ?? 0);
            $periodUnique = (int) ($trk->period_unique_count ?? 0);
            if ($periodHits <= 0) {
                continue;
            }

            $channelKey = isset($channels[$trk->channel])
                ? $trk->channel
                : \App\Models\Management\ManagementTrackingLink::resolveChannel((string) $trk->utm_source, (string) $trk->utm_medium);
            if (!isset($channels[$channelKey])) {
                $channelKey = 'campaign';
            }

            $channels[$channelKey]['tracking_clicks'] += $periodHits;

            // Falls Aufrufe über /t/{slug} stattfanden, die noch nicht als PageVisit-Einstieg gezählt wurden:
            $alreadyCountedSessions = (int) ($attributedTrackingSlugs[$trk->slug] ?? 0);
            $uncountedUnique = max(0, $periodUnique - $alreadyCountedSessions);
            $uncountedHits = max(0, $periodHits - $alreadyCountedSessions);

            if ($uncountedUnique > 0) {
                $channels[$channelKey]['count'] += $uncountedUnique;
            }
            if ($uncountedHits > 0) {
                $channels[$channelKey]['views'] += $uncountedHits;
            }

            $srcLabel = ucfirst((string) $trk->utm_source) . ' (Tracking-Link)';
            if (!isset($sourcesMap[$srcLabel])) {
                $sourcesMap[$srcLabel] = [
                    'name' => $srcLabel,
                    'channel' => $channelKey,
                    'channel_label' => $channels[$channelKey]['label'],
                    'icon' => $channels[$channelKey]['icon'],
                    'domain' => '/t/' . $trk->slug,
                    'count' => 0,
                    'views' => 0,
                    'pct' => 0,
                    'views_pct' => 0,
                ];
            }
            $sourcesMap[$srcLabel]['count'] += max(1, $periodUnique);
            $sourcesMap[$srcLabel]['views'] += $periodHits;

            $campKey = $trk->slug;
            $campaignsMap[$campKey] = [
                'name' => $trk->name,
                'slug' => $trk->slug,
                'source' => $trk->utm_source,
                'medium' => $trk->utm_medium ?: 'linktree',
                'campaign' => $trk->utm_campaign ?: $trk->slug,
                'count' => max((int) ($campaignsMap[$campKey]['count'] ?? 0), $periodHits),
                'unique_count' => max((int) ($campaignsMap[$campKey]['unique_count'] ?? 0), $periodUnique),
                'is_tracking_link' => true,
            ];
        }

        $totalSessions = (int) collect($channels)->sum('count');
        $totalViews = (int) collect($channels)->sum('views');

        // Prozentsätze berechnen (sowohl für Sitzungen als auch für tatsächliche Aufrufzahlen)
        foreach ($channels as $k => $c) {
            $channels[$k]['pct'] = $totalSessions > 0 ? round(($c['count'] / $totalSessions) * 100, 1) : 0;
            $channels[$k]['views_pct'] = $totalViews > 0 ? round(($c['views'] / $totalViews) * 100, 1) : 0;
        }

        $topSources = collect($sourcesMap)
            ->map(function ($s) use ($totalSessions, $totalViews) {
                $s['pct'] = $totalSessions > 0 ? round(($s['count'] / $totalSessions) * 100, 1) : 0;
                $s['views_pct'] = $totalViews > 0 ? round(($s['views'] / $totalViews) * 100, 1) : 0;
                return $s;
            })
            ->sortByDesc('views')
            ->values()
            ->take(8)
            ->toArray();

        $topCampaigns = collect($campaignsMap)
            ->map(function ($c) use ($totalViews) {
                $c['pct'] = $totalViews > 0 ? round(($c['count'] / $totalViews) * 100, 1) : 0;
                return $c;
            })
            ->sortByDesc('count')
            ->values()
            ->take(8)
            ->toArray();

        // Chart Data für Donut / Bar Chart (nutzt tatsächliche Aufrufzahlen für präzise Gewichtung)
        $chartLabels = [];
        $chartData = [];
        $chartColors = [];
        foreach ($channels as $c) {
            if ($c['views'] > 0 || $c['count'] > 0 || $totalViews === 0) {
                $chartLabels[] = $c['label'];
                $chartData[] = $c['views'] > 0 ? $c['views'] : $c['count'];
                $chartColors[] = $c['color'];
            }
        }

        // Summary Text inkl. konkreter Aufrufzahlen
        $strongestChannel = collect($channels)->sortByDesc('views')->first();
        $summaryText = $totalViews > 0 && ($strongestChannel['views'] ?? 0) > 0
            ? "Stärkster Akquise-Kanal ist {$strongestChannel['label']} mit {$strongestChannel['views']} Aufrufen ({$strongestChannel['count']} Sitzungen / {$strongestChannel['views_pct']}% Anteil)."
            : "Noch keine ausreichenden Sitzungsdaten im Zeitraum.";

        return [
            'total_sessions' => $totalSessions,
            'total_views' => $totalViews,
            'total_tracking_clicks' => $totalTrackingPeriodHits,
            'channels' => $channels,
            'top_sources' => $topSources,
            'top_campaigns' => $topCampaigns,
            'chart_labels' => $chartLabels,
            'chart_data' => $chartData,
            'chart_colors' => $chartColors,
            'summary_text' => $summaryText,
        ];
    }

    public function getEmptyTrafficSourcesData(): array
    {
        $channels = [
            'search' => ['key' => 'search', 'label' => 'Organische Suche', 'icon' => 'solar-magnifer-bold-duotone', 'color' => '#3b82f6', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'social' => ['key' => 'social', 'label' => 'Social Media', 'icon' => 'solar-chat-round-line-bold-duotone', 'color' => '#ec4899', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'campaign' => ['key' => 'campaign', 'label' => 'Kampagnen & Ads', 'icon' => 'solar-tag-price-bold-duotone', 'color' => '#f59e0b', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'referral' => ['key' => 'referral', 'label' => 'Empfehlungen & Partner', 'icon' => 'solar-link-round-bold-duotone', 'color' => '#10b981', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
            'direct' => ['key' => 'direct', 'label' => 'Direktzugriffe', 'icon' => 'solar-cursor-bold-duotone', 'color' => '#64748b', 'count' => 0, 'views' => 0, 'tracking_clicks' => 0, 'pct' => 0, 'views_pct' => 0],
        ];

        return [
            'total_sessions' => 0,
            'total_views' => 0,
            'total_tracking_clicks' => 0,
            'channels' => $channels,
            'top_sources' => [],
            'top_campaigns' => [],
            'chart_labels' => ['Organische Suche', 'Social Media', 'Kampagnen & Ads', 'Empfehlungen', 'Direkt'],
            'chart_data' => [0, 0, 0, 0, 0],
            'chart_colors' => ['#3b82f6', '#ec4899', '#f59e0b', '#10b981', '#64748b'],
            'summary_text' => 'Keine Sitzungsdaten im gewählten Zeitraum vorhanden.',
        ];
    }
}

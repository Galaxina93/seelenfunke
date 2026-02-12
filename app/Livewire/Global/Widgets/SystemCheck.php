<?php

namespace App\Livewire\Global\Widgets;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
// use Illuminate\Support\Facades\Cache; // Cache wird nicht mehr benötigt

// Models
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\LoginAttempt;
use App\Models\PageVisit;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Product\Product;
use App\Models\Order\Order;
use App\Models\Invoice;
use App\Models\Quote\QuoteRequest;
use App\Models\ShopSetting;

class SystemCheck extends Component
{
    use WithPagination;

    public $stats = [];

    // Pagination Einstellungen
    protected $paginationTheme = 'tailwind';

    // Toggle States für Tabellen
    public $showFailedLogins = false;
    public $showFullLogins = false;

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        // 1. Hole letzte Logins für Metriken
        $lastLogins = $this->getAllLoginsCollection();

        // 2. Chart Daten (Wochentage)
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $visitsByDay = PageVisit::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->select(DB::raw('WEEKDAY(created_at) as weekday'), DB::raw('count(*) as total'))
            ->groupBy('weekday')
            ->pluck('total', 'weekday')
            ->toArray();

        $visitCounts = [];
        // Mo(0) bis So(6)
        for ($i = 0; $i < 7; $i++) {
            $visitCounts[] = $visitsByDay[$i] ?? 0;
        }

        // 3. Stats Array befüllen
        $this->stats = [
            // User Stats
            'total_users'            => Admin::count() + Customer::count() + Employee::count(),
            'active_users_today'     => $lastLogins->whereBetween('last_seen', [Carbon::today(), Carbon::now()])->count(),
            'new_registrations_week' => Customer::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count()
                + Admin::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count()
                + Employee::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count(),
            'failed_logins'          => DB::table('login_attempts')->where('success', false)->count(),
            'active_sessions'        => DB::table('sessions')->count(),
            'never_logged_in'        => $lastLogins->whereNull('last_seen')->count(),
            'inactive_30_days'       => $lastLogins->filter(fn($u) => $u['last_seen'] && Carbon::parse($u['last_seen'])->lt(Carbon::now()->subDays(30)))->count(),

            // Frontend Stats
            'frontend_visits_total'  => PageVisit::count(),
            'frontend_visits_today'  => PageVisit::whereDate('created_at', now())->count(),

            // Chart Data
            'visit_days'             => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            'visit_counts'           => $visitCounts,
        ];
    }

    /**
     * Sammelt alle User-Typen in einer Collection (für Berechnungen)
     */
    private function getAllLoginsCollection()
    {
        $adminLogins = Admin::with('profile')->get()->map(fn($u) => [
            'type' => 'Admin', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen
        ]);

        $customerLogins = Customer::with('profile')->get()->map(fn($u) => [
            'type' => 'Customer', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen
        ]);

        $employeeLogins = Employee::with('profile')->get()->map(fn($u) => [
            'type' => 'Employee', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen
        ]);

        return $adminLogins->merge($customerLogins)->merge($employeeLogins);
    }

    /**
     * Paginierte Liste für die Tabelle
     */
    public function getPaginatedLoginsProperty()
    {
        $allLogins = $this->getAllLoginsCollection()->sortByDesc('last_seen')->values();

        $perPage = 8;
        $currentPage = LengthAwarePaginator::resolveCurrentPage('loginsPage');
        $items = $allLogins->forPage($currentPage, $perPage);

        return new LengthAwarePaginator(
            $items,
            $allLogins->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => 'loginsPage']
        );
    }

    public function getPaginatedFailedLoginsProperty()
    {
        return LoginAttempt::where('success', false)
            ->orderByDesc('attempted_at')
            ->paginate(5, ['email', 'ip_address', 'attempted_at'], 'failedPage');
    }

    public function render()
    {
        // Wir laden die Config hier einmalig für die View, falls benötigt, aber primär für die Checks
        // $threshold = ShopSetting::where('key', 'inventory_low_stock_threshold')->value('value') ?? 5;

        return view('livewire.global.widgets.system-check.system-check', [
            'checks' => $this->performAllChecks(),
            'paginatedLogins' => $this->paginatedLogins,
            'paginatedFailedLogins' => $this->paginatedFailedLogins,
        ]);
    }

    // --- Business Logic Checks ---

    private function performAllChecks(): array
    {
        $checks = [];
        $checks[] = $this->checkContracts();
        $checks[] = $this->checkSpecialIssues();
        $checks[] = $this->checkInventory();
        $checks[] = $this->checkProductDrafts();
        $checks[] = $this->checkCashflow();
        $checks[] = $this->checkQuotes();
        return array_filter($checks);
    }

    private function checkContracts(): array
    {
        $missingFiles = FinanceCostItem::whereNull('contract_file_path')->count();
        return [
            'title' => 'Vertrags-Check',
            'icon' => 'solar-document-bold-duotone',
            'status' => $missingFiles > 0 ? 'warning' : 'success',
            'message' => $missingFiles > 0 ? "$missingFiles fehlende Unterlagen." : "Alles dokumentiert.",
            'count' => $missingFiles,
            'action_label' => 'Prüfen',
            'action_url' => route('admin.financial-contracts-groups'),
        ];
    }

    private function checkSpecialIssues(): array
    {
        $unlinkedIssues = FinanceSpecialIssue::whereNull('invoice_number')->count();
        return [
            'title' => 'Sonderausgaben',
            'icon' => 'solar-bill-list-bold-duotone',
            'status' => $unlinkedIssues > 5 ? 'warning' : 'success',
            'message' => $unlinkedIssues > 0 ? "$unlinkedIssues ohne Rechnungsnr." : "Alles referenziert.",
            'count' => $unlinkedIssues,
            'action_label' => 'Prüfen',
            'action_url' => route('admin.financial-categories-special-editions'),
        ];
    }

    private function checkInventory(): array
    {
        // Lade den Schwellenwert direkt aus der DB
        $threshold = ShopSetting::where('key', 'inventory_low_stock_threshold')->value('value') ?? 5;

        $lowStockCount = Product::where('track_quantity', true)
            ->where('quantity', '<', $threshold)
            ->where('quantity', '>', 0) // Nur die, die nicht 0 sind (0 ist outOfStock)
            ->where('status', 'active')
            ->count();

        $outOfStock = Product::where('track_quantity', true)
            ->where('quantity', '<=', 0)
            ->where('status', 'active')
            ->count();

        if ($outOfStock > 0) {
            return [
                'title' => 'Lager KRITISCH',
                'icon' => 'solar-box-bold-duotone',
                'status' => 'danger',
                'message' => "$outOfStock ausverkauft!",
                'count' => $outOfStock,
                'action_label' => 'Auffüllen',
                'action_url' => route('admin.products'),
            ];
        }

        if ($lowStockCount > 0) {
            return [
                'title' => 'Lagerbestand',
                'icon' => 'solar-box-bold-duotone',
                'status' => 'warning',
                'message' => "$lowStockCount unter Limit ($threshold Stk).",
                'count' => $lowStockCount,
                'action_label' => 'Prüfen',
                'action_url' => route('admin.products'),
            ];
        }

        return [
            'title' => 'Lagerbestand',
            'icon' => 'solar-box-bold-duotone',
            'status' => 'success',
            'message' => "Bestand optimal.",
            'count' => 0,
            'action_label' => 'Prüfen',
            'action_url' => route('admin.products'),
        ];
    }

    private function checkProductDrafts(): array
    {
        $drafts = Product::where('status', 'draft')->count();
        return [
            'title' => 'Entwürfe',
            'icon' => 'solar-pen-new-square-bold-duotone',
            'status' => $drafts > 0 ? 'info' : 'success',
            'message' => $drafts > 0 ? "$drafts unfertig." : "Keine Entwürfe.",
            'count' => $drafts,
            'action_label' => 'Bearbeiten',
            'action_url' => 'admin.products',
        ];
    }

    private function checkCashflow(): array
    {
        $overdueInvoices = Invoice::where('status', 'open')->where('due_date', '<', now())->count();
        return [
            'title' => 'Cashflow',
            'icon' => 'solar-wad-of-money-bold-duotone',
            'status' => $overdueInvoices > 0 ? 'danger' : 'success',
            'message' => $overdueInvoices > 0 ? "$overdueInvoices überfällig!" : "Alles bezahlt.",
            'count' => $overdueInvoices,
            'action_label' => 'Mahnwesen',
            'action_url' => 'admin.invoices',
        ];
    }

    private function checkQuotes(): array
    {
        $expiringQuotes = QuoteRequest::where('status', 'open')->where('expires_at', '<', now()->addDays(3))->count();
        if($expiringQuotes === 0) return [];
        return [
            'title' => 'Angebots-Fristen',
            'icon' => 'solar-clapping-bold-duotone',
            'status' => 'warning',
            'message' => "$expiringQuotes laufen ab.",
            'count' => $expiringQuotes,
            'action_label' => 'Prüfen',
            'action_url' => 'admin.quote-requests',
        ];
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\LoginAttempt;
use App\Models\PageVisit;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class EvaluationDashboard extends Component
{
    use WithPagination;

    public $stats = [];

    protected $paginationTheme = 'tailwind';
    private $page;

    public function mount()
    {
        $this->loadStats();
    }

    /**
     * Holt die letzten Logins von Admins, Kunden und Mitarbeitern.
     */
    private function getLastLogins()
    {
        $adminLogins = Admin::with('profile')->get()->map(function ($admin) {
            return [
                'type'       => 'Admin',
                'first_name' => $admin->first_name,
                'last_name'  => $admin->last_name,
                'last_seen'  => optional($admin->profile)->last_seen,
            ];
        });

        $customerLogins = Customer::with('profile')->get()->map(function ($customer) {
            return [
                'type'       => 'Customer',
                'first_name' => $customer->first_name,
                'last_name'  => $customer->last_name,
                'last_seen'  => optional($customer->profile)->last_seen,
            ];
        });

        $employeeLogins = Employee::with('profile')->get()->map(function ($employee) {
            return [
                'type'       => 'Employee',
                'first_name' => $employee->first_name,
                'last_name'  => $employee->last_name,
                'last_seen'  => optional($employee->profile)->last_seen,
            ];
        });

        $all = $adminLogins
            ->merge($customerLogins)
            ->merge($employeeLogins)
            ->sortByDesc('last_seen')
            ->values();

        return $all;
    }

    /**
     * Lädt alle Statistiken.
     */
    public function loadStats()
    {
        $lastLogins = $this->getLastLogins();

        $startOfWeek = now()->startOfWeek(); // Montag
        $endOfWeek = now()->endOfWeek();     // Sonntag

        $visitsByDay = PageVisit::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->select(DB::raw('WEEKDAY(created_at) as weekday'), DB::raw('count(*) as total'))
            ->groupBy('weekday')
            ->pluck('total', 'weekday')
            ->toArray();

        $dayLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $visitCounts = [];

        for ($i = 0; $i < 7; $i++) {
            $visitCounts[] = $visitsByDay[$i] ?? 0;
        }

        $this->stats = [
            'total_users'            => Admin::count() + Customer::count() + Employee::count(),
            'active_users_today'     => collect($lastLogins)->whereBetween('last_seen', [Carbon::today(), Carbon::now()])->count(),
            'new_registrations_week' => Customer::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count()
                + Admin::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count()
                + Employee::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count(),
            'failed_logins'          => DB::table('login_attempts')->where('success', false)->count(),
            'active_sessions'        => DB::table('sessions')->count(),
            'never_logged_in'        => collect($lastLogins)->whereNull('last_seen')->count(),
            'inactive_30_days'       => collect($lastLogins)->filter(fn($u) => $u['last_seen'] && Carbon::parse($u['last_seen'])->lt(Carbon::now()->subDays(30)))->count(),
            'frontend_visits_total'  => PageVisit::count(),
            'frontend_visits_today' => PageVisit::whereDate('created_at', now())->count(),
            'visit_days'             => $dayLabels,
            'visit_counts'           => $visitCounts,
        ];
    }

    /**
     * Holt paginierte Logins (10 pro Seite)
     */
    public function getPaginatedLoginsProperty()
    {
        $allLogins = collect($this->getLastLogins())->sortByDesc('last_seen')->values();

        $perPage = 8;
        $currentPage = $this->page;
        $items = $allLogins->forPage($currentPage, $perPage);

        return new LengthAwarePaginator(
            $items,
            $allLogins->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Holt paginierte fehlgeschlagene Logins
     */
    public function getPaginatedFailedLoginsProperty()
    {
        return LoginAttempt::where('success', false)
            ->orderByDesc('attempted_at')
            ->paginate(10, ['email', 'ip_address', 'attempted_at']);
    }

    public function render()
    {
        return view('livewire.admin.evaluation-dashboard', [
            'paginatedLogins'       => $this->paginatedLogins,
            'paginatedFailedLogins' => $this->paginatedFailedLogins,
        ]);
    }
}

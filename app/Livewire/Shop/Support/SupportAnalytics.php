<?php

namespace App\Livewire\Shop\Support;

use Livewire\Attributes\Layout;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportCustomerChat;
use App\Models\Support\SupportContactRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;


#[Layout('components.layouts.backend_layout')]
class SupportAnalytics extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Support';

    #[Url]
    public $dateRange = '30';

    public $dateFrom;
    public $dateTo;

    // Analytics State
    public array $volumeData = [];
    public array $sourceData = [];
    public array $ticketStatusData = [];
    public array $chatStatusData = [];


    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Support Analytics.');
        }

        $this->updateDateRange();

    }

    public function updatedDateRange()
    {
        $this->updateDateRange();
    }

    private function updateDateRange()
    {
        if ($this->dateRange === '7') {
            $this->dateFrom = Carbon::now()->subDays(7)->startOfDay();
        } elseif ($this->dateRange === '30') {
            $this->dateFrom = Carbon::now()->subDays(30)->startOfDay();
        } elseif ($this->dateRange === '90') {
            $this->dateFrom = Carbon::now()->subDays(90)->startOfDay();
        } elseif ($this->dateRange === '365') {
            $this->dateFrom = Carbon::now()->subDays(365)->startOfDay();
        } else {
            $this->dateFrom = Carbon::now()->subYears(5)->startOfDay();
        }
        $this->dateTo = Carbon::now()->endOfDay();
    }


    private function computeAnalytics()
    {
        $groupByFormat = in_array($this->dateRange, ['365', 'all']) ? 'Y-m' : 'Y-m-d';

        // Fetch Data
        $tickets = SupportTicket::whereBetween('created_at', [$this->dateFrom, $this->dateTo])->get();
        $chats = SupportCustomerChat::whereBetween('created_at', [$this->dateFrom, $this->dateTo])->get();
        $contacts = SupportContactRequest::whereBetween('created_at', [$this->dateFrom, $this->dateTo])->get();

        // 1. Support Volume Chart (Line chart)
        $volLabelsMap = [];
        
        $mergeDates = function($collection) use (&$volLabelsMap, $groupByFormat) {
            foreach($collection as $item) {
                $dateKey = $item->created_at->format($groupByFormat);
                if (!isset($volLabelsMap[$dateKey])) {
                    $volLabelsMap[$dateKey] = 0;
                }
                $volLabelsMap[$dateKey]++;
            }
        };

        $mergeDates($tickets);
        $mergeDates($chats);
        $mergeDates($contacts);

        ksort($volLabelsMap);
        
        $volLabels = [];
        $volData = [];
        foreach ($volLabelsMap as $gDate => $count) {
            $volLabels[] = $groupByFormat === 'Y-m' 
                ? Carbon::createFromFormat('Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4)
                : Carbon::createFromFormat('Y-m-d', $gDate)->format('d.m.y');
            $volData[] = $count;
        }
        $this->volumeData = ['labels' => $volLabels, 'data' => $volData];

        // 2. Source Distribution (Doughnut)
        $this->sourceData = [
            'labels' => ['Tickets', 'Live-Chats', 'Kontaktanfragen'],
            'data' => [$tickets->count(), $chats->count(), $contacts->count()]
        ];

        // 3. Ticket Status (Doughnut)
        $tStatusGrouped = $tickets->groupBy('status');
        $tLabels = [];
        $tData = [];
        foreach ($tStatusGrouped as $status => $items) {
            $tLabels[] = ucfirst((string)$status);
            $tData[] = $items->count();
        }
        $this->ticketStatusData = ['labels' => empty($tLabels) ? ['Keine Daten'] : $tLabels, 'data' => empty($tData) ? [1] : $tData];

        // 4. Chat Status (Doughnut)
        $cStatusGrouped = $chats->groupBy('status');
        $cLabels = [];
        $cData = [];
        foreach ($cStatusGrouped as $status => $items) {
            $cLabels[] = ucfirst((string)$status);
            $cData[] = $items->count();
        }
        $this->chatStatusData = ['labels' => empty($cLabels) ? ['Keine Daten'] : $cLabels, 'data' => empty($cData) ? [1] : $cData];
    }

    public function render()
    {
        $this->computeAnalytics();
        $this->dispatch('analytics-updated');

        return view('livewire.shop.support.support-analytics.support-analytics');
    }
}

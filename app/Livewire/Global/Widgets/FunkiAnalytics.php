<?php

namespace App\Livewire\Global\Widgets;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\SystemCheckConfig;
use App\Models\LoginAttempt;
use App\Models\Product\Product;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceSpecialIssue;
use App\Services\FunkiAnalyticsService;

class FunkiAnalytics extends Component
{
    use WithPagination, WithFileUploads;

    public $stats = [];
    public $healthChecks = [];
    public $rangeMode = 'year';
    protected $paginationTheme = 'tailwind';

    public $showFailedLogins = false;
    public $showFullLogins = false;
    public $dateStart;
    public $dateEnd;
    public $filterType = 'all';

    public $uploadFile;
    public array $stockUpdate = [];
    public ?string $expandedHealthKey = null;

    public $infoTexts = [
        'trend' => 'Veränderung des Umsatzes im Vergleich zum vorherigen Zeitraum gleicher Länge.',
        'marge' => 'Verhältnis von Gewinn zu Umsatz. Zeigt, wie viel Prozent vom Umsatz als Gewinn verbleiben.',
        'avg_profit' => 'Durchschnittlicher Gewinn pro Zeiteinheit innerhalb des gewählten Zeitraums.',
        'prognose' => 'Hochrechnung des Gewinns auf das Jahr basierend auf der aktuellen Performance.',
        'break_even' => 'Monatlicher Umsatz, der nötig ist, um alle fixen Kosten zu decken.',
        'offene' => 'Summe aller Rechnungen mit Status "Offen", die noch nicht beglichen wurden.',
        'fix_inc' => 'Regelmäßige Einnahmen wie Mieten oder Gehälter.',
        'shop_rev' => 'Summe aller bezahlten Bestellungen über den Online-Shop.',
        'fix_priv' => 'Regelmäßige private Ausgaben (Miete, Versicherungen, Unterhalt).',
        'fix_bus' => 'Regelmäßige geschäftliche Ausgaben (Server, Software, Miete).',
        'variabel' => 'Einmalige Ausgaben und Sonderausgaben ohne festes Intervall.'
    ];

    public function mount(FunkiAnalyticsService $service)
    {
        $this->loadSettings();
        $this->loadStats($service);
    }

    public function loadSettings()
    {
        $config = SystemCheckConfig::where('user_id', auth()->id())->first();
        if ($config) {
            $this->filterType = $config->filter_type;
            $this->dateStart = $config->date_start;
            $this->dateEnd = $config->date_end;
            $this->rangeMode = $config->range_mode ?? 'custom';
        } else {
            $this->setWholeYear(false);
        }
    }

    public function saveSettings($rangeMode = 'custom')
    {
        $this->rangeMode = $rangeMode;
        SystemCheckConfig::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'filter_type' => $this->filterType,
                'date_start' => $this->dateStart,
                'date_end' => $this->dateEnd,
                'range_mode' => $rangeMode
            ]
        );
    }

    public function setCurrentMonth($save = true)
    {
        $this->dateStart = now()->startOfMonth()->format('Y-m-d');
        $this->dateEnd = now()->endOfMonth()->format('Y-m-d');
        if ($save) {
            $this->saveSettings('current_month');
            $this->loadStats(app(FunkiAnalyticsService::class));
        }
    }

    public function setWholeYear($save = true)
    {
        $this->dateStart = now()->startOfYear()->format('Y-m-d');
        $this->dateEnd = now()->endOfYear()->format('Y-m-d');
        if ($save) {
            $this->saveSettings('year');
            $this->loadStats(app(FunkiAnalyticsService::class));
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['dateStart', 'dateEnd', 'filterType'])) {
            $this->saveSettings('custom');
            $this->loadStats(app(FunkiAnalyticsService::class));
        }
    }

    public function loadStats(FunkiAnalyticsService $service)
    {
        $allLogins = $service->getAllLoginsCollection();
        $rawStats = $service->getStats($this->dateStart, $this->dateEnd, $this->filterType, $allLogins);

        // Zwinge $stats ein Array zu sein (löst Livewire Dehydration Bugs)
        $this->stats = json_decode(json_encode($rawStats), true);

        $rawChecks = $service->getHealthChecks();
        $checks = json_decode(json_encode($rawChecks), true);

        // 1. Offene Tickets laden (Immer einbinden, auch wenn 0)
        if (class_exists(\App\Models\FunkiTicket::class)) {
            $openTickets = \App\Models\FunkiTicket::where('status', 'open')->with('customer')->get();
            $tCount = $openTickets->count();

            $checks['open_tickets'] = [
                'status' => $tCount > 0 ? 'error' : 'success',
                'icon' => 'bi-ticket-detailed',
                'title' => 'Offene Tickets',
                'message' => $tCount > 0 ? $tCount . ' Kundenanfragen warten' : 'Alles beantwortet',
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

        // 2. Produktbewertungen (Immer einbinden, auch wenn 0)
        if (class_exists(\App\Models\Product\ProductReview::class)) {
            $pendingReviews = \App\Models\Product\ProductReview::where('status', 'pending')->with('product')->get();
            $rCount = $pendingReviews->count();

            $checks['product_reviews'] = [
                'status' => $rCount > 0 ? 'error' : 'success',
                'icon' => 'bi-star-half',
                'title' => 'Produkt-Reviews',
                'message' => $rCount > 0 ? $rCount . ' Bewertungen prüfen' : 'Alle geprüft',
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

        $this->healthChecks = $checks;
        $this->dispatch('update-charts', stats: $this->stats);
    }

    public function updateStock($productId, FunkiAnalyticsService $service)
    {
        $newQty = $this->stockUpdate[$productId] ?? null;
        if ($newQty === null || $newQty < 0) return;

        $product = Product::find($productId);
        if ($product) {
            $product->update(['quantity' => $newQty]);
            unset($this->stockUpdate[$productId]);
            session()->flash('success', 'Bestand aktualisiert.');
            $this->loadStats($service);
        }
    }

    public function uploadContract($itemId, FunkiAnalyticsService $service)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $item = FinanceCostItem::find($itemId);
        if ($item) {
            $path = $this->uploadFile->store('contracts', 'public');
            $item->update(['contract_file_path' => $path]);
            $this->reset('uploadFile');
            session()->flash('success', 'Vertrag erfolgreich hochgeladen.');
            $this->loadStats($service);
        }
    }

    public function uploadSpecialReceipt($issueId, FunkiAnalyticsService $service)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $issue = FinanceSpecialIssue::find($issueId);
        if ($issue) {
            $path = $this->uploadFile->store('financial/receipts', 'public');
            $files = $issue->file_paths ?? [];
            $files[] = $path;
            $issue->update(['file_paths' => $files]);
            $this->reset('uploadFile');
            session()->flash('success', 'Beleg erfolgreich hochgeladen.');
            $this->loadStats($service);
        }
    }

    public function approveReview($id, FunkiAnalyticsService $service)
    {
        if (class_exists(\App\Models\Product\ProductReview::class)) {
            $review = \App\Models\Product\ProductReview::find($id);
            if ($review) {
                $review->status = 'approved';
                $review->save();
                session()->flash('success', 'Bewertung erfolgreich freigegeben.');
                $this->loadStats($service);
            }
        }
    }

    public function rejectReview($id, FunkiAnalyticsService $service)
    {
        if (class_exists(\App\Models\Product\ProductReview::class)) {
            $review = \App\Models\Product\ProductReview::find($id);
            if ($review) {
                $review->delete();
                session()->flash('success', 'Bewertung abgelehnt und gelöscht.');
                $this->loadStats($service);
            }
        }
    }

    public function toggleHealthCard($key)
    {
        if ($this->expandedHealthKey === $key) {
            $this->expandedHealthKey = null;
        } else {
            $this->expandedHealthKey = $key;
        }
    }

    public function getPaginatedLoginsProperty()
    {
        $service = app(FunkiAnalyticsService::class);
        $allLogins = $service->getAllLoginsCollection()->sortByDesc('last_seen')->values();

        return new LengthAwarePaginator(
            $allLogins->forPage(LengthAwarePaginator::resolveCurrentPage('loginsPage'), 8),
            $allLogins->count(),
            8,
            null,
            ['path' => request()->url(), 'pageName' => 'loginsPage']
        );
    }

    public function getPaginatedFailedLoginsProperty()
    {
        return LoginAttempt::where('success', false)->orderByDesc('attempted_at')->paginate(5, ['*'], 'failedPage');
    }

    public function render()
    {
        return view('livewire.global.widgets.funki-analytics.funki-analytics', [
            'paginatedLogins' => $this->paginatedLogins,
            'paginatedFailedLogins' => $this->paginatedFailedLogins,
        ]);
    }
}

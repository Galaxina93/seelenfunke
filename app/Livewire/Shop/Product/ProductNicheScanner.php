<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product\ProductNicheItem;
use App\Jobs\RunProductNicheCrawlerJob;
use Illuminate\Support\Facades\Cache;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ProductNicheScanner extends Component
{
    use WithPagination, WithDepartmentTheming;

    protected string $themingDepartment = 'Produkte';

    public $search = '';
    public $filterPlatform = '';
    public $filterMinScore = 0;

    // History Properties (V2)
    public $savedRuns = [];
    public $selectedRunId = null;
    public $historicalRunData = null;
    public $historicalTop3Data = null;


    public function mount()
    {
        $this->loadSavedRuns();

    }

    public function loadSavedRuns()
    {
        $this->savedRuns = \App\Models\Product\ProductNicheCrawlerRun::orderBy('created_at', 'desc')->get()->toArray();
    }

    public function loadHistoricalRun($id = null)
    {
        if (empty($id)) {
            $this->selectedRunId = null;
            $this->historicalRunData = null;
            $this->historicalTop3Data = null;
            session()->flash('message', 'Zurück zur Live-Ansicht gewechselt.');
            return;
        }

        $run = \App\Models\Product\ProductNicheCrawlerRun::find($id);
        if ($run) {
            $this->selectedRunId = $run->id;

            // Re-hydrate the JSON into Collections or arrays
            $allData = is_array($run->products_data) ? collect($run->products_data) : collect(json_decode($run->products_data, true));

            $this->historicalRunData = $allData;
            $this->historicalTop3Data = $allData->sortByDesc('niche_score')->take(3)->values();

            session()->flash('success', 'Historischen Snapshot "' . $run->name . '" geladen.');
        }
    }

    public function deleteRun($id)
    {
        \App\Models\Product\ProductNicheCrawlerRun::destroy($id);
        if ($this->selectedRunId == $id) {
            $this->loadHistoricalRun(null);
        }
        $this->loadSavedRuns();
        session()->flash('success', 'Crawler-Anfrage wurde gelöscht.');
    }

    public function saveCurrentRun()
    {
        // Prevent saving if already in historical view
        if ($this->selectedRunId) {
            session()->flash('error', 'Du betrachtest gerade einen historischen Snapshot. Dieser kann nicht erneut gespeichert werden.');
            return;
        }

        $products = ProductNicheItem::orderBy('niche_score', 'desc')->get();
        if ($products->isEmpty()) {
            session()->flash('error', 'Keine Live-Daten zum Speichern vorhanden. Lass den Crawler laufen!');
            return;
        }

        $platformStr = is_array($this->crawlPlatforms) ? implode(', ', $this->crawlPlatforms) : 'Unbekannt';
        $name = $this->crawlKeyword ? 'Suche: ' . $this->crawlKeyword . ' (' . $platformStr . ')' : 'Scanner-Lauf ' . now()->format('d.m.Y H:i');

        $run = \App\Models\Product\ProductNicheCrawlerRun::create([
            'admin_id' => auth('admin')->id() ?? 1,
            'name' => $name,
            'keyword' => $this->crawlKeyword,
            'platform' => $platformStr,
            'products_data' => $products->toArray(),
            'ai_recommendation' => null,
            'ai_agent_id' => null,
        ]);

        $this->loadSavedRuns();
        $this->loadHistoricalRun($run->id);
    }

    // For dispatching crawler
    public $crawlKeyword = 'personalisiertes geschenk';
    public $crawlPlatforms = [];

    protected $updatesQueryString = ['search', 'filterPlatform', 'filterMinScore'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function dispatchCrawler()
    {
        if (empty($this->crawlPlatforms)) {
            session()->flash('error', 'Bitte wähle mindestens eine Plattform aus.');
            return;
        }

        $activeJobs = Cache::get('active_crawler_jobs', []);

        foreach ($this->crawlPlatforms as $platform) {
            $jobId = uniqid('crawler_') . '_' . strtolower($platform);

            if (!in_array($jobId, $activeJobs)) {
                $activeJobs[] = $jobId;
            }

            Cache::put("crawler_job_{$jobId}", [
                'id' => $jobId,
                'keyword' => $this->crawlKeyword,
                'platform' => $platform,
                'progress' => 1,
                'status' => 'Job in Warteschlange...',
                'is_running' => true
            ], 600);

            RunProductNicheCrawlerJob::dispatch($jobId, $platform, $this->crawlKeyword);
        }

        Cache::put('active_crawler_jobs', $activeJobs, 3600);

        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_rec');
        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_agent');

        session()->flash('message', 'Crawler für ausgewählte Plattformen gestartet.');
        $this->crawlKeyword = '';
    }

    public function cancelCrawler($jobId)
    {
        Cache::put("cancel_crawler_{$jobId}", true, 600);

        $activeJobs = Cache::get('active_crawler_jobs', []);
        $activeJobs = array_filter($activeJobs, fn($id) => $id !== $jobId);
        Cache::put('active_crawler_jobs', array_values($activeJobs), 3600);

        Cache::forget("crawler_job_{$jobId}");

        session()->flash('message', 'Abbruch erzwungen: Crawler Job wurde aus der Anzeige entfernt.');
    }

    public function clearData()
    {
        ProductNicheItem::truncate();
        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_rec');
        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_agent');
        session()->flash('message', 'Alle Crawler-Daten wurden gelöscht.');
    }


    public function exportTop5Pdf()
    {
        $runIdParam = $this->selectedRunId ? '?product_niche_crawler_run_id=' . $this->selectedRunId : '';
        return redirect()->to(route('shop.pdf.top5-niche') . $runIdParam);
    }

    public function render()
    {
        if ($this->selectedRunId && $this->historicalRunData) {
            // HISTORICAL MODE: Bypass DB queries completely and feed arrays directly.
            $chartScores = $this->historicalRunData->groupBy('niche_score')->map(function($group) {
                return (object)['niche_score' => $group->first()['niche_score'], 'count' => $group->count()];
            })->values();

            $chartPlatforms = $this->historicalRunData->groupBy('platform')->map(function($group) {
                return (object)['platform' => $group->first()['platform'], 'count' => $group->count()];
            })->values();

            return view('livewire.shop.product.product-niche-scanner', [
                'products' => $this->historicalRunData, // Since it's <40, we don't need absolute pagination logic, but Blade expects LengthAwarePaginator. Wait, we can just pass the collection for historical. Blade might break on Links(). We will adapt the blade view.
                'top3Products' => $this->historicalTop3Data,
                'chartScores' => $chartScores,
                'chartPlatforms' => $chartPlatforms,
                'activeJobs' => [],
                'hasActiveJobs' => false,
                'isHistorical' => true
            ]);
        }

        // LIVE MODE
        $query = ProductNicheItem::query();


        if (!empty($this->search)) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        if (!empty($this->filterPlatform)) {
            $query->where('platform', $this->filterPlatform);
        }

        if ($this->filterMinScore > 0) {
            $query->where('niche_score', '>=', $this->filterMinScore);
        }

        $top3Query = clone $query;
        $top3Products = $top3Query->orderBy('niche_score', 'desc')->take(3)->get();

        $products = $query->orderBy('niche_score', 'desc')->paginate(40); // Top 40

        $chartScores = ProductNicheItem::selectRaw('niche_score, count(*) as count')->groupBy('niche_score')->get();
        $chartPlatforms = ProductNicheItem::selectRaw('platform, count(*) as count')->groupBy('platform')->get();

        $activeJobIds = Cache::get('active_crawler_jobs', []);
        $activeJobsData = [];
        $hasActiveJobs = false;

        foreach ($activeJobIds as $key => $jobId) {
            $jobData = Cache::get("crawler_job_{$jobId}");
            if ($jobData) {
                $hasActiveJobs = true;
                $activeJobsData[] = $jobData;
            } else {
                unset($activeJobIds[$key]);
            }
        }

        if (count($activeJobIds) !== count(Cache::get('active_crawler_jobs', []))) {
            Cache::put('active_crawler_jobs', array_values($activeJobIds), 3600);
        }

        return view('livewire.shop.product.product-niche-scanner', [
            'products' => $products,
            'top3Products' => $top3Products,
            'chartScores' => $chartScores,
            'chartPlatforms' => $chartPlatforms,
            'activeJobs' => $activeJobsData,
            'hasActiveJobs' => $hasActiveJobs,
            'isHistorical' => false
        ]);
    }
}

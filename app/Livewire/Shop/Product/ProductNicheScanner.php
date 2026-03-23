<?php

namespace App\Livewire\Shop\Product;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product\NicheProduct;
use App\Jobs\RunNicheCrawlerJob;
use Illuminate\Support\Facades\Cache;

class ProductNicheScanner extends Component
{
    use WithPagination;

    public $search = '';
    public $filterPlatform = '';
    public $filterMinScore = 0;
    
    // History Properties (V2)
    public $savedRuns = [];
    public $selectedRunId = null;
    public $historicalRunData = null;
    public $historicalTop3Data = null;
    
    // AI Agent Properties
    public $availableAgents = [];
    public $selectedAgentId = '';
    public $aiRecommendation = null;
    public $isRecommending = false;
    
    public function mount()
    {
        $this->availableAgents = \App\Models\Ai\AiAgent::where('is_active', true)->with('role')->orderBy('name')->get()->toArray();
        $this->loadSavedRuns();
        
        $this->aiRecommendation = \Illuminate\Support\Facades\Cache::get('niche_scanner_live_ai_rec');
        $this->selectedAgentId = \Illuminate\Support\Facades\Cache::get('niche_scanner_live_ai_agent') ?? '';
    }
    
    public function loadSavedRuns()
    {
        $this->savedRuns = \App\Models\Product\NicheCrawlerRun::orderBy('created_at', 'desc')->get()->toArray();
    }

    public function loadHistoricalRun($id = null)
    {
        if (empty($id)) {
            $this->selectedRunId = null;
            $this->historicalRunData = null;
            $this->historicalTop3Data = null;
            $this->aiRecommendation = null;
            session()->flash('message', 'Zurück zur Live-Ansicht gewechselt.');
            return;
        }

        $run = \App\Models\Product\NicheCrawlerRun::find($id);
        if ($run) {
            $this->selectedRunId = $run->id;
            
            // Re-hydrate the JSON into Collections or arrays
            $allData = is_array($run->products_data) ? collect($run->products_data) : collect(json_decode($run->products_data, true));
            
            $this->historicalRunData = $allData;
            $this->historicalTop3Data = $allData->sortByDesc('niche_score')->take(3)->values();
            
            $this->aiRecommendation = $run->ai_recommendation;
            session()->flash('success', 'Historischen Snapshot "' . $run->name . '" geladen.');
        }
    }

    public function deleteRun($id)
    {
        \App\Models\Product\NicheCrawlerRun::destroy($id);
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

        $products = NicheProduct::orderBy('niche_score', 'desc')->get();
        if ($products->isEmpty()) {
            session()->flash('error', 'Keine Live-Daten zum Speichern vorhanden. Lass den Crawler laufen!');
            return;
        }

        $platformStr = is_array($this->crawlPlatforms) ? implode(', ', $this->crawlPlatforms) : 'Unbekannt';
        $name = $this->crawlKeyword ? 'Suche: ' . $this->crawlKeyword . ' (' . $platformStr . ')' : 'Scanner-Lauf ' . now()->format('d.m.Y H:i');

        $run = \App\Models\Product\NicheCrawlerRun::create([
            'admin_id' => auth('admin')->id() ?? 1,
            'name' => $name,
            'keyword' => $this->crawlKeyword,
            'platform' => $platformStr,
            'products_data' => $products->toArray(),
            'ai_recommendation' => $this->aiRecommendation,
            'ai_agent_id' => $this->selectedAgentId ?: null,
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

            RunNicheCrawlerJob::dispatch($jobId, $platform, $this->crawlKeyword);
        }
        
        Cache::put('active_crawler_jobs', $activeJobs, 3600);
        
        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_rec');
        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_agent');
        $this->aiRecommendation = null;

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
        NicheProduct::truncate();
        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_rec');
        \Illuminate\Support\Facades\Cache::forget('niche_scanner_live_ai_agent');
        $this->aiRecommendation = null;
        session()->flash('message', 'Alle Crawler-Daten wurden gelöscht.');
    }

    public function startAiRecommendation()
    {
        if (empty($this->selectedAgentId)) {
            session()->flash('error', 'Bitte wähle zuerst einen KI-Agenten aus.');
            return;
        }

        $this->isRecommending = true;
        
        $agent = \App\Models\Ai\AiAgent::find($this->selectedAgentId);
        if (!$agent) {
            $this->isRecommending = false;
            session()->flash('error', 'Agent nicht gefunden.');
            return;
        }

        // Get Top 3 based on current filters
        $query = NicheProduct::query();
        if (!empty($this->filterPlatform)) $query->where('platform', $this->filterPlatform);
        if ($this->filterMinScore > 0) $query->where('niche_score', '>=', $this->filterMinScore);
        $top3 = $query->orderBy('niche_score', 'desc')->take(3)->get();

        if ($top3->count() < 3) {
            $this->isRecommending = false;
            session()->flash('error', 'Es werden mindestens 3 Produkte für die Analyse benötigt.');
            return;
        }

        $txData = $top3->map(function ($p, $index) {
            return [
                'ranking' => $index + 1,
                'title' => $p->title,
                'price' => $p->price,
                'reviews' => $p->reviews,
                'score' => $p->niche_score,
                'url' => $p->url
            ];
        })->toJson();

        $prompt = "Du bist ein erfahrener E-Commerce Experte und Berater für einen Laser-Graveur.\n";
        $prompt .= "Der Nutzer hat einen CO2 und Faser-Laser. Er kann Produkte direkt gravieren, personalisieren und verschicken.\n";
        $prompt .= "WICHTIGE EINSCHRÄNKUNGEN FÜR PRODUKTE:\n";
        $prompt .= "- Maximale Größe für Trophäen/Acryl: 200x200x40mm\n";
        $prompt .= "- Maximale Größe für Schieferplatten: 180x180mm\n";
        $prompt .= "- Geeignete und sehr gute Artikel: Schlüsselanhänger, Flaschenöffner, Kugelschreiber, Weingläser, kleine Holzboxen.\n";
        $prompt .= "- UNGEEIGNETE ARTIKEL (zu groß): Schränke, Stühle, große Holzfässer, Bilderrahmen größer als A4, massive Tische.\n\n";
        
        $prompt .= "Hier sind die Top 3 Nischen-Produkte aus dem aktuellen Crawler-Scan:\n$txData\n\n";
        $prompt .= "DEINE AUFGABE:\n";
        $prompt .= "Analysiere diese 3 Produkte. Entscheide dich für EXAKT EIN Produkt, das unter Berücksichtigung der Maschinen-Einschränkungen das allerbeste Potenzial für den Laser-Graveur bietet.\n";
        $prompt .= "Erkläre deine Wahl in 3-4 überzeugenden, kurzen Sätzen. Schreibe kein JSON, sondern direkt den Antwort-Text für den Nutzer.\n";

        try {
            $response = \Illuminate\Support\Facades\Http::withToken(config('services.mittwald.key'))
                ->timeout(60)
                ->post(config('services.mittwald.url') . '/chat/completions', [
                    'model' => $agent->model ?? 'gpt-oss-120b',
                    'messages' => [
                        ['role' => 'system', 'content' => $agent->system_prompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.6,
                ]);

            if ($response->successful()) {
                $this->aiRecommendation = $response->json()['choices'][0]['message']['content'] ?? 'Keine Antwort erhalten.';
                
                // Store in cache so it persists on reload
                \Illuminate\Support\Facades\Cache::put('niche_scanner_live_ai_rec', $this->aiRecommendation);
                \Illuminate\Support\Facades\Cache::put('niche_scanner_live_ai_agent', $this->selectedAgentId);
                
                session()->flash('success', 'Der KI-Agent hat die Produkte analysiert.');
            } else {
                session()->flash('error', 'API Verbindungsfehler zum LLM: ' . $response->status());
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler während der KI-Verarbeitung: ' . $e->getMessage());
        }

        $this->isRecommending = false;
    }

    public function exportTop5Pdf()
    {
        $runIdParam = $this->selectedRunId ? '?run_id=' . $this->selectedRunId : '';
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
        $query = NicheProduct::query();


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

        $chartScores = NicheProduct::selectRaw('niche_score, count(*) as count')->groupBy('niche_score')->get();
        $chartPlatforms = NicheProduct::selectRaw('platform, count(*) as count')->groupBy('platform')->get();

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

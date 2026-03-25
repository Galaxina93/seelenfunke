<?php

namespace App\Services\AI\Functions;

use App\Models\Product\NicheProduct;
use App\Models\Product\NicheCrawlerRun;
use App\Jobs\RunNicheCrawlerJob;
use Illuminate\Support\Facades\Cache;

trait AiProductNicheScannerFuncs
{
    /**
     * Define the Product Niche Scanner tools for the Analyst Agent
     */
    public static function getAiProductNicheScannerFuncsSchema(): array
    {
        return [
            [
                'name' => 'niche_run_crawler',
                'description' => 'Startet einen im Hintergrund laufenden Crawler-Job für die tiefgehende Markt- und Nischenforschung auf Marktplätzen (Etsy/Amazon). Da das Auswerten von Webseiten dauert (ca. 1-2 Minuten), informiere den Nutzer im Chat, dass er gleich nach den Ergebnissen fragen soll.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Der exakte Suchbegriff (z.B. "personalisierte tasse weihnachten").'],
                        'platforms' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => ['Etsy', 'Amazon']],
                            'description' => 'Die Zielplattform(en). Meist macht es Sinn, beide Plattformen zu scannen.'
                        ]
                    ],
                    'required' => ['keyword', 'platforms']
                ],
                'callable' => [self::class, 'executeRunCrawler']
            ],
            [
                'name' => 'niche_get_live_data',
                'description' => 'Liest die aktuellen Live-Rankings des Nischen-Scanners aus der Datenbank (immer die Ergebnisse des ZULETZT gestarteten niche_run_crawler Jobs). Rufe dies auf, um dem Nutzer 1-2 finale Empfehlungen für Produkte abzuleiten, die man mit dem CO2- oder Faserlaser rentabel gravieren könnte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Maximale Anzahl abzurufender Top-Produkte (default: 10, max: 25).']
                    ]
                ],
                'callable' => [self::class, 'executeGetLiveData']
            ],
            [
                'name' => 'niche_save_live_run',
                'description' => 'Speichert die temporären Live-Scans sicher ins Archiv ab, damit sie nicht vom nächsten Crawler-Lauf überschrieben werden. Mach dies, wenn du oder der Nutzer ein Set an Suchergebnissen besonders erfolgreich und speicherungswürdig findest.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Ein aussagekräftiger Titel für das Archiv (z.B. "Top Tassen Analyse Q4 2026").']
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeSaveLiveRun']
            ],
            [
                'name' => 'niche_get_historical_runs',
                'description' => 'Gibt dir eine Liste aller jemals GESPEICHERTEN (archivierten) Nischen-Scans zurück (Meta-Daten wie Name und ID).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeGetHistoricalRuns']
            ],
            [
                'name' => 'niche_get_historical_data',
                'description' => 'Liest die konkreten Analyse-Produkte aus einem spezifisch ausgewählten, historischen (gespeicherten) Scan aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'run_id' => ['type' => 'integer', 'description' => 'Die ID des gespeicherten Scans aus niche_get_historical_runs.']
                    ],
                    'required' => ['run_id']
                ],
                'callable' => [self::class, 'executeGetHistoricalData']
            ],
        ];
    }

    public static function executeRunCrawler(array $args)
    {
        try {
            if (empty($args['keyword']) || empty($args['platforms'])) {
                return ['status' => 'error', 'message' => 'Keyword und Plattform-Array fehlen.'];
            }

            // Flush old live data
            NicheProduct::truncate();
            Cache::forget('niche_scanner_live_ai_rec');
            Cache::forget('niche_scanner_live_ai_agent');

            $activeJobs = Cache::get('active_crawler_jobs', []);
            $dispatchedJobs = [];

            foreach ($args['platforms'] as $platform) {
                $jobId = uniqid('crawler_') . '_' . strtolower($platform);
                
                if (!in_array($jobId, $activeJobs)) {
                    $activeJobs[] = $jobId;
                }
                
                Cache::put("crawler_job_{$jobId}", [
                    'id' => $jobId,
                    'keyword' => $args['keyword'],
                    'platform' => $platform,
                    'progress' => 1,
                    'status' => 'Job gestartet via AI Agent...',
                    'is_running' => true
                ], 600);

                RunNicheCrawlerJob::dispatch($jobId, $platform, $args['keyword']);
                $dispatchedJobs[] = $jobId;
            }
            
            Cache::put('active_crawler_jobs', $activeJobs, 3600);

            return [
                'status' => 'success',
                'message' => 'Der Crawler wurde gestartet. Plattformen: ' . implode(', ', $args['platforms']) . '. Sag dem Nutzer unbedingt, dass er ca. 1-2 Minuten warten soll, und erhalte dann Live-Ergebnisse mit niche_get_live_data.',
                'dispatched_jobs' => $dispatchedJobs
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Starten des Crawlers: ' . $e->getMessage()];
        }
    }

    public static function executeGetLiveData(array $args)
    {
        try {
            $limit = isset($args['limit']) ? min((int)$args['limit'], 25) : 10;
            
            $products = NicheProduct::orderBy('niche_score', 'desc')->take($limit)->get();

            if ($products->isEmpty()) {
                return [
                    'status' => 'success',
                    'message' => 'Die Live-Datenbank ist aktuell leer. Zeigt an, dass der Scan entweder aktuell noch läuft (bitte noch 30 Sekunden warten) oder noch kein Scan gestartet wurde.'
                ];
            }

            return [
                'status' => 'success',
                'total_live_items' => NicheProduct::count(),
                'top_products' => $products->map(function($p) {
                    return [
                        'title' => $p->title,
                        'platform' => $p->platform,
                        'price' => $p->price,
                        'reviews' => $p->reviews,
                        'niche_score' => $p->niche_score,
                        'url' => $p->url
                    ];
                })->toArray()
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeSaveLiveRun(array $args)
    {
        try {
            if (empty($args['name'])) {
                return ['status' => 'error', 'message' => 'Bitte einen Namens-Titel für das Archiv vergeben.'];
            }

            $products = NicheProduct::orderBy('niche_score', 'desc')->get();
            if ($products->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine Live-Daten zum Speichern vorhanden.'];
            }

            // Platform String extrahieren:
            $platforms = $products->pluck('platform')->unique()->implode(', ');

            // Normalerweise steht das Keyword nur Session-basiert bereit, daher Hardcodierter Fallback mit AI Hinweis
            $run = NicheCrawlerRun::create([
                'admin_id' => 1,
                'name' => $args['name'],
                'keyword' => 'AI Initiated Scan Output',
                'platform' => $platforms,
                'products_data' => $products->toArray(),
            ]);

            return [
                'status' => 'success',
                'message' => "Live Scorecard erfolgreich archiviert unter der History-ID: {$run->id}."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetHistoricalRuns(array $args)
    {
        try {
            // Get last 20 runs
            $runs = NicheCrawlerRun::orderBy('created_at', 'desc')->take(20)->get()->map(function($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'keyword' => $r->keyword,
                    'platform' => $r->platform,
                    'created_at' => $r->created_at->format('Y-m-d H:i')
                ];
            });

            return [
                'status' => 'success',
                'archives' => $runs->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetHistoricalData(array $args)
    {
        try {
            if (empty($args['run_id'])) return ['status' => 'error', 'message' => 'run_id fehlt.'];

            $run = NicheCrawlerRun::find($args['run_id']);
            if (!$run) return ['status' => 'error', 'message' => 'Historischer Lauf nicht gefunden.'];

            $data = is_array($run->products_data) ? collect($run->products_data) : collect(json_decode($run->products_data, true));

            return [
                'status' => 'success',
                'run_name' => $run->name,
                'top_products' => $data->sortByDesc('niche_score')->take(15)->map(function($p) {
                    return [
                        'title' => $p['title'] ?? '',
                        'platform' => $p['platform'] ?? '',
                        'price' => $p['price'] ?? '',
                        'reviews' => $p['reviews'] ?? '',
                        'niche_score' => $p['niche_score'] ?? '',
                        'url' => $p['url'] ?? ''
                    ];
                })->values()->toArray()
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

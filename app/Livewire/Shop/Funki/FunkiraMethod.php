<?php

namespace App\Livewire\Shop\Funki;

use Livewire\Component;
use App\Services\AI\AIFunctionsRegistry;
use App\Models\Funki\FunkiraToolUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class FunkiraMethod extends Component
{
    public function getToolUsageStatsProperty()
    {
        if (!class_exists(FunkiraToolUsage::class)) return ['total' => [], 'chart' => []];

        // 1. Total usage per tool
        $totals = FunkiraToolUsage::selectRaw('tool_name, COUNT(*) as count')
            ->groupBy('tool_name')
            ->pluck('count', 'tool_name')
            ->toArray();

        // 2. Timeline chart (last 7 days)
        $chartData = ['labels' => [], 'data' => []];
        $startDate = Carbon::now()->subDays(6)->startOfDay();
        
        $dailyUsages = FunkiraToolUsage::where('used_at', '>=', $startDate)
            ->selectRaw('DATE(used_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        for ($i = 0; $i < 7; $i++) {
            $dateStr = $startDate->copy()->addDays($i)->format('Y-m-d');
            $displayDate = $startDate->copy()->addDays($i)->format('d.m.');
            
            $dayData = $dailyUsages->firstWhere('date', $dateStr);
            $chartData['labels'][] = $displayDate;
            $chartData['data'][] = $dayData ? $dayData->count : 0;
        }

        return [
            'total' => $totals,
            'chart' => $chartData
        ];
    }

    public function render()
    {
        $methods = $this->getAiMethods();
        $usageStats = $this->toolUsageStats;

        // Append usage count to methods
        $methods = $methods->map(function ($m) use ($usageStats) {
            $m['usage_count'] = $usageStats['total'][$m['name']] ?? 0;
            return $m;
        });

        return view('livewire.shop.funki.funkira-method', [
            'methods' => $methods,
            'systemCoverage' => $this->getSystemCoverage(),
            'chartData' => $usageStats['chart']
        ]);
    }

    private function getAiMethods()
    {
        // Wir holen die rohen Schema-Daten vom AIFunctionsRegistry
        $schema = AIFunctionsRegistry::getSchema();
        
        $methods = [];
        foreach ($schema as $tool) {
            $methods[] = [
                'name' => $tool['function']['name'] ?? 'Unbekannt',
                'description' => $tool['function']['description'] ?? 'Keine Beschreibung vorhanden.',
                'parameters' => $tool['function']['parameters']['properties'] ?? []
            ];
        }
        
        // Priorisierte Reihenfolge der wichtigsten Tools (von extrem wichtig nach unwichtig)
        $priorityOrder = [
            'get_current_mission',
            'get_finances',
            'get_order',
            'check_inventory',
            'get_todos',
            'create_todo',
            'complete_todo',
            'search_memory',
            'save_memory',
            'get_tickets',
            'get_system_health',
            'get_system_logs',
            'fix_system_errors',
            'get_calendar_events',
            'get_day_routines',
            'get_product_reviews',
            'get_gamification_leaderboard',
            'search_customers',
            'get_next_order_deadline',
            'check_missing_expenses',
            'read_wiki_files',
            'get_system_map',
            'close_ui',
            'open_nav_item',
            'open_zentrum',
            'get_graphical_capabilities',
            'delete_todo'
        ];

        // Sortieren nach Priorität
        usort($methods, function ($a, $b) use ($priorityOrder) {
            $posA = array_search($a['name'], $priorityOrder);
            $posB = array_search($b['name'], $priorityOrder);

            // Wenn beide Tools nicht in der Liste stehen, alphabetisch sortieren
            if ($posA === false && $posB === false) {
                return strcmp($a['name'], $b['name']);
            }
            
            // Unbekannte Tools kommen ans Ende
            if ($posA === false) return 1;
            if ($posB === false) return -1;

            return $posA <=> $posB;
        });

        return collect($methods);
    }

    /**
     * Erstellt eine "Coverage-Matrix", was die KI derzeit kann im Vergleich 
     * zu dem, was das System potenziell an Features anbietet.
     */
    private function getSystemCoverage()
    {
        $schema = AIFunctionsRegistry::getSchema();
        $toolNames = collect($schema)->pluck('function.name')->toArray();

        $modules = [
            'Finanzen & Buchhaltung' => [
                'icon' => 'bi-wallet2',
                'description' => 'Zugriff auf Umsätze, Margen, Kosten und Bilanzen.',
                'features' => [
                    'Umsatzdaten einsehen' => in_array('get_financial_data', $toolNames),
                    'Kostenstruktur analysieren' => in_array('get_cost_data', $toolNames),
                    'Gutschriften (Credits) verwalten' => in_array('get_credits', $toolNames),
                    'Verträge ansehen' => class_exists(\App\Models\Finance\FinanceCostItem::class) && false, // Hat sie noch nicht
                ]
            ],
            'Marketing & Kundenakquise' => [
                'icon' => 'bi-megaphone',
                'description' => 'Verwaltung von Newslettern, Gutscheinen und Kampagnen.',
                'features' => [
                    'Gutscheine generieren' => in_array('generate_voucher', $toolNames),
                    'Gutschein-Performance' => in_array('get_voucher_performance', $toolNames),
                    'Newsletter verwalten' => class_exists(\App\Models\Newsletter\NewsletterCampaign::class) && false, // Fehlt
                    'Kunden werben Kunden (Referrals)' => false, // Fehlt im System komplett
                ]
            ],
            'Produkte & Lager (PIM/ERP)' => [
                'icon' => 'bi-box-seam',
                'description' => 'Kontrolle über Artikel, Bestände und Lieferanten.',
                'features' => [
                    'Produktkatalog lesen' => in_array('get_products', $toolNames),
                    'Bestandsmengen einsehen' => config('shop.features.inventory_tracking', true) && in_array('get_products', $toolNames),
                    'Neue Produkte erstellen' => false,
                    'Lieferantenbestellungen' => false,
                ]
            ],
            'Aufgaben & Systemsteuerung' => [
                'icon' => 'bi-cpu',
                'description' => 'Selbstregulation, Aufgabenverwaltung und Error-Handling.',
                'features' => [
                    'To-Do Listen verwalten' => in_array('create_todo', $toolNames),
                    'System-Gesundheit prüfen' => in_array('get_system_health', $toolNames),
                    'Fehler beheben (Auto-Heal)' => in_array('fix_system_errors', $toolNames),
                    'Logbücher lesen' => in_array('get_system_logs', $toolNames),
                    'Routinen prüfen' => in_array('get_day_routines', $toolNames),
                    'Seiten-Navigation' => in_array('open_nav_item', $toolNames),
                ]
            ],
            'Wissen & Gedächtnis' => [
                'icon' => 'bi-brain',
                'description' => 'Abruf von Unternehmenswissen und Langzeitgedächtnis.',
                'features' => [
                    'Kurzzeitgedächtnis (Chat-History)' => true, // Ist im Agent fest verbaut
                    'Langzeitgedächtnis (Wiki)' => in_array('search_memory', $toolNames) || in_array('save_memory', $toolNames),
                    'Dokumente lesen (.docx, .pdf)' => in_array('read_wiki_files', $toolNames),
                    'System-Architektur kennen' => in_array('get_system_map', $toolNames),
                ]
            ],
        ];

        return collect($modules);
    }
}

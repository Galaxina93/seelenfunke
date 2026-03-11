<?php

namespace App\Livewire\Shop\Funki;

use Livewire\Component;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Support\Facades\Schema;

class FunkiraMethod extends Component
{
    public function render()
    {
        return view('livewire.shop.funki.funkira-method', [
            'methods' => $this->getAiMethods(),
            'systemCoverage' => $this->getSystemCoverage()
        ]);
    }

    /**
     * Holt sich alle aktuell registrierten AI-Tools aus der Registry.
     */
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
        
        // Alphabetisch sortieren
        usort($methods, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
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

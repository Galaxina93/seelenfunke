<?php

namespace App\Livewire\Shop\Ai;

use Livewire\Attributes\Layout;

use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

class AiVisualizationRegistry extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';
    /**
     * Diese Component dient als reines Backend-Dashboard für den Entwickler,
     * um zu dokumentieren, welche Headless Generative UI Blöcke gebaut wurden.
     */
    public array $registry = [
        'voucher' => [
            'name' => 'Gutscheine & Rabatte',
            'description' => 'Zeigt Rabattcodes aus der Datenbank.',
            'views' => [
                'Einzel-Kachel' => 'livewire.global.ai.blocks.voucher-single',
                'Tabellen-Ansicht' => 'livewire.global.ai.blocks.voucher-table'
            ],
            'status' => 'active'
        ],
        'customer' => [
            'name' => 'Kundenakten',
            'description' => 'Zeigt Userprofile und Bestellhistorien.',
            'views' => [
                'Profil-Karte' => 'livewire.shop.ai.blocks.customer-profile'
            ],
            'status' => 'active'
        ],
        'task' => [
            'name' => 'Aufgaben & Tasks',
            'description' => 'Zeigt die persönliche Aufgabenliste.',
            'views' => [
                'Listen-Ansicht' => 'livewire.shop.ai.blocks.task-list'
            ],
            'status' => 'active'
        ],
        'person' => [
            'name' => 'Personenprofile',
            'description' => 'Zeigt strukturierte Kontaktkarten und Personendaten mit interaktiven Links.',
            'views' => [
                'Profil-Karte' => 'livewire.shop.ai.blocks.person-profile'
            ],
            'status' => 'active'
        ],
        'code' => [
            'name' => 'Code Snippets',
            'description' => 'Erlaubt dem System, Quellcode visuell in einem modernen IDE-mäßigen Editor-Fenster anzuzeigen.',
            'views' => [
                'Code Viewer' => 'livewire.shop.ai.blocks.code-viewer'
            ],
            'status' => 'active'
        ],
        'supplier' => [
            'name' => 'Lieferanten & Händler',
            'description' => 'Zeigt die detaillierte Profilkarte eines Lieferanten inklusive Produkte, Logistik-Infos und Kontaktwegen.',
            'views' => [
                'Profil-Karte' => 'livewire.shop.ai.blocks.supplier-profile'
            ],
            'status' => 'active'
        ]
    ];

    public function render()
    {
        return view('livewire.shop.ai.ai-visualization-registry');
    }
}

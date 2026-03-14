<?php

namespace App\Livewire\System\Funkira;

use Livewire\Component;

class VisualizationRegistry extends Component
{
    /**
     * Diese Component dient als reines Backend-Dashboard für den Entwickler,
     * um zu dokumentieren, welche Headless Generative UI Blöcke gebaut wurden.
     */
    public array $registry = [
        'voucher' => [
            'name' => 'Gutscheine & Rabatte',
            'description' => 'Zeigt Rabattcodes aus der Datenbank.',
            'views' => [
                'Einzel-Kachel' => 'livewire.global.funkira.blocks.voucher-single',
                'Tabellen-Ansicht' => 'livewire.global.funkira.blocks.voucher-table'
            ],
            'status' => 'active'
        ],
        'customer' => [
            'name' => 'Kundenakten',
            'description' => 'Zeigt Userprofile und Bestellhistorien.',
            'views' => [
                'Tabellen-Ansicht' => 'Fehlt noch (Fallback JSON)'
            ],
            'status' => 'pending'
        ],
        'task' => [
            'name' => 'Aufgaben & Tasks',
            'description' => 'Zeigt die persönliche Aufgabenliste.',
            'views' => [
                'Listen-Ansicht' => 'Fehlt noch (Fallback JSON)'
            ],
            'status' => 'pending'
        ],
        'person' => [
            'name' => 'Personenprofile',
            'description' => 'Zeigt strukturierte Kontaktkarten und Personendaten mit interaktiven Links.',
            'views' => [
                'Profil-Karte' => 'livewire.global.funkira.blocks.person-profile'
            ],
            'status' => 'active'
        ]
    ];

    public function render()
    {
        return view('livewire.system.funkira.visualization-registry');
    }
}

<?php

namespace App\Livewire\Global\Widgets;

use Livewire\Component;
use Livewire\Attributes\Reactive;

class SystemCheckKPI extends Component
{
    #[Reactive]
    public $stats = [];

    // Erklärungen für die Tooltips
    public $infoTexts = [
        'trend'      => 'Veränderung des Umsatzes im Vergleich zum vorherigen Zeitraum gleicher Länge.',
        'marge'      => 'Verhältnis von Gewinn zu Umsatz. Zeigt, wie viel Prozent vom Umsatz als Gewinn verbleiben.',
        'avg_profit' => 'Durchschnittlicher Gewinn pro Zeiteinheit innerhalb des gewählten Zeitraums.',
        'prognose'   => 'Hochrechnung des Gewinns auf das Jahr basierend auf der aktuellen Performance.',
        'break_even' => 'Monatlicher Umsatz, der nötig ist, um alle fixen Kosten zu decken.',
        'offene'     => 'Summe aller Rechnungen mit Status "Offen", die noch nicht beglichen wurden.',
        'fix_inc'    => 'Regelmäßige Einnahmen wie Mieten oder Gehälter.',
        'shop_rev'   => 'Summe aller bezahlten Bestellungen über den Online-Shop.',
        'fix_priv'   => 'Regelmäßige private Ausgaben (Miete, Versicherungen, Unterhalt).',
        'fix_bus'    => 'Regelmäßige geschäftliche Ausgaben (Server, Software, Miete).',
        'variabel'   => 'Einmalige Ausgaben und Sonderausgaben ohne festes Intervall.'
    ];

    public function render()
    {
        return view('livewire.global.widgets.system-check.system-check-k-p-i');
    }
}

<?php

namespace App\Livewire\Shop\Shipping;

use App\Models\Shipping\ShippingRate;
use App\Models\Shipping\ShippingZone;
use App\Models\Shipping\ShippingZoneCountry;
use Livewire\Component;

class Shipping extends Component
{
    // --- STATE ---
    public $view = 'list'; // 'list', 'edit'
    public $activeZoneId = null;

    // --- FORM DATA (Zone Edit) ---
    public $zoneName;
    public $zoneCountries = []; // Aktuell zugeordnete Länder
    public $availableCountries = []; // Länder, die noch frei sind

    // --- FORM DATA (Rate Add) ---
    public $newRate = [
        'name' => '',
        'min_weight' => 0,
        'max_weight' => null,
        'min_price' => 0, // Cent
        'price' => 0, // Euro (wird konvertiert)
    ];

    // --- FORM DATA (Country Add) ---
    public $selectedCountryToAdd = '';

    // --- FARBPALETTE FÜR DIE KARTE ---
    protected $zoneColors = [
        '#4F46E5', // Indigo (Zone 1)
        '#10B981', // Emerald (Zone 2)
        '#F59E0B', // Amber (Zone 3)
        '#EC4899', // Pink (Zone 4)
        '#3B82F6', // Blue (Zone 5)
        '#8B5CF6', // Violet (Zone 6)
    ];

    // --- LISTENER ---
    protected $listeners = ['refreshComponent' => '$refresh'];

    public function mount()
    {
        $this->refreshAvailableCountries();
    }

    // --- NAVIGATION ---

    public function createZone()
    {
        $this->resetInput();
        $this->view = 'create';
    }

    public function editZone($id)
    {
        $this->resetInput();
        $this->activeZoneId = $id;
        $zone = ShippingZone::with(['countries', 'rates'])->findOrFail($id);

        $this->zoneName = $zone->name;
        $this->view = 'edit';

        $this->refreshAvailableCountries();
    }

    public function cancel()
    {
        $this->resetInput();
        $this->view = 'list';
    }

    private function resetInput()
    {
        $this->activeZoneId = null;
        $this->zoneName = '';
        $this->zoneCountries = [];
        $this->selectedCountryToAdd = '';
        $this->newRate = [
            'name' => 'Standard',
            'min_weight' => 0,
            'max_weight' => null,
            'min_price' => 0,
            'price' => 0,
        ];
        $this->resetValidation();
    }

    // --- ZONEN LOGIK ---

    public function saveZone()
    {
        $this->validate([
            'zoneName' => 'required|string|min:2|max:50',
        ]);

        if ($this->view === 'create') {
            $zone = ShippingZone::create(['name' => $this->zoneName]);
            $this->activeZoneId = $zone->id;
            session()->flash('success', 'Versandzone erstellt. Füge nun Länder und Tarife hinzu.');
            $this->view = 'edit'; // Direkt in den Edit-Modus springen
        } else {
            $zone = ShippingZone::findOrFail($this->activeZoneId);
            $zone->update(['name' => $this->zoneName]);
            session()->flash('success', 'Versandzone aktualisiert.');
        }
    }

    public function deleteZone($id)
    {
        ShippingZone::destroy($id);
        session()->flash('success', 'Zone gelöscht.');
    }

    // --- LÄNDER LOGIK ---

    public function addCountry()
    {
        $this->validate([
            'selectedCountryToAdd' => 'required|size:2',
        ]);

        // Prüfen ob Land schon vergeben (Safety check)
        $exists = ShippingZoneCountry::where('country_code', $this->selectedCountryToAdd)->exists();
        if ($exists) {
            $this->addError('selectedCountryToAdd', 'Dieses Land ist bereits einer anderen Zone zugeordnet.');
            return;
        }

        ShippingZoneCountry::create([
            'shipping_zone_id' => $this->activeZoneId,
            'country_code' => $this->selectedCountryToAdd
        ]);

        $this->selectedCountryToAdd = '';
        $this->refreshAvailableCountries();
        session()->flash('success', 'Land hinzugefügt.');
    }

    public function removeCountry($id)
    {
        ShippingZoneCountry::destroy($id);
        $this->refreshAvailableCountries();
    }

    /**
     * Lädt alle Länder aus der Config und filtert die raus,
     * die schon in IRGENDEINER Zone sind (außer der aktuellen).
     */
    public function refreshAvailableCountries()
    {
        /**
         * NEUE LOGIK: Wir laden die Liste der aktiven Lieferländer
         * direkt aus deinen neuen Shop-Settings.
         */
        $allCountries = shop_setting('active_countries', []);

        // Alle Länder-Codes holen, die bereits einer Versandzone zugewiesen wurden
        $assignedCodes = ShippingZoneCountry::pluck('country_code')->toArray();

        /**
         * Wir filtern die Liste: Nur Länder, die du im Shop-Config-Backend
         * als "aktiv" markiert hast UND die noch keiner Zone zugewiesen wurden,
         * sollen zur Auswahl stehen.
         */
        $this->availableCountries = array_filter($allCountries, function($code) use ($assignedCodes) {
            return !in_array($code, $assignedCodes);
        }, ARRAY_FILTER_USE_KEY);
    }

    // --- TARIF (RATES) LOGIK ---

    public function addRate()
    {
        $this->validate([
            'newRate.name' => 'required|string|min:2',
            'newRate.min_weight' => 'required|numeric|min:0',
            'newRate.max_weight' => 'nullable|numeric|gt:newRate.min_weight',
            'newRate.price' => 'required|numeric|min:0',
        ]);

        ShippingRate::create([
            'shipping_zone_id' => $this->activeZoneId,
            'name' => $this->newRate['name'],
            'min_weight' => $this->newRate['min_weight'], // Gramm
            'max_weight' => $this->newRate['max_weight'], // Gramm oder Null
            'min_price' => 0, // Optional, hier erstmal simple
            'price' => (int) ($this->newRate['price'] * 100), // Euro -> Cent
        ]);

        // Reset Rate Form
        $this->newRate = [
            'name' => '',
            'min_weight' => 0,
            'max_weight' => null,
            'min_price' => 0,
            'price' => 0,
        ];

        session()->flash('success', 'Versandtarif hinzugefügt.');
    }

    public function removeRate($rateId)
    {
        ShippingRate::destroy($rateId);
        session()->flash('success', 'Tarif gelöscht.');
    }

    /**
     * Generiert CSS-Regeln und Legendendaten für die Karte.
     * Dies vermeidet den JS-Fehler mit 'series'.
     */
    public function getMapVisualsProperty()
    {
        $zones = ShippingZone::with('countries')->get();
        $css = "";
        $legend = [];
        $activeCodes = []; // Liste aller aktiven Ländercodes für Tooltips

        foreach ($zones as $index => $zone) {
            $color = $this->zoneColors[$index % count($this->zoneColors)];
            $legend[$zone->name] = $color;

            foreach ($zone->countries as $country) {
                $code = strtoupper($country->country_code);
                // WICHTIG: !important überschreibt den Standard-Fill des SVG
                $css .= ".jvm-region[data-code='{$code}'] { fill: {$color} !important; } ";
                $activeCodes[$code] = $zone->name;
            }
        }

        return [
            'css' => $css,
            'legend' => $legend,
            'activeCodes' => $activeCodes
        ];
    }

    // --- RENDER ---

    public function render()
    {
        // Daten für Dashboard Header
        $stats = [
            'zones' => ShippingZone::count(),
            'countries_covered' => ShippingZoneCountry::count(),
            'rates' => ShippingRate::count(),
        ];

        // Daten für Listenansicht
        $zones = ShippingZone::withCount(['countries', 'rates'])->get();

        // Daten für Edit-Ansicht (nur laden wenn nötig)
        $activeZoneData = null;
        if ($this->activeZoneId) {
            $activeZoneData = ShippingZone::with(['countries', 'rates' => function($q) {
                $q->orderBy('min_weight', 'asc');
            }])->find($this->activeZoneId);
        }

        return view('livewire.shop.shipping.shipping', [
            'zones' => $zones,
            'stats' => $stats,
            'activeZoneModel' => $activeZoneData,
            'mapVisuals' => $this->mapVisuals
        ]);
    }
}

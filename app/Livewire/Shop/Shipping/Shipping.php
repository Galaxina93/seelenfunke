<?php

namespace App\Livewire\Shop\Shipping;

use App\Models\Shipping\ShippingRate;
use App\Models\Shipping\ShippingZone;
use App\Models\Shipping\ShippingZoneCountry;
use Livewire\Component;

class Shipping extends Component
{
    // --- STATE ---
    public $view = 'list'; // 'list', 'edit', 'create'
    public $activeZoneId = null;

    // --- FORM DATA (Zone Edit) ---
    public $zoneName;
    public $zoneCountries = [];
    public $availableCountries = [];

    // --- FORM DATA (Rate Add) ---
    public $newRate = [
        'name' => '',
        'min_weight' => 0,
        'max_weight' => null,
        'min_price' => 0,
        'price' => 0,
    ];

    // --- FORM DATA (Country Add) ---
    public $selectedCountryToAdd = '';

    // --- FARBPALETTE FÜR DIE KARTE ---
    protected $zoneColors = [
        '#4F46E5', // Indigo
        '#10B981', // Emerald
        '#F59E0B', // Amber
        '#EC4899', // Pink
        '#3B82F6', // Blue
        '#8B5CF6', // Violet
    ];

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function mount()
    {
        $this->refreshAvailableCountries();
    }

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

    public function saveZone()
    {
        $this->validate([
            'zoneName' => 'required|string|min:2|max:50',
        ]);

        if ($this->view === 'create') {
            $zone = ShippingZone::create(['name' => $this->zoneName]);
            $this->activeZoneId = $zone->id;
            session()->flash('success', 'Versandzone erstellt. Füge nun Länder hinzu.');
            $this->view = 'edit';
        } else {
            $zone = ShippingZone::findOrFail($this->activeZoneId);
            $zone->update(['name' => $this->zoneName]);
            session()->flash('success', 'Versandzone aktualisiert.');
        }

        $this->dispatchMapUpdate();
    }

    public function deleteZone($id)
    {
        ShippingZone::destroy($id);
        session()->flash('success', 'Zone gelöscht.');
        $this->cancel();
        $this->dispatchMapUpdate();
    }

    public function addCountry()
    {
        $this->validate([
            'selectedCountryToAdd' => 'required|size:2',
        ]);

        $exists = ShippingZoneCountry::where('country_code', $this->selectedCountryToAdd)->exists();
        if ($exists) {
            $this->addError('selectedCountryToAdd', 'Dieses Land ist bereits zugeordnet.');
            return;
        }

        ShippingZoneCountry::create([
            'shipping_zone_id' => $this->activeZoneId,
            'country_code' => $this->selectedCountryToAdd
        ]);

        $this->selectedCountryToAdd = '';
        $this->refreshAvailableCountries();
        session()->flash('success', 'Land hinzugefügt.');

        $this->dispatchMapUpdate();
    }

    public function removeCountry($id)
    {
        ShippingZoneCountry::destroy($id);
        $this->refreshAvailableCountries();
        $this->dispatchMapUpdate();
    }

    public function refreshAvailableCountries()
    {
        $allCountries = shop_setting('active_countries', []);
        $assignedCodes = ShippingZoneCountry::pluck('country_code')->toArray();

        $this->availableCountries = array_filter($allCountries, function($code) use ($assignedCodes) {
            return !in_array($code, $assignedCodes);
        }, ARRAY_FILTER_USE_KEY);
    }

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
            'min_weight' => $this->newRate['min_weight'],
            'max_weight' => $this->newRate['max_weight'],
            'min_price' => 0,
            'price' => (int) ($this->newRate['price'] * 100),
        ]);

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
     * Aktualisiert die Tooltips der Karte ohne Neuladen.
     */
    private function dispatchMapUpdate()
    {
        $this->dispatch('map-updated', activeCodes: $this->mapVisuals['activeCodes']);
    }

    public function getMapVisualsProperty()
    {
        $zones = ShippingZone::with('countries')->get();
        $css = "";
        $legend = [];
        $activeCodes = [];

        foreach ($zones as $index => $zone) {
            $color = $this->zoneColors[$index % count($this->zoneColors)];
            $legend[$zone->name] = $color;

            foreach ($zone->countries as $country) {
                $code = strtoupper($country->country_code);
                // HIER: fill-opacity für die Sterne und ein leichter Glow-Rand
                $css .= ".jvm-region[data-code='{$code}'] { fill: {$color} !important; fill-opacity: 0.65 !important; stroke: rgba(255,255,255,0.2) !important; stroke-width: 1px !important; } ";
                $activeCodes[$code] = $zone->name;
            }
        }

        return [
            'css' => $css,
            'legend' => $legend,
            'activeCodes' => $activeCodes
        ];
    }

    public function render()
    {
        $stats = [
            'zones' => ShippingZone::count(),
            'countries_covered' => ShippingZoneCountry::count(),
            'rates' => ShippingRate::count(),
        ];

        $zones = ShippingZone::withCount(['countries', 'rates'])->get();

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

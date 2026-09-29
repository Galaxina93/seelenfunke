<?php

namespace App\Livewire\Shop\System;

use App\Livewire\Traits\WithDepartmentTheming;

use App\Models\Logistics\LogisticsShippingRate;
use App\Models\Logistics\LogisticsShippingZone;
use App\Models\Logistics\LogisticsShippingZoneCountry;
use Livewire\Component;

class SystemShipping extends Component
{
    use WithDepartmentTheming;
    
    public string $themingDepartment = 'System';

    // --- STATE ---
    public $view = 'list'; // 'list', 'edit', 'create'
    public $activeZoneId = null;
    public bool $showArchived = false;

    // --- FORM DATA (Zone Edit) ---
    public $zoneName;
    public bool $zoneIsActive = true;

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

    public function createZone()
    {
        $this->resetInput();
        $this->view = 'create';
    }

    public function editZone($id)
    {
        $this->resetInput();
        $this->activeZoneId = $id;
        $zone = LogisticsShippingZone::withTrashed()->findOrFail($id);

        $this->zoneName = $zone->name;
        $this->zoneIsActive = (bool) $zone->is_active;
        $this->view = 'edit';
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
        $this->zoneIsActive = true;
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
            $zone = LogisticsShippingZone::create([
                'name' => $this->zoneName,
                'is_active' => $this->zoneIsActive,
            ]);
            $this->activeZoneId = $zone->id;
            session()->flash('success', 'Versandzone erstellt. Füge nun Länder hinzu.');
            $this->view = 'edit';
        } else {
            $zone = LogisticsShippingZone::withTrashed()->findOrFail($this->activeZoneId);
            $zone->update([
                'name' => $this->zoneName,
                'is_active' => $this->zoneIsActive,
            ]);
            session()->flash('success', 'Versandzone aktualisiert.');
        }

        $this->syncActiveCountriesWithSettings();
        $this->dispatchMapUpdate();
    }

    public function toggleZoneActive($id)
    {
        $zone = LogisticsShippingZone::withTrashed()->findOrFail($id);
        $zone->is_active = !$zone->is_active;
        $zone->save();

        $this->syncActiveCountriesWithSettings();
        $this->dispatchMapUpdate();
        session()->flash('success', "Zone '{$zone->name}' ist nun " . ($zone->is_active ? 'aktiv' : 'inaktiv') . '.');
    }

    public function archiveZone($id)
    {
        $zone = LogisticsShippingZone::findOrFail($id);
        $name = $zone->name;
        $zone->delete();

        if ($this->activeZoneId === $id) {
            $this->cancel();
        }

        $this->syncActiveCountriesWithSettings();
        $this->dispatchMapUpdate();
        session()->flash('success', "Zone '{$name}' wurde ins Archiv verschoben.");
    }

    public function restoreZone($id)
    {
        $zone = LogisticsShippingZone::onlyTrashed()->findOrFail($id);
        $name = $zone->name;
        $zone->restore();

        $this->syncActiveCountriesWithSettings();
        $this->dispatchMapUpdate();
        session()->flash('success', "Zone '{$name}' wurde erfolgreich wiederhergestellt.");
    }

    public function forceDeleteZone($id)
    {
        $zone = LogisticsShippingZone::onlyTrashed()->findOrFail($id);
        $name = $zone->name;

        // Cascade delete countries and rates
        $zone->countries()->delete();
        $zone->rates()->delete();
        $zone->forceDelete();

        if ($this->activeZoneId === $id) {
            $this->cancel();
        }

        $this->syncActiveCountriesWithSettings();
        $this->dispatchMapUpdate();
        session()->flash('success', "Zone '{$name}' wurde endgültig gelöscht.");
    }

    public function deleteZone($id)
    {
        $this->archiveZone($id);
    }

    public function syncActiveCountriesWithSettings(): void
    {
        $activeCountryCodes = LogisticsShippingZoneCountry::whereHas('zone', function ($q) {
            $q->whereNull('deleted_at')->where('is_active', true);
        })->pluck('country_code')->map(fn($c) => strtoupper($c))->unique()->toArray();

        $allCountries = $this->getAllCountries();
        $activeCountriesMap = [];
        foreach ($activeCountryCodes as $code) {
            if (isset($allCountries[$code])) {
                $activeCountriesMap[$code] = $allCountries[$code];
            }
        }

        // Falls keine Zonen aktiv sind, mindestens DE als Standard belassen
        if (empty($activeCountriesMap) && isset($allCountries['DE'])) {
            $activeCountriesMap['DE'] = $allCountries['DE'];
        }

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'active_countries'],
            ['value' => json_encode($activeCountriesMap)]
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
    }

    public function addCountry()
    {
        $this->validate([
            'selectedCountryToAdd' => 'required|size:2',
        ]);

        $exists = LogisticsShippingZoneCountry::where('country_code', $this->selectedCountryToAdd)->exists();
        if ($exists) {
            $this->addError('selectedCountryToAdd', 'Dieses Land ist bereits zugeordnet.');
            return;
        }

        LogisticsShippingZoneCountry::create([
            'logistics_shipping_zone_id' => $this->activeZoneId,
            'country_code' => $this->selectedCountryToAdd
        ]);

        $this->selectedCountryToAdd = '';
        session()->flash('success', 'Land hinzugefügt.');

        $this->syncActiveCountriesWithSettings();
        $this->dispatchMapUpdate();
    }

    public function removeCountry($id)
    {
        LogisticsShippingZoneCountry::destroy($id);
        $this->syncActiveCountriesWithSettings();
        $this->dispatchMapUpdate();
    }

    /**
     * Diese Liste ist jetzt die "Wahrheit" für alle verfügbaren Länder
     */
    public function getAllCountries()
    {
        return [
            'DE' => 'Deutschland', 'AT' => 'Österreich', 'CH' => 'Schweiz',
            'BE' => 'Belgien', 'BG' => 'Bulgarien', 'DK' => 'Dänemark',
            'EE' => 'Estland', 'FI' => 'Finnland', 'FR' => 'Frankreich',
            'GR' => 'Griechenland', 'IE' => 'Irland', 'IT' => 'Italien',
            'HR' => 'Kroatien', 'LV' => 'Lettland', 'LT' => 'Litauen',
            'LU' => 'Luxemburg', 'MT' => 'Malta', 'NL' => 'Niederlande',
            'PL' => 'Polen', 'PT' => 'Portugal', 'RO' => 'Rumänien',
            'SE' => 'Schweden', 'SK' => 'Slowakei', 'SI' => 'Slowenien',
            'ES' => 'Spanien', 'CZ' => 'Tschechien', 'HU' => 'Ungarn',
            'CY' => 'Zypern', 'GB' => 'Großbritannien', 'US' => 'USA'
        ];
    }

    public function addRate()
    {
        $this->validate([
            'newRate.name' => 'required|string|min:2',
            'newRate.min_weight' => 'required|numeric|min:0',
            'newRate.max_weight' => 'nullable|numeric|gt:newRate.min_weight',
            'newRate.price' => 'required|numeric|min:0',
        ]);

        LogisticsShippingRate::create([
            'logistics_shipping_zone_id' => $this->activeZoneId,
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
        LogisticsShippingRate::destroy($rateId);
        session()->flash('success', 'Tarif gelöscht.');
    }

    public function toggleArchiveView($show = null)
    {
        $this->showArchived = is_null($show) ? !$this->showArchived : (bool) $show;
        if ($this->view !== 'list') {
            $this->cancel();
        }
    }

    private function dispatchMapUpdate()
    {
        $this->dispatch('map-updated', activeCodes: $this->mapVisuals['activeCodes']);
    }

    public function getMapVisualsProperty()
    {
        $zones = LogisticsShippingZone::with('countries')->get();
        $css = "";
        $legend = [];
        $activeCodes = [];

        foreach ($zones as $index => $zone) {
            $isActive = (bool) $zone->is_active;
            $color = $isActive ? $this->zoneColors[$index % count($this->zoneColors)] : '#64748b';
            $label = $zone->name . ($isActive ? '' : ' (Inaktiv)');
            $legend[$label] = $color;

            foreach ($zone->countries as $country) {
                $code = strtoupper($country->country_code);
                $opacity = $isActive ? '0.65' : '0.25';
                $css .= ".jvm-region[data-code='{$code}'] { fill: {$color} !important; fill-opacity: {$opacity} !important; stroke: rgba(255,255,255,0.2) !important; stroke-width: 1px !important; } ";
                $activeCodes[$code] = $label;
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
            'zones' => LogisticsShippingZone::where('is_active', true)->count(),
            'inactive_zones' => LogisticsShippingZone::where('is_active', false)->count(),
            'archived_zones' => LogisticsShippingZone::onlyTrashed()->count(),
            'countries_covered' => LogisticsShippingZoneCountry::whereHas('zone', fn($q) => $q->whereNull('deleted_at')->where('is_active', true))->count(),
            'rates' => LogisticsShippingRate::whereHas('zone', fn($q) => $q->whereNull('deleted_at')->where('is_active', true))->count(),
        ];

        $zones = LogisticsShippingZone::withCount(['countries', 'rates'])->get();
        $archivedZones = LogisticsShippingZone::onlyTrashed()->withCount(['countries', 'rates'])->get();

        $activeZoneData = null;
        if ($this->activeZoneId) {
            $activeZoneData = LogisticsShippingZone::withTrashed()->with(['countries', 'rates' => function($q) {
                $q->orderBy('min_weight', 'asc');
            }])->find($this->activeZoneId);
        }

        // DYNAMISCHE BERECHNUNG DER VERFÜGBAREN LÄNDER BEI JEDEM RENDER-ZYKLUS
        $allCountries = $this->getAllCountries();
        $assignedCodes = LogisticsShippingZoneCountry::whereHas('zone', fn($q) => $q->whereNull('deleted_at'))->pluck('country_code')->toArray();

        $availableCountries = array_filter($allCountries, function($code) use ($assignedCodes) {
            return !in_array(strtoupper($code), array_map('strtoupper', $assignedCodes));
        }, ARRAY_FILTER_USE_KEY);

        // Alphabetische Sortierung für die Dropdown-Liste
        asort($availableCountries);

        return view('livewire.shop.system.system-shipping', [
            'zones' => $zones,
            'archivedZones' => $archivedZones,
            'stats' => $stats,
            'activeZoneModel' => $activeZoneData,
            'mapVisuals' => $this->mapVisuals,
            'allCountries' => $allCountries,
            'availableCountries' => $availableCountries
        ]);
    }
}

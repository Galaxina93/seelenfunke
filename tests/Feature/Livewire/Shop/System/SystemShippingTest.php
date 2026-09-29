<?php

namespace Tests\Feature\Livewire\Shop\System;

use App\Livewire\Shop\System\SystemShipping;
use App\Models\Logistics\LogisticsShippingRate;
use App\Models\Logistics\LogisticsShippingZone;
use App\Models\Logistics\LogisticsShippingZoneCountry;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemShippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_shipping_zone_with_active_flag()
    {
        Livewire::test(SystemShipping::class)
            ->call('createZone')
            ->set('zoneName', 'Deutschland & Österreich')
            ->set('zoneIsActive', true)
            ->call('saveZone')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('logistics_shipping_zones', [
            'name' => 'Deutschland & Österreich',
            'is_active' => 1,
            'deleted_at' => null,
        ]);
    }

    public function test_it_toggles_zone_active_state_and_syncs_settings()
    {
        $zone = LogisticsShippingZone::create([
            'name' => 'EU Zone',
            'is_active' => true,
        ]);

        LogisticsShippingZoneCountry::create([
            'logistics_shipping_zone_id' => $zone->id,
            'country_code' => 'FR',
        ]);

        $component = Livewire::test(SystemShipping::class)
            ->call('toggleZoneActive', $zone->id);

        $zone->refresh();
        $this->assertFalse($zone->is_active);

        // Active countries should no longer contain FR
        $activeCountries = json_decode(SystemSetting::where('key', 'active_countries')->value('value'), true);
        $this->assertArrayNotHasKey('FR', $activeCountries);

        // Toggle back to active
        $component->call('toggleZoneActive', $zone->id);
        $zone->refresh();
        $this->assertTrue($zone->is_active);

        $activeCountries = json_decode(SystemSetting::where('key', 'active_countries')->value('value'), true);
        $this->assertArrayHasKey('FR', $activeCountries);
    }

    public function test_it_archives_and_restores_shipping_zone()
    {
        $zone = LogisticsShippingZone::create([
            'name' => 'Schweiz',
            'is_active' => true,
        ]);

        $country = LogisticsShippingZoneCountry::create([
            'logistics_shipping_zone_id' => $zone->id,
            'country_code' => 'CH',
        ]);

        $rate = LogisticsShippingRate::create([
            'logistics_shipping_zone_id' => $zone->id,
            'name' => 'Paket',
            'min_weight' => 0,
            'max_weight' => 1000,
            'min_price' => 0,
            'price' => 1490,
        ]);

        // Archive
        Livewire::test(SystemShipping::class)
            ->call('archiveZone', $zone->id);

        $this->assertSoftDeleted('logistics_shipping_zones', ['id' => $zone->id]);

        // Country and rate remain intact in DB
        $this->assertDatabaseHas('logistics_shipping_zone_countries', ['id' => $country->id]);
        $this->assertDatabaseHas('logistics_shipping_rates', ['id' => $rate->id]);

        // Active countries setting should not contain CH anymore
        $activeCountries = json_decode(SystemSetting::where('key', 'active_countries')->value('value'), true);
        $this->assertArrayNotHasKey('CH', $activeCountries);

        // Restore
        Livewire::test(SystemShipping::class)
            ->call('restoreZone', $zone->id);

        $this->assertNotSoftDeleted('logistics_shipping_zones', ['id' => $zone->id]);

        $activeCountries = json_decode(SystemSetting::where('key', 'active_countries')->value('value'), true);
        $this->assertArrayHasKey('CH', $activeCountries);
    }

    public function test_it_permanently_force_deletes_archived_zone()
    {
        $zone = LogisticsShippingZone::create([
            'name' => 'Test Zone',
            'is_active' => true,
        ]);

        $country = LogisticsShippingZoneCountry::create([
            'logistics_shipping_zone_id' => $zone->id,
            'country_code' => 'IT',
        ]);

        $rate = LogisticsShippingRate::create([
            'logistics_shipping_zone_id' => $zone->id,
            'name' => 'Express',
            'min_weight' => 0,
            'price' => 1999,
        ]);

        $zone->delete(); // Soft delete first

        Livewire::test(SystemShipping::class)
            ->call('forceDeleteZone', $zone->id);

        $this->assertDatabaseMissing('logistics_shipping_zones', ['id' => $zone->id]);
        $this->assertDatabaseMissing('logistics_shipping_zone_countries', ['id' => $country->id]);
        $this->assertDatabaseMissing('logistics_shipping_rates', ['id' => $rate->id]);
    }
}

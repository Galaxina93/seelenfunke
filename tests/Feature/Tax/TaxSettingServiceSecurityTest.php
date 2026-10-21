<?php

namespace Tests\Feature\Tax;

use App\Models\System\SystemSetting;
use App\Services\Tax\TaxSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxSettingServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_retroactive_vat_prevents_division_by_zero_and_negative_rates()
    {
        $service = new TaxSettingService();

        // 1. Zero tax rate
        $calcZero = $service->calculateRetroactiveVat(11900, 0.0);
        $this->assertEquals(11900, $calcZero['net_cents']);
        $this->assertEquals(0, $calcZero['vat_cents']);

        // 2. Negative tax rate clamped to 0 without division by zero crash
        $calcNeg = $service->calculateRetroactiveVat(10000, -100.0);
        $this->assertEquals(10000, $calcNeg['net_cents']);
        $this->assertEquals(0, $calcNeg['vat_cents']);

        // 3. Standard 19%
        $calc19 = $service->calculateRetroactiveVat(11900, 19.0);
        $this->assertEquals(10000, $calc19['net_cents']);
        $this->assertEquals(1900, $calc19['vat_cents']);
    }

    public function test_apply_transition_safely_updates_settings_in_transaction()
    {
        $service = new TaxSettingService();

        // Disable small business with next_year transition
        $service->applyTransition(false, 'next_year');

        $this->assertEquals('true', SystemSetting::where('key', 'is_small_business')->value('value'));
        $this->assertEquals((string)now()->year, SystemSetting::where('key', 'small_business_scheduled_end_year')->value('value'));

        // Re-enable small business
        $service->applyTransition(true);

        $this->assertEquals('true', SystemSetting::where('key', 'is_small_business')->value('value'));
        $this->assertEquals('', SystemSetting::where('key', 'small_business_scheduled_end_year')->value('value'));
    }
}

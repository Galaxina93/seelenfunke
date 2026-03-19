<?php

namespace Tests\Feature;

use App\Models\ShopSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShopSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache before each test to ensure a clean state
        Cache::forget('global_shop_settings');
    }

    public function test_it_can_create_and_retrieve_a_setting()
    {
        ShopSetting::create(['key' => 'test_string', 'value' => 'Hello World']);

        // Test the helper function
        $this->assertEquals('Hello World', shop_setting('test_string'));
    }

    public function test_it_returns_default_value_when_setting_does_not_exist()
    {
        // 'non_existent_key' is not in the DB
        $this->assertEquals('Fallback Default', shop_setting('non_existent_key', 'Fallback Default'));
        
        // Should return null if no default is provided
        $this->assertNull(shop_setting('another_missing_key'));
    }

    public function test_it_automatically_casts_string_booleans_to_real_booleans()
    {
        ShopSetting::create(['key' => 'test_bool_true', 'value' => 'true']);
        ShopSetting::create(['key' => 'test_bool_false', 'value' => 'false']);

        $this->assertTrue(shop_setting('test_bool_true'));
        $this->assertTrue(is_bool(shop_setting('test_bool_true')));

        $this->assertFalse(shop_setting('test_bool_false'));
        $this->assertTrue(is_bool(shop_setting('test_bool_false')));
    }

    public function test_it_does_not_cast_numeric_booleans()
    {
        // The helper only explicitly checks for 'true' and 'false' strings
        ShopSetting::create(['key' => 'test_num_one', 'value' => '1']);
        ShopSetting::create(['key' => 'test_num_zero', 'value' => '0']);

        $this->assertEquals('1', shop_setting('test_num_one'));
        $this->assertEquals('0', shop_setting('test_num_zero'));
    }

    public function test_it_automatically_decodes_json_strings_into_arrays()
    {
        $jsonArray = '["apple", "banana"]';
        $jsonObject = '{"feature": "enabled", "version": 2}';

        ShopSetting::create(['key' => 'test_json_array', 'value' => $jsonArray]);
        ShopSetting::create(['key' => 'test_json_object', 'value' => $jsonObject]);

        $decodedArray = shop_setting('test_json_array');
        $this->assertIsArray($decodedArray);
        $this->assertEquals(['apple', 'banana'], $decodedArray);

        $decodedObject = shop_setting('test_json_object');
        $this->assertIsArray($decodedObject);
        $this->assertEquals(['feature' => 'enabled', 'version' => 2], $decodedObject);
    }

    public function test_it_caches_the_settings_and_requires_cache_clearing_to_update()
    {
        // 1. Create a setting
        $setting = ShopSetting::create(['key' => 'cache_test', 'value' => 'Initial Value']);

        // 2. Read it (this triggers the Cache::rememberForever)
        $this->assertEquals('Initial Value', shop_setting('cache_test'));

        // 3. Update the database directly, simulating a bypass of cache expiration
        $setting->update(['value' => 'Updated Value']);

        // 4. The helper should still return the cached value (Initial Value)
        $this->assertEquals('Initial Value', shop_setting('cache_test'));

        // 5. Clear the cache manually (simulating the behavior in ShopConfig / FinancialTax components)
        Cache::forget('global_shop_settings');

        // 6. Now the helper should return the newly updated value from DB
        $this->assertEquals('Updated Value', shop_setting('cache_test'));
    }

    public function test_it_handles_empty_or_null_settings_correctly()
    {
        ShopSetting::create(['key' => 'empty_string', 'value' => '']);
        ShopSetting::create(['key' => 'null_value', 'value' => null]);

        $this->assertEquals('', shop_setting('empty_string'));
        $this->assertNull(shop_setting('null_value'));
    }
}

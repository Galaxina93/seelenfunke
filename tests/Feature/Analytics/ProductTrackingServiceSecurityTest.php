<?php

namespace Tests\Feature\Analytics;

use App\Models\Product\ProductAnalyticsEvent;
use App\Services\ProductTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTrackingServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_track_truncates_long_strings_and_clamps_value_cents()
    {
        $longSource = str_repeat('s', 200);
        $longEvent = str_repeat('e', 300);
        $longProductId = str_repeat('p', 200);
        $longTemplateId = str_repeat('t', 200);

        $event = ProductTrackingService::track($longSource, $longEvent, [
            'product_id' => $longProductId,
            'template_id' => $longTemplateId,
            'value_cents' => 99999999999999, // Exceeds 32-bit INT
            'step' => '3',
            'meta_data' => ['key' => 'value'],
        ]);

        $this->assertNotNull($event);
        $this->assertEquals(30, strlen($event->source));
        $this->assertEquals(60, strlen($event->event_name));
        $this->assertEquals(36, strlen($event->product_id));
        $this->assertEquals(36, strlen($event->template_id));
        $this->assertEquals(2147483647, $event->value_cents);
        $this->assertEquals(3, $event->step);
        $this->assertEquals(['key' => 'value'], $event->meta_data);

        $this->assertDatabaseHas('product_analytics_events', [
            'id' => $event->id,
            'value_cents' => 2147483647,
        ]);
    }

    public function test_session_hash_is_anonymized_sha256()
    {
        $hash = ProductTrackingService::getSessionHash();

        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash)); // SHA-256 is 64 hex characters
    }
}

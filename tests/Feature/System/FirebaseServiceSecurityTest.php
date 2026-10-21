<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\System\SystemUserDevice;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_push_notification_handles_empty_token_and_nested_data()
    {
        $service = new class extends FirebaseService {
            protected function getAccessToken(): ?string {
                return 'mock-token-xyz';
            }
        };

        // Empty device token returns false
        $this->assertFalse($service->sendPushNotification('', 'Test Title', 'Test Body'));

        // Mock FCM success
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/123'], 200)
        ]);

        // Nested data does not crash array to string conversion
        $sent = $service->sendPushNotification('device-token-12345', 'Order #100', 'New Order', [
            'order_id' => 'abc-123',
            'nested_details' => ['item1' => 'Laser Gravur', 'qty' => 2],
        ]);

        $this->assertTrue($sent);
    }

    public function test_send_to_admins_targets_active_admins_efficiently()
    {
        $service = new class extends FirebaseService {
            protected function getAccessToken(): ?string {
                return 'mock-token-xyz';
            }
        };

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200)
        ]);

        $admin1 = Admin::factory()->create();
        $admin2 = Admin::factory()->create();

        // Device for admin1
        SystemUserDevice::create([
            'userable_id' => $admin1->id,
            'userable_type' => Admin::class,
            'fcm_token' => 'token-admin-1',
            'platform' => 'android',
        ]);

        // Soft delete admin2
        $admin2->delete();

        // Device for admin2 (soft deleted)
        SystemUserDevice::create([
            'userable_id' => $admin2->id,
            'userable_type' => Admin::class,
            'fcm_token' => 'token-admin-2',
            'platform' => 'android',
        ]);

        $count = $service->sendToAdmins('System Alert', 'Database backup complete');

        // Only active admin1 device should be sent
        $this->assertEquals(1, $count);
    }
}

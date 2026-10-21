<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SystemMaintenanceSecretRouteTest extends TestCase
{
    public function test_legacy_hardcoded_secret_tokens_are_forbidden(): void
    {
        // When accessed with the old hardcoded tokens without valid configured secret, they are forbidden (403)
        $response1 = $this->get('/system/cronjob/run-secret-847294');
        $response2 = $this->get('/system/cache/clear/clear-secret-847294');

        $this->assertEquals(403, $response1->getStatusCode());
        $this->assertEquals(403, $response2->getStatusCode());

        // Completely removed routes return 404
        $this->get('/system/cache/clear-secret-847294')->assertStatus(404);
    }

    public function test_maintenance_routes_reject_invalid_token(): void
    {
        Config::set('services.system.cron_secret', 'valid-cron-token-123');
        Config::set('services.system.cache_secret', 'valid-cache-token-456');

        $this->get('/system/cronjob/wrong-token')->assertStatus(403);
        $this->get('/system/cache/clear/wrong-token')->assertStatus(403);
    }

    public function test_maintenance_routes_reject_when_secret_is_unconfigured(): void
    {
        Config::set('services.system.cron_secret', null);
        Config::set('services.system.cache_secret', null);
        putenv('SYSTEM_CRON_SECRET');
        putenv('SYSTEM_CACHE_SECRET');

        $this->get('/system/cronjob/some-token')->assertStatus(403);
        $this->get('/system/cache/clear/some-token')->assertStatus(403);
    }
}

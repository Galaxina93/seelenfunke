<?php

namespace Tests\Feature\System;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class StageServerResilienceAndPhp85ConfigTest extends TestCase
{
    public function test_database_config_uses_pdo_mysql_attr_ssl_ca_without_deprecation(): void
    {
        $config = require base_path('config/database.php');

        $this->assertArrayHasKey('mysql', $config['connections']);
        $this->assertIsArray($config['connections']['mysql']['options']);

        $expectedConstant = defined('Pdo\Mysql::ATTR_SSL_CA')
            ? \Pdo\Mysql::ATTR_SSL_CA
            : \PDO::MYSQL_ATTR_SSL_CA;

        $this->assertIsInt($expectedConstant);

        $databaseFileContent = file_get_contents(base_path('config/database.php'));
        $this->assertStringContainsString("defined('Pdo\\Mysql::ATTR_SSL_CA')", $databaseFileContent);
    }

    public function test_app_service_provider_falls_back_to_file_when_redis_dns_fails(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('session.driver', 'redis');
        Config::set('queue.default', 'redis');
        Config::set('database.redis.default.host', 'redis-t4tm3u.pg-s-pis5pf.db.project.host.invalid');

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->assertSame('file', Config::get('cache.default'));
        $this->assertSame('file', Config::get('session.driver'));
        $this->assertSame('database', Config::get('queue.default'));
    }

    public function test_server_twilio_bridge_normalizes_live_model_and_audio_format(): void
    {
        $bridgeJs = file_get_contents(base_path('server-twilio.js'));

        $this->assertStringContainsString("const DEFAULT_LIVE_MODEL = 'models/gemini-3.1-flash-live-preview';", $bridgeJs);
        $this->assertStringContainsString('data.realtimeInput.audio =', $bridgeJs);
        $this->assertStringContainsString('isWhitespaceOnlyInterrupt', $bridgeJs);
        $this->assertStringNotContainsString('v1alpha.GenerativeService.BidiGenerateContent', $bridgeJs);
    }
}

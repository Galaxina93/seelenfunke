<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\System\SystemLog;
use App\Services\AI\Functions\AiSystemFuncs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CrossPlatformErrorSentinelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cross_platform_error_ingest_deduplicates_scrubs_secrets_and_tracks_auto_healing(): void
    {
        $admin = Admin::factory()->create();
        $androidToken = $admin->createToken('AndroidApp', ['*'])->plainTextToken;
        $miniPcToken = $admin->createToken('FunkenwerkMiniPc', ['monitor:pulse', 'ai:funkira'])->plainTextToken;

        $customer = Customer::factory()->create();
        $customerToken = $customer->createToken('CustomerWeb', ['*'])->plainTextToken;

        // 1. Non-admin (customer) must be strictly rejected (403)
        $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->postJson('/api/system/error-ingest', [
                'platform' => 'android',
                'component' => 'trichter_upload',
                'message' => 'Test unauthorized',
            ])
            ->assertStatus(403);

        Auth::forgetGuards();

        // 2. Android App reports an error containing sensitive token/password parameters -> scrubbed & stored
        $resp1 = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/error-ingest', [
                'platform' => 'android',
                'component' => 'trichter_upload',
                'severity' => 'error',
                'title' => 'Trichter Upload Timeout',
                'message' => 'SocketTimeoutException at https://mein-seelenfunke.de/api?token=SUPER_SECRET_12345&password=MyPass99',
                'error_class' => 'java.net.SocketTimeoutException',
                'file' => 'FinanceViewModel.kt',
                'line' => 412,
                'app_version' => '1.3.3',
                'device' => 'Pixel 9 Pro XL',
                'context' => [
                    'access_token' => 'SHOULD_NEVER_BE_SAVED',
                    'files_count' => 3,
                ],
            ]);

        $resp1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ingested', 1);

        $logId = $resp1->json('log_ids.0');
        $log = SystemLog::findOrFail($logId);
        $this->assertSame('android', $log->type);
        $this->assertSame('error', $log->status);
        $this->assertStringNotContainsString('SUPER_SECRET_12345', $log->message);
        $this->assertStringNotContainsString('MyPass99', $log->message);
        $this->assertStringContainsString('[REDACTED]', $log->message);
        $this->assertSame('[REDACTED]', $log->payload['context']['access_token'] ?? null);
        $this->assertSame(1, $log->payload['occurrences_count'] ?? 0);

        // 3. Duplicate occurrence from Android increments occurrences_count instead of creating a new row!
        $resp2 = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/error-ingest', [
                'platform' => 'android',
                'component' => 'trichter_upload',
                'severity' => 'error',
                'title' => 'Trichter Upload Timeout',
                'message' => 'SocketTimeoutException at https://mein-seelenfunke.de/api?token=OTHER_SECRET&password=OtherPass',
                'error_class' => 'java.net.SocketTimeoutException',
                'file' => 'FinanceViewModel.kt',
                'line' => 412,
                'occurrences' => 2,
            ]);

        $resp2->assertOk();
        $this->assertSame($logId, $resp2->json('log_ids.0'));
        $log->refresh();
        $this->assertSame(3, $log->payload['occurrences_count'] ?? 0);

        // 4. Mini-PC reports a Sense daemon hiccup and subsequently reports auto_healed -> status downgrades to warning!
        $mpErr = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
            ->postJson('/api/system/error-ingest', [
                'platform' => 'minipc_linux',
                'component' => 'local_sense_daemon',
                'severity' => 'error',
                'title' => 'Sense-Daemon 17701 nicht erreichbar',
                'message' => 'Lokaler Wake-Word & YuNet Daemon auf Port 17701 antwortete nicht.',
                'file' => 'LocalSenseBridge.cs',
                'line' => 75,
            ]);
        $mpErr->assertOk();
        $mpLogId = $mpErr->json('log_ids.0');
        $this->assertSame('error', SystemLog::findOrFail($mpLogId)->status);

        $mpHeal = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
            ->postJson('/api/system/error-ingest', [
                'platform' => 'minipc_linux',
                'component' => 'local_sense_daemon',
                'severity' => 'auto_healed',
                'auto_healed' => true,
                'auto_heal_action' => 'funkira_local_sense.py automatisch neu gestartet und /health=ok verifiziert.',
                'title' => 'Sense-Daemon 17701 nicht erreichbar',
                'message' => 'Lokaler Wake-Word & YuNet Daemon auf Port 17701 antwortete nicht.',
                'file' => 'LocalSenseBridge.cs',
                'line' => 75,
            ]);
        $mpHeal->assertOk();
        $this->assertSame($mpLogId, $mpHeal->json('log_ids.0'));
        $healedLog = SystemLog::findOrFail($mpLogId);
        $this->assertSame('warning', $healedLog->status);
        $this->assertStringStartsWith('[AUTO-GEHEILT]', (string) $healedLog->title);
        $this->assertTrue((bool) ($healedLog->payload['auto_healed'] ?? false));

        // 5. Funkira's AI tool system_get_error_sentinel reads both open errors and auto-healed entries
        Auth::guard('admin')->login($admin);
        $toolRes = AiSystemFuncs::executeGetErrorSentinel(['platform' => 'all', 'hours' => 24]);
        $this->assertSame('success', $toolRes['status'] ?? '');
        $this->assertGreaterThanOrEqual(1, $toolRes['open_errors_count'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $toolRes['auto_healed_count'] ?? 0);
    }
}

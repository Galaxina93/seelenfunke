<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemLogs;
use App\Models\Admin\Admin;
use App\Models\System\SystemLog;
use App\Services\InvoiceService;
use App\Services\System\SystemErrorLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class SystemLogsPlatformAndCleanupTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        SystemLog::query()->delete();
    }

    public function test_four_platforms_are_detected_filtered_and_shown_in_first_column(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $portalLog = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:exception:checkout',
            'title' => 'Portal Checkout Fehler',
            'message' => 'Testfehler im Webportal',
            'status' => 'error',
        ]);

        $androidLog = SystemLog::create([
            'type' => 'android',
            'action_id' => 'sentinel:android:sync',
            'title' => 'Android Sync Fehler',
            'message' => 'Testfehler in Android App',
            'status' => 'error',
        ]);

        $miniPcLog = SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'sentinel:minipc_linux:camera',
            'title' => 'Mini-PC Kamera Warnung',
            'message' => 'Testwarnung auf Mini-PC',
            'status' => 'warning',
        ]);

        $desktopLog = SystemLog::create([
            'type' => 'windows_desktop',
            'action_id' => 'sentinel:windows_desktop:obs',
            'title' => 'Funkenwerk Studio Fehler',
            'message' => 'Testfehler in Funkenwerk Software',
            'status' => 'error',
        ]);

        $solvedLog = SystemLog::create([
            'type' => 'system',
            'action_id' => 'scheduler:heartbeat',
            'title' => '[GELÖST] Alter Routine-Eintrag',
            'message' => 'Bereits erledigt',
            'status' => 'info',
        ]);

        $this->assertSame('portal', $portalLog->resolvePlatformKey());
        $this->assertSame('android', $androidLog->resolvePlatformKey());
        $this->assertSame('minipc_linux', $miniPcLog->resolvePlatformKey());
        $this->assertSame('windows_desktop', $desktopLog->resolvePlatformKey());

        $this->assertSame(1, SystemLog::openIssues()->forPlatform('portal')->count());
        $this->assertSame(1, SystemLog::openIssues()->forPlatform('android')->count());
        $this->assertSame(1, SystemLog::openIssues()->forPlatform('minipc_linux')->count());
        $this->assertSame(1, SystemLog::openIssues()->forPlatform('windows_desktop')->count());
        $this->assertSame(1, SystemLog::archivedEntries()->count());

        $component = Livewire::test(SystemLogs::class);
        $component
            ->assertSet('viewTab', 'open')
            ->assertSee('Bereich')
            ->assertSee('Portal Checkout Fehler')
            ->assertSee('Android Sync Fehler')
            ->assertSee('Mini-PC Kamera Warnung')
            ->assertSee('Funkenwerk Studio Fehler')
            ->assertDontSee('[GELÖST] Alter Routine-Eintrag');

        // Filter auf einzelnen Bereich (Android) und nur diesen Bereich als gelöst archivieren
        $component->call('selectPlatform', 'android')
            ->assertSee('Android Sync Fehler')
            ->assertDontSee('Portal Checkout Fehler')
            ->call('markAllAsDone');

        $this->assertSame('success', $androidLog->fresh()->status);
        $this->assertSame('error', $portalLog->fresh()->status);

        // Wechsel ins Archiv zeigt gelöste Meldungen
        $component->call('selectPlatform', '')
            ->call('switchViewTab', 'archived')
            ->assertSee('[GELÖST] Android Sync Fehler')
            ->assertSee('[GELÖST] Alter Routine-Eintrag');
    }

    public function test_frontend_error_endpoint_deduplicates_and_filters_transient_dom_swap_noise(): void
    {
        // Harmloses Browser-/Livewire-DOM-Swap-Rauschen wird verworfen
        $noiseResp = $this->postJson('/api/log/frontend-error', [
            'message' => 'Could not find Livewire component in DOM tree',
            'source' => '/admin/orders',
            'lineno' => 5,
            'type' => 'js_error',
        ]);
        $noiseResp->assertOk();
        $this->assertSame(0, SystemLog::count());

        // Echter JS-Fehler wird beim wiederholten Auftreten innerhalb von 24h in 1 Zeile hochgezählt
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/log/frontend-error', [
                'message' => 'Uncaught TypeError: Cannot read properties of undefined (reading price)',
                'source' => '/js/shop-cart.js',
                'lineno' => 42,
                'url' => 'https://stage.mein-seelenfunke.de/warenkorb',
                'type' => 'js_error',
            ])->assertOk();
        }

        $this->assertSame(1, SystemLog::openIssues()->count());
        $onlyLog = SystemLog::openIssues()->first();
        $this->assertSame('portal', $onlyLog->resolvePlatformKey());
        $this->assertSame(3, (int) ($onlyLog->payload['occurrences_count'] ?? 0));
    }

    public function test_cleanup_command_prunes_7_day_archive_while_protecting_30_day_gdpr_security_logs(): void
    {
        // 1. Gelöster Standard-Log älter als 8 Tage -> wird nach 7 Tagen gelöscht
        $oldSolved = SystemLog::create([
            'type' => 'scheduler',
            'action_id' => 'scheduler:backup_clean',
            'title' => 'Backup Clean OK',
            'message' => 'Routine-Erfolg',
            'status' => 'success',
            'started_at' => now()->subDays(8),
            'finished_at' => now()->subDays(8),
        ]);
        SystemLog::whereKey($oldSolved->id)->update([
            'created_at' => now()->subDays(8),
            'finished_at' => now()->subDays(8),
        ]);

        // 2. Frischer gelöster Standard-Log (2 Tage alt) -> bleibt im 7-Tage-Archiv
        $freshSolved = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:route_check',
            'title' => '[GELÖST] Route Check',
            'message' => 'Alles grün',
            'status' => 'info',
            'started_at' => now()->subDays(2),
            'finished_at' => now()->subDays(2),
        ]);

        // 3. DSGVO/Security-Nachweis (12 Tage alt) -> bleibt bis 30 Tage geschützt!
        $gdprAudit = SystemLog::create([
            'type' => 'security',
            'action_id' => 'gdpr:consent_recorded',
            'title' => 'DSGVO Opt-In dokumentiert',
            'message' => 'Nachweis gemäß Art. 7 DSGVO',
            'status' => 'info',
            'started_at' => now()->subDays(12),
            'finished_at' => now()->subDays(12),
        ]);
        SystemLog::whereKey($gdprAudit->id)->update([
            'created_at' => now()->subDays(12),
            'finished_at' => now()->subDays(12),
        ]);

        // 4. Abgelaufener Security-Nachweis (35 Tage alt) -> wird nach 30 Tagen gelöscht
        $expiredAudit = SystemLog::create([
            'type' => 'security',
            'action_id' => 'security:login_failed',
            'title' => 'Fehlgeschlagener Login',
            'message' => 'Alt-Eintrag > 30 Tage',
            'status' => 'info',
            'started_at' => now()->subDays(35),
            'finished_at' => now()->subDays(35),
        ]);
        SystemLog::whereKey($expiredAudit->id)->update([
            'created_at' => now()->subDays(35),
            'finished_at' => now()->subDays(35),
        ]);

        // 5. Offener Fehler (10 Tage alt) -> darf NIEMALS ungelöst weggelöscht werden!
        $openOldError = SystemLog::create([
            'type' => 'minipc_linux',
            'action_id' => 'sentinel:minipc_linux:disk',
            'title' => 'Mini-PC Speicher knapp',
            'message' => 'Noch nicht behoben',
            'status' => 'error',
            'started_at' => now()->subDays(10),
            'finished_at' => now()->subDays(10),
        ]);
        SystemLog::whereKey($openOldError->id)->update([
            'created_at' => now()->subDays(10),
            'finished_at' => now()->subDays(10),
        ]);

        Artisan::call('system:logs-cleanup', ['--prune' => true]);

        $this->assertDatabaseMissing('system_logs', ['id' => $oldSolved->id]);
        $this->assertDatabaseMissing('system_logs', ['id' => $expiredAudit->id]);
        $this->assertDatabaseHas('system_logs', ['id' => $freshSolved->id]);
        $this->assertDatabaseHas('system_logs', ['id' => $gdprAudit->id]);
        $this->assertDatabaseHas('system_logs', ['id' => $openOldError->id, 'status' => 'error']);
    }

    public function test_invoice_qr_code_and_vendor_deprecation_filter_emit_zero_warnings(): void
    {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;
            }
            return false;
        });

        try {
            $dataUri = app(InvoiceService::class)->generateQrCodeSvgDataUri('https://mein-seelenfunke.de/r/TEST-123');
        } finally {
            restore_error_handler();
        }

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
        $this->assertSame([], $deprecations);

        // Vendor-Deprecations erzeugen keinen Warnungs-Spam im SystemLog
        $ignored = SystemErrorLogger::recordLogEvent(
            'warning',
            'BaconQrCode\Encoder\Encoder::chooseMode(): Implicitly marking parameter $encoding as nullable is deprecated in /var/www/html/vendor/bacon/bacon-qr-code/src/Encoder/Encoder.php on line 158'
        );
        $this->assertNull($ignored);
    }

    public function test_packagist_semver_constraint_matcher_and_hmac_bridge_fallback_work_reliably(): void
    {
        $this->assertTrue(
            \App\Console\Commands\SystemSecurityAuditCommand::versionMatchesPackagistConstraint(
                '11.20.0',
                '>=11.0.0,<11.35.1|>=12.0.0,<12.1.1'
            )
        );
        $this->assertFalse(
            \App\Console\Commands\SystemSecurityAuditCommand::versionMatchesPackagistConstraint(
                '11.44.2',
                '>=11.0.0,<11.35.1|>=12.0.0,<12.1.1'
            )
        );
        $this->assertFalse(
            \App\Console\Commands\SystemSecurityAuditCommand::versionMatchesPackagistConstraint(
                '12.5.0',
                '<6.20.42|>=7.0.0,<7.30.6|>=8.0.0,<8.83.26'
            )
        );

        // Ohne explizites GEMINI_BRIDGE_SECRET akzeptiert isTrustedBridgeRequest den domänengetrennten HMAC(APP_KEY), lehnt aber leere/falsche Header strikt ab
        config([
            'services.gemini.bridge_secret' => '',
            'services.ai.proxy_secret' => '',
            'services.twilio.bridge_secret' => '',
            'app.key' => 'base64:TestAppKeyForBridgeHmacVerification1234567890=',
        ]);

        $validHmac = hash_hmac('sha256', 'seelenfunke-internal-bridge-v1', 'base64:TestAppKeyForBridgeHmacVerification1234567890=');

        $reqValid = \Illuminate\Http\Request::create('https://stage.mein-seelenfunke.de/api/ai/verify-token', 'POST');
        $reqValid->headers->set('X-Bridge-Secret', $validHmac);
        $this->assertTrue(\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($reqValid));

        $reqInvalid = \Illuminate\Http\Request::create('https://stage.mein-seelenfunke.de/api/ai/verify-token', 'POST');
        $reqInvalid->headers->set('X-Bridge-Secret', 'forged-secret');
        $this->assertFalse(\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($reqInvalid));
    }
}

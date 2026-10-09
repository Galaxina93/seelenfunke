<?php

namespace Tests\Feature\Livewire\Shop\Master;

use App\Livewire\Auth\AuthLogin;
use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use App\Models\System\SystemBlockedIp;
use App\Models\System\SystemLog;
use App\Models\System\SystemLoginAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MasterAnalyticsSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_security_score_starts_at_100_and_tolerates_minor_typos()
    {
        $this->actingAs($this->admin, 'admin');

        $component = Livewire::test(MasterAnalytics::class);

        // Standard: Keine Fehler -> Score 100
        $this->assertEquals(100, $component->get('securityScore'));
        $details = $component->get('securityScoreDetails');
        $this->assertEquals(100, $details['score']);
        $this->assertEquals('System gesichert', $details['text']);

        // 2 vereinzelte Vertipper von unterschiedlichen IPs
        SystemLoginAttempt::create(['email' => 'user1@test.com', 'ip_address' => '1.1.1.1', 'success' => false, 'attempted_at' => now()]);
        SystemLoginAttempt::create(['email' => 'user2@test.com', 'ip_address' => '2.2.2.2', 'success' => false, 'attempted_at' => now()]);

        $component = Livewire::test(MasterAnalytics::class);
        // Sollte bei 98 liegen (100 - 2), bleibt im grünen Bereich
        $this->assertEquals(98, $component->get('securityScore'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_security_score_penalizes_brute_force_and_security_errors()
    {
        $this->actingAs($this->admin, 'admin');

        // Brute-force Angriff: 5x Falscheingaben von der gleichen IP (192.168.1.50)
        for ($i = 0; $i < 5; $i++) {
            SystemLoginAttempt::create([
                'email' => "target{$i}@test.com",
                'ip_address' => '192.168.1.50',
                'success' => false,
                'attempted_at' => now(),
            ]);
        }

        // 1 kritischer Security-Logeintrag
        SystemLog::create([
            'type' => 'security',
            'action_id' => 'auth:tampering',
            'title' => 'Ungültige Token-Signatur',
            'status' => 'error',
            'started_at' => now(),
        ]);

        $component = Livewire::test(MasterAnalytics::class);
        $score = $component->get('securityScore');

        // Formel: 100 - (1 Brute-Force IP * 15) - (5 Versuche * 1) - (1 Warnung * 10) = 100 - 30 = 70
        $this->assertEquals(70, $score);
        $details = $component->get('securityScoreDetails');
        $this->assertEquals('Erhöhte Aktivität', $details['text']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_clear_security_logs_does_not_wipe_general_system_logs()
    {
        $this->actingAs($this->admin, 'admin');

        // Allgemeine System- & Audit-Logs erstellen
        $auditLog = SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:created',
            'title' => 'Neuer Mitarbeiter angelegt',
            'status' => 'success',
            'started_at' => now(),
        ]);

        $backupLog = SystemLog::create([
            'type' => 'automation',
            'action_id' => 'backup:run',
            'title' => 'Tägliches Backup',
            'status' => 'success',
            'started_at' => now(),
        ]);

        // Security Logs & Fehlversuche erstellen
        SystemLog::create([
            'type' => 'security',
            'action_id' => 'sec:threat',
            'title' => 'Brute Force Alert',
            'status' => 'error',
            'started_at' => now(),
        ]);

        SystemLoginAttempt::create([
            'email' => 'victim@test.com',
            'ip_address' => '185.220.101.5',
            'success' => false,
            'attempted_at' => now(),
        ]);

        $this->assertEquals(3, SystemLog::count());
        $this->assertEquals(1, SystemLoginAttempt::where('success', false)->count());

        // Ausführen von clearSecurityLogs()
        Livewire::test(MasterAnalytics::class)
            ->call('clearSecurityLogs');

        // Security-Logs und Fehlversuche müssen gelöscht sein
        $this->assertEquals(0, SystemLog::where('type', 'security')->count());
        $this->assertEquals(0, SystemLoginAttempt::where('success', false)->count());

        // KRITISCHER SCHUTZ: Allgemeine System- und Automations-Logs dürfen NICHT gelöscht worden sein!
        $this->assertEquals(2, SystemLog::count());
        $this->assertDatabaseHas('system_logs', ['id' => $auditLog->id]);
        $this->assertDatabaseHas('system_logs', ['id' => $backupLog->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_active_defense_ip_blocking_and_unblocking()
    {
        $this->actingAs($this->admin, 'admin');

        $ip = '203.0.113.42';

        $this->assertFalse(SystemBlockedIp::isBlocked($ip));

        $component = Livewire::test(MasterAnalytics::class)
            ->call('blockIpAddress', $ip, 24);

        $this->assertTrue(SystemBlockedIp::isBlocked($ip));
        $this->assertEquals(1, $component->get('blockedIpsCount'));

        // Entsperren
        $component->call('unblockIpAddress', $ip);
        $this->assertFalse(SystemBlockedIp::isBlocked($ip));
        $this->assertEquals(0, $component->get('blockedIpsCount'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_blocked_ip_is_rejected_on_login()
    {
        $blockedIp = '198.51.100.99';
        SystemBlockedIp::blockIp($blockedIp, 'Verdächtige Aktivitäten', 24);

        $this->assertTrue(SystemBlockedIp::isBlocked($blockedIp));

        // Simuliere Request von dieser IP
        request()->server->set('REMOTE_ADDR', $blockedIp);

        $component = Livewire::test(AuthLogin::class)
            ->set('email', 'admin@example.com')
            ->set('password', 'wrong-or-correct')
            ->call('login');

        $component->assertHasErrors(['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_aggregated_threat_logs_groups_attempts_by_ip()
    {
        $this->actingAs($this->admin, 'admin');

        // Erstelle 4 fehlgeschlagene Versuche von derselben IP
        for ($i = 0; $i < 4; $i++) {
            SystemLoginAttempt::create([
                'email' => "user{$i}@company.de",
                'ip_address' => '172.16.0.99',
                'success' => false,
                'attempted_at' => now()->subMinutes(10 - $i),
            ]);
        }

        // Erstelle 1 isolierten Security-Error-Log
        SystemLog::create([
            'type' => 'security',
            'action_id' => 'firewall:detected',
            'title' => 'SQLi Versuch abgefangen',
            'message' => 'Parameter q enthielt UNION SELECT',
            'status' => 'error',
            'started_at' => now(),
        ]);

        $component = Livewire::test(MasterAnalytics::class);
        $threatLogs = $component->get('aggregatedThreatLogs');

        $this->assertCount(2, $threatLogs);

        // Prüfe Gruppierung
        $loginThreat = $threatLogs->firstWhere('ip_address', '172.16.0.99');
        $this->assertNotNull($loginThreat);
        $this->assertEquals(4, $loginThreat['attempts']);
        $this->assertStringContainsString('Wiederholte Fehl-Logins (4x)', $loginThreat['title']);
        $this->assertStringContainsString('172.16.0.99', $loginThreat['message']);

        // Prüfe Security Log Eintrag
        $secLogThreat = $threatLogs->firstWhere('category', 'system_log');
        $this->assertNotNull($secLogThreat);
        $this->assertEquals('SQLi Versuch abgefangen', $secLogThreat['title']);
    }
}

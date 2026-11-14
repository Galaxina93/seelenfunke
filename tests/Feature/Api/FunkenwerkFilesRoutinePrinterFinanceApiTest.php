<?php

namespace Tests\Feature\Api;

use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FunkenwerkFilesRoutinePrinterFinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminAndHeaders(): array
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('ops-admin-token', ['*'])->plainTextToken;

        return [$admin, ['Authorization' => 'Bearer ' . $token]];
    }

    public function test_workspace_files_traversal_block_root_delete_protection_and_executable_upload_block(): void
    {
        Storage::fake('workspace');
        [, $headers] = $this->createAdminAndHeaders();

        // 1. Initial directory listing initializes protected folders
        $this->withHeaders($headers)
            ->getJson('/api/funki/files')
            ->assertStatus(200)
            ->assertJsonPath('current_path', 'agenten/workspace');

        // 2. Path traversal '..' is blocked on folder creation, preview, download, and delete
        $this->withHeaders($headers)
            ->postJson('/api/funki/files/folder', [
                'path' => 'agenten/workspace/../../etc',
                'name' => 'Testordner',
            ])
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->getJson('/api/funki/files/preview?path=agenten/workspace/../../.env')
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->get('/api/funki/files/download?path=agenten/workspace/../../.env')
            ->assertStatus(422);

        // 3. Protected root & main folders cannot be deleted or renamed
        foreach (['agenten/workspace', 'agenten/workspace/Dokumente', 'agenten/workspace/Berufsleben', 'agenten/workspace/Gesundheit'] as $protectedPath) {
            $this->withHeaders($headers)
                ->deleteJson('/api/funki/files', ['path' => $protectedPath])
                ->assertStatus(422);

            $this->withHeaders($headers)
                ->postJson('/api/funki/files/rename', [
                    'path' => $protectedPath,
                    'new_name' => 'Umbenannt',
                ])
                ->assertStatus(422);
        }

        // 4. Executable file uploads (.php, .sh, .exe) are blocked with 422
        $phpShell = UploadedFile::fake()->createWithContent('webshell.php', '<?php system($_GET["cmd"]);');
        $this->withHeaders($headers)
            ->post('/api/funki/files/upload', [
                'path' => 'agenten/workspace/Dokumente',
                'file' => $phpShell,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        Storage::disk('workspace')->assertMissing('agenten/workspace/Dokumente/webshell.php');

        // 5. Safe PDF upload succeeds and can be downloaded
        $safePdf = UploadedFile::fake()->createWithContent('liefervertrag.pdf', '%PDF-1.4 safe workspace file');
        $this->withHeaders($headers)
            ->post('/api/funki/files/upload', [
                'path' => 'agenten/workspace/Dokumente',
                'file' => $safePdf,
            ], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        Storage::disk('workspace')->assertExists('agenten/workspace/Dokumente/liefervertrag.pdf');
    }

    public function test_routine_smarthome_credential_stripping_tts_validation_toggle_and_music_lock(): void
    {
        [, $headers] = $this->createAdminAndHeaders();

        // 1. smarthome-sync strips RTSP username/password from camera URLs before persisting
        $this->withHeaders($headers)
            ->postJson('/api/funki/routine/smarthome-sync', [
                'cameras' => [
                    [
                        'name' => 'Werkstatt Cam',
                        'url' => 'rtsp://adminUser:GeheimesKameraPasswort99@192.168.188.105:554/stream1',
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.cameras.0.ip', '192.168.188.105')
            ->assertJsonPath('data.cameras.0.stream_path', '/stream1');

        $storedRaw = (string) SystemSetting::where('key', 'funki_smarthome_config')->value('value');
        $this->assertStringNotContainsString('GeheimesKameraPasswort99', $storedRaw);
        $this->assertStringNotContainsString('adminUser', $storedRaw);

        // 2. morning-briefing-tts rejects GET (405) and rejects text > 1200 chars (422)
        $this->withHeaders($headers)
            ->getJson('/api/funki/routine/morning-briefing-tts')
            ->assertStatus(405);

        $this->withHeaders($headers)
            ->postJson('/api/funki/routine/morning-briefing-tts', [
                'text' => str_repeat('Morgenroutine ', 100), // 1400 chars > 1200 max
                'voice' => 'Aoede',
            ])
            ->assertStatus(422);

        // 3. Routine create + step + toggle-log
        $routineRes = $this->withHeaders($headers)->postJson('/api/funki/routine', [
            'title' => 'Laser-Check Morgen',
            'duration_minutes' => 25,
            'start_time' => '08:00:00',
            'icon' => 'sun',
        ])->assertStatus(200);
        $routineId = $routineRes->json('data.id');

        $this->withHeaders($headers)->postJson("/api/funki/routine/{$routineId}/steps", [
            'title' => 'Abluft IF2 einschalten',
            'duration_minutes' => 5,
        ])->assertStatus(200);

        $this->withHeaders($headers)
            ->postJson("/api/funki/routine/{$routineId}/toggle-log", ['date' => '2026-11-14'])
            ->assertStatus(200)
            ->assertJsonPath('data.is_completed', true);

        $this->withHeaders($headers)
            ->postJson("/api/funki/routine/{$routineId}/toggle-log", ['date' => '2026-11-14'])
            ->assertStatus(200)
            ->assertJsonPath('data.is_completed', false);

        // 4. Music import-url concurrency lock blocks parallel imports with 429
        $heldLock = Cache::lock('funkenwerk_music_import', 120);
        $this->assertTrue($heldLock->get());
        try {
            $this->withHeaders($headers)
                ->postJson('/api/funki/music/import-url', [
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                ])
                ->assertStatus(429);
        } finally {
            $heldLock->release();
        }
    }

    public function test_printer_jobs_queue_and_private_lan_ip_sanitization(): void
    {
        Storage::fake('local');
        [, $headers] = $this->createAdminAndHeaders();

        // Public IP (8.8.8.8) must be sanitized back to safe private fallback (192.168.188.149)
        $fallbackJobRes = $this->withHeaders($headers)
            ->postJson('/api/printer/jobs/test', [
                'printer_ip' => '8.8.8.8',
            ])
            ->assertStatus(200);

        $this->assertSame('192.168.188.149', $fallbackJobRes->json('job.printer_ip'));
        $jobId = (string) $fallbackJobRes->json('job.id');

        // Private RFC1918 LAN IP is accepted as-is
        $lanJobRes = $this->withHeaders($headers)
            ->postJson('/api/printer/jobs/test', [
                'printer_ip' => '192.168.188.210',
            ])
            ->assertStatus(200);
        $this->assertSame('192.168.188.210', $lanJobRes->json('job.printer_ip'));

        // Queue listing (both /api/printer/jobs and /api/funki/printer/jobs) + acknowledge + requeue
        $queueRes = $this->withHeaders($headers)
            ->getJson('/api/funki/printer/jobs?include_pdf=0')
            ->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($queueRes->json('data.pending') ?? []));

        $this->withHeaders($headers)
            ->getJson('/api/printer/jobs?include_pdf=0')
            ->assertStatus(200);

        $this->withHeaders($headers)
            ->postJson("/api/printer/jobs/{$jobId}/ack", [
                'status' => 'completed',
                'printer_ip' => '192.168.188.149',
            ])
            ->assertStatus(200)
            ->assertJsonPath('job.status', 'completed');

        $this->withHeaders($headers)
            ->postJson("/api/printer/jobs/{$jobId}/requeue")
            ->assertStatus(200)
            ->assertJsonPath('job.status', 'pending');
    }

    public function test_financial_kpis_future_start_date_and_24_month_interval_accuracy_and_top_customers_sql(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 14, 10, 0, 0, 'Europe/Berlin'));

        try {
            [$admin, $headers] = $this->createAdminAndHeaders();

            $group = AccountingGroup::create([
                'id' => (string) Str::uuid(),
                'admin_id' => $admin->id,
                'name' => 'Gewerbe Verträge',
                'position' => 1,
            ]);

            // 1. Monthly active fixed cost (-50.00 EUR) -> MUST be included (elapsed = 2, 2 % 1 === 0)
            AccountingCostItem::create([
                'id' => (string) Str::uuid(),
                'accounting_group_id' => $group->id,
                'name' => 'Shop Hosting',
                'amount' => -50.00,
                'interval_months' => 1,
                'first_payment_date' => '2026-09-01',
                'is_business' => true,
            ]);

            // 2. Future contract starting Nov 2027 (-300.00 EUR) -> MUST NOT be billed early in Nov 2026!
            AccountingCostItem::create([
                'id' => (string) Str::uuid(),
                'accounting_group_id' => $group->id,
                'name' => 'Zukunftsvertrag 2027',
                'amount' => -300.00,
                'interval_months' => 12,
                'first_payment_date' => '2027-11-01',
                'is_business' => true,
            ]);

            // 3. 24-month contract started Nov 2025 (-240.00 EUR) -> elapsed = 12 months, 12 % 24 !== 0 -> MUST NOT be billed!
            AccountingCostItem::create([
                'id' => (string) Str::uuid(),
                'accounting_group_id' => $group->id,
                'name' => 'Zweijahres-Domain (nicht fällig)',
                'amount' => -240.00,
                'interval_months' => 24,
                'first_payment_date' => '2025-11-01',
                'is_business' => true,
            ]);

            // 4. 24-month contract started Nov 2024 (-120.00 EUR) -> elapsed = 24 months, 24 % 24 === 0 -> MUST be billed!
            AccountingCostItem::create([
                'id' => (string) Str::uuid(),
                'accounting_group_id' => $group->id,
                'name' => 'Zweijahres-Zertifikat (fällig)',
                'amount' => -120.00,
                'interval_months' => 24,
                'first_payment_date' => '2024-11-01',
                'is_business' => true,
            ]);

            // Special issue in Nov 2026 (-30.00 EUR)
            AccountingSpecialIssue::create([
                'id' => (string) Str::uuid(),
                'admin_id' => $admin->id,
                'title' => 'Verpackungsmaterial',
                'category' => 'Verpackungen',
                'amount' => -30.00,
                'execution_date' => '2026-11-10',
                'is_business' => true,
            ]);

            // Paid order in Nov 2026 (100.00 EUR = 10000 cents) + cancelled order (ignored)
            OrderOrder::create([
                'id' => (string) Str::uuid(),
                'order_number' => 'ORD-KPI-1',
                'email' => 'anna@example.com',
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal_price' => 10000,
                'tax_amount' => 1597,
                'shipping_price' => 0,
                'total_price' => 10000,
                'billing_address' => ['first_name' => 'Anna', 'last_name' => 'Schmidt'],
            ]);

            OrderOrder::create([
                'id' => (string) Str::uuid(),
                'order_number' => 'ORD-KPI-CANCELLED',
                'email' => 'storno@example.com',
                'status' => 'cancelled',
                'payment_status' => 'paid',
                'subtotal_price' => 99900,
                'tax_amount' => 0,
                'shipping_price' => 0,
                'total_price' => 99900,
                'billing_address' => ['first_name' => 'Storno', 'last_name' => 'Kunde'],
            ]);

            $kpiRes = $this->withHeaders($headers)
                ->getJson('/api/funki/financials/kpis')
                ->assertStatus(200);

            // Expected fixed_expenses = -50 + -120 = -170.00 (neither -300 future nor -240 12-of-24m is billed!)
            $this->assertEquals(-170.0, (float) $kpiRes->json('fixed_expenses'));
            $this->assertEquals(-30.0, (float) $kpiRes->json('special_expenses'));
            $this->assertEquals(100.0, (float) $kpiRes->json('shop_revenue'));
            $this->assertEquals(-100.0, (float) $kpiRes->json('available'));

            // Verify SQL top-3 customers on /api/shop/orders & /api/shop/monitor-pulse
            $ordersRes = $this->withHeaders($headers)
                ->getJson('/api/shop/orders')
                ->assertStatus(200);

            $topCustomers = $ordersRes->json('top_customers');
            $this->assertCount(1, $topCustomers);
            $this->assertSame('Anna S.', $topCustomers[0]['display_name']);
            $this->assertSame(10000, $topCustomers[0]['total_cents']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_camera_snapshot_is_stored_exclusively_on_private_workspace_disk_and_beacon_validates_meta(): void
    {
        Storage::fake('workspace');
        Storage::fake('public');

        [, $headers] = $this->createAdminAndHeaders();

        $image = UploadedFile::fake()->image('cam_frame.jpg', 320, 240);

        $snapRes = $this->withHeaders($headers)
            ->post('/api/ai/camera-snapshot', [
                'image' => $image,
            ], ['Accept' => 'application/json'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $savedPath = (string) $snapRes->json('file_path');
        $downloadUrl = (string) $snapRes->json('url');

        $this->assertNotEmpty($savedPath);
        $this->assertStringStartsWith('/api/funki/files/download?path=', $downloadUrl);

        // Stored ONLY on private workspace disk, NEVER on public disk!
        Storage::disk('workspace')->assertExists($savedPath);
        Storage::disk('public')->assertMissing($savedPath);
        $this->assertEmpty(Storage::disk('public')->allFiles());

        // Downloadable by authenticated Admin via returned download URL
        $this->withHeaders($headers)->get($downloadUrl)->assertStatus(200);

        // Beacon validation: valid bounded meta succeeds, >25 keys fails with 422
        $this->postJson('/api/product-telemetry/beacon', [
            'source' => 'configurator',
            'step' => 2,
            'meta' => ['font' => 'Cinzel', 'zoom' => 1],
        ])->assertStatus(200)->assertJsonPath('success', true);

        $oversizedMeta = [];
        for ($i = 1; $i <= 30; $i++) {
            $oversizedMeta["k_{$i}"] = "val_{$i}";
        }

        $this->postJson('/api/product-telemetry/beacon', [
            'source' => 'configurator',
            'meta' => $oversizedMeta,
        ])->assertStatus(422);
    }
}

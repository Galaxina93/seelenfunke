<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\System\SystemSetting;
use App\Services\System\KdriveTrichterRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KdriveTrichterRelayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(KdriveTrichterRelayService::relayDirectory());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(KdriveTrichterRelayService::relayDirectory());
        parent::tearDown();
    }

    public function test_kdrive_trichter_encrypts_batch_verifies_sha256_and_deletes_on_ack(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        $pdfBytes1 = "%PDF-1.4\n1 0 obj\n<< /Title (PrintEquipment Rechnung 1) >>\nendobj\n%%EOF";
        $pdfBytes2 = "%PDF-1.4\n1 0 obj\n<< /Title (BKK firmus Schreiben 2) >>\nendobj\n%%EOF";

        $file1 = UploadedFile::fake()->createWithContent('Scan_2026-11-20_190001_01.pdf', $pdfBytes1);
        $file2 = UploadedFile::fake()->createWithContent('Scan_2026-11-20_190001_02.pdf', $pdfBytes2);

        $res1 = $this->postJson('/api/funki/kdrive-trichter/enqueue', [
            'file' => $file1,
            'custom_title' => 'Tagespost',
            'source' => 'android_batch_scanner',
            'batch_index' => 1,
            'batch_total' => 2,
        ])->assertOk()->assertJsonPath('success', true);

        $res2 = $this->postJson('/api/funki/kdrive-trichter/enqueue', [
            'file' => $file2,
            'custom_title' => 'Tagespost',
            'source' => 'android_batch_scanner',
            'batch_index' => 2,
            'batch_total' => 2,
        ])->assertOk()->assertJsonPath('success', true);

        $id1 = (string) $res1->json('relay_id');
        $id2 = (string) $res2->json('relay_id');

        // Verify encrypted storage on disk (zero plaintext PDF header!)
        $encFile1 = KdriveTrichterRelayService::relayDirectory() . DIRECTORY_SEPARATOR . $id1 . '.enc';
        $this->assertFileExists($encFile1);
        $rawEncOnDisk = (string) file_get_contents($encFile1);
        $this->assertStringNotContainsString('%PDF', $rawEncOnDisk);
        $this->assertStringNotContainsString('PrintEquipment', $rawEncOnDisk);

        // Pending list preserves exact chronological batch order
        $pending = $this->getJson('/api/funki/kdrive-trichter/pending')
            ->assertOk()
            ->assertJsonPath('count', 2);

        $items = $pending->json('items');
        $this->assertSame($id1, $items[0]['id']);
        $this->assertSame($id2, $items[1]['id']);
        $this->assertStringEndsWith('_Tagespost_01.pdf', $items[0]['file_name']);
        $this->assertStringEndsWith('_Tagespost_02.pdf', $items[1]['file_name']);

        // Pull decrypts exact bytes + SHA-256 header
        $pull1 = $this->get('/api/funki/kdrive-trichter/pull/' . $id1)->assertOk();
        $this->assertSame($pdfBytes1, $pull1->getContent());
        $this->assertSame(hash('sha256', $pdfBytes1), $pull1->headers->get('X-Trichter-Sha256'));

        // ACK removes encrypted payload from server (0 byte permanent footprint)
        $this->deleteJson('/api/funki/kdrive-trichter/ack/' . $id1)
            ->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('remaining_count', 1);

        $this->assertFileDoesNotExist($encFile1);
        $this->get('/api/funki/kdrive-trichter/pull/' . $id1)->assertNotFound();

        $this->deleteJson('/api/funki/kdrive-trichter/ack/' . $id2)
            ->assertOk()
            ->assertJsonPath('remaining_count', 0);
    }

    public function test_kdrive_trichter_blocks_unauthenticated_and_non_admin_roles(): void
    {
        $this->getJson('/api/funki/kdrive-trichter/pending')->assertUnauthorized();

        $influencer = Employee::create([
            'first_name' => 'Test',
            'last_name' => 'Influencer',
            'email' => 'inf-trichter@example.test',
            'role' => 'influencer',
            'password' => bcrypt('Secret123!'),
        ]);
        Sanctum::actingAs($influencer, ['*']);

        $this->getJson('/api/funki/kdrive-trichter/pending')->assertForbidden();
    }

    public function test_kdrive_trichter_classify_endpoint_maps_to_meine_daten_folders(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        $file = UploadedFile::fake()->createWithContent('PrintEquipment_Rohlinge_Rechnung.pdf', '%PDF-1.4 test');
        $res = $this->postJson('/api/funki/kdrive-trichter/classify', [
            'file' => $file,
            'folders' => KdriveTrichterRelayService::DEFAULT_TARGET_FOLDERS,
        ])->assertOk();

        $this->assertSame('01_Mein-Seelenfunke', $res->json('data.target_folder'));
    }

    public function test_kdrive_webdav_config_flushes_pending_scans_and_sorts_new_scans_directly(): void
    {
        KdriveTrichterRelayService::resetWebDavCaches();

        $this->assertSame('654321', KdriveTrichterRelayService::extractKdriveId('654321'));
        $this->assertSame('654321', KdriveTrichterRelayService::extractKdriveId('https://654321.connect.kdrive.infomaniak.com'));
        $this->assertSame('654321', KdriveTrichterRelayService::extractKdriveId('https://ksuite.infomaniak.com/all/kdrive/app/drive/654321/files/99'));
        $this->assertNull(KdriveTrichterRelayService::extractKdriveId('https://evil.example.com/123456'));

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        // 1. Scan arrives BEFORE kDrive WebDAV is connected -> safely encrypted in relay queue
        $waitingPdf = "%PDF-1.4\n1 0 obj\n<< /Title (Waiting Scan) >>\nendobj\n%%EOF";
        $waitingFile = UploadedFile::fake()->createWithContent('Scan_2026-11-22_143200.pdf', $waitingPdf);

        $queuedRes = $this->postJson('/api/funki/kdrive-trichter/enqueue', [
            'file' => $waitingFile,
            'custom_title' => 'Westpack Geschenkboxen',
            'source' => 'android_scanner',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('direct_uploaded', false)
          ->assertJsonPath('kdrive_configured', false);

        $this->assertSame(1, KdriveTrichterRelayService::countPending());
        $this->assertNotEmpty($queuedRes->json('relay_id'));

        // 2. Mock Infomaniak kDrive WebDAV server (API v3 returns 401 for WebDAV password, WebDAV handles PROPFIND/MKCOL/PUT)
        $uploadedFilesOnWebDav = [];
        Http::fake(function (HttpClientRequest $req) use (&$uploadedFilesOnWebDav) {
            $method = strtoupper($req->method());
            $url = $req->url();

            if (str_starts_with($url, 'https://api.infomaniak.com/')) {
                return Http::response(['result' => 'error', 'error' => ['code' => 'not_authorized']], 401);
            }

            if (!str_starts_with($url, 'https://654321.connect.kdrive.infomaniak.com/')) {
                return Http::response('Forbidden host', 403);
            }

            if ($method === 'PROPFIND') {
                if (str_ends_with($url, '/')) {
                    return Http::response('<d:multistatus xmlns:d="DAV:"/>', 207);
                }
                return isset($uploadedFilesOnWebDav[$url])
                    ? Http::response('<d:multistatus xmlns:d="DAV:"/>', 207)
                    : Http::response('Not Found', 404);
            }

            if ($method === 'MKCOL') {
                return Http::response('', 201);
            }

            if ($method === 'PUT') {
                $uploadedFilesOnWebDav[$url] = $req->body();
                return Http::response('', 201);
            }

            return Http::response('Unsupported', 405);
        });

        // 3. Connect kDrive in Settings -> verifies WebDAV AND immediately flushes the waiting scan!
        $cfgRes = $this->postJson('/api/funki/kdrive-trichter/config', [
            'drive_id' => 'https://ksuite.infomaniak.com/all/kdrive/app/drive/654321/files/1',
            'email' => 'kontakt@mein-seelenfunke.de',
            'app_password' => 'AppPassSecret9988',
            'direct_sort' => true,
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.configured', true)
          ->assertJsonPath('data.drive_id', '654321')
          ->assertJsonPath('data.has_password', true)
          ->assertJsonPath('data.flushed_count', 1)
          ->assertJsonPath('data.pending_relay_count', 0);

        // Verify password is encrypted in DB and NEVER exposed in GET /config
        $rawDbJson = (string) SystemSetting::where('key', KdriveTrichterRelayService::WEBDAV_SETTING_KEY)->value('value');
        $this->assertStringNotContainsString('AppPassSecret9988', $rawDbJson);

        $statusRes = $this->getJson('/api/funki/kdrive-trichter/config')->assertOk();
        $this->assertArrayNotHasKey('app_password', $statusRes->json('data'));
        $this->assertTrue((bool) $statusRes->json('data.has_password'));

        // Waiting scan was directly sorted into 01_Mein-Seelenfunke/Eingangsrechnungen_Belege!
        $this->assertCount(1, $uploadedFilesOnWebDav);
        $firstUrl = array_key_first($uploadedFilesOnWebDav);
        $this->assertStringContainsString('/Meine%20Daten/01_Mein-Seelenfunke/Eingangsrechnungen_Belege/', $firstUrl);
        $this->assertStringContainsString('Westpack_Geschenkboxen.pdf', $firstUrl);
        $this->assertSame($waitingPdf, $uploadedFilesOnWebDav[$firstUrl]);

        // 4. New scan arrives -> directly classified & uploaded into 8-pillar subfolder without touching relay disk
        $bkkBytes = "%PDF-1.4\n1 0 obj\n<< /Title (BKK Bescheid) >>\nendobj\n%%EOF";
        $bkkFile = UploadedFile::fake()->createWithContent('Scan_2026-11-22_154500.pdf', $bkkBytes);

        $directRes = $this->postJson('/api/funki/kdrive-trichter/enqueue', [
            'file' => $bkkFile,
            'custom_title' => 'BKK firmus Bescheid',
            'source' => 'android_scanner',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('direct_uploaded', true);

        $this->assertStringStartsWith('06_Gesundheit/Krankenkasse/', (string) $directRes->json('remote_path'));
        $this->assertSame(0, KdriveTrichterRelayService::countPending());
    }

    public function test_infomaniak_api_v3_token_works_on_my_ksuite_without_webdav(): void
    {
        KdriveTrichterRelayService::resetWebDavCaches();

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['*']);

        // Waiting scan from 14:32
        $waitingPdf = "%PDF-1.4\n1 0 obj\n<< /Title (Waiting Scan 1432) >>\nendobj\n%%EOF";
        $waitingFile = UploadedFile::fake()->createWithContent('Scan_2026-11-22_143200.pdf', $waitingPdf);
        $this->postJson('/api/funki/kdrive-trichter/enqueue', [
            'file' => $waitingFile,
            'custom_title' => 'PrintEquipment Rohlinge',
            'source' => 'android_scanner',
        ])->assertOk();
        $this->assertSame(1, KdriveTrichterRelayService::countPending());

        $apiUploads = [];
        Http::fake(function (HttpClientRequest $req) use (&$apiUploads) {
            $url = $req->url();
            $auth = $req->header('Authorization')[0] ?? '';

            if ($auth !== 'Bearer ik-api-token-drive-999') {
                if (str_contains($url, '.connect.kdrive.infomaniak.com')) {
                    return Http::response('', 403); // Simulates Infomaniak my kSuite WebDAV 403 block
                }
                return Http::response(['result' => 'error'], 401);
            }

            if ($url === 'https://api.infomaniak.com/3/drive/2216011/files/1/files?limit=100') {
                return Http::response([
                    'result' => 'success',
                    'data' => [
                        ['id' => 10, 'name' => 'Common documents', 'type' => 'dir', 'visibility' => 'is_team_space'],
                        ['id' => 77, 'name' => 'Private', 'type' => 'dir', 'visibility' => 'is_private_space'],
                    ],
                ], 200);
            }

            if ($url === 'https://api.infomaniak.com/3/drive/2216011/files/77/files?limit=100') {
                return Http::response([
                    'result' => 'success',
                    'data' => [
                        ['id' => 101, 'name' => '00_Eingang_Trichter', 'type' => 'dir'],
                        ['id' => 102, 'name' => '01_Mein-Seelenfunke', 'type' => 'dir'],
                    ],
                ], 200);
            }

            if (str_starts_with($url, 'https://api.infomaniak.com/3/drive/2216011/upload?')) {
                $apiUploads[$url] = $req->body();
                parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
                return Http::response([
                    'result' => 'success',
                    'data' => [
                        'id' => 5001,
                        'name' => $q['file_name'] ?? 'Scan.pdf',
                        'type' => 'file',
                    ],
                ], 200);
            }

            return Http::response('Unexpected URL: ' . $url, 404);
        });

        // 1. Entering a WebDAV password on my kSuite returns the clear API-Token guidance instead of generic 403
        $blockedWebDavRes = $this->postJson('/api/funki/kdrive-trichter/config', [
            'drive_id' => '2216011',
            'email' => 'alina.stone@t-online.de',
            'app_password' => 'wrong-or-webdav-app-pass',
        ])->assertStatus(422);
        $this->assertStringContainsString('API-Token', (string) $blockedWebDavRes->json('message'));

        // 2. Saving an Infomaniak API-Token (even without email!) connects via API v3 AND flushes the waiting scan!
        $this->postJson('/api/funki/kdrive-trichter/config', [
            'drive_id' => '2216011',
            'app_password' => 'ik-api-token-drive-999',
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.configured', true)
          ->assertJsonPath('data.mode', 'api_v3')
          ->assertJsonPath('data.flushed_count', 1)
          ->assertJsonPath('data.pending_relay_count', 0);

        $this->assertCount(1, $apiUploads);
        $uploadUrl = array_key_first($apiUploads);
        parse_str((string) parse_url($uploadUrl, PHP_URL_QUERY), $queryParams);
        $this->assertSame('77', $queryParams['directory_id']);
        $this->assertSame('/01_Mein-Seelenfunke/Eingangsrechnungen_Belege', $queryParams['directory_path']);
        $this->assertStringContainsString('PrintEquipment_Rohlinge.pdf', $queryParams['file_name']);
        $this->assertSame('sha256:' . hash('sha256', $waitingPdf), $queryParams['total_chunk_hash']);
        $this->assertSame($waitingPdf, $apiUploads[$uploadUrl]);
    }
}

<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Services\System\KdriveTrichterRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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
}

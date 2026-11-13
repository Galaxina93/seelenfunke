<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Services\System\MiniPcTrichterRelayService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MiniPcTrichterRelayTest extends TestCase
{
    use DatabaseTransactions;

    private array $createdRelayIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdRelayIds as $id) {
            MiniPcTrichterRelayService::ackAndDelete($id);
        }
        parent::tearDown();
    }

    public function test_encrypted_trichter_relay_end_to_end_and_token_isolation(): void
    {
        $admin = Admin::factory()->create();
        $fullToken = $admin->createToken('AndroidApp', ['*'])->plainTextToken;
        $monitorOnlyToken = $admin->createToken('MonitorOnly', ['monitor:pulse'])->plainTextToken;
        $funkiraMiniPcToken = $admin->createToken('FunkenwerkMiniPc', ['monitor:pulse', 'ai:funkira'])->plainTextToken;

        $secretMarker = 'GEHEIMER_STEUERBESCHEID_GIFHORN_987654321';
        $fakePdfBytes = "%PDF-1.4\n1 0 obj << /Title ({$secretMarker}) >> endobj\n%%EOF";
        $uploaded = UploadedFile::fake()->createWithContent('Scan_2026-11-13_145500.pdf', $fakePdfBytes);

        // 1. Android App uploads document into encrypted Trichter relay
        $enqueueResp = $this->withHeader('Authorization', 'Bearer ' . $fullToken)
            ->postJson('/api/funki/minipc-trichter/enqueue', [
                'file' => $uploaded,
                'custom_title' => 'Finanzamt Bescheid 2026',
                'source' => 'android_widget',
            ]);

        $enqueueResp->assertOk()
            ->assertJsonPath('success', true);

        $relayId = (string) $enqueueResp->json('relay_id');
        $this->assertTrue(MiniPcTrichterRelayService::isValidRelayId($relayId));
        $this->createdRelayIds[] = $relayId;

        // 2. Verify payload is encrypted at rest (plaintext marker MUST NOT appear on disk)
        $encPath = MiniPcTrichterRelayService::relayDirectory() . DIRECTORY_SEPARATOR . $relayId . '.enc';
        $metaPath = MiniPcTrichterRelayService::relayDirectory() . DIRECTORY_SEPARATOR . $relayId . '.json';
        $this->assertFileExists($encPath);
        $this->assertFileExists($metaPath);
        $diskCiphertext = File::get($encPath);
        $this->assertStringNotContainsString($secretMarker, $diskCiphertext);
        $this->assertStringNotContainsString(base64_encode($secretMarker), $diskCiphertext);

        // 3. Monitor pulse exposes pending_trichter_count >= 1
        $pulseResp = $this->withHeader('Authorization', 'Bearer ' . $monitorOnlyToken)
            ->getJson('/api/shop/monitor-pulse');
        $pulseResp->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $pulseResp->json('pending_trichter_count'));

        // 4. Plain monitor:pulse token MUST be blocked (403) from pulling decrypted Trichter files
        $this->withHeader('Authorization', 'Bearer ' . $monitorOnlyToken)
            ->getJson('/api/shop/minipc-trichter/pending')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $monitorOnlyToken)
            ->get('/api/shop/minipc-trichter/pull/' . $relayId)
            ->assertStatus(403);

        // 5. Scoped ai:funkira Mini-PC token lists, pulls decrypted bytes with matching SHA-256, and ACKs/wipes
        $pendingResp = $this->withHeader('Authorization', 'Bearer ' . $funkiraMiniPcToken)
            ->getJson('/api/shop/minipc-trichter/pending');
        $pendingResp->assertOk()
            ->assertJsonPath('success', true);

        $pullResp = $this->withHeader('Authorization', 'Bearer ' . $funkiraMiniPcToken)
            ->get('/api/shop/minipc-trichter/pull/' . $relayId);
        $pullResp->assertOk();
        $this->assertSame($fakePdfBytes, $pullResp->getContent());
        $this->assertSame(hash('sha256', $fakePdfBytes), $pullResp->headers->get('X-Trichter-Sha256'));
        $this->assertSame('Finanzamt Bescheid 2026', rawurldecode((string) $pullResp->headers->get('X-Trichter-Title')));

        $ackResp = $this->withHeader('Authorization', 'Bearer ' . $funkiraMiniPcToken)
            ->deleteJson('/api/shop/minipc-trichter/ack/' . $relayId);
        $ackResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('deleted', true);

        $this->assertFileDoesNotExist($encPath);
        $this->assertFileDoesNotExist($metaPath);
    }
}

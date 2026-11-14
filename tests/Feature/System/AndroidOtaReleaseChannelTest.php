<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\System\AndroidReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

class AndroidOtaReleaseChannelTest extends TestCase
{
    use RefreshDatabase;

    private string $isolatedVaultDir;
    private string $realSignedApkPath;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();

        $this->realSignedApkPath = storage_path('app/private/releases/android/funkenwerk-latest.apk');
        $this->isolatedVaultDir = sys_get_temp_dir() . '/sf_ota_test_vault_' . bin2hex(random_bytes(6));
        mkdir($this->isolatedVaultDir, 0750, true);

        $this->app->instance(AndroidReleaseService::class, new AndroidReleaseService($this->isolatedVaultDir));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->isolatedVaultDir)) {
            File::deleteDirectory($this->isolatedVaultDir);
        }

        parent::tearDown();
    }

    public function test_unauthenticated_and_non_admin_requests_are_strictly_blocked(): void
    {
        // 1. Unauthenticated API calls -> 401
        $this->getJson('/api/funki/system/app-update/check')->assertStatus(401);
        $this->getJson('/api/funki/system/app-update/download')->assertStatus(401);
        $this->postJson('/api/funki/system/app-update/publish', [])->assertStatus(401);

        // 2. Unauthenticated web route -> never 200
        $webGuestResp = $this->get('/admin/system/funkenwerk-apk/download');
        $this->assertContains($webGuestResp->getStatusCode(), [302, 401, 403]);

        // 3. Authenticated Admin WITHOUT valid URL signature -> 403
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')
            ->get('/admin/system/funkenwerk-apk/download')
            ->assertStatus(403);

        // 4. Customer Sanctum token -> 403 Forbidden
        $customer = Customer::factory()->create();
        $custToken = $customer->createToken('test-customer-ota')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $custToken)
            ->getJson('/api/funki/system/app-update/check')
            ->assertStatus(403);
        $this->withHeader('Authorization', 'Bearer ' . $custToken)
            ->getJson('/api/funki/system/app-update/download')
            ->assertStatus(403);
        $this->withHeader('Authorization', 'Bearer ' . $custToken)
            ->postJson('/api/funki/system/app-update/publish', [])
            ->assertStatus(403);

        // 5. All Employee roles (influencer, tax_advisor, internal) -> 403 Forbidden
        foreach (['influencer', 'tax_advisor', 'internal'] as $role) {
            $employee = Employee::create([
                'first_name' => 'Test',
                'last_name' => ucfirst($role),
                'email' => "ota-{$role}@example.com",
                'role' => $role,
                'password' => bcrypt('Secret123!'),
            ]);
            $empToken = $employee->createToken("test-emp-{$role}-ota")->plainTextToken;
            $this->withHeader('Authorization', 'Bearer ' . $empToken)
                ->getJson('/api/funki/system/app-update/check')
                ->assertStatus(403);
            $this->withHeader('Authorization', 'Bearer ' . $empToken)
                ->getJson('/api/funki/system/app-update/download')
                ->assertStatus(403);
        }
    }

    public function test_cryptographic_apk_gate_rejects_unsigned_rogue_cert_and_tampered_signatures(): void
    {
        $svc = app(AndroidReleaseService::class);

        // 1. Plain ZIP with AndroidManifest.xml + classes.dex (> 512 KB incompressible) WITHOUT APK Sig Block 42 -> rejected
        $unsignedZip = $this->isolatedVaultDir . '/unsigned.apk';
        $zip = new ZipArchive();
        $zip->open($unsignedZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('AndroidManifest.xml', random_bytes(320000));
        $zip->addFromString('classes.dex', random_bytes(320000));
        $zip->close();

        try {
            $svc->storeVerifiedRelease($unsignedZip, 20, '2.0.0');
            $this->fail('Unsigned ZIP without APK Sig Block 42 must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Signaturblock', $e->getMessage());
        }

        // 2. Forged APK with structurally valid APK Sig Block 42 signed by a ROGUE 4096-bit RSA key -> rejected by certificate pinning
        $rogueSignedApk = $this->buildSyntheticV2SignedApk($unsignedZip, null, false);
        try {
            $svc->storeVerifiedRelease($rogueSignedApk, 20, '2.0.0');
            $this->fail('APK signed by a rogue RSA key must be rejected by server-side certificate pinning.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Funkenwerk-Zertifikat', $e->getMessage());
        }

        // 3. Forged APK carrying the REAL pinned public X.509 certificate but an INVALID RSA signature -> rejected by OpenSSL verification
        $this->assertFileExists($this->realSignedApkPath);
        $realPinnedCertDer = $this->extractFirstCertDerFromRealApk($this->realSignedApkPath);
        $this->assertSame(AndroidReleaseService::PINNED_CERT_SHA256, hash('sha256', $realPinnedCertDer));

        $spoofedCertApk = $this->buildSyntheticV2SignedApk($unsignedZip, $realPinnedCertDer, true);
        try {
            $svc->storeVerifiedRelease($spoofedCertApk, 20, '2.0.0');
            $this->fail('APK with spoofed public certificate and invalid RSA signature must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Funkenwerk-Zertifikat', $e->getMessage());
        }
    }

    public function test_admin_publish_check_stream_signed_web_download_and_anti_rollback_end_to_end(): void
    {
        $this->assertFileExists($this->realSignedApkPath);
        $expectedSha = hash_file('sha256', $this->realSignedApkPath);
        $expectedSize = filesize($this->realSignedApkPath);

        $admin = Admin::factory()->create();
        $adminToken = $admin->createToken('test-admin-ota-e2e')->plainTextToken;

        // 1. Before publish: check returns has_release = false, download returns 404
        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/funki/system/app-update/check')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'has_release' => false,
                'data' => null,
            ]);

        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->get('/api/funki/system/app-update/download')
            ->assertStatus(404);

        // 2. Publish with mismatched SHA-256 -> 422 rejected
        $uploadCopyBadSha = $this->isolatedVaultDir . '/upload_bad_sha.apk';
        copy($this->realSignedApkPath, $uploadCopyBadSha);
        $badUploaded = new UploadedFile($uploadCopyBadSha, 'app-release.apk', 'application/vnd.android.package-archive', null, true);

        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/funki/system/app-update/publish', [
                'apk' => $badUploaded,
                'version_code' => 13,
                'version_name' => '1.6.0',
                'sha256' => str_repeat('0', 64),
            ])
            ->assertStatus(422);

        // 3. Publish valid 4096-bit RSA signed release (v1.6.0, build 13) -> 200 OK
        $uploadCopyValid = $this->isolatedVaultDir . '/upload_valid_13.apk';
        copy($this->realSignedApkPath, $uploadCopyValid);
        $validUploaded = new UploadedFile($uploadCopyValid, 'app-release.apk', 'application/vnd.android.package-archive', null, true);

        $pubResp = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/funki/system/app-update/publish', [
                'apk' => $validUploaded,
                'version_code' => 13,
                'version_name' => '1.6.0',
                'sha256' => $expectedSha,
                'release_notes' => 'E2E Hardening Test v1.6.0',
            ]);

        $pubResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'package_name' => 'de.meinseelenfunke.app',
                    'version_code' => 13,
                    'version_name' => '1.6.0',
                    'sha256' => $expectedSha,
                    'cert_sha256' => AndroidReleaseService::PINNED_CERT_SHA256,
                    'file_size' => $expectedSize,
                ],
            ]);

        // 4. Check endpoint returns complete verified manifest with no-store headers
        $checkResp = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/funki/system/app-update/check');

        $checkResp->assertStatus(200)
            ->assertJsonPath('has_release', true)
            ->assertJsonPath('data.version_code', 13)
            ->assertJsonPath('data.release_notes', 'E2E Hardening Test v1.6.0');
        $this->assertStringContainsString('no-store', (string) $checkResp->headers->get('Cache-Control'));

        // 5. Stream download via Bearer header -> exact byte length & exact SHA-256 match
        $dlResp = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->get('/api/funki/system/app-update/download');

        $dlResp->assertStatus(200);
        $this->assertSame((string) $expectedSize, $dlResp->headers->get('Content-Length'));
        $this->assertSame($expectedSha, $dlResp->headers->get('X-Apk-Sha256'));
        $streamedBytes = $dlResp->streamedContent();
        $this->assertSame($expectedSize, strlen($streamedBytes));
        $this->assertSame($expectedSha, hash('sha256', $streamedBytes));

        // 6. Signed Admin web portal download route -> 200 OK
        $signedUrl = URL::temporarySignedRoute('admin.system.funkenwerk-apk.download', now()->addMinutes(5));
        $webDlResp = $this->actingAs($admin, 'admin')->get($signedUrl);
        $webDlResp->assertStatus(200);
        $this->assertSame($expectedSha, hash('sha256', $webDlResp->streamedContent()));

        // 7. Anti-rollback: publishing older build 12 -> 422 rejected
        $uploadCopy12 = $this->isolatedVaultDir . '/upload_downgrade_12.apk';
        copy($this->realSignedApkPath, $uploadCopy12);
        $downgradeUploaded = new UploadedFile($uploadCopy12, 'app-release.apk', 'application/vnd.android.package-archive', null, true);

        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/funki/system/app-update/publish', [
                'apk' => $downgradeUploaded,
                'version_code' => 12,
                'version_name' => '1.5.0',
            ])
            ->assertStatus(422);

        // 8. Upgrade to build 14 -> succeeds and cleans up superseded release-b13-*.apk
        $uploadCopy14 = $this->isolatedVaultDir . '/upload_upgrade_14.apk';
        copy($this->realSignedApkPath, $uploadCopy14);
        $upgradeUploaded = new UploadedFile($uploadCopy14, 'app-release.apk', 'application/vnd.android.package-archive', null, true);

        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/funki/system/app-update/publish', [
                'apk' => $upgradeUploaded,
                'version_code' => 14,
                'version_name' => '1.6.1',
                'expected_sha256' => $expectedSha,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.version_code', 14);

        $versionedFiles = glob($this->isolatedVaultDir . '/release-b*.apk') ?: [];
        $this->assertCount(1, $versionedFiles);
        $this->assertStringContainsString('release-b14-', basename($versionedFiles[0]));
    }

    public function test_livewire_system_shop_config_publishes_verified_apk_and_renders_signed_download_link(): void
    {
        $admin = Admin::factory()->create();

        // Guest cannot mount SystemShopConfig
        \Livewire\Livewire::test(\App\Livewire\Shop\System\SystemShopConfig::class)
            ->assertForbidden();

        $uploadCopy = $this->isolatedVaultDir . '/livewire_upload_13.apk';
        copy($this->realSignedApkPath, $uploadCopy);
        $fakeLivewireFile = UploadedFile::fake()->createWithContent('Funkenwerk-v1.6.0.apk', (string) file_get_contents($uploadCopy));

        $this->actingAs($admin, 'admin');

        \Livewire\Livewire::test(\App\Livewire\Shop\System\SystemShopConfig::class)
            ->set('androidVersionName', '1.6.0')
            ->set('androidVersionCode', 13)
            ->set('androidApkUpload', $fakeLivewireFile)
            ->call('publishAndroidApk')
            ->assertHasNoErrors()
            ->assertSee('Funkenwerk Android App')
            ->assertSee('APK herunterladen (v1.6.0)');
    }

    private function extractFirstCertDerFromRealApk(string $apkPath): string
    {
        $size = (int) filesize($apkPath);
        $fp = fopen($apkPath, 'rb');
        $win = min($size, 65557);
        fseek($fp, $size - $win);
        $tail = (string) fread($fp, $win);
        $eocd = strrpos($tail, "PK\x05\x06");
        $cdOffset = (int) unpack('V', substr($tail, $eocd + 16, 4))[1];
        fseek($fp, $cdOffset - 24);
        $blockSizeFooter = (int) unpack('P', (string) fread($fp, 8))[1];
        fseek($fp, $cdOffset - 8 - $blockSizeFooter + 8);
        $pairsBytes = (string) fread($fp, $blockSizeFooter - 24);
        fclose($fp);

        $pairLen = (int) unpack('P', substr($pairsBytes, 0, 8))[1];
        $val = substr($pairsBytes, 12, $pairLen - 4);
        $signer = substr($val, 4, (int) unpack('V', substr($val, 0, 4))[1]);
        $firstSigner = substr($signer, 4, (int) unpack('V', substr($signer, 0, 4))[1]);
        $signedData = substr($firstSigner, 4, (int) unpack('V', substr($firstSigner, 0, 4))[1]);
        $digestsLen = (int) unpack('V', substr($signedData, 0, 4))[1];
        $certs = substr($signedData, 8 + $digestsLen, (int) unpack('V', substr($signedData, 4 + $digestsLen, 4))[1]);

        return substr($certs, 4, (int) unpack('V', substr($certs, 0, 4))[1]);
    }

    private function buildSyntheticV2SignedApk(string $baseZipPath, ?string $overrideCertDer, bool $corruptSignature): string
    {
        $rawZip = (string) file_get_contents($baseZipPath);
        $eocdPos = strrpos($rawZip, "PK\x05\x06");
        $origCdOffset = (int) unpack('V', substr($rawZip, $eocdPos + 16, 4))[1];

        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new(['commonName' => 'Rogue Signer'], $privKey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privKey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($x509, $certPem);
        $rogueCertDer = (string) base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $certPem));

        $certToEmbed = $overrideCertDer ?? $rogueCertDer;
        $digestsPayload = pack('V', 0);
        $certEntry = pack('V', strlen($certToEmbed)) . $certToEmbed;
        $certsPayload = pack('V', strlen($certEntry)) . $certEntry;
        $signedData = $digestsPayload . $certsPayload . pack('V', 0);

        openssl_sign($signedData, $sigBytes, $privKey, OPENSSL_ALGO_SHA256);
        if ($corruptSignature) {
            $sigBytes[0] = chr(ord($sigBytes[0]) ^ 0xFF);
        }

        $sigEntryInner = pack('V', 0x0103) . pack('V', strlen($sigBytes)) . $sigBytes;
        $sigsPayload = pack('V', strlen($sigEntryInner)) . $sigEntryInner;
        $pubKeyDummy = str_repeat('K', 64);

        $firstSignerInner = pack('V', strlen($signedData)) . $signedData
            . pack('V', strlen($sigsPayload)) . $sigsPayload
            . pack('V', strlen($pubKeyDummy)) . $pubKeyDummy;
        $signersPayload = pack('V', strlen($firstSignerInner)) . $firstSignerInner;
        $v2Value = pack('V', strlen($signersPayload)) . $signersPayload;

        $pairBody = pack('V', 0x7109871a) . $v2Value;
        $pairs = pack('P', strlen($pairBody)) . $pairBody;
        $blockSize = strlen($pairs) + 24;
        $sigBlock = pack('P', $blockSize) . $pairs . pack('P', $blockSize) . 'APK Sig Block 42';

        $beforeCd = substr($rawZip, 0, $origCdOffset);
        $cdAndEocd = substr($rawZip, $origCdOffset);
        $newCdOffset = $origCdOffset + strlen($sigBlock);
        $eocdRel = strrpos($cdAndEocd, "PK\x05\x06");
        $patchedCdAndEocd = substr_replace($cdAndEocd, pack('V', $newCdOffset), $eocdRel + 16, 4);

        $outPath = $this->isolatedVaultDir . '/synthetic_' . bin2hex(random_bytes(4)) . '.apk';
        file_put_contents($outPath, $beforeCd . $sigBlock . $patchedCdAndEocd);

        return $outPath;
    }
}

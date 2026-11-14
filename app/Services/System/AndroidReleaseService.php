<?php

namespace App\Services\System;

use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AndroidReleaseService
{
    public const PACKAGE_NAME = 'de.meinseelenfunke.app';
    public const PINNED_CERT_SHA256 = 'e4c85cdd79648fa19430c91b47e1db036544d60d2fa6136a0c5106300c849064';
    public const MIN_APK_BYTES = 524288;      // 512 KB
    public const MAX_APK_BYTES = 99614720;    // 95 MB

    public function __construct(
        private readonly ?string $vaultDirOverride = null
    ) {
    }

    public function vaultDirectory(): string
    {
        return $this->vaultDirOverride ?? storage_path('app/private/releases/android');
    }

    public function apkPath(): string
    {
        $dir = $this->vaultDirectory();
        $manifestFile = $this->manifestPath();
        if (is_file($manifestFile) && !is_link($manifestFile)) {
            $decoded = json_decode((string) @file_get_contents($manifestFile), true);
            $apkFile = is_array($decoded) ? trim((string) ($decoded['apk_file'] ?? '')) : '';
            if ($apkFile !== '' && preg_match('/^[A-Za-z0-9._-]+\.apk$/', $apkFile)) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $apkFile;
                if (is_file($candidate) && !is_link($candidate)) {
                    return $candidate;
                }
            }
        }

        return $dir . DIRECTORY_SEPARATOR . 'funkenwerk-latest.apk';
    }

    public function manifestPath(): string
    {
        return $this->vaultDirectory() . DIRECTORY_SEPARATOR . 'manifest.json';
    }

    public function getLatestManifest(): ?array
    {
        $manifestFile = $this->manifestPath();
        if (is_link($manifestFile) || !is_file($manifestFile)) {
            return null;
        }

        $raw = @file_get_contents($manifestFile);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $apkFile = $this->resolveManifestApkFile($decoded);
        if ($apkFile === null || is_link($apkFile) || !is_file($apkFile)) {
            return null;
        }

        $expectedSha = strtolower(trim((string) ($decoded['sha256'] ?? '')));
        $actualSize = @filesize($apkFile);
        if (
            !preg_match('/^[a-f0-9]{64}$/', $expectedSha)
            || !is_int($actualSize)
            || $actualSize < self::MIN_APK_BYTES
            || $actualSize !== (int) ($decoded['file_size'] ?? 0)
        ) {
            return null;
        }

        return [
            'package_name' => self::PACKAGE_NAME,
            'version_code' => (int) ($decoded['version_code'] ?? 0),
            'version_name' => substr(trim((string) ($decoded['version_name'] ?? '1.0.0')), 0, 32),
            'sha256' => $expectedSha,
            'cert_sha256' => self::PINNED_CERT_SHA256,
            'file_size' => $actualSize,
            'file_name' => $this->buildCleanFilename((string) ($decoded['version_name'] ?? '1.0.0'), (int) ($decoded['version_code'] ?? 1)),
            'release_notes' => substr(trim((string) ($decoded['release_notes'] ?? '')), 0, 500),
            'published_at' => (string) ($decoded['published_at'] ?? ''),
        ];
    }

    public function storeVerifiedRelease(
        string $sourceTempFile,
        int $versionCode,
        string $versionName,
        ?string $expectedSha256 = null,
        ?string $releaseNotes = null,
        bool $allowSameVersionRebuild = true
    ): array {
        if (is_link($sourceTempFile) || !is_file($sourceTempFile)) {
            throw new RuntimeException('Ungültige Quelldatei für das APK-Release.');
        }

        if ($versionCode < 1 || $versionCode > 100000000) {
            throw new RuntimeException('Ungültiger Version-Code.');
        }

        $cleanVersionName = trim($versionName);
        if (!preg_match('/^[0-9]+(\.[0-9]+){1,3}(-[A-Za-z0-9._]+)?$/', $cleanVersionName)) {
            throw new RuntimeException('Ungültiges Versionsformat (erwartet z. B. 1.6.0).');
        }

        clearstatcache(true, $sourceTempFile);
        $size = @filesize($sourceTempFile);
        if (!is_int($size) || $size < self::MIN_APK_BYTES || $size > self::MAX_APK_BYTES) {
            throw new RuntimeException('APK-Dateigröße außerhalb des zulässigen Bereichs.');
        }

        $this->verifyApkStructureAndPinnedCertificate($sourceTempFile);

        $computedSha256 = strtolower((string) hash_file('sha256', $sourceTempFile));
        if (!preg_match('/^[a-f0-9]{64}$/', $computedSha256)) {
            throw new RuntimeException('SHA-256-Prüfsumme konnte nicht berechnet werden.');
        }

        if ($expectedSha256 !== null && trim($expectedSha256) !== '') {
            $cleanExpected = strtolower(trim($expectedSha256));
            if (!hash_equals($computedSha256, $cleanExpected)) {
                throw new RuntimeException('SHA-256-Prüfsumme stimmt nicht mit der Übertragung überein.');
            }
        }

        $dir = $this->vaultDirectory();
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0750, true) && !is_dir($dir)) {
                throw new RuntimeException('Privates Release-Verzeichnis konnte nicht erstellt werden.');
            }
        }
        if (is_link($dir)) {
            throw new RuntimeException('Sicherheitsabbruch: Release-Verzeichnis darf kein Symlink sein.');
        }

        $lockFile = $dir . DIRECTORY_SEPARATOR . '.publish.lock';
        $lockFp = @fopen($lockFile, 'c+');
        if ($lockFp === false || !@flock($lockFp, LOCK_EX)) {
            if (is_resource($lockFp)) {
                fclose($lockFp);
            }
            throw new RuntimeException('Release-Sperre konnte nicht erworben werden.');
        }

        try {
            $existing = $this->getLatestManifest();
            if ($existing !== null) {
                $existingCode = (int) ($existing['version_code'] ?? 0);
                if ($versionCode < $existingCode || (!$allowSameVersionRebuild && $versionCode === $existingCode)) {
                    throw new RuntimeException("Rollback-Schutz: Neuer Build ({$versionCode}) darf nicht älter als der aktuelle Stand ({$existingCode}) sein.");
                }
            }

            $versionedApkName = sprintf('release-b%d-%s.apk', $versionCode, substr($computedSha256, 0, 12));
            $versionedApkPath = $dir . DIRECTORY_SEPARATOR . $versionedApkName;
            $tmpApk = $dir . DIRECTORY_SEPARATOR . $versionedApkName . '.tmp.' . bin2hex(random_bytes(6));

            if (!@copy($sourceTempFile, $tmpApk)) {
                throw new RuntimeException('APK konnte nicht im Tresor abgelegt werden.');
            }
            @chmod($tmpApk, 0640);

            $postCopySha = strtolower((string) hash_file('sha256', $tmpApk));
            if (!hash_equals($computedSha256, $postCopySha)) {
                @unlink($tmpApk);
                throw new RuntimeException('Integritätsfehler beim Schreiben der APK.');
            }

            if (!@rename($tmpApk, $versionedApkPath)) {
                @unlink($tmpApk);
                throw new RuntimeException('Atomare Aktivierung der APK fehlgeschlagen.');
            }

            // Also keep funkenwerk-latest.apk updated atomically for direct path callers
            $tmpLatest = $dir . DIRECTORY_SEPARATOR . 'funkenwerk-latest.apk.tmp.' . bin2hex(random_bytes(6));
            if (@copy($versionedApkPath, $tmpLatest)) {
                @chmod($tmpLatest, 0640);
                if (!@rename($tmpLatest, $dir . DIRECTORY_SEPARATOR . 'funkenwerk-latest.apk')) {
                    @unlink($tmpLatest);
                }
            }

            $manifestRecord = [
                'package_name' => self::PACKAGE_NAME,
                'version_code' => $versionCode,
                'version_name' => $cleanVersionName,
                'sha256' => $computedSha256,
                'cert_sha256' => self::PINNED_CERT_SHA256,
                'file_size' => $size,
                'file_name' => $this->buildCleanFilename($cleanVersionName, $versionCode),
                'apk_file' => $versionedApkName,
                'release_notes' => substr(trim((string) ($releaseNotes ?? '')), 0, 500),
                'published_at' => Carbon::now('Europe/Berlin')->toIso8601String(),
            ];

            $tmpManifest = $dir . DIRECTORY_SEPARATOR . 'manifest.json.tmp.' . bin2hex(random_bytes(6));
            if (@file_put_contents($tmpManifest, json_encode($manifestRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
                @unlink($tmpManifest);
                throw new RuntimeException('Release-Manifest konnte nicht geschrieben werden.');
            }
            @chmod($tmpManifest, 0640);

            if (!@rename($tmpManifest, $this->manifestPath())) {
                @unlink($tmpManifest);
                throw new RuntimeException('Atomare Aktivierung des Manifests fehlgeschlagen.');
            }

            // Clean up superseded release-b*.apk files safely
            foreach (glob($dir . DIRECTORY_SEPARATOR . 'release-b*.apk') ?: [] as $oldApk) {
                if (basename($oldApk) !== $versionedApkName && !is_link($oldApk)) {
                    @unlink($oldApk);
                }
            }

            unset($manifestRecord['apk_file']);
            return $manifestRecord;
        } finally {
            @flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    public function streamLatestApk(Request $request): StreamedResponse
    {
        if ($request->hasSession()) {
            $request->session()->save();
        }
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $manifestFile = $this->manifestPath();
        $decoded = (is_file($manifestFile) && !is_link($manifestFile))
            ? json_decode((string) @file_get_contents($manifestFile), true)
            : null;
        $apkFile = is_array($decoded) ? $this->resolveManifestApkFile($decoded) : null;
        $manifest = $this->getLatestManifest();

        if ($manifest === null || $apkFile === null || is_link($apkFile) || !is_file($apkFile)) {
            abort(404, 'Kein verifiziertes Android-Release hinterlegt.');
        }

        $liveSha = strtolower((string) hash_file('sha256', $apkFile));
        if (!hash_equals((string) $manifest['sha256'], $liveSha)) {
            abort(500, 'Integritätsprüfung der gespeicherten APK fehlgeschlagen.');
        }

        $size = (int) $manifest['file_size'];
        $downloadName = $manifest['file_name'];

        $headers = [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Length' => (string) $size,
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Apk-Version-Code' => (string) $manifest['version_code'],
            'X-Apk-Version-Name' => (string) $manifest['version_name'],
            'X-Apk-Sha256' => (string) $manifest['sha256'],
            'X-Apk-Cert-Sha256' => self::PINNED_CERT_SHA256,
        ];

        return response()->stream(function () use ($apkFile, $size) {
            $fp = @fopen($apkFile, 'rb');
            if ($fp === false) {
                return;
            }
            $remaining = $size;
            while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
                $chunk = fread($fp, min(262144, $remaining));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
            fclose($fp);
        }, 200, $headers);
    }

    private function resolveManifestApkFile(array $decoded): ?string
    {
        $dir = $this->vaultDirectory();
        $apkFile = trim((string) ($decoded['apk_file'] ?? ''));
        if ($apkFile !== '' && preg_match('/^[A-Za-z0-9._-]+\.apk$/', $apkFile)) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $apkFile;
            if (is_file($candidate) && !is_link($candidate)) {
                return $candidate;
            }
        }

        $fallback = $dir . DIRECTORY_SEPARATOR . 'funkenwerk-latest.apk';
        return (is_file($fallback) && !is_link($fallback)) ? $fallback : null;
    }

    private function verifyApkStructureAndPinnedCertificate(string $filePath): void
    {
        $fp = @fopen($filePath, 'rb');
        if ($fp === false) {
            throw new RuntimeException('APK-Datei konnte nicht zum Prüfen geöffnet werden.');
        }
        $magic = fread($fp, 4);
        fclose($fp);

        if ($magic !== "PK\x03\x04") {
            throw new RuntimeException('Ungültiger Datei-Header: Keine echte Android-APK.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive-Erweiterung fehlt zur APK-Prüfung.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('APK-Archiv ist beschädigt oder unlesbar.');
        }

        $hasManifest = $zip->locateName('AndroidManifest.xml') !== false;
        $hasDex = $zip->locateName('classes.dex') !== false;
        $zip->close();

        if (!$hasManifest || !$hasDex) {
            throw new RuntimeException('APK enthält kein gültiges AndroidManifest.xml oder classes.dex.');
        }

        // Parse & cryptographically verify APK Signature Scheme v2 (0x7109871a) or v3 (0xf05368c0) against PINNED_CERT_SHA256
        $size = (int) filesize($filePath);
        $eocdWindow = min($size, 65557);
        $fpTail = @fopen($filePath, 'rb');
        if ($fpTail === false) {
            throw new RuntimeException('APK-Signaturblock konnte nicht gelesen werden.');
        }

        try {
            fseek($fpTail, max(0, $size - $eocdWindow));
            $tailBytes = (string) fread($fpTail, $eocdWindow);
            $eocdPos = strrpos($tailBytes, "PK\x05\x06");
            if ($eocdPos === false || strlen($tailBytes) < $eocdPos + 20) {
                throw new RuntimeException('Ungültiges ZIP-EOCD in APK.');
            }

            $unpackedCd = unpack('VcdOffset', substr($tailBytes, $eocdPos + 16, 4));
            $cdOffset = (int) ($unpackedCd['cdOffset'] ?? 0);
            if ($cdOffset < 32 || $cdOffset >= $size) {
                throw new RuntimeException('APK besitzt keinen gültigen V2/V3-Signaturblock.');
            }

            fseek($fpTail, $cdOffset - 24);
            $footer = (string) fread($fpTail, 24);
            if (strlen($footer) !== 24 || substr($footer, 8, 16) !== 'APK Sig Block 42') {
                throw new RuntimeException('APK besitzt keinen gültigen V2/V3-Signaturblock.');
            }

            $blockSizeFooter = (int) (unpack('Psize', substr($footer, 0, 8))['size'] ?? 0);
            $blockStart = $cdOffset - 8 - $blockSizeFooter;
            if ($blockSizeFooter < 32 || $blockSizeFooter > 16777216 || $blockStart < 0) {
                throw new RuntimeException('Ungültige Größe des APK-Signaturblocks.');
            }

            fseek($fpTail, $blockStart);
            $headerBytes = (string) fread($fpTail, 8);
            $blockSizeHeader = strlen($headerBytes) === 8 ? (int) (unpack('Psize', $headerBytes)['size'] ?? -1) : -1;
            if ($blockSizeHeader !== $blockSizeFooter) {
                throw new RuntimeException('Inkonsistenter APK-Signaturblock.');
            }

            $pairsLen = $blockSizeFooter - 24;
            $pairsBytes = $pairsLen > 0 ? (string) fread($fpTail, $pairsLen) : '';
        } finally {
            fclose($fpTail);
        }

        if (strlen($pairsBytes) !== $pairsLen) {
            throw new RuntimeException('APK-Signaturblock konnte nicht vollständig gelesen werden.');
        }

        $verifiedPinnedSigner = false;
        $pos = 0;
        while ($pos + 12 <= $pairsLen) {
            $pairLen = (int) (unpack('Plen', substr($pairsBytes, $pos, 8))['len'] ?? 0);
            $pos += 8;
            if ($pairLen < 4 || $pos + $pairLen > $pairsLen) {
                break;
            }
            $pairId = (int) (unpack('Vid', substr($pairsBytes, $pos, 4))['id'] ?? 0);
            $pairVal = substr($pairsBytes, $pos + 4, $pairLen - 4);
            $pos += $pairLen;

            // APK Signature Scheme v2 (0x7109871a) or v3 (0xf05368c0)
            if ($pairId === 0x7109871a || $pairId === 0xf05368c0) {
                if ($this->verifyV2OrV3SignerBlock($pairVal)) {
                    $verifiedPinnedSigner = true;
                    break;
                }
            }
        }

        if (!$verifiedPinnedSigner) {
            throw new RuntimeException('Sicherheitsabbruch: APK ist nicht mit dem offiziellen 4096-Bit-RSA-Funkenwerk-Zertifikat signiert.');
        }
    }

    private function verifyV2OrV3SignerBlock(string $blockValue): bool
    {
        if (strlen($blockValue) < 16) {
            return false;
        }

        $signersLen = (int) (unpack('Vlen', substr($blockValue, 0, 4))['len'] ?? 0);
        if ($signersLen < 12 || 4 + $signersLen > strlen($blockValue)) {
            return false;
        }
        $signersBuf = substr($blockValue, 4, $signersLen);

        $firstSignerLen = (int) (unpack('Vlen', substr($signersBuf, 0, 4))['len'] ?? 0);
        if ($firstSignerLen < 12 || 4 + $firstSignerLen > strlen($signersBuf)) {
            return false;
        }
        $firstSigner = substr($signersBuf, 4, $firstSignerLen);

        $signedDataLen = (int) (unpack('Vlen', substr($firstSigner, 0, 4))['len'] ?? 0);
        if ($signedDataLen < 8 || 4 + $signedDataLen + 4 > strlen($firstSigner)) {
            return false;
        }
        $signedData = substr($firstSigner, 4, $signedDataLen);

        // Inside signedData: length-prefixed digests, followed by length-prefixed X.509 certificates
        $digestsLen = (int) (unpack('Vlen', substr($signedData, 0, 4))['len'] ?? 0);
        $certsOffset = 4 + $digestsLen;
        if ($certsOffset + 8 > strlen($signedData)) {
            return false;
        }
        $certsLen = (int) (unpack('Vlen', substr($signedData, $certsOffset, 4))['len'] ?? 0);
        if ($certsLen < 4 || $certsOffset + 4 + $certsLen > strlen($signedData)) {
            return false;
        }
        $certsBuf = substr($signedData, $certsOffset + 4, $certsLen);
        $firstCertLen = (int) (unpack('Vlen', substr($certsBuf, 0, 4))['len'] ?? 0);
        if ($firstCertLen < 64 || 4 + $firstCertLen > strlen($certsBuf)) {
            return false;
        }
        $firstCertDer = substr($certsBuf, 4, $firstCertLen);

        // Enforce exact SHA-256 match against our pinned 4096-bit RSA certificate
        $certSha256 = strtolower(hash('sha256', $firstCertDer));
        if (!hash_equals(self::PINNED_CERT_SHA256, $certSha256)) {
            return false;
        }

        // Locate signatures block inside firstSigner (in v2 right after signedData; in v3 after signedData + minSdk + maxSdk)
        $sigsCandidateOffsets = [4 + $signedDataLen, 4 + $signedDataLen + 8];
        $pemCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($firstCertDer), 64, "\n") . "-----END CERTIFICATE-----\n";

        foreach ($sigsCandidateOffsets as $sigsOffset) {
            if ($sigsOffset + 4 > strlen($firstSigner)) {
                continue;
            }
            $sigsLen = (int) (unpack('Vlen', substr($firstSigner, $sigsOffset, 4))['len'] ?? 0);
            if ($sigsLen < 12 || $sigsOffset + 4 + $sigsLen > strlen($firstSigner)) {
                continue;
            }
            $sigsBuf = substr($firstSigner, $sigsOffset + 4, $sigsLen);
            $sigPos = 0;
            while ($sigPos + 12 <= strlen($sigsBuf)) {
                $entryLen = (int) (unpack('Vlen', substr($sigsBuf, $sigPos, 4))['len'] ?? 0);
                $sigPos += 4;
                if ($entryLen < 8 || $sigPos + $entryLen > strlen($sigsBuf)) {
                    break;
                }
                $entry = substr($sigsBuf, $sigPos, $entryLen);
                $sigPos += $entryLen;

                $sigAlgo = (int) (unpack('Valgo', substr($entry, 0, 4))['algo'] ?? 0);
                $sigBytesLen = (int) (unpack('Vlen', substr($entry, 4, 4))['len'] ?? 0);
                if ($sigBytesLen < 64 || 8 + $sigBytesLen > strlen($entry)) {
                    continue;
                }
                $sigBytes = substr($entry, 8, $sigBytesLen);

                // 0x0103 = RSA-PKCS1-v1_5-SHA256, 0x0104 = RSA-PKCS1-v1_5-SHA512
                $opensslAlgo = match ($sigAlgo) {
                    0x0103 => OPENSSL_ALGO_SHA256,
                    0x0104 => OPENSSL_ALGO_SHA512,
                    default => null,
                };
                if ($opensslAlgo !== null && @openssl_verify($signedData, $sigBytes, $pemCert, $opensslAlgo) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function buildCleanFilename(string $versionName, int $versionCode): string
    {
        $safeVer = preg_replace('/[^0-9A-Za-z._-]/', '', $versionName) ?: '1.0.0';
        return "Funkenwerk-v{$safeVer}-b{$versionCode}.apk";
    }
}

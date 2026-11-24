<?php

namespace App\Services\System;

use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class WindowsReleaseService
{
    public const PACKAGE_ID = 'de.meinseelenfunke.funkenwerk.windows';
    public const MIN_ZIP_BYTES = 16384;         // 16 KB
    public const MAX_ZIP_BYTES = 536870912;     // 512 MB

    public function __construct(
        private readonly ?string $vaultDirOverride = null
    ) {
    }

    public function vaultDirectory(): string
    {
        return $this->vaultDirOverride ?? storage_path('app/private/releases/windows');
    }

    public function manifestPath(): string
    {
        return $this->vaultDirectory() . DIRECTORY_SEPARATOR . 'manifest.json';
    }

    public function suitePath(): string
    {
        $dir = $this->vaultDirectory();
        $manifestFile = $this->manifestPath();
        if (is_file($manifestFile) && !is_link($manifestFile)) {
            $decoded = json_decode((string) @file_get_contents($manifestFile), true);
            $zipFile = is_array($decoded) ? trim((string) ($decoded['zip_file'] ?? '')) : '';
            if ($zipFile !== '' && preg_match('/^[A-Za-z0-9._-]+\.zip$/', $zipFile)) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $zipFile;
                if (is_file($candidate) && !is_link($candidate)) {
                    return $candidate;
                }
            }
        }

        return $dir . DIRECTORY_SEPARATOR . 'funkenwerk-windows-latest.zip';
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

        $zipFile = $this->resolveManifestZipFile($decoded);
        if ($zipFile === null || is_link($zipFile) || !is_file($zipFile)) {
            return null;
        }

        $expectedSha = strtolower(trim((string) ($decoded['sha256'] ?? '')));
        $actualSize = @filesize($zipFile);
        if (
            !preg_match('/^[a-f0-9]{64}$/', $expectedSha)
            || !is_int($actualSize)
            || $actualSize < self::MIN_ZIP_BYTES
            || $actualSize !== (int) ($decoded['file_size'] ?? 0)
        ) {
            return null;
        }

        return [
            'package_id'    => self::PACKAGE_ID,
            'version_code'  => (int) ($decoded['version_code'] ?? 0),
            'version_name'  => substr(trim((string) ($decoded['version_name'] ?? '1.0.0')), 0, 32),
            'sha256'        => $expectedSha,
            'file_size'     => $actualSize,
            'size_bytes'    => $actualSize,
            'file_name'     => $this->buildCleanFilename((string) ($decoded['version_name'] ?? '1.0.0'), (int) ($decoded['version_code'] ?? 1)),
            'modules'       => is_array($decoded['modules'] ?? null) ? $decoded['modules'] : [],
            'release_notes' => substr(trim((string) ($decoded['release_notes'] ?? '')), 0, 500),
            'published_at'  => (string) ($decoded['published_at'] ?? ''),
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
            throw new RuntimeException('Ungültige Quelldatei für das Windows-Release.');
        }

        if ($versionCode < 1 || $versionCode > 100000000) {
            throw new RuntimeException('Ungültiger Build-Code.');
        }

        $cleanVersionName = trim($versionName);
        if (!preg_match('/^[0-9]+(\.[0-9]+){1,3}(-[A-Za-z0-9._]+)?$/', $cleanVersionName)) {
            throw new RuntimeException('Ungültiges Versionsformat (erwartet z. B. 1.0.0).');
        }

        clearstatcache(true, $sourceTempFile);
        $size = @filesize($sourceTempFile);
        if (!is_int($size) || $size < self::MIN_ZIP_BYTES || $size > self::MAX_ZIP_BYTES) {
            throw new RuntimeException('ZIP-Paketgröße außerhalb des zulässigen Bereichs.');
        }

        $detectedModules = $this->verifyWindowsSuiteZipArchive($sourceTempFile);

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
                throw new RuntimeException('Privates Windows-Release-Verzeichnis konnte nicht erstellt werden.');
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

            $versionedZipName = sprintf('release-b%d-%s.zip', $versionCode, substr($computedSha256, 0, 12));
            $versionedZipPath = $dir . DIRECTORY_SEPARATOR . $versionedZipName;
            $tmpZip = $dir . DIRECTORY_SEPARATOR . $versionedZipName . '.tmp.' . bin2hex(random_bytes(6));

            if (!@copy($sourceTempFile, $tmpZip)) {
                throw new RuntimeException('Windows-Paket konnte nicht im Tresor abgelegt werden.');
            }
            @chmod($tmpZip, 0640);

            $postCopySha = strtolower((string) hash_file('sha256', $tmpZip));
            if (!hash_equals($computedSha256, $postCopySha)) {
                @unlink($tmpZip);
                throw new RuntimeException('Integritätsfehler beim Schreiben des Windows-Pakets.');
            }

            if (!@rename($tmpZip, $versionedZipPath)) {
                @unlink($tmpZip);
                throw new RuntimeException('Atomare Aktivierung des Windows-Pakets fehlgeschlagen.');
            }

            $tmpLatest = $dir . DIRECTORY_SEPARATOR . 'funkenwerk-windows-latest.zip.tmp.' . bin2hex(random_bytes(6));
            if (@copy($versionedZipPath, $tmpLatest)) {
                @chmod($tmpLatest, 0640);
                if (!@rename($tmpLatest, $dir . DIRECTORY_SEPARATOR . 'funkenwerk-windows-latest.zip')) {
                    @unlink($tmpLatest);
                }
            }

            $manifestRecord = [
                'package_id'    => self::PACKAGE_ID,
                'version_code'  => $versionCode,
                'version_name'  => $cleanVersionName,
                'sha256'        => $computedSha256,
                'file_size'     => $size,
                'file_name'     => $this->buildCleanFilename($cleanVersionName, $versionCode),
                'zip_file'      => $versionedZipName,
                'modules'       => $detectedModules,
                'release_notes' => substr(trim((string) ($releaseNotes ?? '')), 0, 500),
                'published_at'  => Carbon::now('Europe/Berlin')->toIso8601String(),
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

            foreach (glob($dir . DIRECTORY_SEPARATOR . 'release-b*.zip') ?: [] as $oldZip) {
                if (basename($oldZip) !== $versionedZipName && !is_link($oldZip)) {
                    @unlink($oldZip);
                }
            }

            unset($manifestRecord['zip_file']);

            return $manifestRecord;
        } finally {
            @flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    public function streamLatestSuite(Request $request): StreamedResponse
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
        $zipFile = is_array($decoded) ? $this->resolveManifestZipFile($decoded) : null;
        $manifest = $this->getLatestManifest();

        if ($manifest === null || $zipFile === null || is_link($zipFile) || !is_file($zipFile)) {
            abort(404, 'Kein verifiziertes Windows-Release hinterlegt.');
        }

        $liveSha = strtolower((string) hash_file('sha256', $zipFile));
        if (!hash_equals((string) $manifest['sha256'], $liveSha)) {
            abort(500, 'Integritätsprüfung des gespeicherten Windows-Pakets fehlgeschlagen.');
        }

        $size = (int) $manifest['file_size'];
        $downloadName = $manifest['file_name'];

        return response()->stream(function () use ($zipFile) {
            $fp = @fopen($zipFile, 'rb');
            if (!is_resource($fp)) {
                return;
            }
            try {
                while (!feof($fp)) {
                    $chunk = fread($fp, 65536);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    if (function_exists('flush')) {
                        @flush();
                    }
                }
            } finally {
                fclose($fp);
            }
        }, 200, [
            'Content-Type'              => 'application/zip',
            'Content-Length'            => (string) $size,
            'Content-Disposition'       => 'attachment; filename="' . $downloadName . '"',
            'X-Release-Version-Name'    => (string) $manifest['version_name'],
            'X-Release-Version-Code'    => (string) $manifest['version_code'],
            'X-Release-Sha256'          => (string) $manifest['sha256'],
            'Cache-Control'             => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options'    => 'nosniff',
        ]);
    }

    /**
     * Prüft die ZIP-Struktur auf Zip-Slip/Pfad-Traversal und verifiziert, dass echte Funkenwerk-Binaries enthalten sind.
     *
     * @return array<int, string>
     */
    private function verifyWindowsSuiteZipArchive(string $zipPath): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new RuntimeException('Ungültiges ZIP-Archiv.');
        }

        $foundFunkenwerkBinary = false;
        $modules = [];

        try {
            if ($zip->numFiles < 1 || $zip->numFiles > 25000) {
                throw new RuntimeException('Unplausible Dateianzahl im Windows-Release-Archiv.');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = is_array($stat) ? str_replace('\\', '/', (string) ($stat['name'] ?? '')) : '';
                if ($entryName === '') {
                    continue;
                }

                if (
                    str_starts_with($entryName, '/')
                    || str_contains($entryName, '../')
                    || str_contains($entryName, '/..')
                    || preg_match('/^[A-Za-z]:/', $entryName)
                    || str_contains($entryName, "\0")
                ) {
                    throw new RuntimeException('Sicherheitsabbruch: Ungültiger Archivpfad (Zip-Slip-Schutz).');
                }

                $base = basename($entryName);
                if (preg_match('/^Funkenwerk[A-Za-z0-9]*\.(exe|dll)$/i', $base)) {
                    $foundFunkenwerkBinary = true;
                    $stem = pathinfo($base, PATHINFO_FILENAME);
                    if (!in_array($stem, $modules, true)) {
                        $modules[] = $stem;
                    }
                }
            }
        } finally {
            $zip->close();
        }

        if (!$foundFunkenwerkBinary) {
            throw new RuntimeException('Archiv abgelehnt: Keine gültige Funkenwerk-Programmdatei (Funkenwerk*.exe) im Paket gefunden.');
        }

        sort($modules);

        return $modules;
    }

    private function resolveManifestZipFile(array $decoded): ?string
    {
        $dir = $this->vaultDirectory();
        $zipName = trim((string) ($decoded['zip_file'] ?? ''));
        if ($zipName !== '' && preg_match('/^[A-Za-z0-9._-]+\.zip$/', $zipName)) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $zipName;
            if (is_file($candidate) && !is_link($candidate)) {
                return $candidate;
            }
        }

        $fallback = $dir . DIRECTORY_SEPARATOR . 'funkenwerk-windows-latest.zip';

        return (is_file($fallback) && !is_link($fallback)) ? $fallback : null;
    }

    private function buildCleanFilename(string $versionName, int $versionCode): string
    {
        $safeVer = preg_replace('/[^0-9A-Za-z._-]/', '', $versionName) ?: '1.0.0';

        return sprintf('Funkenwerk-Windows-Suite-v%s-b%d.zip', $safeVer, max(1, $versionCode));
    }
}

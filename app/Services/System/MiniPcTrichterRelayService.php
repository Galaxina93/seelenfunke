<?php

namespace App\Services\System;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MiniPcTrichterRelayService
{
    public const MAX_BYTES = 26214400; // 25 MB

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'csv', 'docx', 'xlsx', 'odt', 'ods',
    ];

    public static function relayDirectory(): string
    {
        $dir = storage_path('app/private/minipc_trichter_relay');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true, true);
        }
        return $dir;
    }

    public static function purgeExpired(int $maxAgeDays = 14): void
    {
        $dir = self::relayDirectory();
        $cutoff = time() - ($maxAgeDays * 86400);

        foreach (File::glob($dir . '/*.json') ?: [] as $metaFile) {
            try {
                if (@filemtime($metaFile) < $cutoff) {
                    $id = pathinfo($metaFile, PATHINFO_FILENAME);
                    self::ackAndDelete($id);
                }
            } catch (\Throwable $e) {
            }
        }
    }

    public static function countPending(): int
    {
        $dir = self::relayDirectory();
        $files = File::glob($dir . '/*.json');
        return is_array($files) ? count($files) : 0;
    }

    public static function enqueueUploadedFile(UploadedFile $file, ?string $customTitle = null, string $source = 'android'): array
    {
        self::purgeExpired();

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Ungültige Dateigröße (maximal 25 MB erlaubt).');
        }

        $origName = (string) $file->getClientOriginalName();
        $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $mime = strtolower((string) $file->getMimeType());
            $ext = match ($mime) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'text/plain' => 'txt',
                default => '',
            };
        }

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Dateityp nicht für den Eingang-Trichter freigegeben.');
        }

        $rawBytes = @file_get_contents($file->getRealPath());
        if ($rawBytes === false || $rawBytes === '') {
            throw new \RuntimeException('Hochgeladene Datei konnte nicht gelesen werden.');
        }

        return self::enqueueRawBytes(
            $rawBytes,
            $origName !== '' ? $origName : ('Trichter_Scan_' . date('Ymd_His') . '.' . $ext),
            (string) ($file->getMimeType() ?: 'application/octet-stream'),
            $customTitle,
            $source
        );
    }

    public static function enqueueRawBytes(
        string $rawBytes,
        string $originalName,
        string $mimeType = 'application/pdf',
        ?string $customTitle = null,
        string $source = 'android'
    ): array {
        $len = strlen($rawBytes);
        if ($len <= 0 || $len > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Ungültige Dateigröße.');
        }

        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Ungültige Dateiendung.');
        }

        $cleanStem = preg_replace('/[^A-Za-z0-9ÄÖÜäöüß._ -]/u', '_', (string) pathinfo($originalName, PATHINFO_FILENAME));
        $cleanStem = trim((string) preg_replace('/_+/', '_', (string) $cleanStem), ' ._');
        if ($cleanStem === '') {
            $cleanStem = 'Trichter_Dokument_' . date('Ymd_His');
        }
        $cleanStem = mb_substr($cleanStem, 0, 90);
        $safeFileName = $cleanStem . '.' . $ext;

        $cleanTitle = $customTitle !== null
            ? trim((string) preg_replace('/[\r\n\t]+/', ' ', $customTitle))
            : '';
        $cleanTitle = mb_substr($cleanTitle, 0, 120);

        $id = (string) Str::uuid();
        $dir = self::relayDirectory();
        $encPath = $dir . DIRECTORY_SEPARATOR . $id . '.enc';
        $metaPath = $dir . DIRECTORY_SEPARATOR . $id . '.json';

        $encryptedPayload = Crypt::encryptString(base64_encode($rawBytes));
        $sha256 = hash('sha256', $rawBytes);

        $meta = [
            'id' => $id,
            'file_name' => $safeFileName,
            'custom_title' => $cleanTitle,
            'mime_type' => $mimeType,
            'extension' => $ext,
            'size_bytes' => $len,
            'sha256' => $sha256,
            'source' => $source,
            'created_at' => now('Europe/Berlin')->toIso8601String(),
        ];

        File::put($encPath, $encryptedPayload, true);
        @chmod($encPath, 0600);
        File::put($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), true);
        @chmod($metaPath, 0600);

        return $meta;
    }

    public static function listPending(): array
    {
        self::purgeExpired();
        $dir = self::relayDirectory();
        $items = [];

        foreach (File::glob($dir . '/*.json') ?: [] as $metaPath) {
            try {
                $id = pathinfo($metaPath, PATHINFO_FILENAME);
                if (!self::isValidRelayId($id)) {
                    continue;
                }
                if (!File::exists($dir . DIRECTORY_SEPARATOR . $id . '.enc')) {
                    @unlink($metaPath);
                    continue;
                }
                $decoded = json_decode((string) File::get($metaPath), true);
                if (is_array($decoded) && isset($decoded['id'])) {
                    $items[] = $decoded;
                }
            } catch (\Throwable $e) {
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));
        return $items;
    }

    public static function pullDecrypted(string $id): ?array
    {
        if (!self::isValidRelayId($id)) {
            return null;
        }

        $dir = self::relayDirectory();
        $encPath = $dir . DIRECTORY_SEPARATOR . $id . '.enc';
        $metaPath = $dir . DIRECTORY_SEPARATOR . $id . '.json';

        if (!File::exists($encPath) || !File::exists($metaPath)) {
            return null;
        }

        $meta = json_decode((string) File::get($metaPath), true);
        if (!is_array($meta)) {
            return null;
        }

        $decryptedB64 = Crypt::decryptString((string) File::get($encPath));
        $rawBytes = base64_decode($decryptedB64, true);
        if ($rawBytes === false) {
            return null;
        }

        return [
            'meta' => $meta,
            'bytes' => $rawBytes,
        ];
    }

    public static function ackAndDelete(string $id): bool
    {
        if (!self::isValidRelayId($id)) {
            return false;
        }

        $dir = self::relayDirectory();
        $encPath = $dir . DIRECTORY_SEPARATOR . $id . '.enc';
        $metaPath = $dir . DIRECTORY_SEPARATOR . $id . '.json';

        $existed = File::exists($encPath) || File::exists($metaPath);

        if (File::exists($encPath)) {
            try {
                $sz = (int) @filesize($encPath);
                if ($sz > 0 && $sz <= 35000000) {
                    $fp = @fopen($encPath, 'r+b');
                    if (is_resource($fp)) {
                        @fwrite($fp, str_repeat("\0", min($sz, 65536)));
                        @fflush($fp);
                        @fclose($fp);
                    }
                }
            } catch (\Throwable $e) {
            }
            @unlink($encPath);
        }

        if (File::exists($metaPath)) {
            @unlink($metaPath);
        }

        return $existed;
    }

    public static function isValidRelayId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id));
    }
}

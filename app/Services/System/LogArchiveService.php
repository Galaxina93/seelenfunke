<?php

namespace App\Services\System;

use App\Models\System\SystemLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LogArchiveService
{
    public const ARCHIVE_ROOT_REL = 'logs/archive';

    /**
     * Liefert alle regulären aktiven Logdateien direkt unter storage/logs/*.log (inkl. laravel.log und cron-*.log,
     * strikt exklusive storage/logs/archive/ sowie Symlinks).
     *
     * @return list<string>
     */
    public static function listActiveLogFiles(): array
    {
        $logsDir = storage_path('logs');
        if (!is_dir($logsDir)) {
            return [];
        }

        $active = [];
        foreach (glob($logsDir . '/*.log') ?: [] as $logPath) {
            if (!is_file($logPath) || is_link($logPath) || dirname($logPath) !== $logsDir) {
                continue;
            }
            $active[] = $logPath;
        }

        return $active;
    }

    /**
     * Extrahiert aus einer einzelnen Logzeile echte Fehler-/Absturz-Meldungen (Monolog ERROR..EMERGENCY,
     * twilio_bridge.log sowie CLI-/PHP-Fehler in cron-*.log), ohne harmlose Info-Zeilen auszulösen.
     */
    public static function extractLogErrorLine(string $baseName, string $line): ?string
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\[[^\]]+\]\s+[A-Za-z0-9_.-]+\.(?:ERROR|CRITICAL|ALERT|EMERGENCY):\s*(.+)$/', $trimmed, $m)) {
            return trim($m[1]);
        }

        if ($baseName === 'twilio_bridge.log' && (str_contains($trimmed, '❌') || stripos($trimmed, 'ERROR:') !== false)) {
            return $trimmed;
        }

        if (str_starts_with($baseName, 'cron-')) {
            if (preg_match('/^(?:\[(?:ERROR|CRITICAL|ALERT|EMERGENCY)\]|(?:PHP\s+)?(?:Fatal|Parse)\s+error:|Uncaught\s+[A-Za-z0-9_\\\\]+(?:Exception|Error|Throwable):|ErrorException:)\s*(.+)$/i', $trimmed, $cm)) {
                return trim($cm[0]);
            }
        }

        return null;
    }

    /**
     * Prüft schnell, ob eine Logdatei mindestens eine echte Fehlerzeile enthält.
     */
    public static function fileHasErrorLines(string $logFile): bool
    {
        if (!is_file($logFile) || !is_readable($logFile)) {
            return false;
        }

        clearstatcache(true, $logFile);
        $size = @filesize($logFile) ?: 0;
        if ($size <= 0) {
            return false;
        }

        $fpHandle = @fopen($logFile, 'rb');
        if (!$fpHandle) {
            return false;
        }
        if ($size > 262144) {
            @fseek($fpHandle, -262144, SEEK_END);
        }
        $chunk = (string) @fread($fpHandle, 262144);
        @fclose($fpHandle);

        if ($chunk === '') {
            return false;
        }

        $baseName = basename($logFile);
        foreach (explode("\n", $chunk) as $line) {
            if (self::extractLogErrorLine($baseName, $line) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft aktive Haupt- und Cron-Logdateien (storage/logs/*.log inkl. cron-*.log, exkl. archive/) auf unstrukturierte
     * ERROR/CRITICAL-Zeilen, die nicht über SystemErrorLogger liefen, und überführt sie dedupliziert in system_logs.
     */
    public static function ingestActiveLogFilesIntoSentinel(): int
    {
        if (!Schema::hasTable('system_logs')) {
            return 0;
        }

        $ingested = 0;
        foreach (self::listActiveLogFiles() as $logFile) {
            if (!is_readable($logFile)) {
                continue;
            }

            clearstatcache(true, $logFile);
            $size = @filesize($logFile) ?: 0;
            if ($size <= 0) {
                continue;
            }

            // Lese max. die letzten 256 KB für schnelles Parsing
            $fpHandle = @fopen($logFile, 'rb');
            if (!$fpHandle) {
                continue;
            }
            if ($size > 262144) {
                @fseek($fpHandle, -262144, SEEK_END);
            }
            $chunk = (string) @fread($fpHandle, 262144);
            @fclose($fpHandle);

            if ($chunk === '') {
                continue;
            }

            $baseName = basename($logFile);
            $lines = explode("\n", $chunk);

            foreach ($lines as $line) {
                $matchedMsg = self::extractLogErrorLine($baseName, $line);
                if ($matchedMsg === null || $matchedMsg === '') {
                    continue;
                }

                // Endlosschleifen verhindern: Ignoriere reine Test-/Artisan-Infozeilen
                $cleanMsg = SystemErrorLogger::sanitizeSensitiveText(Str::limit($matchedMsg, 1500, '...'));
                $normForFp = preg_replace('/\b\d+\b/', '#', strtolower(mb_substr($cleanMsg, 0, 180)));
                $fingerprint = hash('sha256', 'raw_logfile|' . strtolower($baseName) . '|' . $normForFp);

                $exists = SystemLog::where('payload->fingerprint', $fingerprint)
                    ->where('started_at', '>=', now()->subHours(24))
                    ->exists();

                if ($exists) {
                    continue;
                }

                SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'logfile:' . $baseName . ':' . substr($fingerprint, 0, 12),
                    'title' => Str::limit("[{$baseName}] " . trim(Str::before($cleanMsg, '{')), 180),
                    'message' => $cleanMsg,
                    'status' => 'error',
                    'payload' => [
                        'platform' => 'portal',
                        'platform_label' => 'Webportal',
                        'fingerprint' => $fingerprint,
                        'source_logfile' => 'storage/logs/' . $baseName,
                        'occurrences_count' => 1,
                        'first_seen_at' => now()->toIso8601String(),
                        'last_seen_at' => now()->toIso8601String(),
                    ],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
                $ingested++;
            }
        }

        return $ingested;
    }

    /**
     * Alias für archiveAndZeroActiveLogs zur konsistenten Archiv-Rotation aller aktiven storage/logs/*.log Dateien.
     *
     * @return array<string, mixed>
     */
    public static function archiveAndTruncateActiveLogs(string $resolvedBy = 'Antigravity', int $retentionDays = 30): array
    {
        return self::archiveAndZeroActiveLogs($resolvedBy, $retentionDays);
    }

    /**
     * Verschiebt alle nicht-leeren aktiven Logdateien (storage/logs/*.log inkl. cron-*.log, exkl. archive/)
     * sowie erledigte Handoff-Dateien geheimnisbereinigt in storage/logs/archive/YYYY-MM/ und leert die aktiven
     * Logdateien exakt auf 0 Bytes (Zero-Log-Baseline). Bereinigt zudem Archivdateien älter als $retentionDays.
     *
     * @return array<string, mixed>
     */
    public static function archiveAndZeroActiveLogs(string $resolvedBy = 'Antigravity', int $retentionDays = 30): array
    {
        $logsDir = storage_path('logs');
        File::ensureDirectoryExists($logsDir);

        $monthFolder = now()->format('Y-m');
        $stamp = now()->format('Y-m-d_His');
        $archiveDir = storage_path(self::ARCHIVE_ROOT_REL . '/' . $monthFolder);
        $dirMode = app()->environment('local', 'testing') ? 0775 : 0700;
        $fileMode = app()->environment('local', 'testing') ? 0666 : 0600;

        $archivedFiles = 0;
        $archivedBytes = 0;

        foreach (self::listActiveLogFiles() as $activeLogPath) {
            clearstatcache(true, $activeLogPath);
            $size = @filesize($activeLogPath) ?: 0;
            if ($size <= 0) {
                continue;
            }

            // Max. 10 MB am Stück lesen, um RAM zu schonen
            $raw = '';
            $fh = @fopen($activeLogPath, 'rb');
            if ($fh) {
                if ($size > 10 * 1024 * 1024) {
                    @fseek($fh, -10 * 1024 * 1024, SEEK_END);
                }
                $raw = (string) @fread($fh, 10 * 1024 * 1024);
                @fclose($fh);
            }

            $baseName = basename($activeLogPath);

            // Leere/reine Whitespace-Cronlogs direkt auf 0 Bytes setzen ohne leere Archivdatei anzulegen
            if (str_starts_with($baseName, 'cron-') && trim($raw) === '') {
                @file_put_contents($activeLogPath, '');
                @chmod($activeLogPath, $fileMode);
                continue;
            }

            File::ensureDirectoryExists($archiveDir, $dirMode, true);

            $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName) ?: 'app.log';
            $archiveTarget = $archiveDir . "/healed_{$stamp}_{$safeBase}";
            if (is_file($archiveTarget)) {
                $archiveTarget = $archiveDir . "/healed_{$stamp}_" . Str::random(4) . "_{$safeBase}";
            }

            $header = sprintf(
                "# Seelenfunke Log-Archiv | Archiviert: %s | Abgeschlossen durch: %s | Quelle: storage/logs/%s (%d Bytes)\n"
                . "# ====================================================================================================\n",
                now()->toIso8601String(),
                $resolvedBy,
                $baseName,
                $size
            );

            $scrubbedBody = SystemErrorLogger::sanitizeSensitiveText($raw);
            @file_put_contents($archiveTarget, $header . $scrubbedBody . "\n", LOCK_EX);
            @chmod($archiveTarget, $fileMode);

            // Aktive Logdatei (inkl. cron-*.log) exakt auf 0 Bytes zurücksetzen
            @file_put_contents($activeLogPath, '');
            @chmod($activeLogPath, $fileMode);

            $archivedFiles++;
            $archivedBytes += $size;
        }

        // Falls keine offenen SystemLogs mehr existieren, auch eine evtl. vorhandene Handoff-Datei ins Archiv legen
        $openCount = Schema::hasTable('system_logs') ? SystemLog::openIssues()->count() : 0;
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);
        if ($openCount === 0 && is_file($handoffPath)) {
            $hoSize = @filesize($handoffPath) ?: 0;
            if ($hoSize > 0) {
                File::ensureDirectoryExists($archiveDir, $dirMode, true);
                $hoRaw = (string) @file_get_contents($handoffPath);
                $hoArchive = $archiveDir . "/handoff_{$stamp}.json";
                @file_put_contents($hoArchive, SystemErrorLogger::sanitizeSensitiveText($hoRaw), LOCK_EX);
                @chmod($hoArchive, $fileMode);
                $archivedFiles++;
                $archivedBytes += $hoSize;
            }
            @unlink($handoffPath);
        }

        $prunedOld = self::pruneExpiredArchives($retentionDays);

        $remainingBytes = 0;
        foreach (glob($logsDir . '/*.log') ?: [] as $checkPath) {
            if (is_file($checkPath)) {
                clearstatcache(true, $checkPath);
                $remainingBytes += (@filesize($checkPath) ?: 0);
            }
        }

        return [
            'archived_files_count' => $archivedFiles,
            'archived_bytes' => $archivedBytes,
            'archive_dir' => 'storage/' . self::ARCHIVE_ROOT_REL . '/' . $monthFolder,
            'pruned_old_archives' => $prunedOld,
            'active_log_bytes_remaining' => $remainingBytes,
        ];
    }

    /**
     * Löscht Archivdateien in storage/logs/archive/, die älter als $retentionDays sind.
     */
    public static function pruneExpiredArchives(int $retentionDays = 30): int
    {
        $root = storage_path(self::ARCHIVE_ROOT_REL);
        if (!is_dir($root)) {
            return 0;
        }

        $cutoffTs = now()->subDays(max(1, $retentionDays))->getTimestamp();
        $deleted = 0;

        foreach (File::allFiles($root) as $fileInfo) {
            if ($fileInfo->getMTime() < $cutoffTs) {
                if (@unlink($fileInfo->getPathname())) {
                    $deleted++;
                }
            }
        }

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
            $entries = @scandir($subDir);
            if (is_array($entries) && count($entries) <= 2) {
                @rmdir($subDir);
            }
        }

        return $deleted;
    }
}

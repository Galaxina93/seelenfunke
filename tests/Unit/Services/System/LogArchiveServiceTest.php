<?php

namespace Tests\Unit\Services\System;

use App\Models\System\SystemLog;
use App\Services\System\LogArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogArchiveServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ingests_raw_log_errors_archives_with_secret_scrubbing_and_zeros_active_logs(): void
    {
        $testLogPath = storage_path('logs/phpunit_zero_baseline_test.log');
        File::ensureDirectoryExists(dirname($testLogPath));

        file_put_contents(
            $testLogPath,
            "[2026-11-16 02:00:00] local.ERROR: Testfehler mit Key AIzaSyDummySecretKey1234567890 und ya29.DummyVertexToken1234567890\n"
        );

        try {
            $ingested = LogArchiveService::ingestActiveLogFilesIntoSentinel();
            $this->assertGreaterThanOrEqual(1, $ingested);

            $created = SystemLog::where('action_id', 'like', 'logfile:phpunit_zero_baseline_test.log:%')->first();
            $this->assertNotNull($created);
            $this->assertStringNotContainsString('AIzaSyDummySecretKey1234567890', (string) $created->message);
            $this->assertStringNotContainsString('DummyVertexToken1234567890', (string) $created->message);

            // Resolve the DB issue and archive + zero out active logs
            $created->update(['status' => 'resolved', 'finished_at' => now()]);

            $stats = LogArchiveService::archiveAndZeroActiveLogs('PHPUnit Test', 30);

            $this->assertGreaterThanOrEqual(1, $stats['archived_files_count']);
            $this->assertSame(0, $stats['active_log_bytes_remaining']);
            clearstatcache(true, $testLogPath);
            $this->assertSame(0, filesize($testLogPath));

            // Verify archived file exists and contains scrubbed content
            $archiveDir = base_path($stats['archive_dir']);
            $matches = glob($archiveDir . '/healed_*_phpunit_zero_baseline_test.log') ?: [];
            $this->assertNotEmpty($matches);

            $archivedContent = (string) file_get_contents($matches[0]);
            $this->assertStringContainsString('Seelenfunke Log-Archiv', $archivedContent);
            $this->assertStringNotContainsString('AIzaSyDummySecretKey1234567890', $archivedContent);
            $this->assertStringNotContainsString('DummyVertexToken1234567890', $archivedContent);

            // Verify >30d expiration pruning
            $expiredFile = $archiveDir . '/healed_2025-01-01_000000_expired_test.log';
            file_put_contents($expiredFile, 'expired');
            touch($expiredFile, now()->subDays(40)->getTimestamp());

            $pruned = LogArchiveService::pruneExpiredArchives(30);
            $this->assertGreaterThanOrEqual(1, $pruned);
            $this->assertFileDoesNotExist($expiredFile);

            foreach ($matches as $m) {
                @unlink($m);
            }
        } finally {
            @unlink($testLogPath);
        }
    }

    public function test_it_handles_stage_cron_logs_ingests_only_real_errors_excludes_archive_dir_and_truncates_to_zero_bytes(): void
    {
        foreach (LogArchiveService::listActiveLogFiles() as $existingLog) {
            @file_put_contents($existingLog, '');
        }
        $handoffPath = storage_path(\App\Services\System\SentinelTriageService::HANDOFF_FILE);
        $backupHandoff = is_file($handoffPath) ? (string) file_get_contents($handoffPath) : null;
        @unlink($handoffPath);
        foreach (glob(storage_path('logs/archive/*/healed_*_cron-phpunit_*.log')) ?: [] as $staleCronArch) {
            @unlink($staleCronArch);
        }
        $routineCronLog = storage_path('logs/cron-phpunit_routine_ok.log');
        $fatalCronLog = storage_path('logs/cron-phpunit_fatal_err.log');
        $emptyCronLog = storage_path('logs/cron-phpunit_whitespace.log');
        $alreadyArchivedLog = storage_path('logs/archive/2026-11/healed_2026-11-16_000000_old.log');

        File::ensureDirectoryExists(dirname($routineCronLog));
        File::ensureDirectoryExists(dirname($alreadyArchivedLog));

        file_put_contents($routineCronLog, "Sentinel-Triage abgeschlossen: Spool=0 | AutoGeheilt=0 | GesamtPool=0\n");
        file_put_contents($fatalCronLog, "[ERROR] Uncaught RuntimeException: Stage-Cron Fehler mit token=TopSecretCron999\n");
        file_put_contents($emptyCronLog, "   \n\t\n");
        file_put_contents($alreadyArchivedLog, "[2026-11-16 01:00:00] production.ERROR: Bereits archiviert darf nicht erneut importiert werden\n");

        try {
            $this->assertFalse(LogArchiveService::fileHasErrorLines($routineCronLog));
            $this->assertTrue(LogArchiveService::fileHasErrorLines($fatalCronLog));
            $this->assertFalse(LogArchiveService::fileHasErrorLines($emptyCronLog));

            $ingested = LogArchiveService::ingestActiveLogFilesIntoSentinel();
            $this->assertSame(1, $ingested);

            $fatalEntry = SystemLog::where('action_id', 'like', 'logfile:cron-phpunit_fatal_err.log:%')->first();
            $this->assertNotNull($fatalEntry);
            $this->assertStringNotContainsString('TopSecretCron999', (string) $fatalEntry->message);
            $this->assertNull(SystemLog::where('action_id', 'like', 'logfile:cron-phpunit_routine_ok.log:%')->first());
            $this->assertNull(SystemLog::where('action_id', 'like', 'logfile:healed_2026-11-16_000000_old.log:%')->first());

            $fatalEntry->update(['status' => 'resolved', 'finished_at' => now()]);

            $stats = LogArchiveService::archiveAndTruncateActiveLogs('PHPUnit Stage Cron Test', 30);

            $this->assertSame(2, $stats['archived_files_count']);
            $this->assertSame(0, $stats['active_log_bytes_remaining']);

            clearstatcache(true, $routineCronLog);
            clearstatcache(true, $fatalCronLog);
            clearstatcache(true, $emptyCronLog);
            $this->assertSame(0, filesize($routineCronLog));
            $this->assertSame(0, filesize($fatalCronLog));
            $this->assertSame(0, filesize($emptyCronLog));

            $archiveDir = base_path($stats['archive_dir']);
            $cronArchives = array_merge(
                glob($archiveDir . '/healed_*_cron-phpunit_routine_ok.log') ?: [],
                glob($archiveDir . '/healed_*_cron-phpunit_fatal_err.log') ?: []
            );
            $this->assertCount(2, $cronArchives);

            foreach ($cronArchives as $archFile) {
                $this->assertStringNotContainsString('TopSecretCron999', (string) file_get_contents($archFile));
                @unlink($archFile);
            }
        } finally {
            @unlink($routineCronLog);
            @unlink($fatalCronLog);
            @unlink($emptyCronLog);
            @unlink($alreadyArchivedLog);
            if ($backupHandoff !== null) {
                @file_put_contents($handoffPath, $backupHandoff);
            }
        }
    }
}

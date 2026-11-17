<?php

namespace App\Console\Commands;

use App\Console\Commands\System\CleanupSystemLogsCommand;
use App\Models\System\SystemCronjob;
use App\Models\System\SystemLog;
use App\Services\System\LogArchiveService;
use App\Services\System\SentinelTriageService;
use App\Services\System\SystemHealthPulseService;
use Carbon\Carbon;
use Database\Seeders\SystemCronjobSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SystemSentinelAuditCommand extends Command
{
    protected $signature = 'system:sentinel-audit
                            {--json : Gibt das Prüfergebnis strukturiert als JSON aus}
                            {--no-remediate : Prüft ausschließlich ohne automatische Nachsteuerung}';

    protected $description = 'Überwacht regelmäßig die Funktionsfähigkeit der Funkira-Selbstheilung, aktive Rezidiv-Sperren, ungetriagierte Alt-Fehler sowie die Logdatei- und Dump-Hygiene.';

    public function handle(SentinelTriageService $sentinel): int
    {
        $now = now();
        $autoRemediate = !$this->option('no-remediate');

        if ($autoRemediate && Schema::hasTable('system_cronjobs') && class_exists(SystemCronjobSeeder::class)) {
            SystemCronjobSeeder::ensureDefaultCronjobsExist();
        }

        $lastTriageAt = $this->resolveLastTriageTimestamp($now);
        $lastTriageMinutesAgo = $lastTriageAt !== null
            ? max(0, (int) $lastTriageAt->diffInMinutes($now, false))
            : null;
        $triageOverdue = ($lastTriageMinutesAgo === null || $lastTriageMinutesAgo > 15);

        $untriagedStaleBefore = $this->countUntriagedStaleErrors($now);
        $triggeredTriageCatchup = false;

        if ($autoRemediate && ($triageOverdue || $untriagedStaleBefore > 0) && Schema::hasTable('system_logs')) {
            $sentinel->runTriageCycle(probeBridges: false, allowAiEnrichment: false);
            $triggeredTriageCatchup = true;
            $lastTriageAt = $this->resolveLastTriageTimestamp(now()) ?? now();
            $lastTriageMinutesAgo = 0;
            $triageOverdue = false;
        }

        $untriagedStaleAfter = $this->countUntriagedStaleErrors(now());
        $activeRelapses24h = $this->countActiveRelapsesLast24h(now());

        $laravelLogPath = storage_path('logs/laravel.log');
        clearstatcache(true, $laravelLogPath);
        $laravelLogBytesBefore = is_file($laravelLogPath) ? ((int) (@filesize($laravelLogPath) ?: 0)) : 0;

        $orphansDetectedBefore = $this->countOrphanedDumpAndLogFiles();
        $orphansRemoved = 0;
        $prunedLogs = false;
        $archivedHarmlessLogs = false;

        if ($autoRemediate) {
            if ($orphansDetectedBefore > 0 || $laravelLogBytesBefore > 2 * 1024 * 1024) {
                $pruneStats = CleanupSystemLogsCommand::executePrune(7, 30, false);
                $orphansRemoved = (int) ($pruneStats['orphaned_files_removed'] ?? 0);
                $prunedLogs = true;
            } else {
                $orphanRes = CleanupSystemLogsCommand::cleanupOrphanedLogAndDumpFiles();
                $orphansRemoved = (int) ($orphanRes['removed_files_count'] ?? 0);
            }

            if (
                !app()->runningUnitTests()
                && $laravelLogBytesBefore > 0
                && Schema::hasTable('system_logs')
                && SystemLog::openIssues()->count() === 0
                && is_file($laravelLogPath)
                && !LogArchiveService::fileHasErrorLines($laravelLogPath)
            ) {
                LogArchiveService::archiveAndZeroActiveLogs('Funkira Sentinel Audit', 30);
                $archivedHarmlessLogs = true;
            }
        }

        clearstatcache(true, $laravelLogPath);
        $laravelLogBytesAfter = is_file($laravelLogPath) ? ((int) (@filesize($laravelLogPath) ?: 0)) : 0;
        $orphansRemaining = $this->countOrphanedDumpAndLogFiles();

        $status = ($activeRelapses24h > 0 || $untriagedStaleAfter > 0 || $triageOverdue) ? 'warning' : 'ok';

        $summary = [
            'status' => $status,
            'healthy' => $status === 'ok',
            'checked_at' => now()->toIso8601String(),
            'triage_overdue' => $triageOverdue,
            'triage_catchup_triggered' => $triggeredTriageCatchup,
            'last_triage_at' => $lastTriageAt?->toIso8601String(),
            'last_triage_minutes_ago' => $lastTriageMinutesAgo,
            'active_relapses_24h_count' => $activeRelapses24h,
            'untriaged_stale_errors_before' => $untriagedStaleBefore,
            'untriaged_stale_errors_count' => $untriagedStaleAfter,
            'laravel_log_bytes_before' => $laravelLogBytesBefore,
            'laravel_log_bytes' => $laravelLogBytesAfter,
            'orphaned_files_detected' => $orphansDetectedBefore,
            'orphaned_files_removed' => $orphansRemoved,
            'orphaned_files_remaining' => $orphansRemaining,
            'pruned_logs' => $prunedLogs,
            'archived_harmless_logs' => $archivedHarmlessLogs,
        ];

        $this->persistAuditState($summary);

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '🛡️ Sentinel-Audit [%s]: Rezidive(24h)=%d | Ungetriagiert(>20m)=%d | TriageCatchup=%s | laravel.log=%d B | Verwaiste Dumps entfernt=%d',
            strtoupper($status),
            $activeRelapses24h,
            $untriagedStaleAfter,
            $triggeredTriageCatchup ? 'JA' : 'NEIN',
            $laravelLogBytesAfter,
            $orphansRemoved
        ));

        return self::SUCCESS;
    }

    protected function resolveLastTriageTimestamp(Carbon $now): ?Carbon
    {
        try {
            $cached = Cache::get('sentinel:last_triage_cycle_at');
            if (!empty($cached)) {
                return Carbon::parse((string) $cached);
            }
        } catch (\Throwable $ignore) {
        }

        try {
            $filePath = SystemHealthPulseService::resolveSentinelLastTriageFilePath();
            if (is_file($filePath) && is_readable($filePath)) {
                $decoded = json_decode((string) @file_get_contents($filePath), true);
                if (is_array($decoded) && !empty($decoded['ran_at'])) {
                    return Carbon::parse((string) $decoded['ran_at']);
                }
            }
        } catch (\Throwable $ignore) {
        }

        try {
            if (Schema::hasTable('system_cronjobs') && Schema::hasColumn('system_cronjobs', 'last_run_at')) {
                $cronLast = SystemCronjob::query()
                    ->where('command', 'system:sentinel-triage')
                    ->value('last_run_at');
                if (!empty($cronLast)) {
                    return Carbon::parse((string) $cronLast);
                }
            }
        } catch (\Throwable $ignore) {
        }

        return null;
    }

    protected function countUntriagedStaleErrors(Carbon $now): int
    {
        if (!Schema::hasTable('system_logs')) {
            return 0;
        }

        $cutoff = $now->copy()->subMinutes(20);

        return (int) SystemLog::openIssues()
            ->where(function ($q) use ($cutoff) {
                $q->where('started_at', '<=', $cutoff)
                    ->orWhere(function ($sq) use ($cutoff) {
                        $sq->whereNull('started_at')->where('created_at', '<=', $cutoff);
                    });
            })
            ->whereNull('payload->triage->state')
            ->count();
    }

    protected function countActiveRelapsesLast24h(Carbon $now): int
    {
        if (!Schema::hasTable('system_logs')) {
            return 0;
        }

        return (int) SystemLog::openIssues()
            ->where('started_at', '>=', $now->copy()->subHours(24))
            ->where('payload->triage->relapse_detected', true)
            ->count();
    }

    protected function countOrphanedDumpAndLogFiles(): int
    {
        $candidates = [
            public_path('scheduler_cli.log'),
            base_path('bridge.log'),
            base_path('twilio.log'),
            base_path('crash.log'),
            storage_path('logs/admin_test_error.txt'),
            storage_path('logs/twilio.log'),
        ];

        $allowDebugDump = (bool) config('app.debug', false) && (bool) env('AI_DEBUG_DUMP_PAYLOADS', false);
        if (!$allowDebugDump) {
            $candidates[] = storage_path('logs/gemini_payload_dump.json');
        }

        foreach (glob(storage_path('logs/cmd_*.log')) ?: [] as $cmdLog) {
            $candidates[] = $cmdLog;
        }

        $count = 0;
        foreach (array_unique($candidates) as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && !is_link($path)) {
                $count++;
            }
        }

        return $count;
    }

    protected function persistAuditState(array $summary): void
    {
        try {
            Cache::put('sentinel:last_audit_summary', $summary, now()->addMinutes(30));
        } catch (\Throwable $ignore) {
        }

        try {
            $path = SystemHealthPulseService::resolveSentinelAuditStateFilePath();
            File::ensureDirectoryExists(dirname($path));
            @file_put_contents(
                $path,
                json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
            if (!function_exists('posix_geteuid') || posix_geteuid() === 0 || @fileowner($path) === posix_geteuid()) {
                @chmod($path, app()->environment('local', 'testing') ? 0666 : 0664);
            }
        } catch (\Throwable $ignore) {
        }
    }
}

<?php

namespace App\Console\Commands\System;

use App\Models\System\SystemLog;
use App\Models\System\SystemSchedulerLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CleanupSystemLogsCommand extends Command
{
    protected $signature = 'system:logs-cleanup
                            {--audit : Zeigt eine detaillierte Aufschlüsselung aller offenen Fehler, Warnungen und Log-Quellen}
                            {--prune : Führt die 7-Tage-Archivbereinigung, Zombie-Bereinigung und Duplikat-Zusammenführung aus}
                            {--archive-open : Verschiebt bestehende offene Alt-Meldungen nach erfolgtem Code-Fix als [GELÖST] ins 7-Tage-Archiv}
                            {--days=7 : Aufbewahrungsdauer in Tagen für gelöste Meldungen und Routine-Logs}
                            {--audit-days=30 : Aufbewahrungsdauer in Tagen für DSGVO- und Sicherheits-Auditnachweise}';

    protected $description = 'Bereinigt das zentrale SystemLog automatisch (7 Tage für gelöste Logs, 30 Tage für DSGVO/Security-Nachweise) und dedupliziert offene Meldungen.';

    public function handle(): int
    {
        if (!Schema::hasTable('system_logs')) {
            $this->warn('Tabelle system_logs existiert noch nicht.');
            return self::SUCCESS;
        }

        $doAudit = (bool) $this->option('audit');
        $doPrune = (bool) $this->option('prune') || !$doAudit;
        $days = max(1, (int) $this->option('days'));
        $auditDays = max($days, (int) $this->option('audit-days'));

        if ($doAudit) {
            $this->renderAuditSnapshot('VOR BEREINIGUNG');
        }

        if ($doPrune) {
            $stats = self::executePrune($days, $auditDays, false);
            $this->info(sprintf(
                '🧹 Log-Selbstbereinigung abgeschlossen: %d Duplikate zusammengeführt | %d Transienten bereinigt | %d Zombies geschlossen | %d Archiv-Logs (>%dd) gelöscht | %d Altlasten (>%dd) gelöscht | %d Scheduler-Logs gelöscht.',
                $stats['deduplicated_open'],
                $stats['transient_deleted'],
                $stats['zombies_closed'],
                $stats['archived_deleted'],
                $days,
                $stats['expired_audit_deleted'],
                $auditDays,
                $stats['scheduler_deleted']
            ));
        }

        if ($this->option('archive-open')) {
            $resolvedCount = self::archiveAllOpenIssues();
            $this->info("✅ {$resolvedCount} behobene Alt-Meldungen wurden als [GELÖST] ins 7-Tage-Archiv verschoben.");
        }

        if ($doAudit) {
            $this->renderAuditSnapshot('AKTUELLER STAND (OFFENE FEHLER & WARNUNGEN)');
            $this->renderOpenIssuesDetail();
        }

        return self::SUCCESS;
    }

    /**
     * Gedrosselte Hintergrund-Selbstbereinigung (max. alle 6 Stunden),
     * damit sich das Log auch ohne manuelles Eingreifen kontinuierlich selbst aufräumt.
     */
    public static function pruneOpportunistically(): void
    {
        try {
            if (app()->runningUnitTests() || !Schema::hasTable('system_logs')) {
                return;
            }

            $cacheKey = 'system_logs_opportunistic_prune_v1';
            if (Cache::has($cacheKey)) {
                return;
            }

            Cache::put($cacheKey, now()->toIso8601String(), now()->addHours(6));
            self::executePrune(7, 30, false);
        } catch (\Throwable $ignore) {
        }
    }

    /**
     * Kern-Logik für Duplikat-Zusammenführung und gestaffelte Aufbewahrung (7 Tage Archiv / 30 Tage Compliance).
     *
     * @return array<string, int>
     */
    public static function executePrune(int $archiveDays = 7, int $auditDays = 30, bool $clearAllNonProtectedSolved = false): array
    {
        $stats = [
            'deduplicated_open' => 0,
            'transient_deleted' => 0,
            'zombies_closed' => 0,
            'archived_deleted' => 0,
            'expired_audit_deleted' => 0,
            'scheduler_deleted' => 0,
        ];

        if (!Schema::hasTable('system_logs')) {
            return $stats;
        }

        // 1. Bekannte harmlose Browser-/DOM-Swap-Artefakte entfernen
        $openFrontend = SystemLog::query()
            ->where('type', 'frontend_error')
            ->whereIn('status', ['error', 'warning'])
            ->get(['id', 'title', 'message']);

        $transientIds = [];
        foreach ($openFrontend as $fe) {
            if (SystemLog::isTransientFrontendNoise((string) $fe->message, (string) $fe->title)) {
                $transientIds[] = $fe->id;
            }
        }
        if (!empty($transientIds)) {
            $stats['transient_deleted'] = SystemLog::whereIn('id', $transientIds)->delete();
        }

        // 1b. Drittanbieter-/Vendor-PHP-Deprecations aus system_logs entfernen (kein App-Fehler)
        $vendorDeprecationIds = SystemLog::query()
            ->where('type', 'system')
            ->where('status', 'warning')
            ->where('message', 'like', '%deprecated%')
            ->where(function ($q) {
                $q->where('message', 'like', '%/vendor/%')
                    ->orWhere('message', 'like', '%\\vendor\\%');
            })
            ->pluck('id')
            ->all();
        if (!empty($vendorDeprecationIds)) {
            $stats['transient_deleted'] += SystemLog::whereIn('id', $vendorDeprecationIds)->delete();
        }

        // 2. Identische offene Fehler/Warnungen zusammenführen (1 Eintrag mit occurrences_count statt hunderter Duplikate)
        $openLogs = SystemLog::query()
            ->whereIn('status', ['error', 'warning'])
            ->orderByDesc('id')
            ->get();

        $keepersBySignature = [];
        $duplicateIdsToDelete = [];

        foreach ($openLogs as $log) {
            $payload = is_array($log->payload) ? $log->payload : (json_decode((string) $log->payload, true) ?: []);
            $platformKey = SystemLog::detectPlatformKey($log->type, $log->action_id, $payload);
            $normMsg = preg_replace('/\b\d+\b/', '#', strtolower(trim((string) $log->message)));
            $fileSig = (string) ($payload['file'] ?? $payload['source'] ?? '');
            $lineSig = (string) ($payload['line'] ?? $payload['lineno'] ?? '');

            $sig = hash('sha256', strtolower(
                $platformKey . '|'
                . (string) $log->type . '|'
                . (string) $log->action_id . '|'
                . trim((string) $log->title) . '|'
                . $fileSig . '|'
                . $lineSig . '|'
                . mb_substr((string) $normMsg, 0, 180)
            ));

            if (!isset($keepersBySignature[$sig])) {
                $needsSave = false;
                if (($payload['platform'] ?? null) !== $platformKey) {
                    $payload['platform'] = $platformKey;
                    $payload['platform_label'] = SystemLog::PLATFORMS[$platformKey]['label'] ?? 'Webportal';
                    $needsSave = true;
                }
                if (empty($payload['fingerprint'])) {
                    $payload['fingerprint'] = $sig;
                    $needsSave = true;
                }
                if ($needsSave) {
                    $log->payload = $payload;
                    $log->saveQuietly();
                }
                $keepersBySignature[$sig] = $log;
            } else {
                /** @var SystemLog $keeper */
                $keeper = $keepersBySignature[$sig];
                $kp = is_array($keeper->payload) ? $keeper->payload : [];
                $addCount = max(1, (int) ($payload['occurrences_count'] ?? 1));
                $kp['occurrences_count'] = max(1, (int) ($kp['occurrences_count'] ?? 1)) + $addCount;
                $kp['platform'] = $kp['platform'] ?? $platformKey;
                $kp['platform_label'] = $kp['platform_label'] ?? (SystemLog::PLATFORMS[$platformKey]['label'] ?? 'Webportal');
                if ($log->status === 'error' && $keeper->status === 'warning') {
                    $keeper->status = 'error';
                }
                $keeper->payload = $kp;
                $keeper->saveQuietly();
                $duplicateIdsToDelete[] = $log->id;
            }
        }

        if (!empty($duplicateIdsToDelete)) {
            foreach (array_chunk($duplicateIdsToDelete, 500) as $chunk) {
                $stats['deduplicated_open'] += SystemLog::whereIn('id', $chunk)->delete();
            }
        }

        // 3. Hängengebliebene 'running' Zombie-Einträge (> 6 Stunden) sauber abschließen
        $zombieCutoff = now()->subHours(6);
        $stats['zombies_closed'] = SystemLog::query()
            ->where('status', 'running')
            ->where(function ($q) use ($zombieCutoff) {
                $q->where('started_at', '<', $zombieCutoff)
                    ->orWhere(function ($sq) use ($zombieCutoff) {
                        $sq->whereNull('started_at')->where('created_at', '<', $zombieCutoff);
                    });
            })
            ->update([
                'status' => 'success',
                'finished_at' => now(),
            ]);

        // 4. Gelöste Meldungen & Routine-Erfolgslogs nach 7 Tagen löschen (DSGVO/Security-Nachweise bleiben 30 Tage geschützt)
        $archiveCutoff = now()->subDays($archiveDays);
        $archivedQuery = SystemLog::query()->whereNotIn('status', ['error', 'warning', 'running']);

        if (!$clearAllNonProtectedSolved) {
            $archivedQuery->where(function ($q) use ($archiveCutoff) {
                $q->where('finished_at', '<', $archiveCutoff)
                    ->orWhere(function ($sq) use ($archiveCutoff) {
                        $sq->whereNull('finished_at')->where('created_at', '<', $archiveCutoff);
                    });
            });
        }

        $idsToDelete = [];
        $archivedQuery->select(['id', 'type', 'action_id'])->chunkById(500, function ($rows) use (&$idsToDelete) {
            foreach ($rows as $row) {
                if (!$row->isAuditProtected()) {
                    $idsToDelete[] = $row->id;
                }
            }
        });

        if (!empty($idsToDelete)) {
            foreach (array_chunk($idsToDelete, 500) as $chunk) {
                $stats['archived_deleted'] += SystemLog::whereIn('id', $chunk)->delete();
            }
        }

        // 5. Maximale Compliance-Aufbewahrung (30 Tage gemäß ModelBlueprint ShortLived_30Days) für alle Alt-Einträge
        $auditCutoff = now()->subDays($auditDays);
        $stats['expired_audit_deleted'] = SystemLog::query()
            ->where(function ($q) use ($auditCutoff) {
                $q->where('finished_at', '<', $auditCutoff)
                    ->orWhere(function ($sq) use ($auditCutoff) {
                        $sq->whereNull('finished_at')->where('created_at', '<', $auditCutoff);
                    });
            })
            ->delete();

        // 6. Minütliche Scheduler-Ausführungsprotokolle (system_scheduler_logs) kompakt auf 7 Tage halten
        if (Schema::hasTable('system_scheduler_logs')) {
            $stats['scheduler_deleted'] = SystemSchedulerLog::query()
                ->where('created_at', '<', now()->subDays($archiveDays))
                ->delete();
        }

        return $stats;
    }

    /**
     * Verschiebt alle aktuell offenen Fehler/Warnungen nach erfolgtem Bugfix sauber als [GELÖST] ins 7-Tage-Archiv.
     */
    public static function archiveAllOpenIssues(): int
    {
        if (!Schema::hasTable('system_logs')) {
            return 0;
        }

        $count = 0;
        SystemLog::openIssues()->chunkById(200, function ($logs) use (&$count) {
            foreach ($logs as $log) {
                $title = (string) $log->title;
                if (!str_starts_with($title, '[GELÖST]')) {
                    $title = '[GELÖST] ' . $title;
                }
                $payload = is_array($log->payload) ? $log->payload : (json_decode((string) $log->payload, true) ?: []);
                $payload['resolved_at'] = now()->toIso8601String();

                $log->status = 'success';
                $log->title = mb_substr($title, 0, 250);
                $log->finished_at = $log->finished_at ?? now();
                $log->payload = $payload;
                $log->saveQuietly();
                $count++;
            }
        });

        return $count;
    }

    protected function renderAuditSnapshot(string $heading): void
    {
        $total = SystemLog::count();
        $openErrors = SystemLog::where('status', 'error')->count();
        $openWarnings = SystemLog::where('status', 'warning')->count();
        $archived = SystemLog::archivedEntries()->count();

        $this->newLine();
        $this->info("📊 SystemLog Diagnose [{$heading}]");
        $this->line(sprintf(
            '   Gesamt: %d | Offene Fehler: %d | Offene Warnungen: %d | Archiv/Gelöst: %d',
            $total,
            $openErrors,
            $openWarnings,
            $archived
        ));

        $platformRows = [];
        foreach (SystemLog::PLATFORMS as $key => $meta) {
            $platformRows[] = [
                $meta['label'],
                SystemLog::openIssues()->forPlatform($key)->where('status', 'error')->count(),
                SystemLog::openIssues()->forPlatform($key)->where('status', 'warning')->count(),
                SystemLog::archivedEntries()->forPlatform($key)->count(),
            ];
        }
        $this->table(['Bereich', 'Offene Fehler', 'Offene Warnungen', 'Archiv'], $platformRows);

        $topGroups = SystemLog::query()
            ->selectRaw('action_id, type, status, COUNT(*) as cnt')
            ->groupBy('action_id', 'type', 'status')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get();

        if ($topGroups->isNotEmpty()) {
            $this->line('   Top-Volumen nach Aktion & Status:');
            foreach ($topGroups as $g) {
                $this->line(sprintf('   - %5dx | %-8s | %-16s | %s', $g->cnt, $g->status, $g->type ?? '-', $g->action_id ?? '-'));
            }
        }
    }

    protected function renderOpenIssuesDetail(): void
    {
        $open = SystemLog::openIssues()->orderByDesc('id')->limit(120)->get();
        if ($open->isEmpty()) {
            $this->info('✅ 0 offene Fehler oder Warnungen vorhanden (100 % GRÜN).');
            return;
        }

        $this->warn("Offene Meldungen im Detail ({$open->count()} Einträge):");
        foreach ($open as $row) {
            $p = is_array($row->payload) ? $row->payload : [];
            $plat = $row->resolvePlatformMeta()['short'];
            $occ = max(1, (int) ($p['occurrences_count'] ?? 1));
            $loc = trim(($p['file'] ?? $p['source'] ?? '') . (isset($p['line']) ? ':' . $p['line'] : ''));
            $url = (string) ($p['url'] ?? ($p['execution_context']['url'] ?? ($p['execution_context']['command'] ?? '')));
            $this->line(sprintf(
                '  [#%d] [%s] %s (%dx) | %s | %s',
                $row->id,
                $plat,
                strtoupper((string) $row->status),
                $occ,
                (string) $row->action_id,
                (string) $row->title
            ));
            $this->line('      MSG: ' . mb_substr(str_replace(["\r", "\n"], ' ', (string) $row->message), 0, 240));
            if ($loc !== '' || $url !== '') {
                $this->line("      LOC: {$loc} | CTX: {$url}");
            }
        }
    }
}

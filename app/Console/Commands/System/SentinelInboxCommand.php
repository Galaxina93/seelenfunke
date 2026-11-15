<?php

namespace App\Console\Commands\System;

use App\Services\System\SentinelTriageService;
use Illuminate\Console\Command;

class SentinelInboxCommand extends Command
{
    protected $signature = 'system:sentinel-inbox
        {--export-json : Gibt den aktuellen Antigravity-Auffang-Pool als reines JSON auf STDOUT aus}
        {--import-file= : Synchronisiert eine heruntergeladene Stage-Inbox-JSON in die lokale Datenbank}
        {--resolve-fingerprints= : Kommagetrennte Fingerprints als geloest archivieren}
        {--resolve-ids= : Kommagetrennte SystemLog-IDs als geloest archivieren}
        {--resolve-all : Alle offenen Eintraege im Auffang-Pool als geloest archivieren}
        {--resolved-by=Antigravity : Name des loesenden Agenten oder Admins}';

    protected $description = 'Verwaltet, exportiert und synchronisiert den 24/7 Antigravity-Auffang-Pool (Stage <-> Lokal)';

    public function handle(SentinelTriageService $sentinel): int
    {
        $resolvedBy = trim((string) $this->option('resolved-by')) ?: 'Antigravity';

        $resolveAll = (bool) $this->option('resolve-all');
        $fpRaw = trim((string) $this->option('resolve-fingerprints'));
        $idsRaw = trim((string) $this->option('resolve-ids'));

        if ($resolveAll || $fpRaw !== '' || $idsRaw !== '') {
            $fps = $fpRaw !== '' ? array_filter(array_map('trim', explode(',', $fpRaw))) : [];
            $ids = $idsRaw !== '' ? array_filter(array_map('intval', explode(',', $idsRaw))) : [];

            $count = $sentinel->resolvePoolItems(
                fingerprints: $fps,
                logIds: $ids,
                resolveAll: $resolveAll,
                resolvedBy: $resolvedBy
            );

            $prunedCount = 0;
            if ($resolveAll) {
                $pruneStats = CleanupSystemLogsCommand::executePrune(7, 30, true);
                $prunedCount = (int) ($pruneStats['archived_deleted'] ?? 0) + (int) ($pruneStats['transient_deleted'] ?? 0);

                if (!app()->runningUnitTests()) {
                    $laravelLog = storage_path('logs/laravel.log');
                    if (is_file($laravelLog) && is_writable($laravelLog)) {
                        @file_put_contents($laravelLog, '');
                    }
                }
            }

            $this->line((string) json_encode([
                'status' => 'success',
                'resolved_count' => $count,
                'pruned_archive_count' => $prunedCount,
                'resolved_by' => $resolvedBy,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $importFile = trim((string) $this->option('import-file'));
        if ($importFile !== '') {
            if (str_contains($importFile, '..')) {
                $this->error('Ungueltiger Dateipfad.');
                return self::FAILURE;
            }

            $candidatePath = is_file($importFile) ? $importFile : base_path(ltrim($importFile, '/\\'));
            if (!is_file($candidatePath) || !is_readable($candidatePath)) {
                $this->error('Import-Datei nicht gefunden: ' . $importFile);
                return self::FAILURE;
            }

            $decoded = json_decode((string) file_get_contents($candidatePath), true);
            if (!is_array($decoded)) {
                $this->error('Import-Datei enthaelt kein valides JSON.');
                return self::FAILURE;
            }

            $result = $sentinel->importRemoteStageInbox($decoded);
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $snapshot = $sentinel->exportInboxSnapshot(runFastTriageFirst: true);

        if ($this->option('export-json')) {
            $this->line((string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Antigravity-Auffang-Pool (%s): %d offene Dossiers | %d Auto-Heilungen (7 Tage) | Datei: storage/%s',
            (string) ($snapshot['source_environment'] ?? 'local'),
            (int) ($snapshot['open_pool_count'] ?? 0),
            (int) ($snapshot['auto_healed_last_7d_count'] ?? 0),
            SentinelTriageService::INBOX_FILE
        ));

        foreach (($snapshot['items'] ?? []) as $item) {
            $this->line(sprintf(
                ' - [#%s | %s | %dx] %s (%s:%s)',
                (string) ($item['log_id'] ?? '?'),
                strtoupper((string) ($item['criticality'] ?? 'standard')),
                (int) ($item['occurrences_count'] ?? 1),
                (string) ($item['title'] ?? ''),
                (string) ($item['culprit_file'] ?? 'System'),
                (string) ($item['line'] ?? '-')
            ));
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands\System;

use App\Services\System\SystemHealthPulseService;
use Illuminate\Console\Command;

class SystemHealthPulseCommand extends Command
{
    protected $signature = 'system:health-pulse
                            {--strict : Erzwingt Produktions-Schwellenwerte auch lokal}
                            {--sync-logs : Synchronisiert kritische Säulen & Selbstheilungen in SystemLog}
                            {--json : Gibt das vollständige JSON-Ergebnis aus}';

    protected $description = 'Prüft die 4 Watchtower-Säulen (Shop & SSL, Cronjobs, Queue-Worker, Backups) und aktualisiert die 90-Tage-Uptime.';

    public function handle(SystemHealthPulseService $pulseService): int
    {
        $report = $pulseService->evaluate(
            source: 'cli_scheduler',
            strict: (bool) $this->option('strict'),
            recordSample: true,
            syncLogs: (bool) $this->option('sync-logs')
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $report['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $badge = match ($report['status']) {
            'critical' => 'KRITISCH',
            'warning' => 'WARNUNG',
            default => 'OK',
        };

        $this->info("Watchtower Health-Puls [{$badge}] | 90-Tage Uptime: {$report['uptime_90d_percent']}% | {$report['latency_ms']} ms");
        foreach ($report['pillars'] as $key => $pillar) {
            $st = strtoupper((string) ($pillar['status'] ?? 'ok'));
            $lbl = (string) ($pillar['label'] ?? '');
            $this->line(sprintf('  - %-10s [%s] %s', $key, $st, $lbl));
        }

        return $report['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}

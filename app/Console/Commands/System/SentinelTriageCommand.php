<?php

namespace App\Console\Commands\System;

use App\Services\System\SentinelTriageService;
use Illuminate\Console\Command;

class SentinelTriageCommand extends Command
{
    protected $signature = 'system:sentinel-triage
        {--probe-bridges : Prüft aktiv die Echtzeit-Brücken (Reverb + Node.js Live-Bridge) per IPv4-Puls}
        {--no-ai : Überspringt die optionale Gemini-2.5-Flash-Anreicherung}
        {--json : Gibt das Ergebnis als reines JSON aus}';

    protected $description = '24/7 Funkira-Selbstheilungs-Wächter (Stufe 1 Auto-Heal + Stufe 2 Antigravity-Auffang-Pool)';

    public function handle(SentinelTriageService $sentinel): int
    {
        $probeBridges = (bool) $this->option('probe-bridges');
        $allowAi = !(bool) $this->option('no-ai');

        $result = $sentinel->runTriageCycle(
            probeBridges: $probeBridges,
            allowAiEnrichment: $allowAi
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sentinel-Triage abgeschlossen: Spool=%d | QueueJobs=%d | Bruecken=%d | AutoGeheilt=%d | NeuImPool=%d | GesamtPool=%d',
            (int) ($result['drained_deadletter'] ?? 0),
            (int) ($result['failed_jobs_imported'] ?? 0),
            (int) ($result['probed_bridges'] ?? 0),
            (int) ($result['auto_healed_count'] ?? 0),
            (int) ($result['newly_queued_count'] ?? 0),
            (int) ($result['open_pool_count'] ?? 0)
        ));

        return self::SUCCESS;
    }
}

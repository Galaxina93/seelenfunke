<?php

namespace App\Services\System;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\Ai\AiWorkspaceTask;
use App\Services\AI\FunkiraPresenceContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ZeroTokenSelfHealer
{
    protected static bool $isHandling = false;

    /**
     * Kategorien, die rein informativ oder bereits automatisch abgewehrt sind (niemals Ticket-Spam!).
     */
    protected const IGNORED_CATEGORIES = [
        'security_auto_firewall_defense',
        'cli_stdin_eval_error',
        'reverb_port_already_bound',
        'backup_overdue_warning',
    ];

    /**
     * Berechnet den deterministischen 0-Token Fingerabdruck eines Fehlers.
     */
    public static function computeFingerprint(
        string $message,
        ?string $exceptionClass = null,
        ?string $file = null,
        ?int $line = null,
        string $category = 'application_error'
    ): string {
        $normalizedFile = strtolower(str_replace('\\', '/', trim((string) $file)));
        $base = strtolower(str_replace('\\', '/', base_path()));
        if ($base !== '' && str_starts_with($normalizedFile, $base)) {
            $normalizedFile = ltrim(substr($normalizedFile, strlen($base)), '/');
        }

        if ($normalizedFile !== '') {
            $seed = strtolower((string) $exceptionClass) . '|' . $normalizedFile . '|' . ((int) $line) . '|' . strtolower($category);
        } else {
            // Normalisiere variable Zahlen/UUIDs aus reinen Meldungen, damit gleiche Fehler denselben Fingerprint erhalten
            $stableMsg = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<uuid>', $message) ?? $message;
            $stableMsg = preg_replace('/\b\d+\b/', '<n>', $stableMsg) ?? $stableMsg;
            $seed = strtolower((string) $exceptionClass) . '|' . strtolower($category) . '|' . mb_substr(strtolower(trim($stableMsg)), 0, 180);
        }

        return sha1($seed);
    }

    /**
     * 3-Stufen 0-Token Selbstheilung & Fehler-Bündelung:
     * - Stufe 1: Deterministische PHP-Selbstreparatur für bekannte Infrastruktur-Hänger (0 Tokens).
     * - Stufe 2: SHA1-Fingerprinting bündelt identische Fehler in genau 1 Systemi-Ticket (0 Tokens).
     * - Stufe 3: STRIKTES TOTALVERBOT automatischer KI- oder Subagenten-Starts!
     *
     * @return array{action: string, fingerprint: string, ticket_id: ?string, occurrence_count: int, auto_repaired: bool}
     */
    public static function handleSystemError(
        string $message,
        ?string $exceptionClass = null,
        ?string $file = null,
        ?int $line = null,
        ?array $diagnosis = null
    ): array {
        $diag = $diagnosis ?? SystemErrorLogger::diagnose($message, $exceptionClass, $file);
        $category = (string) ($diag['category'] ?? 'application_error');
        $fingerprint = self::computeFingerprint($message, $exceptionClass, $file, $line, $category);

        if (self::$isHandling || in_array($category, self::IGNORED_CATEGORIES, true)) {
            return [
                'action' => 'ignored',
                'fingerprint' => $fingerprint,
                'ticket_id' => null,
                'occurrence_count' => 0,
                'auto_repaired' => false,
            ];
        }

        self::$isHandling = true;
        try {
            // Stufe 1: Deterministische 0-Token Selbstreparatur (mit 30-Minuten-Cooldown)
            $autoRepaired = self::attemptZeroTokenPhpRepair($message, $category);

            if (!Schema::hasTable('ai_universe_tickets')) {
                return [
                    'action' => $autoRepaired ? 'repaired_no_table' : 'skipped_no_table',
                    'fingerprint' => $fingerprint,
                    'ticket_id' => null,
                    'occurrence_count' => 0,
                    'auto_repaired' => $autoRepaired,
                ];
            }

            // Stufe 2: 0-Token Deduplizierung über Fingerabdruck
            $existing = AiUniverseTicket::query()
                ->where('error_fingerprint', $fingerprint)
                ->whereNotIn('status', [AiUniverseTicket::STATUS_COMPLETED, AiUniverseTicket::STATUS_ARCHIVED])
                ->first();

            if ($existing) {
                $newCount = ((int) $existing->occurrence_count) + 1;
                $existingPayload = is_array($existing->action_payload) ? $existing->action_payload : [];
                $existingPayload['occurrences_count'] = $newCount;
                $existingPayload['last_seen_at'] = now()->toIso8601String();
                $existing->update([
                    'occurrence_count' => $newCount,
                    'action_payload' => $existingPayload,
                    'last_activity_at' => now(),
                ]);

                return [
                    'action' => 'deduplicated_increment',
                    'fingerprint' => $fingerprint,
                    'ticket_id' => $existing->id,
                    'occurrence_count' => $newCount,
                    'auto_repaired' => $autoRepaired,
                ];
            }

            $systemDeptId = AiDepartment::where('name', 'System')->value('id');
            $systemiAgent = AiAgent::where('name', 'Systemi')->first();
            $envTier = FunkiraPresenceContext::resolveEnvironmentTier();
            $notebookOnline = FunkiraPresenceContext::isAntigravityNotebookOnline();

            $shortFile = $file ? basename(str_replace('\\', '/', $file)) . ($line ? ":{$line}" : '') : $category;
            $cleanTitle = 'System-Fehler: ' . Str::limit($shortFile . ' (' . trim(preg_replace('/\s+/', ' ', $message) ?? $message) . ')', 140);

            $initialStatus = $autoRepaired
                ? AiUniverseTicket::STATUS_COMPLETED
                : ($notebookOnline ? AiUniverseTicket::STATUS_IN_PROGRESS : AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK);

            $ticket = AiUniverseTicket::create([
                'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
                'title' => $cleanTitle,
                'status' => $initialStatus,
                'priority' => $category === 'php_fatal_or_oom' ? 'high' : 'normal',
                'ticket_type' => 'system_error',
                'ai_department_id' => $systemDeptId,
                'assigned_agent_id' => $systemiAgent?->id,
                'assigned_to_antigravity' => !$autoRepaired,
                'source_channel' => 'system_sentinel',
                'environment_tier' => $envTier,
                'error_fingerprint' => $fingerprint,
                'occurrence_count' => 1,
                'messages_count' => 1,
                'action_payload' => [
                    'type' => $autoRepaired ? 'antigravity_handoff' : 'antigravity_active',
                    'category' => $category,
                    'file' => $file,
                    'line' => $line,
                    'exception_class' => $exceptionClass,
                    'auto_repaired' => $autoRepaired,
                    'ag_channel' => 'tickets',
                    'queue_state' => $autoRepaired ? 'completed' : 'queued',
                    'auto_dispatch_attempts' => 0,
                ],
                'last_activity_at' => now(),
                'completed_at' => $autoRepaired ? now() : null,
            ]);

            $summaryLines = [
                '**0-Token System-Diagnose (`Systemi`)**',
                '- **Umgebung:** `' . $envTier . '`',
                '- **Ort:** `' . ($file ? $file . ($line ? ':' . $line : '') : 'Laufzeit') . '`',
                '- **Ursache:** ' . ($diag['probable_cause'] ?? Str::limit($message, 260)),
                '- **Empfehlung:** ' . ($diag['recommended_action'] ?? 'Automatisch in die serielle Antigravity-Reparatur-Queue eingereiht.'),
            ];
            if ($autoRepaired) {
                $summaryLines[] = '- **Selbstreparatur:** Bekannter Cache-/Queue-Klemmer wurde in 0 Tokens automatisch bereinigt.';
            }

            if (!$autoRepaired) {
                try {
                    $autopilotMandate = "\n\nAutopilot-Mandat (Lösen + Härten in einem Atemzug): Behebe nicht nur das akute Symptom, sondern härte den betroffenen Code-Pfad inkl. Nachbarstellen dauerhaft gegen Wiederauftreten ab und verankere einen automatisierten Regressions-Test.";
                    $enqueued = app(AntigravityDirectInboxService::class)->enqueueMessage([
                        'channel' => 'tickets',
                        'title' => "[{$ticket->ticket_number}] {$cleanTitle}",
                        'body' => implode("\n", $summaryLines) . "\n\nOriginal-Fehlermeldung:\n" . Str::limit($message, 1500) . $autopilotMandate,
                        'execution_mode' => 'direct',
                        'source' => 'system_sentinel',
                        'skip_universe_mirror' => true,
                    ]);
                    $newAgId = trim((string) ($enqueued['item']['id'] ?? ''));
                    if ($newAgId !== '') {
                        $p = is_array($ticket->action_payload) ? $ticket->action_payload : [];
                        $p['ag_item_id'] = $newAgId;
                        $ticket->update(['action_payload' => $p]);
                    }
                } catch (\Throwable) {
                }
            }

            AiUniverseTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_role' => 'agent',
                'sender_name' => 'Systemi',
                'ai_agent_id' => $systemiAgent?->id,
                'channel' => 'system_sentinel',
                'content' => implode("\n", $summaryLines),
                'executed_tools' => ['zero_token_fingerprint_guard'],
                'action_card' => $autoRepaired ? null : [
                    'type' => 'antigravity_handoff',
                    'label' => 'An Antigravity übergeben',
                ],
            ]);

            return [
                'action' => 'created_bundled_ticket',
                'fingerprint' => $fingerprint,
                'ticket_id' => $ticket->id,
                'occurrence_count' => 1,
                'auto_repaired' => $autoRepaired,
            ];
        } finally {
            self::$isHandling = false;
        }
    }

    /**
     * Führt ausschließlich harmlose, deterministische 0-Token PHP-Selbstreparaturen mit 30-Min-Cooldown aus.
     */
    protected static function attemptZeroTokenPhpRepair(string $message, string $category): bool
    {
        $msgLower = strtolower($message);

        // 1. Veraltetes kompiliertes Blade-View-Artefakt
        if (str_contains($msgLower, 'storage/framework/views/') && str_contains($msgLower, 'failed to open stream')) {
            if (Cache::add('zero_token_heal:view_clear', true, 1800)) {
                try {
                    Artisan::call('view:clear');
                    return true;
                } catch (\Throwable) {
                    return false;
                }
            }
        }

        // 2. Feststeckende alte Hintergrund-Tasks (> 25 Minuten unverändert auf processing)
        if (str_contains($msgLower, 'aiworkspacetask') && str_contains($msgLower, 'timeout')) {
            if (Cache::add('zero_token_heal:stuck_tasks', true, 1800) && Schema::hasTable('ai_workspace_tasks')) {
                AiWorkspaceTask::where('status', 'processing')
                    ->where('updated_at', '<', now()->subMinutes(25))
                    ->update(['status' => 'failed']);
                return true;
            }
        }

        // 3. Transiente MySQL Docker-DNS- oder Socket-Unterbrechung -> abgestandenen Connection-Handle sofort purgen
        if ($category === 'mysql_transient_dns_or_socket' || str_contains($msgLower, 'getaddrinfo for db')) {
            try {
                \Illuminate\Support\Facades\DB::purge('mysql');
                \Illuminate\Support\Facades\DB::reconnect('mysql');
                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }
}

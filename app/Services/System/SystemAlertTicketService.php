<?php

namespace App\Services\System;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Services\AI\AiOutputQualityGate;
use App\Services\AI\FunkiraPresenceContext;
use App\Services\AI\TicketDeliverableService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SystemAlertTicketService
{
    public const FINGERPRINT_PREFIX = 'sys_alert:';

    protected static bool $isDispatching = false;

    /**
     * Erzeugt den deterministischen, präfix-stabilen Fingerabdruck (max. 160 Zeichen für VARCHAR(191)-Index),
     * damit zusammengehörige Alarme dublettenfrei gebündelt und bei Heilung per Präfix geschlossen werden können.
     */
    public static function buildFingerprint(string $alertKey): string
    {
        $cleanKey = trim(preg_replace('/\s+/', '_', $alertKey) ?? $alertKey);
        if ($cleanKey === '') {
            $cleanKey = 'generic';
        }

        if (strlen($cleanKey) > 120) {
            $cleanKey = substr($cleanKey, 0, 75) . ':' . sha1($cleanKey);
        }

        return self::FINGERPRINT_PREFIX . $cleanKey;
    }

    /**
     * Ersetzt interne System-/Cronjob-Warnmails vollständig durch ein zentrales, dublettenfreies
     * Universum-Ticket (`AiUniverseTicket`) in der logisch passenden Fachabteilung.
     *
     * @param array<string, mixed> $extraPayload
     */
    public static function upsertSystemTicket(
        string $alertKey,
        string $title,
        string $markdownBody,
        string $departmentName = 'System',
        string $agentName = 'Systemi',
        string $ticketType = 'system_alert',
        string $priority = 'high',
        string $status = AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
        bool $assignToAntigravity = false,
        array $extraPayload = [],
        ?string $attachmentAbsolutePath = null,
        int $followupCooldownMinutes = 120
    ): ?AiUniverseTicket {
        if (
            self::$isDispatching
            || !Schema::hasTable('ai_universe_tickets')
            || !Schema::hasTable('ai_universe_ticket_messages')
        ) {
            return null;
        }

        self::$isDispatching = true;
        try {
            $fingerprint = self::buildFingerprint($alertKey);
            $cleanTitle = Str::limit(
                trim(AiOutputQualityGate::sanitizeText($title)),
                180
            );
            if ($cleanTitle === '') {
                $cleanTitle = 'System-Meldung';
            }

            $cleanBody = trim(AiOutputQualityGate::sanitizeText($markdownBody));
            if ($cleanBody === '') {
                $cleanBody = $cleanTitle;
            }

            $existing = AiUniverseTicket::query()
                ->where('error_fingerprint', $fingerprint)
                ->whereNotIn('status', [
                    AiUniverseTicket::STATUS_COMPLETED,
                    AiUniverseTicket::STATUS_ARCHIVED,
                ])
                ->orderByDesc('created_at')
                ->first();

            $nowIso = now()->toIso8601String();

            if ($existing) {
                $newCount = ((int) $existing->occurrence_count) + 1;
                $payload = is_array($existing->action_payload) ? $existing->action_payload : [];
                $payload = array_merge($payload, $extraPayload);
                $payload['alert_key'] = $alertKey;
                $payload['occurrences_count'] = $newCount;
                $payload['last_seen_at'] = $nowIso;

                $lastMsgAt = !empty($payload['last_alert_message_at'])
                    ? Carbon::parse((string) $payload['last_alert_message_at'])
                    : $existing->last_activity_at;

                $shouldAppendUpdate = $followupCooldownMinutes > 0
                    && $lastMsgAt !== null
                    && $lastMsgAt->diffInMinutes(now()) >= $followupCooldownMinutes;

                if ($shouldAppendUpdate) {
                    $payload['last_alert_message_at'] = $nowIso;
                    AiUniverseTicketMessage::create([
                        'ticket_id' => $existing->id,
                        'sender_role' => 'agent',
                        'sender_name' => $agentName,
                        'ai_agent_id' => $existing->assigned_agent_id,
                        'channel' => 'system_sentinel',
                        'content' => "**Erneute Meldung (#{$newCount})**\n\n" . Str::limit($cleanBody, 2500),
                        'executed_tools' => ['system_alert_ticket_dedup'],
                    ]);
                }

                $existing->update([
                    'occurrence_count' => $newCount,
                    'action_payload' => $payload,
                    'messages_count' => $existing->messages()->count(),
                    'last_activity_at' => now(),
                ]);

                return $existing->fresh(['messages']);
            }

            $dept = Schema::hasTable('ai_departments')
                ? (AiDepartment::where('name', $departmentName)->first()
                    ?? AiDepartment::where('name', 'System')->first())
                : null;

            $agent = Schema::hasTable('ai_agents')
                ? (AiAgent::where('name', $agentName)->first()
                    ?? AiAgent::where('name', 'Systemi')->first()
                    ?? AiAgent::where('name', 'Funkira')->first())
                : null;

            $safePriority = in_array($priority, ['low', 'normal', 'high', 'critical'], true)
                ? ($priority === 'critical' ? 'high' : $priority)
                : 'high';

            $envTier = FunkiraPresenceContext::resolveEnvironmentTier();

            $payload = array_merge([
                'type' => $assignToAntigravity ? 'antigravity_handoff' : 'system_alert',
                'alert_key' => $alertKey,
                'first_seen_at' => $nowIso,
                'last_seen_at' => $nowIso,
                'last_alert_message_at' => $nowIso,
                'occurrences_count' => 1,
                'is_business' => true,
            ], $extraPayload);

            $ticket = AiUniverseTicket::create([
                'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
                'title' => $cleanTitle,
                'status' => $status,
                'priority' => $safePriority,
                'ticket_type' => $ticketType,
                'ai_department_id' => $dept?->id,
                'assigned_agent_id' => $agent?->id,
                'assigned_to_antigravity' => $assignToAntigravity,
                'source_channel' => 'system_sentinel',
                'environment_tier' => $envTier,
                'error_fingerprint' => $fingerprint,
                'occurrence_count' => 1,
                'messages_count' => 1,
                'action_payload' => $payload,
                'last_activity_at' => now(),
            ]);

            $attachments = null;
            if (
                $attachmentAbsolutePath !== null
                && $attachmentAbsolutePath !== ''
                && is_file($attachmentAbsolutePath)
                && is_readable($attachmentAbsolutePath)
                && class_exists(TicketDeliverableService::class)
            ) {
                try {
                    $meta = TicketDeliverableService::registerLocalFileForActiveTicket(
                        $attachmentAbsolutePath,
                        basename($attachmentAbsolutePath),
                        ['pinned' => false],
                        $ticket->id
                    );
                    TicketDeliverableService::pullQueuedAttachments($ticket->id);
                    if ($meta) {
                        $attachments = [$meta];
                    }
                } catch (\Throwable) {
                    $attachments = null;
                }
            }

            AiUniverseTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_role' => 'agent',
                'sender_name' => $agent?->name ?: $agentName,
                'ai_agent_id' => $agent?->id,
                'channel' => 'system_sentinel',
                'content' => $cleanBody,
                'attachments' => $attachments,
                'executed_tools' => ['system_alert_ticket_bridge'],
                'action_card' => $assignToAntigravity ? [
                    'type' => 'antigravity_handoff',
                    'label' => 'An Antigravity übergeben',
                ] : null,
            ]);

            return $ticket->fresh(['messages']);
        } catch (\Throwable $e) {
            Log::error('SystemAlertTicketService failed to create/update ticket: ' . $e->getMessage());
            return null;
        } finally {
            self::$isDispatching = false;
        }
    }

    /**
     * Schließt offene System-Warn-Tickets automatisch als [AUTO-GEHEILT], sobald ein nachfolgender
     * Prüflauf (z. B. Blueprint-Scan, Security-Audit, Bridge-Health oder Cronjob-Lauf) wieder 100 % grün ist.
     */
    public static function autoResolveByAlertPrefix(
        string $alertKeyPrefix,
        string $resolutionSummary,
        string $agentName = 'Systemi'
    ): int {
        if (
            self::$isDispatching
            || !Schema::hasTable('ai_universe_tickets')
            || !Schema::hasTable('ai_universe_ticket_messages')
        ) {
            return 0;
        }

        $cleanPrefix = self::FINGERPRINT_PREFIX . trim(preg_replace('/\s+/', '_', $alertKeyPrefix) ?? $alertKeyPrefix);
        $escapedLike = addcslashes($cleanPrefix, '%_\\') . '%';

        $openTickets = AiUniverseTicket::query()
            ->where('error_fingerprint', 'like', $escapedLike)
            ->whereNotIn('status', [
                AiUniverseTicket::STATUS_COMPLETED,
                AiUniverseTicket::STATUS_ARCHIVED,
            ])
            ->get();

        if ($openTickets->isEmpty()) {
            return 0;
        }

        $cleanSummary = trim(AiOutputQualityGate::sanitizeText($resolutionSummary));
        $resolvedCount = 0;

        foreach ($openTickets as $ticket) {
            try {
                $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
                $payload['auto_healed'] = true;
                $payload['auto_healed_at'] = now()->toIso8601String();
                $payload['auto_heal_reason'] = $cleanSummary;

                AiUniverseTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'agent',
                    'sender_name' => $agentName,
                    'ai_agent_id' => $ticket->assigned_agent_id,
                    'channel' => 'system_sentinel',
                    'content' => "**Status -> Erledigt ([AUTO-GEHEILT])**\n" . $cleanSummary,
                    'executed_tools' => ['system_alert_auto_heal'],
                ]);

                $ticket->update([
                    'status' => AiUniverseTicket::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'action_payload' => $payload,
                    'messages_count' => $ticket->messages()->count(),
                    'last_activity_at' => now(),
                ]);

                $resolvedCount++;
            } catch (\Throwable $e) {
                Log::warning('SystemAlertTicketService autoResolve failed: ' . $e->getMessage());
            }
        }

        return $resolvedCount;
    }
}

<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Services\System\AntigravityDirectInboxService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AntigravityUniverseTicketBridge
{
    private static bool $syncInProgress = false;

    /**
     * Mappt den Antigravity-Direktkanal auf die passende Seelenfunke-Abteilung.
     */
    public static function resolveDepartmentName(string $agChannel): string
    {
        return match (strtolower(trim($agChannel))) {
            'leitung' => 'Leitung',
            'buchhaltung' => 'Buchhaltung',
            'shop' => 'Produkte',
            'tickets' => 'System',
            default => 'System',
        };
    }

    /**
     * Mappt die Quelle aus AntigravityDirectInbox auf den kanonischen Universum-Kanal.
     */
    public static function resolveSourceChannel(string $rawSource, string $agChannel): string
    {
        $src = strtolower(trim($rawSource));
        if (str_contains($src, 'live')) {
            return 'android_live_call';
        }
        if (str_contains($src, 'minipc') || $agChannel === 'minipc') {
            return 'workshop_minipc';
        }
        if (str_contains($src, 'desktop') || str_contains($src, 'funkenwerk')) {
            return 'funkenwerk_desktop';
        }
        if (str_contains($src, 'web') || str_contains($src, 'portal')) {
            return 'web_portal';
        }

        return 'android_app';
    }

    /**
     * Findet das zugehörige Universum-Ticket anhand der ag_... ID.
     */
    public static function findTicketByAgId(string $agId): ?AiUniverseTicket
    {
        if ($agId === '' || !Schema::hasTable('ai_universe_tickets')) {
            return null;
        }

        return AiUniverseTicket::query()
            ->where('action_payload', 'like', '%"' . addcslashes($agId, '%_\\') . '"%')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Synchronisiert ein Antigravity-Inbox-Element 1:1 ohne Umwege in ai_universe_tickets.
     *
     * @param array<string, mixed> $item
     */
    public static function syncInboxItem(array $item, string $event = 'sync'): ?AiUniverseTicket
    {
        if (self::$syncInProgress || !Schema::hasTable('ai_universe_tickets') || !Schema::hasTable('ai_universe_ticket_messages')) {
            return null;
        }

        $agId = trim((string) ($item['id'] ?? ''));
        if ($agId === '') {
            return null;
        }

        self::$syncInProgress = true;
        try {
            $agStatus = strtolower(trim((string) ($item['status'] ?? 'pending')));
            $execMode = strtolower(trim((string) ($item['execution_mode'] ?? 'concept')));
            $agChannel = strtolower(trim((string) ($item['channel'] ?? 'leitung')));
            $rawTitle = AiOutputQualityGate::sanitizeText((string) ($item['title'] ?? $agId));
            $rawBody = AiOutputQualityGate::sanitizeText((string) ($item['body'] ?? ''));
            $summary = AiOutputQualityGate::sanitizeText((string) ($item['ag_response_summary'] ?? ''));
            $pendingQuestion = AiOutputQualityGate::sanitizeText((string) ($item['pending_question'] ?? ''));
            $checklist = is_array($item['checklist'] ?? null) ? $item['checklist'] : [];

            $isDismissed = str_contains($summary, '[Verworfen durch');
            $deptName = self::resolveDepartmentName($agChannel);
            $dept = AiDepartment::where('name', $deptName)->first()
                ?? AiDepartment::where('name', 'System')->first();
            $agAgent = AiAgent::where('name', 'Antigravity')->first()
                ?? AiAgent::where('name', 'Funkira')->first();

            $targetStatus = match (true) {
                $isDismissed => AiUniverseTicket::STATUS_ARCHIVED,
                $agStatus === 'awaiting_go' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                $agStatus === 'completed' => AiUniverseTicket::STATUS_COMPLETED,
                $agStatus === 'delivered' => AiUniverseTicket::STATUS_IN_PROGRESS,
                default => FunkiraPresenceContext::isAntigravityNotebookOnline()
                    ? AiUniverseTicket::STATUS_IN_PROGRESS
                    : AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK,
            };

            $createdAt = !empty($item['created_at']) ? Carbon::parse((string) $item['created_at']) : now();
            $completedAt = !empty($item['completed_at']) ? Carbon::parse((string) $item['completed_at']) : null;
            $lastActivityAt = $completedAt
                ?? (!empty($item['awaiting_go_at']) ? Carbon::parse((string) $item['awaiting_go_at']) : null)
                ?? (!empty($item['delivered_at']) ? Carbon::parse((string) $item['delivered_at']) : null)
                ?? $createdAt;

            $payloadType = $targetStatus === AiUniverseTicket::STATUS_WAITING_FOR_ALINA
                ? 'ag_inbox_blockade'
                : ($isDismissed ? 'ag_inbox_dismissed' : 'antigravity_active');

            $actionPayload = [
                'type' => $payloadType,
                'ag_item_id' => $agId,
                'ag_channel' => $agChannel,
                'execution_mode' => $execMode,
                'ag_status' => $agStatus,
                'dismissed' => $isDismissed,
                'pending_question' => $pendingQuestion !== '' ? $pendingQuestion : null,
                'ag_response_summary' => $summary !== '' ? $summary : null,
            ];

            $ticket = self::findTicketByAgId($agId);
            $sourceChannel = self::resolveSourceChannel((string) ($item['source'] ?? 'android_app'), $agChannel);
            $modeBadge = $execMode === 'concept' ? '[Konzept] ' : '';
            $cleanTitle = Str::limit($modeBadge . $rawTitle, 180);

            if (!$ticket) {
                $ticket = new AiUniverseTicket([
                    'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
                    'title' => $cleanTitle,
                    'status' => $targetStatus,
                    'priority' => $agStatus === 'awaiting_go' ? 'high' : 'normal',
                    'ticket_type' => 'antigravity_dev',
                    'ai_department_id' => $dept?->id,
                    'assigned_agent_id' => $agAgent?->id,
                    'assigned_to_antigravity' => true,
                    'source_channel' => $sourceChannel,
                    'environment_tier' => FunkiraPresenceContext::resolveEnvironmentTier(),
                    'messages_count' => 0,
                    'action_payload' => $actionPayload,
                    'last_activity_at' => $lastActivityAt,
                    'completed_at' => $targetStatus === AiUniverseTicket::STATUS_COMPLETED ? ($completedAt ?? now()) : null,
                ]);
                $ticket->created_at = $createdAt;
                $ticket->updated_at = $lastActivityAt;
                $ticket->save();

                // Chronologische Start-Nachricht (Auftrag von Alina / Funkira an Antigravity)
                $introLines = [];
                $introLines[] = "**Auftrag an Antigravity ({$agId} · Modus: " . ($execMode === 'concept' ? 'Konzept vor Umsetzung' : 'Direkt-Umsetzung') . ")**";
                if ($rawBody !== '') {
                    $introLines[] = $rawBody;
                } else {
                    $introLines[] = $rawTitle;
                }
                if (!empty($checklist)) {
                    $introLines[] = "Checkliste:\n- " . implode("\n- ", $checklist);
                }

                $attachments = null;
                if (!empty($item['has_attachment']) && !empty($item['attachment_name'])) {
                    $resolvedAgMedia = null;
                    try {
                        $resolvedAgMedia = app(\App\Services\System\AntigravityDirectInboxService::class)->resolveAttachmentForStreaming($agId);
                    } catch (\Throwable) {
                        $resolvedAgMedia = null;
                    }

                    if ($resolvedAgMedia !== null && !empty($resolvedAgMedia['path']) && is_file($resolvedAgMedia['path'])) {
                        $storedMeta = TicketDeliverableService::registerLocalFileForActiveTicket(
                            $resolvedAgMedia['path'],
                            (string) $item['attachment_name'],
                            ['mime' => (string) ($item['attachment_mime'] ?? $resolvedAgMedia['mime'] ?? 'image/jpeg')],
                            null
                        );
                        if ($storedMeta !== null) {
                            $attachments = [$storedMeta];
                        }
                    }

                    if ($attachments === null) {
                        $attachments = [[
                            'name' => (string) $item['attachment_name'],
                            'mime' => (string) ($item['attachment_mime'] ?? 'application/octet-stream'),
                            'url' => "/admin/ai/ag-attachment/{$agId}",
                        ]];
                    }
                }

                $firstMsg = new AiUniverseTicketMessage([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'alina',
                    'sender_name' => 'Alina (via Funkira)',
                    'channel' => $sourceChannel,
                    'content' => implode("\n\n", $introLines),
                    'attachments' => $attachments,
                    'executed_tools' => ['ag_send_to_antigravity'],
                ]);
                $firstMsg->created_at = $createdAt;
                $firstMsg->save();

                // Falls bei Backfill bereits eine Antwort/Blockade von Antigravity vorliegt, direkt chronologisch anhängen
                if ($summary !== '' || $pendingQuestion !== '') {
                    $agText = $pendingQuestion !== '' ? $pendingQuestion : $summary;
                    $replyMsg = new AiUniverseTicketMessage([
                        'ticket_id' => $ticket->id,
                        'sender_role' => 'agent',
                        'sender_name' => 'Antigravity',
                        'ai_agent_id' => $agAgent?->id,
                        'channel' => 'funkenwerk_desktop',
                        'content' => $agText,
                        'action_card' => $targetStatus === AiUniverseTicket::STATUS_WAITING_FOR_ALINA ? $actionPayload : null,
                    ]);
                    $replyMsg->created_at = $lastActivityAt;
                    $replyMsg->save();
                }

                $ticket->update(['messages_count' => $ticket->messages()->count()]);
                TicketAutoCompactService::compactIfNeeded($ticket);

                return $ticket;
            }

            // Bestehendes Ticket aktualisieren
            $prevPayload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
            $prevSummary = trim((string) ($prevPayload['ag_response_summary'] ?? ''));

            if ($event === 'delivered' && $ticket->status === AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK) {
                AiUniverseTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'system',
                    'sender_name' => 'Antigravity',
                    'ai_agent_id' => $agAgent?->id,
                    'channel' => 'funkenwerk_desktop',
                    'content' => "Notebook online: Auftrag {$agId} wurde von Antigravity abgeholt und ist jetzt in Bearbeitung.",
                ]);
            } elseif (($event === 'awaiting_go' || $event === 'completed') && ($summary !== '' || $pendingQuestion !== '') && $summary !== $prevSummary) {
                $msgBody = $event === 'awaiting_go'
                    ? "**Konzept / Rückfrage von Antigravity (wartet auf dein Go):**\n" . ($pendingQuestion !== '' ? $pendingQuestion : $summary)
                    : "**Abschlussbericht von Antigravity:**\n" . $summary;

                AiUniverseTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'agent',
                    'sender_name' => 'Antigravity',
                    'ai_agent_id' => $agAgent?->id,
                    'channel' => 'funkenwerk_desktop',
                    'content' => $msgBody,
                    'action_card' => $event === 'awaiting_go' ? $actionPayload : null,
                ]);
            }

            $mergedPayload = array_merge($prevPayload, $actionPayload);
            if ($targetStatus === AiUniverseTicket::STATUS_COMPLETED) {
                $mergedPayload['queue_state'] = 'completed';
                $mergedPayload['queue_completed_at'] = now()->toIso8601String();
            } elseif ($targetStatus === AiUniverseTicket::STATUS_WAITING_FOR_ALINA) {
                $mergedPayload['queue_state'] = 'waiting_for_alina';
            }

            $ticket->update([
                'status' => $targetStatus,
                'priority' => $targetStatus === AiUniverseTicket::STATUS_WAITING_FOR_ALINA ? 'high' : $ticket->priority,
                'action_payload' => $mergedPayload,
                'messages_count' => $ticket->messages()->count(),
                'last_activity_at' => now(),
                'completed_at' => $targetStatus === AiUniverseTicket::STATUS_COMPLETED ? ($ticket->completed_at ?? now()) : $ticket->completed_at,
            ]);

            if ($targetStatus === AiUniverseTicket::STATUS_COMPLETED) {
                $siblingIds = [];
                if (!empty($prevPayload['ag_history_ids']) && is_array($prevPayload['ag_history_ids'])) {
                    $siblingIds = array_merge($siblingIds, array_map('strval', $prevPayload['ag_history_ids']));
                }
                if (!empty($prevPayload['previous_concept_ag_id'])) {
                    $siblingIds[] = (string) $prevPayload['previous_concept_ag_id'];
                }
                if (!empty($prevPayload['ag_item_id']) && (string) $prevPayload['ag_item_id'] !== $agId) {
                    $siblingIds[] = (string) $prevPayload['ag_item_id'];
                }
                if ($siblingIds !== []) {
                    try {
                        app(AntigravityDirectInboxService::class)->closeSiblingItems(
                            $siblingIds,
                            $summary !== '' ? $summary : "Abgeschlossen über Ticket {$ticket->ticket_number}."
                        );
                    } catch (\Throwable) {
                    }
                }

                CentralBrainService::recordTicketCompletionReport($ticket, $summary !== '' ? $summary : null);
            }

            TicketAutoCompactService::compactIfNeeded($ticket);

            return $ticket;
        } catch (\Throwable) {
            return null;
        } finally {
            self::$syncInProgress = false;
        }
    }

    /**
     * Protokolliert Alinas Entscheidung (Go, Antwort, Verworfen, Zurückgestellt) sauber chronologisch im Ticket.
     *
     * @param array<string, mixed> $resolvedItem
     * @param array<string, mixed>|null $followupItem
     */
    public static function syncResolvedBlockade(
        array $resolvedItem,
        string $decision,
        string $answerText,
        string $actorLabel,
        ?array $followupItem = null
    ): void {
        if (self::$syncInProgress || !Schema::hasTable('ai_universe_tickets')) {
            return;
        }

        $agId = trim((string) ($resolvedItem['id'] ?? ''));
        if ($agId === '') {
            return;
        }

        $ticket = self::findTicketByAgId($agId) ?? self::syncInboxItem($resolvedItem, 'sync');
        if (!$ticket) {
            return;
        }

        self::$syncInProgress = true;
        try {
            $cleanAnswer = AiOutputQualityGate::sanitizeText($answerText);

            if ($decision === 'dismissed') {
                AiUniverseTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'alina',
                    'sender_name' => $actorLabel,
                    'channel' => 'web_portal',
                    'content' => "Konzept / Blockade **{$agId}** verworfen" . ($cleanAnswer !== '' ? ": {$cleanAnswer}" : '.'),
                    'executed_tools' => ['ag_resolve_pending_blockade'],
                ]);

                $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
                $payload['type'] = 'ag_inbox_dismissed';
                $payload['dismissed'] = true;
                $payload['ag_status'] = 'completed';

                $ticket->update([
                    'status' => AiUniverseTicket::STATUS_ARCHIVED,
                    'action_payload' => $payload,
                    'messages_count' => $ticket->messages()->count(),
                    'last_activity_at' => now(),
                ]);
                return;
            }

            if ($decision === 'deferred') {
                if ($cleanAnswer !== '') {
                    AiUniverseTicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'sender_role' => 'alina',
                        'sender_name' => $actorLabel,
                        'channel' => 'web_portal',
                        'content' => "Entscheidung zurückgestellt: {$cleanAnswer}",
                    ]);
                    $ticket->update([
                        'messages_count' => $ticket->messages()->count(),
                        'last_activity_at' => now(),
                    ]);
                }
                return;
            }

            // approved oder info_provided: Gleiches Ticket nahtlos in die Umsetzungs-Phase überführen (kein Kontextbruch!)
            $followupAgId = trim((string) ($followupItem['id'] ?? ''));
            $decisionLabel = $decision === 'approved' ? 'GO erteilt' : 'Rückfrage beantwortet';
            $msg = "**{$decisionLabel}**" . ($cleanAnswer !== '' ? ": {$cleanAnswer}" : '.')
                . ($followupAgId !== '' ? "\nDirekt-Umsetzungsauftrag `{$followupAgId}` wurde an Antigravity übergeben." : '');

            AiUniverseTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_role' => 'alina',
                'sender_name' => $actorLabel,
                'channel' => 'web_portal',
                'content' => $msg,
                'executed_tools' => ['ag_resolve_pending_blockade'],
            ]);

            $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
            $payload['type'] = 'antigravity_active';
            $payload['previous_concept_ag_id'] = $agId;
            if ($followupAgId !== '') {
                $payload['ag_item_id'] = $followupAgId;
            }
            $payload['execution_mode'] = 'direct';
            $payload['ag_status'] = 'pending';
            $payload['pending_question'] = null;

            $notebookOnline = FunkiraPresenceContext::isAntigravityNotebookOnline();
            $ticket->update([
                'title' => str_starts_with($ticket->title, '[Konzept] ')
                    ? substr($ticket->title, strlen('[Konzept] '))
                    : $ticket->title,
                'status' => $notebookOnline
                    ? AiUniverseTicket::STATUS_IN_PROGRESS
                    : AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK,
                'action_payload' => $payload,
                'messages_count' => $ticket->messages()->count(),
                'last_activity_at' => now(),
                'completed_at' => null,
            ]);

            TicketAutoCompactService::compactIfNeeded($ticket);
        } catch (\Throwable) {
        } finally {
            self::$syncInProgress = false;
        }
    }

    public static function archiveByAgItemId(string $agId): void
    {
        $ticket = self::findTicketByAgId($agId);
        if ($ticket && $ticket->status !== AiUniverseTicket::STATUS_ARCHIVED) {
            $ticket->update([
                'status' => AiUniverseTicket::STATUS_ARCHIVED,
                'last_activity_at' => now(),
            ]);
        }
    }

    /**
     * Überträgt alle vorhandenen Einträge aus antigravity_direct_inbox.json idempotent ins zentrale Ticketsystem.
     */
    public static function backfillAllFromInbox(?AntigravityDirectInboxService $service = null): int
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            return 0;
        }

        $inbox = $service ?? app(AntigravityDirectInboxService::class);
        $snapshot = $inbox->listInbox(AntigravityDirectInboxService::MAX_ITEMS);
        $items = array_reverse($snapshot['items'] ?? []); // Älteste zuerst für saubere SF-XXXX Nummerierung

        // Filtere reine "GO & Antwort zu:" Folge-Items heraus, wenn deren Referenz-Ticket bereits existiert
        $synced = 0;
        foreach ($items as $item) {
            $body = (string) ($item['body'] ?? '');
            if (preg_match('/^Referenz-Ticket:\s*(ag_[A-Za-z0-9_]+)/m', $body, $m)) {
                $origTicket = self::findTicketByAgId($m[1]);
                if ($origTicket) {
                    $followId = (string) ($item['id'] ?? '');
                    $followStatus = (string) ($item['status'] ?? 'pending');
                    $followSummary = AiOutputQualityGate::sanitizeText((string) ($item['ag_response_summary'] ?? ''));

                    $p = is_array($origTicket->action_payload) ? $origTicket->action_payload : [];
                    $p['previous_concept_ag_id'] = $m[1];
                    $p['ag_item_id'] = $followId;
                    $p['ag_status'] = $followStatus;

                    if ($followStatus === 'completed' && $followSummary !== '') {
                        AiUniverseTicketMessage::create([
                            'ticket_id' => $origTicket->id,
                            'sender_role' => 'agent',
                            'sender_name' => 'Antigravity',
                            'channel' => 'funkenwerk_desktop',
                            'content' => "**Abschlussbericht von Antigravity ({$followId}):**\n{$followSummary}",
                        ]);
                    }

                    $origTicket->update([
                        'status' => $followStatus === 'completed'
                            ? AiUniverseTicket::STATUS_COMPLETED
                            : AiUniverseTicket::STATUS_IN_PROGRESS,
                        'action_payload' => $p,
                        'messages_count' => $origTicket->messages()->count(),
                        'completed_at' => $followStatus === 'completed' ? now() : null,
                    ]);
                    continue;
                }
            }

            if (self::syncInboxItem($item, 'backfill')) {
                $synced++;
            }
        }

        return $synced;
    }
}

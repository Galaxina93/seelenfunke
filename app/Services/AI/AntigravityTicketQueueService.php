<?php

namespace App\Services\AI;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Services\System\AntigravityDirectInboxService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AntigravityTicketQueueService
{
    public const MAX_AUTO_ATTEMPTS = 3;
    public const DEFAULT_IDLE_TIMEOUT_SECONDS = 180;
    public const WORKER_CHANNEL = 'tickets';

    public function __construct(
        private readonly AntigravityDirectInboxService $inboxService
    ) {
    }

    /**
     * Synchronisiert ai_universe_tickets und antigravity_direct_inbox.json bidirektional in 0 KI-Tokens.
     *
     * @return array{closed_orphan_inbox_items: int, synced_tickets: int, backfilled_inbox_items: int}
     */
    public function reconcileQueueState(): array
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            return [
                'closed_orphan_inbox_items' => 0,
                'synced_tickets' => 0,
                'backfilled_inbox_items' => 0,
            ];
        }

        $snapshot = $this->inboxService->listInbox(AntigravityDirectInboxService::MAX_ITEMS);
        $inboxById = [];
        foreach ($snapshot['items'] ?? [] as $item) {
            $iid = trim((string) ($item['id'] ?? ''));
            if ($iid !== '') {
                $inboxById[$iid] = $item;
            }
        }

        $closedOrphans = 0;
        $syncedTickets = 0;
        $backfilledInbox = 0;

        // 1. Abgeschlossene/archivierte Tickets bereinigen verwaiste delivered/pending Inbox-Einträge
        $closedTickets = AiUniverseTicket::query()
            ->whereIn('status', [AiUniverseTicket::STATUS_COMPLETED, AiUniverseTicket::STATUS_ARCHIVED])
            ->whereNotNull('action_payload')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $orphanIdsToClose = [];
        foreach ($closedTickets as $ct) {
            foreach ($this->extractLinkedAgIds($ct) as $linkedId) {
                if (isset($inboxById[$linkedId]) && ($inboxById[$linkedId]['status'] ?? '') !== 'completed') {
                    $orphanIdsToClose[] = $linkedId;
                }
            }
        }

        if ($orphanIdsToClose !== []) {
            $closedOrphans = $this->inboxService->closeSiblingItems(
                $orphanIdsToClose,
                'Ticket im zentralen Ticketsystem bereits abgeschlossen.'
            );
        }

        // 2. Offene Antigravity-Tickets mit Inbox abgleichen (Fertigmeldungen übernehmen oder fehlendes Inbox-Item anlegen)
        $openAgTickets = $this->queryOpenAntigravityTickets();
        foreach ($openAgTickets as $ticket) {
            $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
            $agId = trim((string) ($payload['ag_item_id'] ?? ''));

            if ($agId !== '' && isset($inboxById[$agId])) {
                $inboxItem = $inboxById[$agId];
                $inboxStatus = (string) ($inboxItem['status'] ?? 'pending');

                if ($inboxStatus === 'completed') {
                    AntigravityUniverseTicketBridge::syncInboxItem($inboxItem, 'completed');
                    $syncedTickets++;
                    continue;
                }

                if ($inboxStatus === 'awaiting_go') {
                    AntigravityUniverseTicketBridge::syncInboxItem($inboxItem, 'awaiting_go');
                    $syncedTickets++;
                    continue;
                }
            }

            if ($agId === '' || !isset($inboxById[$agId])) {
                $newAgId = $this->enqueueMissingInboxItemForTicket($ticket);
                if ($newAgId !== null) {
                    $backfilledInbox++;
                }
            }
        }

        return [
            'closed_orphan_inbox_items' => $closedOrphans,
            'synced_tickets' => $syncedTickets,
            'backfilled_inbox_items' => $backfilledInbox,
        ];
    }

    /**
     * Liefert Status-Telemetrie der gesamten seriellen Work-Until-Zero Queue.
     *
     * @return array<string, mixed>
     */
    public function getQueueStatus(): array
    {
        $reconciled = $this->reconcileQueueState();
        $openTickets = $this->sortQueueCandidates($this->queryOpenAntigravityTickets());
        $inFlight = $openTickets->first(fn (AiUniverseTicket $t): bool => $this->isTicketInFlight($t));

        $waitingForAlinaCount = Schema::hasTable('ai_universe_tickets')
            ? AiUniverseTicket::query()
                ->where('status', AiUniverseTicket::STATUS_WAITING_FOR_ALINA)
                ->count()
            : 0;

        return [
            'status' => 'success',
            'open_antigravity_count' => $openTickets->count(),
            'waiting_for_alina_count' => $waitingForAlinaCount,
            'in_flight_ticket' => $inFlight?->ticket_number,
            'reconciled' => $reconciled,
            'queued_tickets' => $openTickets->map(function (AiUniverseTicket $t): array {
                $p = is_array($t->action_payload) ? $t->action_payload : [];

                return [
                    'ticket_number' => $t->ticket_number,
                    'title' => $t->title,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'ag_item_id' => $p['ag_item_id'] ?? null,
                    'queue_state' => $p['queue_state'] ?? 'queued',
                    'attempts' => (int) ($p['auto_dispatch_attempts'] ?? 0),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Serielle FIFO-Auswahl (MaxInFlight = 1) inklusive Inaktivitäts-Watchdog und 3-Versuche-Schutzbremse.
     *
     * @return array<string, mixed>
     */
    public function claimNextDispatchableTicket(
        int $idleTimeoutSeconds = self::DEFAULT_IDLE_TIMEOUT_SECONDS,
        int $chatIdleSeconds = -1
    ): array {
        $lock = Cache::lock('sf_ag_ticket_queue_claim_lock', 10);
        if (!$lock->get()) {
            return [
                'status' => 'busy',
                'action' => 'wait',
                'reason' => 'claim_lock_active',
            ];
        }

        try {
            $idleTimeoutSeconds = max(30, $idleTimeoutSeconds);
            $reconciled = $this->reconcileQueueState();
            $candidates = $this->sortQueueCandidates($this->queryOpenAntigravityTickets());

            if ($candidates->isEmpty()) {
                return [
                    'status' => 'idle',
                    'action' => 'none',
                    'open_count' => 0,
                    'reconciled' => $reconciled,
                ];
            }

            /** @var AiUniverseTicket|null $inFlight */
            $inFlight = $candidates->first(fn (AiUniverseTicket $t): bool => $this->isTicketInFlight($t));
            $selectedTicket = null;
            $isRecoveryRetry = false;

            if ($inFlight !== null) {
                $payload = is_array($inFlight->action_payload) ? $inFlight->action_payload : [];
                $dispatchedAtRaw = (string) ($payload['queue_dispatched_at'] ?? '');
                $elapsedSinceDispatch = $dispatchedAtRaw !== ''
                    ? max(0, (int) Carbon::parse($dispatchedAtRaw)->diffInSeconds(now(), false))
                    : ($idleTimeoutSeconds + 1);

                // Kurze Anlauf-Schonfrist direkt nach dem Senden (damit das Transkript anlaufen kann)
                $inStartupGrace = $elapsedSinceDispatch < min(45, $idleTimeoutSeconds);
                $workerActivelyWriting = $chatIdleSeconds >= 0
                    ? ($chatIdleSeconds < $idleTimeoutSeconds)
                    : ($elapsedSinceDispatch < $idleTimeoutSeconds);

                if ($inStartupGrace || $workerActivelyWriting) {
                    return [
                        'status' => 'busy',
                        'action' => 'wait',
                        'open_count' => $candidates->count(),
                        'in_flight_ticket' => $inFlight->ticket_number,
                        'ag_item_id' => $payload['ag_item_id'] ?? null,
                        'elapsed_seconds' => $elapsedSinceDispatch,
                        'chat_idle_seconds' => $chatIdleSeconds,
                        'reconciled' => $reconciled,
                    ];
                }

                // Worker-Chat ist seit >= idleTimeoutSeconds inaktiv, Ticket aber noch nicht abgeschlossen!
                $attemptsSoFar = (int) ($payload['auto_dispatch_attempts'] ?? 0);
                if ($attemptsSoFar >= self::MAX_AUTO_ATTEMPTS) {
                    $this->tripCircuitBreaker($inFlight, $attemptsSoFar);
                    $candidates = $this->sortQueueCandidates($this->queryOpenAntigravityTickets());
                    if ($candidates->isEmpty()) {
                        return [
                            'status' => 'idle',
                            'action' => 'none',
                            'open_count' => 0,
                            'circuit_breaker_tripped' => $inFlight->ticket_number,
                            'reconciled' => $reconciled,
                        ];
                    }
                } else {
                    $selectedTicket = $inFlight;
                    $isRecoveryRetry = true;
                }
            }

            if ($selectedTicket === null) {
                $selectedTicket = $candidates->first();
            }

            if (!$selectedTicket) {
                return [
                    'status' => 'idle',
                    'action' => 'none',
                    'open_count' => 0,
                    'reconciled' => $reconciled,
                ];
            }

            $payload = is_array($selectedTicket->action_payload) ? $selectedTicket->action_payload : [];
            $agId = trim((string) ($payload['ag_item_id'] ?? ''));
            if ($agId === '') {
                $agId = (string) $this->enqueueMissingInboxItemForTicket($selectedTicket);
                $selectedTicket->refresh();
                $payload = is_array($selectedTicket->action_payload) ? $selectedTicket->action_payload : [];
            }

            $nextAttempt = ((int) ($payload['auto_dispatch_attempts'] ?? 0)) + 1;
            $execMode = strtolower(trim((string) ($payload['execution_mode'] ?? 'direct')));
            if (!in_array($execMode, ['concept', 'direct'], true)) {
                $execMode = 'direct';
            }

            // Wenn es ein expliziter Fachabteilungs-Kanal (z. B. shop, buchhaltung, invest) aus der App ist, respektieren;
            // ansonsten in den dedizierten "[SF] Ticket-Autopilot" Kanal ("tickets") routen, damit "leitung" frei bleibt.
            $targetChannel = $this->resolveQueueTargetChannel($selectedTicket);

            $prompt = $this->buildQueueWorkerPrompt(
                $selectedTicket,
                $agId,
                $execMode,
                $nextAttempt,
                $candidates->count(),
                $isRecoveryRetry
            );

            return [
                'status' => 'ok',
                'action' => 'dispatch',
                'open_count' => $candidates->count(),
                'ticket_id' => $selectedTicket->id,
                'ticket_number' => $selectedTicket->ticket_number,
                'ag_item_id' => $agId,
                'channel' => $targetChannel,
                'execution_mode' => $execMode,
                'attempt' => $nextAttempt,
                'max_attempts' => self::MAX_AUTO_ATTEMPTS,
                'is_recovery_retry' => $isRecoveryRetry,
                'title' => "[{$selectedTicket->ticket_number}] {$selectedTicket->title}",
                'prompt' => $prompt,
                'reconciled' => $reconciled,
            ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Phase 2 des 2-Phasen-Commits: Markiert das Ticket erst nach erfolgreichem agentapi-Versand als "in_flight".
     *
     * @return array<string, mixed>
     */
    public function acknowledgeDispatched(string $ticketNumberOrAgId, ?int $attempt = null): array
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            return ['status' => 'error', 'message' => 'ai_universe_tickets Tabelle fehlt.'];
        }

        $needle = trim($ticketNumberOrAgId);
        $ticket = AiUniverseTicket::where('ticket_number', $needle)->first()
            ?? AntigravityUniverseTicketBridge::findTicketByAgId($needle);

        if (!$ticket) {
            return ['status' => 'not_found', 'message' => "Ticket nicht gefunden: {$needle}"];
        }

        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $effectiveAttempt = $attempt !== null && $attempt > 0
            ? $attempt
            : (((int) ($payload['auto_dispatch_attempts'] ?? 0)) + 1);

        $payload['queue_state'] = 'in_flight';
        $payload['queue_dispatched_at'] = now()->toIso8601String();
        $payload['auto_dispatch_attempts'] = $effectiveAttempt;

        $ticket->update([
            'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
            'assigned_to_antigravity' => true,
            'action_payload' => $payload,
            'last_activity_at' => now(),
        ]);

        $agId = trim((string) ($payload['ag_item_id'] ?? ''));
        if ($agId !== '') {
            $this->inboxService->markDelivered([$agId]);
        }

        return [
            'status' => 'success',
            'ticket_number' => $ticket->ticket_number,
            'ag_item_id' => $agId,
            'attempt' => $effectiveAttempt,
            'queue_state' => 'in_flight',
        ];
    }

    /**
     * @return Collection<int, AiUniverseTicket>
     */
    private function queryOpenAntigravityTickets(): Collection
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            return collect();
        }

        return AiUniverseTicket::query()
            ->where('assigned_to_antigravity', true)
            ->whereNotIn('status', [
                AiUniverseTicket::STATUS_COMPLETED,
                AiUniverseTicket::STATUS_ARCHIVED,
                AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                AiUniverseTicket::STATUS_WAITING_FOR_TRESOR,
            ])
            ->get();
    }

    /**
     * @param  Collection<int, AiUniverseTicket>  $tickets
     * @return Collection<int, AiUniverseTicket>
     */
    private function sortQueueCandidates(Collection $tickets): Collection
    {
        return $tickets->sort(function (AiUniverseTicket $a, AiUniverseTicket $b): int {
            $aInFlight = $this->isTicketInFlight($a) ? 0 : 1;
            $bInFlight = $this->isTicketInFlight($b) ? 0 : 1;
            if ($aInFlight !== $bInFlight) {
                return $aInFlight <=> $bInFlight;
            }

            $prioMap = ['critical' => 0, 'high' => 0, 'normal' => 1, 'low' => 2];
            $aPrio = $prioMap[$a->priority ?? 'normal'] ?? 1;
            $bPrio = $prioMap[$b->priority ?? 'normal'] ?? 1;
            if ($aPrio !== $bPrio) {
                return $aPrio <=> $bPrio;
            }

            return strcmp((string) $a->created_at, (string) $b->created_at) ?: strcmp((string) $a->id, (string) $b->id);
        })->values();
    }

    private function isTicketInFlight(AiUniverseTicket $ticket): bool
    {
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];

        return ($payload['queue_state'] ?? '') === 'in_flight';
    }

    private function tripCircuitBreaker(AiUniverseTicket $ticket, int $attempts): void
    {
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $payload['queue_state'] = 'circuit_breaker';
        $payload['circuit_breaker_at'] = now()->toIso8601String();
        $payload['pending_question'] = "Automatische Sicherheits-Pause nach {$attempts} Zustellversuchen ohne Abschluss. Bitte kurz prüfen oder per Go erneut freigeben.";

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'system',
            'sender_name' => 'Ticket-Autopilot',
            'channel' => 'system_sentinel',
            'content' => "**Sicherheits-Pause (`{$attempts}/" . self::MAX_AUTO_ATTEMPTS . "` Versuche):** Dieses Ticket wurde {$attempts}x angestoßen, ohne dass ein sauberer Abschluss gemeldet wurde. Damit keine Endlosschleife entsteht, steht es jetzt auf **Wartet auf mich** und die Queue arbeitet nahtlos die übrigen Tickets weiter ab.",
        ]);

        $ticket->update([
            'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            'priority' => 'high',
            'action_payload' => $payload,
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);
    }

    private function enqueueMissingInboxItemForTicket(AiUniverseTicket $ticket): ?string
    {
        try {
            $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
            $execMode = strtolower(trim((string) ($payload['execution_mode'] ?? 'direct')));
            if (!in_array($execMode, ['concept', 'direct'], true)) {
                $execMode = 'direct';
            }

            $firstMessage = (string) ($ticket->messages()->orderBy('created_at')->value('content') ?? $ticket->title);
            $mandateSuffix = $execMode === 'direct'
                ? "\n\nAutopilot-Mandat (Lösen + Härten in einem Atemzug): Behebe nicht nur das akute Symptom, sondern härte den betroffenen Code-Pfad inkl. Nachbarstellen dauerhaft gegen Wiederauftreten ab und verankere einen automatisierten Regressions-Test."
                : "\n\nKonzept-Mandat: Plane neben der Fachlösung direkt die präventive Code-Härtung und den Regressions-Schutz mit ein.";

            $targetChannel = $this->resolveQueueTargetChannel($ticket);

            $enqueued = $this->inboxService->enqueueMessage([
                'channel' => $targetChannel,
                'title' => "[{$ticket->ticket_number}] {$ticket->title}",
                'body' => Str::limit($firstMessage, 3700) . $mandateSuffix,
                'execution_mode' => $execMode,
                'source' => 'ticket_autopilot',
                'skip_universe_mirror' => true,
            ]);

            $agId = trim((string) ($enqueued['item']['id'] ?? ''));
            if ($agId === '') {
                return null;
            }

            $payload['ag_item_id'] = $agId;
            $payload['ag_channel'] = $targetChannel;
            $payload['execution_mode'] = $execMode;
            $ticket->update(['action_payload' => $payload]);

            return $agId;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveQueueTargetChannel(AiUniverseTicket $ticket): string
    {
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $normalized = $this->inboxService->normalizeChannel($payload['ag_channel'] ?? self::WORKER_CHANNEL);
        $effective = $this->inboxService->resolveEffectiveChannel(
            $normalized,
            (string) ($ticket->title ?? ''),
            ''
        );

        return in_array($effective, ['shop', 'buchhaltung', 'android', 'minipc', 'invest'], true)
            ? $effective
            : self::WORKER_CHANNEL;
    }

    /**
     * @return array<int, string>
     */
    private function extractLinkedAgIds(AiUniverseTicket $ticket): array
    {
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $ids = [];

        if (!empty($payload['ag_item_id'])) {
            $ids[] = (string) $payload['ag_item_id'];
        }
        if (!empty($payload['previous_concept_ag_id'])) {
            $ids[] = (string) $payload['previous_concept_ag_id'];
        }
        if (!empty($payload['ag_history_ids']) && is_array($payload['ag_history_ids'])) {
            foreach ($payload['ag_history_ids'] as $hid) {
                if (is_scalar($hid) && trim((string) $hid) !== '') {
                    $ids[] = trim((string) $hid);
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function buildQueueWorkerPrompt(
        AiUniverseTicket $ticket,
        string $agId,
        string $execMode,
        int $attempt,
        int $openCount,
        bool $isRecoveryRetry
    ): string {
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $modeLabel = $execMode === 'concept'
            ? 'Erst Konzept inkl. Präventiv-Härtung erstellen unter /admin/tickets?view=files&ftype=concept (danach warte auf Go)'
            : 'Direkt lokal lösen + Code-Pfad in einem Atemzug härten + Regressions-Test verankern';

        $headerBadge = $isRecoveryRetry
            ? "[TICKET-AUTOPILOT AUTO-RECOVERY VERSUCH {$attempt}/" . self::MAX_AUTO_ATTEMPTS . " | TICKET: {$ticket->ticket_number} | ID: {$agId}]"
            : "[TICKET-AUTOPILOT QUEUE | TICKET: {$ticket->ticket_number} | ID: {$agId} | OFFENE TICKETS GESAMT: {$openCount}]";

        $occurrences = max(1, (int) ($ticket->occurrence_count ?? $payload['occurrences_count'] ?? 1));
        $category = isset($payload['category']) && is_scalar($payload['category']) ? trim((string) $payload['category']) : '';
        $file = isset($payload['file']) && is_scalar($payload['file']) ? trim((string) $payload['file']) : '';
        $line = isset($payload['line']) && is_scalar($payload['line']) ? trim((string) $payload['line']) : '';
        $exceptionClass = isset($payload['exception_class']) && is_scalar($payload['exception_class']) ? trim((string) $payload['exception_class']) : '';

        $lines = [
            $headerBadge,
            "Modus: {$modeLabel}",
            "Titel: {$ticket->title}",
            'Umgebung & Typ: ' . ($ticket->environment_tier ?: 'LOCAL') . ' | ' . ($ticket->ticket_type ?: 'antigravity_dev') . " | Auftreten: {$occurrences}x",
        ];

        if ($file !== '') {
            $loc = $file . ($line !== '' ? ':' . $line : '');
            $lines[] = "Fehlerort: {$loc}" . ($exceptionClass !== '' ? " ({$exceptionClass})" : '');
        } elseif ($exceptionClass !== '') {
            $lines[] = "Exception: {$exceptionClass}";
        }

        if ($category !== '') {
            $lines[] = "Fehler-Kategorie: {$category}";
        }

        if (!empty($ticket->error_fingerprint)) {
            $lines[] = "Fehler-Fingerprint: {$ticket->error_fingerprint}";
        }

        if ($occurrences > 1) {
            $lines[] = "Wiederholungs-Warnung: Dieser Vorfall trat bereits {$occurrences}x auf. Analysiere zwingend, warum bestehende Guards diesen Pfad durchgelassen haben, und verriegele die Ursache dauerhaft.";
        }

        $priorHistory = $this->resolveRelatedBrainHistory($ticket, $file);
        if ($priorHistory !== []) {
            $lines[] = '';
            $lines[] = 'Frühere Ticket-Historie zum selben Modul (Wiederholungs-Schutz):';
            foreach ($priorHistory as $histLine) {
                $lines[] = '- ' . $histLine;
            }
        }

        $recentMessages = $ticket->messages()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->reverse()
            ->map(static fn (AiUniverseTicketMessage $m): string => '[' . $m->sender_name . ']: ' . Str::limit(trim((string) $m->content), 900))
            ->filter(static fn (string $l): bool => $l !== '')
            ->values()
            ->all();

        if ($recentMessages !== []) {
            $lines[] = '';
            $lines[] = 'Chronologischer Ticket-Verlauf:';
            foreach ($recentMessages as $rm) {
                $lines[] = $rm;
            }
        }

        $lines[] = '';
        if ($execMode === 'concept') {
            $lines[] = 'Verbindliche Konzept- & Härtungs-Pflicht:';
            $lines[] = '1. Prüfe dublettenfrei unter /admin/tickets?view=files&ftype=concept, ob bereits ein Konzept zum Thema existiert, und lege das Konzept mit Status open an (bzw. aktualisiere es).';
            $lines[] = '2. Plane im Konzept neben der Fachlösung zwingend die präventive Code-Härtung (Randfälle, Null-/Typ-Guards, Nachbarstellen, Idempotenz) sowie den automatisierten Regressions-Test ein.';
            $lines[] = "3. Melde das Konzept nach Ablage via: & 'C:\\Users\\konta\\AppData\\Local\\Funkenwerk\\SentinelCli\\FunkenwerkMcp.exe' --ag-inbox-complete {$agId} \"Konzept inkl. Code-Härtungsplan erstellt und bereit zur Prüfung.\"";
        } else {
            $lines[] = 'Verbindliches 4-Stufen-Protokoll (Lösen + Code-Härtung in einem Atemzug):';
            $lines[] = '1. Ursachen-Fix statt Symptom-Pflaster: Behebe lokal (http://127.0.0.1, kein git push oder Stage-Deploy ohne explizite Freigabe) die echte Wurzelursache statt den Fehler nur oberflächlich zu unterdrücken.';
            $lines[] = '2. Code-Härtung an Fehlerstelle & Nachbarpfaden: Härte im selben Atemzug die betroffene Methode sowie gleichartige Nachbarstellen in derselben Klasse/Service-Schicht dauerhaft gegen Wiederauftreten ab:';
            foreach (self::buildCategoryHardeningChecklist($category, $file, $exceptionClass) as $checkLine) {
                $lines[] = '   - ' . $checkLine;
            }
            $lines[] = '3. Regressions-Schutz verankern: Ergänze oder erweitere zwingend einen gezielten PHPUnit-Test (bzw. --self-test bei C#-Komponenten), der sowohl den konkreten Auslöser als auch die neue Härtung dauerhaft abprüft, und führe ihn grün aus.';
            $lines[] = "4. Work-Until-Zero Abschluss melden: Schließe das Ticket sofort nach grüner Verifikation strukturiert ab (reine Aussagesätze ohne Fragezeichen am Ende und ohne Rückfrage-Marker) via: & 'C:\\Users\\konta\\AppData\\Local\\Funkenwerk\\SentinelCli\\FunkenwerkMcp.exe' --ag-inbox-complete {$agId} \"Ursache: <Wurzelursache> | Fix: <Behebung> | Härtung: <Code-Absicherung + Nachbarstellen> | Test: <grüner Testnachweis>\"";
            $lines[] = '5. Sobald dieser Befehl ausgeführt wird, dokumentiert das Wissensgehirn die Härtung automatisch und die Queue zieht nahtlos das nächste offene Ticket nach, bis 0 offene Tickets erreicht sind.';
        }

        return implode("\n", $lines);
    }

    /**
     * Liefert deterministische, maßgeschneiderte Code-Härtungs-Leitplanken passend zur Fehlerklasse (0 KI-Tokens).
     *
     * @return array<int, string>
     */
    public static function buildCategoryHardeningChecklist(
        ?string $category = null,
        ?string $file = null,
        ?string $exceptionClass = null
    ): array {
        $cat = strtolower(trim((string) $category));
        $fileLower = strtolower(str_replace('\\', '/', trim((string) $file)));
        $excLower = strtolower(trim((string) $exceptionClass));

        $checks = [
            'Strikte Null-, Typ-, Array-Key- und Wertebereichs-Guards setzen und alle direkten Nachbarmethoden derselben Datei auf identische ungeschützte Zugriffe mithärten.',
        ];

        if (
            $cat === 'php_deprecation_notice'
            || str_contains($excLower, 'typeerror')
            || str_contains($excLower, 'argumentcounterror')
        ) {
            $checks[] = 'PHP 8.4+ Typ-Sicherheit erzwingen: Keine ungeprüften null/mixed-Übergaben an String-, Array- oder Mathe-Funktionen; Rückgabewerte externer Helfer defensiv normalisieren.';
        } elseif (
            in_array($cat, ['redis_connectivity_or_dns', 'network_dns_or_socket_eagain', 'mail_smtp_or_imap', 'bridge_health_unreachable', 'gemini_ai_or_live_bridge'], true)
            || str_contains($fileLower, 'streamfactory')
            || str_contains($excLower, 'connectionexception')
            || str_contains($excLower, 'predis')
        ) {
            $checks[] = 'Netzwerk-, DNS-, Stream- und I/O-Resilienz: Verbindungs- und Lese-Timeouts eng begrenzen, transiente DNS-/Socket-Aussetzer kontrolliert abfangen (Retry oder sauberer lokaler Fallback) und Streams/Handles per try/finally schließen.';
        } elseif (
            in_array($cat, ['database_query_or_connection', 'mysql_transient_dns_or_socket'], true)
            || str_contains($excLower, 'queryexception')
            || str_contains($excLower, 'pdoexception')
        ) {
            $checks[] = 'Datenbank-Härtung: Spalten-/Längen-Grenzen, Nullability, Foreign-Keys, Transaktions-Atomarität und idempotente Upserts gegen Doppel- oder Parallelaufrufe absichern.';
        } elseif (
            $cat === 'frontend_alpine_or_js_reference'
            || str_contains($fileLower, 'resources/views/')
            || str_contains($fileLower, 'app/livewire/')
        ) {
            $checks[] = 'Livewire 3 & Alpine.js Stabilität: Alle x-data-Properties vorab definieren, kein Inline-try/catch in x-init, sensible IDs mit #[Locked] schützen und Mobile-/Light-/Darkmode-Konsistenz wahren.';
        } elseif ($cat === 'php_fatal_or_oom') {
            $checks[] = 'Speicher- & Laufzeit-Schutz: Unbegrenzte Abfragen auf chunkById/cursor/limit umstellen, Dateigrößen vor dem Laden ins RAM prüfen und Schleifen hart begrenzen.';
        } elseif (in_array($cat, ['security_package_or_guardrail_audit', 'blueprint_guardrail_or_compliance'], true)) {
            $checks[] = 'Security- & Rollen-Isolation: Auth-Guards, Rollen-Grenzen (influencer / tax_advisor / admin), Pfad-Validierung und ModelBlueprint-Policies an allen Geschwister-Endpunkten gegenprüfen.';
        } else {
            $checks[] = 'Idempotenz, Race-Condition-Schutz und saubere Fehlerbehandlung für ungültige Eingaben, leere Datenbestände oder fehlende Dateien auf Disk sicherstellen.';
        }

        return $checks;
    }

    /**
     * Sucht in < 2 ms (0 KI-Tokens) nach früheren abgeschlossenen Ticket-Berichten zum selben Dateinamen/Modul,
     * damit der Autopilot Wiederholungsfehler erkennt und strukturell abstellt.
     *
     * @return array<int, string>
     */
    private function resolveRelatedBrainHistory(AiUniverseTicket $ticket, string $file): array
    {
        if (!Schema::hasTable('ai_knowledge_bases')) {
            return [];
        }

        $needle = '';
        if ($file !== '') {
            $base = basename(str_replace('\\', '/', $file));
            if (mb_strlen($base) >= 5 && !in_array(strtolower($base), ['index.php', 'controller.php', 'model.php'], true)) {
                $needle = $base;
            }
        }

        if ($needle === '') {
            $tokens = CentralBrainService::extractSignificantTokens((string) $ticket->title, 2);
            $needle = $tokens[0] ?? '';
        }

        if (mb_strlen($needle) < 4) {
            return [];
        }

        try {
            $esc = addcslashes($needle, '%_\\');
            $selfSource = 'ticket:' . $ticket->ticket_number;

            return AiKnowledgeBase::query()
                ->where('source_path', 'like', 'ticket:%')
                ->where('source_path', '!=', $selfSource)
                ->where(function ($q) use ($esc) {
                    $q->where('title', 'like', '%' . $esc . '%')
                        ->orWhere('content', 'like', '%' . $esc . '%');
                })
                ->latest('updated_at')
                ->limit(2)
                ->get(['title', 'content'])
                ->map(static function (AiKnowledgeBase $kb) use ($needle): string {
                    $snippet = CentralBrainService::extractRelevantSnippet((string) $kb->content, [$needle], 180);
                    return trim($kb->title . ': ' . $snippet);
                })
                ->filter(static fn (string $line): bool => $line !== '')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}

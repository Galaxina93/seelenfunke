<?php

namespace App\Console\Commands\System;

use App\Services\AI\AntigravityTicketQueueService;
use Illuminate\Console\Command;

class AntigravityTicketQueueCommand extends Command
{
    protected $signature = 'system:ag-ticket-queue
        {--status : Gibt den aktuellen Status der seriellen Work-Until-Zero Queue als JSON aus}
        {--reconcile : Synchronisiert ai_universe_tickets und antigravity_direct_inbox.json bidirektional}
        {--claim-next : Ermittelt seriell (MaxInFlight=1) das naechste zu bearbeitende Ticket inkl. Watchdog & 3x-Bremse}
        {--ack-dispatched= : Bestaetigt den erfolgreichen agentapi-Versand fuer Ticket-Nummer oder ag_id (Phase 2)}
        {--attempt= : Optionaler Versuchszaehler fuer --ack-dispatched}
        {--idle-timeout=180 : Sekunden ohne Aktivitaet im Worker-Chat, ab denen ein In-Flight-Ticket erneut angestossen wird}
        {--chat-idle-seconds=-1 : Gemessene Inaktivitaet der Ziel-Konversation (transcript.jsonl) in Sekunden}';

    protected $description = 'Serielle Work-Until-Zero Ticket-Queue (FIFO, MaxInFlight=1, 3-Minuten-Watchdog & 3x-Schutzbremse) fuer Antigravity';

    public function handle(AntigravityTicketQueueService $queue): int
    {
        try {
            $ackTarget = trim((string) $this->option('ack-dispatched'));
            if ($ackTarget !== '') {
                $attemptOpt = $this->option('attempt');
                $attempt = ($attemptOpt !== null && $attemptOpt !== '') ? (int) $attemptOpt : null;

                return $this->emitJson($queue->acknowledgeDispatched($ackTarget, $attempt));
            }

            if ((bool) $this->option('claim-next')) {
                $idleTimeout = max(30, (int) $this->option('idle-timeout'));
                $chatIdle = (int) $this->option('chat-idle-seconds');

                return $this->emitJson($queue->claimNextDispatchableTicket($idleTimeout, $chatIdle));
            }

            if ((bool) $this->option('reconcile')) {
                return $this->emitJson([
                    'status' => 'success',
                    'reconciled' => $queue->reconcileQueueState(),
                ]);
            }

            return $this->emitJson($queue->getQueueStatus());
        } catch (\Throwable $e) {
            $this->line((string) json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitJson(array $payload): int
    {
        $this->line((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\AI;

use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use Illuminate\Support\Str;

class TicketAutoCompactService
{
    public const COMPACT_THRESHOLD = 6;
    public const KEEP_RECENT_COUNT = 3;

    /**
     * Prüft nach jeder Nachricht automatisch, ob das Ticket komprimiert werden soll (> 6 Nachrichten).
     * Verdichtet ältere Nachrichten in `ticket.compact_context`, während alle Nachrichten im UI
     * chronologisch zu 100 % sichtbar bleiben!
     */
    public static function compactIfNeeded(AiUniverseTicket $ticket): bool
    {
        $allMessages = AiUniverseTicketMessage::where('ticket_id', $ticket->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $totalCount = $allMessages->count();
        if ($ticket->messages_count !== $totalCount) {
            $ticket->messages_count = $totalCount;
        }

        if ($totalCount <= self::COMPACT_THRESHOLD) {
            if ($ticket->isDirty('messages_count')) {
                $ticket->save();
            }
            return false;
        }

        $sliceCount = max(0, $totalCount - self::KEEP_RECENT_COUNT);
        $olderMessages = $allMessages->slice(0, $sliceCount);
        $uncompactedOlder = $olderMessages->where('is_compacted', false);

        if ($uncompactedOlder->isEmpty()) {
            if ($ticket->isDirty('messages_count')) {
                $ticket->save();
            }
            return false;
        }

        $lines = [];
        if (!empty($ticket->compact_context)) {
            $lines[] = trim((string) $ticket->compact_context);
        }

        foreach ($uncompactedOlder as $msg) {
            $cleanBody = trim(preg_replace('/\s+/', ' ', strip_tags((string) $msg->content)) ?? '');
            $snippet = Str::limit($cleanBody, 180);
            $toolsNote = !empty($msg->executed_tools) ? ' [Tools: ' . implode(', ', array_slice((array) $msg->executed_tools, 0, 4)) . ']' : '';
            $lines[] = "- {$msg->sender_name}{$toolsNote}: {$snippet}";
        }

        $mergedSummary = implode("\n", $lines);
        // Halte den komprimierten Prompt-Block dauerhaft schlank (maximal 1.400 Zeichen)
        if (mb_strlen($mergedSummary) > 1400) {
            $mergedSummary = "...\n" . mb_substr($mergedSummary, -1380);
        }

        AiUniverseTicketMessage::whereIn('id', $uncompactedOlder->pluck('id')->all())
            ->update(['is_compacted' => true]);

        $ticket->update([
            'messages_count' => $totalCount,
            'compact_context' => $mergedSummary,
            'compacted_at' => now(),
        ]);

        return true;
    }

    /**
     * Liefert den extrem tokenarmen Gesprächskontext für den nächsten KI-Aufruf:
     * Komprimierter Fakten-Block + ausschließlich die letzten 3 frischen Nachrichten.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public static function buildPromptHistory(AiUniverseTicket $ticket): array
    {
        $history = [];

        if (!empty($ticket->compact_context)) {
            $history[] = [
                'role' => 'system',
                'content' => "[AUTO-COMPACT TICKET-ZUSAMMENFASSUNG ({$ticket->ticket_number})]:\n" . $ticket->compact_context,
            ];
        }

        $recent = AiUniverseTicketMessage::where('ticket_id', $ticket->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::KEEP_RECENT_COUNT)
            ->get()
            ->reverse();

        foreach ($recent as $msg) {
            $history[] = [
                'role' => $msg->sender_role === 'alina' ? 'user' : 'assistant',
                'content' => "[{$msg->sender_name}]: " . Str::limit((string) $msg->content, 900),
            ];
        }

        return $history;
    }
}

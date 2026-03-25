<?php

namespace App\Services\AI\Functions;

use App\Models\Ticket;

trait AiSupportFuncs
{
    public static function getAiSupportFuncsSchema(): array
    {
        return [

            [
                'name' => 'ticket_get_all',
                'description' => 'Gibt alle offenen Kundensupport-Tickets zurück. Nutze dies, wenn nach Support, Kundenmeldungen oder Tickets gefragt wird. Stichworte: Zeig mir die Tickets, Gibt es Support Anfragen, offene Kundentickets, Beschwerden, Anfragen von Kunden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTickets']
            ]
        ];
    }



    /* Ticket Methods */
    public static function executeGetTickets(array $args)
    {
        try {
            $query = Ticket::where('status', '!=', 'closed');
            $count = $query->count();
            $tickets = $query->orderBy('created_at', 'desc')->take(5)->get();

            if ($tickets->isEmpty()) {
                return ['status' => 'success', 'message' => 'Es gibt aktuell keine offenen Support-Tickets. Alles super!'];
            }

            $formatted = [];
            foreach ($tickets as $t) {
                $formatted[] = [
                    'id' => $t->id,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'date' => $t->created_at->format('d.m.Y H:i')
                ];
            }

            return ['status' => 'success', 'open_tickets_count' => $count, 'tickets' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Tickets konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }
}

<?php

namespace App\Services\AI\Functions;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;

trait AiScoutFuncs
{
    public static function getAiScoutFuncsSchema(): array
    {
        return [

            [
                'name' => 'mission_get_current',
                'description' => 'Gibt den ultimativen nächsten Befehl oder Mission zurück, worauf sich der User fokussieren soll. Stichworte: Was ist mein Fokus, was soll ich als nächstes angreifen, meine Mission, Tagesziel.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetCurrentMission']
            ],
            [
                'name' => 'gamification_get_leaderboard',
                'description' => 'Zeigt die Gamification-Highscore-Liste der Kunden. Stichworte: Wer hat die meisten XP, Leaderboard, Level der Kunden, Punkte Rangliste.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetGamificationLeaderboard']
            ],
            [
                'name' => 'customer_search',
                'description' => 'Sucht nach einem Kunden im System anhand Name oder Email, und liefert Kunden-Lifetime-Value, Order-Anzahl etc. Stichworte: Suche Kunde, Details zu Frau Schmidt, Wer ist dieser Käufer, Kundenstammblatt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Vorname, Nachname oder Email des Kunden.'
                        ]
                    ],
                    'required' => ['search_query']
                ],
                'callable' => [self::class, 'executeSearchCustomers']
            ],

        ];
    }


    public static function executeGetCurrentMission(array $args)
    {
        try {
            $botService = app(\App\Services\AI\AiSupportService::class);
            $missionData = $botService->getUltimateCommand();

            return [
                'status' => 'success',
                'mission' => $missionData
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to resolve mission: ' . $e->getMessage()
            ];
        }
    }

    public static function executeGetGamificationLeaderboard(array $args)
    {
        try {
            $leaders = CustomerGamification::with('customer')
                ->orderBy('total_xp', 'desc')
                ->take(5)->get();

            if ($leaders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Noch keine Spieler in der Gamification-Tabelle.'];
            }

            $formatted = [];
            foreach ($leaders as $idx => $l) {
                $cName = $l->customer ? ($l->customer->first_name . ' ' . substr($l->customer->last_name, 0, 1) . '.') : 'Unbekannt';
                $formatted[] = [
                    'rank' => $idx + 1,
                    'customer' => $cName,
                    'level' => $l->current_level,
                    'xp' => number_format($l->total_xp, 0, ',', '.') . ' XP',
                    'title' => $l->title ?? 'Novize'
                ];
            }

            return ['status' => 'success', 'leaderboard' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Level-Statistiken konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }

    public static function executeSearchCustomers(array $args)
    {
        try {
            if (empty($args['search_query'])) return ['status' => 'error', 'message' => 'Suchbegriff fehlt.'];
            $term = $args['search_query'];

            $customers = Customer::where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->take(3)->get();

            if ($customers->isEmpty()) {
                return ['status' => 'success', 'message' => "Kunde '$term' nicht gefunden."];
            }

            $formatted = [];
            foreach ($customers as $c) {
                $orderCount = \App\Models\Order\OrderOrder::where('customer_id', $c->id)->count();
                $spentCents = \App\Models\Order\OrderOrder::where('customer_id', $c->id)->where('status', 'completed')->sum('total_amount');
                
                $formatted[] = [
                    'name' => $c->first_name . ' ' . $c->last_name,
                    'email' => $c->email,
                    'registered_since' => $c->created_at ? $c->created_at->format('d.m.Y') : '-',
                    'total_orders' => $orderCount,
                    'total_spent' => number_format($spentCents / 100, 2, ',', '.') . ' €'
                ];
            }

            return ['status' => 'success', 'customers' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Kundensuche fehlgeschlagen: ' . $e->getMessage()];
        }
    }


}

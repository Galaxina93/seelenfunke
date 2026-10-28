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
        if (\App\Services\AI\AiAuthHelper::check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            return ['status' => 'error', 'message' => 'Zugriff verweigert: Die CEO-Mission ist nur für Administratoren abrufbar.'];
        }

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
        if (\App\Services\AI\AiAuthHelper::check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return ['status' => 'error', 'message' => 'Zugriff verweigert: Keine Berechtigung für Kunden-Rankings.'];
        }

        try {
            $leaders = CustomerGamification::with('customer.profile')
                ->where('is_active', true)
                ->where('ranking_opt_in', true)
                ->orderBy('level', 'desc')
                ->orderBy('funken_total_earned', 'desc')
                ->take(5)->get();

            if ($leaders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Noch keine Spieler in der Gamification-Tabelle.'];
            }

            $gameService = app(\App\Services\Gamification\GamificationService::class);
            $formatted = [];
            foreach ($leaders as $idx => $l) {
                $hasConsent = \App\Services\AI\AiAuthHelper::hasCustomerAiConsent($l->customer, $l->customer_id);
                $rawName = $l->customer ? ($l->customer->first_name . ' ' . substr((string) $l->customer->last_name, 0, 1) . '.') : '';
                $cName = \App\Services\AI\AiAuthHelper::sanitizeCustomerName($rawName, $hasConsent);
                $formatted[] = [
                    'rank' => $idx + 1,
                    'customer' => $cName,
                    'gdpr_ai_consent' => $hasConsent,
                    'level' => $l->level,
                    'funken' => number_format((int) $l->funken_total_earned, 0, ',', '.') . ' Funken',
                    'title' => $gameService->resolveActiveRankName($l) ?? 'Kein Rang'
                ];
            }

            return ['status' => 'success', 'leaderboard' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Level-Statistiken konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }

    public static function executeSearchCustomers(array $args)
    {
        if (\App\Services\AI\AiAuthHelper::check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return ['status' => 'error', 'message' => 'Zugriff verweigert: Kundensuche erfordert Mitarbeiter- oder Admin-Berechtigungen.'];
        }

        try {
            $term = trim((string)($args['search_query'] ?? ''));
            if (mb_strlen($term) < 2) {
                return ['status' => 'error', 'message' => 'Der Suchbegriff muss mindestens 2 Zeichen lang sein.'];
            }

            $escapedTerm = addcslashes($term, '%_\\');

            $customers = Customer::with('profile')
                ->where(function ($q) use ($escapedTerm) {
                    $q->where('first_name', 'like', "%{$escapedTerm}%")
                      ->orWhere('last_name', 'like', "%{$escapedTerm}%")
                      ->orWhere('email', 'like', "%{$escapedTerm}%");
                })
                ->take(5)->get();

            if ($customers->isEmpty()) {
                return ['status' => 'success', 'message' => "Kunde '$term' nicht gefunden."];
            }

            $formatted = [];
            foreach ($customers as $c) {
                $hasConsent = \App\Services\AI\AiAuthHelper::hasCustomerAiConsent($c);
                $orders = \App\Models\Order\OrderOrder::where('customer_id', $c->id)
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get(['order_number', 'status', 'payment_status', 'total_price', 'created_at']);

                $orderCount = \App\Models\Order\OrderOrder::where('customer_id', $c->id)->count();
                $spentCents = (int) \App\Models\Order\OrderOrder::where('customer_id', $c->id)
                    ->where(function($q) {
                        $q->where('status', 'completed')
                          ->orWhere('payment_status', 'paid');
                    })
                    ->sum('total_price');
                
                $fullName = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: 'Kunde';

                $formatted[] = [
                    'name' => \App\Services\AI\AiAuthHelper::sanitizeCustomerName($fullName, $hasConsent),
                    'email' => \App\Services\AI\AiAuthHelper::sanitizeCustomerEmail($c->email, $hasConsent),
                    'gdpr_ai_consent' => $hasConsent,
                    'gdpr_notice' => $hasConsent ? null : 'Dieser Kunde hat den DSGVO-Haken für die KI-Datenverarbeitung nicht gesetzt. Personenbezogene Daten sind daher als "Anonyme Person" geschützt; sachliche Bestellinformationen sind unten abrufbar.',
                    'registered_since' => $c->created_at ? $c->created_at->format('d.m.Y') : '-',
                    'total_orders' => $orderCount,
                    'total_spent' => number_format($spentCents / 100, 2, ',', '.') . ' €',
                    'recent_orders' => $orders->map(fn($o) => [
                        'order_number' => $o->order_number,
                        'status' => $o->status,
                        'payment_status' => $o->payment_status,
                        'total' => number_format(($o->total_price ?? 0) / 100, 2, ',', '.') . ' €',
                    ])->toArray(),
                ];
            }

            return ['status' => 'success', 'customers' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Kundensuche fehlgeschlagen: ' . $e->getMessage()];
        }
    }


}

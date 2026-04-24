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
            [
                'name' => 'web_search_internet',
                'description' => 'Sucht live im Internet nach aktuellen Nachrichten, Themen oder Begriffen. Stichworte: Suche im Web, Was gibt es neues zu, Google nach, Websuche',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff für die Websuche.'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchInternet']
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

    public static function executeSearchInternet(array $args)
    {
        try {
            if (empty($args['query'])) return ['status' => 'error', 'message' => 'Suchbegriff fehlt.'];
            
            $query = urlencode($args['query']);
            $url = "https://html.duckduckgo.com/html/?q={$query}";
            
            $client = new \GuzzleHttp\Client([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/100.0.4896.127 Safari/537.36',
                    'Accept-Language' => 'de-DE,de;q=0.9',
                    'Accept' => 'text/html'
                ],
                'timeout' => 8
            ]);
            
            $response = $client->request('GET', $url);
            $html = $response->getBody()->getContents();
            
            // Extract text snippets from DuckDuckGo Lite HTML results
            preg_match_all('/<a class="result__snippet[^>]+>(.*?)<\/a>/is', $html, $matches);
            
            if (empty($matches[1])) {
                // Try Wikipedia fallback if DuckDuckGo blocks us
                $wikiUrl = "https://de.wikipedia.org/w/api.php?action=query&list=search&srsearch={$query}&utf8=&format=json";
                $wikiResponse = $client->request('GET', $wikiUrl);
                $wikiData = json_decode($wikiResponse->getBody()->getContents(), true);
                
                if (!empty($wikiData['query']['search'])) {
                    $results = [];
                    foreach (array_slice($wikiData['query']['search'], 0, 3) as $item) {
                        $results[] = trim(strip_tags($item['snippet']));
                    }
                    if(!empty($results)) {
                         return ['status' => 'success', 'source' => 'Wikipedia', 'results' => $results];
                    }
                }
                return ['status' => 'success', 'message' => 'Keine brauchbaren Resultate zur Suchanfrage gefunden.'];
            }
            
            $results = [];
            foreach (array_slice($matches[1], 0, 3) as $snippet) {
                $clean = strip_tags(html_entity_decode($snippet));
                $results[] = trim($clean);
            }
            
            return ['status' => 'success', 'source' => 'Web Search', 'results' => $results];
            
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Internetzugriff fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}

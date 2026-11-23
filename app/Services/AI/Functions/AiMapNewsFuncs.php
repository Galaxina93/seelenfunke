<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait AiMapNewsFuncs
{
    public static function getAiMapNewsFuncsSchema(): array
    {
        return [
            [
                'name' => 'map_search_and_mark_news',
                'description' => 'Sucht nach aktuellen Nachrichten zu einem bestimmten globalen Ereignis (z.B. "Krieg Ukraine", "Messerangriffe") und markiert die betroffenen Orte auf der interaktiven 3D Weltkarte. Nutze dies IMMER, wenn der Nutzer fragt "Zeige mir Vorfälle auf der Map" oder ähnliche globale Ereignisse markiert haben möchte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff für die News-Suche (z.B. "Ukraine Krieg Vorfälle", "Messerangriffe Deutschland").'
                        ],
                        'language' => [
                            'type' => 'string',
                            'description' => 'Die Sprache der Nachrichten. Standard ist "de".'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeMapSearchAndMarkNews']
            ]
        ];
    }

    public static function executeMapSearchAndMarkNews(array $args): array
    {
        if (\App\Services\AI\AiAuthHelper::check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return ['status' => 'error', 'message' => 'Zugriff verweigert: Map-News sind nur für interne Mitarbeiter und Administratoren freigegeben.'];
        }

        $query = trim($args['query'] ?? '');
        $language = strtolower(trim($args['language'] ?? 'de'));

        $allowedLanguages = ['de', 'en', 'fr', 'es', 'it', 'nl', 'pl', 'ru'];
        if (!in_array($language, $allowedLanguages, true)) {
            $language = 'de';
        }

        if (empty($query)) {
            return ['status' => 'error', 'message' => 'Der Suchbegriff für die News-Suche darf nicht leer sein.'];
        }

        $query = mb_substr($query, 0, 100);
        
        $newsApiKey = config('services.newsapi.key') ?: env('NEWS_API_KEY');
        $mapboxToken = config('services.mapbox.token') ?: env('MAPBOX_TOKEN');
        $vertexEndpoint = app()->runningUnitTests()
            ? ['base_url' => '', 'token' => '', 'is_vertex' => false]
            : \App\Services\AI\VertexAiService::resolveOpenAiEndpoint();
        $openAiFallbackKey = config('openai.api_key') ?: config('services.openai.key') ?: env('OPENAI_API_KEY');

        if (empty($newsApiKey) || empty($mapboxToken) || (empty($vertexEndpoint['token']) && empty($openAiFallbackKey))) {
            return [
                'status' => 'error',
                'message' => 'Es fehlen benötigte API-Schlüssel (NEWS_API_KEY, MAPBOX_TOKEN oder Vertex/Gemini-Zugang) in der Systemkonfiguration.'
            ];
        }

        try {
            // 1. Fetch News (mit 5-Minuten Cache für identische Abfragen)
            $articles = \Illuminate\Support\Facades\Cache::remember('map_news_articles_' . md5($language . ':' . mb_strtolower($query)), 300, function () use ($query, $language, $newsApiKey) {
                $newsResponse = Http::timeout(4)->get('https://newsapi.org/v2/everything', [
                    'q' => $query,
                    'language' => $language,
                    'sortBy' => 'publishedAt',
                    'apiKey' => $newsApiKey,
                    'pageSize' => 12
                ]);
                return ($newsResponse->successful() && !empty($newsResponse->json()['articles']))
                    ? $newsResponse->json()['articles']
                    : [];
            });

            if (empty($articles)) {
                return [
                    'status' => 'success',
                    'message' => "Es wurden keine aktuellen Nachrichten zu '{$query}' gefunden, die auf der Karte markiert werden könnten."
                ];
            }
            
            // Prepare text block for LLM to extract locations
            $textForLLM = "";
            foreach (array_slice($articles, 0, 10) as $index => $article) {
                $title = mb_substr($article['title'] ?? '', 0, 150);
                $desc = mb_substr($article['description'] ?? '', 0, 250);
                $textForLLM .= "ID: {$index} | Titel: {$title} | Text: {$desc}\n";
            }

            // 2. Schneller Vertex/Gemini Flash Call (mit OpenAI Fallback) zur Orts-Extraktion
            $systemPrompt = "Du bist ein Geodaten-Extraktor. Finde in den folgenden Texten alle Vorfälle, die einen klaren Ort, eine Stadt oder ein Land nennen. Antworte AUSSCHLIESSLICH als reines JSON-Objekt im Format: {\"markers\":[{\"id\":0,\"location\":\"Stadt, Land\",\"title\":\"Kurzer 1-Satz Titel\"}]}. Ignoriere Texte ohne klaren Ort.";

            $llmBaseUrl = !empty($openAiFallbackKey) && empty($vertexEndpoint['is_vertex']) && app()->runningUnitTests()
                ? 'https://api.openai.com/v1'
                : (!empty($vertexEndpoint['token']) ? rtrim($vertexEndpoint['base_url'], '/') : 'https://api.openai.com/v1');
            $llmToken = ($llmBaseUrl === 'https://api.openai.com/v1') ? $openAiFallbackKey : $vertexEndpoint['token'];
            $llmModel = ($llmBaseUrl === 'https://api.openai.com/v1')
                ? 'gpt-4o-mini'
                : \App\Services\AI\VertexAiService::formatOpenAiModel('gemini-2.5-flash-lite', $vertexEndpoint['is_vertex']);

            $llmResponse = Http::timeout(8)->withHeaders([
                'Authorization' => 'Bearer ' . $llmToken,
                'Content-Type' => 'application/json',
            ])->post($llmBaseUrl . '/chat/completions', [
                'model' => $llmModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $textForLLM]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$llmResponse->successful()) {
                Log::warning('LLM Error in AiMapNewsFuncs', ['status' => $llmResponse->status()]);
                return [
                    'status' => 'error',
                    'message' => 'Die KI-Ortsanalyse konnte temporär nicht durchgeführt werden.'
                ];
            }

            $rawContent = (string) ($llmResponse->json()['choices'][0]['message']['content'] ?? '{}');
            $rawContent = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawContent)) ?? $rawContent);
            $llmResult = json_decode($rawContent, true);
            $extractedMarkers = is_array($llmResult['markers'] ?? null) ? array_slice($llmResult['markers'], 0, 10) : [];

            if (empty($extractedMarkers)) {
                return [
                    'status' => 'success',
                    'message' => "Habe Nachrichten gefunden, aber es gab keine konkreten Ortsangaben darin, die auf der Karte markiert werden könnten."
                ];
            }

            // 3. Paralleles Geocoding via Http::pool() + 24h Cache (statt 10x sequentieller Blockade!)
            $cachedGeo = [];
            $toFetch = [];
            foreach ($extractedMarkers as $idx => $marker) {
                $loc = trim((string) ($marker['location'] ?? ''));
                if ($loc === '') {
                    continue;
                }
                $cKey = 'mapbox_news_geo_' . md5(mb_strtolower($loc));
                $hit = \Illuminate\Support\Facades\Cache::get($cKey);
                if (is_array($hit)) {
                    $cachedGeo[$idx] = $hit;
                } else {
                    $toFetch[$idx] = ['loc' => $loc, 'key' => $cKey];
                }
            }

            if (!empty($toFetch)) {
                $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($toFetch, $mapboxToken) {
                    foreach ($toFetch as $idx => $info) {
                        $geoUrl = "https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($info['loc']) . ".json";
                        $pool->as((string) $idx)->timeout(4)->get($geoUrl, [
                            'access_token' => $mapboxToken,
                            'limit' => 1,
                        ]);
                    }
                });

                foreach ($toFetch as $idx => $info) {
                    $res = $responses[(string) $idx] ?? null;
                    if ($res instanceof \Illuminate\Http\Client\Response && $res->successful()) {
                        $geoData = $res->json();
                        if (!empty($geoData['features'][0]['center'])) {
                            $coords = [
                                'lng' => (float) $geoData['features'][0]['center'][0],
                                'lat' => (float) $geoData['features'][0]['center'][1],
                            ];
                            \Illuminate\Support\Facades\Cache::put($info['key'], $coords, 86400);
                            $cachedGeo[$idx] = $coords;
                        }
                    }
                }
            }

            $finalMarkers = [];
            foreach ($extractedMarkers as $idx => $marker) {
                if (!isset($cachedGeo[$idx])) {
                    continue;
                }
                $loc = trim((string) ($marker['location'] ?? ''));
                $id = $marker['id'] ?? -1;
                $url = ($id >= 0 && isset($articles[$id])) ? $articles[$id]['url'] : null;
                if (is_string($url) && !preg_match('/^https?:\/\//i', trim($url))) {
                    $url = null;
                }

                $finalMarkers[] = [
                    'lat' => $cachedGeo[$idx]['lat'],
                    'lng' => $cachedGeo[$idx]['lng'],
                    'title' => (string) ($marker['title'] ?? 'Ereignis'),
                    'location_name' => $loc,
                    'url' => $url
                ];
            }

            $count = count($finalMarkers);
            if ($count === 0) {
                 return [
                    'status' => 'success',
                    'message' => "Orte extrahiert, aber Geocoding (Mapbox) ist für keinen der Orte geglückt."
                ];
            }

            $lngs = array_column($finalMarkers, 'lng');
            $lats = array_column($finalMarkers, 'lat');
            $bbox = [min($lngs), min($lats), max($lngs), max($lats)];

            // 4. Frontend Event
            return [
                'status' => 'success',
                'message' => "Es wurden {$count} Vorfälle auf der Map gefunden und markiert.",
                '_frontend_events' => [
                    [
                        'name' => 'map-mark-news-events',
                        'detail' => [
                            'query' => $query,
                            'markers' => $finalMarkers,
                            'bbox' => $bbox,
                        ]
                    ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('AiMapNewsFuncs Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Interner Fehler bei der Verarbeitung der Map-News.'
            ];
        }
    }
}

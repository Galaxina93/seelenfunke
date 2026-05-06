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
                            'description' => 'Die Sprache der Nachrichten. Standard ist "de".',
                            'default' => 'de'
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
        $query = $args['query'] ?? '';
        $language = $args['language'] ?? 'de';
        
        $newsApiKey = env('NEWS_API_KEY');
        $openAiKey = env('OPENAI_API_KEY');
        $mapboxToken = env('MAPBOX_TOKEN');

        if (empty($newsApiKey) || empty($openAiKey) || empty($mapboxToken)) {
            return [
                'status' => 'error',
                'message' => 'Es fehlen benötigte API-Schlüssel (NEWS_API_KEY, OPENAI_API_KEY, MAPBOX_TOKEN) in der .env Datei. Bitte den Nutzer informieren.'
            ];
        }

        try {
            // 1. Fetch News
            $newsResponse = Http::get('https://newsapi.org/v2/everything', [
                'q' => $query,
                'language' => $language,
                'sortBy' => 'publishedAt',
                'apiKey' => $newsApiKey,
                'pageSize' => 20
            ]);

            if (!$newsResponse->successful() || empty($newsResponse->json()['articles'])) {
                return [
                    'status' => 'success',
                    'message' => "Es wurden keine aktuellen Nachrichten zu '{$query}' gefunden, die auf der Karte markiert werden könnten."
                ];
            }

            $articles = $newsResponse->json()['articles'];
            
            // Prepare text block for LLM to extract locations
            $textForLLM = "";
            foreach ($articles as $index => $article) {
                $title = $article['title'] ?? '';
                $desc = $article['description'] ?? '';
                $textForLLM .= "ID: {$index} | Titel: {$title} | Text: {$desc}\n";
            }

            // 2. Token-saving LLM call to extract locations
            $systemPrompt = "Du bist ein Geodaten-Extraktor. Finde in den folgenden Texten alle Vorfälle, die einen klaren Ort, eine Stadt oder Adresse nennen. 
Antworte AUSSCHLIESSLICH im JSON-Format. Ignoriere Texte ohne klaren Ort.
Format:
{
  \"markers\": [
    {
      \"id\": 0,
      \"location\": \"(gefundener Ort, z.B. 'Solingen, Deutschland')\",
      \"title\": \"(Kurzer 1-Satz Titel des Vorfalls)\"
    }
  ]
}";

            $openaiResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openAiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $textForLLM]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$openaiResponse->successful()) {
                throw new \Exception("OpenAI API Error: " . $openaiResponse->body());
            }

            $llmResult = json_decode($openaiResponse->json()['choices'][0]['message']['content'], true);
            $extractedMarkers = $llmResult['markers'] ?? [];

            if (empty($extractedMarkers)) {
                return [
                    'status' => 'success',
                    'message' => "Habe Nachrichten gefunden, aber es gab keine konkreten Ortsangaben darin, die auf der Karte markiert werden könnten."
                ];
            }

            // 3. Geocoding
            $finalMarkers = [];
            foreach ($extractedMarkers as $marker) {
                $loc = $marker['location'] ?? '';
                if (empty($loc)) continue;

                $geoUrl = "https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($loc) . ".json";
                $geoResponse = Http::get($geoUrl, [
                    'access_token' => $mapboxToken,
                    'limit' => 1
                ]);

                if ($geoResponse->successful()) {
                    $geoData = $geoResponse->json();
                    if (!empty($geoData['features'])) {
                        $lng = $geoData['features'][0]['center'][0];
                        $lat = $geoData['features'][0]['center'][1];
                        
                        $id = $marker['id'] ?? -1;
                        $url = ($id >= 0 && isset($articles[$id])) ? $articles[$id]['url'] : null;

                        $finalMarkers[] = [
                            'lat' => $lat,
                            'lng' => $lng,
                            'title' => $marker['title'] ?? 'Ereignis',
                            'location_name' => $loc,
                            'url' => $url
                        ];
                    }
                }
            }

            $count = count($finalMarkers);
            if ($count === 0) {
                 return [
                    'status' => 'success',
                    'message' => "Orte extrahiert, aber Geocoding (Mapbox) ist für keinen der Orte geglückt."
                ];
            }

            // 4. Frontend Event
            return [
                'status' => 'success',
                'message' => "Es wurden {$count} Vorfälle auf der Map gefunden und markiert.",
                '_frontend_events' => [
                    [
                        'name' => 'map-mark-news-events',
                        'detail' => [
                            'query' => $query,
                            'markers' => $finalMarkers
                        ]
                    ]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('AiMapNewsFuncs Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Interner Fehler bei der Verarbeitung der Map-News: ' . $e->getMessage()
            ];
        }
    }
}

<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait AiNewsFuncs
{
    /**
     * Define the schema for News tools
     */
    public static function getAiNewsFuncsSchema(): array
    {
        return [
            [
                'name' => 'search_global_news',
                'description' => 'Sucht nach aktuellen Nachrichten und Breaking News zu einem bestimmten Thema, Land oder Ort. Verwende dies, wenn der Nutzer nach der aktuellen Lage (z.B. Krieg, Wahlen, Katastrophen, Personen) fragt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff oder Ort, zu dem Nachrichten gesucht werden sollen (z.B. "Ukraine", "Berlin", "Wirtschaft").'
                        ],
                        'language' => [
                            'type' => 'string',
                            'description' => 'Die Sprache der Nachrichten. Standard ist "de". Mögliche Werte: "de", "en", "es", "fr", etc.',
                            'default' => 'de'
                        ],
                        'sortBy' => [
                            'type' => 'string',
                            'description' => 'Sortierung der Ergebnisse. Mögliche Werte: "publishedAt" (neueste), "relevancy" (relevanteste), "popularity" (beliebteste).',
                            'default' => 'publishedAt'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'searchGlobalNews']
            ],
            [
                'name' => 'news_hide_panel',
                'description' => 'Versteckt das holografische News-Panel wieder aus der Ansicht des Nutzers. Nutze dies, wenn der Nutzer fertig mit den Nachrichten ist oder explizit danach fragt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Optionaler Grund für das Verstecken.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeNewsHidePanel']
            ]
        ];
    }

    /**
     * Search global news via NewsAPI
     */
    public static function searchGlobalNews(array $args): array
    {
        $query = $args['query'] ?? '';
        $language = $args['language'] ?? 'de';
        $sortBy = $args['sortBy'] ?? 'publishedAt';
        
        $apiKey = env('NEWS_API_KEY');
        
        if (empty($apiKey)) {
            return [
                'status' => 'error',
                'message' => 'Die News API (newsapi.org) ist nicht konfiguriert. Der NEWS_API_KEY fehlt in der .env Datei. Bitte weise den Nutzer darauf hin.'
            ];
        }

        try {
            $response = Http::get('https://newsapi.org/v2/everything', [
                'q' => $query,
                'language' => $language,
                'sortBy' => $sortBy,
                'apiKey' => $apiKey,
                'pageSize' => 5 // Begrenze auf die 5 wichtigsten Artikel
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (empty($data['articles'])) {
                    return [
                        'status' => 'success',
                        'message' => "Es wurden keine aktuellen Nachrichten zu '{$query}' gefunden."
                    ];
                }

                $articles = array_map(function ($article) {
                    return [
                        'title' => $article['title'] ?? 'Kein Titel',
                        'description' => $article['description'] ?? 'Keine Beschreibung verfügbar.',
                        'source' => $article['source']['name'] ?? 'Unbekannte Quelle',
                        'publishedAt' => $article['publishedAt'] ?? '',
                        'url' => $article['url'] ?? ''
                    ];
                }, $data['articles']);

                // Sende optional ein Event ans Frontend, falls ein News-Panel aufklappen soll
                if (function_exists('broadcast') && class_exists('\App\Events\AiTriggerEvent')) {
                    broadcast(new \App\Events\AiTriggerEvent([
                        'action' => 'show_news_panel',
                        'data' => [
                            'query' => $query,
                            'articles' => $articles
                        ]
                    ]));
                }

                return [
                    'status' => 'success',
                    'query' => $query,
                    'articles' => $articles,
                    'note' => 'Erstelle eine kurze, prägnante Zusammenfassung dieser Nachrichten für den Nutzer.'
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Fehler beim Abruf der Nachrichten: ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('News API Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Interner Fehler beim Abruf der News: ' . $e->getMessage()
            ];
        }
    }

    public static function executeNewsHidePanel(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Das News-Panel wurde versteckt.',
            '_frontend_event' => [
                'name' => 'hide-news-panel',
                'detail' => []
            ]
        ];
    }
}

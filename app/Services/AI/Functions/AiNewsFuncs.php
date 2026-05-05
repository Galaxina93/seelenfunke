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
            ],
            [
                'name' => 'news_hide_widget',
                'description' => 'Versteckt ein bestimmtes Widget aus der Nachrichtenansicht (z.B. ein Video oder eine bestimmte News). Nutze dies, wenn der Nutzer verlangt, dass ein einzelnes Fenster geschlossen wird.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Optional: Ein Teil des Titels des Widgets, das geschlossen werden soll (z.B. "Apple" oder "YouTube").'
                        ],
                        'index' => [
                            'type' => 'integer',
                            'description' => 'Optional: Die Nummer des Widgets, das geschlossen werden soll (z.B. 1 für das erste Widget, 3 für das dritte).'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeNewsHideWidget']
            ],
            [
                'name' => 'search_youtube_video',
                'description' => 'Sucht nach einem passenden YouTube-Video. Nutze dieses Tool (ggf. zusammen mit search_global_news), wenn Nutzer nach Nachrichten, Themen oder explizit nach Videos fragen, um automatisch einen passenden Videostream als Widget einzublenden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff für YouTube (z.B. "Apple News", "Paris Dokumentation").'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Ein passender Titel für das Video-Widget.'
                        ]
                    ],
                    'required' => ['query', 'title']
                ],
                'callable' => [self::class, 'searchYoutubeVideo']
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
                        'url' => $article['url'] ?? '',
                        'image' => $article['urlToImage'] ?? null,
                        'video' => null
                    ];
                }, $data['articles']);

                // Sende die Events synchron ans Frontend anstatt über defekte WebSockets
                return [
                    'status' => 'success',
                    'query' => $query,
                    'articles' => $articles,
                    'note' => 'Erstelle eine kurze, prägnante Zusammenfassung dieser Nachrichten für den Nutzer. WICHTIGER TIPP: Wenn der Nutzer diese Nachrichten als Dokument wünscht oder per Mail verschickt haben will, nutze zwingend dein Tool "system_generate_pdf_report" (target_action: download oder email) und übergebe die Zusammenfassung als "content_markdown"!',
                    '_frontend_events' => [
                        [
                            'name' => 'ai-show-news',
                            'detail' => [
                                'payload' => [
                                    'query' => $query,
                                    'articles' => $articles
                                ]
                            ]
                        ]
                    ]
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

    public static function executeNewsHideWidget(array $args)
    {
        $title = $args['title'] ?? null;
        $index = $args['index'] ?? null;
        
        $msg = $index ? "Das Widget Nummer {$index} wurde geschlossen." : "Das Widget '{$title}' wurde geschlossen.";

        return [
            'status' => 'success',
            'message' => $msg,
            '_frontend_event' => [
                'name' => 'hide-news-widget',
                'detail' => [
                    'title' => $title,
                    'index' => $index
                ]
            ]
        ];
    }

    public static function searchYoutubeVideo(array $args): array
    {
        $query = $args['query'] ?? '';
        $title = $args['title'] ?? 'YouTube Video';
        
        $html = @file_get_contents('https://www.youtube.com/results?search_query=' . urlencode($query));
        $videoId = null;
        
        if ($html && preg_match('/"videoId":"([^"]+)"/', $html, $matches)) {
            $videoId = $matches[1];
        }
        
        if (!$videoId) {
            return [
                'status' => 'error',
                'message' => 'Es konnte kein passendes YouTube Video für "' . $query . '" gefunden werden.'
            ];
        }

        $embedUrl = 'https://www.youtube.com/embed/' . $videoId . '?autoplay=1&mute=1&controls=1&rel=0';
        $watchUrl = 'https://www.youtube.com/watch?v=' . $videoId;
        
        $article = [
            'id' => uniqid('yt_'),
            'title' => $title,
            'description' => 'YouTube Suchergebnis für: ' . $query,
            'source' => 'YouTube',
            'url' => $watchUrl,
            'image' => null,
            'video' => $embedUrl
        ];

        return [
            'status' => 'success',
            'message' => 'YouTube Video wurde geladen und angezeigt.',
            '_frontend_events' => [
                [
                    'name' => 'ai-show-youtube',
                    'detail' => [
                        'payload' => [
                            'append' => true,
                            'query' => $query,
                            'articles' => [$article]
                        ]
                    ]
                ]
            ]
        ];
    }
}

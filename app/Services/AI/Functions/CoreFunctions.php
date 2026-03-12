<?php

namespace App\Services\AI\Functions;

use App\Models\KnowledgeBase;
use App\Services\AI\Functions\SearchChatHistory;

trait CoreFunctions
{
    public static function getCoreFunctionsSchema(): array
    {
        return [
            [
                'name' => 'save_memory',
                'description' => 'Speichert eine Tatsache, eine persönliche Einstellung oder eine wichtige Notiz in deinem Langzeitgedächtnis (Knowledge Base). VERWENDE DIES IMMER, wenn Alina sagt "Merke dir", "Notiere" oder Ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Kurzer, prägnanter Titel für die Erinnerung (z.B. "Geburtstag Theresa").'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Die eigentliche Information, die du dir merken sollst.'
                        ]
                    ],
                    'required' => ['title', 'content']
                ],
                'callable' => [self::class, 'executeSaveMemory']
            ],
            [
                'name' => 'visualize_data',
                'description' => 'Zeigt strukturierte Daten (wie Listen, Objekte oder Statistiken) visuell im Frontend (Master Modal) des Users an. Nutze dies IMMER, wenn der User nach einer Übersicht, Tabelle, Liste oder Grafik fragt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'Grobe Kategorie der Daten in Kleinschreibung (z.B. "voucher", "customer", "todo", "finance", "system_health").'
                        ],
                        'data' => [
                            'type' => 'array',
                            'description' => 'Die nativen rohen JSON-Daten als Array. Das Backend kümmert sich um das Design.',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => true
                            ]
                        ]
                    ],
                    'required' => ['category', 'data']
                ],
                'callable' => [self::class, 'executeVisualizeData']
            ],
            [
                'name' => 'search_memory',
                'description' => 'Durchsucht dein Langzeitgedächtnis (Knowledge Base) nach gespeicherten Fakten, Einstellungen oder Notizen. Setze dies proaktiv ein, wenn du nach einem bestimmten Detail gefragt wirst, das du früher einmal gelernt haben könntest.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff (z.B. "Theresa Geburtstag", "Vorlieben", "Rentenversicherungsnummer", "Steuernummer").'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchMemory']
            ],
            [
                'name' => 'close_ui',
                'description' => 'Schließt alle aktuell in der 3D-Ansicht geöffneten schwebenden Fenster, Tabellen und Charts. Nutze dies IMMER, wenn Alina sagt "Fenster zu", "Schließen", "Tabellen weg" oder ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseUi']
            ],
            [
                'name' => 'open_nav_item',
                'description' => 'Navigiert den Benutzer zu einer bestimmten Seite im System. Verwende dies, wenn der Benutzer darum bittet, einen bestimmten Bereich zu "öffnen", "dorthin zu gehen" oder "dorthin zu navigieren" (z. B. Bestellungen, Finanzen). Die Navigation wird komplett durch dieses Tool im Hintergrund gesteuert, du musst im Textfall nichts weiter erwähnen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The exact URL to navigate to, e.g. "/admin/orders" or "/admin/financial-evaluation"'
                        ]
                    ],
                    'required' => ['url']
                ],
                'callable' => [self::class, 'executeOpenNavItem']
            ],
            [
                'name' => 'open_zentrum',
                'description' => 'Öffnet das visuelle 3D Zentrum (Funkira Widget). Nutze dies IMMER, wenn Alina sagt "Öffne das Zentrum", "Zeig dich", "Zentrum aufrufen" oder ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeOpenZentrum']
            ],
            [
                'name' => 'close_zentrum',
                'description' => 'Schließt das visuelle 3D Zentrum (Funkira Widget) und kehrt zum Chat zurück. Nutze dies IMMER, wenn Alina sagt "Schließe das Zentrum", "Zentrum zu", "Zurück zum Chat" oder ähnliches.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCloseZentrum']
            ],
            array_merge(SearchChatHistory::schema()['function'], [
                'callable' => [self::class, 'executeSearchChatHistory']
            ])
        ];
    }

    public static function executeSaveMemory(array $args)
    {
        try {
            if (empty($args['title']) || empty($args['content'])) {
                return ['status' => 'error', 'message' => 'Titel und Inhalt sind für das Speichern erforderlich.'];
            }

            $kb = KnowledgeBase::create([
                'title' => substr($args['title'], 0, 255),
                'slug' => \Illuminate\Support\Str::slug(substr($args['title'], 0, 255)) . '-' . rand(1000, 9999),
                'category' => 'AI Memory',
                'content' => $args['content'],
                'tags' => ['ai_memory', 'auto_saved'],
                'is_published' => true
            ]);

            return [
                'status' => 'success',
                'message' => "Die Information '{$kb->title}' wurde erfolgreich im Langzeitgedächtnis (Knowledge Base) gespeichert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Speichern der Erinnerung: ' . $e->getMessage()];
        }
    }

    public static function executeVisualizeData(array $args)
    {
        $category = strtolower($args['category'] ?? 'general');
        
        // Safety Fallbacks & Aliases
        if ($category === 'coupon' || $category === 'gutschein' || $category === 'coupons') {
            $category = 'voucher';
        }
        
        $data = $args['data'] ?? [];

        return [
            'status' => 'success',
            'message' => "Habe ein UI Master Modal für die Kategorie '{$category}' geöffnet.",
            '_frontend_event' => [
                'name' => 'open-ai-visualization',
                'detail' => [
                    'category' => $category,
                    'data' => $data
                ]
            ],
            '_fast_track' => true
        ];
    }

    public static function executeSearchMemory(array $args)
    {
        try {
            if (empty($args['query'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Suchbegriff angegeben.'];
            }

            $queryStr = $args['query'];

            $results = KnowledgeBase::where('is_published', true)
                ->where(function ($q) use ($queryStr) {
                    $q->where('title', 'like', '%' . $queryStr . '%')
                      ->orWhere('content', 'like', '%' . $queryStr . '%')
                      ->orWhereJsonContains('tags', $queryStr);
                })
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get(['title', 'content', 'category', 'created_at']);

            if ($results->isEmpty()) {
                 return [
                    'status' => 'success',
                    'message' => 'Ich habe in meinen Erinnerungen nichts zu "' . $queryStr . '" gefunden.',
                    'results' => []
                ];
            }

            return ['status' => 'success', 'results_count' => $results->count(), 'results' => $results->toArray()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Durchsuchen der Erinnerungen: ' . $e->getMessage()];
        }
    }

    public static function executeCloseUi(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Die UI wurde erfolgreich geschlossen.'
        ];
    }

    public static function executeOpenNavItem(array $args)
    {
        try {
            if (empty($args['url'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine URL übergeben.'];
            }

            // Rewrite typische Plural-Fehler der KI
            $url = $args['url'];
            if ($url === '/admin/newsletters') {
                $url = '/admin/newsletter';
            }
            if ($url === '/admin/vouchers') {
                $url = '/admin/voucher';
            }
            if ($url === '/admin/financial' || $url === '/admin/financials') {
                $url = '/admin/financial-evaluation';
            }

            return [
                'status' => 'success',
                'message' => 'Die Navigation wird nun clientseitig ausgeführt.',
                '_event' => [
                    'type' => 'navigate',
                    'url' => $url
                ],
                '_fast_track' => true
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Navigation: ' . $e->getMessage()];
        }
    }
    public static function executeOpenZentrum(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Das Zentrum öffnet sich in diesem Augenblick im Browser.',
            '_event' => [
                'type' => 'dispatch',
                'name' => 'open-funkira'
            ],
            '_fast_track' => true
        ];
    }

    public static function executeCloseZentrum(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Das Zentrum schließt sich in diesem Augenblick im Browser.',
            '_event' => [
                'type' => 'dispatch',
                'name' => 'close-funkira'
            ],
            '_fast_track' => true
        ];
    }

    public static function executeSearchChatHistory(array $args)
    {
        return SearchChatHistory::call($args);
    }
}

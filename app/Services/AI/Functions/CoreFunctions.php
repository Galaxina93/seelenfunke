<?php

namespace App\Services\AI\Functions;

use App\Models\KnowledgeBase;

trait CoreFunctions
{
    public static function getCoreFunctionsSchema(): array
    {
        return [
            [
                'name' => 'save_memory',
                'description' => 'Saves a fact, user preference, or important note into your long-term memory (Knowledge Base). ALWAYS use this when Alina says "Merke dir", "Notiere", or similar.',
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
                'name' => 'search_memory',
                'description' => 'Searches your long-term memory (Knowledge Base) for past facts, preferences, or notes you have saved. Proactively use this if asked about a specific detail you might have learned earlier.',
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
                'description' => 'Navigates the user to a specific page in the system. Use this when the user asks to "open", "go to", or "navigate to" a specific section (e.g. Orders, Financials). Important: This tool just confirms the route. YOU MUST subsequently output the [NAVIGATE]/url[/NAVIGATE] tag in your text response to actually trigger the browser redirect!',
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
            ]
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
            'command' => 'close_all_panels',
            'message' => 'Alle Fenster wurden geschlossen.'
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

            return [
                'status' => 'success',
                'message' => 'Die Route ist gültig. Vergesse nicht, dem User ZWINGEND mit dem exakten Tag [NAVIGATE]' . $url . '[/NAVIGATE] in deiner Antwort zu antworten, um den Redirect auszuführen!'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Navigation: ' . $e->getMessage()];
        }
    }
    public static function executeOpenZentrum(array $args)
    {
        return [
            'status' => 'success',
            'command' => 'open_zentrum',
            'message' => 'Löse in deinem Text das Event [EVENT]open-funkira[/EVENT] (oder was dein Systemprompt dafür vorsieht, am besten fragst du nicht, sondern tust einfach so als hättest du es geöffnet) aus. WICHTIG: Antworte dem User, dass du das Zentrum jetzt öffnest!'
        ];
    }
}

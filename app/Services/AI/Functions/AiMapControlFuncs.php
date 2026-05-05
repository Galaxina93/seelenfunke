<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

trait AiMapControlFuncs
{
    public static function getAiMapControlFuncsSchema(): array
    {
        return [
            [
                'name' => 'map_generate_pdf_summary',
                'description' => 'Generiert aus den recherchierten oder gefundenen Kartendaten (Orte, Koordinaten, Sehenswürdigkeiten) ein übersichtliches PDF. Du kannst das PDF dem Nutzer entweder zum Download anbieten ODER es direkt als E-Mail Anhang an ihn senden. Nutze diese Funktion zwingend, wenn der Nutzer eine Zusammenfassung von Orten oder Map-Daten als PDF per E-Mail haben möchte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel des Dokuments, z.B. "Zusammenfassung der Orte" oder "Geodaten-Bericht".'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Ein einleitender Text für das Dokument.'
                        ],
                        'locations' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste der gefundenen Orte oder Sehenswürdigkeiten.'
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem PDF passieren? "download" (öffnet Download-Dialog) oder "email" (versendet die PDF per Mail an recipient_email).',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail-Adresse des Empfängers. Wenn der Nutzer keine E-Mail nennt, lasse dieses Feld zwingend leer (null).'
                        ]
                    ],
                    'required' => ['title', 'description', 'locations', 'target_action']
                ],
                'callable' => [self::class, 'executeMapGeneratePdfSummary']
            ],
            [
                'name' => 'map_search_and_fly',
                'description' => 'Sucht nach Geodaten für einen Ort oder eine Sehenswürdigkeit (z.B. "Gifhorn", "Eiffelturm Paris") und bewegt die Mapbox-Karte im Frontend automatisch dorthin. Nutze dies IMMER, wenn der Nutzer nach einem Ort fragt oder du über Stationen im Urlaubsplan sprichst, um dem Nutzer die Orte live zu zeigen!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff (Stadt, Land oder genaue Sehenswürdigkeit).'
                        ],
                        'zoom' => [
                            'type' => 'integer',
                            'description' => 'Optional: Zoom-Level (Stadt=12, Land=5, Gebäude=16).'
                        ],
                        'pitch' => [
                            'type' => 'integer',
                            'description' => 'Optional: 3D Neigungswinkel in Grad (0-85, Standard 60).'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeMapSearchAndFly']
            ],
            [
                'name' => 'map_toggle_livedata',
                'description' => 'Schaltet die globalen Livedaten (Flugzeuge, Schiffe, Krisenherde) auf der Weltkarte an oder aus. Nutze dies, wenn der Nutzer Flugverkehr oder Schiffverkehr sehen will.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'active' => [
                            'type' => 'boolean',
                            'description' => 'True um sie anzuschalten, False um sie auszuschalten.'
                        ]
                    ],
                    'required' => ['active']
                ],
                'callable' => [self::class, 'executeMapToggleLivedata']
            ],
            [
                'name' => 'map_toggle_mapfocus',
                'description' => 'Schaltet den Map-Kontrollmodus an oder aus. Bei "an" wird die stylische schicke Animation von mir selbst verkleinert in die Ecke geschoben und der Nutzer kann die Map bedienen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'active' => [
                            'type' => 'boolean',
                            'description' => 'True um Map-Fokus (Kugel klein) zu aktivieren, False um die Kugel (Die mich widerspiegelt) wieder in den Vollbild-Fokus zu holen.'
                        ]
                    ],
                    'required' => ['active']
                ],
                'callable' => [self::class, 'executeMapToggleMapfocus']
            ],
            [
                'name' => 'ui_toggle_log',
                'description' => 'Schaltet den KI-Live-Log (Debug Log Panel) an oder aus. Nutze dies, wenn der Nutzer fragt "Zeige mir den Log" oder "Öffne das Fehlerprotokoll".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'active' => [
                            'type' => 'boolean',
                            'description' => 'True um den Log anzuzeigen, False um ihn zu verstecken.'
                        ]
                    ],
                    'required' => ['active']
                ],
                'callable' => [self::class, 'executeUiToggleLog']
            ],
            [
                'name' => 'ui_close_youtube',
                'description' => 'Schließt eines oder alle aktuell angezeigten YouTube-Videos auf dem Bildschirm. Nutze dies, wenn der Nutzer dich bittet, das Video wegzumachen oder zu schließen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'index' => [
                            'type' => 'integer',
                            'description' => 'Optional: Die Nummer des Videos (1, 2, 3), das geschlossen werden soll. Wenn leer, werden alle Videos geschlossen.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeUiCloseYoutube']
            ]
        ];
    }

    public static function executeMapGeneratePdfSummary(array $args)
    {
        $title = $args['title'] ?? 'Zusammenfassung der Orte';
        $description = $args['description'] ?? '';
        $locations = $args['locations'] ?? [];
        $action = $args['target_action'] ?? 'download';
        $recipient = $args['recipient_email'] ?? null;
        $agentName = session('current_ai_agent_name', 'System');

        try {
            // Generate PDF
            $pdf = Pdf::loadView('pdf.map_summary', [
                'title' => $title,
                'description' => $description,
                'locations' => $locations
            ]);

            $dir = storage_path('app/public/maps');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'ortsbericht_' . Str::slug($title) . '_' . time() . '.pdf';
            $filePath = $dir . '/' . $filename;
            $pdf->save($filePath);

            $downloadUrl = url('storage/maps/' . $filename);

            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }

                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\AiMapSummaryMail(
                    "Dein Bericht: $title",
                    $description,
                    $agentName,
                    [$filePath]
                ));

                return [
                    'status' => 'success',
                    'message' => "Zusammenfassung erfolgreich als PDF generiert und per E-Mail an $recipient versendet!",
                    'file_name' => $filename,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => 'Zusammenfassung erfolgreich als PDF generiert!',
                    'pdf_url' => $downloadUrl,
                    'file_name' => $filename,
                    'note' => 'Das PDF wurde generiert und kann vom Nutzer heruntergeladen werden.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("Map PDF Generation Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen des PDFs: ' . $e->getMessage()];
        }
    }

    public static function executeMapSearchAndFly(array $args)
    {
        $query = $args['query'] ?? '';
        if (empty($query)) {
            return ['status' => 'error', 'message' => 'Query is required.'];
        }

        $token = env('MAPBOX_TOKEN');
        if (empty($token)) {
            return ['status' => 'error', 'message' => 'MAPBOX_TOKEN is not configured in .env'];
        }

        try {
            $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($query) . ".json";
            $response = Http::get($url, [
                'access_token' => $token,
                'limit' => 3, // Hole bis zu 3 Ergebnisse
                'language' => 'de'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['features']) && count($data['features']) > 0) {
                    // Wenn es mehrere gibt und die Relevanz nicht eindeutig 1 ist, könnte der Agent nachfragen,
                    // aber für UX fliegen wir direkt zum besten (ersten) Ergebnis und melden die anderen zurück.
                    $feature = $data['features'][0];
                    $lng = $feature['center'][0];
                    $lat = $feature['center'][1];
                    $placeName = $feature['place_name'];

                    $zoom = $args['zoom'] ?? 14;
                    // Auto-adjust zoom roughly based on feature type
                    if (isset($feature['place_type'][0])) {
                        if ($feature['place_type'][0] === 'country') $zoom = 5;
                        if ($feature['place_type'][0] === 'region') $zoom = 8;
                        if ($feature['place_type'][0] === 'place' || $feature['place_type'][0] === 'city') $zoom = 12;
                        if ($feature['place_type'][0] === 'poi') $zoom = 16;
                    }

                    $pitch = $args['pitch'] ?? 60;

                    $otherResults = [];
                    if (count($data['features']) > 1) {
                        for ($i = 1; $i < count($data['features']); $i++) {
                            $otherResults[] = $data['features'][$i]['place_name'];
                        }
                    }

                    $message = "Habe Koordinaten für '{$placeName}' gefunden und die Karte dorthin bewegt.";
                    if (!empty($otherResults)) {
                        $message .= " Hinweis: Es gab weitere Treffer (z.B. " . implode(', ', $otherResults) . "). Falls der Nutzer einen anderen meinte, frage gezielt nach.";
                    }

                    // Fetch Wikipedia data for Holographic News Panel
                    $newsEvent = null;
                    try {
                        $wikiUrl = "https://de.wikipedia.org/w/api.php?action=query&generator=geosearch&ggscoord={$lat}|{$lng}&ggsradius=5000&ggslimit=3&prop=extracts|pageimages&exintro=1&exchars=150&explaintext=1&piprop=thumbnail&pithumbsize=300&format=json";
                        $wikiResponse = Http::timeout(3)->get($wikiUrl);
                        if ($wikiResponse->successful()) {
                            $wikiData = $wikiResponse->json();
                            $articles = [];
                            if (isset($wikiData['query']['pages'])) {
                                foreach ($wikiData['query']['pages'] as $pageId => $pageData) {
                                    $articles[] = [
                                        'title' => $pageData['title'] ?? 'Wikipedia',
                                        'description' => $pageData['extract'] ?? '',
                                        'image' => $pageData['thumbnail']['source'] ?? null,
                                        'url' => 'https://de.wikipedia.org/?curid=' . $pageId,
                                        'source' => 'WIKI GEO-LINK',
                                        'date' => 'Live'
                                    ];
                                }
                            }
                            if (!empty($articles)) {
                                $newsEvent = [
                                    'name' => 'ai-show-news',
                                    'detail' => [
                                        'articles' => $articles
                                    ]
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("Wikipedia Fetch Error: " . $e->getMessage());
                    }

                    $frontendEvents = [
                        [
                            'name' => 'map-fly-to',
                            'detail' => [
                                'lng' => $lng,
                                'lat' => $lat,
                                'zoom' => $zoom,
                                'pitch' => $pitch,
                                'markerText' => $query
                            ]
                        ]
                    ];

                    if ($newsEvent) {
                        $frontendEvents[] = $newsEvent;
                    }

                    return [
                        'status' => 'success',
                        'message' => $message,
                        'place_name' => $placeName,
                        'coordinates' => [
                            'lng' => $lng,
                            'lat' => $lat
                        ],
                        '_frontend_events' => $frontendEvents
                    ];
                }
            }

            return [
                'status' => 'error',
                'message' => 'Keine Ergebnisse für diese Suche gefunden. Bitte den Nutzer um eine genauere Ortsangabe.'
            ];
        } catch (\Exception $e) {
            Log::error("Mapbox Geocoding Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'API Error: ' . $e->getMessage()];
        }
    }

    public static function executeMapToggleLivedata(array $args)
    {
        $active = $args['active'] ?? true;
        return [
            'status' => 'success',
            'message' => 'Livedaten wurden ' . ($active ? 'aktiviert' : 'deaktiviert') . '.',
            '_frontend_event' => [
                'name' => 'toggle-livedata',
                'detail' => ['active' => $active]
            ]
        ];
    }

    public static function executeMapToggleMapfocus(array $args)
    {
        $active = $args['active'] ?? true;
        return [
            'status' => 'success',
            'message' => 'Map-Kontrolle wurde ' . ($active ? 'aktiviert' : 'deaktiviert') . '.',
            '_frontend_event' => [
                'name' => 'toggle-mapfocus',
                'detail' => ['active' => $active]
            ]
        ];
    }

    public static function executeUiToggleLog(array $args)
    {
        $active = $args['active'] ?? true;
        return [
            'status' => 'success',
            'message' => 'Live-Log wurde ' . ($active ? 'geöffnet' : 'geschlossen') . '.',
            '_frontend_event' => [
                'name' => 'toggle-log',
                'detail' => ['active' => $active]
            ]
        ];
    }

    public static function executeUiCloseYoutube(array $args)
    {
        $index = $args['index'] ?? null;
        return [
            'status' => 'success',
            'message' => 'YouTube Video(s) geschlossen.',
            '_frontend_event' => [
                'name' => 'hide-youtube-widget',
                'detail' => ['index' => $index]
            ]
        ];
    }
}

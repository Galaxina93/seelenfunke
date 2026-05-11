<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

trait AiMapControlFuncs
{
    // [AREA: SCHEMA & CAPABILITIES]
    public static function getAiMapControlFuncsSchema(): array
    {
        return [
            [
                'name' => 'map_clear_markers',
                'description' => 'Entfernt alle aktuellen Markierungen, Pins oder Routen von der interaktiven Karte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeMapClearMarkers']
            ],
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
                'description' => 'Sucht nach Geodaten für einen EINZELNEN Ort, eine Stadt, ein Land oder eine bestimmte Sehenswürdigkeit (z.B. "Gifhorn", "Deutschland", "Eiffelturm Paris") und bewegt die Mapbox-Karte im Frontend automatisch dorthin. Nutze dies für einzelne konkrete Orte. ACHTUNG: Wenn der Nutzer nach Kategorien oder Geschäften (z.B. "Bäckereien in Gifhorn", "Tankstellen") sucht, nutze stattdessen ZWINGEND "map_search_and_mark_places"!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff (Stadt, Land oder genaue Sehenswürdigkeit). WICHTIG: Da die Adresse per Spracheingabe transkribiert wird (z.B. "Karl-Gürtler-Ring" statt "Carl-Goerdeler-Ring"), prüfe den Ort VOR der Suche auf logische Schreibweise und korrigiere ihn falls nötig!'
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
                'name' => 'map_search_and_mark_places',
                'description' => 'Sucht nach lokalen Orten, Geschäften oder POIs (Points of Interest, z.B. "Bäckereien in Gifhorn", "Krankenhäuser in Berlin") und markiert ALLE gefundenen Treffer auf der Mapbox-Karte im Frontend. Nutze dies IMMER, wenn der Nutzer nach einer Kategorie von Orten in einer bestimmten Stadt sucht.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff (z.B. "Bäckerei Gifhorn", "Tankstelle München"). WICHTIG: Da die Adresse per Spracheingabe transkribiert wird (z.B. "Leifert" als "Leifahrt"), prüfe den Ort VOR der Suche auf logische Schreibweise und korrigiere ihn falls nötig!'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Anzahl der gewünschten Treffer (Maximal 10).'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeMapSearchAndMarkPlaces']
            ],
            [
                'name' => 'map_generate_places_pdf_and_mail',
                'description' => 'Generiert ein PDF mit detaillierten Informationen zu recherchierten Orten (z.B. nach einer Map-Suche) und sendet es optional per E-Mail. Nutze dieses Tool ZWINGEND, wenn der Nutzer nach einer detaillierten Liste mit Kontaktdaten, Telefonnummern oder Webseiten fragt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titel des PDFs (z.B. "Autohäuser in Gifhorn").'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Ein kurzer Einleitungstext für das PDF.'
                        ],
                        'places' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'address' => ['type' => 'string'],
                                    'phone' => ['type' => 'string', 'description' => 'Telefonnummer (falls recherchiert)'],
                                    'email' => ['type' => 'string', 'description' => 'E-Mail-Adresse (falls recherchiert)'],
                                    'website' => ['type' => 'string', 'description' => 'Webseite (falls recherchiert)'],
                                    'description' => ['type' => 'string', 'description' => 'Zusätzliche Details, Öffnungszeiten oder Bemerkungen']
                                ],
                                'required' => ['name', 'address']
                            ],
                            'description' => 'Liste der detaillierten Orte. Recherchiere bei Bedarf Telefonnummern und Webseiten mit web_search, bevor du dieses PDF generierst!'
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem PDF passieren? "download" oder "email".',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'E-Mail-Adresse, falls "email" gewählt. Sonst leer (null).'
                        ]
                    ],
                    'required' => ['title', 'places', 'target_action']
                ],
                'callable' => [self::class, 'executeMapGeneratePlacesPdf']
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
                'name' => 'map_change_style',
                'description' => 'Ändert den visuellen Stil (Modus) der interaktiven Karte (z.B. Dark, Light, Satellite, Cyberpunk).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'style' => [
                            'type' => 'string',
                            'enum' => ['dark', 'light', 'satellite', 'cyber', 'streets'],
                            'description' => 'Der gewünschte Kartenstil.'
                        ]
                    ],
                    'required' => ['style']
                ],
                'callable' => [self::class, 'executeMapChangeStyle']
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
                'name' => 'system_toggle_brain',
                'description' => 'Schaltet das "Projekt Gehirn" (die Neurale 3D System Architektur) an oder aus. Nutze dies, wenn der Nutzer das System, das Gehirn oder die Code-Architektur visualisieren möchte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'active' => [
                            'type' => 'boolean',
                            'description' => 'True um das Gehirn anzuschalten, False um es auszuschalten.'
                        ]
                    ],
                    'required' => ['active']
                ],
                'callable' => [self::class, 'executeSystemToggleBrain']
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
                'description' => 'Schließt eines oder alle aktuell angezeigten YouTube-Videos KOMPLETT. WICHTIG: Wenn der Nutzer nur bittet "mach das Video klein" oder "schließe den Vollbildmodus", nutze stattdessen ZWINGEND ui_close_focus! Nutze dieses Tool nur, wenn das Video wirklich beendet und entfernt werden soll.',
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
            ],
            [
                'name' => 'ui_focus_widget',
                'description' => 'Vergrößert ein aktuell sichtbares Widget (z.B. YouTube Video oder News) und holt es auf den "Hauptschirm" (Vollbild/Zentrum). Nutze dies, wenn der Nutzer bittet, ein Video groß zu machen oder auf dem Hauptschirm anzuzeigen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'description' => 'Der Typ des Widgets ("youtube" oder "news")',
                            'enum' => ['youtube', 'news']
                        ],
                        'index' => [
                            'type' => 'integer',
                            'description' => 'Die Nummer des Widgets (0, 1, 2...). 0 ist das erste.'
                        ]
                    ],
                    'required' => ['type', 'index']
                ],
                'callable' => [self::class, 'executeUiFocusWidget']
            ],
            [
                'name' => 'ui_close_focus',
                'description' => 'Macht ein vergrößertes Video oder Widget wieder klein. Schließt den "Hauptschirm" und bringt alle Widgets zurück in ihre normale Ansicht.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeUiCloseFocus']
            ],
            [
                'name' => 'ui_toggle_youtube_mute',
                'description' => 'Schaltet den Ton eines YouTube-Videos an oder aus (Mute/Unmute).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'index' => [
                            'type' => 'integer',
                            'description' => 'Optional: Die Nummer des Videos (0, 1, 2...), das stummgeschaltet werden soll. Wenn leer, wird das fokussierte Video oder alle stummgeschaltet.'
                        ],
                        'mute' => [
                            'type' => 'boolean',
                            'description' => 'True um stummzuschalten (Mute), False um Ton wieder zu aktivieren (Unmute).'
                        ]
                    ],
                    'required' => ['mute']
                ],
                'callable' => [self::class, 'executeUiToggleYoutubeMute']
            ],
            [
                'name' => 'ui_summarize_youtube',
                'description' => 'Weist das Frontend an, das aktuell fokussierte YouTube-Video (oder ein bestimmtes) zu analysieren und eine Transkription/Zusammenfassung durch die KI vorzubereiten. Das Frontend sendet dann ein Event zurück.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'index' => [
                            'type' => 'integer',
                            'description' => 'Optional: Die Nummer des Videos, das zusammengefasst werden soll.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeUiSummarizeYoutube']
            ],
            [
                'name' => 'ui_analyze_camera',
                'description' => 'Weist das Frontend an, ein sofortiges Foto/Bild von der aktuell aktiven Kamera (Webcam) aufzunehmen und es zur visuellen Analyse an dich (den Agenten) zu schicken. Nutze dieses Werkzeug ZWINGEND und IMMER, wenn der Nutzer dich fragt: "Was siehst du?", "Was halte ich in der Hand?" oder dich bittet, das Kamerabild zu analysieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeUiAnalyzeCamera']
            ],
            [
                'name' => 'camera_process_snapshot',
                'description' => 'Verarbeitet ein Kamera-Bild, das du ZUVOR analysiert hast. Nutze dies ZWINGEND, wenn der Nutzer sagt "Speichere das Bild", "Lege das Bild im Dateimanager ab", "Schicke mir das Bild per Mail" oder "Mach ein PDF aus dem Bild". Du benötigst dazu den `file_path`, der dir bei der Bild-Übergabe in [SYSTEM_INFO] mitgeteilt wurde!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Der Dateipfad des Bildes, den du in der vorherigen [SYSTEM_INFO] erhalten hast (z.B. agenten/workspace/Kamera-Snapshots/snapshot_...).'
                        ],
                        'action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem Bild passieren? "save_to_workspace" (Nur behalten), "generate_pdf" (PDF mit Bild erstellen), "send_email" (Bild/PDF per E-Mail versenden).',
                            'enum' => ['save_to_workspace', 'generate_pdf', 'send_email']
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Ein Titel für das Bild/PDF/E-Mail (z.B. "Foto vom Produkt").'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Eine kurze Beschreibung oder deine Analyse des Bildes für die PDF oder E-Mail.'
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail-Adresse, falls action="send_email". Sonst leer (null).'
                        ]
                    ],
                    'required' => ['file_path', 'action', 'title']
                ],
                'callable' => [self::class, 'executeCameraProcessSnapshot']
            ]
        ];
    }

    // [AREA: EXPORT & REPORTING]
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

    // [AREA: MAP SEARCH & NAVIGATION]
    public static function executeMapSearchAndFly(array $args)
    {
        $query = $args['query'] ?? '';
        if (empty($query)) {
            $received = json_encode($args);
            return ['status' => 'error', 'message' => "Du hast den zwingenden Parameter 'query' vergessen! (Erhalten: {$received}). Bitte rufe die Funktion 'map_search_and_fly' SOFORT noch einmal auf und übergebe den Suchbegriff als 'query'!"];
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

    public static function executeMapSearchAndMarkPlaces(array $args)
    {
        $query = $args['query'] ?? '';
        $limit = $args['limit'] ?? 5;
        if ($limit > 10) $limit = 10;
        
        if (empty($query)) {
            $received = json_encode($args);
            return ['status' => 'error', 'message' => "Du hast den zwingenden Parameter 'query' vergessen! (Erhalten: {$received}). Bitte rufe die Funktion 'map_search_and_mark_places' SOFORT noch einmal auf und übergebe den Suchbegriff als 'query'!"];
        }

        try {
            $url = "https://nominatim.openstreetmap.org/search";
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Seelenfunke/1.0 (AI Agent)'
            ])->get($url, [
                'q' => $query,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => $limit
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data) && count($data) > 0) {
                    
                    $markers = [];
                    foreach ($data as $feature) {
                        $name = $feature['name'] ?: ($feature['address']['shop'] ?? $feature['address']['building'] ?? $feature['address']['commercial'] ?? $feature['display_name']);
                        
                        $markers[] = [
                            'lng' => (float)$feature['lon'],
                            'lat' => (float)$feature['lat'],
                            'title' => $name,
                            'location_name' => $feature['display_name']
                        ];
                    }

                    $count = count($markers);
                    
                    $frontendEvents = [
                        [
                            'name' => 'map-mark-places',
                            'detail' => [
                                'query' => $query,
                                'markers' => $markers
                            ]
                        ]
                    ];

                    return [
                        'status' => 'success',
                        'message' => "Habe {$count} präzise Orte für '{$query}' gefunden und auf der Map markiert. Nutze das Tool 'map_generate_places_pdf_and_mail', falls der Nutzer eine PDF-Liste angefordert hat.",
                        'places' => $markers,
                        '_frontend_events' => $frontendEvents
                    ];
                }
            }

            return [
                'status' => 'error',
                'message' => 'Keine lokalen Ergebnisse für diese Suche gefunden. Probiere es mit einer anderen Formulierung.'
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Nominatim Geocoding Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'API Error: ' . $e->getMessage()];
        }
    }

    public static function executeMapGeneratePlacesPdf(array $args)
    {
        $title = $args['title'] ?? 'Ortsliste';
        $description = $args['description'] ?? '';
        $places = $args['places'] ?? [];
        $action = $args['target_action'] ?? 'download';
        $recipient = $args['recipient_email'] ?? null;
        $agentName = session('current_ai_agent_name', 'Globi');

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.places_list', [
                'title' => $title,
                'description' => $description,
                'places' => $places
            ]);

            $dir = storage_path('app/public/places');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'ortsliste_' . \Illuminate\Support\Str::slug($title) . '_' . time() . '.pdf';
            $filePath = $dir . '/' . $filename;
            $pdf->save($filePath);

            $downloadUrl = url('storage/places/' . $filename);

            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }

                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\AiHolidayPlanMail(
                    "Ihre Ortsliste: $title",
                    $description,
                    $agentName,
                    [$filePath]
                ));

                return [
                    'status' => 'success',
                    'message' => "Die PDF-Liste wurde erfolgreich generiert und per E-Mail an $recipient versendet!",
                    'file_name' => $filename,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => 'Die PDF-Liste wurde erfolgreich generiert!',
                    'pdf_url' => $downloadUrl,
                    'file_name' => $filename,
                    'note' => 'Das Dokument wurde generiert und kann vom Nutzer heruntergeladen werden.'
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("PDF Generation Error (Places): " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen des PDFs: ' . $e->getMessage()];
        }
    }

    // [AREA: UI & MAP CONTROLS]
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

    public static function executeMapChangeStyle(array $args)
    {
        $style = $args['style'] ?? 'dark';
        return [
            'status' => 'success',
            'message' => "Der Kartenstil wurde erfolgreich auf '$style' geändert.",
            '_frontend_event' => [
                'name' => 'map-change-style',
                'detail' => ['style' => $style]
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

    public static function executeSystemToggleBrain(array $args)
    {
        $active = $args['active'] ?? true;
        return [
            'status' => 'success',
            'message' => 'Projekt Gehirn wurde ' . ($active ? 'aktiviert' : 'deaktiviert') . '.',
            '_frontend_event' => [
                'name' => 'ai-toggle-brain-workspace',
                'detail' => ['open' => $active]
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

    public static function executeMapClearMarkers(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Die Karte wurde bereinigt. Alle Markierungen wurden entfernt.',
            '_frontend_event' => [
                'name' => 'map-clear-markers',
                'detail' => []
            ]
        ];
    }

    public static function executeUiFocusWidget(array $args)
    {
        $type = $args['type'] ?? 'youtube';
        $index = $args['index'] ?? 0;
        return [
            'status' => 'success',
            'message' => 'Das Widget wurde auf den Hauptschirm maximiert.',
            '_frontend_event' => [
                'name' => 'ui-focus-widget',
                'detail' => [
                    'type' => $type,
                    'index' => (int)$index
                ]
            ]
        ];
    }

    public static function executeUiCloseFocus(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Der Hauptschirm wurde geschlossen, alle Widgets sind wieder im normalen Layout.',
            '_frontend_event' => [
                'name' => 'ui-clear-focus',
                'detail' => []
            ]
        ];
    }

    public static function executeUiToggleYoutubeMute(array $args)
    {
        $index = $args['index'] ?? null;
        $mute = $args['mute'] ?? true;
        
        return [
            'status' => 'success',
            'message' => 'YouTube Video ' . ($mute ? 'stummgeschaltet' : 'Ton aktiviert') . '.',
            '_frontend_event' => [
                'name' => 'ai-toggle-youtube-mute',
                'detail' => [
                    'index' => $index,
                    'mute' => $mute
                ]
            ]
        ];
    }

    public static function executeUiSummarizeYoutube(array $args)
    {
        $index = $args['index'] ?? null;
        
        // This prompts the frontend to fetch the transcript or URL and then ask the AI to summarize it.
        return [
            'status' => 'success',
            'message' => 'Video-Zusammenfassung angefordert. Bitte warte auf die Transkription des Frontends.',
            '_frontend_event' => [
                'name' => 'ai-summarize-youtube',
                'detail' => [
                    'index' => $index
                ]
            ]
        ];
    }

    public static function executeUiAnalyzeCamera(array $args)
    {
        return [
            'status' => 'success',
            'message' => 'Kamera-Aufnahme angefordert. Das System verarbeitet nun den visuellen Feed und liefert dir in Kürze das Bild für die Analyse zurück. BITTE WARTE KURZ auf die Antwort des Frontends, bevor du dem Nutzer abschließend antwortest!',
            '_frontend_event' => [
                'name' => 'ai-analyze-camera',
                'detail' => []
            ]
        ];
    }

    public static function executeCameraProcessSnapshot(array $args)
    {
        $filePath = $args['file_path'] ?? '';
        $action = $args['action'] ?? 'save_to_workspace';
        $title = $args['title'] ?? 'Kamera-Snapshot';
        $description = $args['description'] ?? '';
        $recipient = $args['recipient_email'] ?? null;
        $agentName = session('current_ai_agent_name', 'System Agent');

        if (empty($filePath) || !\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
            return ['status' => 'error', 'message' => "Fehler: Die angegebene Datei ('{$filePath}') wurde nicht gefunden. Bitte überprüfe den Dateipfad aus der [SYSTEM_INFO]."];
        }

        $fullImagePath = storage_path('app/public/' . $filePath);

        try {
            if ($action === 'generate_pdf' || ($action === 'send_email' && str_contains(strtolower($description), 'pdf'))) {
                // PDF Generierung
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.places_list', [
                    'title' => $title,
                    'description' => $description . '<br><br><img src="data:image/jpeg;base64,' . base64_encode(file_get_contents($fullImagePath)) . '" style="max-width:100%; height:auto;">',
                    'places' => []
                ]);

                $pdfFilename = 'snapshot_' . \Illuminate\Support\Str::slug($title) . '_' . time() . '.pdf';
                $pdfPath = 'agenten/workspace/Kamera-Snapshots/' . $pdfFilename;
                
                \Illuminate\Support\Facades\Storage::disk('public')->put($pdfPath, $pdf->output());
                
                // Wir nutzen ab jetzt den PDF Pfad
                $filePath = $pdfPath;
                $fullImagePath = storage_path('app/public/' . $filePath);
            }

            if ($action === 'send_email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }

                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\AiHolidayPlanMail(
                    "Snapshot: $title",
                    $description,
                    $agentName,
                    [$fullImagePath]
                ));

                return [
                    'status' => 'success',
                    'message' => "Das Bild/PDF wurde erfolgreich an $recipient gesendet. Es liegt zusätzlich im Dateimanager unter: $filePath",
                ];
            }

            return [
                'status' => 'success',
                'message' => "Aktion '$action' ausgeführt. Die Datei befindet sich sicher im Dateimanager unter dem Pfad: $filePath",
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Snapshot Process Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler bei der Verarbeitung: ' . $e->getMessage()];
        }
    }
}

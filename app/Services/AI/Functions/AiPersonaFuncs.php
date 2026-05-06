<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Log;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use Illuminate\Support\Str;

trait AiPersonaFuncs
{
    // [AREA: GET SCHEMA]
    public static function getAiPersonaFuncsSchema(): array
    {
        return [
            [
                'name' => 'persona_visualize_profile',
                'description' => 'Visualisiert ein detailliertes Personenprofil / einen Steckbrief einer gesuchten Person (z.B. nach einer Websuche) in der UI.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Voller Name der Person'],
                        'aliases' => ['type' => 'string', 'description' => 'Spitznamen, Titel oder Rollen (z.B. Parteivorsitzender, Schauspieler)'],
                        'status' => ['type' => 'string', 'description' => 'Aktueller Status (z.B. "Aktiv", "Verstorben", "Im Amt")'],
                        'origin' => ['type' => 'string', 'description' => 'Herkunft / Geburtsort'],
                        'birth_date' => ['type' => 'string', 'description' => 'Geburtsdatum'],
                        'image_url' => ['type' => 'string', 'description' => 'Eine ECHTE, absolute URL zu einem passenden Porträt-/Profilbild (z.B. von Wikimedia Commons). Erfinde keine URLs! Verlinke direkt auf .jpg oder .png Dateien.'],
                        'summary' => ['type' => 'string', 'description' => 'Eine kurze Geheimdienst-Zusammenfassung (Executive Summary) der Person.'],
                        'career_timeline' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'year' => ['type' => 'string'],
                                    'event' => ['type' => 'string']
                                ]
                            ],
                            'description' => 'Wichtige Meilensteine in der Karriere oder im Leben.'
                        ],
                        'known_associates' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Bekannte Partner, Verbündete oder politische Weggefährten.'
                        ]
                    ],
                    'required' => ['name', 'summary']
                ],
                'callable' => [self::class, 'executePersonaVisualizeProfile']
            ],
            [
                'name' => 'persona_generate_pdf_and_mail',
                'description' => 'Generiert eine druckfertige Personenakte / ein Profil (PDF) der Person und sendet es optional per E-Mail.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'aliases' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'origin' => ['type' => 'string'],
                        'birth_date' => ['type' => 'string'],
                        'image_url' => ['type' => 'string', 'description' => 'Eine ECHTE, absolute Bild-URL (z.B. Wikimedia Commons).'],
                        'summary' => ['type' => 'string'],
                        'career_timeline' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'year' => ['type' => 'string'],
                                    'event' => ['type' => 'string']
                                ]
                            ]
                        ],
                        'known_associates' => [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem PDF passieren? "download" oder "email".',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'E-Mail-Adresse, falls "email" gewählt.'
                        ]
                    ],
                    'required' => ['name', 'summary', 'target_action']
                ],
                'callable' => [self::class, 'executePersonaGeneratePdf']
            ],
            [
                'name' => 'persona_toggle_secret_workspace',
                'description' => 'Öffnet oder schließt den Top Secret OSINT Investigation Workspace (String-Board) im Frontend. In diesem Modus kann der Nutzer Ermittlungsergebnisse auf einem schwarzen Board analysieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'enum' => ['open', 'close'],
                            'description' => 'Ob der Secret Workspace geöffnet oder geschlossen werden soll.'
                        ]
                    ],
                    'required' => ['action']
                ],
                'callable' => [self::class, 'executePersonaToggleSecretWorkspace']
            ],
            [
                'name' => 'persona_save_intel',
                'description' => 'Speichert OSINT-Fakten (Personenprofile, Firmendaten, Berichte) dauerhaft in der Top Secret Datenbank (AiKnowledgeBase), damit die KI diese zukünftig wieder abrufen kann.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_name' => ['type' => 'string', 'description' => 'Der Name der Person oder Organisation (wird als Titel gespeichert).'],
                        'intel_content' => ['type' => 'string', 'description' => 'Die ausführlichen Fakten, Profile oder Berichte im Markdown Format.'],
                        'image_url' => ['type' => 'string', 'description' => 'Optional: Eine ECHTE, existierende URL zu einem passenden Bild (z.B. Wikimedia Commons). Verlinke direkt auf das .jpg/.png Bild.'],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Relevante Tags zur Kategorisierung (z.B. "Politiker", "Korruptionsverdacht", "Firma"). "top_secret" wird automatisch hinzugefügt.'
                        ]
                    ],
                    'required' => ['entity_name', 'intel_content']
                ],
                'callable' => [self::class, 'executePersonaSaveIntel']
            ],
            [
                'name' => 'persona_link_entities',
                'description' => 'Speichert eine Verbindung/Beziehung zwischen zwei Entitäten (Personen/Firmen) im OSINT-Netzwerk ab, um Korruption oder Vetternwirtschaft zu dokumentieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'source_entity' => ['type' => 'string', 'description' => 'Der Name der ausgehenden Entität (z.B. Name des Politikers).'],
                        'target_entity' => ['type' => 'string', 'description' => 'Der Name der Ziel-Entität (z.B. Name der Baufirma).'],
                        'relationship_type' => ['type' => 'string', 'description' => 'Art der Beziehung (z.B. "Vorstandsmitglied", "Ehepartner", "Erhält Bestechungsgeld").'],
                        'evidence_summary' => ['type' => 'string', 'description' => 'Kurze Begründung / Zusammenfassung der Beweise, warum diese Verbindung existiert.']
                    ],
                    'required' => ['source_entity', 'target_entity', 'relationship_type', 'evidence_summary']
                ],
                'callable' => [self::class, 'executePersonaLinkEntities']
            ],
            [
                'name' => 'persona_get_network',
                'description' => 'Lädt alle bekannten Verbindungen (Netzwerk-Kanten) aus der OSINT-Datenbank in den KI-Kontext, um Zusammenhänge analysieren zu können.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filter_entity' => ['type' => 'string', 'description' => 'Optional: Nur Verbindungen einer bestimmten Person/Firma abrufen. Leer lassen für das gesamte bekannte Netzwerk.']
                    ]
                ],
                'callable' => [self::class, 'executePersonaGetNetwork']
            ],
            [
                'name' => 'persona_visualize_intel',
                'description' => 'Visualisiert einen kurzen Text, Fakt oder ein Ermittlungsergebnis als kleine Kachel ("Post-it") auf dem Top Secret String-Board.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Kurzer Titel des Fakts (z.B. "Bankverbindung", "Verdächtige Aktivität")'],
                        'content' => ['type' => 'string', 'description' => 'Der Textinhalt der Information.'],
                    ],
                    'required' => ['title', 'content']
                ],
                'callable' => [self::class, 'executePersonaVisualizeIntel']
            ],
            [
                'name' => 'persona_delete_intel',
                'description' => 'Löscht einen Eintrag (Intel oder Person) aus der OSINT Datenbank (AiKnowledgeBase), wenn er als falsch oder irrelevant identifiziert wurde.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Der exakte Titel des Eintrags, der gelöscht werden soll.']
                    ],
                    'required' => ['title']
                ],
                'callable' => [self::class, 'executePersonaDeleteIntel']
            ],
           /* [
                'name' => 'brain_delete_entry',
                'description' => 'Alias für persona_delete_intel. Löscht einen Eintrag (Intel oder Person) aus der OSINT Datenbank.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Der exakte Titel des Eintrags, der gelöscht werden soll.']
                    ],
                    'required' => ['title']
                ],
                'callable' => [self::class, 'executePersonaDeleteIntel']
            ],*/
            [
                'name' => 'persona_fetch_url',
                'description' => 'Lädt den Textinhalt einer beliebigen öffentlichen URL herunter. Ideal für Ermittlungen auf Nachrichtenseiten oder Wikipedia.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => 'Die vollständige URL (https://...) der Webseite.']
                    ],
                    'required' => ['url']
                ],
                'callable' => [self::class, 'executePersonaFetchUrl']
            ],
            [
                'name' => 'persona_close_widgets',
                'description' => 'Schließt/entfernt dynamisch ein oder mehrere angezeigte Widgets (Personas, Profile, Intel etc.) aus der Benutzeroberfläche des Nutzers per Sprachbefehl. Benutze dies, wenn der Nutzer sagt "Blende das Profil von X aus" oder "Schließe alle Profile".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => ['persona', 'intel', 'camera', 'all'],
                            'description' => 'Welcher Typ von Widget geschlossen werden soll ("persona", "intel", "camera" oder "all").'
                        ],
                        'identifier' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff oder Name der Persona/des Profils, das geschlossen werden soll (z.B. "John" oder "Doe"). Wenn leer, werden ALLE Widgets des angegebenen Typs geschlossen.'
                        ]
                    ],
                    'required' => ['type']
                ],
                'callable' => [self::class, 'executePersonaCloseWidgets']
            ],
            [
                'name' => 'persona_focus_widget',
                'description' => 'Ändert die Größe eines angezeigten Widgets (z.B. Profil/Akte). "focus" vergrößert es in den Vollbildmodus zur Detailansicht. "unfocus" macht es wieder klein (schließt den Vollbildmodus, behält es aber in der Übersicht). Nutze dies, wenn der Nutzer sagt "mach das Profil größer" oder "mach es wieder klein".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'enum' => ['focus', 'unfocus'],
                            'description' => 'Die Aktion: "focus" (groß machen) oder "unfocus" (wieder klein machen).'
                        ],
                        'type' => [
                            'type' => 'string',
                            'enum' => ['persona', 'intel'],
                            'description' => 'Typ des Widgets ("persona" oder "intel").'
                        ],
                        'identifier' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff/Name der Persona oder der Titel des Intels. (Nur wichtig bei "focus")'
                        ]
                    ],
                    'required' => ['action', 'type']
                ],
                'callable' => [self::class, 'executePersonaFocusWidget']
            ],
            [
                'name' => 'persona_shelf_widget',
                'description' => 'Verschiebt ein angezeigtes Widget (z.B. Profil/Akte oder Intel) in die intelligente Seitenablage (Shelf/Clipboard), anstatt es komplett zu löschen. Nutze dies, wenn der Nutzer sagt "leg die Person XY zur Seite", "pack es beiseite" oder "in die Zwischenablage".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => ['persona', 'intel'],
                            'description' => 'Typ des Widgets ("persona" oder "intel").'
                        ],
                        'identifier' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff/Name der Persona oder der Titel des Intels, das zur Seite gelegt werden soll.'
                        ]
                    ],
                    'required' => ['type', 'identifier']
                ],
                'callable' => [self::class, 'executePersonaShelfWidget']
            ],
            [
                'name' => 'persona_unshelf_widget',
                'description' => 'Holt eine Akte, ein Profil oder ein Intel aus der Seitenablage (Shelf) zurück auf den Haupt-Arbeitsbereich. Nutze dies, wenn der Nutzer sagt "hol das Profil von X wieder hervor" oder "aus der Zwischenablage laden".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => ['persona', 'intel'],
                            'description' => 'Typ des Widgets ("persona" oder "intel").'
                        ],
                        'identifier' => [
                            'type' => 'string',
                            'description' => 'Der Suchbegriff/Name der Persona oder der Titel des Intels, das aus der Seitenablage geholt werden soll.'
                        ]
                    ],
                    'required' => ['type', 'identifier']
                ],
                'callable' => [self::class, 'executePersonaUnshelfWidget']
            ],
            [
                'name' => 'persona_transform_core',
                'description' => 'Verändert die visuelle Darstellung (den "Kern") des KI-Agenten in der Benutzeroberfläche. Nutze dies, wenn du dich lustig machst und z.B. sagst "Ich verwandle mich jetzt in Jarvis", indem du als target "jarvis" übergibst. Um zum normalen Globi zurückzukehren, übergib "default".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'target' => [
                            'type' => 'string',
                            'enum' => ['default', 'jarvis'],
                            'description' => 'Das Zielaussehen des Kerns: "jarvis" für die Jarvis-Hologramm-Ansicht, "default" für den normalen Seelenfunke-Kern.'
                        ]
                    ],
                    'required' => ['target']
                ],
                'callable' => [self::class, 'executePersonaTransformCore']
            ],
            [
                'name' => 'persona_visualize_camera',
                'description' => 'Öffnet oder schließt das lokale, futuristische Kamera-Livebild-Widget (Webcam). Aufzurufen, wenn der Nutzer die Kamera sehen oder öffnen möchte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'enum' => ['open', 'close'],
                            'description' => 'Soll die Kamera geöffnet ("open") oder geschlossen ("close") werden?'
                        ]
                    ],
                    'required' => ['action']
                ],
                'callable' => [self::class, 'executePersonaVisualizeCamera']
            ],
            [
                'name' => 'persona_visualize_org_chart',
                'description' => 'Generiert und visualisiert ein interaktives Organigramm (Strukturbaum) von Firmen, Behörden oder Ministerien im Top Secret Workspace.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'organization_name' => ['type' => 'string', 'description' => 'Name der Organisation (z.B. "Bundesregierung", "Seelenfunke GmbH")'],
                        'departments' => [
                            'type' => 'array',
                            'description' => 'Liste der Abteilungen oder Ministerien',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'description' => 'Name der Abteilung (z.B. "Verteidigungsministerium")'],
                                    'description' => ['type' => 'string', 'description' => 'Kurzbeschreibung der Aufgabe'],
                                    'members' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'name' => ['type' => 'string'],
                                                'role' => ['type' => 'string'],
                                                'image_url' => ['type' => 'string', 'description' => 'Optionale Bild-URL (echte URL!)']
                                            ]
                                        ]
                                    ]
                                ],
                                'required' => ['name', 'members']
                            ]
                        ]
                    ],
                    'required' => ['organization_name', 'departments']
                ],
                'callable' => [self::class, 'executePersonaVisualizeOrgChart']
            ]
        ];
    }

    // [AREA: IMAGE PROCESSING]
    private static function processPersonaImage($url, $name)
    {
        if (empty($url)) return $url;

        // Falls es schon eine lokale oder base64 URL ist, ignorieren
        if (str_starts_with($url, 'data:') || str_starts_with($url, url('/'))) {
            return $url;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36'
            ])->timeout(15)->get($url);

            if ($response->successful()) {
                $imgContent = $response->body();
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($imgContent);

                if (str_starts_with($mime, 'image/')) {
                    $ext = 'jpg';
                    if ($mime === 'image/png') $ext = 'png';
                    if ($mime === 'image/webp') $ext = 'webp';
                    if ($mime === 'image/gif') $ext = 'gif';

                    $filename = Str::slug($name) . '_' . time() . '.' . $ext;

                    // Speichern im Storage-Ordner (public für Web-Zugriff)
                    $dir = storage_path('app/public/agenten/ai/persona');
                    if (!file_exists($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    file_put_contents($dir . '/' . $filename, $imgContent);

                    return url('storage/agenten/ai/persona/' . $filename);
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Could not download persona image for UI: " . $e->getMessage());
        }

        // FALLBACK: Wenn Wikipedia-Link kaputt war oder 404, hole das Bild über die Wikipedia API
        if (str_contains($url, 'wikipedia') || str_contains($url, 'wikimedia') || empty($url)) {
            try {
                // Suche per Wikipedia API nach dem echten Namen und hole das Thumbnail
                $wikiUrl = "https://de.wikipedia.org/w/api.php?action=query&titles=" . urlencode($name) . "&prop=pageimages&format=json&pithumbsize=400";
                $wikiRes = \Illuminate\Support\Facades\Http::timeout(10)->get($wikiUrl);

                if ($wikiRes->successful()) {
                    $wikiData = $wikiRes->json();
                    if (isset($wikiData['query']['pages'])) {
                        foreach ($wikiData['query']['pages'] as $page) {
                            if (isset($page['thumbnail']['source'])) {
                                $thumbnailUrl = $page['thumbnail']['source'];
                                // Wir rufen die Funktion rekursiv mit der neuen Thumbnail URL auf, um sie zu speichern!
                                return self::processPersonaImage($thumbnailUrl, $name . '_wiki');
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning("Wikipedia API Fallback failed: " . $e->getMessage());
            }
        }

        return $url;
    }

    // [AREA: FRONTEND WIDGETS]
    public static function executePersonaVisualizeProfile(array $args)
    {
        if (!empty($args['image_url'])) {
            $args['image_url'] = self::processPersonaImage($args['image_url'], $args['name'] ?? 'Unbekannt');
        }

        return [
            'status' => 'success',
            'message' => "Das Personenprofil für '{$args['name']}' wurde visualisiert (als kleines Widget).",
            '_frontend_event' => [
                'name' => 'ai-show-persona',
                'detail' => [
                    'payload' => $args
                ]
            ]
        ];
    }

    // [AREA: PDF GENERATION]
    public static function executePersonaGeneratePdf(array $args)
    {
        $name = $args['name'] ?? 'Unbekannt';
        $action = $args['target_action'] ?? 'download';
        $recipient = $args['recipient_email'] ?? null;
        $agentName = session('current_ai_agent_name', 'System');

        // Robust image download
        if (!empty($args['image_url'])) {
            $processedUrl = self::processPersonaImage($args['image_url'], $name);
            // Für DomPDF ist ein lokaler Pfad oft besser als eine URL, aber URL geht auch.
            $args['image_url'] = $processedUrl;
        }

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.persona_dossier', [
                'persona' => $args
            ]);

            $dir = storage_path('app/public/dossiers');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'profil_' . Str::slug($name) . '_' . time() . '.pdf';
            $filePath = $dir . '/' . $filename;
            $pdf->save($filePath);

            $downloadUrl = url('storage/dossiers/' . $filename);

            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }

                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\AiHolidayPlanMail(
                    "Personenprofil: $name",
                    "Anbei finden Sie die angeforderte Personenakte.",
                    $agentName,
                    [$filePath]
                ));

                return [
                    'status' => 'success',
                    'message' => "Die Akte (PDF) wurde erfolgreich generiert und per E-Mail an $recipient versendet!",
                    'file_name' => $filename,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => 'Die Akte (PDF) wurde erfolgreich generiert!',
                    'pdf_url' => $downloadUrl,
                    'file_name' => $filename,
                    'note' => 'Das Dokument wurde generiert und kann vom Nutzer heruntergeladen werden.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("PDF Generation Error (Persona): " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Akte: ' . $e->getMessage()];
        }
    }

    public static function executePersonaCloseWidgets(array $args)
    {
        return [
            'status' => 'success',
            'message' => "Die entsprechenden Fenster wurden in der UI geschlossen.",
            '_frontend_event' => [
                'name' => 'ai-close-widgets',
                'detail' => [
                    'type' => $args['type'] ?? 'all',
                    'identifier' => $args['identifier'] ?? null
                ]
            ]
        ];
    }

    public static function executePersonaFocusWidget(array $args)
    {
        return [
            'status' => 'success',
            'message' => "Das gewünschte Element wurde für den Nutzer vergrößert/fokussiert.",
            '_frontend_event' => [
                'name' => 'ai-focus-widget',
                'detail' => [
                    'type' => $args['type'] ?? 'persona',
                    'identifier' => $args['identifier'] ?? ''
                ]
            ]
        ];
    }

    public static function executePersonaShelfWidget(array $args)
    {
        return [
            'status' => 'success',
            'message' => "Das Element wurde in die Seitenablage (Shelf) verschoben.",
            '_frontend_event' => [
                'name' => 'ai-shelf-widget',
                'detail' => [
                    'type' => $args['type'] ?? 'persona',
                    'identifier' => $args['identifier'] ?? ''
                ]
            ]
        ];
    }

    public static function executePersonaUnshelfWidget(array $args)
    {
        return [
            'status' => 'success',
            'message' => "Das Element wurde aus der Seitenablage (Shelf) wiederhergestellt.",
            '_frontend_event' => [
                'name' => 'ai-unshelf-widget',
                'detail' => [
                    'type' => $args['type'] ?? 'persona',
                    'identifier' => $args['identifier'] ?? ''
                ]
            ]
        ];
    }

    public static function executePersonaTransformCore(array $args)
    {
        $target = $args['target'] ?? 'default';
        $isJarvis = $target === 'jarvis';

        return [
            'status' => 'success',
            'message' => "Der KI-Kern hat sein Aussehen zu '$target' geändert.",
            '_frontend_event' => [
                'name' => 'ai-transform-core',
                'detail' => [
                    'target' => $target
                ]
            ]
        ];
    }

    public static function executePersonaToggleSecretWorkspace(array $args)
    {
        $action = $args['action'] ?? 'open';
        $isOpen = $action === 'open';

        return [
            'status' => 'success',
            'message' => "Der Top Secret OSINT Workspace wurde im Frontend " . ($isOpen ? "geöffnet" : "geschlossen") . ".",
            '_frontend_event' => [
                'name' => 'ai-toggle-secret-workspace',
                'detail' => [
                    'open' => $isOpen
                ]
            ]
        ];
    }

    // [AREA: KNOWLEDGE BASE / OSINT DB]
    public static function executePersonaSaveIntel(array $args)
    {
        $entityName = $args['entity_name'] ?? 'Unbekannte Entität';
        $intelContent = $args['intel_content'] ?? '';
        $customTags = $args['tags'] ?? [];
        $imageUrl = $args['image_url'] ?? '';

        if (!empty($imageUrl)) {
            $imageUrl = self::processPersonaImage($imageUrl, $entityName);
            $intelContent = "![Profilbild]({$imageUrl})\n\n" . $intelContent;
        }

        try {
            $category = AiKnowledgeBaseCategory::firstOrCreate(
                ['slug' => 'osint-intel'],
                ['name' => 'OSINT Intel']
            );

            $kb = AiKnowledgeBase::create([
                'title' => $entityName,
                'slug' => Str::slug($entityName . '-' . time()),
                'ai_knowledge_base_category_id' => $category->id,
                'content' => $intelContent,
                'is_published' => true,
            ]);

            $customTags[] = 'top_secret';
            $customTags = array_unique($customTags);

            $tagIds = [];
            foreach ($customTags as $tagName) {
                $tag = AiKnowledgeBaseTag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );
                $tagIds[] = $tag->id;
            }

            if (!empty($tagIds)) {
                $kb->tags()->sync($tagIds);
            }

            return [
                'status' => 'success',
                'message' => "Die OSINT-Daten für '$entityName' wurden dauerhaft in der Top Secret Datenbank gespeichert."
            ];
        } catch (\Exception $e) {
            Log::error("OSINT Save Intel Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Speichern in der DB: ' . $e->getMessage()];
        }
    }

    public static function executePersonaLinkEntities(array $args)
    {
        $source = $args['source_entity'] ?? '';
        $target = $args['target_entity'] ?? '';
        $relType = $args['relationship_type'] ?? '';
        $evidence = $args['evidence_summary'] ?? '';

        try {
            $category = AiKnowledgeBaseCategory::firstOrCreate(
                ['slug' => 'osint-relation'],
                ['name' => 'OSINT Relation']
            );

            $relationData = json_encode([
                'source' => $source,
                'target' => $target,
                'relationship' => $relType,
                'evidence' => $evidence
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $title = "Verbindung: $source -> $target";

            $kb = AiKnowledgeBase::create([
                'title' => $title,
                'slug' => Str::slug('relation-' . time() . '-' . Str::random(5)),
                'ai_knowledge_base_category_id' => $category->id,
                'content' => $relationData,
                'is_published' => true,
            ]);

            $tag = AiKnowledgeBaseTag::firstOrCreate(
                ['slug' => 'top_secret'],
                ['name' => 'top_secret']
            );
            $kb->tags()->sync([$tag->id]);

            return [
                'status' => 'success',
                'message' => "Die Verbindung zwischen '$source' und '$target' wurde als '$relType' im Netzwerk gespeichert."
            ];
        } catch (\Exception $e) {
            Log::error("OSINT Link Entities Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Verbindung: ' . $e->getMessage()];
        }
    }

    public static function executePersonaGetNetwork(array $args)
    {
        $filterEntity = $args['filter_entity'] ?? null;

        try {
            $category = AiKnowledgeBaseCategory::where('slug', 'osint-relation')->first();

            if (!$category) {
                return [
                    'status' => 'success',
                    'message' => 'Es wurden bisher keine Netzwerkverbindungen in der OSINT-Datenbank gespeichert.',
                    'network' => []
                ];
            }

            $query = AiKnowledgeBase::where('ai_knowledge_base_category_id', $category->id);

            // Fetch all relationships
            $relations = $query->get();
            $network = [];

            foreach ($relations as $rel) {
                $data = json_decode($rel->content, true);
                if (is_array($data)) {
                    if ($filterEntity) {
                        $s = strtolower($data['source'] ?? '');
                        $t = strtolower($data['target'] ?? '');
                        $f = strtolower($filterEntity);
                        if (str_contains($s, $f) || str_contains($t, $f)) {
                            $network[] = $data;
                        }
                    } else {
                        $network[] = $data;
                    }
                }
            }

            return [
                'status' => 'success',
                'message' => 'Das Netzwerk wurde erfolgreich aus der Datenbank geladen. Die Knoten-Kanten-Matrix ist im "network" Feld enthalten.',
                'network' => $network
            ];
        } catch (\Exception $e) {
            Log::error("OSINT Get Network Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Laden des Netzwerks: ' . $e->getMessage()];
        }
    }

    public static function executePersonaVisualizeIntel(array $args)
    {
        return [
            'status' => 'success',
            'message' => "Der Intel-Fakt '{$args['title']}' wurde auf dem String-Board visualisiert.",
            '_frontend_event' => [
                'name' => 'ai-show-intel',
                'detail' => [
                    'payload' => $args
                ]
            ]
        ];
    }

    public static function executePersonaDeleteIntel(array $args)
    {
        $title = $args['title'] ?? '';

        try {
            $kb = AiKnowledgeBase::where('title', $title)->first();
            if ($kb) {
                $kb->delete();
                return ['status' => 'success', 'message' => "Der Eintrag '$title' wurde dauerhaft aus der OSINT Datenbank gelöscht."];
            } else {
                return ['status' => 'error', 'message' => "Kein Eintrag mit dem exakten Titel '$title' gefunden."];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }

    public static function executePersonaFetchUrl(array $args)
    {
        $url = $args['url'] ?? '';
        if (empty($url)) {
            return ['status' => 'error', 'message' => 'Keine URL angegeben.'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url);
            if ($response->successful()) {
                $html = $response->body();
                // Simple text extraction: strip scripts and tags
                $html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
                $html = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $html);
                $text = strip_tags($html);
                // Clean up excessive whitespace
                $text = preg_replace('/\s+/', ' ', $text);

                // Truncate to avoid blowing up the context window
                $text = mb_substr(trim($text), 0, 15000);

                return [
                    'status' => 'success',
                    'message' => "Inhalt der URL erfolgreich abgerufen. Länge: " . strlen($text) . " Zeichen.",
                    'content' => $text
                ];
            } else {
                return ['status' => 'error', 'message' => "Fehler beim Abrufen der URL. HTTP Status: " . $response->status()];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => "Verbindung fehlgeschlagen: " . $e->getMessage()];
        }
    }
    public static function executePersonaVisualizeOrgChart(array $args)
    {
        return [
            'status' => 'success',
            'message' => "Das Organigramm für '{$args['organization_name']}' wurde visualisiert.",
            '_frontend_event' => [
                'name' => 'ai-show-org-chart',
                'detail' => [
                    'payload' => $args
                ]
            ]
        ];
    }

    public static function executePersonaVisualizeCamera(array $args)
    {
        $action = $args['action'] ?? 'open';
        $isOpen = $action === 'open';

        return [
            'status' => 'success',
            'message' => "Das Kamera-Widget wurde " . ($isOpen ? "geöffnet" : "geschlossen") . ".",
            '_frontend_event' => [
                'name' => 'ai-show-camera',
                'detail' => [
                    'open' => $isOpen
                ]
            ]
        ];
    }
}

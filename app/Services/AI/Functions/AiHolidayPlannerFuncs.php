<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

trait AiHolidayPlannerFuncs
{
    public static function getAiHolidayPlannerFuncsSchema(): array
    {
        return [
            [
                'name' => 'holiday_generate_pdf_plan',
                'description' => 'Generiert aus den recherchierten Daten ein professionelles Urlaubs-PDF (inkl. Logistik, Packliste, Sehenswürdigkeiten, Route). WICHTIG: Bevor du diese Funktion aufrufst, MUSST du prüfen, ob der Nutzer Termine in dem Reisezeitraum hat! Nutze dazu deine Kalender-Funktionen. Falls es dort WICHTIGE Termine gibt, plane die Reise nicht, sondern frage den Nutzer zuerst! Die gefundenen (unwichtigen) Termine trägst du hier in calendar_events_during_trip ein. Nutze diese Funktion zwingend, wenn der Nutzer einen Plan oder eine Zusammenfassung als PDF per E-Mail haben möchte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel der Reise, z.B. "Rom entdecken: Ein Wochenende in der ewigen Stadt".'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Ein motivierender Einleitungstext für die Reise.'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Startdatum der Reise (z.B. 10.08.2024).'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Enddatum der Reise.'
                        ],
                        'travel_logistics' => [
                            'type' => 'object',
                            'properties' => [
                                'start_address' => ['type' => 'string', 'description' => 'Von wo startet die Reise (Startadresse/Ort).'],
                                'destination_address' => ['type' => 'string', 'description' => 'Zieladresse der Reise.'],
                                'accommodation' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string', 'description' => 'Name des Hotels/Airbnbs'],
                                        'address' => ['type' => 'string', 'description' => 'Adresse der Unterkunft'],
                                        'details' => ['type' => 'string', 'description' => 'Zusatzinfos wie Check-In Zeit. WICHTIG: Füge hier ZWINGEND einen korrekten Google Maps Link ein! Format MUSS so sein: https://www.google.com/maps/search/?api=1&query=[Name+Ort]. Niemals goo.gl Links verwenden!']
                                    ]
                                ],
                                'route_stops' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'type' => ['type' => 'string', 'description' => 'Typ des Stopps, z.B. "Autofahrt", "Flug", "Zwischenstopp"'],
                                            'details' => ['type' => 'string', 'description' => 'Details. WICHTIG: Füge hier ZWINGEND einen korrekten Google Maps Link ein! Format MUSS so sein: https://www.google.com/maps/search/?api=1&query=[Name+Ort]. Niemals goo.gl Links verwenden!']
                                        ]
                                    ],
                                    'description' => 'Details zur Anreise und Zwischenstopps.'
                                ]
                            ]
                        ],
                        'trip_specific_packing_items' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Nur spezifische Gegenstände, die speziell für diese Reise benötigt werden (z.B. Wanderstöcke, UK-Adapter, dicker Schal). Die Standarddinge (Zahnbürste, Ausweis, Ladekabel) sind bereits im System hinterlegt!'
                        ],
                        'attractions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'description' => 'Name der Sehenswürdigkeit'],
                                    'address' => ['type' => 'string', 'description' => 'Adresse oder Ort'],
                                    'tips' => ['type' => 'string', 'description' => 'Insider-Tipps. WICHTIG: Füge hier ZWINGEND einen korrekten Google Maps Link ein! Format MUSS so sein: https://www.google.com/maps/search/?api=1&query=[Name+Ort]. Niemals goo.gl Links verwenden!']
                                ]
                            ],
                            'description' => 'Besondere Sehenswürdigkeiten und Tipps für die Reise.'
                        ],
                        'itinerary' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'day' => ['type' => 'string', 'description' => 'Tag (z.B. "Tag 1 (Mo, 10.08.)")'],
                                    'activities' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'time' => ['type' => 'string', 'description' => 'Uhrzeit, z.B. "09:00"'],
                                                'title' => ['type' => 'string', 'description' => 'Name der Aktivität, z.B. "Kolosseum"'],
                                                'description' => ['type' => 'string', 'description' => 'Kurzbeschreibung oder Adresse']
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            'description' => 'Der detaillierte Reiseverlauf.'
                        ],
                        'calendar_events_during_trip' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Termine aus dem Kalender des Nutzers, die in diesen Reisezeitraum fallen (als Erinnerung auf dem Plan).'
                        ],
                        'general_tips' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Allgemeine Hinweise, Tipps, und Ideen für den gesamten Urlaubsbereich (was man dort tun kann, kulinarische Highlights, Kultur).'
                        ],
                        'target_action' => [
                            'type' => 'string',
                            'description' => 'Was soll mit dem generierten Urlaubsplan passieren? "download" (öffnet Download-Dialog beim Nutzer im Browser) oder "email" (versendet die PDF per Mail an recipient_email).',
                            'enum' => ['download', 'email']
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail-Adresse des Empfängers. Wenn der Nutzer keine E-Mail nennt, lasse dieses Feld zwingend leer (null). Das System nutzt dann automatisch die Standard-E-Mail.'
                        ],
                        'design' => [
                            'type' => 'string',
                            'description' => 'Das visuelle Design der E-Mail. "seelenfunke" (inkl. Briefkopf, CI-Farben, Logo) oder "generic" (neutrales Design ohne Firmenbezug). Standardmäßig "seelenfunke", es sei denn, der Nutzer wünscht neutral.',
                            'enum' => ['seelenfunke', 'generic']
                        ]
                    ],
                    'required' => ['title', 'description', 'start_date', 'end_date', 'travel_logistics', 'itinerary', 'general_tips', 'target_action']
                ],
                'callable' => [self::class, 'executeHolidayGeneratePdfPlan']
            ]
        ];
    }

    public static function executeHolidayGeneratePdfPlan(array $args, $agent = null)
    {
        $title = $args['title'] ?? 'Urlaubsplan';
        $description = $args['description'] ?? '';
        $startDate = $args['start_date'] ?? null;
        $endDate = $args['end_date'] ?? null;
        $logistics = $args['travel_logistics'] ?? [];
        $tripSpecific = $args['trip_specific_packing_items'] ?? [];
        
        // Basis-Packliste (Spart AI-Tokens)
        $packingList = [
            'documents' => ['Personalausweis / Reisepass', 'Krankenversicherungskarte', 'Reisetickets & Buchungsbestätigungen', 'Bargeld & EC/Kreditkarte'],
            'medication' => ['Reiseapotheke (Schmerzmittel, Pflaster)', 'Persönliche Dauermedikamente'],
            'hygiene' => ['Zahnbürste & Zahnpasta', 'Duschgel, Shampoo & Spülung', 'Deodorant', 'Haarbürste / Kamm', 'Sonnencreme & Lippenbalsam', 'Handtuch / Waschlappen'],
            'clothing' => ['Ausreichend Unterwäsche & Socken', 'Schlafanzug / Nachthemd', 'Bequeme Reisekleidung', 'Wettergerechte Jacken & Schuhe'],
            'comfort' => ['Nackenhörnchen / Reisekissen', 'Ohrstöpsel & Schlafmaske', 'Eigene Trinkflasche', 'Kleiner Standspiegel'],
            'tech' => ['Mobiltelefon', 'Ladekabel / Powerbank', 'Kopfhörer', 'Mehrfachsteckdose'],
            'specific' => $tripSpecific
        ];
        $attractions = $args['attractions'] ?? [];
        $generalTips = $args['general_tips'] ?? [];
        $itinerary = $args['itinerary'] ?? [];
        $calendarEvents = $args['calendar_events_during_trip'] ?? [];
        $action = $args['target_action'] ?? 'download';
        $recipient = $args['recipient_email'] ?? null;
        $agentName = $agent ? $agent->name : session('current_ai_agent_name', 'Globi - Leiter Globale Planung');

        try {
            // Generate PDF using dompdf
            $pdf = Pdf::loadView('pdf.holiday_plan', [
                'title' => $title,
                'description' => $description,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'logistics' => $logistics,
                'packingList' => $packingList,
                'attractions' => $attractions,
                'generalTips' => $generalTips,
                'itinerary' => $itinerary,
                'calendarEvents' => $calendarEvents
            ]);

            // Ensure the directory exists
            $dir = storage_path('app/public/holidays');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            // Save PDF
            $filename = 'urlaubsplan_' . Str::slug($title) . '_' . time() . '.pdf';
            $filePath = $dir . '/' . $filename;
            $pdf->save($filePath);

            $downloadUrl = url('storage/holidays/' . $filename);

            if ($action === 'email') {
                if (empty($recipient)) {
                    $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                }

                $design = $args['design'] ?? 'seelenfunke';
                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Services\AI\Mails\AiHolidayPlanMail(
                    "Dein exklusiver Urlaubsplan: $title",
                    $description,
                    $agentName,
                    [$filePath],
                    $design
                ));

                return [
                    'status' => 'success',
                    'message' => "Urlaubsplan PDF erfolgreich generiert und per E-Mail an $recipient versendet!",
                    'file_name' => $filename,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => 'Urlaubsplan PDF erfolgreich generiert!',
                    'pdf_url' => $downloadUrl,
                    'file_name' => $filename,
                    'note' => 'Der Plan wurde erfolgreich generiert und kann vom Nutzer heruntergeladen werden.'
                ];
            }
        } catch (\Exception $e) {
            Log::error("PDF Generation Error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen des PDFs: ' . $e->getMessage()];
        }
    }
}

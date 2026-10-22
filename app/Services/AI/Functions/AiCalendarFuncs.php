<?php

namespace App\Services\AI\Functions;

use App\Mail\CalendarEventCreated;
use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait AiCalendarFuncs
{
    public static function getAiCalendarFuncsSchema(): array
    {
        return [
            [
                'name' => 'calendar_get_events',
                'description' => 'Sucht Termine im lokalen Kalender. WICHTIG: Das aktuelle Datum auf dem Server ist: ' . now()->format('Y-m-d H:i:s') . ' (' . now()->locale('de')->dayName . '). Wandle Aussagen wie "nächsten Dienstag" anhand dieses Datums selbstständig in echte Y-m-d Daten um. Stichworte: Was steht an, habe ich Termine, Kalender prüfen, was ist heute los, wann ist das Meeting.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Optional. Startdatum (Y-m-d H:i:s oder Y-m-d) ab wann gesucht werden soll.'
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'Optional. Enddatum (Y-m-d H:i:s oder Y-m-d) bis wann gesucht werden soll.'
                        ],
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Optional. Ein Suchbegriff im Titel oder der Beschreibung.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Optional. Maximale Anzahl an Terminen. Sende 1 für "Was ist der aller nächste Termin?".'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetCalendarEvents']
            ],
            [
                'name' => 'calendar_create_event',
                'description' => 'Erstellt einen neuen Termin im Kalender und kann ihn auf Wunsch direkt als umfangreiche .ics Kalenderdatei per E-Mail versenden. Nutze dieses Tool, wenn der User sagt "Trag mir ein", "Erstelle einen Termin", "Erstelle einen Termin und schick mir die ICS per Mail" etc.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Name/Titel des Termins.'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Startdatum in Y-m-d H:i:s Form. Beispiel: "2026-05-14 15:30:00". Wenn keine Zeit genannt, nimm 09:00:00 (oder 00:00:00 bei is_all_day=true).'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Optional. Enddatum in Y-m-d H:i:s Form. Wenn leer, wird Startdatum + 1 Stunde gesetzt.'
                        ],
                        'is_all_day' => [
                            'type' => 'boolean',
                            'description' => 'True, wenn es den ganzen Tag dauert, sonst False (Standard).'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optionale Notizen, Agenda oder Details zum Termin.'
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'Optional. Ort, Adresse oder Online-Meeting-Link zum Termin (wird in die Beschreibung und das LOCATION-Feld der ICS-Datei aufgenommen).'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Optionale Kategorie (z.B. "termin", "meeting", "geburtstag", "urlaub", "erinnerung", "wichtig"). Standard: "termin".'
                        ],
                        'reminder_minutes' => [
                            'type' => 'integer',
                            'description' => 'Optionale Erinnerung vor Terminbeginn in Minuten (z.B. 15, 30, 60, 1440 für 1 Tag vorher). Wird auch als VALARM in der ICS-Datei hinterlegt.'
                        ],
                        'recurrence' => [
                            'type' => 'string',
                            'description' => 'Optionale Wiederholung: "daily", "weekly", "monthly" oder "yearly".',
                            'enum' => ['daily', 'weekly', 'monthly', 'yearly']
                        ],
                        'recurrence_end_date' => [
                            'type' => 'string',
                            'description' => 'Optionales Enddatum der Wiederholungsserie im Format Y-m-d.'
                        ],
                        'send_ics_email' => [
                            'type' => 'boolean',
                            'description' => 'Setze auf true, wenn der Termin sofort als .ics Kalenderdatei per E-Mail versendet werden soll.'
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Optional (wenn send_ics_email=true): E-Mail-Adresse oder Kontaktname des Empfängers. Lass dieses Feld leer (null), wenn die ICS-Datei an Alina / die Standard-Admin-E-Mail geschickt werden soll. Erfinde NIEMALS eine E-Mail-Adresse!'
                        ],
                        'custom_email_message' => [
                            'type' => 'string',
                            'description' => 'Optionale persönliche Begleitnachricht für die E-Mail beim ICS-Versand.'
                        ]
                    ],
                    'required' => ['title', 'start_date']
                ],
                'callable' => [self::class, 'executeCreateCalendarEvent']
            ],
            [
                'name' => 'calendar_send_ics_email',
                'description' => 'Versendet einen bestehenden Termin, mehrere Termine eines Zeitraums (Sammel-ICS) ODER einen direkt angegebenen Termin als RFC-5545 .ics Kalenderdatei per E-Mail (inkl. Google-Kalender-Direktlink, VALARM-Erinnerung, Ort, Wiederholung und Ablage im Dateimanager). Stichworte: Sende Termin als ICS, Schick mir die ICS Datei per Mail, Kalendereinladung versenden, Termine exportieren und mailen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'Optional: Die exakte ID eines bestehenden Termins aus calendar_get_events.'
                        ],
                        'title_suche' => [
                            'type' => 'string',
                            'description' => 'Optional: Suchbegriff nach dem Titel eines bestehenden Termins (falls event_id nicht bekannt ist).'
                        ],
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Optional (für Zeitraum-/Sammel-Export mehrerer Termine in einer ICS-Datei): Startdatum (Y-m-d oder Y-m-d H:i:s).'
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'Optional (für Zeitraum-/Sammel-Export mehrerer Termine in einer ICS-Datei): Enddatum (Y-m-d oder Y-m-d H:i:s).'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Optional (für Direktversand oder Neuanlage): Titel des Termins, falls kein bestehender Termin gesucht wird.'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Optional (für Direktversand oder Neuanlage): Startdatum (Y-m-d H:i:s).'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Optional: Enddatum (Y-m-d H:i:s).'
                        ],
                        'is_all_day' => [
                            'type' => 'boolean',
                            'description' => 'Optional: Ganztägiger Termin (true/false).'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optional: Beschreibung / Notizen / Agenda für den Termin in der ICS-Datei.'
                        ],
                        'location' => [
                            'type' => 'string',
                            'description' => 'Optional: Ort, Adresse oder Meeting-Link für das LOCATION-Feld der ICS-Datei.'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Optional: Kategorie des Termins (z.B. "termin", "meeting", "wichtig").'
                        ],
                        'reminder_minutes' => [
                            'type' => 'integer',
                            'description' => 'Optional: Erinnerung vorab in Minuten (z.B. 15, 30, 60, 1440) für den VALARM-Block der ICS-Datei.'
                        ],
                        'recurrence' => [
                            'type' => 'string',
                            'description' => 'Optionale Wiederholung ("daily", "weekly", "monthly", "yearly").',
                            'enum' => ['daily', 'weekly', 'monthly', 'yearly']
                        ],
                        'create_in_calendar' => [
                            'type' => 'boolean',
                            'description' => 'Wenn ein neuer Termin über title + start_date übergeben wird: Soll er gleichzeitig auch fest im lokalen Kalender gespeichert werden? Standard: true.'
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Optional: E-Mail-Adresse(n) (Komma-separiert möglich) oder Name eines gespeicherten Kontakts. WICHTIG: Erfinde NIEMALS eine E-Mail-Adresse! Wenn nicht genannt, lass dieses Feld leer (null) – das System sendet die ICS-Datei dann automatisch an Alina / die Standard-Admin-E-Mail.'
                        ],
                        'custom_email_message' => [
                            'type' => 'string',
                            'description' => 'Optionale persönliche Nachricht im E-Mail-Text.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeSendCalendarIcsEmail']
            ],
            [
                'name' => 'calendar_update_event',
                'description' => 'Verändert einen bestehenden Termin (verschieben, umbenennen, Details ändern) und kann optional die aktualisierte .ics Datei per E-Mail versenden. Frage erst die IDs mit calendar_get_events ab, bevor du dieses Tool nutzt!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'Die exakte ID (id Spalte) des Termins, den du verschieben/ändern willst.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Neuer Titel des Termins (falls geändert).'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Neues Startdatum (Y-m-d H:i:s).'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Neues Enddatum (Y-m-d H:i:s).'
                        ],
                        'is_all_day' => [
                            'type' => 'boolean',
                            'description' => 'Ganztägig (true/false).'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Neue Beschreibung oder Notizen.'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Neue Kategorie.'
                        ],
                        'reminder_minutes' => [
                            'type' => 'integer',
                            'description' => 'Erinnerung in Minuten vor Terminbeginn.'
                        ],
                        'recurrence' => [
                            'type' => 'string',
                            'description' => 'Wiederholung ("daily", "weekly", "monthly", "yearly").'
                        ],
                        'send_ics_email' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, wird nach dem Update direkt die aktualisierte .ics Datei per E-Mail versendet.'
                        ],
                        'recipient_email' => [
                            'type' => 'string',
                            'description' => 'Optionaler Empfänger für das ICS-Update (leer lassen für Standard-Admin-E-Mail).'
                        ]
                    ],
                    'required' => ['event_id']
                ],
                'callable' => [self::class, 'executeUpdateCalendarEvent']
            ],
            [
                'name' => 'calendar_delete_event',
                'description' => 'Löscht einen Termin dauerhaft. Stichworte: Termin absagen, lösche das Meeting aus dem Kalender, Termin entfernen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'Die ID des zu löschenden Termins. Wenn nicht bekannt, sende stattdessen title_suche.'
                        ],
                        'title_suche' => [
                            'type' => 'string',
                            'description' => 'Optional (wenn ID unbekannt): Suche den Termin anhand dieses ungenauen Namens und lösche den ersten Treffer.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteCalendarEvent']
            ]
        ];
    }

    public static function executeGetCalendarEvents(array $args)
    {
        try {
            $dateFrom = $args['date_from'] ?? null;
            $dateTo = $args['date_to'] ?? null;
            $keyword = $args['keyword'] ?? null;
            $limit = $args['limit'] ?? null;

            $query = ManagementCalendarEvent::query()->orderBy('start_date', 'asc');

            if ($dateFrom) {
                $query->where('start_date', '>=', Carbon::parse($dateFrom));
            } else {
                // Wenn nichts angegeben, default auf ab Jetzt
                $query->where('start_date', '>=', now()->startOfDay());
            }

            if ($dateTo) {
                // Ende des Tages, wenn nur Y-m-d
                $end = Carbon::parse($dateTo);
                if (strlen($dateTo) <= 10) $end->endOfDay();
                $query->where('start_date', '<=', $end);
            }

            if ($keyword) {
                $escapedKeyword = addcslashes(trim((string)$keyword), '%_\\');
                if ($escapedKeyword !== '') {
                    $query->where(function($q) use ($escapedKeyword) {
                        $q->where('title', 'like', "%{$escapedKeyword}%")
                          ->orWhere('description', 'like', "%{$escapedKeyword}%");
                    });
                }
            }

            if ($limit) {
                $query->limit(max(1, min(100, (int)$limit)));
            } else {
                // Hardcap, um zu viele Daten zu verhindern
                $query->limit(50);
            }

            $events = $query->get(['id', 'title', 'start_date', 'end_date', 'is_all_day', 'category', 'description', 'recurrence', 'reminder_minutes']);

            if ($events->isEmpty()) {
                 return [
                    'status' => 'success',
                    'message' => 'Es wurden keine Termine in dem angegebenen Zeitraum/Suchbegriff gefunden.'
                ];
            }

            $formattedEvents = $events->map(function ($event) {
                $data = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'is_all_day' => $event->is_all_day,
                    'category' => $event->category,
                    'description' => $event->description,
                    'recurrence' => $event->recurrence,
                    'reminder_minutes' => $event->reminder_minutes,
                ];

                if ($event->is_all_day) {
                    $data['date_info'] = "Gilt für den ganzen Tag: " . Carbon::parse($event->start_date)->format('Y-m-d');
                    $data['AI_INSTRUCTION'] = "Lies keine Uhrzeiten vor! Erwähne diesen Termin nur beiläufig als Tagesereignis.";
                } else {
                    $data['start'] = Carbon::parse($event->start_date)->format('Y-m-d H:i');
                    $data['end'] = $event->end_date ? Carbon::parse($event->end_date)->format('Y-m-d H:i') : null;
                }
                return $data;
            });

            return [
                'status' => 'success',
                'events_count' => $events->count(),
                'upcoming_events' => $formattedEvents->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abrufen der Termine: ' . $e->getMessage()];
        }
    }

    public static function executeCreateCalendarEvent(array $args, $agent = null)
    {
        try {
            if (empty($args['title']) || empty($args['start_date'])) {
                return ['status' => 'error', 'message' => 'Titel und Startdatum (start_date) sind zwingend erforderlich!'];
            }

            $start = Carbon::parse($args['start_date']);
            $isAllDay = filter_var($args['is_all_day'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (!empty($args['end_date'])) {
                 $end = Carbon::parse($args['end_date']);
                 if ($end->lessThan($start)) {
                     $end = $start->copy()->addHour();
                 }
            } else {
                 $end = $start->copy()->addHour(); // Default 1 Hour
                 if ($isAllDay) $end = $start->copy()->endOfDay();
            }

            $description = $args['description'] ?? null;
            $location = !empty($args['location']) ? trim((string) $args['location']) : null;
            if ($location && (!$description || !str_contains($description, $location))) {
                $description = $description ? ($description . "\nOrt: " . $location) : ("Ort: " . $location);
            }

            $event = ManagementCalendarEvent::create([
                'title' => substr($args['title'], 0, 255),
                'start_date' => $start,
                'end_date' => $end,
                'is_all_day' => $isAllDay,
                'description' => $description,
                'category' => !empty($args['category']) ? strtolower(trim((string) $args['category'])) : 'termin',
                'reminder_minutes' => isset($args['reminder_minutes']) && is_numeric($args['reminder_minutes']) ? (int) $args['reminder_minutes'] : null,
                'recurrence' => $args['recurrence'] ?? null,
                'recurrence_end_date' => !empty($args['recurrence_end_date']) ? Carbon::parse($args['recurrence_end_date'])->format('Y-m-d') : null,
                'ics_uid' => (string) Str::uuid() . '@mein-seelenfunke.de',
            ]);

            $msg = "Der Termin '{$event->title}' wurde für den {$start->format('d.m.Y H:i')} angelegt.";
            $icsResult = null;

            $sendIcs = filter_var($args['send_ics_email'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || !empty($args['recipient_email']);

            if ($sendIcs) {
                $icsResult = self::executeSendCalendarIcsEmail([
                    'event_id' => $event->id,
                    'location' => $location,
                    'recipient_email' => $args['recipient_email'] ?? null,
                    'custom_email_message' => $args['custom_email_message'] ?? null,
                ], $agent);

                if (($icsResult['status'] ?? '') === 'success') {
                    $msg .= ' ' . ($icsResult['message'] ?? 'Die ICS-Datei wurde per E-Mail versendet.');
                } else {
                    $msg .= ' Hinweis zum ICS-Versand: ' . ($icsResult['message'] ?? 'Konnte nicht gesendet werden.');
                }
            }

            return [
                'status' => 'success',
                'message' => $msg,
                'event_id' => $event->id,
                'ics_email_sent' => ($icsResult['status'] ?? '') === 'success',
                'ics_workspace_path' => $icsResult['workspace_file_path'] ?? null,
                'ics_delivery' => $icsResult,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Konnte Termin nicht anlegen: ' . $e->getMessage()];
        }
    }

    public static function executeSendCalendarIcsEmail(array $args, $agent = null)
    {
        try {
            $events = collect();
            $location = !empty($args['location']) ? trim((string) $args['location']) : null;
            $createdInDb = false;

            // 1. Einzelner Termin per event_id
            if (!empty($args['event_id'])) {
                $found = ManagementCalendarEvent::find($args['event_id']);
                if ($found) {
                    $events->push($found);
                }
            }

            // 2. Suche per title_suche / keyword
            if ($events->isEmpty() && (!empty($args['title_suche']) || !empty($args['keyword']))) {
                $search = trim((string) ($args['title_suche'] ?? $args['keyword']));
                $escaped = addcslashes($search, '%_\\');
                $query = ManagementCalendarEvent::where(function ($q) use ($escaped) {
                    $q->where('title', 'like', "%{$escaped}%")
                      ->orWhere('description', 'like', "%{$escaped}%");
                })->orderBy('start_date', 'desc');

                $found = $query->first();
                if ($found) {
                    $events->push($found);
                }
            }

            // 3. Zeitraum-Export (mehrere Termine in einer ICS-Datei)
            if ($events->isEmpty() && (!empty($args['date_from']) || !empty($args['date_to']))) {
                $query = ManagementCalendarEvent::query()->orderBy('start_date', 'asc');
                if (!empty($args['date_from'])) {
                    $query->where('start_date', '>=', Carbon::parse($args['date_from']));
                } else {
                    $query->where('start_date', '>=', now()->startOfDay());
                }
                if (!empty($args['date_to'])) {
                    $end = Carbon::parse($args['date_to']);
                    if (strlen((string) $args['date_to']) <= 10) {
                        $end->endOfDay();
                    }
                    $query->where('start_date', '<=', $end);
                }
                $events = $query->limit(50)->get();
            }

            // 4. Ad-hoc / Neuer Termin über title + start_date
            if ($events->isEmpty() && !empty($args['title']) && !empty($args['start_date'])) {
                $start = Carbon::parse($args['start_date']);
                $isAllDay = filter_var($args['is_all_day'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (!empty($args['end_date'])) {
                    $end = Carbon::parse($args['end_date']);
                    if ($end->lessThan($start)) {
                        $end = $start->copy()->addHour();
                    }
                } else {
                    $end = $isAllDay ? $start->copy()->endOfDay() : $start->copy()->addHour();
                }

                $description = $args['description'] ?? null;
                if ($location && (!$description || !str_contains($description, $location))) {
                    $description = $description ? ($description . "\nOrt: " . $location) : ("Ort: " . $location);
                }

                $attrs = [
                    'title' => substr((string) $args['title'], 0, 255),
                    'start_date' => $start,
                    'end_date' => $end,
                    'is_all_day' => $isAllDay,
                    'description' => $description,
                    'category' => !empty($args['category']) ? strtolower(trim((string) $args['category'])) : 'termin',
                    'reminder_minutes' => isset($args['reminder_minutes']) && is_numeric($args['reminder_minutes']) ? (int) $args['reminder_minutes'] : 30,
                    'recurrence' => $args['recurrence'] ?? null,
                    'ics_uid' => (string) Str::uuid() . '@mein-seelenfunke.de',
                ];

                $shouldCreate = !array_key_exists('create_in_calendar', $args)
                    || filter_var($args['create_in_calendar'], FILTER_VALIDATE_BOOLEAN);

                if ($shouldCreate) {
                    $ev = ManagementCalendarEvent::create($attrs);
                    $createdInDb = true;
                } else {
                    $ev = new ManagementCalendarEvent($attrs);
                }
                $events->push($ev);
            }

            // Fallback: Wenn nur title ohne start_date übergeben wurde, versuche noch einmal in bestehenden Terminen zu suchen
            if ($events->isEmpty() && !empty($args['title'])) {
                $escaped = addcslashes(trim((string) $args['title']), '%_\\');
                $found = ManagementCalendarEvent::where('title', 'like', "%{$escaped}%")
                    ->orderBy('start_date', 'desc')
                    ->first();
                if ($found) {
                    $events->push($found);
                }
            }

            if ($events->isEmpty()) {
                return [
                    'status' => 'error',
                    'message' => 'Es wurde kein passender Termin gefunden. Übergib entweder eine event_id, einen Suchbegriff (title_suche), einen Zeitraum (date_from/date_to) oder title + start_date für einen neuen Termin.'
                ];
            }

            // Optionale Überschreibungen (z.B. Ort, Beschreibung, Erinnerung) auf den Haupttermin anwenden
            $primaryEvent = $events->first();
            if ($location) {
                $primaryEvent->location = $location;
            }
            if (isset($args['reminder_minutes']) && is_numeric($args['reminder_minutes'])) {
                $primaryEvent->reminder_minutes = (int) $args['reminder_minutes'];
            }

            // Empfänger auflösen
            $recipientsResult = self::resolveCalendarRecipients($args['recipient_email'] ?? null);
            if (($recipientsResult['status'] ?? '') === 'error') {
                return $recipientsResult;
            }
            $recipients = $recipientsResult['emails'];

            $agentName = $agent ? $agent->name : ($args['agent_name'] ?? 'Funkira');
            $customMessage = $args['custom_email_message'] ?? null;

            // ICS-Datei generieren und im Workspace-Dateimanager ablegen
            $slug = $events->count() > 1
                ? 'kalender_export_' . now()->format('Y-m-d_His')
                : ('termin_' . (Str::slug($primaryEvent->title ?? 'event') ?: 'event') . '_' . Carbon::parse($primaryEvent->start_date)->format('Y-m-d'));
            $icsFilename = "{$slug}.ics";

            $icsContent = CalendarEventCreated::buildIcsForEvents($events, [
                'location' => $location,
                'attendee_email' => $recipients[0] ?? null,
                'agent_name' => $agentName,
            ]);

            $workspaceRelativePath = 'agenten/workspace/Dokumente/Kalender/' . $icsFilename;
            try {
                Storage::disk('public')->put($workspaceRelativePath, $icsContent);
                if ( array_key_exists('workspace', config('filesystems.disks', [])) ) {
                    Storage::disk('workspace')->put($workspaceRelativePath, $icsContent);
                }
            } catch (\Throwable $storageEx) {
                // Storage ist optional, E-Mail-Versand hat Priorität
            }

            $additionalEvents = $events->slice(1)->values()->all();

            foreach ($recipients as $email) {
                Mail::to($email)->send(new CalendarEventCreated(
                    $primaryEvent,
                    $additionalEvents,
                    $customMessage,
                    $agentName,
                    $location,
                    $email,
                    $icsFilename
                ));
            }

            $recipientStr = implode(', ', $recipients);
            $count = $events->count();
            $summaryTitle = $count > 1
                ? "{$count} Termine (ab '" . $primaryEvent->title . "')"
                : "Termin '{$primaryEvent->title}' (" . Carbon::parse($primaryEvent->start_date)->format('d.m.Y H:i') . " Uhr)";

            return [
                'status' => 'success',
                'message' => "Die ICS-Kalenderdatei ({$icsFilename}) für {$summaryTitle} wurde erfolgreich per E-Mail an {$recipientStr} versendet" . ($createdInDb ? ' und im Kalender eingetragen.' : '.'),
                'recipients' => $recipients,
                'events_count' => $count,
                'ics_filename' => $icsFilename,
                'workspace_file_path' => $workspaceRelativePath,
                'ics_workspace_path' => $workspaceRelativePath,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Versenden der ICS-Kalenderdatei: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Löst E-Mail-Empfänger für Kalender-Einladungen intelligent auf:
     * - Leer / null -> Alina / Standard-Admin-E-Mail
     * - Echte E-Mail(s) -> Validierung gegen Dummy-Domains
     * - Kontaktname (z.B. "Alina", "Max") -> Suche in ManagementContact & Admin
     */
    protected static function resolveCalendarRecipients(?string $rawRecipient): array
    {
        $raw = trim((string) $rawRecipient);

        $defaultEmail = function_exists('shop_setting')
            ? (shop_setting('owner_email') ?: shop_setting('company_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de')
            : (config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de');

        if ($raw === '' || in_array(strtolower($raw), ['alina', 'mir', 'mich', 'meine mail', 'admin', 'standard'], true)) {
            $adminAlina = \App\Models\Admin\Admin::where('first_name', 'like', '%Alina%')->first();
            $target = ($adminAlina && !empty($adminAlina->email)) ? $adminAlina->email : $defaultEmail;
            return ['status' => 'success', 'emails' => [$target]];
        }

        $parts = array_filter(array_map('trim', preg_split('/[,;]+/', $raw)));
        $resolvedEmails = [];

        foreach ($parts as $part) {
            $lower = strtolower($part);
            if (str_contains($lower, 'example.') || str_contains($lower, 'test.com') || str_contains($lower, 'dummy') || str_contains($lower, 'domain.de') || str_contains($lower, 'platzhalter')) {
                return [
                    'status' => 'error',
                    'message' => "Ungültige Platzhalter-E-Mail '{$part}'. Bitte erfinde keine E-Mail-Adressen! Lass recipient_email leer (null), um an die Standard-Admin-E-Mail zu senden."
                ];
            }

            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $resolvedEmails[] = $part;
                continue;
            }

            // Versuche Kontaktname in ManagementContact aufzulösen
            if (class_exists(ManagementContact::class)) {
                $escaped = addcslashes($part, '%_\\');
                $contact = ManagementContact::where('first_name', 'like', "%{$escaped}%")
                    ->orWhere('last_name', 'like', "%{$escaped}%")
                    ->orWhereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?", ["%{$escaped}%"])
                    ->first();

                if ($contact && !empty($contact->email) && filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                    $resolvedEmails[] = $contact->email;
                    continue;
                }
            }

            // Versuche in Admin-Tabelle aufzulösen
            $admin = \App\Models\Admin\Admin::where('first_name', 'like', '%' . addcslashes($part, '%_\\') . '%')
                ->orWhere('last_name', 'like', '%' . addcslashes($part, '%_\\') . '%')
                ->first();
            if ($admin && !empty($admin->email) && filter_var($admin->email, FILTER_VALIDATE_EMAIL)) {
                $resolvedEmails[] = $admin->email;
                continue;
            }

            return [
                'status' => 'error',
                'message' => "Für '{$part}' wurde keine gültige E-Mail-Adresse in den Kontakten gefunden. Bitte gib eine gültige E-Mail-Adresse an oder lass recipient_email leer."
            ];
        }

        $resolvedEmails = array_values(array_unique($resolvedEmails));
        if (empty($resolvedEmails)) {
            $resolvedEmails = [$defaultEmail];
        }

        return ['status' => 'success', 'emails' => $resolvedEmails];
    }

    public static function executeUpdateCalendarEvent(array $args, $agent = null)
    {
        try {
             if (empty($args['event_id'])) {
                return ['status' => 'error', 'message' => 'Du musst eine event_id angeben. Hole sie dir vorher mit calendar_get_events.'];
            }

            $event = ManagementCalendarEvent::find($args['event_id']);
            if (!$event) {
                return ['status' => 'error', 'message' => 'Termin mit dieser ID nicht in der Kalender-Datenbank gefunden.'];
            }

            if (!empty($args['title'])) $event->title = substr($args['title'], 0, 255);
            if (!empty($args['start_date'])) $event->start_date = Carbon::parse($args['start_date']);
            if (!empty($args['end_date'])) {
                $parsedEnd = Carbon::parse($args['end_date']);
                if ($event->start_date && $parsedEnd->lessThan($event->start_date)) {
                    $parsedEnd = Carbon::parse($event->start_date)->copy()->addHour();
                }
                $event->end_date = $parsedEnd;
            }
            if (array_key_exists('is_all_day', $args)) {
                $event->is_all_day = filter_var($args['is_all_day'], FILTER_VALIDATE_BOOLEAN);
            }
            if (array_key_exists('description', $args)) {
                $event->description = $args['description'];
            }
            if (!empty($args['category'])) {
                $event->category = strtolower(trim((string) $args['category']));
            }
            if (array_key_exists('reminder_minutes', $args)) {
                $event->reminder_minutes = is_numeric($args['reminder_minutes']) ? (int) $args['reminder_minutes'] : null;
            }
            if (array_key_exists('recurrence', $args)) {
                $event->recurrence = $args['recurrence'];
            }

            $event->save();

            $msg = "Der Termin '{$event->title}' wurde erfolgreich aktualisiert.";
            if (!empty($args['send_ics_email'])) {
                $icsRes = self::executeSendCalendarIcsEmail([
                    'event_id' => $event->id,
                    'recipient_email' => $args['recipient_email'] ?? null,
                ], $agent);
                if (($icsRes['status'] ?? '') === 'success') {
                    $msg .= ' ' . $icsRes['message'];
                }
            }

            return [
                'status' => 'success',
                'message' => $msg
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Konnte Termin nicht aktualisieren: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteCalendarEvent(array $args)
    {
        try {
            if (empty($args['event_id']) && empty($args['title_suche'])) {
                 return ['status' => 'error', 'message' => 'Bitte entweder event_id oder title_suche übergeben.'];
            }

            $event = null;
            if (!empty($args['event_id'])) {
                $event = ManagementCalendarEvent::find($args['event_id']);
            } elseif (!empty($args['title_suche'])) {
                $titleSuche = trim((string)$args['title_suche']);
                if (mb_strlen($titleSuche) < 3) {
                    return ['status' => 'error', 'message' => 'Der Suchbegriff für title_suche muss mindestens 3 Zeichen lang sein.'];
                }
                $escapedTitle = addcslashes($titleSuche, '%_\\');
                $event = ManagementCalendarEvent::where('title', 'LIKE', '%' . $escapedTitle . '%')->first();
            }

            if (!$event) {
                 return ['status' => 'error', 'message' => 'Der zu löschende Termin konnte in der Datenbank nicht gefunden werden.'];
            }

            $title = $event->title;
            $event->delete();

            return [
                'status' => 'success',
                'message' => "Der Termin '{$title}' wurde restlos aus dem Calender gelöscht."
            ];

         } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Konnte Termin nicht löschen: ' . $e->getMessage()];
        }
    }
}

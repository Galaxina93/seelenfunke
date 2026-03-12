<?php

namespace App\Services\AI\Functions;

use App\Models\CalendarEvent;
use Carbon\Carbon;

trait CalendarFunctions
{
    public static function getCalendarFunctionsSchema(): array
    {
        return [
            [
                'name' => 'get_calendar_events',
                'description' => 'Sucht Termine im lokalen Kalender. WICHTIG: Das aktuelle Datum auf dem Server ist: ' . now()->format('Y-m-d H:i:s') . ' (' . now()->locale('de')->dayName . '). Wandle Aussagen wie "nächsten Dienstag" anhand dieses Datums selbstständig in echte Y-m-d Daten um.',
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
                'name' => 'create_calendar_event',
                'description' => 'Erstellt einen neuen Termin im lokalen Kalender. Nutze dieses Tool, wenn der User sagt "Trag mir ein", "Erstelle einen Termin" etc.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Name des Termins.'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Startdatum in Y-m-d H:i:s Form. Beispiel: "2026-05-14 15:30:00". Wenn keine Zeit genannt, nimm 00:00:00.'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Optional. Enddatum in Y-m-d H:i:s Form. Wenn leer, setze es auf Startdatum + 1 Stunde.'
                        ],
                        'is_all_day' => [
                            'type' => 'boolean',
                            'description' => 'True, wenn es den ganzen Tag dauert, sonst False (Standard).'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optionale Notizen zum Termin.'
                        ]
                    ],
                    'required' => ['title', 'start_date']
                ],
                'callable' => [self::class, 'executeCreateCalendarEvent']
            ],
            [
                'name' => 'update_calendar_event',
                'description' => 'Verändert einen bestehenden Termin (verschieben, umbenennen). Du FRAST erst die IDs mit get_calendar_events ab, bevor du dieses Tool nutzt!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'Die exakte ID (id Spalte) des Termins, den du verschieben/ändern willst. Keine Namen!'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Muss nur gesendet werden, wenn der Name geändert wird.'
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Muss nur gesendet werden, wenn verschoben wird (Y-m-d H:i:s).'
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'Muss nur gesendet werden, wenn das Ende verschoben wird (Y-m-d H:i:s).'
                        ]
                    ],
                    'required' => ['event_id']
                ],
                'callable' => [self::class, 'executeUpdateCalendarEvent']
            ],
            [
                'name' => 'delete_calendar_event',
                'description' => 'Löscht einen Termin dauerhaft.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'event_id' => [
                            'type' => 'string',
                            'description' => 'Die ID des zu löschenden Termins. Wenn nicht bekannt, sende stattdessen titel_suche.'
                        ],
                        'title_suche' => [
                            'type' => 'string',
                            'description' => 'Optional (wenn ID unbekannt): Suche den Termin anhand dieses ungenauen Namens und lösche den ersten Treffer.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteCalendarEvent']
            ],
        ];
    }

    public static function executeGetCalendarEvents(array $args)
    {
        try {
            $dateFrom = $args['date_from'] ?? null;
            $dateTo = $args['date_to'] ?? null;
            $keyword = $args['keyword'] ?? null;
            $limit = $args['limit'] ?? null;
            
            $query = CalendarEvent::query()->orderBy('start_date', 'asc');
            
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
                $query->where(function($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                      ->orWhere('description', 'like', "%{$keyword}%");
                });
            }

            if ($limit) {
                $query->limit($limit);
            } else {
                // Hardcap, um zu viele Daten zu verhindern
                $query->limit(50);
            }

            $events = $query->get(['id', 'title', 'start_date', 'end_date', 'is_all_day', 'category', 'description']);
            
            if ($events->isEmpty()) {
                 return [
                    'status' => 'success',
                    'message' => 'Es wurden keine Termine in dem angegebenen Zeitraum/Suchbegriff gefunden.'
                ];
            }

            return [
                'status' => 'success',
                'events_count' => $events->count(),
                'upcoming_events' => $events->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abrufen der Termine: ' . $e->getMessage()];
        }
    }

    public static function executeCreateCalendarEvent(array $args)
    {
        try {
            if (empty($args['title']) || empty($args['start_date'])) {
                return ['status' => 'error', 'message' => 'Titel und Startdatum (start_date) sind zwingend erforderlich!'];
            }

            $start = Carbon::parse($args['start_date']);
            $isAllDay = filter_var($args['is_all_day'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (!empty($args['end_date'])) {
                 $end = Carbon::parse($args['end_date']);
            } else {
                 $end = $start->copy()->addHour(); // Default 1 Hour
                 if ($isAllDay) $end = $start->copy()->endOfDay();
            }

            $event = CalendarEvent::create([
                'title' => substr($args['title'], 0, 255),
                'start_date' => $start,
                'end_date' => $end,
                'is_all_day' => $isAllDay,
                'description' => $args['description'] ?? null,
                'category' => 'termin', // User requested Default
                'reminder_minutes' => null, // User requested Default: Aus
                'recurrence' => null, // Default
            ]);

            return [
                'status' => 'success',
                'message' => "Der Termin '{$event->title}' wurde für den {$start->format('d.m.Y H:i')} angelegt.",
                'event_id' => $event->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Konnte Termin nicht anlegen: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateCalendarEvent(array $args)
    {
        try {
             if (empty($args['event_id'])) {
                return ['status' => 'error', 'message' => 'Du musst eine event_id angeben. Hole sie dir vorher mit get_calendar_events.'];
            }

            $event = CalendarEvent::find($args['event_id']);
            if (!$event) {
                return ['status' => 'error', 'message' => 'Termin mit dieser ID nicht in der Kalender-Datenbank gefunden.'];
            }

            if (!empty($args['title'])) $event->title = substr($args['title'], 0, 255);
            if (!empty($args['start_date'])) $event->start_date = Carbon::parse($args['start_date']);
            if (!empty($args['end_date'])) $event->end_date = Carbon::parse($args['end_date']);

            $event->save();

            return [
                'status' => 'success',
                'message' => "Der Termin '{$event->title}' wurde erfolgreich aktualisiert/verschoben."
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
                $event = CalendarEvent::find($args['event_id']);
            } elseif (!empty($args['title_suche'])) {
                $event = CalendarEvent::where('title', 'LIKE', '%' . $args['title_suche'] . '%')->first();
            }

            if (!$event) {
                 return ['status' => 'error', 'message' => 'Der zu löschende Termin konnte in der Datenbank nicht gefunden werden.'];
            }

            $title = $event->title;
            $event->delete();

            return [
                'status' => 'success',
                'message' => "Der Termin '{$title}' wurde restlos aus dem Kalender gelöscht."
            ];

         } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Konnte Termin nicht löschen: ' . $e->getMessage()];
        }
    }
}

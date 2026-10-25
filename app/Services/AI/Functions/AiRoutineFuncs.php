<?php

namespace App\Services\AI\Functions;

use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementDayRoutineLog;
use App\Models\Management\ManagementDayRoutineStep;
use Carbon\Carbon;
use Illuminate\Support\Str;

trait AiRoutineFuncs
{
    /**
     * Sicherheits-Schranke: Tagesroutinen sind nur für Administratoren und interne Mitarbeiter zugänglich.
     */
    private static function assertRoutineStaffAccess(): ?array
    {
        if (\App\Services\AI\AiAuthHelper::check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: Tagesroutinen sind nur für Mitarbeiter und Administratoren abrufbar.'
            ];
        }
        return null;
    }

    /**
     * Parst natürliche deutsche Datumsangaben ("heute", "gestern", "montag", "25.10.2026", "2026-10-25") zu Y-m-d.
     */
    public static function parseRoutineDate(?string $input): string
    {
        if (empty($input)) {
            return Carbon::now()->format('Y-m-d');
        }

        $clean = mb_strtolower(trim($input));
        $now = Carbon::now();

        if (in_array($clean, ['heute', 'today', 'jetzt', 'aktuell'], true)) {
            return $now->format('Y-m-d');
        }
        if (in_array($clean, ['gestern', 'yesterday'], true)) {
            return $now->copy()->subDay()->format('Y-m-d');
        }
        if ($clean === 'vorgestern') {
            return $now->copy()->subDays(2)->format('Y-m-d');
        }
        if (in_array($clean, ['morgen', 'tomorrow'], true)) {
            return $now->copy()->addDay()->format('Y-m-d');
        }

        $weekdayMap = [
            'montag' => Carbon::MONDAY,
            'mo' => Carbon::MONDAY,
            'dienstag' => Carbon::TUESDAY,
            'di' => Carbon::TUESDAY,
            'mittwoch' => Carbon::WEDNESDAY,
            'mi' => Carbon::WEDNESDAY,
            'donnerstag' => Carbon::THURSDAY,
            'do' => Carbon::THURSDAY,
            'freitag' => Carbon::FRIDAY,
            'fr' => Carbon::FRIDAY,
            'samstag' => Carbon::SATURDAY,
            'sa' => Carbon::SATURDAY,
            'sonntag' => Carbon::SUNDAY,
            'so' => Carbon::SUNDAY,
        ];

        $strippedWeekday = trim(str_replace(['letzten', 'letzter', 'diesen', 'dieser', 'am'], '', $clean));
        if (isset($weekdayMap[$strippedWeekday])) {
            $isoDay = $weekdayMap[$strippedWeekday];
            $monday = $now->copy()->startOfWeek(Carbon::MONDAY);
            $target = $monday->copy()->addDays($isoDay - 1);
            if (str_contains($clean, 'letzt') && $target->gt($now)) {
                $target->subWeek();
            }
            return $target->format('Y-m-d');
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.?$/', $clean, $m)) {
            return sprintf('%04d-%02d-%02d', $now->year, (int) $m[2], (int) $m[1]);
        }

        try {
            return Carbon::parse($input)->format('Y-m-d');
        } catch (\Exception $e) {
            return $now->format('Y-m-d');
        }
    }

    /**
     * Intelligente Auflösung einer Tagesroutine anhand von UUID, Startzeit ("07:00", "7 Uhr") oder Titel/Teilbegriff.
     */
    public static function resolveDayRoutine(?string $query, bool $includeInactive = true): ?ManagementDayRoutine
    {
        if (empty($query)) {
            return null;
        }

        $qStr = trim($query);
        $baseQuery = ManagementDayRoutine::with('steps');
        if (!$includeInactive) {
            $baseQuery->where('is_active', true);
        }

        // 1. Direkte UUID
        if (Str::isUuid($qStr)) {
            $found = (clone $baseQuery)->find($qStr);
            if ($found) {
                return $found;
            }
        }

        $allRoutines = $baseQuery->orderBy('start_time', 'asc')->get();
        if ($allRoutines->isEmpty()) {
            return null;
        }

        // 2. Exakter Titel-Treffer (case-insensitive)
        $lower = mb_strtolower($qStr);
        foreach ($allRoutines as $r) {
            if (mb_strtolower(trim($r->title)) === $lower) {
                return $r;
            }
        }

        // 3. Uhrzeit-Abgleich (z.B. "07:00", "7:00", "7 Uhr", "07:00 Frischestart")
        if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(?:uhr)?\b/iu', $qStr, $timeMatch)) {
            $hour = (int) $timeMatch[1];
            $min = isset($timeMatch[2]) && $timeMatch[2] !== '' ? (int) $timeMatch[2] : null;
            if ($hour >= 0 && $hour <= 23) {
                $exactTimeStr = $min !== null ? sprintf('%02d:%02d', $hour, $min) : null;
                if ($exactTimeStr) {
                    foreach ($allRoutines as $r) {
                        if (Carbon::parse($r->start_time)->format('H:i') === $exactTimeStr) {
                            return $r;
                        }
                    }
                }
                foreach ($allRoutines as $r) {
                    if ((int) Carbon::parse($r->start_time)->format('G') === $hour) {
                        return $r;
                    }
                }
            }
        }

        // 4. Normalisierter Alphanumerischer Substring-Abgleich
        $normalize = fn(string $s) => preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower($s));
        $normQuery = $normalize($qStr);

        if ($normQuery !== '') {
            foreach ($allRoutines as $r) {
                $normTitle = $normalize($r->title);
                if ($normTitle !== '' && (str_contains($normTitle, $normQuery) || str_contains($normQuery, $normTitle))) {
                    return $r;
                }
            }

            // 5. Suche auch in den Unterschritten (Steps) oder im Typ
            foreach ($allRoutines as $r) {
                foreach ($r->steps as $step) {
                    $normStep = $normalize($step->title);
                    if ($normStep !== '' && (str_contains($normStep, $normQuery) || str_contains($normQuery, $normStep))) {
                        return $r;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Intelligente Auflösung eines einzelnen Unterschritts (Steps) innerhalb einer Routine.
     */
    public static function resolveRoutineStep(ManagementDayRoutine $routine, ?string $stepQuery): ?ManagementDayRoutineStep
    {
        if (empty($stepQuery)) {
            return null;
        }

        $steps = $routine->relationLoaded('steps') ? $routine->steps : $routine->steps()->orderBy('position')->get();
        if ($steps->isEmpty()) {
            return null;
        }

        $qStr = trim($stepQuery);

        // 1. UUID
        if (Str::isUuid($qStr)) {
            $found = $steps->firstWhere('id', $qStr);
            if ($found) {
                return $found;
            }
        }

        // 2. Positionsnummer (z.B. "1", "Schritt 2", "#3")
        if (preg_match('/^(?:schritt|step|#)?\s*(\d+)$/iu', $qStr, $m)) {
            $pos = (int) $m[1];
            $byPos = $steps->firstWhere('position', $pos);
            if ($byPos) {
                return $byPos;
            }
            $values = $steps->values();
            if (isset($values[$pos - 1])) {
                return $values[$pos - 1];
            }
        }

        // 3. Exakter oder Teil-Titel
        $normalize = fn(string $s) => preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower($s));
        $normQuery = $normalize($qStr);

        foreach ($steps as $step) {
            if (mb_strtolower(trim($step->title)) === mb_strtolower($qStr)) {
                return $step;
            }
        }

        if ($normQuery !== '') {
            foreach ($steps as $step) {
                $normTitle = $normalize($step->title);
                if ($normTitle !== '' && (str_contains($normTitle, $normQuery) || str_contains($normQuery, $normTitle))) {
                    return $step;
                }
            }
        }

        return null;
    }

    /**
     * Berechnet die aktuelle 🔥 Serie (Streak) einer Routine.
     * Wochenende (Sa/So) und Krankheitstage (is_sick) unterbrechen die Serie NIEMALS.
     */
    public static function calculateRoutineStreak(string $routineId, array $logMap, array $sickDateMap): int
    {
        $now = Carbon::now();
        $todayStr = $now->format('Y-m-d');
        $checkDay = $now->copy();

        if ($checkDay->isWeekend()) {
            $checkDay = $checkDay->startOfWeek(Carbon::MONDAY)->addDays(4);
        }

        $streak = 0;
        for ($stepBack = 0; $stepBack < 65; $stepBack++) {
            if ($checkDay->isWeekend()) {
                $checkDay->subDay();
                continue;
            }
            $cStr = $checkDay->format('Y-m-d');
            $isCompleted = !empty($logMap[$routineId][$cStr]) && $logMap[$routineId][$cStr]->is_completed;
            $isSickDay = !empty($sickDateMap[$cStr]);

            if ($isCompleted) {
                $streak++;
            } elseif ($isSickDay) {
                $checkDay->subDay();
                continue;
            } else {
                if ($cStr === $todayStr) {
                    $checkDay->subDay();
                    continue;
                }
                break;
            }
            $checkDay->subDay();
        }

        return $streak;
    }

    /**
     * Bestimmt die Funki-Bewertungsstufe (0-25%, 26-50%, 51-75%, 76-100% oder Medizin-Funki bei Krankheit).
     */
    public static function evaluateFunkiTier(int $pct, bool $isWeekendToday, bool $isSickActive): array
    {
        if ($isSickActive) {
            return [
                'tier' => 'sick',
                'range' => 'Genesungs-Modus (Medizin-Funki)',
                'pct' => $pct,
                'speech' => 'GUTE BESSERUNG!',
                'title' => 'Medizin-Funki wünscht gute Besserung!',
                'advice' => 'Krankheitstag aktiv: Deine 🔥 Serien sind eingefroren und deine Quote wird nicht belastet. Kurier dich in Ruhe aus!'
            ];
        }

        if ($pct <= 25) {
            return [
                'tier' => '0_25',
                'range' => '0% – 25% · Loser-Zone',
                'pct' => $pct,
                'speech' => 'DU LOSER!',
                'title' => 'Disziplin-Alarm: Zu wenig umgesetzt!',
                'advice' => $isWeekendToday
                    ? 'Unter 25% deiner Mo–Fr Routine erreicht. Starte am Montag um 07:00 Uhr konsequent durch!'
                    : 'Weniger als ein Viertel deiner fälligen Routine erledigt. Streich die Ausreden und starte mit dem nächsten Block!'
            ];
        }

        if ($pct <= 50) {
            return [
                'tier' => '25_50',
                'range' => '26% – 50% · Ausreden-Zone',
                'pct' => $pct,
                'speech' => 'KEINE AUSREDEN!',
                'title' => 'Unter 50% Zielerreichung',
                'advice' => 'Mehr als die Hälfte deiner geplanten Routine bleibt liegen. Fokus auf die ersten zwei Blöcke am Morgen!'
            ];
        }

        if ($pct <= 75) {
            return [
                'tier' => '50_75',
                'range' => '51% – 75% · Solide Basis',
                'pct' => $pct,
                'speech' => 'DA GEHT MEHR!',
                'title' => 'Gute Basis – Da geht noch mehr!',
                'advice' => 'Du ziehst den Großteil deiner Mo–Fr Routine durch. Schließe jetzt noch die letzten Lücken!'
            ];
        }

        return [
            'tier' => '75_100',
            'range' => '76% – 100% · Champion',
            'pct' => $pct,
            'speech' => 'CHAMPION!',
            'title' => 'Stark durchgezogen – Champion-Disziplin!',
            'advice' => 'Starke Konstanz von Montag bis Freitag. Du hast deinen Tagesablauf fest im Griff!'
        ];
    }

    public static function getAiRoutineFuncsSchema(): array
    {
        return [
            [
                'name' => 'routine_get_day_routines',
                'description' => 'Ruft die Tagesroutinen (Bio-Rhythmus Timeline) inklusive aller Unterschritte (Steps), Abhak-Status für das gewählte Datum, Krankheitsstatus (Medizin-Funki), aktueller 🔥 Serie (Streak), aktuellem Zeitfenster-Status (past/current/future) und Tagesfortschritt ab. Stichworte: Wie sieht meine Routine aus, Meine Morning Routine, Tagesablauf, Abendroutine, was steht heute an, welche Routine ist gerade dran, was habe ich heute schon abgehakt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'Optionales Datum (z.B. "heute", "gestern", "montag" oder "YYYY-MM-DD"). Standard: heute.'
                        ],
                        'filter_days' => [
                            'type' => 'string',
                            'description' => 'Optionaler Filter nach Wochentags-Gültigkeit: "all", "weekdays" (Mo–Fr Fokus) oder "weekend" (Sa–So).',
                            'enum' => ['all', 'weekdays', 'weekend']
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Optionaler Kategorie-Filter: "hygiene", "sport", "work", "food", "break", "sleep", "general".',
                            'enum' => ['hygiene', 'sport', 'work', 'food', 'break', 'sleep', 'general']
                        ],
                        'search' => [
                            'type' => 'string',
                            'description' => 'Optionaler Suchbegriff im Titel der Routine oder in den Unterschritten.'
                        ],
                        'include_inactive' => [
                            'type' => 'boolean',
                            'description' => 'Optional: Wenn true, werden auch pausierte/inaktive Routinen mit ausgegeben (Standard: false).'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetDayRoutines']
            ],
            [
                'name' => 'routine_get_evaluation',
                'description' => 'Berechnet die vollständige Habit-Tracker-Auswertung (Woche Mo–Fr, Monat oder Jahr) inklusive Funki-Bewertungsstufe (0-25% DU LOSER!, 26-50% KEINE AUSREDEN!, 51-75% DA GEHT MEHR!, 76-100% CHAMPION! oder Medizin-Funki GUTE BESSERUNG!), Quoten pro Routine (z.B. "3 von 5 Arbeitstagen geschafft"), 🔥 Serien (Streaks), ⭐ Sternen, Wochentags-Analyse (Mo–Fr), Kategorie-Performance, Krankheitstagen und den 3 logisch errechneten System-Coaching-Tipps. Beachtet strikt: Das Wochenende (Sa & So) ist Heilige Freizeit! Stichworte: Routine Auswertung, Wochenauswertung, Monatsauswertung, Jahresauswertung, wie oft habe ich es geschafft, Funki Score, Coaching Tipps.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'period' => [
                            'type' => 'string',
                            'description' => 'Auswertungszeitraum: "week" (Woche Mo–Fr), "month" (Monat) oder "year" (Jahr). Standard: "week".',
                            'enum' => ['week', 'month', 'year']
                        ],
                        'period_offset' => [
                            'type' => 'integer',
                            'description' => 'Verschiebung des Zeitraums: 0 = aktueller Zeitraum, -1 = letzte Woche / letzter Monat / letztes Jahr, -2 = vorletzter Zeitraum. Standard: 0.'
                        ],
                        'score_mode' => [
                            'type' => 'string',
                            'description' => 'Bewertungsmodus: "smart" (Fair-Modus: zählt heute nur bis zur aktuellen Uhrzeit fällige Routinen) oder "strict" (Strikt-Modus: zählt alle Werktage Mo–Fr des gesamten Zeitraums). Standard: "smart".',
                            'enum' => ['smart', 'strict']
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetRoutineEvaluation']
            ],
            [
                'name' => 'routine_log_progress',
                'description' => 'Hakt eine oder mehrere Tagesroutinen (oder alle bis jetzt fälligen / alle Routinen eines Tages) für ein Datum als erledigt (completed: true) oder offen (completed: false) ab und speichert optional eine Notiz. Erkennt Routinen intelligent per Titel (z.B. "Frischestart", "Deep Work"), Uhrzeit ("07:00", "7 Uhr") oder ID. Stichworte: Hake meine Routine ab, Frischestart erledigt, ich bin heute um 7 aufgestanden, Morgenroutine geschafft, Routine abhaken, alle Routinen für heute abhaken.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'routine' => [
                            'type' => 'string',
                            'description' => 'Titel, Teilname, Startuhrzeit (z.B. "07:00") oder ID einer einzelnen Routine.'
                        ],
                        'routines' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optional: Mehrere Routinen gleichzeitig abhaken (Liste von Titeln, Uhrzeiten oder IDs).'
                        ],
                        'mark_all_due' => [
                            'type' => 'boolean',
                            'description' => 'Optional: Wenn true, werden alle bis zur aktuellen Uhrzeit fälligen Routinen des Tages auf einmal abgehakt.'
                        ],
                        'mark_entire_day' => [
                            'type' => 'boolean',
                            'description' => 'Optional: Wenn true, werden alle aktiven Werktags-Routinen des gesamten Tages abgehakt (oder zurückgesetzt bei completed=false).'
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Optionales Datum ("heute", "gestern", "montag", "YYYY-MM-DD"). Standard: heute.'
                        ],
                        'completed' => [
                            'type' => 'boolean',
                            'description' => 'True = als erledigt abhaken (Standard), False = Haken entfernen / zurücksetzen.'
                        ],
                        'note' => [
                            'type' => 'string',
                            'description' => 'Optionale Notiz zum Durchführungs-Log.'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeLogRoutineProgress']
            ],
            [
                'name' => 'routine_log_step',
                'description' => 'Hakt einen oder mehrere spezifische Unterschritte (Steps) innerhalb einer Tagesroutine für ein Datum ab (z.B. "Kaltes Wasser ins Gesicht", "Espresso kochen"). Wenn alle Unterschritte einer Routine erledigt sind, wird die gesamte Routine automatisch als erledigt markiert. Stichworte: Unterschritt abhaken, Teilschritt der Routine erledigt, nur einen Schritt in der Routine geschafft.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'routine' => [
                            'type' => 'string',
                            'description' => 'Titel, Uhrzeit (z.B. "07:00") oder ID der übergeordneten Routine.'
                        ],
                        'step' => [
                            'type' => 'string',
                            'description' => 'Titel, Positionsnummer (1, 2, 3...) oder ID des Unterschritts.'
                        ],
                        'steps' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optional: Mehrere Unterschritte gleichzeitig abhaken (Titel, Positionsnummern oder IDs).'
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Optionales Datum ("heute", "gestern", "YYYY-MM-DD"). Standard: heute.'
                        ],
                        'completed' => [
                            'type' => 'boolean',
                            'description' => 'True = als erledigt markieren (Standard), False = Haken beim Unterschritt entfernen.'
                        ]
                    ],
                    'required' => ['routine']
                ],
                'callable' => [self::class, 'executeLogRoutineStep']
            ],
            [
                'name' => 'routine_set_sick_day',
                'description' => 'Aktiviert oder deaktiviert den Krankheits- und Genesungsmodus (Medizin-Funki: GUTE BESSERUNG!) für einen Tag oder Zeitraum. Krankheitstage schützen alle 🔥 Serien (Streaks) vor dem Abbruch und entschuldigen den Tag aus dem Pflicht-Soll der Erfolgsquote. Stichworte: Ich bin heute krank, Krankmeldung für meine Routine, Genesungstag eintragen, Medizin-Funki aktivieren, ich bin wieder gesund.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'Datum des Krankheitstages ("heute", "gestern", "YYYY-MM-DD"). Standard: heute.'
                        ],
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Optional (bei mehreren Tagen): Startdatum (YYYY-MM-DD).'
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'Optional (bei mehreren Tagen): Enddatum (YYYY-MM-DD).'
                        ],
                        'is_sick' => [
                            'type' => 'boolean',
                            'description' => 'True = als krank / Genesungstag markieren (Standard), False = Krankmeldung aufheben (wieder gesund).'
                        ],
                        'note' => [
                            'type' => 'string',
                            'description' => 'Optionale Notiz (z.B. "Erkältung / Ruhetag").'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeSetSickDay']
            ],
            [
                'name' => 'routine_create',
                'description' => 'Erstellt einen neuen Baustein in der Tagesroutine (Bio-Rhythmus Timeline) inklusive Uhrzeit, Dauer, Kategorie, Wochentags-Gültigkeit und optionalen Unterschritten (Steps). Stichworte: Neue Routine erstellen, neuen Block in Tagesablauf einfügen, Routine hinzufügen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titel der neuen Routine (z.B. "Power-Workout & Mobility").'
                        ],
                        'start_time' => [
                            'type' => 'string',
                            'description' => 'Startuhrzeit im Format HH:MM (z.B. "07:30" oder "17:00").'
                        ],
                        'duration_minutes' => [
                            'type' => 'integer',
                            'description' => 'Gesamtdauer in Minuten (z.B. 30, 60, 120).'
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Kategorie: "hygiene" (Pflege), "sport" (Training/Bewegung), "work" (Deep Work/Business), "food" (Ernährung), "break" (Orga/Reset/Pause), "sleep" (Nachtruhe), "general" (Allgemein). Standard: "general".',
                            'enum' => ['hygiene', 'sport', 'work', 'food', 'break', 'sleep', 'general']
                        ],
                        'days_of_week' => [
                            'type' => 'string',
                            'description' => 'Gültigkeit: "weekdays" (Mo–Fr Fokus), "weekend" (Sa–So), "all" (Täglich). Standard: "weekdays".',
                            'enum' => ['all', 'weekdays', 'weekend']
                        ],
                        'message' => [
                            'type' => 'string',
                            'description' => 'Optionale Fokus-Anweisung oder Motivations-Beschreibung für diesen Block.'
                        ],
                        'icon' => [
                            'type' => 'string',
                            'description' => 'Optionales Icon oder Emoji.'
                        ],
                        'steps' => [
                            'type' => 'array',
                            'description' => 'Optionale Unterschritte der Routine.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string', 'description' => 'Titel des Unterschritts.'],
                                    'duration_minutes' => ['type' => 'integer', 'description' => 'Dauer in Minuten (Standard: 5).']
                                ],
                                'required' => ['title']
                            ]
                        ]
                    ],
                    'required' => ['title', 'start_time', 'duration_minutes']
                ],
                'callable' => [self::class, 'executeCreateDayRoutine']
            ],
            [
                'name' => 'routine_update',
                'description' => 'Bearbeitet eine bestehende Tagesroutine (Titel, Startuhrzeit verschieben, Dauer ändern, Kategorie, Wochentags-Gültigkeit, Fokus-Nachricht, Aktiv-Status pausieren/aktivieren oder Unterschritte ersetzen). Stichworte: Routine verschieben, Uhrzeit der Routine ändern, Routine bearbeiten, Routine pausieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'routine' => [
                            'type' => 'string',
                            'description' => 'Aktuelle ID, Titel oder Startuhrzeit (z.B. "07:00") der zu bearbeitenden Routine.'
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'Optional: Neuer Titel der Routine.'
                        ],
                        'new_start_time' => [
                            'type' => 'string',
                            'description' => 'Optional: Neue Startuhrzeit im Format HH:MM (z.B. "07:30").'
                        ],
                        'new_duration_minutes' => [
                            'type' => 'integer',
                            'description' => 'Optional: Neue Dauer in Minuten.'
                        ],
                        'new_type' => [
                            'type' => 'string',
                            'description' => 'Optional: Neue Kategorie ("hygiene", "sport", "work", "food", "break", "sleep", "general").',
                            'enum' => ['hygiene', 'sport', 'work', 'food', 'break', 'sleep', 'general']
                        ],
                        'new_days_of_week' => [
                            'type' => 'string',
                            'description' => 'Optional: Neue Wochentags-Gültigkeit ("all", "weekdays", "weekend").',
                            'enum' => ['all', 'weekdays', 'weekend']
                        ],
                        'new_message' => [
                            'type' => 'string',
                            'description' => 'Optional: Neue Fokus-Anweisung / Beschreibung.'
                        ],
                        'is_active' => [
                            'type' => 'boolean',
                            'description' => 'Optional: True = aktivieren, False = pausieren/deaktivieren.'
                        ],
                        'replace_steps' => [
                            'type' => 'array',
                            'description' => 'Optional: Ersetzt alle Unterschritte durch diese neue Liste.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'duration_minutes' => ['type' => 'integer']
                                ],
                                'required' => ['title']
                            ]
                        ]
                    ],
                    'required' => ['routine']
                ],
                'callable' => [self::class, 'executeUpdateDayRoutine']
            ],
            [
                'name' => 'routine_delete',
                'description' => 'Löscht eine Tagesroutine samt ihrer Unterschritte vollständig aus dem System. Stichworte: Routine löschen, Routine-Block entfernen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'routine' => [
                            'type' => 'string',
                            'description' => 'ID, exakter Titel oder Uhrzeit der zu löschenden Routine.'
                        ]
                    ],
                    'required' => ['routine']
                ],
                'callable' => [self::class, 'executeDeleteDayRoutine']
            ],
            [
                'name' => 'routine_manage_steps',
                'description' => 'Fügt einer bestehenden Tagesroutine einen neuen Unterschritt (Step) hinzu ("add"), bearbeitet einen Unterschritt ("update") oder löscht einen Unterschritt ("delete"). Stichworte: Schritt zur Routine hinzufügen, Unterschritt in Morgenroutine ergänzen, Schritt aus Routine löschen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'routine' => [
                            'type' => 'string',
                            'description' => 'ID, Titel oder Uhrzeit der übergeordneten Routine.'
                        ],
                        'action' => [
                            'type' => 'string',
                            'description' => 'Gewünschte Aktion: "add" (hinzufügen), "update" (ändern), "delete" (löschen).',
                            'enum' => ['add', 'update', 'delete']
                        ],
                        'step' => [
                            'type' => 'string',
                            'description' => 'ID, Positionsnummer (1, 2...) oder aktueller Titel des Unterschritts (erforderlich bei "update" und "delete").'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titel des neuen Unterschritts (bei "add") oder neuer Titel (bei "update").'
                        ],
                        'duration_minutes' => [
                            'type' => 'integer',
                            'description' => 'Dauer des Unterschritts in Minuten (Standard bei "add": 5).'
                        ],
                        'position' => [
                            'type' => 'integer',
                            'description' => 'Optionale 1-basierte Position innerhalb der Routine.'
                        ]
                    ],
                    'required' => ['routine', 'action']
                ],
                'callable' => [self::class, 'executeManageRoutineSteps']
            ],
        ];
    }

    /**
     * 1. Tagesroutinen + heutiger/gewählter Tracking-Status, Streaks & Timeline-Status abrufen.
     */
    public static function executeGetDayRoutines(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            $targetDate = self::parseRoutineDate($args['date'] ?? null);
            $targetCarbon = Carbon::parse($targetDate);
            $now = Carbon::now();
            $isToday = $targetDate === $now->format('Y-m-d');
            $includeInactive = !empty($args['include_inactive']);

            $query = ManagementDayRoutine::with(['steps' => function ($q) {
                $q->orderBy('position', 'asc');
            }]);

            if (!$includeInactive) {
                $query->where('is_active', true);
            }

            $filterDays = $args['filter_days'] ?? null;
            if ($filterDays === 'weekdays') {
                $query->where(function ($q) {
                    $q->whereIn('days_of_week', ['all', 'weekdays'])->orWhereNull('days_of_week');
                });
            } elseif ($filterDays === 'weekend') {
                $query->where(function ($q) {
                    $q->whereIn('days_of_week', ['all', 'weekend'])->orWhereNull('days_of_week');
                });
            }

            if (!empty($args['type'])) {
                $query->where('type', strtolower(trim($args['type'])));
            }

            if (!empty($args['search'])) {
                $escaped = addcslashes(trim((string) $args['search']), '%_\\');
                $query->where(function ($q) use ($escaped) {
                    $q->where('title', 'like', '%' . $escaped . '%')
                      ->orWhere('message', 'like', '%' . $escaped . '%')
                      ->orWhereHas('steps', function ($sq) use ($escaped) {
                          $sq->where('title', 'like', '%' . $escaped . '%');
                      });
                });
            }

            $routines = $query->orderBy('start_time', 'asc')->get();

            // Logs für Streak-Lookback (90 Tage) + Ziel-Datum laden
            $lookbackStart = $now->copy()->subDays(90)->format('Y-m-d');
            $fetchStart = $targetDate < $lookbackStart ? $targetDate : $lookbackStart;
            $fetchEnd = $targetDate > $now->format('Y-m-d') ? $targetDate : $now->format('Y-m-d');

            $logs = ManagementDayRoutineLog::whereBetween('date', [$fetchStart, $fetchEnd])->get();
            $logMap = [];
            $sickDateMap = [];
            foreach ($logs as $log) {
                $dStr = $log->date instanceof Carbon ? $log->date->format('Y-m-d') : substr((string) $log->date, 0, 10);
                $logMap[$log->day_routine_id][$dStr] = $log;
                if ($log->is_sick) {
                    $sickDateMap[$dStr] = true;
                }
            }

            $isSickDay = !empty($sickDateMap[$targetDate]);
            $completedCount = 0;
            $dueCount = 0;
            $currentActiveRoutine = null;
            $nextUpcomingRoutine = null;

            $formattedRoutines = $routines->map(function ($routine) use (
                $targetDate,
                $isToday,
                $isSickDay,
                $now,
                $logMap,
                $sickDateMap,
                &$completedCount,
                &$dueCount,
                &$currentActiveRoutine,
                &$nextUpcomingRoutine
            ) {
                $start = Carbon::parse($routine->start_time);
                $duration = (int) ($routine->duration_minutes ?: ($routine->type === 'sleep' ? 480 : 15));
                $end = $start->copy()->addMinutes($duration);

                $crossesMidnight = $start->format('H:i:s') > $end->format('H:i:s');
                if ($crossesMidnight && $now->format('H:i:s') < $end->format('H:i:s')) {
                    $start->subDay();
                    $end->subDay();
                }

                $timelineStatus = 'future';
                $currentActiveStep = null;
                if ($isToday) {
                    if ($end->isPast()) {
                        $timelineStatus = 'past';
                    } elseif ($now->between($start, $end)) {
                        $timelineStatus = 'current';
                        $minutesPassed = $start->diffInMinutes($now);
                        $acc = 0;
                        foreach ($routine->steps as $st) {
                            if ($minutesPassed >= $acc && $minutesPassed < ($acc + $st->duration_minutes)) {
                                $currentActiveStep = $st->title;
                                break;
                            }
                            $acc += $st->duration_minutes;
                        }
                    }
                } elseif ($targetDate < $now->format('Y-m-d')) {
                    $timelineStatus = 'past';
                }

                $log = $logMap[$routine->id][$targetDate] ?? null;
                $isCompleted = $log ? (bool) $log->is_completed : false;
                $completedStepIds = ($log && is_array($log->completed_steps)) ? $log->completed_steps : [];
                if ($isCompleted && empty($completedStepIds) && $routine->steps->isNotEmpty()) {
                    $completedStepIds = $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray();
                }

                if ($isCompleted) {
                    $completedCount++;
                }
                if (!$isToday || $timelineStatus !== 'future' || $isCompleted) {
                    $dueCount++;
                }

                $streak = self::calculateRoutineStreak($routine->id, $logMap, $sickDateMap);

                $stepsFormatted = $routine->steps->map(function ($st) use ($completedStepIds, $isCompleted) {
                    return [
                        'id' => $st->id,
                        'day_routine_id' => $st->day_routine_id,
                        'position' => $st->position,
                        'title' => $st->title,
                        'duration_minutes' => $st->duration_minutes,
                        'is_completed' => $isCompleted || in_array((string) $st->id, $completedStepIds, true),
                    ];
                })->values()->toArray();

                $item = [
                    'id' => $routine->id,
                    'title' => $routine->title,
                    'start_time' => Carbon::parse($routine->start_time)->format('H:i'),
                    'end_time' => $end->format('H:i'),
                    'duration_minutes' => $routine->duration_minutes,
                    'type' => $routine->type ?? 'general',
                    'days_of_week' => $routine->days_of_week ?? 'all',
                    'is_active' => (bool) $routine->is_active,
                    'message' => $routine->message,
                    'icon' => $routine->icon,
                    'timeline_status' => $timelineStatus,
                    'is_completed' => $isCompleted,
                    'is_sick' => $log ? (bool) $log->is_sick : $isSickDay,
                    'completed_at' => $log?->completed_at?->format('H:i'),
                    'note' => $log?->note,
                    'streak' => $streak,
                    'completed_steps_count' => count($completedStepIds),
                    'total_steps_count' => count($stepsFormatted),
                    'steps' => $stepsFormatted,
                ];

                if ($timelineStatus === 'current' && !$currentActiveRoutine) {
                    $currentActiveRoutine = [
                        'id' => $routine->id,
                        'title' => $routine->title,
                        'time_window' => $item['start_time'] . ' - ' . $item['end_time'],
                        'current_step' => $currentActiveStep ?? 'Fokus-Phase',
                        'is_completed' => $isCompleted,
                    ];
                } elseif ($timelineStatus === 'future' && !$nextUpcomingRoutine) {
                    $nextUpcomingRoutine = [
                        'id' => $routine->id,
                        'title' => $routine->title,
                        'start_time' => $item['start_time'],
                        'duration_minutes' => $routine->duration_minutes,
                    ];
                }

                return $item;
            })->values()->toArray();

            $totalCount = count($formattedRoutines);
            $effectiveDenom = $dueCount > 0 ? $dueCount : $totalCount;
            $fairPct = $effectiveDenom > 0 ? min(100, (int) round(($completedCount / $effectiveDenom) * 100)) : 0;
            $totalPct = $totalCount > 0 ? min(100, (int) round(($completedCount / $totalCount) * 100)) : 0;

            $funkiStatus = self::evaluateFunkiTier($fairPct, $targetCarbon->isWeekend(), $isSickDay);

            return [
                'status' => 'success',
                'date' => $targetDate,
                'weekday' => $targetCarbon->locale('de')->dayName,
                'is_weekend' => $targetCarbon->isWeekend(),
                'weekend_note' => $targetCarbon->isWeekend() ? 'Heiliges Wochenende (Freie Gestaltung – kein Pflicht-Tracking)' : 'Werktag (Mo–Fr Fokus)',
                'is_sick_day' => $isSickDay,
                'active_routines_count' => $totalCount,
                'completed_count' => $completedCount,
                'due_count' => $dueCount,
                'progress_pct_total' => $totalPct,
                'progress_pct_fair' => $fairPct,
                'funki_evaluation' => $funkiStatus,
                'current_active_routine' => $currentActiveRoutine,
                'next_upcoming_routine' => $nextUpcomingRoutine,
                'routines' => $formattedRoutines,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * 2. Vollständige Habit-Tracker-Auswertung (Woche Mo–Fr, Monat, Jahr), Funki-Bewertung & System-Tipps.
     */
    public static function executeGetRoutineEvaluation(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            $period = strtolower(trim($args['period'] ?? 'week'));
            if (!in_array($period, ['week', 'month', 'year'], true)) {
                $period = 'week';
            }
            $periodOffset = (int) ($args['period_offset'] ?? 0);
            if ($periodOffset > 0) {
                $periodOffset = 0;
            }
            $scoreMode = strtolower(trim($args['score_mode'] ?? 'smart'));
            if (!in_array($scoreMode, ['smart', 'strict'], true)) {
                $scoreMode = 'smart';
            }

            $now = Carbon::now();
            $todayStr = $now->format('Y-m-d');
            $nowTimeStr = $now->format('H:i');

            $focusRoutines = ManagementDayRoutine::with('steps')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereIn('days_of_week', ['all', 'weekdays'])->orWhereNull('days_of_week');
                })
                ->orderBy('start_time', 'asc')
                ->get();

            $germanDaysShort = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
            $germanMonthsFull = [
                1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
                7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
            ];

            if ($period === 'week') {
                $ref = $now->copy()->addWeeks($periodOffset);
                $periodStart = $ref->copy()->startOfWeek(Carbon::MONDAY);
                $periodEnd = $ref->copy()->endOfWeek(Carbon::SUNDAY);
                $workEnd = $periodStart->copy()->addDays(4);
                $periodLabel = 'KW ' . $periodStart->isoWeek() . ' (' . $periodStart->format('d.m.') . ' – ' . $workEnd->format('d.m.Y') . ')';
            } elseif ($period === 'month') {
                $ref = $now->copy()->startOfMonth()->addMonths($periodOffset);
                $periodStart = $ref->copy()->startOfMonth();
                $periodEnd = $ref->copy()->endOfMonth();
                $periodLabel = $germanMonthsFull[$ref->month] . ' ' . $ref->year;
            } else {
                $ref = $now->copy()->startOfYear()->addYears($periodOffset);
                $periodStart = $ref->copy()->startOfYear();
                $periodEnd = $ref->copy()->endOfYear();
                $periodLabel = 'Jahresübersicht ' . $ref->year;
            }

            $streakLookback = $now->copy()->subDays(90)->startOfDay();
            $fetchStart = $periodStart->lt($streakLookback) ? $periodStart->copy() : $streakLookback;
            $fetchEnd = $periodEnd->gt($now) ? $periodEnd->copy() : $now->copy()->endOfDay();

            $allLogs = ManagementDayRoutineLog::whereBetween('date', [$fetchStart->format('Y-m-d'), $fetchEnd->format('Y-m-d')])->get();
            $logMap = [];
            $sickDateMap = [];
            foreach ($allLogs as $log) {
                $dStr = $log->date instanceof Carbon ? $log->date->format('Y-m-d') : substr((string) $log->date, 0, 10);
                $logMap[$log->day_routine_id][$dStr] = $log;
                if ($log->is_sick) {
                    $sickDateMap[$dStr] = true;
                }
            }

            $firstLogDateRaw = ManagementDayRoutineLog::where(function ($q) {
                $q->where('is_completed', true)->orWhere('is_sick', true);
            })->min('date');
            $firstTrackedMonday = $firstLogDateRaw
                ? Carbon::parse($firstLogDateRaw)->startOfWeek(Carbon::MONDAY)
                : $now->copy()->startOfWeek(Carbon::MONDAY);

            $workdaysInPeriod = [];
            $healthyWorkdaysInPeriod = [];
            $dueWorkdaysInPeriod = [];
            $smartDueWorkdaysInPeriod = [];
            $sickWorkdaysCount = 0;

            $cursor = $periodStart->copy();
            while ($cursor->lte($periodEnd)) {
                $dStr = $cursor->format('Y-m-d');
                $isWeekend = $cursor->isWeekend();
                $isFuture = $dStr > $todayStr;
                $isSick = !empty($sickDateMap[$dStr]);

                if (!$isWeekend) {
                    $dayObj = [
                        'date' => $dStr,
                        'day_short' => $germanDaysShort[$cursor->dayOfWeekIso],
                        'is_future' => $isFuture,
                        'is_sick' => $isSick,
                    ];
                    $workdaysInPeriod[] = $dayObj;
                    if ($isSick) {
                        $sickWorkdaysCount++;
                    } else {
                        $healthyWorkdaysInPeriod[] = $dayObj;
                        if (!$isFuture) {
                            $dueWorkdaysInPeriod[] = $dayObj;
                            if ($period === 'week' || $cursor->gte($firstTrackedMonday)) {
                                $smartDueWorkdaysInPeriod[] = $dayObj;
                            }
                        }
                    }
                }
                $cursor->addDay();
            }

            $effectiveDueWorkdays = ($scoreMode === 'smart' && !empty($smartDueWorkdaysInPeriod))
                ? $smartDueWorkdaysInPeriod
                : $dueWorkdaysInPeriod;

            $dueWorkdayDateStrings = array_column($effectiveDueWorkdays, 'date');
            $allWorkdayDateStrings = array_column($workdaysInPeriod, 'date');
            $healthyWorkdayCount = count($healthyWorkdaysInPeriod);

            $routineBreakdown = [];
            $totalPeriodTargetSlots = $healthyWorkdayCount * $focusRoutines->count();
            $totalDueSlots = 0;
            $totalCompletedInPeriod = 0;
            $totalCompletedInDue = 0;
            $totalCompletedMinutes = 0;
            $totalDueMinutes = 0;
            $routineStreaks = [];

            foreach ($focusRoutines as $r) {
                $rTimeStr = Carbon::parse($r->start_time)->format('H:i');
                $completedInWorkdays = 0;
                $completedInDue = 0;
                $dueCountForRoutine = 0;

                foreach ($allWorkdayDateStrings as $wDateStr) {
                    $isDone = !empty($logMap[$r->id][$wDateStr]) && $logMap[$r->id][$wDateStr]->is_completed;
                    if ($isDone) {
                        $completedInWorkdays++;
                        $totalCompletedInPeriod++;
                        $totalCompletedMinutes += (int) $r->duration_minutes;
                    }
                }

                foreach ($dueWorkdayDateStrings as $dDateStr) {
                    $isDone = !empty($logMap[$r->id][$dDateStr]) && $logMap[$r->id][$dDateStr]->is_completed;
                    $isDueToday = true;
                    if ($scoreMode === 'smart' && $dDateStr === $todayStr && $rTimeStr > $nowTimeStr && !$isDone) {
                        $isDueToday = false;
                    }

                    if ($isDueToday) {
                        $dueCountForRoutine++;
                        $totalDueSlots++;
                        $totalDueMinutes += (int) $r->duration_minutes;
                        if ($isDone) {
                            $completedInDue++;
                            $totalCompletedInDue++;
                        }
                    }
                }

                $streak = self::calculateRoutineStreak($r->id, $logMap, $sickDateMap);
                $routineStreaks[$r->id] = $streak;

                $effectiveDenominator = $dueCountForRoutine > 0 ? $dueCountForRoutine : $healthyWorkdayCount;
                $fairPct = $effectiveDenominator > 0
                    ? (int) round(($completedInDue / $effectiveDenominator) * 100)
                    : ($sickWorkdaysCount > 0 ? 100 : 0);
                $periodPct = $healthyWorkdayCount > 0
                    ? (int) round(($completedInWorkdays / $healthyWorkdayCount) * 100)
                    : ($sickWorkdaysCount > 0 ? 100 : 0);

                $displayPct = min(100, ($scoreMode === 'smart' && $dueCountForRoutine > 0) ? $fairPct : $periodPct);
                $denomForText = ($scoreMode === 'smart' && $dueCountForRoutine > 0) ? $dueCountForRoutine : $healthyWorkdayCount;

                $routineBreakdown[] = [
                    'id' => $r->id,
                    'title' => $r->title,
                    'start_time' => $rTimeStr,
                    'type' => $r->type ?? 'general',
                    'duration_minutes' => $r->duration_minutes,
                    'completed_count' => $completedInWorkdays,
                    'due_count' => $dueCountForRoutine,
                    'target_workdays' => $healthyWorkdayCount,
                    'pct' => $displayPct,
                    'period_pct' => min(100, $periodPct),
                    'streak' => $streak,
                    'stars' => $completedInWorkdays,
                    'summary_text' => "{$completedInWorkdays} von {$denomForText} Arbeitstagen geschafft ({$displayPct}%)",
                ];
            }

            // Wochentags-Performance (Mo - Fr)
            $weekdayStats = [];
            for ($iso = 1; $iso <= 5; $iso++) {
                $wdPossible = 0;
                $wdDone = 0;
                $wdSickCount = 0;
                foreach ($workdaysInPeriod as $wdItem) {
                    if (Carbon::parse($wdItem['date'])->dayOfWeekIso === $iso && !$wdItem['is_future']) {
                        if ($wdItem['is_sick']) {
                            $wdSickCount++;
                        } elseif (in_array($wdItem['date'], $dueWorkdayDateStrings, true)) {
                            foreach ($focusRoutines as $fr) {
                                $wdPossible++;
                                if (!empty($logMap[$fr->id][$wdItem['date']]) && $logMap[$fr->id][$wdItem['date']]->is_completed) {
                                    $wdDone++;
                                }
                            }
                        }
                    }
                }
                $weekdayStats[] = [
                    'day' => $germanDaysShort[$iso],
                    'done' => $wdDone,
                    'target' => $wdPossible,
                    'sick_days' => $wdSickCount,
                    'pct' => $wdPossible > 0 ? min(100, (int) round(($wdDone / $wdPossible) * 100)) : ($wdSickCount > 0 ? 100 : 0),
                ];
            }

            // Kategorie-Performance
            $typeLabels = [
                'hygiene' => 'Morgen- & Abendpflege',
                'sport' => 'Sport & Workout',
                'work' => 'Deep Work & Business',
                'food' => 'Ernährung & Energie',
                'break' => 'Orga, Reset & Freizeit',
                'sleep' => 'Nachtruhe & Schlaf',
                'general' => 'Allgemein',
            ];
            $categoryStats = [];
            foreach ($routineBreakdown as $row) {
                $t = $row['type'];
                if (!isset($categoryStats[$t])) {
                    $categoryStats[$t] = [
                        'type' => $t,
                        'label' => $typeLabels[$t] ?? 'Allgemein',
                        'done' => 0,
                        'target' => 0,
                        'pct' => 0,
                    ];
                }
                $categoryStats[$t]['done'] += $row['completed_count'];
                $categoryStats[$t]['target'] += ($scoreMode === 'smart' && $row['due_count'] > 0) ? $row['due_count'] : $row['target_workdays'];
            }
            foreach ($categoryStats as &$cs) {
                $cs['pct'] = $cs['target'] > 0 ? min(100, (int) round(($cs['done'] / $cs['target']) * 100)) : 0;
            }
            unset($cs);

            $strictOverallPct = $totalPeriodTargetSlots > 0
                ? min(100, (int) round(($totalCompletedInPeriod / $totalPeriodTargetSlots) * 100))
                : ($sickWorkdaysCount > 0 ? 100 : 0);

            $smartOverallPct = $totalDueSlots > 0
                ? min(100, (int) round(($totalCompletedInDue / $totalDueSlots) * 100))
                : $strictOverallPct;

            $evaluationPct = $scoreMode === 'smart' ? $smartOverallPct : $strictOverallPct;
            $isTodaySick = !empty($sickDateMap[$todayStr]);

            $funkiEvaluation = self::evaluateFunkiTier($evaluationPct, $now->isWeekend(), $isTodaySick);

            // System-Coaching-Tipps berechnen
            $systemTips = [];
            if (!empty($routineBreakdown)) {
                $sortedWeakest = $routineBreakdown;
                usort($sortedWeakest, fn($a, $b) => $a['pct'] === $b['pct'] ? strcmp($a['start_time'], $b['start_time']) : ($a['pct'] <=> $b['pct']));
                $weakest = $sortedWeakest[0];

                $sortedStrongest = $routineBreakdown;
                usort($sortedStrongest, fn($a, $b) => $b['pct'] === $a['pct'] ? ($b['streak'] <=> $a['streak']) : ($b['pct'] <=> $a['pct']));
                $strongest = $sortedStrongest[0];

                $systemTips[] = [
                    'badge' => $weakest['pct'] < 80 ? 'Größter Hebel (Schwachstelle)' : 'Keine Schwachstelle',
                    'title' => $weakest['start_time'] . ' ' . $weakest['title'] . ' (' . $weakest['summary_text'] . ')',
                    'advice' => $weakest['pct'] <= 40
                        ? 'Häufigster Abbruchpunkt: Prüfe, ob Uhrzeit (' . $weakest['start_time'] . ') oder Dauer (' . $weakest['duration_minutes'] . ' Min) angepasst werden müssen.'
                        : 'Fast stabil: Setze dir für diesen Block morgen früh einen festen Anker-Trigger.',
                ];

                $systemTips[] = [
                    'badge' => 'Stärkster Routine-Anker',
                    'title' => $strongest['start_time'] . ' ' . $strongest['title'] . ' (' . $strongest['pct'] . '% · 🔥 ' . $strongest['streak'] . 'er Serie)',
                    'advice' => 'Dein stabilster Baustein! Kopple schwierigere Aufgaben direkt an diesen Erfolgsblock.',
                ];
            }

            return [
                'status' => 'success',
                'period' => $period,
                'period_label' => $periodLabel,
                'score_mode' => $scoreMode,
                'weekend_policy' => 'Samstag & Sonntag gelten als Heilige Freizeit (Freie Gestaltung) und belasten niemals den Score oder die 🔥 Serien.',
                'funki_evaluation' => $funkiEvaluation,
                'overall_stats' => [
                    'evaluation_pct' => $evaluationPct,
                    'smart_pct' => $smartOverallPct,
                    'strict_pct' => $strictOverallPct,
                    'completed_slots' => $totalCompletedInPeriod,
                    'due_slots' => $totalDueSlots,
                    'target_slots' => $totalPeriodTargetSlots,
                    'workdays_total' => count($workdaysInPeriod),
                    'workdays_healthy' => $healthyWorkdayCount,
                    'workdays_due' => count($effectiveDueWorkdays),
                    'sick_days_count' => $sickWorkdaysCount,
                    'completed_hours' => round($totalCompletedMinutes / 60, 1),
                    'due_hours' => round($totalDueMinutes / 60, 1),
                    'best_streak' => !empty($routineStreaks) ? max($routineStreaks) : 0,
                    'is_weekend_today' => $now->isWeekend(),
                    'is_sick_today' => $isTodaySick,
                ],
                'routine_breakdown' => $routineBreakdown,
                'weekday_stats' => $weekdayStats,
                'category_stats' => array_values($categoryStats),
                'system_tips' => $systemTips,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Routine-Auswertung: ' . $e->getMessage()];
        }
    }

    /**
     * 3. Eine oder mehrere Routinen (oder alle fälligen / den ganzen Tag) für ein Datum abhaken oder zurücksetzen.
     */
    public static function executeLogRoutineProgress(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            $targetDate = self::parseRoutineDate($args['date'] ?? null);
            $completed = isset($args['completed']) ? (bool) $args['completed'] : true;
            $note = isset($args['note']) ? trim((string) $args['note']) : null;
            $markAllDue = !empty($args['mark_all_due']);
            $markEntireDay = !empty($args['mark_entire_day']);

            $targetRoutines = collect();

            if ($markEntireDay || $markAllDue) {
                $query = ManagementDayRoutine::with('steps')
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereIn('days_of_week', ['all', 'weekdays'])->orWhereNull('days_of_week');
                    })
                    ->orderBy('start_time', 'asc')
                    ->get();

                if ($markAllDue && $targetDate === Carbon::now()->format('Y-m-d')) {
                    $nowTime = Carbon::now()->format('H:i');
                    $query = $query->filter(fn($r) => Carbon::parse($r->start_time)->format('H:i') <= $nowTime);
                }
                $targetRoutines = $query;
            } else {
                $queries = [];
                if (!empty($args['routine'])) {
                    $queries[] = (string) $args['routine'];
                }
                if (!empty($args['routines']) && is_array($args['routines'])) {
                    foreach ($args['routines'] as $rInput) {
                        if (!empty($rInput)) {
                            $queries[] = (string) $rInput;
                        }
                    }
                }

                if (empty($queries)) {
                    return [
                        'status' => 'error',
                        'message' => 'Bitte gib den Namen, die Uhrzeit (z.B. "07:00") oder die ID der Routine an (oder nutze mark_all_due / mark_entire_day).'
                    ];
                }

                $notFound = [];
                foreach ($queries as $q) {
                    $resolved = self::resolveDayRoutine($q);
                    if ($resolved) {
                        $targetRoutines->push($resolved);
                    } else {
                        $notFound[] = $q;
                    }
                }

                $targetRoutines = $targetRoutines->unique('id')->values();
                if ($targetRoutines->isEmpty()) {
                    $available = ManagementDayRoutine::where('is_active', true)
                        ->orderBy('start_time')
                        ->get()
                        ->map(fn($r) => Carbon::parse($r->start_time)->format('H:i') . ' ' . $r->title)
                        ->implode(', ');
                    return [
                        'status' => 'error',
                        'message' => 'Keine passende Routine für "' . implode(', ', $notFound) . '" gefunden. Aktive Routinen: ' . $available
                    ];
                }
            }

            $updatedTitles = [];
            foreach ($targetRoutines as $routine) {
                $allStepIds = $completed
                    ? $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray()
                    : [];

                $payload = [
                    'is_completed' => $completed,
                    'completed_steps' => $allStepIds,
                    'completed_at' => $completed ? Carbon::now() : null,
                ];
                if ($completed) {
                    $payload['is_sick'] = false;
                }
                if ($note !== null && $note !== '') {
                    $payload['note'] = $note;
                }

                ManagementDayRoutineLog::updateOrCreate(
                    [
                        'day_routine_id' => $routine->id,
                        'date' => $targetDate,
                    ],
                    $payload
                );

                $updatedTitles[] = Carbon::parse($routine->start_time)->format('H:i') . ' ' . $routine->title;
            }

            $dateFormatted = Carbon::parse($targetDate)->format('d.m.Y');
            $actionWord = $completed ? 'als erledigt abgehakt' : 'zurückgesetzt (als offen markiert)';

            return [
                'status' => 'success',
                'message' => count($updatedTitles) === 1
                    ? "Routine '{$updatedTitles[0]}' wurde für den {$dateFormatted} {$actionWord}."
                    : count($updatedTitles) . " Routinen (" . implode(', ', $updatedTitles) . ") wurden für den {$dateFormatted} {$actionWord}.",
                'date' => $targetDate,
                'completed' => $completed,
                'updated_routines' => $updatedTitles,
                '_frontend_event' => [
                    'type' => 'dispatch',
                    'name' => 'routine-updated',
                    'detail' => ['date' => $targetDate]
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abhaken der Routine: ' . $e->getMessage()];
        }
    }

    /**
     * 4. Einzelne Unterschritte (Steps) einer Routine für ein Datum abhaken oder zurücksetzen.
     */
    public static function executeLogRoutineStep(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            if (empty($args['routine'])) {
                return ['status' => 'error', 'message' => 'Bitte gib die übergeordnete Routine (Name, Uhrzeit oder ID) an.'];
            }

            $routine = self::resolveDayRoutine((string) $args['routine']);
            if (!$routine) {
                return ['status' => 'error', 'message' => "Routine '{$args['routine']}' wurde nicht gefunden."];
            }

            $stepQueries = [];
            if (!empty($args['step'])) {
                $stepQueries[] = (string) $args['step'];
            }
            if (!empty($args['steps']) && is_array($args['steps'])) {
                foreach ($args['steps'] as $sInput) {
                    if (!empty($sInput)) {
                        $stepQueries[] = (string) $sInput;
                    }
                }
            }

            if (empty($stepQueries)) {
                return ['status' => 'error', 'message' => 'Bitte gib mindestens einen Unterschritt (step oder steps) an.'];
            }

            $targetDate = self::parseRoutineDate($args['date'] ?? null);
            $shouldComplete = isset($args['completed']) ? (bool) $args['completed'] : true;

            $matchedSteps = collect();
            foreach ($stepQueries as $sq) {
                $resolvedStep = self::resolveRoutineStep($routine, $sq);
                if ($resolvedStep) {
                    $matchedSteps->push($resolvedStep);
                }
            }

            if ($matchedSteps->isEmpty()) {
                $availableSteps = $routine->steps->map(fn($s) => "{$s->position}. {$s->title}")->implode(', ');
                return [
                    'status' => 'error',
                    'message' => "Kein passender Unterschritt in '{$routine->title}' gefunden. Verfügbare Schritte: " . ($availableSteps ?: 'Keine Unterschritte angelegt.')
                ];
            }

            $log = ManagementDayRoutineLog::firstOrNew([
                'day_routine_id' => $routine->id,
                'date' => $targetDate,
            ]);

            $completedSteps = is_array($log->completed_steps) ? $log->completed_steps : [];
            if ($log->exists && $log->is_completed && empty($completedSteps) && $routine->steps->isNotEmpty()) {
                $completedSteps = $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray();
            }

            foreach ($matchedSteps as $stepModel) {
                $sid = (string) $stepModel->id;
                if ($shouldComplete) {
                    if (!in_array($sid, $completedSteps, true)) {
                        $completedSteps[] = $sid;
                    }
                } else {
                    $completedSteps = array_values(array_diff($completedSteps, [$sid]));
                }
            }

            $totalSteps = $routine->steps->count();
            $isAllCompleted = $totalSteps > 0 ? (count($completedSteps) >= $totalSteps) : !empty($completedSteps);

            $log->completed_steps = $completedSteps;
            $log->is_completed = $isAllCompleted;
            $log->completed_at = $isAllCompleted ? Carbon::now() : null;
            $log->save();

            $stepNames = $matchedSteps->pluck('title')->implode(', ');
            $statusMsg = $shouldComplete ? 'abgehakt' : 'zurückgesetzt';

            return [
                'status' => 'success',
                'message' => "Unterschritt(e) '{$stepNames}' in Routine '{$routine->title}' für den " . Carbon::parse($targetDate)->format('d.m.Y') . " {$statusMsg}. Fortschritt: " . count($completedSteps) . "/{$totalSteps} Schritte erledigt" . ($isAllCompleted ? ' (Routine komplett abgeschlossen!).' : '.'),
                'routine_id' => $routine->id,
                'routine_title' => $routine->title,
                'date' => $targetDate,
                'routine_is_completed' => $isAllCompleted,
                'completed_steps_count' => count($completedSteps),
                'total_steps_count' => $totalSteps,
                '_frontend_event' => [
                    'type' => 'dispatch',
                    'name' => 'routine-updated',
                    'detail' => ['date' => $targetDate]
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abhaken des Unterschritts: ' . $e->getMessage()];
        }
    }

    /**
     * 5. Krankheits- / Genesungsmodus ("Medizin-Funki") für einen Tag oder Zeitraum setzen / aufheben.
     */
    public static function executeSetSickDay(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            $isSick = isset($args['is_sick']) ? (bool) $args['is_sick'] : true;
            $note = isset($args['note']) ? trim((string) $args['note']) : null;

            $dates = [];
            if (!empty($args['date_from']) && !empty($args['date_to'])) {
                $start = Carbon::parse(self::parseRoutineDate($args['date_from']))->startOfDay();
                $end = Carbon::parse(self::parseRoutineDate($args['date_to']))->startOfDay();
                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }
                if ($start->diffInDays($end) > 60) {
                    $end = $start->copy()->addDays(60);
                }
                $cursor = $start->copy();
                while ($cursor->lte($end)) {
                    $dates[] = $cursor->format('Y-m-d');
                    $cursor->addDay();
                }
            } else {
                $dates[] = self::parseRoutineDate($args['date'] ?? null);
            }

            $routines = ManagementDayRoutine::where('is_active', true)->get();
            if ($routines->isEmpty()) {
                return ['status' => 'error', 'message' => 'Es sind keine aktiven Tagesroutinen vorhanden.'];
            }

            foreach ($dates as $dStr) {
                if (!$isSick) {
                    ManagementDayRoutineLog::whereDate('date', $dStr)->update(['is_sick' => false]);
                } else {
                    foreach ($routines as $routine) {
                        $existing = ManagementDayRoutineLog::where('day_routine_id', $routine->id)
                            ->whereDate('date', $dStr)
                            ->first();

                        if ($existing) {
                            $updateData = ['is_sick' => true];
                            if ($note) {
                                $updateData['note'] = $note;
                            }
                            $existing->update($updateData);
                        } else {
                            ManagementDayRoutineLog::create([
                                'day_routine_id' => $routine->id,
                                'date' => $dStr,
                                'is_completed' => false,
                                'is_sick' => true,
                                'completed_steps' => [],
                                'completed_at' => null,
                                'note' => $note,
                            ]);
                        }
                    }
                }
            }

            $formattedDates = array_map(fn($d) => Carbon::parse($d)->format('d.m.Y'), $dates);
            $dateText = count($formattedDates) === 1
                ? $formattedDates[0]
                : ($formattedDates[0] . ' bis ' . end($formattedDates) . ' (' . count($formattedDates) . ' Tage)');

            $message = $isSick
                ? "Genesungs-Modus (Medizin-Funki: 'GUTE BESSERUNG!') für {$dateText} aktiviert. Deine 🔥 Serien sind eingefroren und die Erfolgsquote wird geschont!"
                : "Krankheitsmodus für {$dateText} aufgehoben. Du bist wieder im regulären Routine-Modus!";

            return [
                'status' => 'success',
                'message' => $message,
                'dates' => $dates,
                'is_sick' => $isSick,
                '_frontend_event' => [
                    'type' => 'dispatch',
                    'name' => 'routine-updated',
                    'detail' => ['dates' => $dates, 'is_sick' => $isSick]
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Setzen des Krankheitsstatus: ' . $e->getMessage()];
        }
    }

    /**
     * 6. Neue Tagesroutine (inkl. Steps) anlegen.
     */
    public static function executeCreateDayRoutine(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            $title = trim((string) ($args['title'] ?? ''));
            $startTimeRaw = trim((string) ($args['start_time'] ?? ''));
            $duration = (int) ($args['duration_minutes'] ?? 30);

            if ($title === '' || $startTimeRaw === '') {
                return ['status' => 'error', 'message' => 'Titel (title) und Startuhrzeit (start_time) sind erforderlich.'];
            }

            $startTime = Carbon::parse($startTimeRaw)->format('H:i:00');
            $duration = max(1, min(1440, $duration));

            $allowedTypes = ['hygiene', 'sport', 'work', 'food', 'break', 'sleep', 'general'];
            $type = strtolower(trim((string) ($args['type'] ?? 'general')));
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'general';
            }

            $allowedDays = ['all', 'weekdays', 'weekend'];
            $daysOfWeek = strtolower(trim((string) ($args['days_of_week'] ?? 'weekdays')));
            if (!in_array($daysOfWeek, $allowedDays, true)) {
                $daysOfWeek = 'weekdays';
            }

            $routine = ManagementDayRoutine::create([
                'title' => substr($title, 0, 255),
                'start_time' => $startTime,
                'duration_minutes' => $duration,
                'type' => $type,
                'days_of_week' => $daysOfWeek,
                'message' => isset($args['message']) ? substr(trim((string) $args['message']), 0, 2000) : null,
                'icon' => $args['icon'] ?? null,
                'is_active' => true,
            ]);

            $createdSteps = [];
            if (!empty($args['steps']) && is_array($args['steps'])) {
                foreach (array_values($args['steps']) as $idx => $stepItem) {
                    $stepTitle = is_array($stepItem) ? trim((string) ($stepItem['title'] ?? '')) : trim((string) $stepItem);
                    $stepDur = is_array($stepItem) ? max(1, (int) ($stepItem['duration_minutes'] ?? 5)) : 5;
                    if ($stepTitle !== '') {
                        $st = ManagementDayRoutineStep::create([
                            'id' => Str::uuid(),
                            'day_routine_id' => $routine->id,
                            'title' => substr($stepTitle, 0, 255),
                            'duration_minutes' => $stepDur,
                            'position' => $idx + 1,
                        ]);
                        $createdSteps[] = $st->title . ' (' . $st->duration_minutes . 'm)';
                    }
                }
            }

            return [
                'status' => 'success',
                'message' => "Neue Tagesroutine '{$routine->title}' um " . Carbon::parse($routine->start_time)->format('H:i') . " Uhr ({$duration} Min., Kategorie: {$type}) wurde erfolgreich erstellt" . (!empty($createdSteps) ? ' (inkl. ' . count($createdSteps) . ' Unterschritten).' : '.'),
                'routine_id' => $routine->id,
                'routine' => $routine->load('steps')->toArray(),
                '_frontend_event' => [
                    'type' => 'dispatch',
                    'name' => 'routine-updated',
                    'detail' => ['routine_id' => $routine->id]
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Tagesroutine: ' . $e->getMessage()];
        }
    }

    /**
     * 7. Bestehende Tagesroutine aktualisieren.
     */
    public static function executeUpdateDayRoutine(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            if (empty($args['routine'])) {
                return ['status' => 'error', 'message' => 'Bitte gib die zu bearbeitende Routine (ID, Titel oder Uhrzeit) an.'];
            }

            $routine = self::resolveDayRoutine((string) $args['routine'], true);
            if (!$routine) {
                return ['status' => 'error', 'message' => "Routine '{$args['routine']}' wurde nicht gefunden."];
            }

            $changes = [];
            if (!empty($args['new_title'])) {
                $routine->title = substr(trim((string) $args['new_title']), 0, 255);
                $changes[] = "Titel -> '{$routine->title}'";
            }
            if (!empty($args['new_start_time'])) {
                $routine->start_time = Carbon::parse(trim((string) $args['new_start_time']))->format('H:i:00');
                $changes[] = 'Startzeit -> ' . Carbon::parse($routine->start_time)->format('H:i') . ' Uhr';
            }
            if (!empty($args['new_duration_minutes'])) {
                $routine->duration_minutes = max(1, min(1440, (int) $args['new_duration_minutes']));
                $changes[] = "Dauer -> {$routine->duration_minutes} Min.";
            }
            if (!empty($args['new_type'])) {
                $allowedTypes = ['hygiene', 'sport', 'work', 'food', 'break', 'sleep', 'general'];
                $newType = strtolower(trim((string) $args['new_type']));
                if (in_array($newType, $allowedTypes, true)) {
                    $routine->type = $newType;
                    $changes[] = "Kategorie -> {$newType}";
                }
            }
            if (!empty($args['new_days_of_week'])) {
                $allowedDays = ['all', 'weekdays', 'weekend'];
                $newDays = strtolower(trim((string) $args['new_days_of_week']));
                if (in_array($newDays, $allowedDays, true)) {
                    $routine->days_of_week = $newDays;
                    $changes[] = "Wochentage -> {$newDays}";
                }
            }
            if (array_key_exists('new_message', $args)) {
                $routine->message = $args['new_message'] !== null ? substr(trim((string) $args['new_message']), 0, 2000) : null;
                $changes[] = 'Beschreibung aktualisiert';
            }
            if (isset($args['is_active'])) {
                $routine->is_active = (bool) $args['is_active'];
                $changes[] = $routine->is_active ? 'Aktiviert' : 'Pausiert/Deaktiviert';
            }

            $routine->save();

            if (isset($args['replace_steps']) && is_array($args['replace_steps'])) {
                ManagementDayRoutineStep::where('day_routine_id', $routine->id)->delete();
                foreach (array_values($args['replace_steps']) as $idx => $stepItem) {
                    $stepTitle = is_array($stepItem) ? trim((string) ($stepItem['title'] ?? '')) : trim((string) $stepItem);
                    $stepDur = is_array($stepItem) ? max(1, (int) ($stepItem['duration_minutes'] ?? 5)) : 5;
                    if ($stepTitle !== '') {
                        ManagementDayRoutineStep::create([
                            'id' => Str::uuid(),
                            'day_routine_id' => $routine->id,
                            'title' => substr($stepTitle, 0, 255),
                            'duration_minutes' => $stepDur,
                            'position' => $idx + 1,
                        ]);
                    }
                }
                $changes[] = 'Unterschritte neu gesetzt (' . count($args['replace_steps']) . ')';
            }

            return [
                'status' => 'success',
                'message' => "Routine '{$routine->title}' wurde erfolgreich aktualisiert (" . (implode(', ', $changes) ?: 'Keine Änderungen') . ').',
                'routine_id' => $routine->id,
                'routine' => $routine->fresh('steps')->toArray(),
                '_frontend_event' => [
                    'type' => 'dispatch',
                    'name' => 'routine-updated',
                    'detail' => ['routine_id' => $routine->id]
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Aktualisieren der Tagesroutine: ' . $e->getMessage()];
        }
    }

    /**
     * 8. Tagesroutine löschen.
     */
    public static function executeDeleteDayRoutine(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            if (empty($args['routine'])) {
                return ['status' => 'error', 'message' => 'Bitte gib die zu löschende Routine (ID, Titel oder Uhrzeit) an.'];
            }

            $routine = self::resolveDayRoutine((string) $args['routine'], true);
            if (!$routine) {
                return ['status' => 'error', 'message' => "Routine '{$args['routine']}' wurde nicht gefunden."];
            }

            $deletedTitle = Carbon::parse($routine->start_time)->format('H:i') . ' ' . $routine->title;
            $routine->steps()->delete();
            $routine->logs()->delete();
            $routine->delete();

            return [
                'status' => 'success',
                'message' => "Die Tagesroutine '{$deletedTitle}' wurde inklusive aller Unterschritte gelöscht.",
                '_frontend_event' => [
                    'type' => 'dispatch',
                    'name' => 'routine-updated',
                    'detail' => ['deleted' => $deletedTitle]
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Tagesroutine: ' . $e->getMessage()];
        }
    }

    /**
     * 9. Unterschritte (Steps) einer Routine gezielt hinzufügen, bearbeiten oder löschen.
     */
    public static function executeManageRoutineSteps(array $args)
    {
        if ($denied = self::assertRoutineStaffAccess()) {
            return $denied;
        }

        try {
            if (empty($args['routine']) || empty($args['action'])) {
                return ['status' => 'error', 'message' => 'Bitte gib routine und action ("add", "update" oder "delete") an.'];
            }

            $routine = self::resolveDayRoutine((string) $args['routine'], true);
            if (!$routine) {
                return ['status' => 'error', 'message' => "Routine '{$args['routine']}' wurde nicht gefunden."];
            }

            $action = strtolower(trim((string) $args['action']));

            if ($action === 'add') {
                $title = trim((string) ($args['title'] ?? ''));
                if ($title === '') {
                    return ['status' => 'error', 'message' => 'Für action="add" ist ein Titel (title) des Unterschritts erforderlich.'];
                }
                $duration = max(1, (int) ($args['duration_minutes'] ?? 5));
                $maxPos = (int) $routine->steps()->max('position');
                $pos = !empty($args['position']) ? max(1, (int) $args['position']) : ($maxPos + 1);

                $step = ManagementDayRoutineStep::create([
                    'id' => Str::uuid(),
                    'day_routine_id' => $routine->id,
                    'title' => substr($title, 0, 255),
                    'duration_minutes' => $duration,
                    'position' => $pos,
                ]);

                return [
                    'status' => 'success',
                    'message' => "Unterschritt '{$step->title}' ({$step->duration_minutes} Min.) wurde zur Routine '{$routine->title}' hinzugefügt.",
                    'step' => $step->toArray(),
                    '_frontend_event' => [
                        'type' => 'dispatch',
                        'name' => 'routine-updated',
                        'detail' => ['routine_id' => $routine->id]
                    ]
                ];
            }

            if ($action === 'update') {
                $targetStep = self::resolveRoutineStep($routine, $args['step'] ?? null);
                if (!$targetStep) {
                    return ['status' => 'error', 'message' => "Unterschritt '{$args['step']}' in Routine '{$routine->title}' nicht gefunden."];
                }

                if (!empty($args['title'])) {
                    $targetStep->title = substr(trim((string) $args['title']), 0, 255);
                }
                if (!empty($args['duration_minutes'])) {
                    $targetStep->duration_minutes = max(1, (int) $args['duration_minutes']);
                }
                if (!empty($args['position'])) {
                    $targetStep->position = max(1, (int) $args['position']);
                }
                $targetStep->save();

                return [
                    'status' => 'success',
                    'message' => "Unterschritt '{$targetStep->title}' in Routine '{$routine->title}' wurde aktualisiert.",
                    'step' => $targetStep->toArray(),
                    '_frontend_event' => [
                        'type' => 'dispatch',
                        'name' => 'routine-updated',
                        'detail' => ['routine_id' => $routine->id]
                    ]
                ];
            }

            if ($action === 'delete') {
                $targetStep = self::resolveRoutineStep($routine, $args['step'] ?? $args['title'] ?? null);
                if (!$targetStep) {
                    return ['status' => 'error', 'message' => "Unterschritt in Routine '{$routine->title}' nicht gefunden."];
                }

                $deletedStepTitle = $targetStep->title;
                $targetStep->delete();

                // Positionen sauber neu durchnummerieren
                $remaining = ManagementDayRoutineStep::where('day_routine_id', $routine->id)->orderBy('position')->get();
                foreach ($remaining as $idx => $remStep) {
                    $remStep->update(['position' => $idx + 1]);
                }

                return [
                    'status' => 'success',
                    'message' => "Unterschritt '{$deletedStepTitle}' wurde aus der Routine '{$routine->title}' entfernt.",
                    '_frontend_event' => [
                        'type' => 'dispatch',
                        'name' => 'routine-updated',
                        'detail' => ['routine_id' => $routine->id]
                    ]
                ];
            }

            return ['status' => 'error', 'message' => "Unbekannte Aktion '{$action}'. Erlaubt sind: add, update, delete."];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Verwaltung der Unterschritte: ' . $e->getMessage()];
        }
    }
}

<?php

namespace App\Services\AI\Functions;

use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Illuminate\Support\Str;

trait AiTaskFuncs
{
    /**
     * Intelligente Erkennung und Auflösung einer Aufgabenliste (per UUID, exaktem Namen, Slug oder Teilname).
     */
    public static function resolveTaskListId(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $input = trim($input);

        // 1. Direkte UUID
        if (Str::isUuid($input)) {
            $list = ManagementTaskList::find($input);
            if ($list) {
                return $list->id;
            }
        }

        // 2. Exakter Namensabgleich (case-insensitive)
        $list = ManagementTaskList::whereRaw('LOWER(name) = ?', [mb_strtolower($input)])->first();
        if ($list) {
            return $list->id;
        }

        // 3. Normalisierter Abgleich (Bindestriche, Unterstriche, Leerzeichen ignorieren)
        // z.B. "phase_1_produkte" oder "Phase 1 Produkte" -> "phase1produkte"
        $normalize = function ($str) {
            return preg_replace('/[^a-z0-9]/', '', mb_strtolower($str));
        };
        $normalizedInput = $normalize($input);

        if (!empty($normalizedInput)) {
            $allLists = ManagementTaskList::all();
            foreach ($allLists as $l) {
                if ($normalize($l->name) === $normalizedInput) {
                    return $l->id;
                }
            }

            // 4. Substring-Matching im normalisierten Namen
            foreach ($allLists as $l) {
                $normListName = $normalize($l->name);
                if (!empty($normListName) && (str_contains($normListName, $normalizedInput) || str_contains($normalizedInput, $normListName))) {
                    return $l->id;
                }
            }
        }

        // 5. Standard SQL LIKE Abgleich
        $escapedInput = addcslashes($input, '%_\\');
        $list = ManagementTaskList::where('name', 'LIKE', '%' . $escapedInput . '%')->first();
        if ($list) {
            return $list->id;
        }

        return null;
    }

    /**
     * Löst ein Aufgaben-Ticket schnell per UUID, exaktem Titel oder normalisiertem Teil-Titel auf.
     */
    public static function resolveTaskRecord(?string $input, bool $includeCompleted = true): ?ManagementTask
    {
        if (empty($input)) {
            return null;
        }

        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if (Str::isUuid($input)) {
            $task = ManagementTask::where('is_archived', false)->find($input);
            if ($task) {
                return $task;
            }
        }

        $baseQuery = fn () => ManagementTask::where('is_archived', false)
            ->when(!$includeCompleted, fn ($q) => $q->where('is_completed', false));

        $exact = $baseQuery()
            ->whereNull('parent_id')
            ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($input)])
            ->first();
        if ($exact) {
            return $exact;
        }

        $escaped = addcslashes($input, '%_\\');
        $likeParent = $baseQuery()
            ->whereNull('parent_id')
            ->where('title', 'LIKE', '%' . $escaped . '%')
            ->orderBy('is_completed', 'asc')
            ->orderBy('is_mine', 'desc')
            ->orderBy('position', 'asc')
            ->first();
        if ($likeParent) {
            return $likeParent;
        }

        $normalize = fn (string $s) => preg_replace('/[^a-z0-9äöüß]/u', '', mb_strtolower($s));
        $normInput = $normalize($input);
        if (mb_strlen($normInput) >= 3) {
            $candidates = $baseQuery()->whereNull('parent_id')->limit(200)->get();
            foreach ($candidates as $candidate) {
                $normTitle = $normalize((string) $candidate->title);
                if ($normTitle !== '' && (str_contains($normTitle, $normInput) || str_contains($normInput, $normTitle))) {
                    return $candidate;
                }
            }
        }

        return $baseQuery()->where('title', 'LIKE', '%' . $escaped . '%')->first();
    }

    /**
     * Übersetzt deutsche und englische Status-Begriffe auf die 4 Ticket-Statuswerte.
     */
    public static function normalizeAiTaskStatus(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $clean = mb_strtolower(trim($raw));

        return match ($clean) {
            'open', 'offen', 'rot' => ManagementTask::STATUS_OPEN,
            'waiting', 'warten', 'wartend', 'gelb' => ManagementTask::STATUS_WAITING,
            'in_progress', 'in bearbeitung', 'bearbeitung', 'in arbeit', 'orange' => ManagementTask::STATUS_IN_PROGRESS,
            'completed', 'done', 'erledigt', 'abgeschlossen', 'grün', 'gruen' => ManagementTask::STATUS_COMPLETED,
            default => in_array($clean, ManagementTask::VALID_STATUSES, true) ? $clean : null,
        };
    }

    /**
     * Sammelt alle Dateianhänge eines Tickets (Legacy file_paths + Ticket-Chat Anhänge) dublettenfrei.
     *
     * @return array<int, array{filename: string, path: string, source: string, message_id?: string}>
     */
    private static function collectTaskAttachments(ManagementTask $task): array
    {
        $seen = [];
        $attachments = [];

        if (!empty($task->file_paths) && is_array($task->file_paths)) {
            foreach ($task->file_paths as $path) {
                $pathStr = trim((string) $path);
                if ($pathStr === '' || isset($seen[$pathStr])) {
                    continue;
                }
                $seen[$pathStr] = true;
                $attachments[] = [
                    'filename' => basename($pathStr),
                    'path' => $pathStr,
                    'source' => 'ticket',
                ];
            }
        }

        $messages = $task->relationLoaded('messages')
            ? $task->messages
            : $task->messages()->orderBy('created_at', 'asc')->get();

        foreach ($messages as $msg) {
            foreach ((array) ($msg->attachments ?? []) as $att) {
                $pathStr = trim((string) ($att['path'] ?? ''));
                if ($pathStr === '' || isset($seen[$pathStr])) {
                    continue;
                }
                $seen[$pathStr] = true;
                $attachments[] = [
                    'filename' => (string) ($att['original_name'] ?? basename($pathStr)),
                    'path' => $pathStr,
                    'source' => 'chat',
                    'message_id' => $msg->id,
                ];
            }
        }

        return $attachments;
    }

    public static function getAiTaskFuncsSchema(): array
    {
        return [
            [
                'name' => 'task_get_all',
                'description' => 'Liest Alinas Aufgaben-Tickets schnell und tokenarm aus (Kacheln "Meine Tickets" via only_mine=true oder "Offene Tickets" via only_mine=false, die 4 Status open/waiting/in_progress/completed, Ticket-Chat-Verlauf, Beschreibung, Fälligkeit mit Uhrzeit, Listen und alle Dateianhänge). Übergib task_id oder search + include_messages=true, um ein konkretes Ticket samt Chat und Dateien vollständig zu lesen. ACHTUNG: Nicht für die Einkaufsliste.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Optional: UUID oder Teil-Titel eines konkreten Tickets, um dessen Details, Chat-Nachrichten und Dateianhänge direkt zu lesen.'
                        ],
                        'only_mine' => [
                            'type' => 'boolean',
                            'description' => 'Optional: true = nur Kachel "Meine Tickets", false = nur Kachel "Offene Tickets". Weglassen für beide Kacheln.'
                        ],
                        'list_name' => [
                            'type' => 'string',
                            'description' => 'Optional: Filter nach Listenname (z.B. "Fahrplan bis Livegang", "Kanzlei & Steuern").'
                        ],
                        'task_list_id' => [
                            'type' => 'string',
                            'description' => 'Optional: Exakte ID oder Name der Liste.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Optional: Filter nach Status ("open" = Offen/Rot, "waiting" = Warten/Gelb, "in_progress" = In Bearbeitung/Orange, "completed" = Erledigt/Grün, "escalated" = Eskaliert).',
                            'enum' => ['open', 'waiting', 'in_progress', 'completed', 'escalated']
                        ],
                        'search' => [
                            'type' => 'string',
                            'description' => 'Optional: Suchbegriff im Titel, Bearbeiter oder der Beschreibung des Tickets.'
                        ],
                        'assignee' => [
                            'type' => 'string',
                            'description' => 'Optional: Filter nach Bearbeiter-Name (z.B. "Alina" oder Mitarbeitername, oder "none" für unzugewiesene Tickets).'
                        ],
                        'include_messages' => [
                            'type' => 'boolean',
                            'description' => 'Optional: true lädt die Ticket-Beschreibung und die letzten Chat-Einträge direkt mit (automatisch aktiv bei task_id).'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetTasks']
            ],
            [
                'name' => 'task_get_lists',
                'description' => 'Ruft alle existierenden Aufgabenlisten (unterhalb von "Meine Tickets") mit Namen, Reihenfolge und offenen Ticket-Zahlen ab.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTaskLists']
            ],
            [
                'name' => 'task_create',
                'description' => 'Erstellt ein neues Aufgaben-Ticket. Standardmäßig landet es mit Status "open" (Offen) in "Offene Tickets"; mit is_mine=true landet es direkt in "Meine Tickets" (Status bleibt frei wählbar). Optional mit Beschreibung, erstem Chat-Eintrag, Datum, Uhrzeit und Liste. ACHTUNG: Nicht für die Einkaufsliste.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titel des neuen Tickets.'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priorität (Standard: mittel)',
                            'enum' => ['hoch', 'mittel', 'niedrig']
                        ],
                        'list_name' => [
                            'type' => 'string',
                            'description' => 'Name oder Teilname der Ziel-Liste (z.B. "Fahrplan bis Livegang", "Kanzlei & Steuern").'
                        ],
                        'task_list_id' => [
                            'type' => 'string',
                            'description' => 'Optionale ID, Name oder Slug der Liste.'
                        ],
                        'relevant_from' => [
                            'type' => 'string',
                            'description' => 'Optionales Fälligkeitsdatum (z.B. "2026-11-28", "heute", "morgen").'
                        ],
                        'due_time' => [
                            'type' => 'string',
                            'description' => 'Optionale Uhrzeit im Format HH:MM (z.B. "14:30") für direkte Kalender-Einplanung.'
                        ],
                        'duration_minutes' => [
                            'type' => 'integer',
                            'description' => 'Optionale Dauer in Minuten für den Kalender-Zeitslot (Standard: 60).'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Optionaler Ticket-Status ("open" = Offen, "waiting" = Warten, "in_progress" = In Bearbeitung, "completed" = Erledigt). Standard: open.',
                            'enum' => ['open', 'waiting', 'in_progress', 'completed']
                        ],
                        'is_mine' => [
                            'type' => 'boolean',
                            'description' => 'Optional: true legt das Ticket direkt in die Kachel "Meine Tickets" (ohne den Status zwangsweise zu ändern). Standard: false.'
                        ],
                        'assign_to' => [
                            'type' => 'string',
                            'description' => 'Optional: Name oder Rolle des gewünschten Bearbeiters (z.B. "Alina" oder Name eines Mitarbeiters; unscharfe Suche aktiv).'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optionale Ticket-Beschreibung / Notiz.'
                        ],
                        'chat_note' => [
                            'type' => 'string',
                            'description' => 'Optionaler erster Eintrag direkt im Ticket-Chat.'
                        ],
                        'subtasks' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optionale Teilschritte, die direkt mit angelegt werden.'
                        ]
                    ],
                    'required' => ['title']
                ],
                'callable' => [self::class, 'executeCreateTask']
            ],
            [
                'name' => 'task_add_subtask',
                'description' => 'Fügt einem bestehenden Ticket einen Teilschritt hinzu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'UUID oder Teil-Titel des Tickets.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titel des neuen Teilschritts.'
                        ]
                    ],
                    'required' => ['task_id', 'title']
                ],
                'callable' => [self::class, 'executeAddSubtask']
            ],
            [
                'name' => 'task_update',
                'description' => 'Zentrales Schnell-Werkzeug für Ticket-Änderungen: Schiebt ein Ticket in/aus "Meine Tickets" (is_mine=true/false, Status bleibt unverändert!), ändert den Status (open=Offen, waiting=Warten, in_progress=In Bearbeitung, completed=Erledigt), schreibt in den Ticket-Chat (chat_note), passt den letzten eigenen Chat-Eintrag innerhalb von 5 Minuten an (edit_last_chat_note), ändert Titel, Reihenfolge (new_position), Liste oder Datum/Uhrzeit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'UUID oder Titel / Teil-Titel des Tickets.'
                        ],
                        'is_mine' => [
                            'type' => 'boolean',
                            'description' => 'Optional: true schiebt das Ticket in "Meine Tickets", false schiebt es zurück in "Offene Tickets" (der Status bleibt dabei unverändert!).'
                        ],
                        'assign_to' => [
                            'type' => 'string',
                            'description' => 'Optional: Weist das Ticket einem Bearbeiter per Namen/Kürzel zu (z.B. "Alina", Mitarbeitername oder "none" zum Aufheben der Zuweisung).'
                        ],
                        'new_status' => [
                            'type' => 'string',
                            'description' => 'Optional: Neuer Status ("open" = Offen/Rot, "waiting" = Warten/Gelb, "in_progress" = In Bearbeitung/Orange, "completed" = Erledigt/Grün).',
                            'enum' => ['open', 'waiting', 'in_progress', 'completed']
                        ],
                        'chat_note' => [
                            'type' => 'string',
                            'description' => 'Optional: Schreibt eine neue Nachricht / ein Update direkt in den Ticket-Chat.'
                        ],
                        'edit_last_chat_note' => [
                            'type' => 'string',
                            'description' => 'Optional: Korrigiert den Text des letzten Chat-Eintrags (innerhalb des 5-Minuten-Bearbeitungsfensters).'
                        ],
                        'new_title' => [
                            'type' => 'string',
                            'description' => 'Neuer Titel des Tickets (optional).'
                        ],
                        'new_description' => [
                            'type' => 'string',
                            'description' => 'Neue Ticket-Beschreibung / Notiz (optional).'
                        ],
                        'new_priority' => [
                            'type' => 'string',
                            'description' => 'Neue Priorität (optional: hoch, mittel, niedrig).'
                        ],
                        'new_list_name' => [
                            'type' => 'string',
                            'description' => 'Name der neuen Ziel-Liste (optional).'
                        ],
                        'new_list_id' => [
                            'type' => 'string',
                            'description' => 'Neue Listen-ID oder Name (optional).'
                        ],
                        'new_relevant_from' => [
                            'type' => 'string',
                            'description' => 'Neues Fälligkeitsdatum (z.B. YYYY-MM-DD, "heute", "morgen" oder leer zum Entfernen).'
                        ],
                        'new_due_time' => [
                            'type' => 'string',
                            'description' => 'Neue Uhrzeit im Format HH:MM (z.B. "09:30") oder leer zum Entfernen.'
                        ],
                        'new_duration_minutes' => [
                            'type' => 'integer',
                            'description' => 'Neue Dauer in Minuten (15 bis 720).'
                        ],
                        'new_position' => [
                            'type' => 'integer',
                            'description' => 'Optionale 0-basierte Zielposition innerhalb der Kachel (0 = ganz nach oben).'
                        ]
                    ],
                    'required' => ['task_id']
                ],
                'callable' => [self::class, 'executeUpdateTask']
            ],
            [
                'name' => 'task_complete',
                'description' => 'Markiert eine offene Aufgabe als erledigt. Stichworte: Hake Aufgabe ab, Todo erledigt, Task abschließen, ToDo abhaken.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Die ID oder der genaue Titel der Aufgabe.'
                        ]
                    ],
                    'required' => ['task_id']
                ],
                'callable' => [self::class, 'executeCompleteTask']
            ],
            [
                'name' => 'task_delete',
                'description' => 'Löscht eine oder mehrere Aufgaben vollständig. Kann für Listen von IDs, Titeln oder für ganze Listen (mit Ausnahmen) genutzt werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste von exakten Aufgaben-IDs.'
                        ],
                        'task_titles' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Eine Liste von exakten oder teilweisen Aufgabentiteln.'
                        ],
                        'list_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Wenn gesetzt, werden ALLE Aufgaben in diesen Listen gelöscht.'
                        ],
                        'exclude_titles' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Aufgaben, die NICHT gelöscht werden sollen (nützlich in Kombination mit list_names).'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteTask']
            ],
            [
                'name' => 'task_list_add',
                'description' => 'Erstellt eine neue Aufgabenliste (Kategorie).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name der neuen Liste.'
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Hex-Farbe der Liste (z.B. #10B981).'
                        ],
                        'icon' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Nur GÜLTIGE Heroicons: clipboard-document-list, check-circle, document-text, star, heart, bookmark, home, briefcase, user. Standard: clipboard-document-list.'
                        ]
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeAddTaskList']
            ],
            [
                'name' => 'task_list_update',
                'description' => 'Aktualisiert eine bestehende Aufgabenliste (Name, Farbe, Icon).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'old_name' => [
                            'type' => 'string',
                            'description' => 'Der aktuelle Name der Liste.'
                        ],
                        'new_name' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Der neue Name der Liste.'
                        ],
                        'new_color' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Die neue Hex-Farbe.'
                        ],
                        'new_icon' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Das neue Heroicon.'
                        ]
                    ],
                    'required' => ['old_name']
                ],
                'callable' => [self::class, 'executeUpdateTaskList']
            ],
            [
                'name' => 'task_list_delete',
                'description' => 'Löscht eine oder mehrere Aufgabenlisten. Kann dynamisch gesteuert werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'list_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Die Namen der zu löschenden Listen.'
                        ],
                        'delete_all' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden ALLE Listen gelöscht (kann mit exclude_names kombiniert werden).'
                        ],
                        'exclude_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Listen, die NICHT gelöscht werden sollen.'
                        ],
                        'delete_tasks' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden alle Aufgaben in diesen Listen gelöscht. Sonst fallen sie auf "Ohne Liste" zurück.'
                        ],
                        'move_tasks_to_list' => [
                            'type' => 'string',
                            'description' => 'OPTIONAL. Name der Zielliste. Aufgaben werden dorthin verschoben, bevor die Ursprungsliste gelöscht wird.'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeDeleteTaskList']
            ],
            [
                'name' => 'task_read_file',
                'description' => 'Liest den Inhalt einer angehängten Datei aus einem Ticket oder Ticket-Chat (PDF, TXT, CSV, JSON, XML, MD) oder analysiert ein Bild (PNG, JPG, WEBP, GIF). Du kannst entweder den Pfad aus task_get_all ("leitung/tasks/attachments/...") oder einfach den Dateinamen zusammen mit task_id übergeben.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'file_path' => [
                            'type' => 'string',
                            'description' => 'Pfad der Datei ("leitung/tasks/attachments/filename.pdf") oder Dateiname (z.B. "angebot.pdf").'
                        ],
                        'task_id' => [
                            'type' => 'string',
                            'description' => 'Optional: UUID oder Titel des zugehörigen Tickets zur schnellen Dateinamen-Auflösung.'
                        ]
                    ],
                    'required' => ['file_path']
                ],
                'callable' => [self::class, 'executeReadFile']
            ]
        ];
    }

    private static function assertTaskStaffAccess(): ?array
    {
        if (\App\Services\AI\AiAuthHelper::check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            return ['status' => 'error', 'message' => 'Zugriff verweigert: Aufgabenverwaltung ist nur für interne Mitarbeiter und Administratoren freigegeben.'];
        }
        return null;
    }

    public static function executeGetTasks(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            $ticketService = app(\App\Services\Management\ManagementTaskTicketService::class);
            $ticketService->evaluateEscalations();

            $specificTaskQuery = trim((string) ($args['task_id'] ?? ''));
            $statusRaw = isset($args['status']) ? strtolower(trim((string) $args['status'])) : null;
            $normalizedStatus = self::normalizeAiTaskStatus($statusRaw);
            $includeCompleted = ($normalizedStatus === ManagementTask::STATUS_COMPLETED) || ($specificTaskQuery !== '');

            $query = ManagementTask::rootTickets()
                ->with(['list', 'subtasks', 'messages'])
                ->withCount('messages');

            if (!$includeCompleted) {
                $query->where('is_completed', false)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '!=', ManagementTask::STATUS_COMPLETED);
                    });
            }

            if ($specificTaskQuery !== '') {
                $resolved = self::resolveTaskRecord($specificTaskQuery, true);
                if ($resolved) {
                    $query->where('id', $resolved->id);
                } else {
                    $escaped = addcslashes($specificTaskQuery, '%_\\');
                    $query->where('title', 'like', '%' . $escaped . '%');
                }
            }

            if (array_key_exists('only_mine', $args) && $args['only_mine'] !== null) {
                $query->where('is_mine', (bool) $args['only_mine']);
            }

            $listFilter = $args['task_list_id'] ?? $args['list_name'] ?? null;
            if (!empty($listFilter)) {
                $resolvedListId = self::resolveTaskListId($listFilter);
                if ($resolvedListId) {
                    $query->where('task_list_id', $resolvedListId);
                }
            }

            if ($statusRaw === 'escalated') {
                $query->whereNotNull('escalated_at');
            } elseif ($normalizedStatus !== null) {
                if ($normalizedStatus === ManagementTask::STATUS_COMPLETED) {
                    $query->where(function ($q) {
                        $q->where('status', ManagementTask::STATUS_COMPLETED)->orWhere('is_completed', true);
                    });
                } elseif ($normalizedStatus === ManagementTask::STATUS_OPEN) {
                    $query->where(function ($q) {
                        $q->where('status', ManagementTask::STATUS_OPEN)->orWhereNull('status');
                    });
                } else {
                    $query->where('status', $normalizedStatus);
                }
            }

            if (!empty($args['assignee']) && \Illuminate\Support\Facades\Schema::hasColumn('management_tasks', 'assignee_id')) {
                $rawAss = trim((string) $args['assignee']);
                if (in_array(strtolower($rawAss), ['none', 'unassigned', 'niemand', 'keiner', 'frei'], true)) {
                    $query->whereNull('assignee_id');
                } else {
                    $matchedActor = $ticketService->resolveActorByKey($rawAss);
                    if ($matchedActor) {
                        $query->where('assignee_type', $matchedActor['type'])
                            ->where('assignee_id', $matchedActor['id']);
                    } else {
                        $escAss = addcslashes($rawAss, '%_\\');
                        $query->where('assignee_name', 'like', '%' . $escAss . '%');
                    }
                }
            }

            if (!empty($args['search'])) {
                $escapedSearch = addcslashes((string) $args['search'], '%_\\');
                $query->where(function ($q) use ($escapedSearch) {
                    $q->where('title', 'like', '%' . $escapedSearch . '%')
                      ->orWhere('ai_plan', 'like', '%' . $escapedSearch . '%')
                      ->orWhere('assignee_name', 'like', '%' . $escapedSearch . '%');
                });
            }

            $tasks = $query
                ->orderByDesc('is_mine')
                ->orderBy('my_position', 'asc')
                ->orderBy('position', 'asc')
                ->orderBy('created_at', 'desc')
                ->limit(30)
                ->get();

            $includeMessages = !empty($args['include_messages'])
                || $specificTaskQuery !== ''
                || (!empty($args['search']) && $tasks->count() <= 3);

            $formattedTasks = $tasks->map(function (ManagementTask $task) use ($includeMessages, $ticketService) {
                $attachments = self::collectTaskAttachments($task);

                $subtasks = $task->subtasks->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'title' => $sub->title,
                        'is_completed' => (bool) $sub->is_completed,
                    ];
                })->toArray();

                $effectiveStatus = $task->is_completed
                    ? ManagementTask::STATUS_COMPLETED
                    : ManagementTask::normalizeStatus($task->status);

                $entry = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'is_mine' => (bool) $task->is_mine,
                    'tile' => $task->is_mine ? 'Meine Tickets' : 'Offene Tickets',
                    'assignee' => $task->assignee_name ?: 'Kein Bearbeiter',
                    'assignee_key' => $task->assignee_key,
                    'status' => $effectiveStatus,
                    'status_label' => ManagementTask::statusLabelFor($effectiveStatus),
                    'priority' => $task->priority,
                    'is_escalated' => (bool) $task->is_escalated,
                    'escalation_reason' => $task->escalation_reason,
                    'created_at' => $task->created_at?->format('d.m.Y H:i'),
                    'relevant_from' => $task->relevant_from?->format('Y-m-d'),
                    'due_time' => $task->due_time,
                    'duration_minutes' => (int) ($task->duration_minutes ?: 60),
                    'messages_count' => (int) ($task->messages_count ?? $task->messages->count()),
                    'task_list_id' => $task->task_list_id,
                    'list_name' => $task->list?->name ?? 'Ohne Liste',
                    'subtasks' => $subtasks,
                    'has_attachments' => !empty($attachments),
                    'attachments' => $attachments,
                ];

                if ($includeMessages) {
                    $entry['description'] = $task->ai_plan;
                    $entry['messages'] = $task->messages
                        ->sortBy('created_at')
                        ->take(-15)
                        ->values()
                        ->map(fn ($m) => $ticketService->serializeMessage($m))
                        ->all();
                } elseif (!empty($task->ai_plan)) {
                    $entry['description_preview'] = Str::limit((string) $task->ai_plan, 180);
                }

                return $entry;
            });

            return [
                'status' => 'success',
                'open_tasks_count' => $formattedTasks->count(),
                'tasks' => $formattedTasks->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetTaskLists(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            $lists = ManagementTaskList::where('is_archived', false)
                ->withCount(['tasks as open_tasks_count' => function ($q) {
                    $q->where('is_completed', false)->whereNull('parent_id')->where('is_archived', false);
                }])
                ->orderBy('position', 'asc')
                ->orderBy('created_at', 'asc')
                ->get(['id', 'name', 'color', 'icon', 'position']);

            return [
                'status' => 'success',
                'lists_count' => $lists->count(),
                'lists' => $lists->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeCreateTask(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            if (empty($args['title'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Titel für die Aufgabe angegeben.'];
            }

            $title = trim($args['title']);

            // 1. Ziel-Liste ermitteln
            $targetListId = null;
            $targetListName = null;
            $listQuery = $args['task_list_id'] ?? $args['list_name'] ?? $args['task_list_name'] ?? null;

            if (!empty($listQuery)) {
                $targetListId = self::resolveTaskListId($listQuery);
            }

            if (!empty($listQuery) && !$targetListId) {
                $newList = ManagementTaskList::firstOrCreate(
                    ['name' => trim($listQuery)],
                    ['icon' => 'clipboard-document-list', 'color' => '#C5A059']
                );
                $targetListId = $newList->id;
                $targetListName = $newList->name;
            } elseif ($targetListId) {
                $targetList = ManagementTaskList::find($targetListId);
                $targetListName = $targetList?->name;
            }

            if (!$targetListId) {
                $defaultList = ManagementTaskList::where('is_archived', false)
                    ->orderBy('position', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($defaultList) {
                    $targetListId = $defaultList->id;
                    $targetListName = $defaultList->name;
                } else {
                    $list = ManagementTaskList::firstOrCreate(
                        ['name' => 'Allgemein'],
                        ['icon' => 'sparkles', 'color' => '#10B981']
                    );
                    $targetListId = $list->id;
                    $targetListName = $list->name;
                }
            }

            // 2. Exakter Duplikats-Check NUR innerhalb derselben Liste
            $exactExisting = ManagementTask::where('task_list_id', $targetListId)
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($title)])
                ->first();

            if ($exactExisting) {
                return [
                    'status' => 'success',
                    'message' => "Das Ticket '{$exactExisting->title}' existiert bereits in der Liste '{$targetListName}' und ist noch aktiv.",
                    'task_id' => $exactExisting->id,
                    'list_name' => $targetListName,
                    'already_existed' => true
                ];
            }

            // 3. Fälligkeitsdatum / Relevanzdatum parsen
            $relevantFrom = null;
            if (!empty($args['relevant_from'])) {
                try {
                    $relString = strtolower(trim($args['relevant_from']));
                    if ($relString === 'heute') {
                        $relevantFrom = now()->startOfDay();
                    } elseif ($relString === 'morgen') {
                        $relevantFrom = now()->addDay()->startOfDay();
                    } else {
                        $relevantFrom = \Carbon\Carbon::parse($args['relevant_from'])->startOfDay();
                    }
                } catch (\Exception $e) {
                    // Ignore parse error
                }
            }

            // 4. Priorität normalisieren
            $priority = strtolower($args['priority'] ?? 'mittel');
            if (!in_array($priority, ['hoch', 'mittel', 'niedrig'])) {
                $priority = 'mittel';
            }

            // 5. Status, Meine-Tickets-Flag, Uhrzeit & Dauer normalisieren
            $status = self::normalizeAiTaskStatus($args['status'] ?? null) ?? ManagementTask::STATUS_OPEN;
            $isMine = !empty($args['is_mine']);
            $dueTime = ManagementTask::normalizeDueTime($args['due_time'] ?? null);
            $durationMinutes = max(15, min(720, (int) ($args['duration_minutes'] ?? 60)));
            $isCompleted = $status === ManagementTask::STATUS_COMPLETED;

            $nextPos = $isMine
                ? ((int) ManagementTask::where('is_mine', true)->whereNull('parent_id')->max('my_position') + 1)
                : ((int) ManagementTask::where('is_mine', false)->whereNull('parent_id')->max('position') + 1);

            // 6. Ticket anlegen (inkl. optionalem Bearbeiter via Fuzzy-Auflösung)
            $ticketService = app(\App\Services\Management\ManagementTaskTicketService::class);
            $actor = $ticketService->resolveCurrentActor();
            $resolvedAssignee = !empty($args['assign_to'])
                ? $ticketService->resolveActorByKey((string) $args['assign_to'])
                : (($isMine || $status === ManagementTask::STATUS_IN_PROGRESS) ? $actor : null);

            $createPayload = [
                'title' => substr($title, 0, 255),
                'ai_plan' => !empty($args['description']) ? trim((string) $args['description']) : null,
                'status' => $status,
                'is_mine' => $isMine,
                'position' => $isMine ? 0 : $nextPos,
                'my_position' => $isMine ? $nextPos : 0,
                'priority' => $priority,
                'is_completed' => $isCompleted,
                'completed_at' => $isCompleted ? now() : null,
                'task_list_id' => $targetListId,
                'relevant_from' => $relevantFrom,
                'due_time' => $dueTime,
                'duration_minutes' => $durationMinutes,
                'last_activity_at' => now(),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('management_tasks', 'assignee_id')) {
                $createPayload['assignee_type'] = $resolvedAssignee['type'] ?? null;
                $createPayload['assignee_id'] = $resolvedAssignee['id'] ?? null;
                $createPayload['assignee_name'] = $resolvedAssignee['name'] ?? null;
                $createPayload['assignee_avatar'] = $resolvedAssignee['avatar'] ?? null;
                $createPayload['created_by_type'] = $actor['type'];
                $createPayload['created_by_id'] = $actor['id'];
            }
            $task = ManagementTask::create($createPayload);

            if (!empty(trim((string) ($args['chat_note'] ?? '')))) {
                app(\App\Services\Management\ManagementTaskTicketService::class)->postMessage(
                    $task,
                    trim((string) $args['chat_note'])
                );
            }

            // 7. Teilschritte anlegen falls übergeben
            $subtasksCreated = [];
            if (!empty($args['subtasks']) && is_array($args['subtasks'])) {
                foreach ($args['subtasks'] as $subTitle) {
                    if (!empty(trim($subTitle))) {
                        $sub = ManagementTask::create([
                            'title' => substr(trim($subTitle), 0, 255),
                            'priority' => 'niedrig',
                            'is_completed' => false,
                            'task_list_id' => $targetListId,
                            'parent_id' => $task->id
                        ]);
                        $subtasksCreated[] = $sub->title;
                    }
                }
            }

            $tileLabel = $task->is_mine ? 'Meine Tickets' : 'Offene Tickets';
            $statusLabel = ManagementTask::statusLabelFor($task->status);
            $msg = "Ticket '{$task->title}' wurde in '{$tileLabel}' (Liste '{$targetListName}', Status '{$statusLabel}') angelegt.";
            if (!empty($subtasksCreated)) {
                $msg .= " Inklusive " . count($subtasksCreated) . " Teilschritten (" . implode(', ', $subtasksCreated) . ").";
            }
            if ($relevantFrom) {
                $msg .= " Fällig/Geplant ab: " . $relevantFrom->format('d.m.Y') . ($dueTime ? ' um ' . $dueTime . ' Uhr' : '') . ".";
            }

            return [
                'status' => 'success',
                'message' => $msg,
                'task_id' => $task->id,
                'ticket_status' => $task->status,
                'status_label' => $statusLabel,
                'is_mine' => (bool) $task->is_mine,
                'title' => $task->title,
                'list_name' => $targetListName,
                'list_id' => $targetListId
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Aufgabe: ' . $e->getMessage()];
        }
    }

    public static function executeAddSubtask(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben-ID (task_id) angegeben.'];
            }
            if (empty($args['title'])) {
                return ['status' => 'error', 'message' => 'Es wurde kein Titel für den Teilschritt angegeben.'];
            }

            $parentTask = self::resolveTaskRecord((string) $args['task_id'], false);

            if (!$parentTask) {
                return ['status' => 'error', 'message' => "Übergeordnete Aufgabe '{$args['task_id']}' nicht gefunden."];
            }

            $subtask = ManagementTask::create([
                'title' => substr(trim($args['title']), 0, 255),
                'priority' => 'niedrig',
                'is_completed' => false,
                'task_list_id' => $parentTask->task_list_id,
                'parent_id' => $parentTask->id
            ]);

            return [
                'status' => 'success',
                'message' => "Teilschritt '{$subtask->title}' wurde zu Aufgabe '{$parentTask->title}' hinzugefügt.",
                'subtask_id' => $subtask->id,
                'parent_task_id' => $parentTask->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Hinzufügen des Teilschritts: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateTask(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben ID oder Titel angegeben.'];
            }

            $task = self::resolveTaskRecord((string) $args['task_id'], true);

            if (!$task) {
                return ['status' => 'error', 'message' => 'Ticket nicht gefunden.'];
            }

            $changed = false;
            $updates = [];

            $ticketService = app(\App\Services\Management\ManagementTaskTicketService::class);
            $requestedStatus = isset($args['new_status']) ? self::normalizeAiTaskStatus((string) $args['new_status']) : null;

            if ($task->is_escalated && (!empty($args['new_relevant_from']) || ($requestedStatus !== null && $requestedStatus !== ManagementTask::STATUS_COMPLETED)) && empty(trim((string) ($args['chat_note'] ?? '')))) {
                return [
                    'status' => 'error',
                    'message' => 'Dieses Ticket ist eskaliert. Zum Auflösen der Eskalation werden zwingend ein neues zukünftiges Fälligkeitsdatum (new_relevant_from) UND ein Status-Eintrag im Ticket-Chat (chat_note) benötigt.',
                ];
            }

            if ($task->is_escalated && !empty($args['new_relevant_from']) && !empty(trim((string) ($args['chat_note'] ?? '')))) {
                try {
                    $ticketService->resolveEscalation(
                        task: $task,
                        newDueDate: (string) $args['new_relevant_from'],
                        newDueTime: $args['new_due_time'] ?? $task->due_time,
                        chatStatusText: trim((string) $args['chat_note']),
                        targetStatus: $requestedStatus ?? $task->status ?? ManagementTask::STATUS_IN_PROGRESS,
                        durationMinutes: isset($args['new_duration_minutes']) ? (int) $args['new_duration_minutes'] : null
                    );
                } catch (\InvalidArgumentException $e) {
                    return ['status' => 'error', 'message' => $e->getMessage()];
                }
                $task->refresh();
                $changed = true;
                $updates[] = 'Eskalation aufgelöst';
                unset($args['new_relevant_from'], $args['new_due_time'], $args['chat_note']);
                $requestedStatus = null;
            }

            // Bearbeiter-Zuweisung per Namen/Kuerzel/UUID (unscharfe Suche)
            if (array_key_exists('assign_to', $args) && $args['assign_to'] !== null) {
                $rawTarget = trim((string) $args['assign_to']);
                $task = $ticketService->assignTicket($task, $rawTarget);
                $changed = true;
                $updates[] = $task->assignee_name
                    ? ("Bearbeiter auf '{$task->assignee_name}' gesetzt")
                    : 'Bearbeiter-Zuweisung aufgehoben';
            }

            // Kachel-Wechsel ("Meine Tickets" <-> "Offene Tickets") OHNE Status-Änderung!
            if (array_key_exists('is_mine', $args) && $args['is_mine'] !== null) {
                $targetMine = (bool) $args['is_mine'];
                $targetPos = isset($args['new_position']) ? max(0, (int) $args['new_position']) : null;
                $task = $ticketService->moveToMine($task, $targetMine, $targetPos);
                $changed = true;
                $updates[] = $targetMine ? "in 'Meine Tickets' verschoben (Status unverändert)" : "in 'Offene Tickets' verschoben (Status unverändert)";
                unset($args['new_position']);
            }

            if (!empty($args['new_title'])) {
                $task->title = substr(trim($args['new_title']), 0, 255);
                $changed = true;
                $updates[] = "Titel geändert";
            }

            if (array_key_exists('new_description', $args)) {
                $desc = trim((string) ($args['new_description'] ?? ''));
                $task->ai_plan = $desc !== '' ? $desc : null;
                $changed = true;
                $updates[] = "Beschreibung aktualisiert";
            }

            if (!empty($args['new_priority'])) {
                $prio = strtolower(trim($args['new_priority']));
                if (in_array($prio, ['hoch', 'mittel', 'niedrig'])) {
                    $task->priority = $prio;
                    $changed = true;
                    $updates[] = "Priorität auf '{$prio}' gesetzt";
                }
            }

            $newListQuery = $args['new_list_id'] ?? $args['new_list_name'] ?? null;
            if (!empty($newListQuery)) {
                $resolvedListId = self::resolveTaskListId($newListQuery);
                if ($resolvedListId) {
                    $task->task_list_id = $resolvedListId;
                    $targetList = ManagementTaskList::find($resolvedListId);
                    $changed = true;
                    $updates[] = "in Liste '{$targetList?->name}' verschoben";
                }
            }

            if (isset($args['new_relevant_from'])) {
                if (empty($args['new_relevant_from'])) {
                    $task->relevant_from = null;
                    $task->due_time = null;
                    $changed = true;
                    $updates[] = "Fälligkeitsdatum entfernt";
                } else {
                    try {
                        $relStr = strtolower(trim((string) $args['new_relevant_from']));
                        if ($relStr === 'heute') {
                            $task->relevant_from = now()->startOfDay();
                        } elseif ($relStr === 'morgen') {
                            $task->relevant_from = now()->addDay()->startOfDay();
                        } else {
                            $task->relevant_from = \Carbon\Carbon::parse($args['new_relevant_from'])->startOfDay();
                        }
                        $changed = true;
                        $updates[] = "Fälligkeitsdatum auf " . $task->relevant_from->format('d.m.Y') . " gesetzt";
                    } catch (\Exception $e) {
                        // ignore parse error
                    }
                }
            }

            if (array_key_exists('new_due_time', $args)) {
                $task->due_time = ManagementTask::normalizeDueTime($args['new_due_time']);
                $changed = true;
                $updates[] = $task->due_time ? "Uhrzeit auf {$task->due_time} gesetzt" : "Uhrzeit entfernt";
            }

            if (isset($args['new_duration_minutes'])) {
                $task->duration_minutes = max(15, min(720, (int) $args['new_duration_minutes']));
                $changed = true;
                $updates[] = "Dauer auf {$task->duration_minutes} Min. gesetzt";
            }

            if ($requestedStatus !== null) {
                if ($changed) {
                    $task->save();
                }
                $task = $ticketService->changeStatus($task, $requestedStatus);
                $changed = true;
                $updates[] = "Status auf '" . ManagementTask::statusLabelFor($requestedStatus) . "' gesetzt";
            }

            if (isset($args['new_position'])) {
                $pos = max(0, (int) $args['new_position']);
                $poolQuery = ManagementTask::whereNull('parent_id')
                    ->where('is_archived', false)
                    ->where('is_completed', false)
                    ->where('is_mine', (bool) $task->is_mine)
                    ->orderBy($task->is_mine ? 'my_position' : 'position', 'asc')
                    ->pluck('id')
                    ->values()
                    ->all();
                $withoutCurrent = array_values(array_filter($poolQuery, fn ($id) => $id !== $task->id));
                $clamped = min($pos, count($withoutCurrent));
                array_splice($withoutCurrent, $clamped, 0, [$task->id]);
                $ticketService->reorderTickets($withoutCurrent, (bool) $task->is_mine, 'all');
                $task->refresh();
                $changed = true;
                $updates[] = "auf Position " . ($clamped + 1) . " sortiert";
            }

            if (!empty(trim((string) ($args['edit_last_chat_note'] ?? '')))) {
                $lastEditable = $task->messages()
                    ->whereIn('message_type', [
                        \App\Models\Management\ManagementTaskMessage::TYPE_COMMENT,
                        \App\Models\Management\ManagementTaskMessage::TYPE_ESCALATION_RESOLUTION,
                    ])
                    ->orderByDesc('created_at')
                    ->first();
                if (!$lastEditable) {
                    return ['status' => 'error', 'message' => 'Kein anpassbarer Chat-Eintrag in diesem Ticket gefunden.'];
                }
                if (!$lastEditable->isEditable()) {
                    return ['status' => 'error', 'message' => 'Das 5-Minuten-Bearbeitungsfenster für den letzten Chat-Eintrag ist bereits abgelaufen. Nutze chat_note für einen neuen Eintrag.'];
                }
                $ticketService->editMessage($lastEditable, trim((string) $args['edit_last_chat_note']));
                $changed = true;
                $updates[] = "letzten Chat-Eintrag angepasst";
            }

            if (!empty(trim((string) ($args['chat_note'] ?? '')))) {
                $ticketService->postMessage($task, trim((string) $args['chat_note']));
                $changed = true;
                $updates[] = "Chat-Nachricht eingetragen";
            }

            if ($changed) {
                $task->last_activity_at = now();
                $task->save();
                return [
                    'status' => 'success',
                    'message' => "Ticket '{$task->title}' aktualisiert (" . implode(', ', $updates) . ").",
                    'task_id' => $task->id,
                    'ticket_status' => $task->status,
                    'status_label' => ManagementTask::statusLabelFor($task->status),
                    'is_mine' => (bool) $task->is_mine,
                ];
            }

            return ['status' => 'success', 'message' => 'Es wurden keine Änderungen vorgenommen, da keine neuen Werte übergeben wurden.'];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Bearbeiten der Aufgabe: ' . $e->getMessage()];
        }
    }

    public static function executeCompleteTask(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            if (empty($args['task_id'])) {
                return ['status' => 'error', 'message' => 'Es wurde keine Aufgaben ID oder Titel angegeben.'];
            }

            $task = self::resolveTaskRecord((string) $args['task_id'], false);

            if (!$task) {
                return ['status' => 'error', 'message' => 'Ticket nicht gefunden oder bereits erledigt.'];
            }

            $task = app(\App\Services\Management\ManagementTaskTicketService::class)->changeStatus($task, ManagementTask::STATUS_COMPLETED);

            $listName = $task->list?->name ?? 'Ohne Liste';

            return [
                'status' => 'success',
                'message' => "Ticket '{$task->title}' (Liste '{$listName}') wurde auf Status 'Erledigt' gesetzt.",
                'task_id' => $task->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abschließen der Aufgabe: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteTask(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            $taskIds = $args['task_ids'] ?? [];
            $taskTitles = $args['task_titles'] ?? [];
            $listNames = $args['list_names'] ?? [];
            $excludeTitles = $args['exclude_titles'] ?? [];

            if (empty($taskIds) && empty($taskTitles) && empty($listNames)) {
                return ['status' => 'error', 'message' => 'Es muss entweder eine Liste von IDs, Titeln oder Listen (list_names) angegeben werden.'];
            }

            $query = ManagementTask::query();

            if (!empty($listNames)) {
                $sanitizedListNames = [];
                foreach ($listNames as $lName) {
                    $cleaned = trim((string)$lName);
                    if (mb_strlen($cleaned) >= 2) {
                        $sanitizedListNames[] = addcslashes($cleaned, '%_\\');
                    }
                }
                if (empty($sanitizedListNames)) {
                    return ['status' => 'error', 'message' => 'Die angegebenen Listennamen sind zu kurz oder ungültig.'];
                }
                $query->whereHas('list', function($q) use ($sanitizedListNames) {
                    $q->where(function($subQ) use ($sanitizedListNames) {
                        foreach ($sanitizedListNames as $lName) {
                            $subQ->orWhere('name', 'like', '%' . $lName . '%');
                        }
                    });
                });
            } else {
                $sanitizedTitles = [];
                if (!empty($taskTitles)) {
                    foreach ($taskTitles as $title) {
                        $cleaned = trim((string)$title);
                        if (mb_strlen($cleaned) >= 3) {
                            $sanitizedTitles[] = addcslashes($cleaned, '%_\\');
                        }
                    }
                }

                if (empty($taskIds) && empty($sanitizedTitles)) {
                    return ['status' => 'error', 'message' => 'Keine gültigen Aufgaben-IDs oder ausreichend lange Aufgabentitel (mind. 3 Zeichen) angegeben.'];
                }

                $query->where(function($q) use ($taskIds, $sanitizedTitles) {
                    if (!empty($taskIds)) {
                        $q->whereIn('id', $taskIds);
                    }
                    if (!empty($sanitizedTitles)) {
                        foreach ($sanitizedTitles as $title) {
                            $q->orWhere('title', 'like', '%' . $title . '%');
                        }
                    }
                });
            }

            $tasks = $query->get();

            if ($tasks->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine passenden Aufgaben gefunden.'];
            }

            $deletedCount = 0;
            $deletedTitles = [];

            foreach ($tasks as $task) {
                $isExcluded = false;
                foreach ($excludeTitles as $exTitle) {
                    if (stripos($task->title, $exTitle) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }

                if (empty($listNames) && !empty($taskTitles) && empty($taskIds)) {
                    $isIncluded = false;
                    foreach ($taskTitles as $inTitle) {
                        if (stripos($task->title, $inTitle) !== false) {
                            $isIncluded = true;
                            break;
                        }
                    }
                    if (!$isIncluded) continue;
                }

                if (!$isExcluded) {
                    $deletedTitles[] = $task->title;
                    $task->delete();
                    $deletedCount++;
                }
            }

            return [
                'status' => 'success',
                'message' => "Es wurden $deletedCount Aufgaben gelöscht.",
                'deleted_tasks' => $deletedTitles
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Aufgaben: ' . $e->getMessage()];
        }
    }

    public static function executeAddTaskList(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            if (empty($args['name'])) {
                return ['status' => 'error', 'message' => 'Der Name der Liste fehlt.'];
            }

            $icon = $args['icon'] ?? 'clipboard-document-list';
            $allowedIcons = ['clipboard-document-list', 'check-circle', 'document-text', 'star', 'heart', 'bookmark', 'home', 'briefcase', 'user'];
            if (!in_array($icon, $allowedIcons)) {
                $icon = 'clipboard-document-list';
            }

            $color = $args['color'] ?? '#3B82F6';

            $list = ManagementTaskList::firstOrCreate(
                ['name' => $args['name']],
                ['icon' => $icon, 'color' => $color]
            );

            return ['status' => 'success', 'message' => "Die Liste '{$list->name}' wurde erfolgreich angelegt.", 'list_id' => $list->id];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Anlegen der Liste: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateTaskList(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            if (empty($args['old_name'])) {
                return ['status' => 'error', 'message' => 'Der alte Name der Liste fehlt.'];
            }

            $escapedOldListName = addcslashes((string) $args['old_name'], '%_\\');
            $list = ManagementTaskList::where('name', 'like', $escapedOldListName)->first();

            if (!$list) {
                return ['status' => 'error', 'message' => "Liste '{$args['old_name']}' nicht gefunden."];
            }

            if (!empty($args['new_name'])) {
                $list->name = $args['new_name'];
            }
            if (!empty($args['new_icon'])) {
                $icon = $args['new_icon'];
                $allowedIcons = ['clipboard-document-list', 'check-circle', 'document-text', 'star', 'heart', 'bookmark', 'home', 'briefcase', 'user'];
                if (!in_array($icon, $allowedIcons)) {
                    $icon = 'clipboard-document-list';
                }
                $list->icon = $icon;
            }
            if (!empty($args['new_color'])) {
                $list->color = $args['new_color'];
            }

            $list->save();

            return ['status' => 'success', 'message' => "Die Liste wurde erfolgreich aktualisiert auf: '{$list->name}'."];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Aktualisieren der Liste: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteTaskList(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            $listNames = $args['list_names'] ?? [];
            $deleteAll = $args['delete_all'] ?? false;
            $excludeNames = $args['exclude_names'] ?? [];
            $deleteTasks = $args['delete_tasks'] ?? false;
            $moveToListName = $args['move_tasks_to_list'] ?? null;

            if (empty($listNames) && !$deleteAll) {
                return ['status' => 'error', 'message' => 'Es muss entweder eine Liste von Namen (list_names) oder delete_all = true angegeben werden.'];
            }

            $query = ManagementTaskList::query();

            if (!$deleteAll) {
                $query->where(function($q) use ($listNames) {
                    foreach ($listNames as $lName) {
                        $escapedLName = addcslashes((string) $lName, '%_\\');
                        $q->orWhere('name', 'like', '%' . $escapedLName . '%');
                    }
                });
            }

            $lists = $query->get();

            if ($lists->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine passenden Listen gefunden.'];
            }

            // Ziel-Liste verarbeiten (wenn gesetzt)
            $targetListId = null;
            if (!empty($moveToListName)) {
                $targetList = ManagementTaskList::firstOrCreate(
                    ['name' => $moveToListName],
                    ['icon' => 'clipboard-document-list', 'color' => '#3B82F6']
                );
                $targetListId = $targetList->id;
            }

            $deletedCount = 0;
            $deletedNames = [];

            foreach ($lists as $list) {
                $isExcluded = false;
                foreach ($excludeNames as $exName) {
                    if (stripos($list->name, $exName) !== false) {
                        $isExcluded = true;
                        break;
                    }
                }

                if ($targetListId && $list->id === $targetListId) {
                    $isExcluded = true;
                }

                if (!$isExcluded) {
                    $listId = $list->id;
                    $deletedNames[] = $list->name;

                    if ($targetListId) {
                        ManagementTask::where('task_list_id', $listId)->update(['task_list_id' => $targetListId]);
                    } elseif ($deleteTasks) {
                        ManagementTask::where('task_list_id', $listId)->delete();
                    } else {
                        ManagementTask::where('task_list_id', $listId)->update(['task_list_id' => null]);
                    }

                    $list->delete();
                    $deletedCount++;
                }
            }

            $msg = "Es wurden $deletedCount Listen gelöscht.";
            if ($targetListId) {
                $msg .= " Alle enthaltenen Aufgaben wurden erfolgreich nach '$moveToListName' verschoben.";
            } elseif ($deleteTasks) {
                $msg .= " Alle enthaltenen Aufgaben wurden ebenfalls gelöscht.";
            } else {
                $msg .= " Enthaltene Aufgaben fielen auf 'Ohne Liste' zurück.";
            }

            return [
                'status' => 'success',
                'message' => $msg,
                'deleted_lists' => $deletedNames
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Listen: ' . $e->getMessage()];
        }
    }

    public static function executeReadFile(array $args)
    {
        if ($denied = self::assertTaskStaffAccess()) return $denied;

        try {
            if (empty($args['file_path'])) {
                return ['status' => 'success', 'message' => 'Kein Dateipfad angegeben.'];
            }

            $filePath = trim((string) $args['file_path']);

            // Direkte Abwehr von Path-Traversal-Versuchen
            if (
                str_contains($filePath, '..')
                || str_contains($filePath, '\\')
                || str_contains($filePath, "\0")
            ) {
                return ['status' => 'success', 'message' => 'Zugriff verweigert: Ungültiger Pfad.'];
            }

            // Falls Funkira nur einen reinen Dateinamen (ohne Ordnerpfad) übergibt, aus Ticket-Anhängen auflösen
            if (!str_starts_with($filePath, 'leitung/tasks/attachments/') && !str_contains($filePath, '/')) {
                $needle = mb_strtolower($filePath);
                $candidateTasks = collect();
                if (!empty($args['task_id'])) {
                    $resolvedTask = self::resolveTaskRecord((string) $args['task_id'], true);
                    if ($resolvedTask) {
                        $candidateTasks->push($resolvedTask->loadMissing('messages'));
                    }
                }
                if ($candidateTasks->isEmpty()) {
                    $candidateTasks = ManagementTask::where('is_archived', false)
                        ->whereNull('parent_id')
                        ->with('messages')
                        ->orderByDesc('updated_at')
                        ->limit(50)
                        ->get();
                }

                foreach ($candidateTasks as $candTask) {
                    foreach (self::collectTaskAttachments($candTask) as $att) {
                        $attName = mb_strtolower((string) ($att['filename'] ?? ''));
                        $attBase = mb_strtolower(basename((string) ($att['path'] ?? '')));
                        if ($attName === $needle || $attBase === $needle || str_contains($attName, $needle)) {
                            $filePath = (string) $att['path'];
                            break 2;
                        }
                    }
                }
            }

            if (
                str_contains($filePath, '..')
                || str_contains($filePath, '\\')
                || str_contains($filePath, "\0")
                || !str_starts_with($filePath, 'leitung/tasks/attachments/')
            ) {
                return ['status' => 'success', 'message' => 'Zugriff verweigert: Ungültiger Pfad.'];
            }

            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                return ['status' => 'success', 'message' => 'Datei nicht gefunden.'];
            }

            $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
            if (is_link($absolutePath)) {
                return ['status' => 'success', 'message' => 'Zugriff verweigert: Symlinks sind nicht erlaubt.'];
            }
            $realPath = realpath($absolutePath);
            $allowedBase = realpath(\Illuminate\Support\Facades\Storage::disk('local')->path('leitung/tasks/attachments'));
            if ($realPath === false || ($allowedBase !== false && !str_starts_with($realPath, $allowedBase . DIRECTORY_SEPARATOR))) {
                return ['status' => 'success', 'message' => 'Zugriff verweigert: Ungültiger Pfad.'];
            }
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            $content = '';
            
            if ($extension === 'pdf') {
                if (class_exists(\App\Services\AI\AIFunctionsRegistry::class) && method_exists(\App\Services\AI\AIFunctionsRegistry::class, 'extractPdfContent')) {
                    $content = \App\Services\AI\AIFunctionsRegistry::extractPdfContent($absolutePath);
                } else {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($absolutePath);
                    $content = $pdf->getText();
                }
            } elseif (in_array($extension, ['txt', 'csv', 'md', 'json', 'xml'])) {
                $content = file_get_contents($absolutePath);
            } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                $mime = match ($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                };
                $content = self::analyzeImageWithGemini($absolutePath, $mime);
            } else {
                return [
                    'status' => 'success', 
                    'message' => 'Dateiformat .' . $extension . ' kann nicht ausgelesen werden. Bitte nur PDF, TXT, CSV, JSON, XML, MD oder Bilder (PNG, JPG, WEBP, GIF) anfragen.'
                ];
            }

            // Text kürzen, falls extrem lang
            if (strlen($content) > 100000) {
                $content = substr($content, 0, 100000) . "... [Text wurde abgeschnitten, da zu lang]";
            }

            return [
                'status' => 'success',
                'filename' => basename($filePath),
                'content' => $content
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Lesen der Datei: ' . $e->getMessage()
            ];
        }
    }

    private static function analyzeImageWithGemini(string $absolutePath, string $mime): string
    {
        try {
            $base64 = base64_encode(file_get_contents($absolutePath));
            $modelName = 'gemini-3-flash-preview';
            
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Analysiere dieses Bild im Detail und beschreibe genau, was darauf zu sehen ist. Falls Text (OCR) enthalten ist, transkribiere ihn vollständig auf Deutsch.'
                            ],
                            [
                                'inlineData' => [
                                    'mimeType' => $mime,
                                    'data' => $base64
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                ]
            ];

            if (!\App\Services\AI\VertexAiService::hasCredentials()) {
                return 'Fehler: Kein Gemini API Key konfiguriert.';
            }

            $response = \App\Services\AI\VertexAiService::generateContent($modelName, $payload, 60);

            if ($response->failed()) {
                return 'API Fehler bei der Bildanalyse: ' . $response->body();
            }

            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Keine Beschreibung generiert.';

        } catch (\Exception $e) {
            return 'Fehler bei der Bildanalyse: ' . $e->getMessage();
        }
    }
}

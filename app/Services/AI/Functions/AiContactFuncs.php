<?php

namespace App\Services\AI\Functions;

use App\Models\Management\ManagementContact;
use Illuminate\Support\Str;

trait AiContactFuncs
{
    public static function getAiContactFuncsSchema(): array
    {
        return [
            [
                'name' => 'contact_get_all',
                'description' => 'Gibt eine Liste aller im System bekannten Kontakte und Personen zurück. Nutze dies, um dir einen Überblick zu verschaffen, wen du überhaupt kennst. Stichworte: Wer ist alles gespeichert, Zeig alle Kontakte, Wen kennst du.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeContactGetAll']
            ],
            [
                'name' => 'contact_search',
                'description' => 'Sucht spezifisch nach einer Person in deinen Kontakten und gibt deren detailliertes Profil (Beziehung, Geburtstag, erlernte Fakten und E-Mail/Telefon) zurück. Stichworte: Such Theresa, Zeig Max Profil, Wer ist Tom.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff, Name oder Vorname der gesuchten Person.'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeContactSearch']
            ],
            [
                'name' => 'contact_create',
                'description' => 'Lege einen komplett neuen Kontakt an, falls die Person noch nicht existiert. Stichworte: Speicher einen neuen Kontakt, Neuer Kunde Max, Lege Theresa an.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'Vorname der Person.'
                        ],
                        'last_name' => [
                            'type' => 'string',
                            'description' => 'Nachname der Person.'
                        ],
                        'relation_type' => [
                            'type' => 'string',
                            'description' => 'Art der Beziehung (z.B. "Familie", "Kunde", "Freund", "Dienstleister", "Unbekannt").',
                            'default' => 'Unbekannt'
                        ]
                    ],
                    'required' => ['first_name']
                ],
                'callable' => [self::class, 'executeContactCreate']
            ],
            [
                'name' => 'contact_add_info',
                'description' => 'Fügt eine neue Information, Vorliebe oder Notiz zum gespeicherten Profil eines existierenden Kontakts hinzu. Stichworte: Merk dir zu Theresa, Speichere bei Max, Tom mag Pizza.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'Vorname des Kontakts.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Die Information, die du dir zu dieser Person merken sollst.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Kurze Kategorie oder Thema der Info (z.B. "Lieblingsessen").'
                        ]
                    ],
                    'required' => ['first_name', 'content']
                ],
                'callable' => [self::class, 'executeContactAddInfo']
            ],
            [
                'name' => 'contact_update_info',
                'description' => 'Aktualisiert oder ändert eine fehlerhafte Info in einem Profil (z.B. ein falsches Alter, neuer Job). Stichworte: Korrigiere ihr Profil, Ändere das bei Tom, Update diese Info.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'Vorname der Person.'
                        ],
                        'old_content_substring' => [
                            'type' => 'string',
                            'description' => 'EXAKT der Text, der im Profil gefunden wurde und GEÄNDERT werden soll.'
                        ],
                        'new_content' => [
                            'type' => 'string',
                            'description' => 'Der neue Text, der den alten Text überschreiben soll.'
                        ]
                    ],
                    'required' => ['first_name', 'old_content_substring', 'new_content']
                ],
                'callable' => [self::class, 'executeContactUpdateInfo']
            ],
            [
                'name' => 'contact_delete_info',
                'description' => 'Löscht eine bestimmte Information restlos aus dem Profil der Person. Stichworte: Lösche das bei Theresa, Entferne die Info, Vergiss das.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'Vorname der Person.'
                        ],
                        'content_substring_to_delete' => [
                            'type' => 'string',
                            'description' => 'Genau der Teil des Profil-Textes, der gelöscht werden soll.'
                        ]
                    ],
                    'required' => ['first_name', 'content_substring_to_delete']
                ],
                'callable' => [self::class, 'executeContactDeleteInfo']
            ],
            [
                'name' => 'contact_draft_call',
                'description' => 'WICHTIG: Nutze dies IMMER zuerst, bevor du einen Anruf tätigst! Erstellt einen Entwurf/Aufgabenplan für einen Anruf, der dem Nutzer in der UI angezeigt wird. Besprich den Plan mit dem Nutzer und hole dir sein finales "Go", bevor du contact_call nutzt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'Vorname der anzurufenden Person.'
                        ],
                        'objective' => [
                            'type' => 'string',
                            'description' => 'Der konkrete Aufgabenplan / Die Checkliste für den Anruf.'
                        ]
                    ],
                    'required' => ['first_name', 'objective']
                ],
                'callable' => [self::class, 'executeContactDraftCall']
            ],
            [
                'name' => 'contact_call',
                'description' => 'ACHTUNG: Führe diesen Befehl erst aus, nachdem du mit contact_draft_call einen Plan erstellt hast UND der Nutzer ihn freigegeben hat! Löst den eigentlichen Anruf aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'Vorname der anzurufenden Person.'
                        ],
                        'objective' => [
                            'type' => 'string',
                            'description' => 'Genaue Anweisung, was die KI am Telefon sagen soll oder in Erfahrung bringen soll (z.B. "Sag ihm dass Alina die geilste ist und frag wie es ihm geht").'
                        ]
                    ],
                    'required' => ['first_name', 'objective']
                ],
                'callable' => [self::class, 'executeContactCall']
            ]
        ];
    }

    public static function executeContactGetAll(array $args)
    {
        $profiles = ManagementContact::all();
        if ($profiles->isEmpty()) {
            return ['status' => 'success', 'message' => 'Es sind aktuell gar keine Personenprofile gespeichert.'];
        }

        $list = $profiles->map(function ($p) {
            return "- {$p->full_name} (" . ($p->relation_type ?? 'Unbekannt') . ")";
        })->implode("\n");

        return [
            'status' => 'success',
            'message' => "Folgende Personen kenne ich:\n" . $list
        ];
    }

    public static function executeContactSearch(array $args)
    {
        if (empty($args['query'])) {
            return ['status' => 'error', 'message' => 'Es wurde kein Suchbegriff angegeben.'];
        }

        $queryLower = strtolower(trim($args['query']));
        $allProfiles = ManagementContact::all();
        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($allProfiles as $p) {
            $dbFullName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name . ' ' . $p->last_name));
            $dbFirstName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name));
            $dbNickname = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->nickname ?? ''));

            if (str_contains($queryLower, $dbFirstName) || str_contains($queryLower, $dbFullName) ||
                ($dbNickname && str_contains($queryLower, $dbNickname))) {
                $bestMatch = $p;
                $highestSimilarity = 100;
                break;
            }

            $simFirst = 0; similar_text($dbFirstName, $queryLower, $simFirst);
            if ($simFirst > $highestSimilarity) {
                $highestSimilarity = $simFirst;
                $bestMatch = $p;
            }
        }

        if ($bestMatch && $highestSimilarity > 60) {
            $contextParts = [
                "[PERSONEN PROFIL GEFUNDEN]",
                "Name: {$bestMatch->full_name} " . ($bestMatch->nickname ? "(\"{$bestMatch->nickname}\")" : ''),
                "Beziehung: " . ($bestMatch->relation_type ?? 'Unbekannt'),
                "Geburtstag: " . ($bestMatch->birthday ? $bestMatch->birthday->format('Y-m-d') : 'Unbekannt'),
                "E-Mail: " . ($bestMatch->email ?? 'Keine'),
                "Telefon: " . ($bestMatch->phone ?? 'Keine'),
            ];
            if ($bestMatch->system_instructions) {
                $contextParts[] = "\n--- SYSTEM INSTRUKTIONEN ---\n" . $bestMatch->system_instructions;
            }
            if ($bestMatch->ai_learned_facts) {
                $contextParts[] = "\n--- GELERNTES WISSEN ---\n" . $bestMatch->ai_learned_facts;
            }

            return [
                'status' => 'success',
                'message' => "Profil gefunden.",
                'profile_data' => implode("\n", $contextParts)
            ];
        }

        return ['status' => 'error', 'message' => "Ich konnte keine Person mit diesem Namen in deinen Kontakten finden."];
    }

    public static function executeContactCreate(array $args)
    {
        if (empty($args['first_name'])) {
            return ['status' => 'error', 'message' => 'Vorname ist erforderlich.'];
        }

        $p = ManagementContact::create([
            'first_name' => $args['first_name'],
            'last_name' => $args['last_name'] ?? null,
            'relation_type' => $args['relation_type'] ?? 'Unbekannt',
            'is_active' => true
        ]);

        return [
            'status' => 'success',
            'message' => "Person {$p->first_name} wurde erfolgreich als Kontakt angelegt."
        ];
    }

    public static function executeContactAddInfo(array $args)
    {
        if (empty($args['first_name']) || empty($args['content'])) {
            return ['status' => 'error', 'message' => 'Vorname und Inhalt sind erforderlich.'];
        }

        $p = static::findPersonProfile($args['first_name']);
        if (!$p) return ['status' => 'error', 'message' => 'Person zum Hinzufügen von Wissen nicht gefunden.'];

        $dateStr = now()->format('d.m.Y');
        $titleStr = !empty($args['title']) ? " (Notiz: {$args['title']})" : '';
        $newEntry = "\n[{$dateStr}] {$args['content']}{$titleStr}";

        $p->ai_learned_facts = ($p->ai_learned_facts ?? '') . $newEntry;
        $p->save();

        return ['status' => 'success', 'message' => "Die Information wurde erfolgreich bei {$p->first_name} gespeichert."];
    }

    public static function executeContactUpdateInfo(array $args)
    {
        if (empty($args['first_name']) || empty($args['old_content_substring']) || empty($args['new_content'])) {
            return ['status' => 'error', 'message' => 'Parameter unvollständig.'];
        }

        $p = static::findPersonProfile($args['first_name']);
        if (!$p) return ['status' => 'error', 'message' => 'Person für das Update nicht gefunden.'];

        $oldFacts = $p->ai_learned_facts ?? '';
        $updatedFacts = str_ireplace($args['old_content_substring'], $args['new_content'], $oldFacts);

        if ($oldFacts !== $updatedFacts) {
            $p->ai_learned_facts = $updatedFacts;
            $p->save();
            return ['status' => 'success', 'message' => "Die Info bei {$p->first_name} wurde aktualisiert."];
        }

        return ['status' => 'error', 'message' => "Der alte Text wurde im Profil nicht gefunden."];
    }

    public static function executeContactDeleteInfo(array $args)
    {
        if (empty($args['first_name']) || empty($args['content_substring_to_delete'])) {
            return ['status' => 'error', 'message' => 'Parameter unvollständig.'];
        }

        $p = static::findPersonProfile($args['first_name']);
        if (!$p) return ['status' => 'error', 'message' => 'Person nicht gefunden.'];

        $currentFacts = $p->ai_learned_facts ?? '';
        $updatedFacts = str_ireplace($args['content_substring_to_delete'], '', $currentFacts);
        $updatedFacts = preg_replace('/\[\d{2}\.\d{2}\.\d{4}\]\s*(?:Notiz:.*?\))?\s*(?=\n|$)/im', '', $updatedFacts);
        $updatedFacts = preg_replace('/^\s*[\r\n]/m', '', $updatedFacts);

        $p->ai_learned_facts = trim($updatedFacts);
        $p->save();

        return ['status' => 'success', 'message' => "Die Information bei {$p->first_name} wurde gelöscht."];
    }

    public static function executeContactDraftCall(array $args)
    {
        if (empty($args['first_name']) || empty($args['objective'])) {
            return ['status' => 'error', 'message' => 'Vorname und Ziel (objective) für den Anruf erforderlich.'];
        }

        $p = static::findPersonProfile($args['first_name']);
        if (!$p) return ['status' => 'error', 'message' => 'Person nicht gefunden.'];

        if (empty($p->phone)) {
            return ['status' => 'error', 'message' => "Für {$p->first_name} ist keine Telefonnummer im Profil hinterlegt."];
        }

        // Erstelle den geplanten Anruf
        $record = new \App\Models\SupportTelephonyCall();
        $record->contact_name = $p->full_name;
        $record->phone = $p->phone;
        $record->objective = $args['objective'];
        $record->status = 'planned';
        $record->save();

        return [
            'status' => 'success',
            'message' => "Der Entwurf wurde erfolgreich in der UI unter 'Anruf-Historie' (als Geplant) gespeichert. Bitte den Nutzer jetzt um sein finales 'Go' oder eventuelle Anpassungen, bevor du contact_call benutzt."
        ];
    }

    public static function executeContactCall(array $args)
    {
        if (empty($args['first_name']) || empty($args['objective'])) {
            return ['status' => 'error', 'message' => 'Vorname und Ziel (objective) für den Anruf erforderlich.'];
        }

        $p = static::findPersonProfile($args['first_name']);
        if (!$p) return ['status' => 'error', 'message' => 'Person nicht gefunden.'];

        if (empty($p->phone)) {
            return ['status' => 'error', 'message' => "Für {$p->first_name} ist keine Telefonnummer im Profil hinterlegt."];
        }

        // Suche nach einem geplanten Anruf für diese Nummer und aktualisiere ihn (oder erstelle später einen neuen, falls nicht vorhanden)
        $plannedCall = \App\Models\SupportTelephonyCall::where('phone', preg_replace('/[^0-9+]/', '', $p->phone))
            ->where('status', 'planned')
            ->orderBy('created_at', 'desc')
            ->first();

        // Hole die Kalender-Termine der nächsten 30 Tage für den Kontext
        $calendarEventsStr = "Keine anstehenden Termine in den nächsten 30 Tagen.";
        if (class_exists(\App\Models\Management\ManagementCalendarEvent::class)) {
            $upcomingEvents = \App\Models\Management\ManagementCalendarEvent::where('start_date', '>=', now())
                ->where('start_date', '<=', now()->addDays(30))
                ->orderBy('start_date', 'asc')
                ->get();
            if ($upcomingEvents->isNotEmpty()) {
                $calendarEventsStr = "Termine der nächsten 30 Tage:\n";
                foreach ($upcomingEvents as $evt) {
                    $dateStr = $evt->is_all_day ? $evt->start_date->format('d.m.Y') . ' (Ganztägig)' : $evt->start_date->format('d.m.Y H:i') . ' - ' . ($evt->end_date ? $evt->end_date->format('H:i') : '');
                    $calendarEventsStr .= "- {$dateStr}: {$evt->title}\n";
                }
            }
        }

        // Kontext im Cache speichern, damit der Twilio Webhook (Outbound) darauf zugreifen kann
        // Twilio sendet die Nummer im E.164 Format zurück.
        $cacheKey = "twilio_call_" . preg_replace('/[^0-9+]/', '', $p->phone);
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'contact_name' => $p->full_name,
            'objective' => $args['objective'],
            'system_instructions' => $p->system_instructions ?? '',
            'ai_learned_facts' => $p->ai_learned_facts ?? '',
            'calendar_events' => $calendarEventsStr,
            'planned_call_id' => $plannedCall ? $plannedCall->id : null
        ], 600); // 10 Minuten gültig

        // WICHTIG: API Call direkt hier auslösen
        $callResult = static::triggerTwilioCall($p->phone);

        if (!$callResult['success']) {
            return [
                'status' => 'error',
                'message' => "Der Anruf konnte nicht aufgebaut werden! Twilio API Fehler: " . $callResult['error']
            ];
        }

        return [
            'status' => 'success',
            'message' => "Anruf wurde erfolgreich initiiert. (Twilio SID: " . $callResult['sid'] . ")",
            '_frontend_event' => [
                'name' => 'open-call-modal',
                'detail' => [
                    'name' => $p->full_name,
                    'phone' => $p->phone
                ]
            ],
        ];
    }

    /**
     * Helper Methode, um den echten Twilio Call zu feuern.
     * Kann vom Agent-Runner asynchron aufgerufen werden, sobald er das _backend_action sieht.
     * Oder wir können ihn direkt hier abfeuern.
     */
    public static function triggerTwilioCall(string $toPhone)
    {
        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $fromNumber = env('TWILIO_PHONE_NUMBER');

        if (!$sid || !$token || !$fromNumber) {
            \Log::error("Twilio Credentials fehlen in .env");
            return ['success' => false, 'error' => 'Twilio Credentials fehlen in der .env Datei.'];
        }

        try {
            $toPhoneClean = preg_replace('/[^0-9+]/', '', $toPhone);
            
            $cacheKey = "twilio_call_" . $toPhoneClean;
            $context = \Illuminate\Support\Facades\Cache::get($cacheKey);

            $host = request()->getHost();
            // Erlaube Fallback auf öffentlichen Ngrok für lokale Entwicklung
            $wssUrl = env('TWILIO_WSS_URL', 'wss://' . $host . '/twilio-stream');
            if (str_contains($host, '.test') || str_contains($host, 'localhost')) {
                \Log::warning("Lokale Umgebung erkannt! Twilio benötigt eine öffentliche WSS URL. Bitte TWILIO_WSS_URL in der .env setzen (z.B. ngrok).");
            }

            // Direktes TwiML bauen, um HTTP Webhook Request durch Twilio zu umgehen
            $response = new \Twilio\TwiML\VoiceResponse();
            $connect = $response->connect();
            $stream = $connect->stream([
                'url' => $wssUrl,
                'track' => 'both_tracks'
            ]);

            if ($context) {
                $stream->parameter(['name' => 'contact_name', 'value' => $context['contact_name'] ?? 'Unbekannt']);
                $stream->parameter(['name' => 'objective', 'value' => $context['objective'] ?? '']);
                $stream->parameter(['name' => 'system_instructions', 'value' => $context['system_instructions'] ?? '']);
                $stream->parameter(['name' => 'ai_learned_facts', 'value' => $context['ai_learned_facts'] ?? '']);
                $stream->parameter(['name' => 'calendar_events', 'value' => $context['calendar_events'] ?? '']);
            }

            $client = new \Twilio\Rest\Client($sid, $token);
            $call = $client->calls->create(
                $toPhoneClean,
                $fromNumber,
                [
                    "twiml" => $response->asXML(),
                    "timeLimit" => 180 // Maximal 3 Minuten Gesprächsdauer
                ]
            );

            // NEU: Trage den Call als aktiven Aufgabenplan ein oder update den Entwurf
            $plannedCallId = $context['planned_call_id'] ?? null;
            if ($plannedCallId) {
                $record = \App\Models\SupportTelephonyCall::find($plannedCallId);
            }
            
            if (!isset($record) || !$record) {
                $record = new \App\Models\SupportTelephonyCall();
            }

            $record->twilio_sid = $call->sid;
            $record->contact_name = $context['contact_name'] ?? 'Unbekannt';
            $record->phone = $toPhoneClean;
            $record->objective = $context['objective'] ?? '';
            $record->status = 'ongoing';
            $record->save();

            return ['success' => true, 'sid' => $call->sid];
        } catch (\Exception $e) {
            \Log::error("Twilio Call Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private static function findPersonProfile($queryLower)
    {
        $queryLower = strtolower(trim($queryLower));
        $allProfiles = ManagementContact::all();
        $bestMatch = null;
        $highestSimilarity = 0;

        foreach ($allProfiles as $p) {
            $dbFullName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name . ' ' . $p->last_name));
            $dbFirstName = strtolower(str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $p->first_name));

            if (str_contains($queryLower, $dbFirstName) || str_contains($queryLower, $dbFullName)) {
                $bestMatch = $p;
                $highestSimilarity = 100;
                break;
            }

            $simFirst = 0; similar_text($dbFirstName, $queryLower, $simFirst);
            if ($simFirst > $highestSimilarity) {
                $highestSimilarity = $simFirst;
                $bestMatch = $p;
            }
        }

        if ($bestMatch && $highestSimilarity > 60) return $bestMatch;
        return null;
    }
}

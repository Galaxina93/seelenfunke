<?php

namespace App\Services\AI\Functions;

trait AiTelefonyFuncs
{
    public static function getAiTelefonyFuncsSchema(): array
    {
        return [
            [
                'name' => 'call_contact_draft',
                'description' => 'ABSOLUTE PFLICHT: Erstelle Anrufpläne/Aufgabenpläne für Telefonate AUSSCHLIESSLICH mit diesem Tool! Schreibe Anrufpläne NIEMALS in die Knowledge Base, in Dokumente oder Notizen. Dieser Befehl legt den Plan direkt im Bereich "Support Telefonie" an. Besprich den Plan danach mit dem Nutzer, passe ihn bei Bedarf an (indem du dieses Tool erneut aufrufst), und hole dir sein finales "Go", bevor du call_contact ausführst.',
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
                'callable' => [self::class, 'executeTelephonyCallContactDraft']
            ],
            [
                'name' => 'call_contact',
                'description' => 'ACHTUNG: Führe diesen Befehl erst aus, nachdem du mit call_contact_draft einen Plan erstellt hast UND der Nutzer ihn freigegeben hat! Löst den eigentlichen Anruf aus.',
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
                'callable' => [self::class, 'executeTelephonyCallContact']
            ],
            [
                'name' => 'call_get_history',
                'description' => 'Liest die Historie der letzten Telefonanrufe aus. Liefert dir Dauer, Status, das generierte Fazit und das komplette Transkript. Nutze dies, um das Ergebnis eines Anrufs zu analysieren oder zu prüfen, ob er abgeschlossen ist.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => [
                            'type' => 'string',
                            'description' => 'Optional: Vorname der Person, um nur deren Anrufe zu sehen.'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Anzahl der Anrufe (Standard: 3).'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeTelephonyCallGetHistory']
            ],
            [
                'name' => 'call_update_status',
                'description' => 'Ändert den Status eines Anrufs (z.B. wenn er in der UI fälschlicherweise auf "ongoing" hängt) auf "completed" oder "failed".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'call_id' => [
                            'type' => 'integer',
                            'description' => 'Die Datenbank-ID des Anrufs (aus call_get_history).'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Der neue Status (completed, failed, ongoing, planned).'
                        ]
                    ],
                    'required' => ['call_id', 'status']
                ],
                'callable' => [self::class, 'executeTelephonyCallUpdateStatus']
            ],
            [
                'name' => 'call_analyze_errors',
                'description' => 'Analysiert einen fehlgeschlagenen Anruf direkt über die Twilio API und liefert echte technische Fehlercodes (z.B. HTTP 404, WebSocket Connection Failed 11200), die erklären, warum die englische Ansage "An application error has occurred" kam.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'call_id' => [
                            'type' => 'integer',
                            'description' => 'Die lokale Datenbank-ID des Anrufs aus call_get_history.'
                        ]
                    ],
                    'required' => ['call_id']
                ],
                'callable' => [self::class, 'executeTelephonyCallAnalyzeErrors']
            ]
        ];
    }

    public static function executeTelephonyCallContactDraft(array $args)
    {
        if (empty($args['first_name']) || empty($args['objective'])) {
            return ['status' => 'error', 'message' => 'Vorname und Ziel (objective) für den Anruf erforderlich.'];
        }

        $p = self::findPersonProfile($args['first_name']);
        if (!$p) return ['status' => 'error', 'message' => 'Person nicht gefunden.'];

        if (empty($p->phone)) {
            return ['status' => 'error', 'message' => "Für {$p->first_name} ist keine Telefonnummer im Profil hinterlegt."];
        }

        // Suche nach bestehendem Entwurf für diese Person
        $phoneClean = preg_replace('/[^0-9+]/', '', $p->phone);
        $record = \App\Models\SupportTelephonyCall::where('phone', $phoneClean)
            ->where('status', 'planned')
            ->first();

        if (!$record) {
            $record = new \App\Models\SupportTelephonyCall();
            $record->contact_name = $p->full_name;
            $record->phone = $phoneClean; // Speichere die bereinigte Nummer für konsistente Abfragen
            $record->status = 'planned';
        }
        
        $record->objective = $args['objective'];
        $record->save();
        \App\Events\SupportTelephonyUpdated::dispatch();

        return [
            'status' => 'success',
            'message' => "Der Anrufplan wurde erfolgreich im Bereich 'Support Telefonie' (als Geplant) angelegt/aktualisiert. Er ist für den Nutzer dort jetzt sichtbar. Bitte ihn nun um Feedback oder Freigabe.",
            '_frontend_event' => [
                'name' => 'refreshTelephony'
            ]
        ];
    }

    public static function executeTelephonyCallContact(array $args)
    {
        if (empty($args['first_name']) || empty($args['objective'])) {
            return ['status' => 'error', 'message' => 'Vorname und Ziel (objective) für den Anruf erforderlich.'];
        }

        $p = self::findPersonProfile($args['first_name']);
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

        // Lade den aktiven Agenten
        $agent = \App\Models\Ai\AiAgent::where('is_active', true)->first();

        // Kontext im Cache speichern, damit der Twilio Webhook (Outbound) darauf zugreifen kann
        // Twilio sendet die Nummer im E.164 Format zurück.
        $cacheKey = "twilio_call_" . preg_replace('/[^0-9+]/', '', $p->phone);
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'contact_name' => $p->full_name,
            'objective' => $args['objective'],
            'system_instructions' => $p->system_instructions ?? '',
            'ai_learned_facts' => $p->ai_learned_facts ?? '',
            'calendar_events' => $calendarEventsStr,
            'planned_call_id' => $plannedCall ? $plannedCall->id : null,
            'agent_name' => $agent ? $agent->name : 'Alina Steinhauer',
            'agent_profile' => $agent ? $agent->system_prompt : 'Du bist eine professionelle und freundliche KI Assistentin.',
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

    public static function executeTelephonyCallGetHistory(array $args)
    {
        $limit = $args['limit'] ?? 3;
        $query = \App\Models\SupportTelephonyCall::orderBy('created_at', 'desc');

        if (!empty($args['first_name'])) {
            $p = self::findPersonProfile($args['first_name']);
            if ($p) {
                $query->where('phone', preg_replace('/[^0-9+]/', '', $p->phone))
                      ->orWhere('contact_name', 'like', '%' . $p->first_name . '%');
            } else {
                $query->where('contact_name', 'like', '%' . $args['first_name'] . '%');
            }
        }

        $calls = $query->limit($limit)->get();

        if ($calls->isEmpty()) {
            return ['status' => 'success', 'message' => 'Keine Anrufe in der Historie gefunden.'];
        }

        $result = [];
        foreach ($calls as $c) {
            $result[] = [
                'id' => $c->id,
                'contact' => $c->contact_name,
                'status' => $c->status,
                'duration_seconds' => $c->duration_seconds,
                'objective' => $c->objective,
                'summary_fazit' => $c->summary,
                'next_steps' => json_decode($c->next_steps, true),
                'transcript_preview' => substr(json_encode(json_decode($c->transcript, true)), 0, 500) . '...',
                'created_at' => $c->created_at->format('Y-m-d H:i:s')
            ];
        }

        return [
            'status' => 'success',
            'data' => $result
        ];
    }

    public static function executeTelephonyCallUpdateStatus(array $args)
    {
        if (empty($args['call_id']) || empty($args['status'])) {
            return ['status' => 'error', 'message' => 'call_id und status sind erforderlich.'];
        }

        $call = \App\Models\SupportTelephonyCall::find($args['call_id']);
        if (!$call) {
            return ['status' => 'error', 'message' => 'Anruf mit dieser ID nicht gefunden.'];
        }

        $call->status = $args['status'];
        $call->save();
        \App\Events\SupportTelephonyUpdated::dispatch();

        return [
            'status' => 'success',
            'message' => "Der Status von Anruf ID {$call->id} wurde erfolgreich auf '{$args['status']}' geändert.",
            '_frontend_event' => [
                'name' => 'refreshTelephony'
            ]
        ];
    }

    public static function executeTelephonyCallAnalyzeErrors(array $args)
    {
        if (empty($args['call_id'])) {
            return ['status' => 'error', 'message' => 'call_id erforderlich.'];
        }

        $call = \App\Models\SupportTelephonyCall::find($args['call_id']);
        if (!$call || empty($call->twilio_sid)) {
            return ['status' => 'error', 'message' => 'Anruf oder Twilio SID nicht gefunden.'];
        }

        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');

        if (!$sid || !$token) {
            return ['status' => 'error', 'message' => 'Twilio Credentials fehlen in .env'];
        }

        try {
            $client = new \Twilio\Rest\Client($sid, $token);
            $twilioCall = $client->calls($call->twilio_sid)->fetch();
            $notifications = $client->calls($call->twilio_sid)->notifications->read([], 5);

            $errorMessages = [];
            foreach ($notifications as $notification) {
                $errorMessages[] = "[Code {$notification->errorCode}] {$notification->messageText} - Log: {$notification->log}";
            }

            if (empty($errorMessages)) {
                return [
                    'status' => 'success',
                    'message' => "Der Anruf hat laut Twilio-API den Status '{$twilioCall->status}'. Es wurden keine expliziten Twilio-Fehlerbenachrichtigungen (Notifications) gefunden. Wenn die KI-Ansage auf Englisch kam, konnte Twilio vermutlich die WSS WebSocket URL nicht erreichen oder sie hat ein 404/500 geworfen."
                ];
            }

            // Setze den Call automatisch auf failed, wenn wir hier Fehler finden
            if ($call->status !== 'failed') {
                $call->status = 'failed';
                $call->summary = "Technischer Fehler: " . implode(" | ", $errorMessages);
                $call->save();
                \App\Events\SupportTelephonyUpdated::dispatch();
            }

            return [
                'status' => 'success',
                'message' => "Folgende technische Twilio-Fehler sind während des Anrufs aufgetreten:\n" . implode("\n", $errorMessages)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Twilio API Fehler beim Abrufen der Logs: ' . $e->getMessage()];
        }
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
            return ['success' => false, 'error' => 'Twilio Credentials fehlen (SID, TOKEN oder PHONE_NUMBER).'];
        }

        $toPhoneClean = preg_replace('/[^0-9+]/', '', $toPhone);
        if (!str_starts_with($toPhoneClean, '+')) {
            $toPhoneClean = '+' . $toPhoneClean; // Fallback, falls + fehlt
        }

        try {
            $cacheKey = "twilio_call_" . $toPhoneClean;
            $context = \Illuminate\Support\Facades\Cache::get($cacheKey);

            $host = request()->getHost();
            // Erlaube Fallback auf öffentlichen Ngrok für lokale Entwicklung
            $wssUrl = env('TWILIO_WSS_URL', 'wss://' . $host . ':8081/twilio-stream');
            if (str_contains($host, '.test') || str_contains($host, 'localhost')) {
                \Log::warning("Lokale Umgebung erkannt! Twilio benötigt eine öffentliche WSS URL. Bitte TWILIO_WSS_URL in der .env setzen (z.B. ngrok).");
            }

            $response = new \Twilio\TwiML\VoiceResponse();
            $connect = $response->connect();
            $stream = $connect->stream([
                'url' => $wssUrl
            ]);

            // Start-Parameter (Context) übergeben
            if ($context) {
                $stream->parameter(['name' => 'contact_name', 'value' => $context['contact_name'] ?? 'Unbekannt']);
                $stream->parameter(['name' => 'objective', 'value' => $context['objective'] ?? '']);
                $stream->parameter(['name' => 'system_instructions', 'value' => $context['system_instructions'] ?? '']);
                $stream->parameter(['name' => 'ai_learned_facts', 'value' => $context['ai_learned_facts'] ?? '']);
                $stream->parameter(['name' => 'calendar_events', 'value' => $context['calendar_events'] ?? '']);
            } else {
                $stream->parameter(['name' => 'objective', 'value' => 'ki_agent_outbound']);
            }

            $client = new \Twilio\Rest\Client($sid, $token);
            $call = $client->calls->create(
                $toPhoneClean,
                $fromNumber,
                [
                    "twiml" => $response->asXML(),
                    "timeLimit" => 180, // Maximal 3 Minuten Gesprächsdauer
                    "statusCallback" => "https://" . $host . "/api/twilio/call-log",
                    "statusCallbackEvent" => ["initiated", "ringing", "answered", "completed"],
                    "statusCallbackMethod" => "POST"
                ]
            );

            // Den Call als aktiven Aufgabenplan eintragen oder den Entwurf updaten
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
            \App\Events\SupportTelephonyUpdated::dispatch();

            return [
                'success' => true,
                'sid' => $call->sid
            ];
        } catch (\Exception $e) {
            \Log::error("Twilio Call Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTelephonyCall;
use App\Services\System\FunkiraPhoneConciergeService;
use App\Services\System\SystemErrorLogger;
use App\Services\System\SystemSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Twilio\TwiML\VoiceResponse;

class TwilioCallController extends Controller
{
    private const CACHE_INBOUND_META_PREFIX = 'twilio_inbound_call_meta_';
    private const CACHE_SID_TO_CALL_PREFIX = 'twilio_sid_to_call_';
    private const CACHE_GREETING_WAV_PREFIX = 'twilio_greeting_wav_';

    private function isAuthorizedTwilioWebhook(Request $request): bool
    {
        $configuredAccountSid = config('services.twilio.sid');
        if (!empty($configuredAccountSid)) {
            return $request->filled('AccountSid')
                && is_string($request->input('AccountSid'))
                && hash_equals((string) $configuredAccountSid, (string) $request->input('AccountSid'));
        }

        return SystemSecurityService::isTrustedBridgeRequest($request);
    }

    /**
     * Wird von Twilio aufgerufen, sobald ein von uns initiierter Anruf (Outbound) vom Empfänger abgenommen wird.
     */
    public function outbound(Request $request)
    {
        if (!$this->isAuthorizedTwilioWebhook($request)) {
            return response('Forbidden', 403);
        }

        $response = new VoiceResponse();
        // Versuch, den im Vorfeld gespeicherten Kontext aus dem Cache zu holen (nur wenn eine gültige, in der DB registrierte CallSid vorliegt)
        $toPhone = preg_replace('/[^0-9+]/', '', (string) $request->input('To', ''));
        $callSid = is_string($request->input('CallSid')) ? trim($request->input('CallSid')) : '';
        $hasVerifiedCall = $callSid !== ''
            && preg_match('/^CA[a-f0-9]{16,64}$/i', $callSid)
            && SupportTelephonyCall::where('twilio_sid', $callSid)->exists();
        $context = ($toPhone !== '' && $hasVerifiedCall)
            ? Cache::pull('twilio_call_' . $toPhone)
            : null;

        // Bidirektionaler <Connect><Stream>: kein 'track' => 'both_tracks' übergeben (verhindert Twilio Error 31941)!
        $connect = $response->connect();
        $trustedHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $url = config('services.twilio.wss_url') ?: ('wss://' . $trustedHost . ':8081/twilio-stream');
        $stream = $connect->stream([
            'url' => $url,
        ]);

        if ($context) {
            $stream->parameter(['name' => 'contact_name', 'value' => $context['contact_name'] ?? 'Unbekannt']);
            $stream->parameter(['name' => 'objective', 'value' => $context['objective'] ?? 'Führe ein nettes Gespräch.']);
            $stream->parameter(['name' => 'system_instructions', 'value' => $context['system_instructions'] ?? '']);
            $stream->parameter(['name' => 'ai_learned_facts', 'value' => $context['ai_learned_facts'] ?? '']);
            $stream->parameter(['name' => 'calendar_events', 'value' => $context['calendar_events'] ?? 'Keine Termine']);
            $stream->parameter(['name' => 'agent_name', 'value' => $context['agent_name'] ?? shop_setting('owner_proprietor', 'Alina Steinhauer')]);
            $stream->parameter(['name' => 'agent_profile', 'value' => $context['agent_profile'] ?? 'Du bist eine hilfreiche KI Assistentin.']);
            $stream->parameter(['name' => 'planned_call_id', 'value' => (string) ($context['planned_call_id'] ?? '')]);
        }

        return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Wird von Twilio aufgerufen, wenn ein Anruf auf die Gifhorner Funkira-Nummer (+49 5371 7269023)
     * weitergeleitet wird oder direkt eingeht (Whitelist Telefon-Assistent).
     */
    public function inbound(Request $request)
    {
        if (!$this->isAuthorizedTwilioWebhook($request)) {
            return response('Forbidden', 403);
        }

        $response = new VoiceResponse();

        // 1. Ist der Telefon-Assistent deaktiviert? -> Sofort gebührenfrei abweisen (<Reject reason="busy"/> = 0,00 €)
        if (!FunkiraPhoneConciergeService::isEnabled()) {
            $response->reject(['reason' => 'busy']);
            return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
        }

        // 2. Strikter Whitelist-Türsteher: Unbekannte oder nicht freigegebene Anrufer werden NIEMALS angenommen!
        $from = trim((string) $request->input('From', ''));
        $caller = trim((string) $request->input('Caller', ''));
        $forwardedFrom = trim((string) $request->input('ForwardedFrom', ''));

        $matchedContact = FunkiraPhoneConciergeService::matchIncomingTwilioCaller($from, $caller, $forwardedFrom);
        if (!$matchedContact) {
            $response->reject(['reason' => 'busy']);
            return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
        }

        // 3. Freigegebener Whitelist-Kontakt erkannt (z. B. Philip Goik oder Papa) -> Live-Sekretärin Funkira übernimmt!
        $callSid = is_string($request->input('CallSid')) ? trim($request->input('CallSid')) : '';
        $greetingName = FunkiraPhoneConciergeService::resolveSpokenGreetingName($matchedContact);
        $fullName = trim($matchedContact->full_name) ?: $greetingName;
        $nick = trim((string) ($matchedContact->nickname ?? ''));
        $displayLabel = ($nick !== '' && $nick !== $fullName) ? "{$nick} ({$fullName})" : $fullName;
        $relation = trim((string) ($matchedContact->relation_type ?? ''));
        $contactInstructions = Str::limit(trim((string) ($matchedContact->system_instructions ?? '')), 800, '');
        $learnedFacts = Str::limit(trim((string) ($matchedContact->ai_learned_facts ?? '')), 800, '');

        $callRecord = SupportTelephonyCall::create([
            'twilio_sid' => $callSid,
            'contact_name' => $displayLabel,
            'phone' => $matchedContact->phone ?: $from,
            'objective' => "Funkira Telefon-Sekretärin: Eingehender Anruf von {$displayLabel}",
            'status' => 'ongoing',
        ]);

        $callNonce = bin2hex(random_bytes(32));
        $meta = [
            'nonce' => $callNonce,
            'call_record_id' => $callRecord->id,
            'call_sid' => $callSid,
            'contact_id' => $matchedContact->id,
            'caller_name' => $fullName,
            'greeting_name' => $greetingName,
            'display_label' => $displayLabel,
            'caller_phone' => $matchedContact->phone ?: $from,
        ];
        Cache::put(self::CACHE_INBOUND_META_PREFIX . $callRecord->id, $meta, now()->addMinutes(25));
        if ($callSid !== '') {
            Cache::put(self::CACHE_SID_TO_CALL_PREFIX . $callSid, $callRecord->id, now()->addMinutes(25));
        }

        // 4. Sofortige Übergabe (0,0s Verzögerung, KEIN statischer Standardtext vorweg!) in den bidirektionalen
        //    Gemini-3.1-Flash-Live-Audiostream: Funkira begrüßt den Anrufer live und klärt aktiv alle Rahmenbedingungen.
        $ownerName = function_exists('shop_setting')
            ? shop_setting('owner_proprietor', 'Alina Steinhauer')
            : 'Alina Steinhauer';

        $calendarSlots = FunkiraPhoneConciergeService::buildSafeCalendarSlotsForSecretary();

        $secretaryObjective = "LIVE TELEFON-SEKRETÄRIN FUNKIRA: Führe sofort ein herzliches, lebendiges Gespräch mit {$greetingName} ({$fullName}), "
            . "kläre aktiv das Anliegen, alle wichtigen Rahmenbedingungen (Datum, Uhrzeit, Ort, Fristen, Details) sowie was {$ownerName} konkret tun soll.";

        $combinedInstructions = trim(
            ($relation !== '' ? "Beziehung zu Alina: {$relation}. " : '')
            . ($nick !== '' ? "Spitzname bei Alina: {$nick}. " : '')
            . $contactInstructions
            . ' ZERO-PII-REGEL: Gib niemals interne Geschäftsdaten, Kunden, Bestellungen oder Finanzen preis.'
        );

        $wssUrl = config('services.twilio.wss_url') ?: 'wss://ws.mein-seelenfunke.de/twilio-stream';

        $connect = $response->connect();
        $stream = $connect->stream([
            'url' => $wssUrl,
        ]);
        $stream->parameter(['name' => 'call_mode', 'value' => 'inbound_secretary']);
        $stream->parameter(['name' => 'agent_name', 'value' => $ownerName]);
        $stream->parameter(['name' => 'contact_name', 'value' => "{$greetingName} ({$fullName})"]);
        $stream->parameter(['name' => 'greeting_name', 'value' => $greetingName]);
        $stream->parameter(['name' => 'relation_type', 'value' => $relation]);
        $stream->parameter(['name' => 'objective', 'value' => $secretaryObjective]);
        $stream->parameter(['name' => 'system_instructions', 'value' => $combinedInstructions]);
        $stream->parameter(['name' => 'ai_learned_facts', 'value' => $learnedFacts]);
        $stream->parameter(['name' => 'calendar_events', 'value' => $calendarSlots]);
        $stream->parameter(['name' => 'planned_call_id', 'value' => "{$callRecord->id}:{$callNonce}"]);

        return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Liefert die kurzlebige Gemini-Aoede-Begrüßungs-WAV an Twilio <Play> aus.
     */
    public function greetingAudio(string $token)
    {
        $wavBase64 = Cache::get(self::CACHE_GREETING_WAV_PREFIX . $token);
        if (!is_string($wavBase64) || $wavBase64 === '') {
            abort(404);
        }

        $binary = base64_decode($wavBase64, true);
        if ($binary === false || $binary === '') {
            abort(404);
        }

        return response($binary, 200, [
            'Content-Type' => 'audio/wav',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Twilio Status-Callback Sicherheitsnetz: Falls ein Whitelist-Anrufer schon während der Begrüßung auflegt
     * (bevor der WebSocket-Stream überhaupt lief), geht der Anruf trotzdem niemals verloren.
     */
    public function inboundStatus(Request $request)
    {
        if (!$this->isAuthorizedTwilioWebhook($request)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $callSid = is_string($request->input('CallSid')) ? trim($request->input('CallSid')) : '';
        $callStatus = strtolower(trim((string) $request->input('CallStatus', '')));
        $durationSec = (int) $request->input('CallDuration', 0);

        if ($callSid === '' || !in_array($callStatus, ['completed', 'no-answer', 'canceled', 'busy', 'failed'], true)) {
            return response()->json(['status' => 'ignored']);
        }

        $callRecordId = Cache::get(self::CACHE_SID_TO_CALL_PREFIX . $callSid);
        if (!$callRecordId) {
            return response()->json(['status' => 'no_whitelist_session']);
        }

        $meta = Cache::get(self::CACHE_INBOUND_META_PREFIX . $callRecordId);
        if (!is_array($meta)) {
            return response()->json(['status' => 'expired']);
        }

        // Nur wenn das Gespräch extrem kurz war (<= 4s, d. h. sofortiges Auflegen vor Gesprächsbeginn)
        // kurzen Fallback anlegen; echte Gespräche sendet die Live-Bridge direkt per /api/twilio/call-log
        // (und completeCall aktualisiert einen Fallback bei Bedarf nahtlos mit dem echten Gesprächsprotokoll!).
        if ($durationSec <= 4) {
            $callRecord = SupportTelephonyCall::find($callRecordId);
            if ($callRecord && $callRecord->status === 'ongoing') {
                $who = $meta['greeting_name'] ?? ($meta['caller_name'] ?? 'Kontakt');
                $stubSummary = "{$who} hat kurz angerufen und noch vor Gesprächsbeginn wieder aufgelegt.";
                $stubAction = "{$who} bei Gelegenheit kurz zurückrufen.";
                $callRecord->status = 'completed';
                $callRecord->duration_seconds = $durationSec;
                $callRecord->summary = $stubSummary;
                $callRecord->next_steps = json_encode([
                    'mode' => 'inbound_secretary',
                    'rahmenbedingungen' => [],
                    'action_required' => $stubAction,
                    'urgency' => 'mittel',
                    'callback_requested' => true,
                    'steps' => [$stubAction],
                ], JSON_UNESCAPED_UNICODE);
                $callRecord->save();

                FunkiraPhoneConciergeService::completeCall([
                    'call_id' => 'twilio_' . $callRecordId,
                    'is_simulation' => false,
                    'is_fallback_stub' => true,
                    'contact_id' => $meta['contact_id'] ?? null,
                    'caller_name' => $meta['caller_name'] ?? 'Kontakt',
                    'greeting_name' => $meta['greeting_name'] ?? 'Kontakt',
                    'caller_phone' => $meta['caller_phone'] ?? '',
                    'handled_by' => 'twilio_secretary',
                    'summary' => $stubSummary,
                    'rahmenbedingungen' => [],
                    'action_required' => $stubAction,
                    'urgency' => 'mittel',
                    'callback_requested' => true,
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Wird vom Node.js Server am Ende eines Anrufs aufgerufen.
     * Erhält das KI-Transkript, bewertet es mit Gemini und legt für Whitelist-Anrufe sofort Alinas Dashboard-To-Do an.
     */
    public function callLog(Request $request)
    {
        $data = $request->validate([
            'planned_call_id' => 'nullable|string|max:160',
            'twilio_sid' => 'nullable|string|max:64',
            'contact_name' => 'nullable|string|max:160',
            'objective' => 'nullable|string|max:2500',
            'transcript' => 'nullable|array|max:100',
            'transcript.*' => 'string|max:2000',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        // Twilio sendet CallSid, Node.js sendet twilio_sid
        $sid = $data['twilio_sid'] ?? $request->input('CallSid');

        // Direkter Twilio Fallback bei Fehlern (nur wenn eine gültige, existierende CallSid vorliegt)
        if ($request->has('CallStatus') && in_array($request->input('CallStatus'), ['failed', 'canceled', 'no-answer', 'busy'], true)) {
            if (!$this->isAuthorizedTwilioWebhook($request)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
            if (!is_string($sid) || !preg_match('/^CA[a-f0-9]{16,64}$/i', $sid)) {
                return response()->json(['error' => 'Missing CallSid'], 400);
            }
            $callRecord = SupportTelephonyCall::where('twilio_sid', $sid)->first();
            if ($callRecord) {
                $errorCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('ErrorCode', 'KeinCode'));
                $callRecord->status = 'failed';
                $callRecord->summary = 'Anruf fehlgeschlagen. Twilio Status: ' . $request->input('CallStatus') . '. Error: ' . ($errorCode !== '' ? $errorCode : 'Kein Code');
                $callRecord->save();
                return response()->json(['status' => 'updated_from_twilio_status']);
            }
            return response()->json(['status' => 'ignored_unknown_sid'], 404);
        }

        // Prüfe entweder globales Bridge-Secret ODER kryptografisches 256-Bit Per-Call-Token aus inbound()
        $rawPlannedId = trim((string) ($data['planned_call_id'] ?? ''));
        $resolvedCallRecordId = $rawPlannedId;
        $verifiedInboundMeta = null;

        if ($rawPlannedId !== '' && preg_match('/^(\d+):([a-f0-9]{64})$/', $rawPlannedId, $m)) {
            $candidateId = $m[1];
            $providedNonce = $m[2];
            $cachedMeta = Cache::get(self::CACHE_INBOUND_META_PREFIX . $candidateId);
            if (is_array($cachedMeta) && !empty($cachedMeta['nonce']) && hash_equals((string) $cachedMeta['nonce'], $providedNonce)) {
                $verifiedInboundMeta = $cachedMeta;
                $resolvedCallRecordId = $candidateId;
            }
        }

        if ($verifiedInboundMeta === null && !SystemSecurityService::isTrustedBridgeRequest($request)) {
            SystemSecurityService::logEvent(
                'security:twilio_calllog_unauthorized',
                'Unautorisierter Twilio-CallLog-Aufruf blockiert',
                'Externer Aufruf auf /api/twilio/call-log von IP ' . $request->ip() . ' abgewehrt.',
                'error'
            );
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $callRecord = null;
        if (!empty($resolvedCallRecordId) && ctype_digit((string) $resolvedCallRecordId)) {
            $callRecord = SupportTelephonyCall::find((int) $resolvedCallRecordId);
        }
        if (!$callRecord && !empty($sid)) {
            $callRecord = SupportTelephonyCall::where('twilio_sid', $sid)->first();
        }
        if (!$callRecord) {
            $callRecord = new SupportTelephonyCall();
            $callRecord->contact_name = $data['contact_name'] ?? 'Unbekannt';
            $callRecord->objective = $data['objective'] ?? '';
        }
        if (empty($callRecord->twilio_sid) && !empty($sid)) {
            $callRecord->twilio_sid = $sid;
        }

        $transcriptLines = array_slice($data['transcript'] ?? [], 0, 120);
        $callRecord->transcript = json_encode($transcriptLines, JSON_UNESCAPED_UNICODE);
        $callRecord->duration_seconds = $data['duration_seconds'] ?? 0;
        $callRecord->status = 'completed';

        $extractedUrgency = 'mittel';
        $extractedCallback = true;
        $extractedRahmenbedingungen = [];
        $extractedActionRequired = '';

        // Bewerte das Live-Gespräch strukturiert mit Gemini (Besprochenes, Rahmenbedingungen, Was Alina tun soll)
        if (!empty($transcriptLines)) {
            $apiKey = config('services.gemini.key');
            if ($apiKey && (!app()->environment('testing') || config('services.gemini.allow_in_tests', false))) {
                try {
                    $transcriptText = mb_substr(implode("\n", $transcriptLines), 0, 15000);
                    if ($verifiedInboundMeta !== null) {
                        $callerLabel = $verifiedInboundMeta['caller_name'] ?? 'Kontakt';
                        $prompt = "Du bist Funkira, die persönliche Sekretärin von Alina Steinhauer. Folgendes ist das vollständige Live-Gesprächsprotokoll mit {$callerLabel}:\n\n"
                            . $transcriptText
                            . "\n\nErstelle für Alina eine glasklare Sekretärinnen-Zusammenfassung auf Deutsch (niemals KI-Gedankenstriche verwenden!). "
                            . "Extrahiere präzise:\n"
                            . "1. 'summary': Was wurde besprochen? (1 bis 2 prägnante Sätze)\n"
                            . "2. 'rahmenbedingungen': Array aller wichtigen geklärten Eckdaten (Datum, Uhrzeit, Zeitraum, Ort, Fristen, Wünsche oder Fakten als kurze Stichpunkte)\n"
                            . "3. 'action_required': Was genau soll Alina tun? (Konkrete Handlungsanweisung, z. B. 'Philip heute ab 18:00 Uhr zurückrufen und wegen Samstag Bescheid geben' oder 'Keine Aktion nötig, reine Information')\n"
                            . "4. 'urgency': 'hoch', 'mittel' oder 'niedrig'\n"
                            . "5. 'callback_requested': true oder false\n\n"
                            . "Antworte ausschließlich mit einem reinen JSON-Objekt ohne Markdown-Backticks im Format:\n"
                            . '{"summary": "...", "rahmenbedingungen": ["..."], "action_required": "...", "urgency": "hoch|mittel|niedrig", "callback_requested": true}';
                    } else {
                        $prompt = "Du bist eine Analyse-KI. Folgendes ist das vollständige Protokoll eines Telefonats.\nZiel des Anrufs war: " . ($data['objective'] ?? 'Unbekannt') . "\n\nTranscript:\n" . $transcriptText . "\n\nBitte bewerte, ob das Ziel erreicht wurde. Antworte ausschließlich mit einem JSON Objekt in folgendem exaktem Format (Kein Markdown, keine Backticks): {\"summary\": \"Kurzes klares Fazit\", \"rahmenbedingungen\": [], \"action_required\": \"Nächster Schritt\", \"next_steps\": [\"Task 1\"], \"goals\": [{\"task\": \"Erstes Ziel aus dem Objective\", \"achieved\": true}]}";
                    }

                    $candidateModels = ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-3.5-flash'];
                    $response = null;
                    foreach ($candidateModels as $modelName) {
                        try {
                            $tryRes = Http::withOptions([
                                'force_ip_resolve' => 'v4',
                                'connect_timeout' => 5,
                            ])->timeout(15)->withHeaders([
                                'Content-Type' => 'application/json',
                                'x-goog-api-key' => $apiKey,
                            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent", [
                                'contents' => [
                                    ['parts' => [['text' => $prompt]]],
                                ],
                            ]);
                            if ($tryRes->successful()) {
                                $response = $tryRes;
                                break;
                            }
                        } catch (\Throwable $candidateErr) {
                            // Nächstes Fallback-Modell versuchen (deterministischer Fallback greift zusätzlich weiter unten)
                            continue;
                        }
                    }

                    if ($response && $response->successful()) {
                        $geminiData = $response->json();
                        $text = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                        $text = preg_replace('/```json|```/', '', (string) $text);
                        $result = json_decode(trim((string) $text), true);

                        if (is_array($result)) {
                            $cleanSummary = str_replace(' – ', ', ', trim((string) ($result['summary'] ?? '')));
                            if ($cleanSummary !== '') {
                                $callRecord->summary = $cleanSummary;
                            }
                            if (!empty($result['urgency']) && in_array(strtolower((string) $result['urgency']), ['hoch', 'mittel', 'niedrig'], true)) {
                                $extractedUrgency = strtolower((string) $result['urgency']);
                            }
                            if (array_key_exists('callback_requested', $result)) {
                                $extractedCallback = (bool) $result['callback_requested'];
                            }
                            if (!empty($result['rahmenbedingungen']) && is_array($result['rahmenbedingungen'])) {
                                foreach ($result['rahmenbedingungen'] as $rb) {
                                    $cleanRb = str_replace(' – ', ': ', trim((string) $rb));
                                    if ($cleanRb !== '') {
                                        $extractedRahmenbedingungen[] = $cleanRb;
                                    }
                                }
                            }
                            if (!empty($result['action_required'])) {
                                $extractedActionRequired = str_replace(' – ', ', ', trim((string) $result['action_required']));
                            }
                            $stepsList = $result['next_steps'] ?? ($extractedActionRequired !== '' ? [$extractedActionRequired] : []);
                            $combinedJson = [
                                'mode' => $verifiedInboundMeta !== null ? 'inbound_secretary' : 'outbound',
                                'rahmenbedingungen' => $extractedRahmenbedingungen,
                                'action_required' => $extractedActionRequired,
                                'urgency' => $extractedUrgency,
                                'callback_requested' => $extractedCallback,
                                'steps' => $stepsList,
                                'goals' => $result['goals'] ?? [],
                            ];
                            $callRecord->next_steps = json_encode($combinedJson, JSON_UNESCAPED_UNICODE);
                        }
                    }
                } catch (\Exception $e) {
                    $safeMsg = SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
                    \Log::error('Fehler bei der Call-Auswertung: ' . $safeMsg);
                }
            }
        }

        // Deterministischer Fallback, falls kein LLM-Summary erzeugt wurde (z. B. kurzes Gespräch oder Timeout)
        if ($verifiedInboundMeta !== null && empty($callRecord->summary)) {
            $who = $verifiedInboundMeta['greeting_name'] ?? ($verifiedInboundMeta['caller_name'] ?? 'Kontakt');
            $callerTurns = [];
            foreach ($transcriptLines as $line) {
                if (str_starts_with($line, 'Anrufer:')) {
                    $t = trim(substr($line, strlen('Anrufer:')));
                    if ($t !== '') {
                        $callerTurns[] = $t;
                    }
                }
            }
            $joinedCaller = trim(implode(' ', $callerTurns));
            if (mb_strlen($joinedCaller) >= 4) {
                $callRecord->summary = Str::limit($joinedCaller, 600, '...');
                if ($extractedActionRequired === '') {
                    $extractedActionRequired = "{$who} zurückrufen bzw. Anliegen bearbeiten.";
                }
            } else {
                $callRecord->summary = "{$who} hat angerufen (kurzes Gespräch ohne nähere Details).";
                if ($extractedActionRequired === '') {
                    $extractedActionRequired = "{$who} bei Gelegenheit zurückrufen.";
                }
            }
            $callRecord->next_steps = json_encode([
                'mode' => 'inbound_secretary',
                'rahmenbedingungen' => $extractedRahmenbedingungen,
                'action_required' => $extractedActionRequired,
                'urgency' => $extractedUrgency,
                'callback_requested' => $extractedCallback,
                'steps' => [$extractedActionRequired],
                'goals' => [],
            ], JSON_UNESCAPED_UNICODE);
        }

        $callRecord->save();

        // Für eingehende Whitelist-Anrufe: Dashboard-To-Do + Kontakt-Gedächtnis + Smartphone-Push auslösen!
        if ($verifiedInboundMeta !== null) {
            FunkiraPhoneConciergeService::completeCall([
                'call_id' => 'twilio_' . $callRecord->id,
                'is_simulation' => false,
                'is_fallback_stub' => false,
                'contact_id' => $verifiedInboundMeta['contact_id'] ?? null,
                'caller_name' => $verifiedInboundMeta['caller_name'] ?? 'Kontakt',
                'greeting_name' => $verifiedInboundMeta['greeting_name'] ?? 'Kontakt',
                'caller_phone' => $verifiedInboundMeta['caller_phone'] ?? '',
                'handled_by' => 'twilio_secretary',
                'summary' => (string) $callRecord->summary,
                'rahmenbedingungen' => $extractedRahmenbedingungen,
                'action_required' => $extractedActionRequired,
                'urgency' => $extractedUrgency,
                'callback_requested' => $extractedCallback,
            ]);
        }

        return response()->json(['status' => 'success', 'id' => $callRecord->id]);
    }
}

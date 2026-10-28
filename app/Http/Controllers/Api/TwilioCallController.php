<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse;

class TwilioCallController extends Controller
{
    /**
     * Wird von Twilio aufgerufen, sobald ein von uns initiierter Anruf (Outbound) vom Empfänger abgenommen wird.
     */
    public function outbound(Request $request)
    {
        $configuredAccountSid = config('services.twilio.sid') ?: env('TWILIO_SID') ?: env('TWILIO_ACCOUNT_SID');
        if (!empty($configuredAccountSid)) {
            if (!$request->filled('AccountSid') || !is_string($request->input('AccountSid')) || !hash_equals((string) $configuredAccountSid, (string) $request->input('AccountSid'))) {
                return response('Forbidden', 403);
            }
        } elseif (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request)) {
            return response('Forbidden', 403);
        }

        $response = new VoiceResponse();
        // Versuch, den im Vorfeld gespeicherten Kontext aus dem Cache zu holen (nur wenn eine gültige, in der DB registrierte CallSid vorliegt)
        $toPhone = preg_replace('/[^0-9+]/', '', (string) $request->input('To', ''));
        $callSid = is_string($request->input('CallSid')) ? trim($request->input('CallSid')) : '';
        $hasVerifiedCall = $callSid !== ''
            && preg_match('/^CA[a-f0-9]{16,64}$/i', $callSid)
            && \App\Models\SupportTelephonyCall::where('twilio_sid', $callSid)->exists();
        $context = ($toPhone !== '' && $hasVerifiedCall)
            ? \Illuminate\Support\Facades\Cache::pull("twilio_call_" . $toPhone)
            : null;

        // Verbinde mit dem Node.js WebSocket (Host aus vertrauenswürdiger App-Konfiguration statt unvalidiertem Host-Header)
        $connect = $response->connect();
        $trustedHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $url = env('TWILIO_WSS_URL', 'wss://' . $trustedHost . ':8081/twilio-stream');
        $stream = $connect->stream([
            'url' => $url,
            'track' => 'both_tracks'
        ]);

        if ($context) {
            $stream->parameter(['name' => 'contact_name', 'value' => $context['contact_name'] ?? 'Unbekannt']);
            $stream->parameter(['name' => 'objective', 'value' => $context['objective'] ?? 'Führe ein nettes Gespräch.']);
            $stream->parameter(['name' => 'system_instructions', 'value' => $context['system_instructions'] ?? '']);
            $stream->parameter(['name' => 'ai_learned_facts', 'value' => $context['ai_learned_facts'] ?? '']);
            $stream->parameter(['name' => 'calendar_events', 'value' => $context['calendar_events'] ?? 'Keine Termine']);
            $stream->parameter(['name' => 'agent_name', 'value' => $context['agent_name'] ?? 'Alina Steinhauer']);
            $stream->parameter(['name' => 'agent_profile', 'value' => $context['agent_profile'] ?? 'Du bist eine hilfreiche KI Assistentin.']);
            $stream->parameter(['name' => 'planned_call_id', 'value' => (string)($context['planned_call_id'] ?? '')]);
        }

        return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Wird von Twilio aufgerufen, wenn jemand DEINE Twilio-Nummer anruft (Inbound).
     */
    public function inbound(Request $request)
    {
        $configuredAccountSid = config('services.twilio.sid') ?: env('TWILIO_SID') ?: env('TWILIO_ACCOUNT_SID');
        if (!empty($configuredAccountSid)) {
            if (!$request->filled('AccountSid') || !is_string($request->input('AccountSid')) || !hash_equals((string) $configuredAccountSid, (string) $request->input('AccountSid'))) {
                return response('Forbidden', 403);
            }
        } elseif (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request)) {
            return response('Forbidden', 403);
        }

        $response = new VoiceResponse();
        
        $response->say('Willkommen bei Seelenfunke. Sie werden nun mit der KI verbunden.', ['language' => 'de-DE']);

        // Hier wird später der WebSocket (Stream) zu unserer Node.js Audio-Bridge aufgebaut
        // $connect = $response->connect();
        // $connect->stream([
        //     'url' => env('TWILIO_WSS_URL', 'wss://' . $request->getHost() . '/twilio-stream'),
        //     'track' => 'both_tracks'
        // ]);

        return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Wird vom Node.js Server am Ende eines Anrufs aufgerufen.
     * Erhält das KI-Transkript, bewertet es mit Gemini und speichert das Fazit.
     */
    public function callLog(Request $request)
    {
        $data = $request->validate([
            'planned_call_id' => 'nullable|string|max:64',
            'twilio_sid' => 'nullable|string|max:64',
            'contact_name' => 'nullable|string|max:120',
            'objective' => 'nullable|string|max:1000',
            'transcript' => 'nullable|array|max:100',
            'transcript.*' => 'string|max:2000',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        // Twilio sendet CallSid, Node.js sendet twilio_sid
        $sid = $data['twilio_sid'] ?? $request->input('CallSid');
        
        // Direkter Twilio Fallback bei Fehlern (nur wenn eine gültige, existierende CallSid vorliegt)
        if ($request->has('CallStatus') && in_array($request->input('CallStatus'), ['failed', 'canceled', 'no-answer', 'busy'], true)) {
            $configuredAccountSid = config('services.twilio.sid') ?: env('TWILIO_SID') ?: env('TWILIO_ACCOUNT_SID');
            if (!empty($configuredAccountSid)) {
                if (!$request->filled('AccountSid') || !is_string($request->input('AccountSid')) || !hash_equals((string) $configuredAccountSid, (string) $request->input('AccountSid'))) {
                    return response()->json(['error' => 'Forbidden'], 403);
                }
            } elseif (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
            if (!is_string($sid) || !preg_match('/^CA[a-f0-9]{16,64}$/i', $sid)) {
                return response()->json(['error' => 'Missing CallSid'], 400);
            }
            $callRecord = \App\Models\SupportTelephonyCall::where('twilio_sid', $sid)->first();
            if ($callRecord) {
                $errorCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->input('ErrorCode', 'KeinCode'));
                $callRecord->status = 'failed';
                $callRecord->summary = "Anruf fehlgeschlagen. Twilio Status: " . $request->input('CallStatus') . ". Error: " . ($errorCode !== '' ? $errorCode : 'Kein Code');
                $callRecord->save();
                return response()->json(['status' => 'updated_from_twilio_status']);
            }
            return response()->json(['status' => 'ignored_unknown_sid'], 404);
        }

        // Voller Call-Log & KI-Auswertungs-Endpunkt darf nur intern von der Node.js Bridge aufgerufen werden
        if (!\App\Services\System\SystemSecurityService::isTrustedBridgeRequest($request)) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:twilio_calllog_unauthorized',
                'Unautorisierter Twilio-CallLog-Aufruf blockiert',
                'Externer Aufruf auf /api/twilio/call-log von IP ' . $request->ip() . ' abgewehrt.',
                'error'
            );
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $callRecord = null;
        if (!empty($data['planned_call_id'])) {
            $callRecord = \App\Models\SupportTelephonyCall::find($data['planned_call_id']);
        }
        if (!$callRecord) {
            $callRecord = \App\Models\SupportTelephonyCall::where('twilio_sid', $sid)->first();
        }
        if (!$callRecord) {
            $callRecord = new \App\Models\SupportTelephonyCall();
            $callRecord->contact_name = $data['contact_name'] ?? 'Unbekannt';
            $callRecord->objective = $data['objective'] ?? '';
        }
        $callRecord->twilio_sid = $sid ?? '';
        
        $callRecord->transcript = json_encode($data['transcript'] ?? []);
        $callRecord->duration_seconds = $data['duration_seconds'] ?? 0;
        $callRecord->status = 'completed';

        // Bewerte den Call mit Gemini
        if (!empty($data['transcript'])) {
            $apiKey = env('GEMINI_API_KEY') ?: env('GOOGLE_API_KEY');
            if ($apiKey) {
                try {
                    $transcriptLines = array_slice($data['transcript'], 0, 100);
                    $transcriptText = mb_substr(implode("\n", $transcriptLines), 0, 15000);
                    $prompt = "Du bist eine Analyse-KI. Folgendes ist das vollständige Protokoll eines Telefonats.\nZiel des Anrufs war: " . ($data['objective'] ?? 'Unbekannt') . "\n\nTranscript:\n" . $transcriptText . "\n\nBitte bewerte, ob das Ziel erreicht wurde. Antworte ausschließlich mit einem JSON Objekt in folgendem exaktem Format (Kein Markdown, keine Backticks): {\"summary\": \"Kurzes klares Fazit\", \"next_steps\": [\"Task 1\"], \"goals\": [{\"task\": \"Erstes Ziel aus dem Objective\", \"achieved\": true}, {\"task\": \"Zweites Ziel\", \"achieved\": false}]}";

                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $apiKey,
                    ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent", [
                        'contents' => [
                            ['parts' => [['text' => $prompt]]]
                        ]
                    ]);

                    if ($response->successful()) {
                        $geminiData = $response->json();
                        $text = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                        $text = preg_replace('/```json|```/', '', $text);
                        $result = json_decode(trim($text), true);

                        if ($result) {
                            $callRecord->summary = $result['summary'] ?? null;
                            
                            // Combine next_steps and goals into the next_steps JSON column to avoid DB schema changes
                            $combinedJson = [
                                'steps' => $result['next_steps'] ?? [],
                                'goals' => $result['goals'] ?? []
                            ];
                            $callRecord->next_steps = json_encode($combinedJson);
                        }
                    }
                } catch (\Exception $e) {
                    $safeMsg = \App\Services\System\SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
                    \Log::error('Fehler bei der Call-Auswertung: ' . $safeMsg);
                }
            }
        }

        $callRecord->save();

        return response()->json(['status' => 'success', 'id' => $callRecord->id]);
    }
}

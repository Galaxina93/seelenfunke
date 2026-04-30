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
        $response = new VoiceResponse();
        // Versuch, den im Vorfeld gespeicherten Kontext aus dem Cache zu holen
        $toPhone = preg_replace('/[^0-9+]/', '', $request->input('To', ''));
        $context = \Illuminate\Support\Facades\Cache::pull("twilio_call_" . $toPhone);

        // Verbinde mit dem Node.js WebSocket
        $connect = $response->connect();
        
        $url = env('TWILIO_WSS_URL', 'wss://' . $request->getHost() . ':8081/twilio-stream');
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
        }

        return response($response->asXML(), 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Wird von Twilio aufgerufen, wenn jemand DEINE Twilio-Nummer anruft (Inbound).
     */
    public function inbound(Request $request)
    {
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
            'twilio_sid' => 'nullable|string',
            'contact_name' => 'nullable|string',
            'objective' => 'nullable|string',
            'transcript' => 'nullable|array',
            'duration_seconds' => 'nullable|integer',
        ]);

        $callRecord = new \App\Models\SupportTelephonyCall();
        $callRecord->twilio_sid = $data['twilio_sid'] ?? '';
        $callRecord->contact_name = $data['contact_name'] ?? 'Unbekannt';
        $callRecord->objective = $data['objective'] ?? '';
        $callRecord->transcript = json_encode($data['transcript'] ?? []);
        $callRecord->duration_seconds = $data['duration_seconds'] ?? 0;
        $callRecord->status = 'completed';

        // Bewerte den Call mit Gemini
        if (!empty($data['transcript'])) {
            $apiKey = env('GEMINI_API_KEY') ?: env('GOOGLE_API_KEY');
            if ($apiKey) {
                try {
                    $transcriptText = implode("\n", $data['transcript']);
                    $prompt = "Du bist eine Analyse-KI. Folgendes ist das Protokoll (nur KI-Antworten) eines Telefonats.\nZiel des Anrufs war: " . ($data['objective'] ?? 'Unbekannt') . "\n\nTranscript:\n" . $transcriptText . "\n\nBitte bewerte, ob das Ziel erreicht wurde. Antworte ausschließlich mit einem JSON Objekt in folgendem Format (Kein Markdown, keine Backticks): {\"summary\": \"Kurzes klares Fazit (z.B. Termin wurde vereinbart)\", \"next_steps\": [\"Task 1\", \"Task 2\"]}";

                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Content-Type' => 'application/json'
                    ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
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
                            $callRecord->next_steps = json_encode($result['next_steps'] ?? []);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Fehler bei der Call-Auswertung: ' . $e->getMessage());
                }
            }
        }

        $callRecord->save();

        return response()->json(['status' => 'success', 'id' => $callRecord->id]);
    }
}

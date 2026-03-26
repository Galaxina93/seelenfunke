<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiVoiceController extends Controller
{
    /**
     * Proxies text to ElevenLabs API and streams the audio response.
     * If the quota is exceeded or an error occurs, it returns a 402/500
     * so the frontend can fallback to local browser TTS.
     */
    public function generateSpeech(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:1000'
        ]);

        $text = $request->input('text');
        $agent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first();
        if (!$agent) {
            return response('Agent is inactive or offline.', 403);
        }
        
        // Neu: Text vorher für deutsche Sprachausgabe "vorreinigen" und lesbar machen
        $text = \App\Services\AI\TTSHelper::sanitizeForGermanTTS($text);
        
        $ttsProvider = $agent ? $agent->tts_provider : 'toni_xttsv2';
        
        // Zwinge das System auf Toni, wenn der User in der UI den Cloudflare-Link eingegeben hat, 
        // aber die DB aus Legacy-Gründen noch auf 'elevenlabs' festhängt.
        if ($ttsProvider === 'elevenlabs' && $agent && !empty($agent->tts_api_url)) {
            $ttsProvider = 'toni_xttsv2';
        }

        // -------------------------------------------------------------
        // TONI XTTSv2 Pfad
        // -------------------------------------------------------------
        if ($ttsProvider === 'toni_xttsv2') {
            $apiUrl = $agent->tts_api_url ?: env('TONI_AI_URL', 'http://192.168.188.32:8000');
            if (!str_ends_with(parse_url($apiUrl, PHP_URL_PATH) ?? '', '/api/toni/tts')) {
                $apiUrl = rtrim($apiUrl, '/') . '/api/toni/tts';
            }
            $apiKey = env('TONI_AI_API_KEY');
            $speed = $agent->tts_speed ?? 1.0;
            
            $payload = [
                'text' => $text,
                'language' => 'de',
                'speed' => $speed
            ];
            if ($agent->tts_voice) {
                $payload['voice_key'] = $agent->tts_voice;
            }

            try {
                Log::info("Toni XTTSv2 Request. Text: '{$text}' an {$apiUrl}");
                $requestObj = Http::timeout(30);
                if (!empty($apiKey)) {
                    $requestObj = $requestObj->withToken($apiKey);
                }
                
                $response = $requestObj->post($apiUrl, $payload);
                
                $isAudio = str_contains($response->header('Content-Type'), 'audio/');
                
                // Fallback: If Toni fails (HTTP error or JSON error payload instead of audio), retry with 'default'
                if ((!$response->successful() || !$isAudio) && isset($payload['voice_key'])) {
                    Log::warning("Toni rejected voice_key '{$payload['voice_key']}'. Retrying with default voice: " . $response->body());
                    unset($payload['voice_key']);
                    $response = $requestObj->post($apiUrl, $payload);
                    $isAudio = str_contains($response->header('Content-Type'), 'audio/');
                }
                
                if ($response->successful() && $isAudio) {
                    return response($response->body(), 200)
                        ->header('Content-Type', 'audio/wav')
                        ->header('Cache-Control', 'no-cache')
                        ->header('Pragma', 'no-cache');
                } else {
                    Log::warning("Toni HTTP-Error in FunkiraVoiceController: " . $response->status() . " - " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Toni TTS proxy failed: " . $e->getMessage());
            }
            
            // Falls Toni fehlschlägt, aber ElevenLabs nicht explizit konfiguriert war, abbrechen.
            if ($agent->tts_provider !== 'elevenlabs') {
                 return response()->json(['error' => 'Toni TTS Error'], 500);
            }
        }

        // -------------------------------------------------------------
        // ELEVENLABS Pfad (Fallback oder Explizit)
        // -------------------------------------------------------------
        $apiKey = env('ELEVENLABS_API_KEY');
        $voiceId = $agent ? $agent->tts_voice : env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM');

        if (empty($apiKey)) {
            return response()->json(['error' => 'ElevenLabs API Key not configured.'], 400);
        }

        try {
            Log::info("ElevenLabs TTS Request. Text length: " . mb_strlen($text) . " | Text: '{$text}'");
            
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'audio/mpeg'
            ])->timeout(30)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => 'eleven_multilingual_v2', // Best current multilingual model
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                ]
            ]);

            if ($response->successful()) {
                $audioBytes = strlen($response->body());
                Log::info("ElevenLabs TTS Success. Audio bytes received: {$audioBytes}");
                
                // Return the raw audio binary with correct headers
                return response($response->body(), 200)
                    ->header('Content-Type', 'audio/mpeg')
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');
            }

            // Quota Exceeded handles (401/402 usually signify payment/quota issues on ElevenLabs)
            if ($response->status() === 401 || $response->status() === 402) {
                 Log::warning("ElevenLabs Quota Exceeded: " . $response->body());
                 return response()->json(['error' => 'Quota reached.'], 402);
            }

            $apiResponse = $response->json();
            
            Log::error("ElevenLabs Error (Missing API Permissions or Invalid Voice ID): " . $response->body());
            return response()->json(['error' => 'Upstream API Error'], 400);

        } catch (\Throwable $e) {
            Log::error("ElevenLabs proxy failed: " . $e->getMessage());
            return response()->json(['error' => 'Internal Proxy Error'], 400);
        }
    }
}

<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FunkiraVoiceController extends Controller
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
        
        // Neu: Text vorher für deutsche Sprachausgabe (ElevenLabs) "vorreinigen" und lesbar machen
        $text = \App\Services\AI\TTSHelper::sanitizeForGermanTTS($text);
        
        $apiKey = env('ELEVENLABS_API_KEY');
        // Fallback to Rachel voice if none is configured
        $voiceId = env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM');

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
            
            // Auto-Fallback if the Voice ID does not exist on this account
            if (($response->status() === 400 || $response->status() === 404) && str_contains($response->body(), 'voice_not_found')) {
                Log::warning("Voice {$voiceId} not found. Attempting to auto-fetch first available voice.");
                $voicesResponse = Http::withHeaders(['xi-api-key' => $apiKey])->timeout(30)->get("https://api.elevenlabs.io/v1/voices");
                $voices = $voicesResponse->json();
                
                Log::info("ElevenLabs /v1/voices Fallback Response: " . $voicesResponse->body());
                
                if (!empty($voices['voices'])) {
                    $firstVoice = $voices['voices'][0]['voice_id'];
                    Log::info("Auto-selected fallback voice: {$firstVoice}");
                    
                    // Retry with the new valid voice ID
                    $retry = Http::withHeaders([
                        'xi-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'audio/mpeg'
                    ])->timeout(30)->post("https://api.elevenlabs.io/v1/text-to-speech/{$firstVoice}", [
                        'text' => $text,
                        'model_id' => 'eleven_multilingual_v2',
                        'voice_settings' => [
                            'stability' => 0.5,
                            'similarity_boost' => 0.75,
                        ]
                    ]);
                    
                    if ($retry->successful()) {
                        return response($retry->body(), 200)
                            ->header('Content-Type', 'audio/mpeg')
                            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                            ->header('Pragma', 'no-cache')
                            ->header('Expires', '0');
                    }
                }
            }

            Log::error("ElevenLabs Error (Missing API Permissions or Invalid Voice ID): " . $response->body());
            return response()->json(['error' => 'Upstream API Error (Invalid Voice or Missing Permissions)'], 400);

        } catch (\Throwable $e) {
            Log::error("ElevenLabs proxy failed: " . $e->getMessage());
            return response()->json(['error' => 'Internal Proxy Error'], 400);
        }
    }
}

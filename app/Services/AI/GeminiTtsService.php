<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiTtsService
{
    /**
     * Synthesize speech using Gemini native audio API and return base64 encoded WAV string.
     */
    public static function synthesizeWav(string $text, string $voice = 'Aoede'): ?string
    {
        $voice = trim($voice) ?: 'Aoede';
        $cacheKey = 'gemini_tts_sample_' . md5($voice . '_' . $text);

        return Cache::remember($cacheKey, 86400, function () use ($text, $voice) {
            $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
            if (empty($apiKey)) {
                Log::warning("GeminiTtsService: No GEMINI_API_KEY found.");
                return null;
            }

            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-tts-preview:generateContent?key=' . $apiKey;

            $promptText = "Lies folgenden Text freundlich und natürlich vor: " . $text;

            $data = [
                "contents" => [
                    ["role" => "user", "parts" => [["text" => $promptText]]]
                ],
                "generationConfig" => [
                    "responseModalities" => ["AUDIO"],
                    "speechConfig" => [
                        "voiceConfig" => [
                            "prebuiltVoiceConfig" => [
                                "voiceName" => $voice
                            ]
                        ]
                    ]
                ],
                "safetySettings" => [
                    ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"],
                    ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"]
                ]
            ];

            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $response = curl_exec($ch);
                curl_close($ch);

                $json = json_decode($response, true);
                if (isset($json['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                    $rawBase64 = $json['candidates'][0]['content']['parts'][0]['inlineData']['data'];
                    $pcmData = base64_decode($rawBase64);

                    // Wrap RAW PCM into standard WAV container (24kHz, mono, 16-bit)
                    $numChannels = 1;
                    $sampleRate = 24000;
                    $bitsPerSample = 16;
                    $byteRate = $sampleRate * $numChannels * ($bitsPerSample / 8);
                    $blockAlign = $numChannels * ($bitsPerSample / 8);
                    $subchunk2Size = strlen($pcmData);
                    $chunkSize = 36 + $subchunk2Size;

                    $wavHeader = pack('A4VA4A4VvvVVvvA4V',
                        'RIFF', $chunkSize, 'WAVE',
                        'fmt ', 16, 1, $numChannels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample,
                        'data', $subchunk2Size
                    );

                    return base64_encode($wavHeader . $pcmData);
                } else {
                    Log::warning("GeminiTtsService error response: " . substr($response, 0, 300));
                    return null;
                }
            } catch (\Throwable $e) {
                Log::error("GeminiTtsService exception: " . $e->getMessage());
                return null;
            }
        });
    }
}

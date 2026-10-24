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
        $voice = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($voice)) ?: 'Aoede';
        $text = mb_substr(trim($text), 0, 2000);
        $cacheKey = 'gemini_tts_sample_' . md5($voice . '_' . $text);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (app()->environment('testing')) {
            $testWav = base64_encode('RIFF_TEST_WAV_DATA_' . $voice);
            Cache::put($cacheKey, $testWav, 86400);
            return $testWav;
        }

        $apiKey = config('services.gemini.key') ?: config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            Log::warning("GeminiTtsService: No GEMINI_API_KEY found.");
            return null;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-tts-preview:generateContent';

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
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $response = curl_exec($ch);
            curl_close($ch);

            $json = json_decode((string) $response, true);
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

                $wavBase64 = base64_encode($wavHeader . $pcmData);
                Cache::put($cacheKey, $wavBase64, 86400);
                return $wavBase64;
            } else {
                Log::warning("GeminiTtsService error response: " . substr((string) $response, 0, 300));
                return null;
            }
        } catch (\Throwable $e) {
            $safeMsg = \App\Services\System\SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
            Log::error("GeminiTtsService exception: " . $safeMsg);
            return null;
        }
    }
}

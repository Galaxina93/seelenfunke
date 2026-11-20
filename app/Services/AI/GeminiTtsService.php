<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiTtsService
{
    /**
     * Synthesize speech using Gemini native audio API and return base64 encoded WAV string.
     */
    public static function synthesizeWav(string $text, string $voice = 'Aoede', int $timeoutSeconds = 25): ?string
    {
        $voice = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($voice)) ?: 'Aoede';
        $text = mb_substr(trim($text), 0, 2000);
        $timeoutSeconds = max(4, min(35, $timeoutSeconds));
        $cacheKey = 'gemini_tts_sample_' . md5($voice . '_' . $text);

        try {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable) {
        }

        if (app()->environment('testing')) {
            $testWav = base64_encode('RIFF_TEST_WAV_DATA_' . $voice);
            try {
                Cache::put($cacheKey, $testWav, 86400);
            } catch (\Throwable) {
            }
            return $testWav;
        }

        if (!VertexAiService::hasCredentials()) {
            Log::warning("GeminiTtsService: Neither Vertex AI nor GEMINI_API_KEY configured.");
            return null;
        }

        $billingCooldownKey = 'gemini_tts_billing_depleted_cooldown';
        try {
            if (!VertexAiService::isEnabled() && Cache::has($billingCooldownKey)) {
                return null;
            }
        } catch (\Throwable) {
        }

        $models = [
            'gemini-2.5-flash-tts',
            'gemini-3.1-flash-tts-preview',
        ];

        $promptText = "Lies ausschließlich den folgenden Text Wort für Wort freundlich und natürlich vor, ohne eigene Sätze oder Ortsangaben zu ergänzen: " . $text;

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

        $errorsSummary = [];
        $allFailuresWereQuotaOrTransient = true;

        foreach ($models as $model) {
            $cooldownKey = 'gemini_tts_quota_cooldown_' . $model;
            try {
                if (Cache::has($cooldownKey)) {
                    $errorsSummary[] = "{$model}: quota_cooldown";
                    continue;
                }
            } catch (\Throwable) {
            }

            $endpoint = VertexAiService::resolveGenerateContentEndpoint($model);
            $url = $endpoint['url'];
            $curlHeaders = [];
            foreach ($endpoint['headers'] as $hKey => $hVal) {
                $curlHeaders[] = "{$hKey}: {$hVal}";
            }
            $curlHeaders[] = 'Expect:';

            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(6, $timeoutSeconds));
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                $response = curl_exec($ch);
                $curlErr = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($response === false) {
                    $errorsSummary[] = "{$model}: curl_error=" . ($curlErr ?: 'timeout');
                    continue;
                }

                $json = json_decode((string) $response, true);
                if (isset($json['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                    $rawBase64 = $json['candidates'][0]['content']['parts'][0]['inlineData']['data'];
                    $pcmData = base64_decode($rawBase64, true);
                    if ($pcmData === false || $pcmData === '') {
                        $errorsSummary[] = "{$model}: empty_pcm_payload";
                        continue;
                    }

                    // Manche Modelle (z.B. gemini-3.8-flash-tts) liefern bereits einen vollständigen RIFF/WAV-Container
                    if (str_starts_with($pcmData, 'RIFF')) {
                        $wavBase64 = base64_encode($pcmData);
                        try {
                            Cache::put($cacheKey, $wavBase64, 604800);
                        } catch (\Throwable) {
                        }
                        return $wavBase64;
                    }

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
                    try {
                        Cache::put($cacheKey, $wavBase64, 604800);
                    } catch (\Throwable) {
                    }
                    return $wavBase64;
                }

                $apiErrCode = (int) ($json['error']['code'] ?? $httpCode);
                $apiErrStatus = (string) ($json['error']['status'] ?? '');
                $apiErrMsg = \App\Services\System\SystemErrorLogger::sanitizeSensitiveText(
                    (string) ($json['error']['message'] ?? substr((string) $response, 0, 180))
                );

                if ($apiErrCode === 429 || $apiErrStatus === 'RESOURCE_EXHAUSTED') {
                    // 5 Minuten Cooldown für dieses spezifische TTS-Modell aktivieren, damit Fallback-Modelle sofort übernehmen
                    try {
                        Cache::put($cooldownKey, true, 300);
                    } catch (\Throwable) {
                    }
                    $errorsSummary[] = "{$model}: HTTP 429 quota_exhausted";
                    continue;
                }

                if (
                    $apiErrCode === 402
                    || stripos($apiErrMsg, 'prepayment credits') !== false
                    || stripos($apiErrMsg, 'billing') !== false
                ) {
                    // Projektweites Prepaid-Guthaben leer -> 30 Min Cooldown & sofortiger Offline-TTS-Fallback ohne Warnungs-Flut
                    try {
                        Cache::put($billingCooldownKey, true, 1800);
                    } catch (\Throwable) {
                    }
                    $errorsSummary[] = "{$model}: HTTP 402 billing_credits_depleted";
                    break;
                }

                if ($apiErrCode >= 400 && $apiErrCode < 500 && $apiErrCode !== 408 && $apiErrCode !== 404) {
                    $allFailuresWereQuotaOrTransient = false;
                }

                $errorsSummary[] = "{$model}: HTTP {$apiErrCode} {$apiErrMsg}";
            } catch (\Throwable $e) {
                $safeMsg = \App\Services\System\SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
                $errorsSummary[] = "{$model}: exception {$safeMsg}";
            }
        }

        if (!empty($errorsSummary)) {
            $summaryStr = implode(' | ', $errorsSummary);
            if ($allFailuresWereQuotaOrTransient) {
                // Transientes Upstream-Limit / Timeout: Client nutzt automatisch den nativen Offline-TTS-Fallback
                Log::info("GeminiTtsService fallback to client local TTS ({$summaryStr})");
            } else {
                Log::warning("GeminiTtsService all TTS models failed with permanent error: {$summaryStr}");
            }
        }

        return null;
    }
}

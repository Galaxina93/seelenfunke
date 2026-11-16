<?php

namespace App\Services\AI;

use App\Services\System\SystemErrorLogger;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VertexAiService
{
    /**
     * Prüft, ob Google Cloud Vertex AI aktiv und mit gültiger Service-Account-Datei konfiguriert ist.
     * In PHPUnit-Tests bleibt Vertex standardmäßig deaktiviert, damit bestehende Http::fake()-Mocks
     * gegen generativelanguage.googleapis.com deterministisch und ohne echte Netzwerkaufrufe laufen.
     */
    public static function isEnabled(): bool
    {
        if (app()->runningUnitTests() && !config('services.vertex.allow_in_tests', false)) {
            return false;
        }

        if (!config('services.vertex.enabled', true)) {
            return false;
        }

        return self::loadServiceAccount() !== null;
    }

    /**
     * Prüft, ob entweder Vertex AI oder ein klassischer GEMINI_API_KEY konfiguriert ist.
     */
    public static function hasCredentials(): bool
    {
        if (self::isEnabled()) {
            return true;
        }

        $apiKey = trim((string) (config('services.gemini.key') ?: config('services.gemini.api_key') ?: ''));

        return $apiKey !== '';
    }

    /**
     * Löst den absoluten Dateipfad zur Service-Account-JSON-Datei auf.
     */
    public static function resolveCredentialsPath(): string
    {
        $configured = trim((string) config('services.vertex.credentials_file', ''));
        if ($configured === '') {
            $configured = storage_path('app/vertex-service-account.json');
        }

        if (is_file($configured)) {
            return $configured;
        }

        $relative = base_path(ltrim($configured, '/\\'));
        if (is_file($relative)) {
            return $relative;
        }

        return $configured;
    }

    /**
     * Lädt und validiert die Service-Account-Daten aus der JSON-Datei oder aus services.vertex.credentials_json.
     */
    public static function loadServiceAccount(): ?array
    {
        $raw = null;
        $inlineJson = trim((string) config('services.vertex.credentials_json', ''));
        if ($inlineJson !== '') {
            $raw = str_starts_with($inlineJson, '{')
                ? $inlineJson
                : ((string) base64_decode($inlineJson, true) ?: '');
        }

        if ($raw === null || $raw === '') {
            $path = self::resolveCredentialsPath();
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                return null;
            }

            $fileContent = @file_get_contents($path);
            if (!is_string($fileContent) || $fileContent === '') {
                return null;
            }
            $raw = $fileContent;
        }

        $decoded = json_decode($raw, true);
        if (
            !is_array($decoded)
            || empty($decoded['client_email'])
            || empty($decoded['private_key'])
        ) {
            return null;
        }

        return $decoded;
    }

    /**
     * Ermittelt die aktive Google Cloud Project ID.
     */
    public static function getProjectId(?array $serviceAccount = null): string
    {
        $configured = trim((string) config('services.vertex.project_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        $sa = $serviceAccount ?? self::loadServiceAccount();

        return trim((string) ($sa['project_id'] ?? 'gen-lang-client-0670786367'));
    }

    /**
     * Erzeugt oder liest einen gecachten OAuth2-Bearer-Token (~53 Min. Cache) für Google Cloud Vertex AI.
     */
    public static function getAccessToken(bool $forceRefresh = false): ?string
    {
        if (!self::isEnabled()) {
            return null;
        }

        $sa = self::loadServiceAccount();
        if ($sa === null) {
            return null;
        }

        $projectId = self::getProjectId($sa);
        $cacheKey = 'google_vertex_oauth2_token_' . substr(sha1($sa['client_email'] . '|' . $projectId), 0, 16);

        if ($forceRefresh) {
            try {
                Cache::forget($cacheKey);
            } catch (\Throwable) {
            }
        } else {
            try {
                $cached = Cache::get($cacheKey);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
            } catch (\Throwable) {
            }
        }

        $jwt = self::signServiceAccountJwt($sa);
        if ($jwt === null) {
            return null;
        }

        $tokenUri = trim((string) ($sa['token_uri'] ?? 'https://oauth2.googleapis.com/token'));

        try {
            $response = Http::asForm()
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                    'connect_timeout' => 5,
                    'timeout' => 10,
                ])
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if ($response->successful()) {
                $accessToken = trim((string) $response->json('access_token', ''));
                $expiresIn = max(300, ((int) $response->json('expires_in', 3600)) - 420);

                if ($accessToken !== '') {
                    try {
                        Cache::put($cacheKey, $accessToken, $expiresIn);
                    } catch (\Throwable) {
                    }

                    return $accessToken;
                }
            } else {
                Log::warning('VertexAiService: OAuth2 Token-Abruf fehlgeschlagen.', [
                    'status' => $response->status(),
                    'body' => SystemErrorLogger::sanitizeSensitiveText(substr($response->body(), 0, 240)),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('VertexAiService: OAuth2 Token Exception: ' . SystemErrorLogger::sanitizeSensitiveText($e->getMessage()));
        }

        return null;
    }

    /**
     * Signiert ein kurzlebiges RS256-JWT für den Service-Account-Token-Tausch.
     */
    public static function signServiceAccountJwt(array $serviceAccount, ?int $now = null): ?string
    {
        $clientEmail = trim((string) ($serviceAccount['client_email'] ?? ''));
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');
        $tokenUri = trim((string) ($serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token'));

        if ($clientEmail === '' || $privateKey === '') {
            return null;
        }

        $issuedAt = ($now ?? time()) - 10;
        $headerSegment = self::base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimsSegment = self::base64UrlEncode((string) json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => $tokenUri,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3590,
        ]));

        $signingInput = $headerSegment . '.' . $claimsSegment;
        $signature = '';

        $signed = @openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed || $signature === '') {
            Log::error('VertexAiService: RS256 Signatur fehlgeschlagen.');

            return null;
        }

        return $signingInput . '.' . self::base64UrlEncode($signature);
    }

    /**
     * Mappt veraltete oder AI-Studio-spezifische Modellnamen auf aktive Vertex-AI-Modelle.
     */
    public static function resolveModel(string $model): string
    {
        $clean = strtolower(trim($model));
        if (str_starts_with($clean, 'models/')) {
            $clean = substr($clean, 7);
        }
        if (str_starts_with($clean, 'google/')) {
            $clean = substr($clean, 7);
        }

        return match ($clean) {
            '',
            'gemini-2.5-flash',
            'gemini-flash-latest',
            'gemini-2.0-flash',
            'gemini-2.0-flash-exp',
            'gemini-1.5-flash',
            'gemini-1.5-flash-8b' => 'gemini-3.5-flash',

            'gemini-2.5-pro',
            'gemini-3-pro-preview',
            'gemini-3.1-pro',
            'gemini-1.5-pro' => 'gemini-3.1-pro-preview',

            'gemini-2.5-flash-lite',
            'gemini-2.0-flash-lite',
            'gemini-3.1-flash-lite-preview' => 'gemini-3.5-flash-lite',

            'gemini-2.5-flash-preview-tts' => 'gemini-2.5-flash-tts',

            default => $clean,
        };
    }

    /**
     * Formatiert den Modellnamen für den OpenAI-kompatiblen Endpunkt (Vertex verlangt "google/"-Präfix).
     */
    public static function formatOpenAiModel(string $model, ?bool $isVertex = null): string
    {
        $resolved = self::resolveModel($model);
        $useVertex = $isVertex ?? self::isEnabled();

        if ($useVertex && !str_starts_with($resolved, 'google/')) {
            return 'google/' . $resolved;
        }

        return $resolved;
    }

    /**
     * Löst Basis-URL und Bearer-Token für OpenAI-kompatible Aufrufe (/chat/completions) auf.
     *
     * @return array{base_url: string, token: string, is_vertex: bool}
     */
    public static function resolveOpenAiEndpoint(): array
    {
        if (self::isEnabled()) {
            $token = self::getAccessToken();
            if ($token !== null && $token !== '') {
                $sa = self::loadServiceAccount();
                $projectId = self::getProjectId($sa);
                $location = trim((string) config('services.vertex.location', 'global')) ?: 'global';
                $host = $location === 'global'
                    ? 'aiplatform.googleapis.com'
                    : "{$location}-aiplatform.googleapis.com";

                return [
                    'base_url' => "https://{$host}/v1beta1/projects/{$projectId}/locations/{$location}/endpoints/openapi",
                    'token' => $token,
                    'is_vertex' => true,
                ];
            }
        }

        return [
            'base_url' => rtrim((string) (config('services.gemini.url') ?: 'https://generativelanguage.googleapis.com/v1beta/openai'), '/'),
            'token' => (string) (config('services.gemini.key') ?: config('services.gemini.api_key') ?: ''),
            'is_vertex' => false,
        ];
    }

    /**
     * Löst URL, Header und normalisiertes Modell für native :generateContent-Aufrufe auf.
     *
     * @return array{url: string, headers: array<string, string>, model: string, is_vertex: bool, location: string}
     */
    public static function resolveGenerateContentEndpoint(
        string $model,
        bool $forceRefreshToken = false,
        ?string $locationOverride = null,
        bool $preserveExactModel = false
    ): array {
        if (self::isEnabled()) {
            $token = self::getAccessToken($forceRefreshToken);
            if ($token !== null && $token !== '') {
                $sa = self::loadServiceAccount();
                $projectId = self::getProjectId($sa);
                $location = trim((string) ($locationOverride ?: config('services.vertex.location', 'global'))) ?: 'global';
                $host = $location === 'global'
                    ? 'aiplatform.googleapis.com'
                    : "{$location}-aiplatform.googleapis.com";
                $resolvedModel = $preserveExactModel
                    ? preg_replace('#^(?:models/|google/)#i', '', trim($model))
                    : self::resolveModel($model);

                return [
                    'url' => "https://{$host}/v1beta1/projects/{$projectId}/locations/{$location}/publishers/google/models/{$resolvedModel}:generateContent",
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'model' => (string) $resolvedModel,
                    'is_vertex' => true,
                    'location' => $location,
                ];
            }
        }

        $fallbackModel = (app()->runningUnitTests() || $preserveExactModel)
            ? preg_replace('#^(?:models/|google/)#i', '', trim($model))
            : self::resolveModel($model);
        $apiKey = (string) (config('services.gemini.key') ?: config('services.gemini.api_key') ?: '');

        return [
            'url' => "https://generativelanguage.googleapis.com/v1beta/models/{$fallbackModel}:generateContent",
            'headers' => [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ],
            'model' => (string) $fallbackModel,
            'is_vertex' => false,
            'location' => 'ai_studio',
        ];
    }

    /**
     * Normalisiert :generateContent-Payloads für Vertex AI (insb. Pflichtfeld role="user" in contents[]
     * sowie camelCase inlineData/mimeType).
     */
    public static function normalizeGenerateContentPayload(array $payload): array
    {
        if (isset($payload['system_instruction']) && !isset($payload['systemInstruction'])) {
            $payload['systemInstruction'] = $payload['system_instruction'];
            unset($payload['system_instruction']);
        }

        if (isset($payload['contents']) && is_array($payload['contents'])) {
            foreach ($payload['contents'] as $idx => $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }
                if (empty($contentItem['role'])) {
                    $contentItem['role'] = 'user';
                }
                if (isset($contentItem['parts']) && is_array($contentItem['parts'])) {
                    foreach ($contentItem['parts'] as $pIdx => $part) {
                        if (!is_array($part)) {
                            continue;
                        }
                        if (isset($part['inline_data']) && is_array($part['inline_data'])) {
                            $inline = $part['inline_data'];
                            if (isset($inline['mime_type']) && !isset($inline['mimeType'])) {
                                $inline['mimeType'] = $inline['mime_type'];
                                unset($inline['mime_type']);
                            }
                            $part['inlineData'] = $inline;
                            unset($part['inline_data']);
                            $contentItem['parts'][$pIdx] = $part;
                        }
                    }
                }
                $payload['contents'][$idx] = $contentItem;
            }
        }

        return $payload;
    }

    /**
     * Zentraler HTTP-Aufruf für :generateContent (Vertex AI Postpay primär, AI Studio Fallback in Unit-Tests).
     * Erneuert bei HTTP 401 automatisch einmalig den gecachten OAuth2-Token.
     */
    public static function generateContent(
        string $model,
        array $payload,
        int $timeout = 45,
        int $connectTimeout = 6,
        ?string $locationOverride = null,
        bool $preserveExactModel = false
    ): Response {
        $endpoint = self::resolveGenerateContentEndpoint($model, false, $locationOverride, $preserveExactModel);
        $normalizedPayload = $endpoint['is_vertex']
            ? self::normalizeGenerateContentPayload($payload)
            : $payload;

        $response = Http::withOptions([
            'force_ip_resolve' => 'v4',
            'connect_timeout' => $connectTimeout,
        ])
            ->timeout($timeout)
            ->withHeaders($endpoint['headers'])
            ->post($endpoint['url'], $normalizedPayload);

        if ($endpoint['is_vertex'] && $response->status() === 401) {
            $refreshedEndpoint = self::resolveGenerateContentEndpoint($model, true, $locationOverride, $preserveExactModel);
            $response = Http::withOptions([
                'force_ip_resolve' => 'v4',
                'connect_timeout' => $connectTimeout,
            ])
                ->timeout($timeout)
                ->withHeaders($refreshedEndpoint['headers'])
                ->post($refreshedEndpoint['url'], $normalizedPayload);
        }

        return $response;
    }

    /**
     * Löst die Live-Audio-WebSocket-Konfiguration (BidiGenerateContent) für EU-Vertex-AI (europe-west4)
     * bzw. AI-Studio-Fallback auf.
     *
     * @return array{is_vertex: bool, host: string, path: string, token: string, model: string, headers: array<string, string>, direct_ws_url: string}
     */
    public static function resolveLiveWebSocketEndpoint(): array
    {
        if (self::isEnabled()) {
            $token = self::getAccessToken();
            if ($token !== null && $token !== '') {
                $sa = self::loadServiceAccount();
                $projectId = self::getProjectId($sa);
                $liveLocation = trim((string) config('services.vertex.live_location', 'europe-west4')) ?: 'europe-west4';
                $liveModelId = trim((string) config('services.vertex.live_model', 'gemini-live-2.5-flash-native-audio')) ?: 'gemini-live-2.5-flash-native-audio';
                $host = "{$liveLocation}-aiplatform.googleapis.com";
                $path = '/ws/google.cloud.aiplatform.v1beta1.LlmBidiService/BidiGenerateContent';
                $modelResource = "projects/{$projectId}/locations/{$liveLocation}/publishers/google/models/{$liveModelId}";

                return [
                    'is_vertex' => true,
                    'host' => $host,
                    'path' => $path,
                    'token' => $token,
                    'model' => $modelResource,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'direct_ws_url' => "wss://{$host}{$path}?access_token=" . rawurlencode($token),
                ];
            }
        }

        $apiKey = (string) (config('services.gemini.key') ?: config('services.gemini.api_key') ?: '');
        $host = 'generativelanguage.googleapis.com';
        $path = '/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=' . rawurlencode($apiKey);

        return [
            'is_vertex' => false,
            'host' => $host,
            'path' => $path,
            'token' => $apiKey,
            'model' => 'models/gemini-3.1-flash-live-preview',
            'headers' => [],
            'direct_ws_url' => "wss://{$host}{$path}",
        ];
    }

    /**
     * Liefert die aktive Ping-/Healthcheck-Host-URL für Admin-Dashboards.
     */
    public static function getHealthCheckUrl(): string
    {
        if (self::isEnabled()) {
            return 'https://aiplatform.googleapis.com';
        }

        return 'https://generativelanguage.googleapis.com';
    }

    protected static function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}

<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WebSearchCascadeService
{
    private const TIER2_TIMEOUT_SECONDS = 5;
    private const TIER2_CONNECT_TIMEOUT_SECONDS = 3;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    /**
     * Führt eine 3-stufig abgesicherte, quota-resiliente Echtzeit-Websuche durch:
     * - Stufe 1: Google Cloud Vertex AI Postpay Grounding (google_search) mit Regions- & Modell-Kaskade
     *   (inkl. optionalem AI-Studio-Fallback falls Vertex deaktiviert ist).
     * - Stufe 2: Quota-freier, SSRF-geschützter Echtzeit-Web-Fallback (DuckDuckGo HTML/Lite + Wikipedia DE/EN)
     *   mit striktem 5s Timeout, sodass 429/RESOURCE_EXHAUSTED niemals an die Nutzerin durchschlägt.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function search(string $query, array $options = []): array
    {
        $cleanQuery = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        if ($cleanQuery === '') {
            return [
                'status' => 'error',
                'message' => 'Query fehlt.',
            ];
        }

        if (mb_strlen($cleanQuery) > 400) {
            $cleanQuery = mb_substr($cleanQuery, 0, 400);
        }

        $tier1Timeout = max(5, min(30, (int) ($options['tier1_timeout'] ?? 20)));
        $attempts = [];

        $tier1Result = self::attemptTier1Grounding($cleanQuery, $tier1Timeout, $attempts);
        if ($tier1Result !== null) {
            return $tier1Result;
        }

        return self::executeTier2RealtimeWebFallback($cleanQuery, $attempts);
    }

    /**
     * Stufe 1: Primäres Grounding über Vertex AI Postpay (mit Regions- & Modell-Kaskade)
     * bzw. Gemini-Grounding falls nur ein API-Key hinterlegt ist.
     *
     * @param  array<int, array<string, mixed>>  $attempts
     * @return array<string, mixed>|null
     */
    private static function attemptTier1Grounding(string $query, int $timeoutSeconds, array &$attempts): ?array
    {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => 'Bitte recherchiere detailliert, aktuell und präzise im Internet nach folgendem Thema. '
                                . 'Liefere eine zusammenhängende, faktenbasierte und auf den Punkt gebrachte Zusammenfassung auf Deutsch '
                                . '(ohne Gedankenstriche). Suchanfrage: ' . $query,
                        ],
                    ],
                ],
            ],
            'tools' => [
                [
                    'google_search' => (object) [],
                ],
            ],
        ];

        if (VertexAiService::isEnabled()) {
            foreach (self::buildVertexTargets() as [$location, $model, $preserveExactModel]) {
                try {
                    $response = VertexAiService::generateContent(
                        $model,
                        $payload,
                        $timeoutSeconds,
                        5,
                        $location,
                        $preserveExactModel
                    );

                    $parsed = self::parseGroundingResponse($response, 'vertex_grounding', $location, $model);
                    if ($parsed !== null) {
                        $attempts[] = [
                            'tier' => 1,
                            'provider' => 'vertex_grounding',
                            'location' => $location,
                            'model' => $model,
                            'http_status' => $response->status(),
                            'outcome' => 'success',
                        ];
                        $parsed['attempts'] = $attempts;

                        return $parsed;
                    }

                    $attempts[] = [
                        'tier' => 1,
                        'provider' => 'vertex_grounding',
                        'location' => $location,
                        'model' => $model,
                        'http_status' => $response->status(),
                        'outcome' => self::classifyFailureOutcome($response),
                    ];
                } catch (\Throwable $e) {
                    $attempts[] = [
                        'tier' => 1,
                        'provider' => 'vertex_grounding',
                        'location' => $location,
                        'model' => $model,
                        'http_status' => 0,
                        'outcome' => 'exception',
                    ];
                }
            }

            return null;
        }

        if (VertexAiService::hasCredentials()) {
            foreach (['gemini-3.5-flash', 'gemini-2.5-flash'] as $fallbackModel) {
                try {
                    $response = VertexAiService::generateContent(
                        $fallbackModel,
                        $payload,
                        $timeoutSeconds,
                        5,
                        null,
                        true
                    );

                    $parsed = self::parseGroundingResponse($response, 'gemini_grounding', 'ai_studio', $fallbackModel);
                    if ($parsed !== null) {
                        $attempts[] = [
                            'tier' => 1,
                            'provider' => 'gemini_grounding',
                            'location' => 'ai_studio',
                            'model' => $fallbackModel,
                            'http_status' => $response->status(),
                            'outcome' => 'success',
                        ];
                        $parsed['attempts'] = $attempts;

                        return $parsed;
                    }

                    $attempts[] = [
                        'tier' => 1,
                        'provider' => 'gemini_grounding',
                        'location' => 'ai_studio',
                        'model' => $fallbackModel,
                        'http_status' => $response->status(),
                        'outcome' => self::classifyFailureOutcome($response),
                    ];
                } catch (\Throwable) {
                    $attempts[] = [
                        'tier' => 1,
                        'provider' => 'gemini_grounding',
                        'location' => 'ai_studio',
                        'model' => $fallbackModel,
                        'http_status' => 0,
                        'outcome' => 'exception',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Erstellt die deduplizierte Vertex-Zielkaskade (Konfiguriertes Primärziel -> EU/US Regional -> Global).
     *
     * @return array<int, array{0: string, 1: string, 2: bool}>
     */
    public static function buildVertexTargets(): array
    {
        $primaryLocation = trim((string) config('services.vertex.grounding_location', 'global')) ?: 'global';
        $primaryModel = trim((string) config('services.vertex.grounding_model', 'gemini-3.5-flash')) ?: 'gemini-3.5-flash';

        $candidates = [
            [$primaryLocation, $primaryModel, true],
            ['europe-west4', 'gemini-2.5-flash', true],
            ['us-central1', 'gemini-2.5-flash', true],
            ['global', 'gemini-3.5-flash', false],
            ['global', 'gemini-3.5-flash-lite', false],
            ['global', 'gemini-3-flash-preview', true],
        ];

        $unique = [];
        $seen = [];
        foreach ($candidates as [$loc, $mod, $preserve]) {
            $normalizedModel = $preserve
                ? (string) preg_replace('#^(?:models/|google/)#i', '', trim($mod))
                : VertexAiService::resolveModel($mod);
            $key = strtolower($loc . '|' . $normalizedModel);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = [$loc, $normalizedModel, true];
        }

        return $unique;
    }

    /**
     * Extrahiert Antworttext und verifizierte Web-Quellen aus einer erfolgreichen Grounding-Response.
     *
     * @return array<string, mixed>|null
     */
    private static function parseGroundingResponse(
        Response $response,
        string $provider,
        string $location,
        string $model
    ): ?array {
        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        if (!is_array($data)) {
            return null;
        }

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $textChunks = [];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text']) && trim($part['text']) !== '') {
                    $textChunks[] = trim($part['text']);
                }
            }
        }

        $text = self::sanitizeCleanText(implode("\n\n", $textChunks), true);
        if ($text === '') {
            return null;
        }

        $sources = [];
        $sourceLabels = [];
        $chunks = $data['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [];
        if (is_array($chunks)) {
            foreach ($chunks as $chunk) {
                $title = self::sanitizeCleanText((string) ($chunk['web']['title'] ?? ''));
                $uri = trim((string) ($chunk['web']['uri'] ?? ''));
                $safeUri = self::isSafeExternalResultUrl($uri) ? $uri : '';

                if ($title !== '' || $safeUri !== '') {
                    $label = $title !== '' ? $title : (string) parse_url($safeUri, PHP_URL_HOST);
                    $sourceLabels[] = $label;
                    $sources[] = [
                        'title' => $label,
                        'url' => $safeUri,
                        'snippet' => '',
                        'source' => 'google_grounding',
                    ];
                }
            }
        }

        $uniqueLabels = array_values(array_unique(array_filter($sourceLabels)));
        $sourceHint = !empty($uniqueLabels)
            ? "\n\n(Quellen: " . implode(', ', array_slice($uniqueLabels, 0, 8)) . ')'
            : '';

        return [
            'status' => 'success',
            'provider' => $provider,
            'tier' => 1,
            'location' => $location,
            'model' => $model,
            'content' => $text . $sourceHint,
            'results' => $sources,
            'sources' => $uniqueLabels,
            'note' => 'Nutze diese detaillierten Deep Research Informationen, um der Nutzerin präzise auf Deutsch zu antworten.',
        ];
    }

    /**
     * Stufe 2: Quota-freier, SSRF-geschützter Echtzeit-Web-Fallback ohne API-Limit
     * (DuckDuckGo HTML/Lite + Wikipedia DE/EN Search/Opensearch).
     *
     * @param  array<int, array<string, mixed>>  $attempts
     * @return array<string, mixed>
     */
    private static function executeTier2RealtimeWebFallback(string $query, array $attempts = []): array
    {
        $collectedHits = [];
        $anyEndpointReachable = false;

        // 1. DuckDuckGo HTML Echtzeit-Suche
        $ddgHtml = self::fetchDuckDuckGoHtml($query);
        if ($ddgHtml['reachable']) {
            $anyEndpointReachable = true;
        }
        $attempts[] = [
            'tier' => 2,
            'provider' => 'duckduckgo_html',
            'http_status' => $ddgHtml['http_status'],
            'outcome' => !empty($ddgHtml['hits']) ? 'success' : ($ddgHtml['reachable'] ? 'empty' : 'unreachable'),
        ];
        foreach ($ddgHtml['hits'] as $hit) {
            $collectedHits[] = $hit;
        }

        // 2. DuckDuckGo Lite Fallback, falls HTML keine Treffer lieferte
        if (empty($collectedHits)) {
            $ddgLite = self::fetchDuckDuckGoLite($query);
            if ($ddgLite['reachable']) {
                $anyEndpointReachable = true;
            }
            $attempts[] = [
                'tier' => 2,
                'provider' => 'duckduckgo_lite',
                'http_status' => $ddgLite['http_status'],
                'outcome' => !empty($ddgLite['hits']) ? 'success' : ($ddgLite['reachable'] ? 'empty' : 'unreachable'),
            ];
            foreach ($ddgLite['hits'] as $hit) {
                $collectedHits[] = $hit;
            }
        }

        // 3. Wikipedia DE Echtzeit-Wissens- & Faktenabfrage (ergänzend für hohe Faktenpräzision)
        $wikiDe = self::fetchWikipediaSearch($query, 'de');
        if ($wikiDe['reachable']) {
            $anyEndpointReachable = true;
        }
        $attempts[] = [
            'tier' => 2,
            'provider' => 'wikipedia_de',
            'http_status' => $wikiDe['http_status'],
            'outcome' => !empty($wikiDe['hits']) ? 'success' : ($wikiDe['reachable'] ? 'empty' : 'unreachable'),
        ];
        foreach ($wikiDe['hits'] as $hit) {
            $collectedHits[] = $hit;
        }

        // 4. Wikipedia EN Ergänzung bei internationalen Fachbegriffen mit wenigen DE-Treffern
        if (count($collectedHits) < 2) {
            $wikiEn = self::fetchWikipediaSearch($query, 'en');
            if ($wikiEn['reachable']) {
                $anyEndpointReachable = true;
            }
            $attempts[] = [
                'tier' => 2,
                'provider' => 'wikipedia_en',
                'http_status' => $wikiEn['http_status'],
                'outcome' => !empty($wikiEn['hits']) ? 'success' : ($wikiEn['reachable'] ? 'empty' : 'unreachable'),
            ];
            foreach ($wikiEn['hits'] as $hit) {
                $collectedHits[] = $hit;
            }
        }

        $dedupedHits = self::deduplicateHits($collectedHits, 6);

        if (empty($dedupedHits) && !$anyEndpointReachable) {
            return [
                'status' => 'error',
                'provider' => 'realtime_web_fallback',
                'tier' => 2,
                'attempts' => $attempts,
                'message' => 'Websuche vorübergehend nicht erreichbar: Weder Vertex-Grounding noch die Echtzeit-Webquellen konnten kontaktiert werden.',
            ];
        }

        if (empty($dedupedHits)) {
            return [
                'status' => 'success',
                'provider' => 'realtime_web_fallback',
                'tier' => 2,
                'content' => 'Zur Suchanfrage "' . self::sanitizeCleanText($query) . '" wurden in der Echtzeit-Websuche aktuell keine spezifischen Treffer gefunden. Bitte formuliere den Suchbegriff bei Bedarf etwas allgemeiner.',
                'results' => [],
                'sources' => [],
                'attempts' => $attempts,
                'note' => 'Es lagen keine direkten Web-Treffer vor; teile dies der Nutzerin kurz und ehrlich auf Deutsch mit.',
            ];
        }

        $lines = [
            'Echtzeit-Webrecherche zu "' . self::sanitizeCleanText($query) . '":',
        ];
        $sourceLabels = [];

        foreach ($dedupedHits as $idx => $hit) {
            $num = $idx + 1;
            $title = $hit['title'];
            $snippet = $hit['snippet'];
            $url = $hit['url'];
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: $hit['source']);
            $sourceLabels[] = $title . ' (' . $host . ')';

            $entry = "{$num}. {$title}";
            if ($snippet !== '') {
                $entry .= ': ' . $snippet;
            }
            if ($url !== '') {
                $entry .= " [Quelle: {$url}]";
            }
            $lines[] = $entry;
        }

        $uniqueSources = array_values(array_unique($sourceLabels));
        $content = implode("\n", $lines) . "\n\n(Quellen: " . implode(', ', array_slice($uniqueSources, 0, 6)) . ')';

        return [
            'status' => 'success',
            'provider' => 'realtime_web_fallback',
            'tier' => 2,
            'content' => $content,
            'results' => $dedupedHits,
            'sources' => $uniqueSources,
            'attempts' => $attempts,
            'note' => 'Nutze diese aktuellen Echtzeit-Web-Ergebnisse und Quellen direkt, um der Nutzerin präzise auf Deutsch zu antworten.',
        ];
    }

    /**
     * Ruft DuckDuckGo HTML ab und extrahiert SSRF-geprüfte Titel, Snippets und Ziel-URLs.
     *
     * @return array{reachable: bool, http_status: int, hits: array<int, array{title: string, snippet: string, url: string, source: string}>}
     */
    private static function fetchDuckDuckGoHtml(string $query): array
    {
        try {
            $response = Http::asForm()
                ->withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
                ])
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                    'connect_timeout' => self::TIER2_CONNECT_TIMEOUT_SECONDS,
                    'timeout' => self::TIER2_TIMEOUT_SECONDS,
                ])
                ->post('https://html.duckduckgo.com/html/', [
                    'q' => $query,
                    'kl' => 'de-de',
                ]);

            if (!$response->successful()) {
                return ['reachable' => false, 'http_status' => $response->status(), 'hits' => []];
            }

            return [
                'reachable' => true,
                'http_status' => $response->status(),
                'hits' => self::parseDuckDuckGoHtml($response->body()),
            ];
        } catch (\Throwable) {
            return ['reachable' => false, 'http_status' => 0, 'hits' => []];
        }
    }

    /**
     * Ruft DuckDuckGo Lite als schlanke Zweitquelle ab.
     *
     * @return array{reachable: bool, http_status: int, hits: array<int, array{title: string, snippet: string, url: string, source: string}>}
     */
    private static function fetchDuckDuckGoLite(string $query): array
    {
        try {
            $response = Http::asForm()
                ->withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
                ])
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                    'connect_timeout' => self::TIER2_CONNECT_TIMEOUT_SECONDS,
                    'timeout' => self::TIER2_TIMEOUT_SECONDS,
                ])
                ->post('https://lite.duckduckgo.com/lite/', [
                    'q' => $query,
                    'kl' => 'de-de',
                ]);

            if (!$response->successful()) {
                return ['reachable' => false, 'http_status' => $response->status(), 'hits' => []];
            }

            return [
                'reachable' => true,
                'http_status' => $response->status(),
                'hits' => self::parseDuckDuckGoHtml($response->body()),
            ];
        } catch (\Throwable) {
            return ['reachable' => false, 'http_status' => 0, 'hits' => []];
        }
    }

    /**
     * Parst DuckDuckGo HTML- oder Lite-Suchergebnisse.
     *
     * @return array<int, array{title: string, snippet: string, url: string, source: string}>
     */
    public static function parseDuckDuckGoHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $hits = [];
        $linkPattern = '/<a[^>]*class="[^"]*(?:result__a|result-link)[^"]*"[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is';
        $snippetPattern = '/<(?:a|div|td|span)[^>]*class="[^"]*(?:result__snippet|result-snippet)[^"]*"[^>]*>(.*?)<\/(?:a|div|td|span)>/is';

        preg_match_all($linkPattern, $html, $linkMatches);
        preg_match_all($snippetPattern, $html, $snippetMatches);

        $hrefs = $linkMatches[1] ?? [];
        $titles = $linkMatches[2] ?? [];
        $snippets = $snippetMatches[1] ?? [];

        foreach ($hrefs as $index => $rawHref) {
            $url = self::unwrapDuckDuckGoUrl(html_entity_decode((string) $rawHref, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!self::isSafeExternalResultUrl($url)) {
                continue;
            }

            // Überspringe DuckDuckGo-Werbeweiterleitungen (duckduckgo.com/y.js)
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (str_contains($host, 'duckduckgo.com')) {
                continue;
            }

            $title = self::sanitizeCleanText((string) ($titles[$index] ?? ''));
            if ($title === '') {
                continue;
            }

            $snippet = self::sanitizeCleanText((string) ($snippets[$index] ?? ''));

            $hits[] = [
                'title' => $title,
                'snippet' => $snippet,
                'url' => $url,
                'source' => 'duckduckgo',
            ];

            if (count($hits) >= 6) {
                break;
            }
        }

        return $hits;
    }

    /**
     * Ruft Wikipedia Search / Opensearch API (DE oder EN) mit 5s Timeout ab.
     *
     * @return array{reachable: bool, http_status: int, hits: array<int, array{title: string, snippet: string, url: string, source: string}>}
     */
    private static function fetchWikipediaSearch(string $query, string $lang = 'de'): array
    {
        $safeLang = in_array($lang, ['de', 'en'], true) ? $lang : 'de';
        $endpoint = "https://{$safeLang}.wikipedia.org/w/api.php";

        try {
            $response = Http::withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => 'MeinSeelenfunkeBot/1.0 (https://mein-seelenfunke.de; System-Research)',
                    'Accept' => 'application/json',
                ])
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                    'connect_timeout' => self::TIER2_CONNECT_TIMEOUT_SECONDS,
                    'timeout' => self::TIER2_TIMEOUT_SECONDS,
                ])
                ->get($endpoint, [
                    'action' => 'query',
                    'list' => 'search',
                    'srsearch' => $query,
                    'srlimit' => 3,
                    'utf8' => 1,
                    'format' => 'json',
                ]);

            if (!$response->successful()) {
                return ['reachable' => false, 'http_status' => $response->status(), 'hits' => []];
            }

            $json = $response->json();
            $hits = [];

            // Format A: MediaWiki query.search
            if (is_array($json) && isset($json['query']['search']) && is_array($json['query']['search'])) {
                foreach ($json['query']['search'] as $item) {
                    $title = self::sanitizeCleanText((string) ($item['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $snippet = self::sanitizeCleanText((string) ($item['snippet'] ?? ''));
                    $wikiUrl = "https://{$safeLang}.wikipedia.org/wiki/" . rawurlencode(str_replace(' ', '_', $title));
                    if (!self::isSafeExternalResultUrl($wikiUrl)) {
                        continue;
                    }
                    $hits[] = [
                        'title' => $title . ' (Wikipedia ' . strtoupper($safeLang) . ')',
                        'snippet' => $snippet,
                        'url' => $wikiUrl,
                        'source' => 'wikipedia_' . $safeLang,
                    ];
                }
            } elseif (is_array($json) && isset($json[1], $json[3]) && is_array($json[1]) && is_array($json[3])) {
                // Format B: OpenSearch [query, [titles], [descriptions], [urls]]
                $titles = $json[1];
                $descriptions = is_array($json[2] ?? null) ? $json[2] : [];
                $urls = $json[3];
                foreach ($titles as $idx => $rawTitle) {
                    $title = self::sanitizeCleanText((string) $rawTitle);
                    $wikiUrl = trim((string) ($urls[$idx] ?? ''));
                    if ($title === '' || !self::isSafeExternalResultUrl($wikiUrl)) {
                        continue;
                    }
                    $hits[] = [
                        'title' => $title . ' (Wikipedia ' . strtoupper($safeLang) . ')',
                        'snippet' => self::sanitizeCleanText((string) ($descriptions[$idx] ?? '')),
                        'url' => $wikiUrl,
                        'source' => 'wikipedia_' . $safeLang,
                    ];
                }
            }

            return [
                'reachable' => true,
                'http_status' => $response->status(),
                'hits' => $hits,
            ];
        } catch (\Throwable) {
            return ['reachable' => false, 'http_status' => 0, 'hits' => []];
        }
    }

    /**
     * Entpackt DuckDuckGo-Redirect-Links (//duckduckgo.com/l/?uddg=https%3A%2F%2F...).
     */
    public static function unwrapDuckDuckGoUrl(string $rawUrl): string
    {
        $trimmed = trim($rawUrl);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '//')) {
            $trimmed = 'https:' . $trimmed;
        }

        $parts = @parse_url($trimmed);
        if (!is_array($parts)) {
            return '';
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (str_contains($host, 'duckduckgo.com') && !empty($parts['query'])) {
            parse_str((string) $parts['query'], $queryParams);
            if (!empty($queryParams['uddg']) && is_string($queryParams['uddg'])) {
                return trim($queryParams['uddg']);
            }
        }

        return $trimmed;
    }

    /**
     * Strikte SSRF-Prüfung für alle zurückgegebenen und weiterverarbeiteten Web-URLs:
     * Erlaubt ausschließlich öffentliche http/https-Ziele und blockiert Loopback-, Private-LAN-,
     * Link-Local-/Cloud-Metadata-Adressen sowie interne Hostnamen.
     */
    public static function isSafeExternalResultUrl(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '' || strlen($trimmed) > 2048) {
            return false;
        }

        if (!preg_match('/^https?:\/\//i', $trimmed)) {
            return false;
        }

        $parts = @parse_url($trimmed);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim((string) $parts['host'], '[]'));
        if ($host === '') {
            return false;
        }

        $blockedExact = [
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
            '169.254.169.254',
            'metadata.google.internal',
            'metadata',
        ];
        if (in_array($host, $blockedExact, true)) {
            return false;
        }

        foreach (['.localhost', '.local', '.internal', '.test', '.invalid', '.lan', '.home.arpa'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $publicIp = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            return $publicIp !== false;
        }

        if (!str_contains($host, '.')) {
            return false;
        }

        return true;
    }

    /**
     * Dedupliziert Treffer anhand der kanonischen URL.
     *
     * @param  array<int, array{title: string, snippet: string, url: string, source: string}>  $hits
     * @return array<int, array{title: string, snippet: string, url: string, source: string}>
     */
    private static function deduplicateHits(array $hits, int $limit = 6): array
    {
        $unique = [];
        $seen = [];

        foreach ($hits as $hit) {
            $urlKey = strtolower(rtrim($hit['url'], '/'));
            if ($urlKey === '' || isset($seen[$urlKey])) {
                continue;
            }
            $seen[$urlKey] = true;
            $unique[] = $hit;

            if (count($unique) >= $limit) {
                break;
            }
        }

        return $unique;
    }

    /**
     * Entfernt HTML-Tags, dekodiert Entities und ersetzt KI-Gedankenstriche konsequent.
     */
    private static function sanitizeCleanText(string $raw, bool $preserveLineBreaks = false): string
    {
        if ($raw === '') {
            return '';
        }

        $decoded = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $withoutDashes = str_replace([" \u{2013} ", " \u{2014} "], ', ', $decoded);

        if ($preserveLineBreaks) {
            $normalized = preg_replace("/[ \t]+/u", ' ', $withoutDashes) ?? $withoutDashes;
            $normalized = preg_replace("/\n{3,}/u", "\n\n", $normalized) ?? $normalized;

            return trim($normalized);
        }

        return trim(preg_replace('/\s+/u', ' ', $withoutDashes) ?? $withoutDashes);
    }

    private static function classifyFailureOutcome(Response $response): string
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 429 || stripos($body, 'RESOURCE_EXHAUSTED') !== false || stripos($body, 'Quota exceeded') !== false) {
            return 'quota_exhausted';
        }

        if ($response->successful()) {
            return 'empty_candidates';
        }

        return 'http_' . $status;
    }
}

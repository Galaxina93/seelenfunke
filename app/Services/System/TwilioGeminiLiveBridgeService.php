<?php

namespace App\Services\System;

use Laravel\Reverb\Servers\Reverb\Connection as ReverbWsConnection;
use Laravel\Reverb\Servers\Reverb\Http\Connection as ReverbHttpConnection;
use Laravel\Reverb\Servers\Reverb\Http\Response as ReverbHttpResponse;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

/**
 * Bidirektionale Echtzeit-Audio-Brücke (ReactPHP) zwischen Twilio Media Streams (/twilio-stream)
 * und Google Gemini 3.1 Flash Live (Aoede). Läuft nativ im Reverb-WebSocket-Worker (ws.mein-seelenfunke.de).
 */
class TwilioGeminiLiveBridgeService
{
    public const BRIDGE_VERSION = 'v2.0-reverb-live-secretary';

    private static string $startedAtIso = '';
    private static int $activeCalls = 0;
    private static int $totalCalls = 0;
    private static int $totalErrors = 0;
    private static ?array $lastIssue = null;
    private static ?int $cachedEnvMtime = null;
    private static ?string $cachedEnvGeminiKey = null;

    public static function bootMetrics(): void
    {
        if (self::$startedAtIso === '') {
            self::$startedAtIso = gmdate('c');
        }
    }

    public static function handleHealthHttp(RequestInterface $request, ReverbHttpConnection $connection): ReverbHttpResponse
    {
        self::bootMetrics();
        $key = self::resolveLiveGeminiApiKey();

        return new ReverbHttpResponse((object) [
            'status' => 'ok',
            'bridge_version' => self::BRIDGE_VERSION,
            'started_at' => self::$startedAtIso,
            'gemini_key_configured' => $key !== '',
            'gemini_key_prefix' => $key !== '' ? substr($key, 0, 8) . '...' : null,
            'active_twilio_calls' => self::$activeCalls,
            'total_twilio_calls' => self::$totalCalls,
            'total_errors' => self::$totalErrors,
            'memory_mb' => round(memory_get_usage(true) / 1048576, 1),
            'last_issue' => self::$lastIssue,
        ]);
    }

    /**
     * Liest den aktuellen GEMINI_API_KEY und erkennt Änderungen in der .env im laufenden Betrieb automatisch.
     */
    public static function resolveLiveGeminiApiKey(): string
    {
        try {
            $envPath = base_path('.env');
            if (is_file($envPath)) {
                clearstatcache(true, $envPath);
                $mtime = @filemtime($envPath) ?: 0;
                if ($mtime > 0 && $mtime !== self::$cachedEnvMtime) {
                    self::$cachedEnvMtime = $mtime;
                    $raw = (string) @file_get_contents($envPath);
                    if (preg_match('/^GEMINI_API_KEY=(.+)$/m', $raw, $m)) {
                        $parsed = trim($m[1], " \t\n\r\0\x0B\"'");
                        if ($parsed !== '') {
                            self::$cachedEnvGeminiKey = $parsed;
                        }
                    }
                }
                if (!empty(self::$cachedEnvGeminiKey)) {
                    return self::$cachedEnvGeminiKey;
                }
            }
        } catch (\Throwable $ignore) {
        }

        return trim((string) (config('services.gemini.key') ?: env('GEMINI_API_KEY', '')));
    }

    public static function buildInboundSecretarySystemPrompt(array $callContext): string
    {
        $ownerName = trim((string) ($callContext['agent_name'] ?? 'Alina Steinhauer')) ?: 'Alina Steinhauer';
        $greetName = trim((string) ($callContext['greeting_name'] ?? ($callContext['contact_name'] ?? 'du'))) ?: 'du';
        $contactDisplay = trim((string) ($callContext['contact_name'] ?? $greetName));
        $relation = trim((string) ($callContext['relation_type'] ?? ''));
        $instructions = trim((string) ($callContext['system_instructions'] ?? ''));
        $learnedFacts = trim((string) ($callContext['ai_learned_facts'] ?? ''));
        $calendarSlots = trim((string) ($callContext['calendar_events'] ?? ''));

        $formalKeywords = ['arzt', 'praxis', 'steuer', 'kanzlei', 'behörde', 'bank', 'versicherung', 'lieferant', 'geschäft'];
        $useFormalSie = false;
        $relLower = mb_strtolower($relation . ' ' . $contactDisplay);
        foreach ($formalKeywords as $kw) {
            if (str_contains($relLower, $kw)) {
                $useFormalSie = true;
                break;
            }
        }
        $addressRule = $useFormalSie
            ? "Sprich höflich, warm und professionell per 'Sie' (wechsle nur auf 'Du', falls der Anrufer dich von sich aus duzt)."
            : "Sprich herzlich, vertraut und natürlich per 'Du' und mit dem Rufnamen '{$greetName}'.";

        $lines = [
            "ROLLE & IDENTITÄT (HÖCHSTE PRIORITÄT):",
            "- Du sprichst zu 100 Prozent ausschließlich natürliches, lebendiges Deutsch.",
            "- Dein Name ist Funkira. Du bist die herzliche, kluge und aufmerksame persönliche Sekretärin von {$ownerName}.",
            "- Du telefonierst gerade LIVE mit: {$contactDisplay} (Anrede: {$greetName}).",
            "- {$addressRule}",
            $relation !== '' ? "- Beziehung zu {$ownerName}: {$relation}." : null,
            $instructions !== '' ? "- Wichtiger Kontakt-Kontext: {$instructions}" : null,
            $learnedFacts !== '' ? "- Frühere Notizen zum Kontakt: {$learnedFacts}" : null,
            $calendarSlots !== '' ? "- Zeitfenster im Kalender von {$ownerName} (nur zur Orientierung frei/belegt, nenne NIEMALS private Termindetails): {$calendarSlots}" : null,
            "",
            "LIVE-GESPRÄCHSFÜHRUNG ALS ECHTE SEKRETÄRIN (KEIN ANRUFBEANTWORTER!):",
            "- Sage NIEMALS Sätze wie 'Bitte sprechen Sie nach dem Signalton' oder 'Ich starte eine Aufnahme'. Du führst ein echtes, direktes Dialog-Telefonat wie eine menschliche Sekretärin!",
            "- Halte jeden einzelnen Sprechbeitrag angenehm kurz, warm und telefonisch natürlich (1 bis maximal 2 kurze Sätze, keine Aufzählungen vorlesen).",
            "- Reagiere direkt auf das, was {$greetName} sagt oder fragt, und kläre aktiv alle wichtigen Rahmenbedingungen für {$ownerName}, damit {$ownerName} später sofort im Bilde ist:",
            "  1. Worum geht es konkret?",
            "  2. Welche Rahmenbedingungen gelten (z. B. genaues Datum, Uhrzeit, Zeitraum, Ort, Frist oder wichtige Details)? Wenn etwas davon noch unklar ist, hake freundlich und natürlich mit EINER gezielten Frage nach.",
            "  3. Was genau soll {$ownerName} tun (z. B. bis wann zurückrufen, etwas bestätigen, entscheiden oder mitbringen)?",
            "- Stelle immer nur EINE kurze Rückfrage auf einmal, damit sich das Gespräch locker und menschlich anfühlt.",
            "",
            "GESPRÄCHSABSCHLUSS:",
            "- Sobald das Anliegen, die Rahmenbedingungen und die konkrete Aufgabe für {$ownerName} klar sind (oder wenn {$greetName} keine weiteren Punkte hat), fasse das Vereinbarte in einem kurzen, herzlichen Satz zusammen, verabschiede dich lieb und rufe direkt im selben Zug das Tool 'end_call' auf.",
            "",
            "DATENSCHUTZ-GRENZE:",
            "- Gib am Telefon niemals interne Geschäftsdaten, Kunden, Bestellungen, Passwörter oder Finanzzahlen heraus.",
        ];

        return implode("\n", array_values(array_filter($lines, fn ($l) => $l !== null)));
    }

    public static function buildInboundSecretaryKickoffPrompt(array $callContext): string
    {
        $ownerName = trim((string) ($callContext['agent_name'] ?? 'Alina')) ?: 'Alina';
        $ownerFirst = explode(' ', $ownerName)[0] ?: 'Alina';
        $greetName = trim((string) ($callContext['greeting_name'] ?? 'du')) ?: 'du';

        return "[ANRUF LIVE VERBUNDEN] {$greetName} ist jetzt live am Telefon. "
            . "Begrüße {$greetName} jetzt sofort herzlich, natürlich und kurz auf Deutsch als Funkira, {$ownerFirst}s persönliche Assistentin "
            . "(nur 1 kurzer Satz, z. B.: 'Hallo {$greetName}, hier ist Funkira, {$ownerFirst}s Assistentin! {$ownerFirst} ist gerade kurz eingebunden, was darf ich direkt mit dir klären oder für sie aufnehmen?') "
            . "und führe danach ein lebendiges Gespräch.";
    }

    public static function buildOutboundSystemPrompt(array $callContext): string
    {
        $ownerName = trim((string) ($callContext['agent_name'] ?? 'Alina Steinhauer')) ?: 'Alina Steinhauer';
        $contactName = trim((string) ($callContext['contact_name'] ?? 'Unbekannt')) ?: 'Unbekannt';
        $objective = trim((string) ($callContext['objective'] ?? 'Führe ein freundliches, zielgerichtetes Gespräch.'));
        $instructions = trim((string) ($callContext['system_instructions'] ?? ''));
        $facts = trim((string) ($callContext['ai_learned_facts'] ?? ''));
        $calendar = trim((string) ($callContext['calendar_events'] ?? 'Keine anstehenden Termine.'));

        return implode("\n", [
            "Du bist die persönliche Telefon-Sekretärin von {$ownerName}.",
            "Dein Name ist Funkira. Du sprichst ausschließlich natürliches, freundliches und kurzes Deutsch.",
            "Du führst ein ausgehendes Telefonat mit: {$contactName}.",
            "Dein Ziel für dieses Telefonat: {$objective}",
            $instructions !== '' ? "Kontext zur Person: {$instructions}" : '',
            $facts !== '' ? "Bekannte Notizen: {$facts}" : '',
            "Kalender-Verfügbarkeit von {$ownerName}: {$calendar}",
            "GESPRÄCHSERÖFFNUNG: Warte kurz ab, bis {$contactName} sich am Telefon meldet, stelle dich dann freundlich als Funkira aus dem Büro von {$ownerName} vor und kläre das Anliegen samt aller Rahmenbedingungen kurz und strukturiert.",
            "Sobald alles geklärt und verabschiedet ist, rufe das Tool 'end_call' auf.",
        ]);
    }

    public function handleTwilioWebSocket(RequestInterface $request, ReverbWsConnection $twilioConn): void
    {
        self::bootMetrics();
        self::$activeCalls++;
        self::$totalCalls++;

        $loop = Loop::get();
        $twilioConn->withMaxMessageSize(1024 * 1024);

        $streamSid = '';
        $callSid = '';
        $callContext = [];
        $callStartTime = microtime(true);
        $callTranscript = [];
        $currentAiTurn = '';
        $currentUserTurn = '';
        $shouldEndCall = false;
        $isCleanedUp = false;
        $callSaved = false;

        /** @var ConnectionInterface|null $googleStream */
        $googleStream = null;
        $isGeminiSetupComplete = false;
        $kickoffSentAtMs = 0.0;
        $mulawBuffer = '';
        $prevDecodeSample = 0;
        $pcm24Carry = '';
        /** @var TimerInterface|null $pingTimer */
        $pingTimer = null;
        /** @var TimerInterface|null $hangupSafetyTimer */
        $hangupSafetyTimer = null;

        $sendTwilioJson = function (array $payload) use ($twilioConn, &$isCleanedUp): void {
            if ($isCleanedUp) {
                return;
            }
            try {
                $twilioConn->send((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $ignore) {
            }
        };

        $sendGoogleFrame = function (string $payload, int $opcode = Frame::OP_TEXT) use (&$googleStream): void {
            if ($googleStream === null || !$googleStream->isWritable()) {
                return;
            }
            try {
                $frame = new Frame($payload, true, $opcode);
                $frame->maskPayload();
                $googleStream->write($frame->getContents());
            } catch (\Throwable $ignore) {
            }
        };

        $saveCallLogOnce = function () use (
            &$callSaved,
            &$currentUserTurn,
            &$currentAiTurn,
            &$callTranscript,
            &$callStartTime,
            &$callSid,
            &$streamSid,
            &$callContext
        ): void {
            if ($callSaved) {
                return;
            }
            $callSaved = true;

            if (trim($currentUserTurn) !== '') {
                $callTranscript[] = 'Anrufer: ' . trim($currentUserTurn);
                $currentUserTurn = '';
            }
            if (trim($currentAiTurn) !== '') {
                $callTranscript[] = 'Funkira: ' . trim($currentAiTurn);
                $currentAiTurn = '';
            }

            if (empty($callContext) && empty($callTranscript)) {
                return;
            }

            $durationSec = max(0, (int) round(microtime(true) - $callStartTime));
            $payload = [
                'twilio_sid' => $callSid !== '' ? $callSid : $streamSid,
                'planned_call_id' => (string) ($callContext['planned_call_id'] ?? ''),
                'contact_name' => (string) ($callContext['contact_name'] ?? 'Kontakt'),
                'objective' => (string) ($callContext['objective'] ?? ''),
                'transcript' => $callTranscript,
                'duration_seconds' => $durationSec,
            ];

            self::dispatchCallLogNonBlocking($payload);
        };

        $cleanupAll = function (bool $closeTwilio = false) use (
            &$isCleanedUp,
            &$googleStream,
            &$pingTimer,
            &$hangupSafetyTimer,
            $loop,
            $twilioConn,
            $saveCallLogOnce
        ): void {
            if ($isCleanedUp) {
                return;
            }
            $isCleanedUp = true;
            self::$activeCalls = max(0, self::$activeCalls - 1);

            if ($pingTimer !== null) {
                $loop->cancelTimer($pingTimer);
                $pingTimer = null;
            }
            if ($hangupSafetyTimer !== null) {
                $loop->cancelTimer($hangupSafetyTimer);
                $hangupSafetyTimer = null;
            }
            if ($googleStream !== null) {
                try {
                    $closeFrame = new Frame(pack('n', 1000), true, Frame::OP_CLOSE);
                    $closeFrame->maskPayload();
                    $googleStream->write($closeFrame->getContents());
                    $googleStream->close();
                } catch (\Throwable $ignore) {
                }
                $googleStream = null;
            }

            $saveCallLogOnce();

            if ($closeTwilio) {
                try {
                    $twilioConn->close();
                } catch (\Throwable $ignore) {
                }
            }
        };

        $connectGoogleLive = function () use (
            $loop,
            &$callContext,
            &$streamSid,
            &$googleStream,
            &$isGeminiSetupComplete,
            &$kickoffSentAtMs,
            &$pcm24Carry,
            &$currentAiTurn,
            &$currentUserTurn,
            &$callTranscript,
            &$shouldEndCall,
            &$pingTimer,
            &$hangupSafetyTimer,
            &$isCleanedUp,
            $sendTwilioJson,
            $sendGoogleFrame,
            $cleanupAll
        ): void {
            $liveEndpoint = \App\Services\AI\VertexAiService::resolveLiveWebSocketEndpoint();
            $isVertexLive = (bool) ($liveEndpoint['is_vertex'] ?? false);
            $apiKey = $isVertexLive ? (string) ($liveEndpoint['token'] ?? '') : self::resolveLiveGeminiApiKey();

            if ($apiKey === '') {
                self::recordIssue('missing_gemini_api_key', 'Weder Vertex AI Service Account noch GEMINI_API_KEY für Twilio-Live konfiguriert.');
                $cleanupAll(true);
                return;
            }

            $wsHost = $isVertexLive ? (string) $liveEndpoint['host'] : 'generativelanguage.googleapis.com';
            $wsPath = $isVertexLive
                ? (string) $liveEndpoint['path']
                : ('/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=' . rawurlencode($apiKey));
            $liveModelName = $isVertexLive ? (string) $liveEndpoint['model'] : 'models/gemini-3.1-flash-live-preview';

            $connector = new Connector([
                'timeout' => 8.0,
                'happy_eyeballs' => true,
                'tls' => [
                    'SNI_enabled' => true,
                    'peer_name' => $wsHost,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ], $loop);

            $connector->connect("tls://{$wsHost}:443")->then(
                function (ConnectionInterface $stream) use (
                    $apiKey,
                    $isVertexLive,
                    $wsHost,
                    $wsPath,
                    $liveModelName,
                    $loop,
                    &$callContext,
                    &$streamSid,
                    &$googleStream,
                    &$isGeminiSetupComplete,
                    &$kickoffSentAtMs,
                    &$pcm24Carry,
                    &$currentAiTurn,
                    &$currentUserTurn,
                    &$callTranscript,
                    &$shouldEndCall,
                    &$pingTimer,
                    &$hangupSafetyTimer,
                    &$isCleanedUp,
                    $sendTwilioJson,
                    $sendGoogleFrame,
                    $cleanupAll
                ): void {
                    if ($isCleanedUp) {
                        $stream->close();
                        return;
                    }

                    $googleStream = $stream;
                    $handshakeDone = false;
                    $handshakeBuf = '';
                    $originUrl = rtrim((string) config('app.url', 'https://stage.mein-seelenfunke.de'), '/');

                    $onGooglePayload = function (MessageInterface $msgObj) use (
                        $loop,
                        &$callContext,
                        &$streamSid,
                        &$isGeminiSetupComplete,
                        &$kickoffSentAtMs,
                        &$pcm24Carry,
                        &$currentAiTurn,
                        &$currentUserTurn,
                        &$callTranscript,
                        &$shouldEndCall,
                        &$hangupSafetyTimer,
                        $sendTwilioJson,
                        $sendGoogleFrame,
                        $cleanupAll
                    ): void {
                        $raw = (string) $msgObj;
                        $resp = json_decode($raw, true);
                        if (!is_array($resp)) {
                            return;
                        }

                        if (array_key_exists('setupComplete', $resp)) {
                            $isGeminiSetupComplete = true;
                            if (($callContext['call_mode'] ?? '') === 'inbound_secretary') {
                                $kickoffSentAtMs = microtime(true) * 1000.0;
                                $kickoff = [
                                    'realtimeInput' => [
                                        'text' => self::buildInboundSecretaryKickoffPrompt($callContext),
                                    ],
                                ];
                                $sendGoogleFrame((string) json_encode($kickoff, JSON_UNESCAPED_UNICODE));
                            }
                            return;
                        }

                        if (!empty($resp['toolCall']['functionCalls']) && is_array($resp['toolCall']['functionCalls'])) {
                            foreach ($resp['toolCall']['functionCalls'] as $fnCall) {
                                if (($fnCall['name'] ?? '') === 'end_call') {
                                    $shouldEndCall = true;
                                    $toolReply = [
                                        'toolResponse' => [
                                            'functionResponses' => [[
                                                'id' => $fnCall['id'] ?? 'end_call_1',
                                                'name' => 'end_call',
                                                'response' => ['success' => true],
                                            ]],
                                        ],
                                    ];
                                    $sendGoogleFrame((string) json_encode($toolReply, JSON_UNESCAPED_UNICODE));
                                }
                            }
                        }

                        $serverContent = $resp['serverContent'] ?? null;
                        if (!is_array($serverContent)) {
                            return;
                        }

                        if (!empty($serverContent['outputTranscription']['text'])) {
                            $currentAiTurn .= (string) $serverContent['outputTranscription']['text'];
                        }

                        if (!empty($serverContent['inputTranscription']['text'])) {
                            $currentUserTurn .= (string) $serverContent['inputTranscription']['text'];
                        }

                        if (!empty($serverContent['interrupted'])) {
                            if ($streamSid !== '') {
                                $sendTwilioJson([
                                    'event' => 'clear',
                                    'streamSid' => $streamSid,
                                ]);
                            }
                            $pcm24Carry = '';
                            if (trim($currentAiTurn) !== '') {
                                $callTranscript[] = 'Funkira: ' . trim($currentAiTurn) . ' (unterbrochen)';
                                $currentAiTurn = '';
                            }
                        }

                        if (!empty($serverContent['modelTurn']['parts']) && is_array($serverContent['modelTurn']['parts'])) {
                            foreach ($serverContent['modelTurn']['parts'] as $part) {
                                $inline = $part['inlineData'] ?? null;
                                if (is_array($inline)
                                    && isset($inline['mimeType'], $inline['data'])
                                    && str_starts_with((string) $inline['mimeType'], 'audio/pcm')
                                ) {
                                    $pcm24 = base64_decode((string) $inline['data'], true);
                                    if (is_string($pcm24) && $pcm24 !== '' && $streamSid !== '') {
                                        $mulawOut = AudioMulawCodec::pcm24kToMulaw8k($pcm24, $pcm24Carry);
                                        $totalOut = strlen($mulawOut);
                                        for ($off = 0; $off < $totalOut; $off += 1600) {
                                            $slice = substr($mulawOut, $off, 1600);
                                            $sendTwilioJson([
                                                'event' => 'media',
                                                'streamSid' => $streamSid,
                                                'media' => ['payload' => base64_encode($slice)],
                                            ]);
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($serverContent['turnComplete'])) {
                            if (trim($currentUserTurn) !== '') {
                                $callTranscript[] = 'Anrufer: ' . trim($currentUserTurn);
                                $currentUserTurn = '';
                            }
                            if (trim($currentAiTurn) !== '') {
                                $callTranscript[] = 'Funkira: ' . trim($currentAiTurn);
                                $currentAiTurn = '';
                            }

                            if ($shouldEndCall && $streamSid !== '') {
                                $sendTwilioJson([
                                    'event' => 'mark',
                                    'streamSid' => $streamSid,
                                    'mark' => ['name' => 'end_of_call'],
                                ]);
                                if ($hangupSafetyTimer === null) {
                                    $hangupSafetyTimer = $loop->addTimer(10.0, function () use ($cleanupAll): void {
                                        $cleanupAll(true);
                                    });
                                }
                            }
                        }
                    };

                    $googleFrameBuffer = new MessageBuffer(
                        new CloseFrameChecker(),
                        $onGooglePayload,
                        function (FrameInterface $ctrl) use ($sendGoogleFrame, $cleanupAll): void {
                            $op = $ctrl->getOpcode();
                            if ($op === Frame::OP_PING) {
                                $sendGoogleFrame($ctrl->getPayload(), Frame::OP_PONG);
                            } elseif ($op === Frame::OP_CLOSE) {
                                $pl = $ctrl->getPayload();
                                $code = strlen($pl) >= 2 ? (unpack('n', substr($pl, 0, 2))[1] ?? 1000) : 1000;
                                $reason = strlen($pl) > 2 ? substr($pl, 2) : '';
                                if ($code !== 1000 && $code !== 1001) {
                                    self::recordIssue("google_ws_close_{$code}", "Google Live geschlossen ({$code}): {$reason}");
                                }
                                $cleanupAll(false);
                            }
                        },
                        false,
                        null,
                        16 * 1024 * 1024,
                        16 * 1024 * 1024
                    );

                    $stream->on('data', function (string $chunk) use (
                        &$handshakeDone,
                        &$handshakeBuf,
                        &$callContext,
                        $googleFrameBuffer,
                        $sendGoogleFrame,
                        $cleanupAll
                    ): void {
                        if (!$handshakeDone) {
                            $handshakeBuf .= $chunk;
                            $sepPos = strpos($handshakeBuf, "\r\n\r\n");
                            if ($sepPos === false) {
                                if (strlen($handshakeBuf) > 16384) {
                                    self::recordIssue('google_ws_handshake_overflow', 'Google Handshake Header zu groß.');
                                    $cleanupAll(true);
                                }
                                return;
                            }

                            $headerPart = substr($handshakeBuf, 0, $sepPos);
                            $remainder = substr($handshakeBuf, $sepPos + 4);
                            $handshakeBuf = '';

                            if (!preg_match('#^HTTP/1\.[01]\s+101\b#i', $headerPart)) {
                                $firstLine = strtok($headerPart, "\r\n") ?: 'HTTP Error';
                                self::recordIssue('google_ws_upgrade_rejected', "Google Live Upgrade abgelehnt: {$firstLine}");
                                $cleanupAll(true);
                                return;
                            }

                            $handshakeDone = true;

                            $isInbound = ($callContext['call_mode'] ?? '') === 'inbound_secretary';
                            $systemPrompt = $isInbound
                                ? self::buildInboundSecretarySystemPrompt($callContext)
                                : self::buildOutboundSystemPrompt($callContext);

                            $setupPayload = [
                                'setup' => [
                                    'model' => $liveModelName,
                                    'systemInstruction' => [
                                        'parts' => [['text' => $systemPrompt]],
                                    ],
                                    'tools' => [[
                                        'functionDeclarations' => [[
                                            'name' => 'end_call',
                                            'description' => 'Beendet das Telefonat sauber, nachdem alle Rahmenbedingungen geklärt wurden und du dich freundlich verabschiedet hast.',
                                        ]],
                                    ]],
                                    'generationConfig' => [
                                        'responseModalities' => ['AUDIO'],
                                        'speechConfig' => [
                                            'voiceConfig' => [
                                                'prebuiltVoiceConfig' => [
                                                    'voiceName' => 'Aoede',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'inputAudioTranscription' => (object) [],
                                    'outputAudioTranscription' => (object) [],
                                ],
                            ];

                            $sendGoogleFrame((string) json_encode($setupPayload, JSON_UNESCAPED_UNICODE));

                            if ($remainder !== '') {
                                $googleFrameBuffer->onData($remainder);
                            }
                            return;
                        }

                        try {
                            $googleFrameBuffer->onData($chunk);
                        } catch (\Throwable $bufErr) {
                            self::recordIssue('google_ws_frame_error', $bufErr->getMessage());
                        }
                    });

                    $stream->on('error', function (\Throwable $err) use ($cleanupAll): void {
                        self::recordIssue('google_ws_stream_error', $err->getMessage());
                        $cleanupAll(false);
                    });

                    $stream->on('close', function () use ($cleanupAll): void {
                        $cleanupAll(false);
                    });

                    $pingTimer = $loop->addPeriodicTimer(18.0, function () use ($sendGoogleFrame): void {
                        $sendGoogleFrame('keepalive', Frame::OP_PING);
                    });

                    $wsKey = base64_encode(random_bytes(16));
                    $authHeader = $isVertexLive ? "Authorization: Bearer {$apiKey}\r\n" : '';
                    $upgradeReq = "GET {$wsPath} HTTP/1.1\r\n"
                        . "Host: {$wsHost}\r\n"
                        . "Upgrade: websocket\r\n"
                        . "Connection: Upgrade\r\n"
                        . "Sec-WebSocket-Key: {$wsKey}\r\n"
                        . "Sec-WebSocket-Version: 13\r\n"
                        . $authHeader
                        . "Origin: {$originUrl}\r\n"
                        . "Referer: {$originUrl}\r\n\r\n";

                    $stream->write($upgradeReq);
                },
                function (\Throwable $connErr) use ($cleanupAll): void {
                    self::recordIssue('google_ws_connect_failed', $connErr->getMessage());
                    $cleanupAll(true);
                }
            );
        };

        $twilioConn->onMessage(function ($rawMsg) use (
            &$streamSid,
            &$callSid,
            &$callContext,
            &$callStartTime,
            &$isGeminiSetupComplete,
            &$kickoffSentAtMs,
            &$mulawBuffer,
            &$prevDecodeSample,
            $connectGoogleLive,
            $sendGoogleFrame,
            $cleanupAll
        ): void {
            $json = json_decode((string) $rawMsg, true);
            if (!is_array($json)) {
                return;
            }

            $event = (string) ($json['event'] ?? '');

            if ($event === 'start') {
                $start = is_array($json['start'] ?? null) ? $json['start'] : [];
                $streamSid = (string) ($start['streamSid'] ?? '');
                $callSid = (string) ($start['callSid'] ?? $streamSid);
                $callContext = is_array($start['customParameters'] ?? null) ? $start['customParameters'] : [];
                $callStartTime = microtime(true);
                $connectGoogleLive();
                return;
            }

            if ($event === 'media') {
                if (!$isGeminiSetupComplete) {
                    return;
                }
                // Kurze Dämpfung der ersten 500ms Leitungs-Umschaltknackser während Funkira das erste Wort spricht
                if ($kickoffSentAtMs > 0.0 && ((microtime(true) * 1000.0) - $kickoffSentAtMs) < 500.0) {
                    return;
                }

                $b64 = (string) ($json['media']['payload'] ?? '');
                if ($b64 === '') {
                    return;
                }
                $decoded = base64_decode($b64, true);
                if (!is_string($decoded) || $decoded === '') {
                    return;
                }

                $mulawBuffer .= $decoded;
                if (strlen($mulawBuffer) >= 480) {
                    $pcm16k = AudioMulawCodec::mulaw8kToPcm16k($mulawBuffer, $prevDecodeSample);
                    $mulawBuffer = '';
                    $audioFrame = [
                        'realtimeInput' => [
                            'audio' => [
                                'mimeType' => 'audio/pcm;rate=16000',
                                'data' => base64_encode($pcm16k),
                            ],
                        ],
                    ];
                    $sendGoogleFrame((string) json_encode($audioFrame));
                }
                return;
            }

            if ($event === 'mark' && ($json['mark']['name'] ?? '') === 'end_of_call') {
                $cleanupAll(true);
                return;
            }

            if ($event === 'stop') {
                $cleanupAll(false);
            }
        });

        $twilioConn->onClose(function () use ($cleanupAll): void {
            $cleanupAll(false);
        });

        $twilioConn->openBuffer();
    }

    private static function recordIssue(string $code, string $message): void
    {
        self::$totalErrors++;
        $clean = preg_replace('/AIza[A-Za-z0-9\-_]{12,}/', 'AIza[REDACTED]', $message) ?? $message;
        self::$lastIssue = [
            'timestamp' => gmdate('c'),
            'code' => $code,
            'message' => mb_substr($clean, 0, 260),
        ];
    }

    /**
     * Überträgt das fertige Gesprächsprotokoll asynchron (0ms Blockzeit für den Event-Loop) an /api/twilio/call-log.
     */
    private static function dispatchCallLogNonBlocking(array $payload): void
    {
        $appUrl = rtrim((string) config('app.url', 'https://stage.mein-seelenfunke.de'), '/');
        $parsed = parse_url($appUrl);
        $host = $parsed['host'] ?? 'stage.mein-seelenfunke.de';
        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        $basePath = rtrim($parsed['path'] ?? '', '/');
        $requestPath = $basePath . '/api/twilio/call-log';

        $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
        $bridgeSecret = self::resolveBridgeSecret();

        $loop = Loop::get();
        $targetUri = ($scheme === 'https' ? 'tls://' : 'tcp://') . $host . ':' . $port;
        $connector = new Connector([
            'timeout' => 8.0,
            'happy_eyeballs' => true,
            'tls' => [
                'SNI_enabled' => true,
                'peer_name' => $host,
                'verify_peer' => $scheme === 'https' && !in_array($host, ['localhost', '127.0.0.1'], true),
            ],
        ], $loop);

        $connector->connect($targetUri)->then(
            function (ConnectionInterface $conn) use ($host, $requestPath, $body, $bridgeSecret): void {
                $req = "POST {$requestPath} HTTP/1.1\r\n"
                    . "Host: {$host}\r\n"
                    . "Content-Type: application/json\r\n"
                    . "Accept: application/json\r\n"
                    . "Content-Length: " . strlen($body) . "\r\n"
                    . ($bridgeSecret !== '' ? "X-Bridge-Secret: {$bridgeSecret}\r\nX-AI-Proxy-Secret: {$bridgeSecret}\r\n" : '')
                    . "Connection: close\r\n\r\n"
                    . $body;

                $conn->on('data', function (): void {
                });
                $conn->on('error', function (): void {
                });
                $conn->write($req);
            },
            function (): void {
            }
        );
    }

    private static function resolveBridgeSecret(): string
    {
        $candidates = [
            config('services.gemini.bridge_secret'),
            config('services.ai.proxy_secret'),
            config('services.twilio.bridge_secret'),
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                return trim($c);
            }
        }
        $appKey = trim((string) config('app.key', ''));
        if ($appKey !== '') {
            return hash_hmac('sha256', 'seelenfunke-internal-bridge-v1', $appKey);
        }

        return '';
    }
}

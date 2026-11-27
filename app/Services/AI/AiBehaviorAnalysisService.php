<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiBehaviorFeedback;
use App\Models\Ai\AiBehaviorProfile;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiBehaviorAnalysisService
{
    public const ALLOWED_CHANNELS = [
        'web_portal',
        'live_widget',
        'android_app',
        'android_live_call',
        'workshop_minipc',
        'funkenwerk_desktop',
    ];

    public const RULE_SCOPE_PREFIX = 'behavior:';
    public const PROPOSAL_THRESHOLD = 3;
    public const HIGH_INTENSITY_PROPOSAL_THRESHOLD = 2;
    public const MAX_DIRECTIVE_CHARS = 220;
    public const MAX_PROMPT_BLOCK_CHARS = 580;

    /**
     * Explizite Schnell-Chips für negative Web-/Widget-Reaktionen.
     *
     * @return array<string, array{label: string, category: string, polarity: string, intensity: int}>
     */
    public static function quickReasonChips(): array
    {
        return [
            'too_long' => [
                'label' => 'Zu lang / umständlich',
                'category' => 'tone_length',
                'polarity' => 'criticism',
                'intensity' => 4,
            ],
            'wrong_tool_or_fact' => [
                'label' => 'Tool oder Fakt falsch',
                'category' => 'tool_accuracy',
                'polarity' => 'correction',
                'intensity' => 5,
            ],
            'unnecessary_question' => [
                'label' => 'Unnötige Rückfrage',
                'category' => 'proactivity',
                'polarity' => 'criticism',
                'intensity' => 4,
            ],
            'wrong_tone' => [
                'label' => 'Tonfall passt nicht',
                'category' => 'tone_length',
                'polarity' => 'criticism',
                'intensity' => 3,
            ],
        ];
    }

    /**
     * Strikter Funki-Schutz: Externer Support-Agent Funki bleibt 100 % eingefroren und isoliert.
     */
    public static function isAgentExcluded(?AiAgent $agent = null, ?string $agentName = null): bool
    {
        $name = strtolower(trim((string) ($agent?->name ?? $agentName ?? '')));

        return $name === 'funki';
    }

    /**
     * Normalisiert eingehende Kanäle strikt auf die Whitelist.
     */
    public static function normalizeChannel(?string $channel): string
    {
        $clean = strtolower(trim((string) $channel));
        if (in_array($clean, self::ALLOWED_CHANNELS, true)) {
            return $clean;
        }

        return match ($clean) {
            'web', 'portal', 'admin', 'workspace' => 'web_portal',
            'widget', 'web_widget' => 'live_widget',
            'android', 'app', 'mobile' => 'android_app',
            'live_call', 'voice', '3d_live_call', 'live' => 'android_live_call',
            'minipc', 'mini_pc', 'kiosk', 'laser' => 'workshop_minipc',
            'desktop', 'windows', 'funkenwerk' => 'funkenwerk_desktop',
            default => 'web_portal',
        };
    }

    /**
     * Bereinigt und kürzt Text-Ausschnitte sicher für die Speicherung (entfernt Secrets & <speak>-Tags).
     */
    public static function cleanExcerpt(?string $text, int $maxLength = 600): ?string
    {
        if ($text === null) {
            return null;
        }

        $clean = preg_replace('/<\/?speak>/iu', '', $text) ?? $text;
        $clean = preg_replace('/\b(?:Bearer|sk-|ghp_|AIza)[A-Za-z0-9_\-\.]{8,}\b/u', '[geschützt]', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? trim($clean);
        if ($clean === '') {
            return null;
        }

        return mb_strlen($clean) > $maxLength ? mb_substr($clean, 0, $maxLength - 1) . '…' : $clean;
    }

    /**
     * Prüft, ob Tabellen bereits migriert sind (verhindert Fehler bei frühen Tests oder Bootstrap).
     */
    public static function tablesReady(): bool
    {
        return Schema::hasTable('ai_behavior_feedbacks') && Schema::hasTable('ai_behavior_profiles');
    }

    /**
     * 1. Explizite 1-Klick-Reaktion (Daumen hoch / runter + Schnellgrund) aus Web, Live-Widget oder API.
     *
     * @param array<string, mixed> $context
     */
    public static function recordExplicitReaction(
        ?AiAgent $agent,
        string $channel,
        string $polarity,
        ?string $category = null,
        ?string $reasonKeyOrNote = null,
        array $context = []
    ): ?AiBehaviorFeedback {
        if (!self::tablesReady()) {
            return null;
        }
        if (!empty($context['visitor_sandbox_locked'])) {
            return null;
        }
        if (self::isAgentExcluded($agent, $context['agent_name'] ?? null)) {
            return null;
        }

        $normChannel = self::normalizeChannel($channel);
        $normPolarity = in_array($polarity, ['praise', 'criticism', 'correction'], true) ? $polarity : 'criticism';

        $chips = self::quickReasonChips();
        $reasonNote = null;
        $intensity = $normPolarity === 'praise' ? 4 : 4;
        $resolvedCategory = $category;

        if ($reasonKeyOrNote !== null && trim($reasonKeyOrNote) !== '') {
            $trimReason = trim($reasonKeyOrNote);
            if (isset($chips[$trimReason])) {
                $chip = $chips[$trimReason];
                $resolvedCategory = $resolvedCategory ?: $chip['category'];
                $normPolarity = $normPolarity === 'praise' ? 'praise' : $chip['polarity'];
                $intensity = $chip['intensity'];
                $reasonNote = $chip['label'];
            } else {
                $reasonNote = mb_substr(AiOutputQualityGate::sanitizeText($trimReason), 0, 240);
            }
        }

        if (!$resolvedCategory || !array_key_exists($resolvedCategory, AiBehaviorFeedback::CATEGORIES)) {
            $resolvedCategory = $normPolarity === 'praise' ? 'domain_competence' : 'tone_length';
        }

        $resolvedAgent = $agent ?? self::resolveDefaultInternalAgent($context['agent_name'] ?? null);
        if (self::isAgentExcluded($resolvedAgent, $context['agent_name'] ?? null)) {
            return null;
        }

        $memoryId = !empty($context['ai_chat_memory_id']) && Str::isUuid((string) $context['ai_chat_memory_id'])
            ? (string) $context['ai_chat_memory_id']
            : null;
        $sessionId = !empty($context['session_id']) ? mb_substr((string) $context['session_id'], 0, 191) : null;
        $agentExcerpt = self::cleanExcerpt($context['agent_excerpt'] ?? null);
        $userExcerpt = self::cleanExcerpt($context['user_excerpt'] ?? null);

        $dedupeAnchor = $memoryId ?: sha1($normChannel . '|' . ($sessionId ?? '') . '|' . mb_substr((string) $agentExcerpt, 0, 160));
        $signalHash = hash('sha256', 'explicit|' . ($resolvedAgent?->id ?? 'global') . '|' . $dedupeAnchor);

        // Härtung: Ein erneuter Klick auf derselben Antwort (z. B. Daumen hoch -> Daumen runter oder Grund ergänzt)
        // aktualisiert das bestehende Signal statt Dubletten zu erzeugen.
        $existing = AiBehaviorFeedback::where('source_type', 'explicit_reaction')
            ->where('signal_hash', $signalHash)
            ->first();

        $payload = [
            'ai_agent_id' => $resolvedAgent?->id,
            'ai_department_id' => $resolvedAgent?->ai_department_id,
            'channel' => $normChannel,
            'source_type' => 'explicit_reaction',
            'polarity' => $normPolarity,
            'category' => $resolvedCategory,
            'intensity' => max(1, min(5, (int) ($context['intensity'] ?? $intensity))),
            'session_id' => $sessionId,
            'ai_chat_memory_id' => $memoryId,
            'executed_tools' => !empty($context['executed_tools']) && is_array($context['executed_tools'])
                ? array_values(array_slice($context['executed_tools'], 0, 12))
                : null,
            'latency_ms' => isset($context['latency_ms']) ? max(0, (int) $context['latency_ms']) : null,
            'environment_tier' => (string) ($context['environment_tier'] ?? FunkiraPresenceContext::resolveEnvironmentTier()),
            'user_excerpt' => $userExcerpt,
            'agent_excerpt' => $agentExcerpt,
            'reason_note' => $reasonNote,
            'signal_hash' => $signalHash,
            'status' => 'new',
        ];

        if ($existing) {
            $existing->update($payload);
            $record = $existing->fresh();
        } else {
            $record = AiBehaviorFeedback::create($payload);
        }

        self::touchProfileAsyncSafe($resolvedAgent?->id, $normChannel);

        return $record;
    }

    /**
     * 2. Deterministischer 0-Token-Vorfilter für implizite Sprach- & Text-Signale über alle Kanäle.
     *
     * @return array{polarity: string, category: string, intensity: int, matched_label: string}|null
     */
    public static function detectImplicitSignal(string $text): ?array
    {
        $trimmed = trim($text);
        if (mb_strlen($trimmed) < 4) {
            return null;
        }

        $lower = mb_strtolower($trimmed);

        // Zuerst Kritik & Korrekturen prüfen (inkl. negiertem Lob wie "nicht perfekt" / "gar nicht gut")
        $criticismRules = [
            [
                'polarity' => 'correction',
                'category' => 'hallucination_bluff',
                'intensity' => 5,
                'label' => 'Fakt oder Aktion angezweifelt',
                'pattern' => '/\b(hast\s+du\s+(?:dir\s+)?erfunden|ausgedacht|halluzinier|bluff|stimmt\s+(?:so\s+)?nicht|das\s+ist\s+(?:komplett\s+)?falsch|du\s+hast\s+gar\s+nichts\s+gemacht|du\s+lügst|erfindest\s+du)\b/iu',
            ],
            [
                'polarity' => 'correction',
                'category' => 'tool_accuracy',
                'intensity' => 4,
                'label' => 'Werkzeug- oder Kanal-Korrektur',
                'pattern' => '/\b(falsches\s+tool|falscher\s+kanal|falscher\s+ordner|falscher\s+betrag|falsch\s+gebucht|nicht\s+im\s+richtigen\s+kanal|kamera\s+nicht\s+öffnen|öffne\s+nicht\s+(?:schon\s+wieder\s+)?die\s+kamera|hat\s+nicht\s+geklappt|falsch\s+eingetragen)\b/iu',
            ],
            [
                'polarity' => 'criticism',
                'category' => 'voice_pacing',
                'intensity' => 4,
                'label' => 'Sprachfluss oder Wiederholung kritisiert',
                'pattern' => '/\b(sprich\s+(?:bitte\s+)?langsamer|sprich\s+(?:bitte\s+)?schneller|zu\s+schnell\s+gesprochen|unterbrich\s+mich\s+nicht|rede\s+(?:mir\s+)?nicht\s+dazwischen|wiederholst\s+dich|doppelt\s+gesagt|zweimal\s+gesagt)\b/iu',
            ],
            [
                'polarity' => 'criticism',
                'category' => 'proactivity',
                'intensity' => 4,
                'label' => 'Unnötige Rückfrage oder ungefragter Vortrag',
                'pattern' => '/\b(hör\s+auf\s+zu\s+fragen|frag\s+nicht\s+(?:immer|ständig|so\s+oft)|unnötige\s+rückfrage|hab\s+ich\s+nicht\s+(?:nach\s+)?gefragt|nicht\s+ungefragt|mach\s+(?:es\s+)?einfach\s+ohne\s+zu\s+fragen)\b/iu',
            ],
            [
                'polarity' => 'criticism',
                'category' => 'tone_length',
                'intensity' => 4,
                'label' => 'Antwort zu lang oder Tonfall unpassend',
                'pattern' => '/\b(zu\s+lang|viel\s+zu\s+lang|kürzer\s+bitte|fass\s+dich\s+(?:bitte\s+)?kürzer|nicht\s+so\s+viel\s+text|zu\s+umständlich|komm\s+(?:bitte\s+)?auf\s+den\s+punkt|zu\s+viel\s+gelaber|tonfall\s+passt\s+nicht|nicht\s+so\s+steif|weniger\s+text)\b/iu',
            ],
            [
                'polarity' => 'correction',
                'category' => 'navigation_ux',
                'intensity' => 3,
                'label' => 'Falsche Seite oder Navigation',
                'pattern' => '/\b(falsche\s+seite|falscher\s+link|falsch\s+navigiert|falsches\s+menü)\b/iu',
            ],
            [
                'polarity' => 'criticism',
                'category' => 'domain_competence',
                'intensity' => 3,
                'label' => 'Unzufriedenheit mit Antwortqualität',
                'pattern' => '/\b(nicht\s+perfekt|gar\s+nicht\s+gut|überhaupt\s+nicht\s+hilfreich|total\s+schlecht|nervt\s+(?:mich|gerade)|bringt\s+(?:mir\s+)?nichts)\b/iu',
            ],
        ];

        foreach ($criticismRules as $rule) {
            if (preg_match($rule['pattern'], $lower)) {
                return [
                    'polarity' => $rule['polarity'],
                    'category' => $rule['category'],
                    'intensity' => $rule['intensity'],
                    'matched_label' => $rule['label'],
                ];
            }
        }

        // Danach klare Lob-Muster prüfen (nur ohne Negationspräfix unmittelbar davor)
        $praisePatterns = [
            ['pattern' => '/(?<!nicht\s)(?<!kein\s)\b(perfekt|genau\s+so|mega\s+gut|richtig\s+gut|super\s+gemacht|stark\s+gemacht|top\s+antwort|danke\s+(?:dir\s+)?super|sehr\s+gut\s+gemacht|klasse\s+gemacht|hammer\s+gut|genial\s+gelöst|genau\s+das\s+meinte\s+ich)\b/iu', 'intensity' => 5, 'label' => 'Explizites Lob'],
            ['pattern' => '/^(?:danke,?\s+)?(?:klasse|super|spitze|genial|mega|top|wunderbar|ausgezeichnet|bravo|liebe\s+ich)[!\.\s]*$/iu', 'intensity' => 4, 'label' => 'Kurzes Lob'],
        ];

        foreach ($praisePatterns as $pRule) {
            if (preg_match($pRule['pattern'], $lower)) {
                return [
                    'polarity' => 'praise',
                    'category' => 'domain_competence',
                    'intensity' => $pRule['intensity'],
                    'matched_label' => $pRule['label'],
                ];
            }
        }

        return null;
    }

    /**
     * Erfasst ein implizites Sprach- oder Text-Signal aus einem beliebigen Kanal (0 ms / 0 Tokens).
     *
     * @param array<string, mixed> $context
     */
    public static function scanAndRecordImplicitMessage(
        string $userMessage,
        ?AiAgent $agent,
        string $channel,
        array $context = []
    ): ?AiBehaviorFeedback {
        if (!self::tablesReady()) {
            return null;
        }
        if (!empty($context['visitor_sandbox_locked'])) {
            return null;
        }
        if (self::isAgentExcluded($agent, $context['agent_name'] ?? null)) {
            return null;
        }

        $detected = self::detectImplicitSignal($userMessage);
        if ($detected === null) {
            return null;
        }

        $resolvedAgent = $agent ?? self::resolveDefaultInternalAgent($context['agent_name'] ?? null);
        if (self::isAgentExcluded($resolvedAgent, $context['agent_name'] ?? null)) {
            return null;
        }

        $normChannel = self::normalizeChannel($channel);
        $sessionId = !empty($context['session_id']) ? mb_substr((string) $context['session_id'], 0, 191) : null;
        $memoryId = !empty($context['ai_chat_memory_id']) && Str::isUuid((string) $context['ai_chat_memory_id'])
            ? (string) $context['ai_chat_memory_id']
            : null;

        // Deduplizierung bei Streaming-Transcript-Updates (gleicher Memory-Datensatz oder selbe Session+Kategorie+Polarität binnen 90s)
        $signalHash = hash(
            'sha256',
            'implicit|' . ($resolvedAgent?->id ?? 'global') . '|' . $normChannel . '|' . ($memoryId ?: ($sessionId . '|' . $detected['polarity'] . '|' . $detected['category']))
        );

        $existing = AiBehaviorFeedback::where('source_type', 'implicit_text_voice')
            ->where('signal_hash', $signalHash)
            ->where('created_at', '>=', now()->subSeconds(120))
            ->first();

        $payload = [
            'ai_agent_id' => $resolvedAgent?->id,
            'ai_department_id' => $resolvedAgent?->ai_department_id,
            'channel' => $normChannel,
            'source_type' => 'implicit_text_voice',
            'polarity' => $detected['polarity'],
            'category' => $detected['category'],
            'intensity' => $detected['intensity'],
            'session_id' => $sessionId,
            'ai_chat_memory_id' => $memoryId,
            'executed_tools' => !empty($context['executed_tools']) && is_array($context['executed_tools'])
                ? array_values(array_slice($context['executed_tools'], 0, 12))
                : null,
            'latency_ms' => isset($context['latency_ms']) ? max(0, (int) $context['latency_ms']) : null,
            'environment_tier' => (string) ($context['environment_tier'] ?? FunkiraPresenceContext::resolveEnvironmentTier()),
            'user_excerpt' => self::cleanExcerpt($userMessage),
            'agent_excerpt' => self::cleanExcerpt($context['agent_excerpt'] ?? null),
            'reason_note' => $detected['matched_label'],
            'signal_hash' => $signalHash,
            'status' => 'new',
        ];

        if ($existing) {
            $existing->update($payload);
            $record = $existing->fresh();
        } else {
            $record = AiBehaviorFeedback::create($payload);
        }

        self::touchProfileAsyncSafe($resolvedAgent?->id, $normChannel);

        return $record;
    }

    /**
     * 3a. Deterministische Qualitäts-Gate-Signale (Bluff-Erkennung, ungeprüfte Bestellnummern, Refusal-Block).
     *
     * @param array<string, mixed> $gateResult
     * @param array<string, mixed> $context
     */
    public static function recordQualityGateSignal(
        array $gateResult,
        ?AiAgent $agent,
        string $channel,
        array $context = []
    ): ?AiBehaviorFeedback {
        if (!self::tablesReady()) {
            return null;
        }
        if (!empty($context['visitor_sandbox_locked']) || self::isAgentExcluded($agent, $context['agent_name'] ?? null)) {
            return null;
        }

        $bluff = !empty($gateResult['bluff_detected']);
        $missingOrders = !empty($gateResult['missing_order_numbers']) && is_array($gateResult['missing_order_numbers'])
            ? $gateResult['missing_order_numbers']
            : [];
        $cannedRefusal = !empty($gateResult['canned_refusal_detected']);

        if (!$bluff && empty($missingOrders) && !$cannedRefusal) {
            return null;
        }

        $resolvedAgent = $agent ?? self::resolveDefaultInternalAgent($context['agent_name'] ?? null);
        if (self::isAgentExcluded($resolvedAgent, $context['agent_name'] ?? null)) {
            return null;
        }

        $normChannel = self::normalizeChannel($channel);
        $category = ($bluff || !empty($missingOrders)) ? 'hallucination_bluff' : 'tone_length';
        $intensity = $bluff ? 5 : 4;
        $reason = $bluff
            ? 'Quality-Gate: Ausführungsversprechen ohne Tool-Lauf gestoppt'
            : (!empty($missingOrders)
                ? 'Quality-Gate: Unbekannte Bestellnummer (' . implode(', ', array_slice($missingOrders, 0, 3)) . ')'
                : 'Quality-Gate: Verbotene Sprachmodell-Floskel abgefangen');

        $record = AiBehaviorFeedback::create([
            'ai_agent_id' => $resolvedAgent?->id,
            'ai_department_id' => $resolvedAgent?->ai_department_id,
            'channel' => $normChannel,
            'source_type' => 'quality_gate',
            'polarity' => 'correction',
            'category' => $category,
            'intensity' => $intensity,
            'session_id' => !empty($context['session_id']) ? mb_substr((string) $context['session_id'], 0, 191) : null,
            'ai_chat_memory_id' => !empty($context['ai_chat_memory_id']) && Str::isUuid((string) $context['ai_chat_memory_id'])
                ? (string) $context['ai_chat_memory_id']
                : null,
            'executed_tools' => !empty($context['executed_tools']) && is_array($context['executed_tools'])
                ? array_values(array_slice($context['executed_tools'], 0, 12))
                : [],
            'latency_ms' => isset($context['latency_ms']) ? max(0, (int) $context['latency_ms']) : null,
            'environment_tier' => (string) ($context['environment_tier'] ?? FunkiraPresenceContext::resolveEnvironmentTier()),
            'user_excerpt' => self::cleanExcerpt($context['user_excerpt'] ?? null),
            'agent_excerpt' => self::cleanExcerpt($context['agent_excerpt'] ?? ($gateResult['text'] ?? null)),
            'reason_note' => mb_substr($reason, 0, 250),
            'signal_hash' => hash('sha256', 'qg|' . ($resolvedAgent?->id ?? 'global') . '|' . $normChannel . '|' . $reason . '|' . microtime(true)),
            'status' => 'new',
        ]);

        self::touchProfileAsyncSafe($resolvedAgent?->id, $normChannel);

        return $record;
    }

    /**
     * 3b. Deterministisches Tool-Fehler-Signal aus Live- oder Chat-Werkzeugausführung.
     *
     * @param array<string, mixed> $context
     */
    public static function recordToolErrorSignal(
        string $toolName,
        string $errorMessage,
        ?AiAgent $agent,
        string $channel,
        array $context = []
    ): ?AiBehaviorFeedback {
        if (!self::tablesReady()) {
            return null;
        }
        if (!empty($context['visitor_sandbox_locked']) || self::isAgentExcluded($agent, $context['agent_name'] ?? null)) {
            return null;
        }

        $resolvedAgent = $agent ?? self::resolveDefaultInternalAgent($context['agent_name'] ?? null);
        if (self::isAgentExcluded($resolvedAgent, $context['agent_name'] ?? null)) {
            return null;
        }

        $normChannel = self::normalizeChannel($channel);
        $cleanTool = mb_substr(trim($toolName), 0, 80);
        $cleanError = self::cleanExcerpt($errorMessage, 220) ?? 'Unbekannter Werkzeugfehler';

        // Dedupliziere identische Tool-Fehler innerhalb von 60 Sekunden in derselben Session
        $dedupeHash = hash('sha256', 'toolerr|' . ($resolvedAgent?->id ?? 'global') . '|' . $normChannel . '|' . ($context['session_id'] ?? '') . '|' . $cleanTool);
        if (AiBehaviorFeedback::where('source_type', 'tool_error')
            ->where('signal_hash', $dedupeHash)
            ->where('created_at', '>=', now()->subSeconds(60))
            ->exists()) {
            return null;
        }

        $record = AiBehaviorFeedback::create([
            'ai_agent_id' => $resolvedAgent?->id,
            'ai_department_id' => $resolvedAgent?->ai_department_id,
            'channel' => $normChannel,
            'source_type' => 'tool_error',
            'polarity' => 'correction',
            'category' => 'tool_accuracy',
            'intensity' => 4,
            'session_id' => !empty($context['session_id']) ? mb_substr((string) $context['session_id'], 0, 191) : null,
            'executed_tools' => [$cleanTool],
            'latency_ms' => isset($context['latency_ms']) ? max(0, (int) $context['latency_ms']) : null,
            'environment_tier' => (string) ($context['environment_tier'] ?? FunkiraPresenceContext::resolveEnvironmentTier()),
            'user_excerpt' => self::cleanExcerpt($context['user_excerpt'] ?? null),
            'agent_excerpt' => $cleanError,
            'reason_note' => mb_substr("Werkzeugfehler bei {$cleanTool}: {$cleanError}", 0, 250),
            'signal_hash' => $dedupeHash,
            'status' => 'new',
        ]);

        self::touchProfileAsyncSafe($resolvedAgent?->id, $normChannel);

        return $record;
    }

    /**
     * Berechnet und speichert das aggregierte Verhaltensprofil inkl. Trend, Clustern,
     * Regelvorschlägen und Before/After-Wirkungsmessung für aktive Verhaltensregeln.
     */
    public static function rebuildProfile(?string $agentId = null, string $channel = 'all', int $windowDays = 30): AiBehaviorProfile
    {
        $windowDays = in_array($windowDays, [7, 30, 90, 365], true) ? $windowDays : 30;
        $channel = $channel === 'all' ? 'all' : self::normalizeChannel($channel);

        $periodStart = now()->subDays($windowDays);
        $prevPeriodStart = now()->subDays($windowDays * 2);

        $baseQuery = AiBehaviorFeedback::query()
            ->when($agentId !== null && $agentId !== '', fn ($q) => $q->where('ai_agent_id', $agentId))
            ->when($channel !== 'all', fn ($q) => $q->where('channel', $channel));

        $currentSignals = (clone $baseQuery)->where('created_at', '>=', $periodStart)->get();
        $prevSignals = (clone $baseQuery)
            ->where('created_at', '>=', $prevPeriodStart)
            ->where('created_at', '<', $periodStart)
            ->get();

        $praiseCount = $currentSignals->where('polarity', 'praise')->count();
        $criticismCount = $currentSignals->where('polarity', 'criticism')->count();
        $correctionCount = $currentSignals->where('polarity', 'correction')->count();

        $score = self::calculateSatisfactionScore($currentSignals);
        $prevScore = $prevSignals->isNotEmpty() ? self::calculateSatisfactionScore($prevSignals) : $score;
        $trendDelta = round($score - $prevScore, 2);

        $topStrengths = self::clusterByCategoryAndChannel($currentSignals->where('polarity', 'praise'), 5);
        $frictionSignals = $currentSignals->whereIn('polarity', ['criticism', 'correction']);
        $topFriction = self::clusterByCategoryAndChannel($frictionSignals, 6);

        $pendingProposals = self::buildRuleProposals(
            $frictionSignals->where('status', 'new'),
            $agentId
        );

        $ruleImpacts = self::computeBeforeAfterImpactMetrics($agentId, $channel);

        $profile = AiBehaviorProfile::query()
            ->where('channel', $channel)
            ->where('window_days', $windowDays)
            ->when($agentId === null || $agentId === '', fn ($q) => $q->whereNull('ai_agent_id'), fn ($q) => $q->where('ai_agent_id', $agentId))
            ->first();

        $attributes = [
            'ai_agent_id' => ($agentId !== null && $agentId !== '') ? $agentId : null,
            'channel' => $channel,
            'window_days' => $windowDays,
            'praise_count' => $praiseCount,
            'criticism_count' => $criticismCount,
            'correction_count' => $correctionCount,
            'satisfaction_score' => $score,
            'trend_delta' => $trendDelta,
            'top_strengths' => $topStrengths,
            'top_friction_points' => $topFriction,
            'pending_rule_proposals' => $pendingProposals,
            'rule_impact_metrics' => $ruleImpacts,
            'last_analyzed_at' => now(),
        ];

        if ($profile) {
            $profile->update($attributes);
            return $profile->fresh();
        }

        return AiBehaviorProfile::create($attributes);
    }

    /**
     * Gewichteter Zufriedenheits-Index (0..100) auf Basis von Polarität und Intensität.
     */
    public static function calculateSatisfactionScore(Collection $signals): float
    {
        if ($signals->isEmpty()) {
            return 100.0;
        }

        $positiveWeight = 0.0;
        $negativeWeight = 0.0;

        foreach ($signals as $sig) {
            $w = max(1, min(5, (int) ($sig->intensity ?? 3)));
            if ($sig->polarity === 'praise') {
                $positiveWeight += $w;
            } else {
                $negativeWeight += ($sig->polarity === 'correction' ? $w * 1.15 : $w);
            }
        }

        $total = $positiveWeight + $negativeWeight;
        if ($total <= 0) {
            return 100.0;
        }

        return round(($positiveWeight / $total) * 100, 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function clusterByCategoryAndChannel(Collection $signals, int $limit = 5): array
    {
        if ($signals->isEmpty()) {
            return [];
        }

        return $signals
            ->groupBy(fn ($s) => $s->channel . '|' . $s->category)
            ->map(function (Collection $group, string $key) {
                [$chan, $cat] = explode('|', $key, 2);
                $latest = $group->sortByDesc('created_at')->first();

                return [
                    'cluster_key' => $key,
                    'channel' => $chan,
                    'channel_label' => AiBehaviorFeedback::CHANNELS[$chan] ?? $chan,
                    'category' => $cat,
                    'category_label' => AiBehaviorFeedback::CATEGORIES[$cat] ?? $cat,
                    'count' => $group->count(),
                    'avg_intensity' => round((float) $group->avg('intensity'), 1),
                    'sample_note' => $latest?->reason_note ?: $latest?->user_excerpt,
                    'last_seen_at' => $latest?->created_at?->toIso8601String(),
                ];
            })
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Erzeugt gebündelte, kompakte Regelvorschläge ab >= 3 gleichartigen Kritiken (oder >= 2 mit Intensität >= 4).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildRuleProposals(Collection $openFrictionSignals, ?string $agentId = null): array
    {
        if ($openFrictionSignals->isEmpty()) {
            return [];
        }

        $activeScopes = Schema::hasTable('ai_learned_preferences')
            ? AiLearnedPreference::where('is_active', true)
                ->where('scope_key', 'like', self::RULE_SCOPE_PREFIX . '%')
                ->pluck('scope_key')
                ->flip()
                ->all()
            : [];

        $proposals = [];
        $grouped = $openFrictionSignals->groupBy(fn ($s) => $s->channel . '|' . $s->category);

        foreach ($grouped as $key => $group) {
            $count = $group->count();
            $highIntensityCount = $group->where('intensity', '>=', 4)->count();
            if ($count < self::PROPOSAL_THRESHOLD && $highIntensityCount < self::HIGH_INTENSITY_PROPOSAL_THRESHOLD) {
                continue;
            }

            [$chan, $cat] = explode('|', $key, 2);
            $scopeKey = self::buildRuleScopeKey($chan, $cat);
            $alreadyActive = isset($activeScopes[$scopeKey]);

            $directive = self::suggestCompactDirective($chan, $cat);
            $sampleExcerpts = $group->sortByDesc('created_at')
                ->take(3)
                ->map(fn ($s) => $s->reason_note ?: $s->user_excerpt)
                ->filter()
                ->values()
                ->all();

            $proposals[] = [
                'proposal_key' => $key,
                'scope_key' => $scopeKey,
                'ai_agent_id' => $agentId ?? $group->first()?->ai_agent_id,
                'channel' => $chan,
                'channel_label' => AiBehaviorFeedback::CHANNELS[$chan] ?? $chan,
                'category' => $cat,
                'category_label' => AiBehaviorFeedback::CATEGORIES[$cat] ?? $cat,
                'signal_count' => $count,
                'avg_intensity' => round((float) $group->avg('intensity'), 1),
                'suggested_title' => (AiBehaviorFeedback::CHANNELS[$chan] ?? $chan) . ': ' . (AiBehaviorFeedback::CATEGORIES[$cat] ?? $cat),
                'suggested_directive' => $directive,
                'sample_reasons' => $sampleExcerpts,
                'already_active' => $alreadyActive,
            ];
        }

        usort($proposals, fn (array $a, array $b) => $b['signal_count'] <=> $a['signal_count']);

        return array_slice($proposals, 0, 8);
    }

    public static function buildRuleScopeKey(string $channel, string $category): string
    {
        return self::RULE_SCOPE_PREFIX . self::normalizeChannel($channel) . ':' . Str::slug($category, '_');
    }

    /**
     * Erzeugt eine prägnante, token-sparsame Verhaltensdirektive (<= 60 Tokens, 0 KI-Gedankenstriche).
     */
    public static function suggestCompactDirective(string $channel, string $category): string
    {
        $channelPrefix = match ($channel) {
            'android_live_call' => 'Im 3D Live-Call: ',
            'workshop_minipc' => 'Am Werkstatt Mini-PC: ',
            'live_widget' => 'Im Live-Widget: ',
            'android_app' => 'In der Android-App: ',
            'funkenwerk_desktop' => 'In der Windows-Suite: ',
            default => 'Im Web-Portal: ',
        };

        $body = match ($category) {
            'tone_length' => 'Maximal 2 kurze, direkte Sätze ohne Einleitung oder Füllfloskeln antworten.',
            'proactivity' => 'Bei eindeutigen Status- oder Kurzabfragen sofort handeln ohne unnötige Gegenfragen.',
            'hallucination_bluff' => 'Niemals Aktionen oder Bestellnummern behaupten, die nicht per echtem Tool-Aufruf belegt sind.',
            'tool_accuracy' => 'Vor jeder Schreibaktion Zielkanal, Parameter und Vorzeichen exakt prüfen und Ergebnis verifizieren.',
            'voice_pacing' => 'Ruhig, flüssig und einmalig sprechen, niemals doppelt ansetzen oder Alina unterbrechen.',
            'navigation_ux' => 'Nur exakt geprüfte Admin-Routen nennen und direkt zum richtigen Bereich führen.',
            default => 'Präzise, faktenbasiert und unmittelbar handlungsorientiert antworten.',
        };

        return mb_substr(AiOutputQualityGate::sanitizeText($channelPrefix . $body), 0, self::MAX_DIRECTIVE_CHARS);
    }

    /**
     * 1-Klick-Freigabe: Alias für mobile & API-Aufrufe ohne Doppel-Logik.
     */
    public static function approveProposalAsRule(
        string $channel,
        string $category,
        string $directive = '',
        ?string $agentId = null,
        ?string $customTitle = null
    ): AiLearnedPreference {
        return self::activateRuleProposal($channel, $category, $directive, $agentId, $customTitle);
    }

    /**
     * 1-Klick-Freigabe: Aktiviert eine Verhaltensregel kontrolliert als AiLearnedPreference
     * und speichert die Vorher-Baseline für die spätere 20-Interaktionen-Wirkungsmessung.
     */
    public static function activateRuleProposal(
        string $channel,
        string $category,
        string $directive,
        ?string $agentId = null,
        ?string $customTitle = null
    ): AiLearnedPreference {
        $normChannel = self::normalizeChannel($channel);
        $cleanCat = array_key_exists($category, AiBehaviorFeedback::CATEGORIES) ? $category : 'tone_length';
        $cleanDirective = mb_substr(AiOutputQualityGate::sanitizeText(trim($directive)), 0, self::MAX_DIRECTIVE_CHARS);
        if ($cleanDirective === '') {
            $cleanDirective = self::suggestCompactDirective($normChannel, $cleanCat);
        }

        $agent = $agentId ? AiAgent::find($agentId) : null;
        if ($agent && self::isAgentExcluded($agent)) {
            throw new \InvalidArgumentException('Für den eingefrorenen Support-Agenten Funki dürfen keine Verhaltensregeln aktiviert werden.');
        }

        $scopeKey = self::buildRuleScopeKey($normChannel, $cleanCat);
        $title = trim((string) $customTitle) !== ''
            ? mb_substr(AiOutputQualityGate::sanitizeText($customTitle), 0, 120)
            : 'Verhaltensregel · ' . (AiBehaviorFeedback::CHANNELS[$normChannel] ?? $normChannel) . ' (' . (AiBehaviorFeedback::CATEGORIES[$cleanCat] ?? $cleanCat) . ')';

        // Vorher-Baseline der letzten 20 Signale auf diesem Kanal berechnen
        $baselineSignals = AiBehaviorFeedback::query()
            ->where('channel', $normChannel)
            ->when($agent, fn ($q) => $q->where('ai_agent_id', $agent->id))
            ->latest('created_at')
            ->limit(20)
            ->get();

        $baselineTotal = $baselineSignals->count();
        $baselineNeg = $baselineSignals->whereIn('polarity', ['criticism', 'correction'])->count();
        $baselineCritRate = $baselineTotal > 0 ? round(($baselineNeg / $baselineTotal) * 100, 1) : 0.0;

        $preference = AiLearnedPreference::updateOrCreate(
            ['scope_key' => $scopeKey],
            [
                'ai_department_id' => $agent?->ai_department_id,
                'subject_label' => $title,
                'rule_summary' => $cleanDirective,
                'structured_value' => [
                    'type' => 'behavior_rule',
                    'channel' => $normChannel,
                    'category' => $cleanCat,
                    'ai_agent_id' => $agent?->id,
                    'activated_at' => now()->toIso8601String(),
                    'baseline_criticism_rate' => $baselineCritRate,
                    'baseline_sample_size' => $baselineTotal,
                    'target_window_interactions' => 20,
                ],
                'is_active' => true,
            ]
        );

        // Offene passende Signale als "applied" verknüpfen
        AiBehaviorFeedback::query()
            ->where('channel', $normChannel)
            ->where('category', $cleanCat)
            ->whereIn('polarity', ['criticism', 'correction'])
            ->where('status', 'new')
            ->when($agent, fn ($q) => $q->where('ai_agent_id', $agent->id))
            ->update([
                'status' => 'applied',
                'learned_preference_id' => $preference->id,
                'updated_at' => now(),
            ]);

        Cache::forget(self::directivesCacheKey($normChannel));
        Cache::forget(self::directivesCacheKey('all'));

        self::rebuildProfile(null, 'all', 30);
        self::rebuildProfile(null, $normChannel, 30);
        if ($agent) {
            self::rebuildProfile($agent->id, 'all', 30);
        }

        return $preference;
    }

    /**
     * Überführt ein Kritik-Cluster als strukturiertes Entwicklungs-Ticket ins Universum-Ticketsystem.
     */
    public static function escalateProposalToTicket(
        string $channel,
        string $category,
        string $directive,
        ?string $agentId = null
    ): AiUniverseTicket {
        $normChannel = self::normalizeChannel($channel);
        $catLabel = AiBehaviorFeedback::CATEGORIES[$category] ?? $category;
        $chanLabel = AiBehaviorFeedback::CHANNELS[$normChannel] ?? $normChannel;

        $signals = AiBehaviorFeedback::query()
            ->where('channel', $normChannel)
            ->where('category', $category)
            ->whereIn('polarity', ['criticism', 'correction'])
            ->where('status', 'new')
            ->when($agentId, fn ($q) => $q->where('ai_agent_id', $agentId))
            ->latest('created_at')
            ->limit(10)
            ->get();

        $department = AiDepartment::whereRaw('LOWER(name) IN (?, ?)', ['agenten', 'system'])->first()
            ?? AiDepartment::first();
        $agent = $agentId ? AiAgent::find($agentId) : AiAgent::where('name', 'Funkira')->first();

        $nextNum = (int) (AiUniverseTicket::count() + 1);
        $ticketNumber = sprintf('SF-%04d', max(200, $nextNum));
        while (AiUniverseTicket::where('ticket_number', $ticketNumber)->exists()) {
            $nextNum++;
            $ticketNumber = sprintf('SF-%04d', $nextNum);
        }

        $ticket = AiUniverseTicket::create([
            'ticket_number' => $ticketNumber,
            'title' => "KI-Verhaltensanalyse: {$catLabel} ({$chanLabel})",
            'ai_department_id' => $department?->id,
            'assigned_agent_id' => $agent?->id,
            'status' => AiUniverseTicket::STATUS_NEW,
            'priority' => $signals->count() >= 5 ? 'high' : 'normal',
            'ticket_type' => 'behavior_improvement',
            'is_business' => true,
            'source_channel' => $normChannel,
            'environment_tier' => FunkiraPresenceContext::resolveEnvironmentTier(),
            'messages_count' => 1,
            'last_activity_at' => now(),
            'compact_context' => mb_substr("Cluster aus {$signals->count()} Kritik-Signalen in {$chanLabel} ({$catLabel}). Vorschlag: {$directive}", 0, 500),
        ]);

        $bullets = [];
        foreach ($signals->take(5) as $sig) {
            $bullets[] = '- [' . $sig->created_at?->format('d.m. H:i') . '] ' . ($sig->reason_note ?: $sig->user_excerpt ?: 'Signal ohne Text');
        }
        $body = "**Automatisch eskaliertes Verhaltens-Cluster**\n\n"
            . "- **Kanal:** {$chanLabel} (`{$normChannel}`)\n"
            . "- **Kategorie:** {$catLabel} (`{$category}`)\n"
            . "- **Anzahl offener Signale:** {$signals->count()}\n"
            . "- **Vorgeschlagene Ziel-Regel:** {$directive}\n\n"
            . "**Beispiele:**\n" . (empty($bullets) ? '- Keine Einzelnachweise' : implode("\n", $bullets));

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'system',
            'sender_name' => 'Verhaltensanalyse',
            'ai_agent_id' => $agent?->id,
            'channel' => $normChannel,
            'content' => $body,
        ]);

        if ($signals->isNotEmpty()) {
            AiBehaviorFeedback::whereIn('id', $signals->pluck('id'))->update(['status' => 'reviewed']);
        }

        self::rebuildProfile(null, 'all', 30);
        self::rebuildProfile(null, $normChannel, 30);

        return $ticket;
    }

    /**
     * Verwirft ein Cluster sauber, damit derselbe Vorschlag nicht erneut erscheint.
     */
    public static function dismissProposal(string $channel, string $category, ?string $agentId = null): int
    {
        $normChannel = self::normalizeChannel($channel);

        $updated = AiBehaviorFeedback::query()
            ->where('channel', $normChannel)
            ->where('category', $category)
            ->whereIn('polarity', ['criticism', 'correction'])
            ->where('status', 'new')
            ->when($agentId, fn ($q) => $q->where('ai_agent_id', $agentId))
            ->update(['status' => 'dismissed', 'updated_at' => now()]);

        self::rebuildProfile(null, 'all', 30);
        self::rebuildProfile(null, $normChannel, 30);

        return $updated;
    }

    /**
     * Before/After-Verifikation: Vergleicht die bis zu 20 Interaktionen nach Regelaktivierung
     * auf demselben Kanal mit der Vorher-Baseline.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function computeBeforeAfterImpactMetrics(?string $agentId = null, string $channel = 'all'): array
    {
        if (!Schema::hasTable('ai_learned_preferences') || !self::tablesReady()) {
            return [];
        }

        $rules = AiLearnedPreference::query()
            ->where('scope_key', 'like', self::RULE_SCOPE_PREFIX . '%')
            ->where('is_active', true)
            ->latest('updated_at')
            ->limit(12)
            ->get();

        $impacts = [];
        foreach ($rules as $rule) {
            $meta = is_array($rule->structured_value) ? $rule->structured_value : [];
            $ruleChannel = self::normalizeChannel((string) ($meta['channel'] ?? 'web_portal'));
            if ($channel !== 'all' && $ruleChannel !== $channel) {
                continue;
            }
            $ruleAgentId = $meta['ai_agent_id'] ?? null;
            if ($agentId !== null && $agentId !== '' && $ruleAgentId !== null && $ruleAgentId !== $agentId) {
                continue;
            }

            $activatedAt = !empty($meta['activated_at']) ? Carbon::parse($meta['activated_at']) : $rule->updated_at;
            $targetWindow = max(5, (int) ($meta['target_window_interactions'] ?? 20));
            $baselineRate = (float) ($meta['baseline_criticism_rate'] ?? 0.0);

            $afterSignals = AiBehaviorFeedback::query()
                ->where('channel', $ruleChannel)
                ->when($ruleAgentId, fn ($q) => $q->where('ai_agent_id', $ruleAgentId))
                ->where('created_at', '>=', $activatedAt)
                ->where(function ($q) use ($rule) {
                    $q->whereNull('learned_preference_id')
                        ->orWhere('learned_preference_id', '!=', $rule->id);
                })
                ->orderBy('created_at', 'asc')
                ->limit($targetWindow)
                ->get();

            $afterTotal = $afterSignals->count();
            $afterNeg = $afterSignals->whereIn('polarity', ['criticism', 'correction'])->count();
            $afterRate = $afterTotal > 0 ? round(($afterNeg / $afterTotal) * 100, 1) : null;
            $delta = $afterRate !== null ? round($afterRate - $baselineRate, 1) : null;

            $impacts[] = [
                'preference_id' => $rule->id,
                'scope_key' => $rule->scope_key,
                'title' => $rule->subject_label,
                'directive' => $rule->rule_summary,
                'channel' => $ruleChannel,
                'channel_label' => AiBehaviorFeedback::CHANNELS[$ruleChannel] ?? $ruleChannel,
                'category' => (string) ($meta['category'] ?? 'tone_length'),
                'category_label' => AiBehaviorFeedback::CATEGORIES[(string) ($meta['category'] ?? 'tone_length')] ?? 'Verhalten',
                'activated_at' => $activatedAt?->toIso8601String(),
                'baseline_criticism_rate' => $baselineRate,
                'baseline_sample_size' => (int) ($meta['baseline_sample_size'] ?? 0),
                'after_criticism_rate' => $afterRate,
                'after_sample_size' => $afterTotal,
                'target_window' => $targetWindow,
                'criticism_rate_delta' => $delta, // negativ = Verbesserung!
                'is_improved' => $delta !== null && $delta < 0,
            ];
        }

        return $impacts;
    }

    /**
     * Erzeugt den kompakten, token-gedeckelten Block aktivierter Verhaltensregeln für den System-Prompt.
     * Strikt gesperrt für Funki und bei aktivem Mini-PC Besuchermodus.
     */
    public static function buildActiveBehaviorDirectivesBlock(
        ?string $channel = null,
        ?AiAgent $agent = null,
        bool $visitorSandboxLocked = false
    ): string {
        if ($visitorSandboxLocked || self::isAgentExcluded($agent)) {
            return '';
        }
        if (!Schema::hasTable('ai_learned_preferences')) {
            return '';
        }

        $normChannel = $channel ? self::normalizeChannel($channel) : 'all';
        $cacheKey = self::directivesCacheKey($normChannel);

        $rules = Cache::remember($cacheKey, 120, function () use ($normChannel) {
            return AiLearnedPreference::query()
                ->where('is_active', true)
                ->where('scope_key', 'like', self::RULE_SCOPE_PREFIX . '%')
                ->orderByDesc('updated_at')
                ->limit(15)
                ->get(['scope_key', 'rule_summary', 'structured_value'])
                ->filter(function (AiLearnedPreference $pref) use ($normChannel) {
                    if ($normChannel === 'all') {
                        return true;
                    }
                    $metaChannel = is_array($pref->structured_value) ? ($pref->structured_value['channel'] ?? null) : null;
                    return $metaChannel === null || $metaChannel === 'all' || $metaChannel === $normChannel;
                })
                ->take(5)
                ->values()
                ->all();
        });

        if (empty($rules)) {
            return '';
        }

        $lines = ['[AKTIVE VERHALTENSREGELN VON ALINA (VERBINDLICH)]:'];
        $usedChars = mb_strlen($lines[0]);

        foreach ($rules as $rule) {
            $agentScope = is_array($rule->structured_value) ? ($rule->structured_value['ai_agent_id'] ?? null) : null;
            if ($agent && $agentScope && $agentScope !== $agent->id) {
                continue;
            }
            $entry = '- ' . trim((string) $rule->rule_summary);
            if (($usedChars + mb_strlen($entry) + 1) > self::MAX_PROMPT_BLOCK_CHARS) {
                break;
            }
            $lines[] = $entry;
            $usedChars += mb_strlen($entry) + 1;
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * Erzeugt die komplette Kanal-Matrix für das Verhaltens-Cockpit.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildChannelMatrix(int $windowDays = 30, ?string $agentId = null): array
    {
        if (!self::tablesReady()) {
            return [];
        }

        $periodStart = now()->subDays(max(1, $windowDays));
        $signals = AiBehaviorFeedback::query()
            ->where('created_at', '>=', $periodStart)
            ->when($agentId !== null && $agentId !== '', fn ($q) => $q->where('ai_agent_id', $agentId))
            ->get(['channel', 'polarity', 'intensity', 'category']);

        $matrix = [];
        foreach (AiBehaviorFeedback::CHANNELS as $key => $label) {
            $subset = $signals->where('channel', $key);
            $praise = $subset->where('polarity', 'praise')->count();
            $criticism = $subset->where('polarity', 'criticism')->count();
            $correction = $subset->where('polarity', 'correction')->count();
            $total = $subset->count();
            $score = $total > 0 ? self::calculateSatisfactionScore($subset) : null;
            $topIssue = $subset->whereIn('polarity', ['criticism', 'correction'])
                ->groupBy('category')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first();

            $matrix[] = [
                'channel' => $key,
                'label' => $label,
                'total' => $total,
                'praise' => $praise,
                'criticism' => $criticism,
                'correction' => $correction,
                'score' => $score,
                'top_issue' => $topIssue ? (AiBehaviorFeedback::CATEGORIES[$topIssue] ?? $topIssue) : null,
            ];
        }

        return $matrix;
    }

    /**
     * Kompakte Score-Zusammenfassung für einen einzelnen Agenten (z. B. im AiAgentEditor).
     *
     * @return array<string, mixed>
     */
    public static function getAgentCompactSummary(AiAgent $agent, int $windowDays = 30): array
    {
        if (self::isAgentExcluded($agent)) {
            return [
                'excluded' => true,
                'reason' => 'Eingefroren (Kunden-Supporter Funki)',
                'score' => null,
                'praise' => 0,
                'negative' => 0,
                'total' => 0,
                'active_rules' => 0,
            ];
        }

        if (!self::tablesReady()) {
            return [
                'excluded' => false,
                'score' => 100.0,
                'praise' => 0,
                'negative' => 0,
                'total' => 0,
                'active_rules' => 0,
            ];
        }

        $signals = AiBehaviorFeedback::query()
            ->where('ai_agent_id', $agent->id)
            ->where('created_at', '>=', now()->subDays(max(1, $windowDays)))
            ->get(['polarity', 'intensity']);

        $praise = $signals->where('polarity', 'praise')->count();
        $negative = $signals->whereIn('polarity', ['criticism', 'correction'])->count();
        $activeRules = Schema::hasTable('ai_learned_preferences')
            ? AiLearnedPreference::where('is_active', true)
                ->where('scope_key', 'like', self::RULE_SCOPE_PREFIX . '%')
                ->get(['structured_value'])
                ->filter(fn ($p) => empty($p->structured_value['ai_agent_id']) || $p->structured_value['ai_agent_id'] === $agent->id)
                ->count()
            : 0;

        return [
            'excluded' => false,
            'score' => $signals->isNotEmpty() ? self::calculateSatisfactionScore($signals) : null,
            'praise' => $praise,
            'negative' => $negative,
            'total' => $signals->count(),
            'active_rules' => $activeRules,
        ];
    }

    protected static function resolveDefaultInternalAgent(?string $agentName = null): ?AiAgent
    {
        if ($agentName !== null && trim($agentName) !== '') {
            $named = AiAgent::whereRaw('LOWER(name) = ?', [strtolower(trim($agentName))])->first();
            if ($named) {
                return $named;
            }
        }

        return AiAgent::where('name', 'Funkira')->first()
            ?? AiAgent::where('is_active', true)->whereRaw('LOWER(name) != ?', ['funki'])->first();
    }

    protected static function touchProfileAsyncSafe(?string $agentId, string $channel): void
    {
        try {
            self::rebuildProfile(null, 'all', 30);
            self::rebuildProfile(null, $channel, 30);
            if ($agentId !== null && $agentId !== '') {
                self::rebuildProfile($agentId, 'all', 30);
            }
        } catch (\Throwable) {
            // Niemals Live-Chat-Antworten wegen Hintergrund-Aggregation unterbrechen
        }
    }

    protected static function directivesCacheKey(string $channel): string
    {
        return 'ai_behavior_active_directives_v1_' . $channel;
    }

    public static function clearDirectivesCache(): void
    {
        Cache::forget(self::directivesCacheKey('all'));
        foreach (self::ALLOWED_CHANNELS as $chan) {
            Cache::forget(self::directivesCacheKey($chan));
        }
    }
}

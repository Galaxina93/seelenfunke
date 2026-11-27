<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiBehaviorFeedback;
use App\Models\Ai\AiLearnedPreference;
use App\Services\AI\AiAuthHelper;
use App\Services\AI\AiBehaviorAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FunkiraMobileBehaviorController extends Controller
{
    /**
     * Kompakte Kurz-Labels für 1-Tap-Schnell-Chips in der Funkenwerk Android-App.
     */
    private const SHORT_CHIP_LABELS = [
        'too_long' => 'Zu lang',
        'wrong_tool_or_fact' => 'Tool/Fakt falsch',
        'unnecessary_question' => 'Unnötige Rückfrage',
        'wrong_tone' => 'Tonfall',
    ];

    /**
     * GET /api/ai/funkira/behavior/summary
     * Liefert 30T-Kennzahlen, 4 Schnell-Chips, offene Regelvorschläge, aktive Verhaltensregeln
     * und den kompakten System-Prompt-Block für android_live_call / android_app.
     */
    public function summary(Request $request): JsonResponse
    {
        if (!$this->isStrictAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $resolved = $this->resolveTargetAgent(
            $request->query('agent_id') ? (string) $request->query('agent_id') : null,
            $request->query('agent_name') ? (string) $request->query('agent_name') : null
        );
        if ($resolved['excluded']) {
            return $this->excludedFunkiResponse();
        }

        $agent = $resolved['agent'];
        $days = (int) $request->query('days', 30);
        if (!in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        $rawChannel = trim((string) $request->query('channel', 'all'));
        $profileChannel = ($rawChannel === '' || strtolower($rawChannel) === 'all')
            ? 'all'
            : AiBehaviorAnalysisService::normalizeChannel($rawChannel);
        $promptChannel = ($rawChannel === '' || strtolower($rawChannel) === 'all')
            ? 'android_live_call'
            : $profileChannel;

        $profile = AiBehaviorAnalysisService::tablesReady()
            ? AiBehaviorAnalysisService::rebuildProfile($agent?->id, $profileChannel, $days)
            : null;
        $channelMatrix = AiBehaviorAnalysisService::buildChannelMatrix($days, $agent?->id);
        $promptBlock = AiBehaviorAnalysisService::buildActiveBehaviorDirectivesBlock($promptChannel, $agent, false);

        $quickChips = [];
        foreach (AiBehaviorAnalysisService::quickReasonChips() as $code => $meta) {
            $quickChips[] = [
                'code' => $code,
                'label' => $meta['label'],
                'short_label' => self::SHORT_CHIP_LABELS[$code] ?? $meta['label'],
                'category' => $meta['category'],
                'polarity' => $meta['polarity'],
                'intensity' => (int) $meta['intensity'],
            ];
        }

        $activeRules = [];
        if (Schema::hasTable('ai_learned_preferences')) {
            $rows = AiLearnedPreference::query()
                ->where('is_active', true)
                ->where('scope_key', 'like', AiBehaviorAnalysisService::RULE_SCOPE_PREFIX . '%')
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get();

            foreach ($rows as $row) {
                $meta = is_array($row->structured_value) ? $row->structured_value : [];
                $ruleAgentId = $meta['ai_agent_id'] ?? null;
                if ($agent && $ruleAgentId && $ruleAgentId !== $agent->id) {
                    continue;
                }
                $ruleChannel = (string) ($meta['channel'] ?? 'all');
                $ruleCategory = (string) ($meta['category'] ?? 'tone_length');
                $activeRules[] = [
                    'id' => (string) $row->id,
                    'scope_key' => (string) $row->scope_key,
                    'title' => (string) $row->subject_label,
                    'directive' => (string) $row->rule_summary,
                    'channel' => $ruleChannel,
                    'channel_label' => AiBehaviorFeedback::CHANNELS[$ruleChannel] ?? $ruleChannel,
                    'category' => $ruleCategory,
                    'category_label' => AiBehaviorFeedback::CATEGORIES[$ruleCategory] ?? $ruleCategory,
                    'is_active' => (bool) $row->is_active,
                    'updated_at' => $row->updated_at?->toIso8601String(),
                ];
            }
        }

        $praise = (int) ($profile?->praise_count ?? 0);
        $criticism = (int) ($profile?->criticism_count ?? 0);
        $correction = (int) ($profile?->correction_count ?? 0);

        return response()->json([
            'status' => 'success',
            'agent' => [
                'id' => $agent?->id,
                'name' => $agent?->name ?? 'Funkira',
            ],
            'window_days' => $days,
            'channel' => $profileChannel,
            'prompt_channel' => $promptChannel,
            'metrics' => [
                'praise_count' => $praise,
                'criticism_count' => $criticism,
                'correction_count' => $correction,
                'total_count' => $praise + $criticism + $correction,
                'satisfaction_score' => (float) ($profile?->satisfaction_score ?? 100.0),
                'trend_delta' => (float) ($profile?->trend_delta ?? 0.0),
            ],
            'quick_chips' => $quickChips,
            'pending_proposals' => $profile?->pending_rule_proposals ?? [],
            'active_rules' => $activeRules,
            'rule_impact_metrics' => $profile?->rule_impact_metrics ?? [],
            'channel_matrix' => $channelMatrix,
            'prompt_block' => $promptBlock,
        ], 200, $this->noStoreHeaders());
    }

    /**
     * POST /api/ai/funkira/behavior/feedback
     * Erfasst explizite 1-Tap-Reaktionen (explicit_reaction) oder implizite Sprach-/Text-Signale
     * (implicit_text_voice) aus Android-Chat und 3D-Live-Call für Funkira (niemals Funki).
     */
    public function feedback(Request $request): JsonResponse
    {
        if (!$this->isStrictAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'source_type' => 'nullable|string|in:explicit_reaction,implicit_text_voice',
            'channel' => 'nullable|string|max:40',
            'polarity' => 'nullable|string|in:praise,criticism,correction',
            'category' => 'nullable|string|max:40',
            'reason_code' => 'nullable|string|max:240',
            'reason_key' => 'nullable|string|max:240',
            'reason_note' => 'nullable|string|max:240',
            'user_message' => 'nullable|string|max:2000',
            'transcript_text' => 'nullable|string|max:2000',
            'agent_id' => 'nullable|string|max:64',
            'agent_name' => 'nullable|string|max:80',
            'session_id' => 'nullable|string|max:191',
            'ai_chat_memory_id' => 'nullable|string|max:64',
            'user_excerpt' => 'nullable|string|max:1200',
            'agent_excerpt' => 'nullable|string|max:1200',
            'executed_tools' => 'nullable|array|max:12',
            'executed_tools.*' => 'string|max:80',
            'latency_ms' => 'nullable|integer|min:0|max:600000',
        ]);

        $resolved = $this->resolveTargetAgent(
            $validated['agent_id'] ?? null,
            $validated['agent_name'] ?? null
        );
        if ($resolved['excluded']) {
            return $this->excludedFunkiResponse();
        }

        $agent = $resolved['agent'];
        $sourceType = (string) ($validated['source_type'] ?? '');
        $implicitText = trim((string) ($validated['user_message'] ?? $validated['transcript_text'] ?? ''));

        if ($sourceType === '') {
            $sourceType = (!empty($validated['polarity'])) ? 'explicit_reaction' : 'implicit_text_voice';
        }

        if ($sourceType === 'implicit_text_voice') {
            $textToScan = $implicitText !== '' ? $implicitText : trim((string) ($validated['user_excerpt'] ?? ''));
            if ($textToScan === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Für implizites Feedback wird ein Text oder Transkript benötigt.',
                ], 422, $this->noStoreHeaders());
            }

            $channel = AiBehaviorAnalysisService::normalizeChannel($validated['channel'] ?? 'android_live_call');
            $record = AiBehaviorAnalysisService::scanAndRecordImplicitMessage(
                $textToScan,
                $agent,
                $channel,
                [
                    'agent_name' => $agent?->name ?? 'Funkira',
                    'session_id' => $validated['session_id'] ?? null,
                    'ai_chat_memory_id' => $validated['ai_chat_memory_id'] ?? null,
                    'agent_excerpt' => $validated['agent_excerpt'] ?? null,
                    'executed_tools' => $validated['executed_tools'] ?? null,
                    'latency_ms' => $validated['latency_ms'] ?? null,
                    'visitor_sandbox_locked' => false,
                ]
            );

            if (!$record) {
                return response()->json([
                    'status' => 'ignored_neutral',
                    'detected' => false,
                    'source_type' => 'implicit_text_voice',
                    'channel' => $channel,
                    'feedback_id' => null,
                ], 200, $this->noStoreHeaders());
            }

            return response()->json([
                'status' => 'success',
                'detected' => true,
                'source_type' => 'implicit_text_voice',
                'channel' => $record->channel,
                'feedback_id' => (string) $record->id,
                'polarity' => $record->polarity,
                'category' => $record->category,
                'reason_note' => $record->reason_note,
            ], 200, $this->noStoreHeaders());
        }

        if (empty($validated['polarity'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Für explizites Feedback ist polarity (praise, criticism oder correction) erforderlich.',
            ], 422, $this->noStoreHeaders());
        }

        $channel = AiBehaviorAnalysisService::normalizeChannel($validated['channel'] ?? 'android_app');
        $reason = $validated['reason_code'] ?? $validated['reason_key'] ?? $validated['reason_note'] ?? null;

        $record = AiBehaviorAnalysisService::recordExplicitReaction(
            $agent,
            $channel,
            (string) $validated['polarity'],
            $validated['category'] ?? null,
            $reason,
            [
                'agent_name' => $agent?->name ?? 'Funkira',
                'session_id' => $validated['session_id'] ?? null,
                'ai_chat_memory_id' => $validated['ai_chat_memory_id'] ?? null,
                'user_excerpt' => $validated['user_excerpt'] ?? ($implicitText !== '' ? $implicitText : null),
                'agent_excerpt' => $validated['agent_excerpt'] ?? null,
                'executed_tools' => $validated['executed_tools'] ?? null,
                'latency_ms' => $validated['latency_ms'] ?? null,
                'visitor_sandbox_locked' => false,
            ]
        );

        $pendingCount = 0;
        if (AiBehaviorAnalysisService::tablesReady()) {
            $profile = AiBehaviorAnalysisService::rebuildProfile($agent?->id, $channel, 30);
            $pendingCount = count($profile->pending_rule_proposals ?? []);
        }

        return response()->json([
            'status' => 'success',
            'detected' => $record !== null,
            'source_type' => 'explicit_reaction',
            'channel' => $record?->channel ?? $channel,
            'feedback_id' => $record ? (string) $record->id : null,
            'polarity' => $record?->polarity,
            'category' => $record?->category,
            'reason_note' => $record?->reason_note,
            'pending_proposals_count' => $pendingCount,
        ], 200, $this->noStoreHeaders());
    }

    /**
     * POST /api/ai/funkira/behavior/rules/approve
     * 1-Klick-Freigabe eines Regelvorschlags als aktive AiLearnedPreference.
     */
    public function approveRule(Request $request): JsonResponse
    {
        if (!$this->isStrictAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'channel' => 'required|string|max:40',
            'category' => 'required|string|max:40',
            'directive' => 'nullable|string|max:240',
            'title' => 'nullable|string|max:120',
            'agent_id' => 'nullable|string|max:64',
            'agent_name' => 'nullable|string|max:80',
        ]);

        $resolved = $this->resolveTargetAgent(
            $validated['agent_id'] ?? null,
            $validated['agent_name'] ?? null
        );
        if ($resolved['excluded']) {
            return $this->excludedFunkiResponse();
        }

        $agent = $resolved['agent'];
        $channel = AiBehaviorAnalysisService::normalizeChannel($validated['channel']);
        $directive = trim((string) ($validated['directive'] ?? ''));

        $preference = AiBehaviorAnalysisService::approveProposalAsRule(
            $channel,
            (string) $validated['category'],
            $directive,
            $agent?->id,
            $validated['title'] ?? null
        );

        $promptBlock = AiBehaviorAnalysisService::buildActiveBehaviorDirectivesBlock($channel, $agent, false);

        return response()->json([
            'status' => 'success',
            'rule' => [
                'id' => (string) $preference->id,
                'scope_key' => (string) $preference->scope_key,
                'title' => (string) $preference->subject_label,
                'directive' => (string) $preference->rule_summary,
                'channel' => $channel,
                'category' => (string) ($preference->structured_value['category'] ?? $validated['category']),
                'is_active' => (bool) $preference->is_active,
            ],
            'prompt_block' => $promptBlock,
        ], 200, $this->noStoreHeaders());
    }

    /**
     * POST /api/ai/funkira/behavior/rules/dismiss
     * Verwirft einen offenen Regelvorschlag sauber, damit derselbe Cluster nicht erneut erscheint.
     */
    public function dismissRule(Request $request): JsonResponse
    {
        if (!$this->isStrictAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'channel' => 'required|string|max:40',
            'category' => 'required|string|max:40',
            'agent_id' => 'nullable|string|max:64',
            'agent_name' => 'nullable|string|max:80',
        ]);

        $resolved = $this->resolveTargetAgent(
            $validated['agent_id'] ?? null,
            $validated['agent_name'] ?? null
        );
        if ($resolved['excluded']) {
            return $this->excludedFunkiResponse();
        }

        $agent = $resolved['agent'];
        $channel = AiBehaviorAnalysisService::normalizeChannel($validated['channel']);
        $dismissedCount = AiBehaviorAnalysisService::dismissProposal(
            $channel,
            (string) $validated['category'],
            $agent?->id
        );

        return response()->json([
            'status' => 'success',
            'channel' => $channel,
            'category' => (string) $validated['category'],
            'dismissed_count' => $dismissedCount,
        ], 200, $this->noStoreHeaders());
    }

    private function isStrictAdmin(Request $request): bool
    {
        return AiAuthHelper::isAdmin()
            || ($request->user() instanceof Admin)
            || (auth('admin')->user() instanceof Admin)
            || (auth('sanctum')->user() instanceof Admin);
    }

    /**
     * Löst Funkira (oder einen erlaubten internen Agenten) auf und schließt Support-Agent Funki strikt aus.
     *
     * @return array{agent: ?AiAgent, excluded: bool}
     */
    private function resolveTargetAgent(?string $agentId, ?string $agentName): array
    {
        $cleanName = $agentName !== null ? trim($agentName) : null;
        if (AiBehaviorAnalysisService::isAgentExcluded(null, $cleanName)) {
            return ['agent' => null, 'excluded' => true];
        }

        $agent = null;
        if ($agentId !== null && trim($agentId) !== '') {
            $agent = AiAgent::find(trim($agentId));
        }
        if (!$agent && $cleanName !== null && $cleanName !== '') {
            $agent = AiAgent::whereRaw('LOWER(name) = ?', [strtolower($cleanName)])->first();
        }
        if ($agent && AiBehaviorAnalysisService::isAgentExcluded($agent, $cleanName)) {
            return ['agent' => null, 'excluded' => true];
        }

        if (!$agent) {
            $agent = AiAgent::where('name', 'Funkira')->first()
                ?? AiAgent::where('is_active', true)->whereRaw('LOWER(name) != ?', ['funki'])->first();
        }

        if (AiBehaviorAnalysisService::isAgentExcluded($agent, $cleanName)) {
            return ['agent' => null, 'excluded' => true];
        }

        return ['agent' => $agent, 'excluded' => false];
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Nur für Administratoren freigegeben.',
        ], 403, $this->noStoreHeaders());
    }

    private function excludedFunkiResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'ignored_immutable_agent',
            'excluded' => true,
            'message' => 'Support-Agent Funki bleibt strikt eingefroren und ist vom Verhaltens-Tracking ausgeschlossen.',
        ], 422, $this->noStoreHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ];
    }
}

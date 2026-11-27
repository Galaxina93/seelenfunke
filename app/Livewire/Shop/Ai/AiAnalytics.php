<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiBehaviorFeedback;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiMetric;
use App\Models\Ai\AiToolUsage;
use App\Models\System\SystemAiHostingPlan;
use App\Services\AI\AiAuthHelper;
use App\Services\AI\AiBehaviorAnalysisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class AiAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';

    private const ALLOWED_DATE_RANGES = ['7', '30', '90', '365'];
    private const ALLOWED_TABS = ['telemetry', 'behavior'];

    #[Url]
    public string $dateRange = '30';

    #[Url(as: 'tab')]
    public string $activeTab = 'telemetry';

    #[Url(as: 'channel')]
    public string $behaviorChannel = 'all';

    #[Url(as: 'agent')]
    public string $behaviorAgentId = 'all';

    #[Url(as: 'polarity')]
    public string $behaviorPolarity = 'all';

    public ?string $selectedFeedbackId = null;
    public ?string $behaviorNotice = null;

    public function boot(): void
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert: Nur für autorisiertes Personal.');
        }

        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }
        if (!in_array($this->activeTab, self::ALLOWED_TABS, true)) {
            $this->activeTab = 'telemetry';
        }
        if ($this->behaviorChannel !== 'all' && !in_array($this->behaviorChannel, AiBehaviorAnalysisService::ALLOWED_CHANNELS, true)) {
            $this->behaviorChannel = 'all';
        }
        if (!in_array($this->behaviorPolarity, ['all', 'praise', 'criticism', 'correction'], true)) {
            $this->behaviorPolarity = 'all';
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, self::ALLOWED_TABS, true)) {
            $this->activeTab = $tab;
            if ($tab === 'telemetry') {
                $this->dispatch('ai-analytics-updated');
            }
        }
    }

    public function selectBehaviorChannel(string $channel): void
    {
        if ($channel === 'all' || in_array($channel, AiBehaviorAnalysisService::ALLOWED_CHANNELS, true)) {
            $this->behaviorChannel = $channel;
        }
    }

    public function openFeedbackDetail(string $feedbackId): void
    {
        $this->selectedFeedbackId = $feedbackId;
    }

    public function closeFeedbackDetail(): void
    {
        $this->selectedFeedbackId = null;
    }

    public function updateFeedbackStatus(string $feedbackId, string $status): void
    {
        if (!AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Verhaltens-Signale bearbeiten.');
        }
        if (!in_array($status, ['new', 'reviewed', 'dismissed'], true) || !AiBehaviorAnalysisService::tablesReady()) {
            return;
        }

        $fb = AiBehaviorFeedback::find($feedbackId);
        if (!$fb) {
            return;
        }

        $fb->update(['status' => $status]);
        AiBehaviorAnalysisService::rebuildProfile(null, 'all', (int) $this->dateRange);
        $this->behaviorNotice = 'Signal-Status aktualisiert.';
    }

    public function activateProposal(string $channel, string $category, string $directive, ?string $agentId = null): void
    {
        if (!AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Verhaltensregeln aktivieren.');
        }

        $pref = AiBehaviorAnalysisService::activateRuleProposal(
            $channel,
            $category,
            $directive,
            ($agentId && $agentId !== 'all') ? $agentId : null
        );
        $this->behaviorNotice = 'Dauer-Regel aktiviert: ' . $pref->subject_label;
    }

    public function escalateProposal(string $channel, string $category, string $directive, ?string $agentId = null): void
    {
        if (!AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Tickets aus Verhaltens-Clustern erstellen.');
        }

        $ticket = AiBehaviorAnalysisService::escalateProposalToTicket(
            $channel,
            $category,
            $directive,
            ($agentId && $agentId !== 'all') ? $agentId : null
        );
        $this->behaviorNotice = "Entwicklungs-Ticket {$ticket->ticket_number} erstellt.";
    }

    public function dismissProposal(string $channel, string $category, ?string $agentId = null): void
    {
        if (!AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen Vorschläge verwerfen.');
        }

        AiBehaviorAnalysisService::dismissProposal(
            $channel,
            $category,
            ($agentId && $agentId !== 'all') ? $agentId : null
        );
        $this->behaviorNotice = 'Cluster verworfen.';
    }

    public function toggleBehaviorRule(string $preferenceId): void
    {
        if (!AiAuthHelper::isAdmin() || !Schema::hasTable('ai_learned_preferences')) {
            return;
        }

        $pref = AiLearnedPreference::where('id', $preferenceId)
            ->where('scope_key', 'like', AiBehaviorAnalysisService::RULE_SCOPE_PREFIX . '%')
            ->first();
        if (!$pref) {
            return;
        }

        $pref->update(['is_active' => !$pref->is_active]);
        AiBehaviorAnalysisService::clearDirectivesCache();
        $this->behaviorNotice = $pref->is_active ? 'Verhaltensregel reaktiviert.' : 'Verhaltensregel pausiert.';
    }

    public function updatedDateRange(): void
    {
        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }

        $this->dispatch('ai-analytics-updated');
    }

    /**
     * Gibt [inputRatePerMillionEur, outputRatePerMillionEur] passend zur Google Gemini / Vertex AI Modellklasse zurück.
     *
     * @return array{0: float, 1: float}
     */
    public static function resolveModelRatesEur(?string $model): array
    {
        $m = strtolower((string) $model);

        if (str_contains($m, 'pro')) {
            return [1.25, 5.00];
        }

        if (str_contains($m, 'lite')) {
            return [0.08, 0.30];
        }

        if (str_contains($m, 'live') || str_contains($m, 'audio')) {
            return [0.50, 2.00];
        }

        return [0.15, 0.60];
    }

    public static function formatCompactTokens(int|float $count): string
    {
        $val = (float) $count;
        if ($val >= 1000000) {
            $mio = $val / 1000000;

            return ($mio == floor($mio) ? number_format($mio, 0, ',', '.') : number_format($mio, 1, ',', '.')) . ' Mio.';
        }

        if ($val >= 1000) {
            return number_format($val / 1000, 1, ',', '.') . 'k';
        }

        return number_format($val, 0, ',', '.');
    }

    public static function resolveAgentDotClass(?string $color): string
    {
        $clean = trim((string) $color);
        if ($clean === '') {
            return 'bg-emerald-500';
        }

        $base = preg_replace('/-\d{2,3}$/', '', $clean) ?: $clean;

        return match ($base) {
            'emerald' => 'bg-emerald-500',
            'amber', 'yellow' => 'bg-amber-500',
            'sky', 'blue', 'cyan' => 'bg-sky-500',
            'indigo', 'violet', 'purple' => 'bg-indigo-500',
            'rose', 'red', 'pink' => 'bg-rose-500',
            'orange' => 'bg-orange-500',
            'teal' => 'bg-teal-500',
            default => 'bg-slate-500',
        };
    }

    public function render()
    {
        $days = max(1, (int) $this->dateRange);
        $periodStart = now()->subDays($days)->startOfDay();
        $today = now()->startOfDay();

        // 1. KPI: Tokens heute (24h Puls)
        $tokensToday = (int) AiMetric::where('created_at', '>=', $today)
            ->sum(DB::raw('input_tokens + output_tokens'));

        // 2. KPI: Durchschnittliche Latenz im gewählten Zeitraum
        $avgLatency = (int) round((float) (AiMetric::where('type', 'inference')
            ->where('created_at', '>=', $periodStart)
            ->avg('total_time_ms') ?? 0));

        // 3. KPI: Erfolgsrate im gewählten Zeitraum
        $totalInferences = AiMetric::where('type', 'inference')
            ->where('created_at', '>=', $periodStart)
            ->count();
        $successfulInferences = AiMetric::where('type', 'inference')
            ->where('created_at', '>=', $periodStart)
            ->where('is_success', true)
            ->count();
        $successRate = $totalInferences > 0 ? round(($successfulInferences / $totalInferences) * 100, 1) : 100;

        // 4. Hosting Plan & exakte modellbasierte Kostenberechnung im aktuellen Kalendermonat
        $activePlan = class_exists(SystemAiHostingPlan::class)
            ? SystemAiHostingPlan::where('is_active', true)->first()
            : null;

        $monthlyByModel = AiMetric::leftJoin('ai_agents', 'ai_metrics.ai_agent_id', '=', 'ai_agents.id')
            ->whereMonth('ai_metrics.created_at', now()->month)
            ->whereYear('ai_metrics.created_at', now()->year)
            ->select(
                'ai_agents.model',
                DB::raw('SUM(ai_metrics.input_tokens) as input_sum'),
                DB::raw('SUM(ai_metrics.output_tokens) as output_sum')
            )
            ->groupBy('ai_agents.model')
            ->get();

        $inputTokensThisMonth = 0;
        $outputTokensThisMonth = 0;
        $estimatedCostThisMonth = 0.0;

        foreach ($monthlyByModel as $row) {
            $inTok = (int) ($row->input_sum ?? 0);
            $outTok = (int) ($row->output_sum ?? 0);
            $inputTokensThisMonth += $inTok;
            $outputTokensThisMonth += $outTok;

            if ($activePlan && ((float) $activePlan->price_per_million_input_tokens > 0 || (float) $activePlan->price_per_million_output_tokens > 0)) {
                $inRate = (float) $activePlan->price_per_million_input_tokens;
                $outRate = (float) $activePlan->price_per_million_output_tokens;
            } else {
                [$inRate, $outRate] = self::resolveModelRatesEur($row->model);
            }

            $estimatedCostThisMonth += (($inTok / 1000000) * $inRate) + (($outTok / 1000000) * $outRate);
        }

        $monthlyBudgetEur = ($activePlan && (float) $activePlan->price_monthly_eur > 0)
            ? (float) $activePlan->price_monthly_eur
            : 50.00;
        $planDisplayName = $activePlan ? $activePlan->name : 'Vertex AI Postpay';
        $planTokenLimit = $activePlan ? (int) $activePlan->token_limit : 0;

        // 5. Letzte 5 Fehler im gewählten Zeitraum
        $recentErrors = AiMetric::with('agent')
            ->where('created_at', '>=', $periodStart)
            ->where('is_success', false)
            ->latest()
            ->take(5)
            ->get();

        // 6. Chart Data: Token-Verlauf im gewählten Zeitraum
        $dailyTokens = AiMetric::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(input_tokens) as input_sum'),
            DB::raw('SUM(output_tokens) as output_sum')
        )
            ->where('created_at', '>=', $periodStart)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        // 7. Agenten-Verteilung im gewählten Zeitraum
        $agentUsage = AiMetric::with('agent')
            ->select(
                'ai_agent_id',
                DB::raw('COUNT(*) as calls'),
                DB::raw('SUM(input_tokens + output_tokens) as total_tokens'),
                DB::raw('AVG(total_time_ms) as avg_latency')
            )
            ->where('created_at', '>=', $periodStart)
            ->whereNotNull('ai_agent_id')
            ->groupBy('ai_agent_id')
            ->orderByDesc('total_tokens')
            ->get();

        // 8. Top Genutzte Tools im gewählten Zeitraum
        $topTools = AiToolUsage::select(
            'tool_name',
            DB::raw('COUNT(*) as uses'),
            DB::raw('SUM(CASE WHEN is_error = 1 THEN 1 ELSE 0 END) as errors')
        )
            ->where('used_at', '>=', $periodStart)
            ->groupBy('tool_name')
            ->orderByDesc('uses')
            ->take(10)
            ->get();

        // 9. Verhaltensprofil (Lob / Kritik / Closed-Loop Regeln)
        $filterAgentId = ($this->behaviorAgentId !== 'all' && $this->behaviorAgentId !== '') ? $this->behaviorAgentId : null;
        $behaviorProfile = null;
        $channelMatrix = [];
        $behaviorSignals = collect();
        $selectedFeedback = null;
        $allBehaviorRules = collect();
        $openProposalsCount = 0;

        if (AiBehaviorAnalysisService::tablesReady()) {
            $behaviorProfile = AiBehaviorAnalysisService::rebuildProfile($filterAgentId, $this->behaviorChannel, $days);
            $channelMatrix = AiBehaviorAnalysisService::buildChannelMatrix($days, $filterAgentId);
            $openProposalsCount = count($behaviorProfile->pending_rule_proposals ?? []);

            $behaviorSignals = AiBehaviorFeedback::with(['agent', 'learnedPreference'])
                ->where('created_at', '>=', $periodStart)
                ->when($filterAgentId, fn ($q) => $q->where('ai_agent_id', $filterAgentId))
                ->when($this->behaviorChannel !== 'all', fn ($q) => $q->where('channel', $this->behaviorChannel))
                ->when($this->behaviorPolarity !== 'all', fn ($q) => $q->where('polarity', $this->behaviorPolarity))
                ->latest('created_at')
                ->limit(40)
                ->get();

            if ($this->selectedFeedbackId) {
                $selectedFeedback = AiBehaviorFeedback::with(['agent', 'learnedPreference'])->find($this->selectedFeedbackId);
            }
        }

        if (Schema::hasTable('ai_learned_preferences')) {
            $allBehaviorRules = AiLearnedPreference::query()
                ->where('scope_key', 'like', AiBehaviorAnalysisService::RULE_SCOPE_PREFIX . '%')
                ->orderByDesc('is_active')
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get();
        }

        $internalAgents = AiAgent::query()
            ->whereRaw('LOWER(name) != ?', ['funki'])
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return view('livewire.shop.ai.ai-analytics', [
            'tokensToday' => $tokensToday,
            'avgLatency' => $avgLatency,
            'successRate' => $successRate,
            'totalInferences' => $totalInferences,
            'activePlan' => $activePlan,
            'planDisplayName' => $planDisplayName,
            'monthlyBudgetEur' => $monthlyBudgetEur,
            'planTokenLimit' => $planTokenLimit,
            'estimatedCostThisMonth' => $estimatedCostThisMonth,
            'inputTokensThisMonth' => $inputTokensThisMonth,
            'outputTokensThisMonth' => $outputTokensThisMonth,
            'recentErrors' => $recentErrors,
            'dailyTokens' => $dailyTokens,
            'agentUsage' => $agentUsage,
            'topTools' => $topTools,
            'behaviorProfile' => $behaviorProfile,
            'channelMatrix' => $channelMatrix,
            'behaviorSignals' => $behaviorSignals,
            'selectedFeedback' => $selectedFeedback,
            'allBehaviorRules' => $allBehaviorRules,
            'openProposalsCount' => $openProposalsCount,
            'internalAgents' => $internalAgents,
            'isAdminViewer' => AiAuthHelper::isAdmin(),
        ]);
    }
}

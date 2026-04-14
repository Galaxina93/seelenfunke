<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiMetric;
use App\Models\Ai\AiToolUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class AiAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Agenten';

    public function render()
    {
        $today = Carbon::today();
        $thirtyDaysAgo = Carbon::today()->subDays(30);

        // 1. KPI Stats
        $tokensToday = AiMetric::where('type', 'inference')->whereDate('created_at', $today)->sum(DB::raw('input_tokens + output_tokens'));
        $tokensYesterday = AiMetric::where('type', 'inference')->whereDate('created_at', Carbon::yesterday())->sum(DB::raw('input_tokens + output_tokens'));
        $tokensThisMonth = AiMetric::where('type', 'inference')->whereMonth('created_at', $today->month)->whereYear('created_at', $today->year)->sum(DB::raw('input_tokens + output_tokens'));
        
        $inputTokensThisMonth = AiMetric::where('type', 'inference')->whereMonth('created_at', $today->month)->whereYear('created_at', $today->year)->sum('input_tokens') ?? 0;
        $outputTokensThisMonth = AiMetric::where('type', 'inference')->whereMonth('created_at', $today->month)->whereYear('created_at', $today->year)->sum('output_tokens') ?? 0;
        
        $monthlyMetrics = AiMetric::with('agent')
            ->where('type', 'inference')
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->get();
            
        $estimatedCostThisMonth = 0;
        foreach ($monthlyMetrics as $metric) {
            $model = strtolower($metric->agent->model ?? 'gpt-oss-120b');
            $inTokens = $metric->input_tokens;
            $outTokens = $metric->output_tokens;
            
            if (str_contains($model, 'gemini-1.5-pro') || str_contains($model, 'gemini-2.5-pro')) {
                $estimatedCostThisMonth += ($inTokens / 1000000) * 1.25 + ($outTokens / 1000000) * 5.00;
            } elseif (str_contains($model, 'flash')) {
                $estimatedCostThisMonth += ($inTokens / 1000000) * 0.075 + ($outTokens / 1000000) * 0.30;
            } else {
                // Durchschnittswert für andere OSS Modelle (Mittwald/Huggingface)
                $estimatedCostThisMonth += ($inTokens / 1000000) * 0.50 + ($outTokens / 1000000) * 1.50;
            }
        }
        $avgLatency = AiMetric::where('type', 'inference')->whereDate('created_at', '>=', $thirtyDaysAgo)->avg('total_time_ms') ?? 0;
        
        $totalQueries = AiMetric::where('type', 'inference')->whereDate('created_at', '>=', $thirtyDaysAgo)->count();
        $successQueries = AiMetric::where('type', 'inference')->where('is_success', true)->whereDate('created_at', '>=', $thirtyDaysAgo)->count();
        $successRate = $totalQueries > 0 ? round(($successQueries / $totalQueries) * 100, 1) : 100;

        $totalChatMessages = \App\Models\Ai\AiChatMemory::where('role', 'user')
            ->whereDate('created_at', '>=', $thirtyDaysAgo)
            ->count();

        // Include newly decoupled Support Chats explicitly for the Dashboard Sync
        if (class_exists(\App\Models\Support\SupportCustomerChatMessage::class)) {
            $totalChatMessages += \App\Models\Support\SupportCustomerChatMessage::where('sender', 'customer')
                ->whereDate('created_at', '>=', $thirtyDaysAgo)
                ->count();
        }

        // 1.5 Top Tools Used Globally
        $topToolsAllAgents = AiToolUsage::select('tool_name', DB::raw('COUNT(*) as usage_count'))
            ->whereDate('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('tool_name')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get();

        // 2. Cost Trend (Last 30 days)
        $trendDataRaw = AiMetric::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(input_tokens + output_tokens) as total_tokens')
            )
            ->where('type', 'inference')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dates = [];
        $tokens = [];
        // Fill gaps
        for ($i = 30; $i >= 0; $i--) {
            $dateStr = Carbon::today()->subDays($i)->format('Y-m-d');
            $dates[] = Carbon::parse($dateStr)->format('d.m.');
            $tokens[] = $trendDataRaw->firstWhere('date', $dateStr)->total_tokens ?? 0;
        }
        $trendData = ['product_categories' => $dates, 'data' => $tokens];

        // 3. Resource Distribution (Donut Chart)
        $agentLoads = AiMetric::select('ai_agent_id', DB::raw('SUM(input_tokens + output_tokens) as total'))
            ->where('type', 'inference')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('ai_agent_id')
            ->with('agent')
            ->get();
            
        $resourceDistribution = [
            'labels' => $agentLoads->map(fn($item) => $item->agent->name ?? 'Gelöscht')->toArray(),
            'series' => $agentLoads->pluck('total')->toArray(),
        ];

        // 4. Tool Errors (Bar Chart)
        $toolErrorsRaw = AiToolUsage::select('tool_name', DB::raw('COUNT(*) as error_count'))
            ->where('is_error', true)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('tool_name')
            ->orderByDesc('error_count')
            ->limit(5)
            ->get();
            
        $toolErrors = [
            'product_categories' => $toolErrorsRaw->pluck('tool_name')->toArray(),
            'data' => $toolErrorsRaw->pluck('error_count')->toArray(),
        ];

        // 5. Cognitive Load History (Heatmap/Timeline alternative)
        // Zeigt max. tokens der Agents heute an
        $cognitiveLoad = AiMetric::select('ai_agent_id', DB::raw('MAX(input_tokens) as max_tokens'))
            ->where('type', 'inference')
            ->whereDate('created_at', $today)
            ->groupBy('ai_agent_id')
            ->with('agent')
            ->get()
            ->map(function($m) {
                $model = strtolower($m->agent->model ?? '');
                $limit = 32000;
                if (str_contains($model, 'gemini-3') || str_contains($model, 'gemini-2.5-pro')) {
                    $limit = 2000000;
                } elseif (str_contains($model, 'gemini')) {
                    $limit = 1000000;
                } elseif (str_contains($model, '120b') || str_contains($model, 'gpt-4')) {
                    $limit = 120000;
                }

                return [
                    'agent' => $m->agent->name ?? 'Unbekannt',
                    'color' => $m->agent->color ?? 'gray-500',
                    'tokens' => $m->max_tokens,
                    'percent' => min(100, round(($m->max_tokens / $limit) * 100))
                ];
            });

        // 6. Active Hosting Plan
        $activePlan = null;
        if (class_exists(\App\Models\System\SystemAiHostingPlan::class)) {
            $activePlan = \App\Models\System\SystemAiHostingPlan::where('is_active', true)->first();
        }

        return view('livewire.shop.ai.ai-analytics', [
            'tokensToday' => $tokensToday,
            'tokensYesterday' => $tokensYesterday,
            'tokensThisMonth' => $tokensThisMonth,
            'avgLatency' => round($avgLatency),
            'successRate' => $successRate,
            'totalChatMessages' => $totalChatMessages,
            'topToolsAllAgents' => $topToolsAllAgents,
            'trendData' => json_encode($trendData),
            'resourceDistribution' => json_encode($resourceDistribution),
            'toolErrors' => json_encode($toolErrors),
            'cognitiveLoad' => $cognitiveLoad,
            'activePlan' => $activePlan,
            'estimatedCostThisMonth' => $estimatedCostThisMonth,
        ]);
    }
}

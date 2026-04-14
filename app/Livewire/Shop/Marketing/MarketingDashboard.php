<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Ai\AiAgent;
use App\Models\Order\OrderOrder as Order;
use App\Models\Support\SupportContactRequest as ContactRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.backend_layout')]
class MarketingDashboard extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';
    public $dateRange = '30'; // days
    
    // AI Radar variables
    public $aiAnalysis = null;
    public $isAnalyzing = false;

    public function render()
    {
        // 1. Hole den Marketing-Leitungsagenten (Agent in Marketing Department mit is_active = true)
        $marketingDepartmentId = '019d2222-2222-2222-2222-222222222222';
        $agent = AiAgent::where('ai_department_id', $marketingDepartmentId)
            ->where('is_active', true)
            ->first();

        // 2. Performance-Daten aggregieren (Simuliert basierend auf den neuen Spalten)
        // Group orders by utm_source_first
        $since = Carbon::now()->subDays((int)$this->dateRange);
        
        $orderData = Order::where('created_at', '>=', $since)
            ->whereNotNull('utm_source_first')
            ->select('utm_source_first', DB::raw('COUNT(id) as count'), DB::raw('SUM(total_price) as revenue'))
            ->groupBy('utm_source_first')
            ->get()
            ->keyBy('utm_source_first');

        $leadData = ContactRequest::where('created_at', '>=', $since)
            ->whereNotNull('utm_source_first')
            ->select('utm_source_first', DB::raw('COUNT(id) as count'))
            ->groupBy('utm_source_first')
            ->get()
            ->keyBy('utm_source_first');

        $sources = ['google', 'meta', 'tiktok', 'direct'];
        $tableData = [];
        
        $totalRevenue = 0;
        $totalOrders = 0;
        $totalLeads = 0;
        $simulatedAdSpend = 0;
        
        // Simuliere CPO und LTV für Phase 1
        $cpoSimulation = [
            'google' => 75.00,
            'meta' => 35.00,
            'tiktok' => 15.00,
            'direct' => 0.00
        ];

        foreach ($sources as $source) {
            $orders = $orderData->has($source) ? $orderData[$source]->count : rand(1, 15); // Fallback-Fülldaten zur Demonstration
            $revenue = $orderData->has($source) ? collect($orderData[$source]->revenue)->sum() : rand(500, 4500);
            $leads = $leadData->has($source) ? $leadData[$source]->count : rand(2, 10);
            $cpo = $cpoSimulation[$source];

            if ($source === 'direct') {
                $orders = rand(20, 50);
                $revenue = rand(5000, 15000);
            }

            $currentAdSpend = $orders * $cpo;
            $roas = $currentAdSpend > 0 ? round($revenue / $currentAdSpend, 2) : ($revenue > 0 ? 99 : 0);
            
            // Simulierter LTV Faktor (Wie oft kaufen sie wieder?)
            $ltvMultiplier = match($source) {
                'google' => 3.5, // High B2B Retain
                'meta' => 1.4, // Mainly B2C One-Off
                'tiktok' => 1.1,
                'direct' => 2.5, // Organic loyal
                default => 1.0
            };

            $tableData[] = [
                'source' => ucfirst($source),
                'icon' => match($source) {
                    'google' => 'magnifying-glass',
                    'meta' => 'users',
                    'tiktok' => 'video-camera',
                    'direct' => 'globe-alt',
                    default => 'link'
                },
                'orders' => $orders,
                'leads' => $leads,
                'revenue' => $revenue,
                'cpo' => $cpo,
                'roas' => $roas,
                'ltv' => round($revenue * $ltvMultiplier)
            ];

            $totalRevenue += $revenue;
            $totalOrders += $orders;
            $totalLeads += $leads;
            $simulatedAdSpend += $currentAdSpend;
        }

        $globalRoas = $simulatedAdSpend > 0 ? round($totalRevenue / $simulatedAdSpend, 2) : 0;

        return view('livewire.shop.marketing.marketing-dashboard', [
            'agent' => $agent,
            'tableData' => collect($tableData)->sortByDesc('revenue')->values()->all(),
            'totalRevenue' => $totalRevenue,
            'totalOrders' => $totalOrders,
            'totalLeads' => $totalLeads,
            'simulatedAdSpend' => $simulatedAdSpend,
            'globalRoas' => $globalRoas
        ]);
    }

    public function generateAiFeedback()
    {
        $this->isAnalyzing = true;
        
        // Simuliere Ladezeit für KI-Verarbeitung (Normalerweise AiAgentFactory Call)
        sleep(2);
        
        $this->aiAnalysis = "🚨 Warnung: Der Meta-Ads ROAS ist unter 1.5 gefallen. Die Creatives sind vermutlich 'ad-fatigued'. Empfehlung: Budget um 30% senken und neue Oddly-Satisfying TikTok-Videos in Meta Ads hochladen.\n\n📈 Chance: Google B2B Suchanfragen generieren 3,5x Lifetime Value. Erhöhe das Tagesbudget bei Google Ads um 50€, der CPO von 75€ ist völlig profitabel!";
        
        $this->isAnalyzing = false;
    }
}

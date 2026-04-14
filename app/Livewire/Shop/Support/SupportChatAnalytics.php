<?php

namespace App\Livewire\Shop\Support;

use App\Livewire\Traits\WithDepartmentTheming;

use Livewire\Component;
use App\Models\Support\SupportCustomerChat;
use Livewire\WithPagination;

class SupportChatAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Support';

    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $ratingFilter = '';

    public function markAsResolved($id)
    {
        $chat = SupportCustomerChat::find($id);
        if ($chat) {
            $chat->update(['status' => 'resolved_admin']);
        }
    }

    public function render()
    {
        $query = SupportCustomerChat::with(['messages']);

        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('top_topic', 'like', '%' . $this->search . '%')
                  ->orWhere('mentioned_product', 'like', '%' . $this->search . '%')
                  ->orWhere('session_token', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->ratingFilter)) {
            $query->where('rating', $this->ratingFilter);
        }

        $chats = $query->orderByRaw("FIELD(status, 'needs_employee', 'open', 'resolved')")->orderBy('updated_at', 'desc')->paginate(15);

        // KPI Daten
        $openCount = SupportCustomerChat::where('status', 'open')->count();
        $needsEmployeeCount = SupportCustomerChat::where('status', 'needs_employee')->count();
        $resolvedCount = SupportCustomerChat::where('status', 'resolved')->count();
        $resolvedAdminCount = SupportCustomerChat::where('status', 'resolved_admin')->count();
        $resolvedAutoCount = SupportCustomerChat::where('status', 'resolved_auto')->count();

        // Erweiterte Telemetrie KPIs
        $uniqueAiCustomers = SupportCustomerChat::whereNotNull('session_token')->distinct('session_token')->count('session_token');
        $avgResponseTime = (int) SupportCustomerChat::avg('avg_response_time_ms');
        $avgConfidence = (int) SupportCustomerChat::avg('ai_confidence_score');

        // Top 5 Themen / Produkte
        $topTopics = SupportCustomerChat::whereNotNull('top_topic')
            ->select('top_topic', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('top_topic')
            ->orderByDesc('count')
            ->limit(5)->get();

        $topProducts = SupportCustomerChat::whereNotNull('mentioned_product')
            ->select('mentioned_product', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('mentioned_product')
            ->orderByDesc('count')
            ->limit(5)->get();

        // Bewertungs KPIs
        $avgRating = SupportCustomerChat::whereNotNull('rating')->avg('rating') ?? 0;
        $totalRatings = SupportCustomerChat::whereNotNull('rating')->count();
        $ratingBreakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $countForStar = SupportCustomerChat::where('rating', $i)->count();
            $percent = $totalRatings > 0 ? round(($countForStar / $totalRatings) * 100) : 0;
            $ratingBreakdown[$i] = [
                'count' => $countForStar,
                'percent' => $percent
            ];
        }

        $supportAgent = \App\Models\Ai\AiAgent::whereHas('department', function ($query) {
            $query->where('name', 'Support');
        })->where('is_active', true)->first();
        
        $agentName = 'Funki';
        $agentImage = asset('shop/ai/images/funki_selfie.png');
        if ($supportAgent) {
            $agentName = $supportAgent->name;
            if ($supportAgent->profile_picture) {
                $agentImage = \Illuminate\Support\Str::startsWith($supportAgent->profile_picture, 'shop/') ? asset($supportAgent->profile_picture) : Storage::url($supportAgent->profile_picture);
            }
        }

        return view('livewire.shop.support.support-chat-analytics', [
            'chats' => $chats,
            'openCount' => $openCount,
            'needsEmployeeCount' => $needsEmployeeCount,
            'resolvedCount' => $resolvedCount,
            'resolvedAdminCount' => $resolvedAdminCount,
            'resolvedAutoCount' => $resolvedAutoCount,
            'uniqueAiCustomers' => $uniqueAiCustomers,
            'avgResponseTime' => $avgResponseTime,
            'avgConfidence' => $avgConfidence,
            'topTopics' => $topTopics,
            'topProducts' => $topProducts,
            'agentName' => $agentName,
            'agentImage' => $agentImage,
            'avgRating' => $avgRating,
            'totalRatings' => $totalRatings,
            'ratingBreakdown' => $ratingBreakdown
        ])->layout('components.layouts.backend_layout', ['guard' => 'admin']);
    }
}

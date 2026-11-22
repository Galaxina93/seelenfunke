<?php

namespace App\Livewire\Shop\Marketing;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Marketing\MarketingBlogCategory;
use App\Models\Marketing\MarketingBlogPost;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Marketing\MarketingLinktreeVisit;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use App\Models\Marketing\MarketingTrackingLinkHit;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Tracking\PageVisit;
use App\Services\AI\AiAuthHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class MarketingAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    private const ALLOWED_DATE_RANGES = ['7', '30', '90', '365', 'all'];

    private const VOUCHER_TYPE_LABELS = [
        'percentage' => 'Prozent-Rabatt (%)',
        'percent' => 'Prozent-Rabatt (%)',
        'fixed' => 'Festbetrag (€)',
        'fixed_amount' => 'Festbetrag (€)',
        'free_shipping' => 'Gratis-Versand',
        'manual' => 'Manueller Code',
        'auto' => 'Automatik-Kampagne',
    ];

    #[Url]
    public string $dateRange = '30';

    protected ?Carbon $dateFrom = null;
    protected ?Carbon $dateTo = null;

    // Headline KPIs
    public int $kpiVerifiedSubscribers = 0;
    public int $kpiNewSubscribers = 0;
    public int $kpiActiveVouchers = 0;
    public int $kpiPublishedPosts = 0;
    public int $kpiCampaignVisits = 0;

    // Chart Payloads
    public array $newsletterData = ['labels' => [], 'data' => []];
    public array $voucherData = ['labels' => [], 'data' => []];
    public array $blogData = ['labels' => [], 'data' => []];
    public array $voucherTypeData = ['labels' => [], 'data' => []];
    public array $landingPageData = ['labels' => [], 'data' => []];
    public array $topLandingPages = [];

    public function boot(): void
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert: Nur für autorisiertes Personal.');
        }

        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }
    }

    public function mount(): void
    {
        $this->computeAnalytics();
    }

    public function updatedDateRange(): void
    {
        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }

        $this->computeAnalytics();
        $this->dispatch('analytics-updated');
    }

    protected function resolveDateWindow(): array
    {
        $to = Carbon::now()->endOfDay();

        if ($this->dateRange === 'all') {
            $candidates = array_filter([
                MarketingNewsletterSubscriber::min('created_at'),
                MarketingVoucher::min('created_at'),
                PageVisit::min('created_at'),
            ]);
            $from = !empty($candidates)
                ? Carbon::parse(min($candidates))->startOfDay()
                : Carbon::now()->subDays(30)->startOfDay();
        } else {
            $days = max(1, (int) $this->dateRange);
            $from = Carbon::now()->subDays($days)->startOfDay();
        }

        $this->dateFrom = $from;
        $this->dateTo = $to;

        return [$from, $to];
    }

    public function computeAnalytics(): void
    {
        [$from, $to] = $this->resolveDateWindow();
        $groupByMonth = in_array($this->dateRange, ['365', 'all'], true) && $from->diffInDays($to) > 90;

        $buildTimeline = function () use ($from, $to, $groupByMonth): array {
            $map = [];
            $cursor = $groupByMonth ? $from->copy()->startOfMonth() : $from->copy();
            while ($cursor->lte($to)) {
                $key = $groupByMonth ? $cursor->format('m/Y') : $cursor->format('d.m.');
                $map[$key] = 0;
                $cursor = $groupByMonth ? $cursor->addMonth() : $cursor->addDay();
            }

            return $map;
        };

        // 0. Headline KPIs
        $this->kpiVerifiedSubscribers = MarketingNewsletterSubscriber::where('is_verified', true)->count();

        $subscribers = MarketingNewsletterSubscriber::whereBetween('created_at', [$from, $to])
            ->where('is_verified', true)
            ->get(['id', 'created_at']);
        $this->kpiNewSubscribers = $subscribers->count();

        $activePromoVouchers = MarketingVoucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->count();
        $activeGiftVouchers = Schema::hasTable('marketing_gift_vouchers')
            ? MarketingGiftVoucher::where('is_active', true)->where('current_balance', '>', 0)->count()
            : 0;
        $this->kpiActiveVouchers = $activePromoVouchers + $activeGiftVouchers;

        $this->kpiPublishedPosts = MarketingBlogPost::where('status', 'published')->count();

        // 1. Newsletter Growth
        $nlTimeline = $buildTimeline();
        foreach ($subscribers as $sub) {
            if (!$sub->created_at) {
                continue;
            }
            $key = $groupByMonth ? $sub->created_at->format('m/Y') : $sub->created_at->format('d.m.');
            if (array_key_exists($key, $nlTimeline)) {
                $nlTimeline[$key]++;
            }
        }
        $this->newsletterData = [
            'labels' => array_keys($nlTimeline),
            'data' => array_values($nlTimeline),
        ];

        // 2. Vouchers & Gift Vouchers Created Over Time
        $vouchers = MarketingVoucher::whereBetween('created_at', [$from, $to])->get(['id', 'type', 'mode', 'created_at']);
        $giftVouchers = Schema::hasTable('marketing_gift_vouchers')
            ? MarketingGiftVoucher::whereBetween('created_at', [$from, $to])->get(['id', 'created_at'])
            : collect();

        $voucherTimeline = $buildTimeline();
        foreach ($vouchers as $voucher) {
            if ($voucher->created_at) {
                $key = $groupByMonth ? $voucher->created_at->format('m/Y') : $voucher->created_at->format('d.m.');
                if (array_key_exists($key, $voucherTimeline)) {
                    $voucherTimeline[$key]++;
                }
            }
        }
        foreach ($giftVouchers as $gv) {
            if ($gv->created_at) {
                $key = $groupByMonth ? $gv->created_at->format('m/Y') : $gv->created_at->format('d.m.');
                if (array_key_exists($key, $voucherTimeline)) {
                    $voucherTimeline[$key]++;
                }
            }
        }
        $this->voucherData = [
            'labels' => array_keys($voucherTimeline),
            'data' => array_values($voucherTimeline),
        ];

        // 3. Blog Categories Distribution
        $categories = MarketingBlogCategory::withCount(['posts' => function ($q) {
            $q->where('status', 'published');
        }])->get();

        $catLabels = [];
        $catCounts = [];
        foreach ($categories as $cat) {
            if ((int) $cat->posts_count > 0) {
                $catLabels[] = $cat->name;
                $catCounts[] = (int) $cat->posts_count;
            }
        }
        $uncategorizedPublished = MarketingBlogPost::where('status', 'published')->whereNull('blog_category_id')->count();
        if ($uncategorizedPublished > 0) {
            $catLabels[] = 'Allgemein';
            $catCounts[] = $uncategorizedPublished;
        }
        $this->blogData = [
            'labels' => $catLabels,
            'data' => $catCounts,
        ];

        // 4. Voucher Type Distribution (All active/existing vouchers + gift vouchers)
        $allVouchers = MarketingVoucher::select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get();

        $vTypeLabels = [];
        $vTypeValues = [];
        foreach ($allVouchers as $row) {
            $rawKey = strtolower((string) ($row->type ?: 'manual'));
            $label = self::VOUCHER_TYPE_LABELS[$rawKey] ?? ucfirst($rawKey);
            $vTypeLabels[] = $label;
            $vTypeValues[] = (int) $row->total;
        }
        $totalGiftVouchers = Schema::hasTable('marketing_gift_vouchers') ? MarketingGiftVoucher::count() : 0;
        if ($totalGiftVouchers > 0) {
            $vTypeLabels[] = 'Geschenkgutschein';
            $vTypeValues[] = $totalGiftVouchers;
        }
        $this->voucherTypeData = [
            'labels' => $vTypeLabels,
            'data' => $vTypeValues,
        ];

        // 5. Landing Page Visits (PageVisit stores paths WITHOUT leading '/', e.g. 'l/valentinstag', support both!)
        $landingVisits = PageVisit::whereBetween('created_at', [$from, $to])
            ->where(function ($q) {
                $q->where('path', 'like', 'l/%')
                    ->orWhere('path', 'like', '/l/%');
            })
            ->get(['id', 'path', 'created_at']);

        $lpTimeline = $buildTimeline();
        $visitsBySlug = [];

        foreach ($landingVisits as $visit) {
            if ($visit->created_at) {
                $key = $groupByMonth ? $visit->created_at->format('m/Y') : $visit->created_at->format('d.m.');
                if (array_key_exists($key, $lpTimeline)) {
                    $lpTimeline[$key]++;
                }
            }

            $slug = trim((string) preg_replace('#^/?l/#', '', (string) $visit->path), '/');
            if ($slug !== '') {
                $visitsBySlug[$slug] = ($visitsBySlug[$slug] ?? 0) + 1;
            }
        }

        $this->landingPageData = [
            'labels' => array_keys($lpTimeline),
            'data' => array_values($lpTimeline),
        ];

        arsort($visitsBySlug);
        $topSlugs = array_slice($visitsBySlug, 0, 5, true);
        $lpModels = !empty($topSlugs)
            ? MarketingLandingPage::whereIn('slug', array_keys($topSlugs))->get()->keyBy('slug')
            : collect();

        $topList = [];
        foreach ($topSlugs as $slug => $viewsCount) {
            $lpModel = $lpModels->get($slug);
            $topList[] = [
                'title' => $lpModel ? $lpModel->title : ('/l/' . $slug),
                'path' => '/l/' . $slug,
                'views' => (int) $viewsCount,
            ];
        }
        $this->topLandingPages = $topList;

        // Total campaign visits KPI (Landing Pages + Linktree + Tracking Links in timeframe)
        $linktreeHits = Schema::hasTable('marketing_linktree_visits')
            ? MarketingLinktreeVisit::whereBetween('created_at', [$from, $to])->count()
            : 0;
        $trackingHits = Schema::hasTable('marketing_tracking_link_hits')
            ? MarketingTrackingLinkHit::whereBetween('created_at', [$from, $to])->count()
            : 0;

        $this->kpiCampaignVisits = $landingVisits->count() + $linktreeHits + $trackingHits;
    }

    public function render()
    {
        return view('livewire.shop.marketing.marketing-analytics.marketing-analytics');
    }
}

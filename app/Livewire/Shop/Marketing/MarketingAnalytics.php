<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Attributes\Layout;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Marketing\MarketingBlogPost;
use App\Models\Marketing\MarketingBlogCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;


#[Layout('components.layouts.backend_layout')]
class MarketingAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    #[Url]
    public $dateRange = '30';

    public $dateFrom;
    public $dateTo;

    // Analytics State
    public array $newsletterData = [];
    public array $voucherData = [];
    public array $blogData = [];
    public array $voucherTypeData = [];
    public array $landingPageData = [];
    public array $topLandingPages = [];


    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Marketing Analytics.');
        }

        $this->updateDateRange();

    }

    public function updatedDateRange()
    {
        $this->updateDateRange();
    }

    private function updateDateRange()
    {
        if ($this->dateRange === '7') {
            $this->dateFrom = Carbon::now()->subDays(7)->startOfDay();
        } elseif ($this->dateRange === '30') {
            $this->dateFrom = Carbon::now()->subDays(30)->startOfDay();
        } elseif ($this->dateRange === '90') {
            $this->dateFrom = Carbon::now()->subDays(90)->startOfDay();
        } elseif ($this->dateRange === '365') {
            $this->dateFrom = Carbon::now()->subDays(365)->startOfDay();
        } else {
            $this->dateFrom = Carbon::now()->subYears(5)->startOfDay();
        }
        $this->dateTo = Carbon::now()->endOfDay();
    }


    private function computeAnalytics()
    {
        $groupByFormat = in_array($this->dateRange, ['365', 'all']) ? 'Y-m' : 'Y-m-d';

        // 1. Newsletter Growth (Verified only)
        $subscribers = MarketingNewsletterSubscriber::where('is_verified', true)
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->get();
            
        $nlGrouped = $subscribers->groupBy(fn($s) => $s->created_at->format($groupByFormat));
        $nlLabels = [];
        $nlData = [];
        foreach ($nlGrouped->sortKeys() as $gDate => $items) {
            $nlLabels[] = $groupByFormat === 'Y-m' 
                ? Carbon::createFromFormat('Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4)
                : Carbon::createFromFormat('Y-m-d', $gDate)->format('d.m.y');
            $nlData[] = $items->count();
        }
        $this->newsletterData = ['labels' => $nlLabels, 'data' => $nlData];

        // 2. Voucher Generation
        $vouchers = MarketingVoucher::whereBetween('created_at', [$this->dateFrom, $this->dateTo])->get();
        $vcGrouped = $vouchers->groupBy(fn($v) => $v->created_at->format($groupByFormat));
        $vcLabels = [];
        $vcData = [];
        foreach ($vcGrouped->sortKeys() as $gDate => $items) {
            $vcLabels[] = $groupByFormat === 'Y-m' 
                ? Carbon::createFromFormat('Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4)
                : Carbon::createFromFormat('Y-m-d', $gDate)->format('d.m.y');
            $vcData[] = $items->count();
        }
        $this->voucherData = ['labels' => $vcLabels, 'data' => $vcData];

        // 3. Blog Categories (Doughnut)
        $blogs = MarketingBlogPost::whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->with('category')->get();
            
        $blGrouped = $blogs->groupBy('blog_category_id');
        $blLabels = [];
        $blData = [];
        foreach ($blGrouped as $catId => $items) {
            $catName = tap($items->first()->category, fn($c) => $c ? $c->name : null) ?? 'Ohne Kategorie';
            $blLabels[] = $catName;
            $blData[] = $items->count();
        }
        $this->blogData = ['labels' => $blLabels, 'data' => $blData];

        // 4. Voucher Types (Doughnut)
        $vtGrouped = $vouchers->groupBy('type');
        $vtLabels = [];
        $vtData = [];
        foreach ($vtGrouped as $type => $items) {
            $label = match($type) {
                'percentage' => 'Prozentual (%)',
                'absolute' => 'Fester Wert (€)',
                'shipping' => 'Versandkostenfrei',
                default => ucfirst((string)$type)
            };
            $vtLabels[] = $label;
            $vtData[] = $items->count();
        }
        $this->voucherTypeData = ['labels' => $vtLabels, 'data' => $vtData];
        // 5. Landing Page Visits
        $landingPageVisits = \App\Models\Tracking\PageVisit::where('path', 'like', '/l/%')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->get();
            
        $lpGrouped = $landingPageVisits->groupBy(fn($v) => $v->created_at->format($groupByFormat));
        $lpLabels = [];
        $lpData = [];
        foreach ($lpGrouped->sortKeys() as $gDate => $items) {
            $lpLabels[] = $groupByFormat === 'Y-m' 
                ? Carbon::createFromFormat('Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4)
                : Carbon::createFromFormat('Y-m-d', $gDate)->format('d.m.y');
            $lpData[] = $items->count();
        }
        $this->landingPageData = ['labels' => $lpLabels, 'data' => $lpData];

        // 6. Top Landing Pages Ranking
        $topPages = \App\Models\Tracking\PageVisit::where('path', 'like', '/l/%')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->selectRaw('path, count(*) as visits_count')
            ->groupBy('path')
            ->orderByDesc('visits_count')
            ->limit(4)
            ->get();
            
        $enrichedTopPages = [];
        foreach($topPages as $page) {
            $slug = str_replace('/l/', '', $page->path);
            $lp = \App\Models\Marketing\MarketingLandingPage::where('slug', $slug)->with('product')->first();
            $enrichedTopPages[] = [
                'path' => $page->path,
                'slug' => $slug,
                'product_name' => $lp ? $lp->product->name : 'Gelöscht/Unbekannt',
                'visits' => $page->visits_count
            ];
        }
        $this->topLandingPages = $enrichedTopPages;
    }

    public function render()
    {
        $this->computeAnalytics();
        $this->dispatch('analytics-updated');

        return view('livewire.shop.marketing.marketing-analytics.marketing-analytics');
    }
}

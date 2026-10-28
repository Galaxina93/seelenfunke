<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Marketing\MarketingTrackingLink;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Layout('components.layouts.backend_layout')]
class MarketingTracking extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    public $trackingLinks = [];

    // Tracking-Links Form State (Inline & Modal)
    public $showModal = false;
    #[Locked]
    public $editingTrackingId = null;
    public $trackingName = '';
    public $trackingSlug = '';
    public $trackingTargetUrl = '/shop';
    public $trackingUtmSource = '';
    public $trackingUtmMedium = '';
    public $trackingUtmCampaign = '';
    public $trackingIsActive = true;

    public function boot(): void
    {
        $isAdmin = auth('admin')->check();
        $employee = auth('employee')->user();
        $isInternalEmployee = $employee && $employee->isInternalEmployee() && $employee->isAccountActive();
        if (!$isAdmin && !$isInternalEmployee) {
            abort(403);
        }
    }

    public function mount()
    {
        $this->boot();

        $this->loadTrackingLinks();
    }

    public function loadTrackingLinks()
    {
        if (!Schema::hasTable('marketing_tracking_links')) {
            $this->trackingLinks = [];
            return;
        }

        $this->trackingLinks = MarketingTrackingLink::orderByDesc('clicks_count')
            ->orderBy('name')
            ->get()
            ->map(function (MarketingTrackingLink $t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'short_url' => $t->short_url,
                    'full_utm_url' => $t->full_utm_url,
                    'target_url' => $t->target_url,
                    'channel' => $t->channel,
                    'utm_source' => $t->utm_source,
                    'utm_medium' => $t->utm_medium,
                    'utm_campaign' => $t->utm_campaign,
                    'clicks_count' => (int) $t->clicks_count,
                    'unique_clicks_count' => (int) $t->unique_clicks_count,
                    'last_clicked_at' => $t->last_clicked_at ? $t->last_clicked_at->diffForHumans() : null,
                    'is_active' => (bool) $t->is_active,
                ];
            })
            ->toArray();
    }

    public function updatedTrackingName($value)
    {
        if (!$this->editingTrackingId) {
            $this->trackingSlug = Str::slug((string) $value);
        }
    }

    public function applyTrackingPreset(string $preset): void
    {
        $presets = [
            'whatsapp' => ['name' => 'WhatsApp', 'slug' => 'whatsapp'],
            'instagram' => ['name' => 'Instagram Bio', 'slug' => 'instagram-bio'],
            'tiktok' => ['name' => 'TikTok Bio', 'slug' => 'tiktok-bio'],
            'facebook' => ['name' => 'Facebook', 'slug' => 'facebook'],
            'pinterest' => ['name' => 'Pinterest', 'slug' => 'pinterest'],
            'newsletter' => ['name' => 'Newsletter', 'slug' => 'newsletter'],
        ];

        if (!isset($presets[$preset])) {
            return;
        }

        $p = $presets[$preset];
        $this->editingTrackingId = null;
        $this->trackingName = $p['name'];
        $this->trackingSlug = $p['slug'];
        $this->trackingTargetUrl = '/shop';
        $this->trackingIsActive = true;
    }

    public function create()
    {
        $this->resetTrackingForm();
        $this->showModal = true;
    }

    public function resetTrackingForm(): void
    {
        $this->resetValidation();
        $this->editingTrackingId = null;
        $this->trackingName = '';
        $this->trackingSlug = '';
        $this->trackingTargetUrl = '/shop';
        $this->trackingUtmSource = '';
        $this->trackingUtmMedium = '';
        $this->trackingUtmCampaign = '';
        $this->trackingIsActive = true;
        $this->showModal = false;
    }

    public function editTrackingLink(string $id): void
    {
        $this->resetValidation();
        $trk = MarketingTrackingLink::findOrFail($id);
        $this->editingTrackingId = $trk->id;
        $this->trackingName = $trk->name;
        $this->trackingSlug = $trk->slug;
        $this->trackingTargetUrl = $trk->target_url ?: '/shop';
        $this->trackingUtmSource = $trk->utm_source;
        $this->trackingUtmMedium = (string) $trk->utm_medium;
        $this->trackingUtmCampaign = (string) $trk->utm_campaign;
        $this->trackingIsActive = (bool) $trk->is_active;
    }

    public function saveTrackingLink(): void
    {
        if (!Schema::hasTable('marketing_tracking_links')) {
            return;
        }

        $cleanSlug = Str::slug($this->trackingSlug ?: $this->trackingName);
        if ($cleanSlug === '') {
            $cleanSlug = 'link-' . strtolower(Str::random(5));
        }
        $this->trackingSlug = $cleanSlug;

        $uniqueRule = 'unique:marketing_tracking_links,slug';
        if ($this->editingTrackingId) {
            $uniqueRule .= ',' . $this->editingTrackingId;
        }

        $this->validate([
            'trackingName' => 'required|string|max:120',
            'trackingSlug' => ['required', 'string', 'max:100', 'alpha_dash', $uniqueRule],
            'trackingTargetUrl' => 'required|string|max:2048',
        ]);

        // Automatische Ableitung von Quelle, Medium & Kanal aus der Bezeichnung (z.B. "WhatsApp" -> source: whatsapp, channel: social)
        $autoSource = trim((string) $this->trackingUtmSource) !== ''
            ? strtolower(trim((string) $this->trackingUtmSource))
            : str_replace('-', '_', $cleanSlug);

        $channel = MarketingTrackingLink::resolveChannel($autoSource, $this->trackingUtmMedium, $this->trackingName);

        $autoMedium = trim((string) $this->trackingUtmMedium) !== ''
            ? strtolower(trim((string) $this->trackingUtmMedium))
            : match ($channel) {
                'social' => 'social',
                'referral' => 'referral',
                'search' => 'organic',
                default => 'tracking',
            };

        $data = [
            'name' => trim($this->trackingName),
            'slug' => $cleanSlug,
            'target_url' => trim($this->trackingTargetUrl) ?: '/shop',
            'channel' => $channel,
            'utm_source' => $autoSource,
            'utm_medium' => $autoMedium,
            'utm_campaign' => trim((string) $this->trackingUtmCampaign) !== '' ? trim((string) $this->trackingUtmCampaign) : $cleanSlug,
            'is_active' => (bool) $this->trackingIsActive,
        ];

        if ($this->editingTrackingId) {
            $trk = MarketingTrackingLink::findOrFail($this->editingTrackingId);
            $trk->update($data);
        } else {
            MarketingTrackingLink::create($data);
        }

        $this->resetTrackingForm();
        $this->loadTrackingLinks();
        session()->flash('tracking_saved', 'Tracking-Link erfolgreich gespeichert!');
    }

    public function toggleTrackingActive(string $id): void
    {
        $trk = MarketingTrackingLink::findOrFail($id);
        $trk->update(['is_active' => !$trk->is_active]);
        $this->loadTrackingLinks();
    }

    public function deleteTrackingLink(string $id): void
    {
        $trk = MarketingTrackingLink::findOrFail($id);
        $trk->delete();
        $this->loadTrackingLinks();
    }

    public function render()
    {
        $totalTrackingClicks = collect($this->trackingLinks)->sum('clicks_count');
        $totalTrackingUnique = collect($this->trackingLinks)->sum('unique_clicks_count');
        $activeTrackingCount = collect($this->trackingLinks)->where('is_active', true)->count();

        return view('livewire.shop.marketing.marketing-tracking', [
            'totalTrackingClicks' => $totalTrackingClicks,
            'totalTrackingUnique' => $totalTrackingUnique,
            'activeTrackingCount' => $activeTrackingCount,
        ]);
    }
}

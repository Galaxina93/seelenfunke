<?php

namespace App\Livewire\Frontend\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Marketing\MarketingLinktree;
use App\Models\Marketing\MarketingLinktreeVisit;
use App\Models\Marketing\MarketingLinktreeClick;
use Illuminate\Support\Facades\Request;

#[Layout('components.layouts.frontend_layout')]
class MarketingLinktreePage extends Component
{
    public $links = [];
    public $themeColor = '#C5A059';
    public $profileImage;
    public $bgVideo;

    public function mount()
    {
        $this->trackVisit();

        $this->themeColor = shop_setting('linktree_theme_color', '#C5A059');
        $this->profileImage = shop_setting('linktree_profile_image');
        $this->bgVideo = shop_setting('linktree_bg_video');

        $this->links = MarketingLinktree::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function (MarketingLinktree $link) {
                $data = $link->toArray();
                $data['resolved_url'] = $link->resolved_url;
                return $data;
            })
            ->toArray();
    }

    public function trackClick($linkId)
    {
        $ipHash = hash('sha256', Request::ip() . config('app.key') . date('Y-m-d'));
        $device = $this->detectDevice();

        $link = MarketingLinktree::find($linkId);
        if ($link) {
            MarketingLinktreeClick::create([
                'link_id' => $link->id,
                'ip_hash' => $ipHash,
                'device_type' => $device,
            ]);
        }
    }

    public function trackAndRedirect($linkId, $url = null)
    {
        $link = MarketingLinktree::where('is_active', true)->find($linkId);
        if (!$link) {
            return;
        }

        if ($url !== null && $url !== '' && $url !== $link->url && $url !== $link->resolved_url) {
            \App\Models\System\SystemLog::create([
                'type' => 'security',
                'action_id' => 'security:open_redirect_blocked',
                'title' => 'Open-Redirect Manipulationsversuch auf Linktree blockiert',
                'message' => "Manipulierte Ziel-URL '{$url}' für Linktree-ID {$linkId} wurde verworfen.",
                'status' => 'warning',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
            return;
        }

        $this->trackClick($link->id);
        return $this->redirect($link->resolved_url);
    }

    protected function trackVisit()
    {
        $ipHash = hash('sha256', Request::ip() . config('app.key') . date('Y-m-d'));

        // Simple check to avoid duplicate spam within same session/day if wanted,
        // or just log every visit. Let's log if not visited today from this hash:
        $exists = MarketingLinktreeVisit::where('ip_hash', $ipHash)
            ->whereDate('created_at', today())
            ->exists();

        if (!$exists) {
            MarketingLinktreeVisit::create([
                'ip_hash' => $ipHash,
                'referrer' => Request::header('referer'),
                'device_type' => $this->detectDevice(),
            ]);
        }
    }

    protected function detectDevice()
    {
        $agent = strtolower(Request::header('User-Agent'));
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $agent)) {
            return 'tablet';
        }
        if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $agent)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public function render()
    {
        return view('livewire.frontend.marketing.marketing-linktree-page');
    }
}

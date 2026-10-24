<?php

namespace App\Livewire\Frontend\Management;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use App\Models\Management\ManagementLinktree;
use App\Models\Management\ManagementLinktreeVisit;
use App\Models\Management\ManagementLinktreeClick;
use App\Services\System\SystemSecurityService;

#[Layout('components.layouts.frontend_layout')]
class ManagementLinktreePage extends Component
{
    #[Locked]
    public $links = [];
    public $showSecureModal = false;
    #[Locked]
    public $secureLinkUrl = '';
    #[Locked]
    public $secureLinkId = '';

    public $themeColor = '#C5A059';
    public $profileImage = '';

    public function mount()
    {
        $this->themeColor = shop_setting('linktree_theme_color', '#C5A059');
        $this->profileImage = shop_setting('linktree_profile_image');

        $this->links = ManagementLinktree::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        $this->trackVisit();
    }

    protected function trackVisit(): void
    {
        // DSGVO-konforme Anonymisierung (Tages-Salted Hash, keine Klartext-IP)
        $ipHash = hash('sha256', request()->ip() . '|' . config('app.key') . '|' . now()->format('Y-m-d'));
        $referrer = request()->headers->get('referer');

        $deviceType = $this->detectDeviceType((string) request()->header('User-Agent'));

        ManagementLinktreeVisit::create([
            'ip_hash' => $ipHash,
            'referrer' => $referrer ? \Illuminate\Support\Str::limit((string) $referrer, 250, '') : null,
            'device_type' => $deviceType,
        ]);
    }

    protected function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
            return 'mobile';
        }
        return 'desktop';
    }

    protected function recordClickForLink(ManagementLinktree $link): void
    {
        $ipHash = hash('sha256', request()->ip() . '|' . config('app.key') . '|' . now()->format('Y-m-d'));
        $deviceType = $this->detectDeviceType((string) request()->header('User-Agent'));

        ManagementLinktreeClick::create([
            'link_id' => $link->id,
            'ip_hash' => $ipHash,
            'device_type' => $deviceType,
        ]);
    }

    /**
     * Erfasst den Klick asynchron, während der Link nativ in einem neuen Tab (target="_blank") öffnet.
     */
    public function trackClick($id): void
    {
        $link = ManagementLinktree::where('is_active', true)->find($id);
        if (!$link || empty($link->url)) {
            return;
        }

        $this->recordClickForLink($link);
    }

    public function handleLinkClick($id, $url = null, $type = null)
    {
        $link = ManagementLinktree::where('is_active', true)->find($id);
        if (!$link || empty($link->url)) {
            return;
        }

        if ($url !== null && trim((string) $url) !== trim((string) $link->url) && trim((string) $url) !== trim((string) $link->resolved_url)) {
            SystemSecurityService::logEvent(
                'security:open_redirect_blocked',
                'Open-Redirect-Versuch auf Linktree blockiert',
                'Abweichende Ziel-URL bei Linktree-Klick abgewiesen.',
                'warning',
                ['link_id' => $id, 'supplied_url' => substr((string) $url, 0, 120)]
            );
            return;
        }

        if (($link->type ?? $type) === 'secure') {
            $this->secureLinkId = $link->id;
            $this->secureLinkUrl = $link->resolved_url;
            $this->showSecureModal = true;
        } else {
            $this->trackAndRedirect($link->id);
        }
    }

    public function proceedSecureLink(): void
    {
        if (empty($this->secureLinkId)) {
            return;
        }

        $link = ManagementLinktree::where('is_active', true)->find($this->secureLinkId);
        if ($link) {
            $this->recordClickForLink($link);
        }

        $this->showSecureModal = false;
    }

    public function trackAndRedirect($id, $url = null)
    {
        $link = ManagementLinktree::where('is_active', true)->find($id);
        if (!$link || empty($link->url)) {
            return;
        }

        $rawUrl = trim((string) $link->url);
        $resolvedUrl = trim((string) $link->resolved_url);

        if ($url !== null && trim((string) $url) !== $rawUrl && trim((string) $url) !== $resolvedUrl) {
            SystemSecurityService::logEvent(
                'security:open_redirect_blocked',
                'Open-Redirect-Versuch auf Linktree blockiert',
                'Abweichende Ziel-URL in trackAndRedirect abgewiesen.',
                'warning',
                ['link_id' => $id, 'supplied_url' => substr((string) $url, 0, 120)]
            );
            return;
        }

        $targetUrl = $resolvedUrl ?: $rawUrl;
        $scheme = strtolower((string) parse_url($targetUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return;
        }

        $this->recordClickForLink($link);

        return redirect()->away($targetUrl);
    }

    public function render()
    {
        return view('livewire.frontend.management.management-linktree-page');
    }
}

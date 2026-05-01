<?php

namespace App\Livewire\Frontend\Management;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Management\ManagementLinktree;
use App\Models\Management\ManagementLinktreeVisit;
use App\Models\Management\ManagementLinktreeClick;

#[Layout('components.layouts.frontend_layout')]
class ManagementLinktreePage extends Component
{
    public $links = [];
    public $showSecureModal = false;
    public $secureLinkUrl = '';
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

    protected function trackVisit()
    {
        // Simple anonymized tracking using session ID or hashed IP
        $ipHash = md5(request()->ip() . env('APP_KEY'));
        $referrer = request()->headers->get('referer');
        
        // Basic device detection
        $userAgent = request()->header('User-Agent');
        $deviceType = 'desktop';
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
            $deviceType = 'mobile';
        }

        ManagementLinktreeVisit::create([
            'ip_hash' => $ipHash,
            'referrer' => $referrer,
            'device_type' => $deviceType,
        ]);
    }

    public function handleLinkClick($id, $url, $type)
    {
        if ($type === 'secure') {
            $this->secureLinkId = $id;
            $this->secureLinkUrl = $url;
            $this->showSecureModal = true;
        } else {
            $this->trackAndRedirect($id, $url);
        }
    }

    public function proceedSecureLink()
    {
        $this->trackAndRedirect($this->secureLinkId, $this->secureLinkUrl);
    }

    public function trackAndRedirect($id, $url)
    {
        $ipHash = md5(request()->ip() . env('APP_KEY'));
        
        $userAgent = request()->header('User-Agent');
        $deviceType = 'desktop';
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
            $deviceType = 'mobile';
        }

        ManagementLinktreeClick::create([
            'link_id' => $id,
            'ip_hash' => $ipHash,
            'device_type' => $deviceType,
        ]);

        return redirect()->away($url);
    }

    public function render()
    {
        return view('livewire.frontend.management.management-linktree-page');
    }
}

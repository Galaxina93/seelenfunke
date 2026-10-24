<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Marketing\MarketingLinktree as MarketingLinktreeModel;
use App\Models\Marketing\MarketingLinktreeVisit;
use App\Models\Marketing\MarketingLinktreeClick;
use App\Models\System\SystemSetting;

#[Layout('components.layouts.backend_layout')]
class MarketingLinktree extends Component
{
    use WithFileUploads;
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    public $links = [];

    // Form Modal State
    public $showModal = false;
    public $editingId = null;

    // Form Fields
    public $title = '';
    public $url = '';
    public $icon = 'globe-alt';
    public $type = 'standard';
    public $is_active = true;

    // Design Settings (für die öffentliche /links Landingpage)
    public $themeColor = '#C5A059';
    public $profileImage;
    public $bgVideo;
    public $existingProfileImage;
    public $existingBgVideo;

    protected $rules = [
        'title' => 'required|string|max:255',
        'url' => 'required|url|max:255',
        'icon' => 'nullable|string|max:50',
        'type' => 'required|in:standard,secure,highlight',
        'is_active' => 'boolean',
    ];

    public function mount()
    {
        if (!auth('admin')->check() && !auth('employee')->check()) {
            abort(403);
        }

        $this->loadLinks();
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $this->themeColor = shop_setting('linktree_theme_color', '#C5A059');
        $this->existingProfileImage = shop_setting('linktree_profile_image');
        $this->existingBgVideo = shop_setting('linktree_bg_video');
    }

    public function saveSettings()
    {
        $this->validate([
            'themeColor' => 'required|string|max:20',
            'profileImage' => 'nullable|image|max:5120', // 5MB
            'bgVideo' => 'nullable|mimetypes:video/mp4,video/webm|max:51200', // 50MB
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'linktree_theme_color'],
            ['value' => $this->themeColor]
        );

        if ($this->profileImage) {
            $path = $this->profileImage->store('linktree', 'public');
            SystemSetting::updateOrCreate(
                ['key' => 'linktree_profile_image'],
                ['value' => '/storage/' . $path]
            );
            $this->existingProfileImage = '/storage/' . $path;
            $this->profileImage = null;
        }

        if ($this->bgVideo) {
            $path = $this->bgVideo->store('linktree', 'public');
            SystemSetting::updateOrCreate(
                ['key' => 'linktree_bg_video'],
                ['value' => '/storage/' . $path]
            );
            $this->existingBgVideo = '/storage/' . $path;
            $this->bgVideo = null;
        }

        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        session()->flash('settings_saved', 'Design-Einstellungen erfolgreich aktualisiert!');
    }

    public function deleteProfileImage()
    {
        SystemSetting::where('key', 'linktree_profile_image')->delete();
        $this->existingProfileImage = null;
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
    }

    public function deleteBgVideo()
    {
        SystemSetting::where('key', 'linktree_bg_video')->delete();
        $this->existingBgVideo = null;
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
    }

    public function loadLinks()
    {
        $this->links = MarketingLinktreeModel::withCount('clicks')
            ->orderBy('sort_order', 'asc')
            ->get()
            ->toArray();
    }

    public function create()
    {
        $this->resetValidation();
        $this->reset(['editingId', 'title', 'url', 'icon', 'type', 'is_active']);
        $this->is_active = true;
        $this->type = 'standard';
        $this->icon = 'globe-alt';
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $link = MarketingLinktreeModel::findOrFail($id);
        $this->editingId = $link->id;
        $this->title = $link->title;
        $this->url = $link->url;
        $this->icon = $link->icon;
        $this->type = $link->type;
        $this->is_active = $link->is_active;

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->editingId) {
            $link = MarketingLinktreeModel::findOrFail($this->editingId);
            $link->update([
                'title' => $this->title,
                'url' => $this->url,
                'icon' => $this->icon,
                'type' => $this->type,
                'is_active' => $this->is_active,
            ]);
        } else {
            $maxOrder = MarketingLinktreeModel::max('sort_order') ?? 0;
            MarketingLinktreeModel::create([
                'title' => $this->title,
                'url' => $this->url,
                'icon' => $this->icon,
                'type' => $this->type,
                'is_active' => $this->is_active,
                'sort_order' => $maxOrder + 1,
            ]);
        }

        $this->showModal = false;
        $this->loadLinks();
    }

    public function delete($id)
    {
        $link = MarketingLinktreeModel::findOrFail($id);
        $link->delete();
        $this->loadLinks();
    }

    public function toggleActive($id)
    {
        $link = MarketingLinktreeModel::findOrFail($id);
        $link->update(['is_active' => !$link->is_active]);
        $this->loadLinks();
    }

    public function updateOrder($list)
    {
        foreach ($list as $item) {
            MarketingLinktreeModel::where('id', $item['value'])->update(['sort_order' => $item['order']]);
        }
        $this->loadLinks();
    }

    public function moveUp($id)
    {
        $link = MarketingLinktreeModel::findOrFail($id);
        $previous = MarketingLinktreeModel::where('sort_order', '<', $link->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($previous) {
            $temp = $link->sort_order;
            $link->update(['sort_order' => $previous->sort_order]);
            $previous->update(['sort_order' => $temp]);
            $this->loadLinks();
        }
    }

    public function moveDown($id)
    {
        $link = MarketingLinktreeModel::findOrFail($id);
        $next = MarketingLinktreeModel::where('sort_order', '>', $link->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($next) {
            $temp = $link->sort_order;
            $link->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $temp]);
            $this->loadLinks();
        }
    }

    public function render()
    {
        $totalVisits = MarketingLinktreeVisit::count();
        $totalClicks = MarketingLinktreeClick::count();
        $ctr = $totalVisits > 0 ? round(($totalClicks / $totalVisits) * 100, 1) : 0;

        return view('livewire.shop.marketing.marketing-linktree', [
            'totalVisits' => $totalVisits,
            'totalClicks' => $totalClicks,
            'ctr' => $ctr,
        ]);
    }
}

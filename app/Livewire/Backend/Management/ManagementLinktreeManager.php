<?php

namespace App\Livewire\Backend\Management;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Management\ManagementLinktree;
use App\Models\Management\ManagementLinktreeVisit;
use App\Models\Management\ManagementLinktreeClick;
use App\Livewire\Traits\WithDepartmentTheming;

use Livewire\WithFileUploads;
use App\Models\System\SystemSetting;

#[Layout('components.layouts.backend_layout')]
class ManagementLinktreeManager extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    protected string $themingDepartment = 'Leitung';

    public $links = [];

    // Form fields
    public $editId = null;
    public $title = '';
    public $url = '';
    public $icon = 'link';
    public $type = 'standard';
    public $isActive = true;

    // Analytics KPIs
    public $totalVisits = 0;
    public $totalClicks = 0;
    public $globalCtr = 0;

    // Settings
    public $themeColor = '#C5A059';
    public $profileImage;
    public $currentProfileImage;

    public function mount()
    {
        $this->themeColor = shop_setting('linktree_theme_color', '#C5A059');
        $this->currentProfileImage = shop_setting('linktree_profile_image');
        $this->loadData();
    }

    public function loadData()
    {
        $this->links = ManagementLinktree::orderBy('sort_order', 'asc')
            ->withCount('clicks')
            ->get();

        $this->totalVisits = ManagementLinktreeVisit::count();
        $this->totalClicks = ManagementLinktreeClick::count();
        
        if ($this->totalVisits > 0) {
            $this->globalCtr = round(($this->totalClicks / $this->totalVisits) * 100, 1);
        } else {
            $this->globalCtr = 0;
        }
    }

    public function getThemeColorHexProperty()
    {
        return $this->themeColor ?? '#C5A059';
    }

    public function saveSettings()
    {
        $this->validate([
            'themeColor' => 'required|string|max:20',
            'profileImage' => 'nullable|image|max:2048', // max 2MB
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
            $this->currentProfileImage = '/storage/' . $path;
            $this->profileImage = null; // reset input
        }

        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Einstellungen gespeichert!']);
        $this->dispatch('settings-saved');
    }

    public function deleteProfileImage()
    {
        SystemSetting::where('key', 'linktree_profile_image')->delete();
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        $this->currentProfileImage = null;
        $this->profileImage = null;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Profilbild entfernt!']);
    }

    public function create()
    {
        $this->resetForm();
    }

    public function edit($id)
    {
        $link = ManagementLinktree::find($id);
        if ($link) {
            $this->editId = $link->id;
            $this->title = $link->title;
            $this->url = $link->url;
            $this->icon = $link->icon;
            $this->type = $link->type;
            $this->isActive = $link->is_active;
        }
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'url' => 'required|url',
            'icon' => 'nullable|string',
            'type' => 'required|in:standard,secure,highlight',
        ]);

        if ($this->editId) {
            $link = ManagementLinktree::find($this->editId);
            $link->update([
                'title' => $this->title,
                'url' => $this->url,
                'icon' => $this->icon,
                'type' => $this->type,
                'is_active' => $this->isActive,
            ]);
        } else {
            $maxOrder = ManagementLinktree::max('sort_order') ?? 0;
            ManagementLinktree::create([
                'title' => $this->title,
                'url' => $this->url,
                'icon' => $this->icon,
                'type' => $this->type,
                'is_active' => $this->isActive,
                'sort_order' => $maxOrder + 1,
            ]);
        }

        $this->resetForm();
        $this->loadData();
    }

    public function delete($id)
    {
        ManagementLinktree::where('id', $id)->delete();
        $this->loadData();
    }

    public function toggleActive($id)
    {
        $link = ManagementLinktree::find($id);
        if ($link) {
            $link->is_active = !$link->is_active;
            $link->save();
            $this->loadData();
        }
    }

    public function updateOrder($orderedIds)
    {
        foreach ($orderedIds as $index => $id) {
            ManagementLinktree::where('id', $id['value'])->update(['sort_order' => $index]);
        }
        $this->loadData();
    }

    public function resetForm()
    {
        $this->editId = null;
        $this->title = '';
        $this->url = '';
        $this->icon = 'link';
        $this->type = 'standard';
        $this->isActive = true;
    }

    public function render()
    {
        return view('livewire.backend.management.management-linktree-manager');
    }
}

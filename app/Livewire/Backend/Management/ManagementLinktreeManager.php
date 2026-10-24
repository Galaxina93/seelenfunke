<?php

namespace App\Livewire\Backend\Management;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Management\ManagementLinktree;
use App\Models\Management\ManagementLinktreeVisit;
use App\Models\Management\ManagementLinktreeClick;
use App\Models\Management\ManagementTrackingLink;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithFileUploads;
use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Layout('components.layouts.backend_layout')]
class ManagementLinktreeManager extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    protected string $themingDepartment = 'Leitung';

    public $links = [];
    public $trackingLinks = [];

    // Linktree Form fields
    public $editId = null;
    public $title = '';
    public $url = '';
    public $icon = 'link';
    public $type = 'standard';
    public $isActive = true;

    // Fast Tracking Link Form fields
    public $trackingEditId = null;
    public $trackingName = '';
    public $trackingSource = 'instagram';
    public $trackingMedium = 'bio';
    public $trackingSlug = '';
    public $trackingTarget = '/shop';

    // Analytics KPIs
    public $totalVisits = 0;
    public $totalClicks = 0;
    public $totalTrackingClicks = 0;
    public $globalCtr = 0;

    // Settings
    public $themeColor = '#C5A059';
    public $profileImage;
    public $currentProfileImage;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $this->themeColor = shop_setting('linktree_theme_color', '#C5A059');
        $this->currentProfileImage = shop_setting('linktree_profile_image');
        $this->loadData();
    }

    public function loadData()
    {
        $this->links = ManagementLinktree::orderBy('sort_order', 'asc')
            ->withCount('clicks')
            ->get();

        if (Schema::hasTable('management_tracking_links')) {
            $this->trackingLinks = ManagementTrackingLink::orderByDesc('created_at')->get();
            $this->totalTrackingClicks = (int) ManagementTrackingLink::sum('clicks_count');
        } else {
            $this->trackingLinks = collect();
            $this->totalTrackingClicks = 0;
        }

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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $this->validate([
            'themeColor' => ['required', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'profileImage' => 'nullable|image|max:2048',
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'linktree_theme_color'],
            ['value' => $this->themeColor]
        );

        if ($this->profileImage) {
            $path = $this->profileImage->store('marketing/linktree', 'public');
            SystemSetting::updateOrCreate(
                ['key' => 'linktree_profile_image'],
                ['value' => '/storage/' . $path]
            );
            $this->currentProfileImage = '/storage/' . $path;
            $this->profileImage = null;
        }

        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Einstellungen gespeichert!']);
        $this->dispatch('settings-saved');
    }

    public function deleteProfileImage()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $normalizedUrl = trim((string) $this->url);
        if (str_starts_with($normalizedUrl, '/')) {
            $normalizedUrl = rtrim((string) (config('app.url') ?: url('/')), '/') . '/' . ltrim($normalizedUrl, '/');
            $this->url = $normalizedUrl;
        }

        $this->validate([
            'title' => 'required|string|max:255',
            'url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//i'],
            'icon' => 'nullable|string',
            'type' => 'required|in:standard,secure,highlight',
        ]);

        if ($this->editId) {
            $link = ManagementLinktree::find($this->editId);
            if ($link) {
                $link->update([
                    'title' => $this->title,
                    'url' => $this->url,
                    'icon' => $this->icon,
                    'type' => $this->type,
                    'is_active' => $this->isActive,
                ]);
            }
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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        ManagementLinktree::where('id', $id)->delete();
        $this->loadData();
    }

    public function toggleActive($id)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $link = ManagementLinktree::find($id);
        if ($link) {
            $link->is_active = !$link->is_active;
            $link->save();
            $this->loadData();
        }
    }

    public function updateOrder($orderedIds)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        foreach ($orderedIds as $index => $id) {
            $linkId = is_array($id) ? ($id['value'] ?? $id['id'] ?? null) : $id;
            if ($linkId) {
                ManagementLinktree::where('id', $linkId)->update(['sort_order' => $index]);
            }
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

    // =========================================================================
    // TRACKING LINKS (Schnelles & DSGVO-konformes Erstellen von Tracking-IDs)
    // =========================================================================

    public function applyTrackingPreset(string $source, string $medium, string $label, string $target = '/shop'): void
    {
        $this->trackingSource = $source;
        $this->trackingMedium = $medium;
        $this->trackingName = $label;
        $this->trackingTarget = $target;
        $this->generateTrackingId();
    }

    public function generateTrackingId(): void
    {
        $base = Str::slug($this->trackingName ?: ($this->trackingSource . '-' . $this->trackingMedium)) ?: 'trk';
        $base = substr($base, 0, 28);
        $this->trackingSlug = $base . '-' . strtolower(Str::random(4));
    }

    public function editTrackingLink(string $id): void
    {
        if (!Schema::hasTable('management_tracking_links')) {
            return;
        }

        $trk = ManagementTrackingLink::find($id);
        if ($trk) {
            $this->trackingEditId = $trk->id;
            $this->trackingName = $trk->name;
            $this->trackingSource = $trk->utm_source;
            $this->trackingMedium = $trk->utm_medium ?: 'bio';
            $this->trackingSlug = $trk->slug;
            $this->trackingTarget = $trk->target_url ?: '/shop';
        }
    }

    public function saveTrackingLink(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        if (!Schema::hasTable('management_tracking_links')) {
            return;
        }

        $this->trackingName = trim((string) $this->trackingName);
        $this->trackingSource = strtolower(trim((string) $this->trackingSource)) ?: 'instagram';
        $this->trackingMedium = strtolower(trim((string) $this->trackingMedium)) ?: 'bio';
        $this->trackingTarget = trim((string) $this->trackingTarget) ?: '/shop';

        if ($this->trackingName === '') {
            $this->trackingName = ucfirst($this->trackingSource) . ' (' . strtoupper($this->trackingMedium) . ')';
        }

        $cleanSlug = Str::slug($this->trackingSlug ?: $this->trackingName);
        if ($cleanSlug === '') {
            $cleanSlug = 'trk-' . strtolower(Str::random(6));
        }

        // Eindeutigkeit der Tracking-ID sicherstellen
        $slugQuery = ManagementTrackingLink::where('slug', $cleanSlug);
        if ($this->trackingEditId) {
            $slugQuery->where('id', '!=', $this->trackingEditId);
        }
        if ($slugQuery->exists()) {
            $cleanSlug .= '-' . strtolower(Str::random(4));
        }
        $this->trackingSlug = $cleanSlug;

        $this->validate([
            'trackingName' => 'required|string|max:150',
            'trackingSource' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'trackingMedium' => ['nullable', 'string', 'max:60', 'regex:/^[a-zA-Z0-9_\-]*$/'],
            'trackingSlug' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'trackingTarget' => ['required', 'string', 'max:2048', function ($attribute, $value, $fail) {
                $val = trim((string) $value);
                if (!str_starts_with($val, '/') && !preg_match('/^https?:\/\//i', $val)) {
                    $fail('Ziel muss ein interner Pfad (z.B. /shop) oder eine gültige https:// URL sein.');
                }
            }],
        ]);

        $channel = ManagementTrackingLink::resolveChannel($this->trackingSource, (string) $this->trackingMedium);

        if ($this->trackingEditId) {
            $trk = ManagementTrackingLink::find($this->trackingEditId);
            if ($trk) {
                $trk->update([
                    'name' => $this->trackingName,
                    'slug' => $this->trackingSlug,
                    'target_url' => $this->trackingTarget,
                    'channel' => $channel,
                    'utm_source' => $this->trackingSource,
                    'utm_medium' => $this->trackingMedium,
                    'utm_campaign' => $this->trackingSlug,
                ]);
            }
        } else {
            ManagementTrackingLink::create([
                'name' => $this->trackingName,
                'slug' => $this->trackingSlug,
                'target_url' => $this->trackingTarget,
                'channel' => $channel,
                'utm_source' => $this->trackingSource,
                'utm_medium' => $this->trackingMedium,
                'utm_campaign' => $this->trackingSlug,
                'is_active' => true,
            ]);
        }

        $this->resetTrackingForm();
        $this->loadData();
    }

    public function toggleTrackingLink(string $id): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $trk = ManagementTrackingLink::find($id);
        if ($trk) {
            $trk->is_active = !$trk->is_active;
            $trk->save();
            $this->loadData();
        }
    }

    public function deleteTrackingLink(string $id): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        ManagementTrackingLink::where('id', $id)->delete();
        if ($this->trackingEditId === $id) {
            $this->resetTrackingForm();
        }
        $this->loadData();
    }

    public function resetTrackingForm(): void
    {
        $this->trackingEditId = null;
        $this->trackingName = '';
        $this->trackingSource = 'instagram';
        $this->trackingMedium = 'bio';
        $this->trackingSlug = '';
        $this->trackingTarget = '/shop';
    }

    public function render()
    {
        return view('livewire.backend.management.management-linktree-manager');
    }
}

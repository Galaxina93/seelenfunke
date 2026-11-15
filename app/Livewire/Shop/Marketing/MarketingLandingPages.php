<?php

namespace App\Livewire\Shop\Marketing;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use App\Models\Tracking\PageVisit;
use App\Services\ConfiguratorService;
use App\Services\Marketing\HolidayOfferAutopilotService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class MarketingLandingPages extends Component
{
    use WithDepartmentTheming;

    public $themingDepartment = 'Marketing';
    public $loadingMessage = '';
    public $actionError = '';

    // Filter & Ansicht
    public string $search = '';
    public string $filterStatus = 'all'; // all, active, draft
    public string $filterType = 'all';   // all, physical, digital, service
    public string $layoutMode = 'large'; // 'large' (Groß), 'medium' (Mittel), 'list' (Listenansicht)

    // Modal state for editing (1 Landingpage + 13 Vorlagen-Profile)
    public bool $showEditModal = false;
    #[\Livewire\Attributes\Locked]
    public ?string $editingLandingPageId = null;
    public string $selectedProfileSlot = 'standard';
    public bool $editProfileEnabled = true;
    public string $editTitle = '';
    public string $editHeadline = '';
    public string $editSalesCopy = '';
    public string $editCtaText = '';
    public string $editStatus = 'active';

    protected function rules(): array
    {
        return [
            'editTitle'          => 'required|string|max:255',
            'editHeadline'       => 'nullable|string|max:255',
            'editSalesCopy'      => 'nullable|string|max:5000',
            'editCtaText'        => 'nullable|string|max:100',
            'editStatus'         => 'required|in:active,draft',
            'editProfileEnabled' => 'boolean',
        ];
    }

    private function ensureStaffAccess(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function boot(): void
    {
        $this->ensureStaffAccess();
    }

    public function mount()
    {
        $this->ensureStaffAccess();

        // Automatische Synchronisation für alle Produkte (inkl. aller 13 Vorlagen-Profile)
        $products = Product::with('landingPage')
            ->where('slug', 'not like', 'draft-%')
            ->get();

        foreach ($products as $product) {
            MarketingLandingPage::syncForProduct($product, false);
        }
    }

    public function setLayoutMode(string $mode): void
    {
        if ($mode === 'grid') {
            $mode = 'large';
        }
        if (in_array($mode, ['large', 'medium', 'list'], true)) {
            $this->layoutMode = $mode;
        }
    }

    public function updateStatus($landingPageId, string $status): void
    {
        $this->ensureStaffAccess();

        if (!in_array($status, ['active', 'draft'], true)) {
            return;
        }

        $lp = MarketingLandingPage::find($landingPageId);
        if ($lp) {
            $lp->update(['status' => $status]);
        }
    }

    /**
     * Synchronisiert alle Produkte und aktualisiert auf Wunsch alle 13 Vorlagen-Profile anhand der aktuellen Produktdaten.
     */
    public function syncAllLandingPages(bool $forceRegenerateCopy = false): void
    {
        $this->ensureStaffAccess();
        $this->actionError = '';

        $products = Product::where('slug', 'not like', 'draft-%')->get();
        $syncedCount = 0;

        foreach ($products as $product) {
            $lp = MarketingLandingPage::syncForProduct($product, $forceRegenerateCopy);
            if ($lp) {
                $syncedCount++;
            }
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => $forceRegenerateCopy
                ? "Alle {$syncedCount} Landing Pages inkl. 13 Saison- & Feiertags-Profilen erneuert!"
                : "{$syncedCount} Landing Pages erfolgreich geprüft & synchronisiert.",
        ]);
    }

    /**
     * Activates or creates a dynamic landing page (central master-template, no redundant files).
     */
    public function generateLandingPage($productId)
    {
        $this->ensureStaffAccess();
        $this->actionError = '';

        try {
            $product = Product::findOrFail($productId);
            $lp = MarketingLandingPage::syncForProduct($product, true);

            if ($lp && $lp->status !== 'active') {
                $lp->update(['status' => 'active']);
            }

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => 'Landing Page für „' . $product->name . '“ erfolgreich aktiviert!',
            ]);
        } catch (\Exception $e) {
            Log::error('LandingPage Activation Error: ' . $e->getMessage());
            $this->actionError = 'Fehler beim Aktivieren: ' . $e->getMessage();
        }
    }

    /**
     * Generiert alle 13 Vorlagen-Profile (Standard + 4 Jahreszeiten + 8 Feiertage) einer einzelnen Landing Page neu.
     */
    public function regenerateCopy($landingPageId): void
    {
        $this->ensureStaffAccess();

        $lp = MarketingLandingPage::with('product')->findOrFail($landingPageId);
        if (!$lp->product) {
            return;
        }

        MarketingLandingPage::syncForProduct($lp->product, true);

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Alle 13 Vorlagen-Profile für „' . $lp->product->name . '“ wurden neu generiert.',
        ]);
    }

    /**
     * Opens the edit modal for a specific landing page (optional direkt auf einem bestimmten Saison-/Feiertags-Profil).
     */
    public function openEditModal($landingPageId, string $initialSlot = 'standard')
    {
        $this->ensureStaffAccess();

        $lp = MarketingLandingPage::with('product')->findOrFail($landingPageId);
        $this->editingLandingPageId = $lp->id;
        $this->editStatus = $lp->status ?: 'active';
        $this->loadProfileIntoForm($lp, $initialSlot);
        $this->showEditModal = true;
    }

    /**
     * Wechselt im geöffneten Bearbeiten-Modal zwischen 'standard', den 4 Jahreszeiten und den 8 Feiertagen.
     */
    public function selectProfileSlot(string $slot): void
    {
        $this->ensureStaffAccess();

        if (!$this->editingLandingPageId) {
            return;
        }

        $definitions = MarketingLandingPage::getAllProfileDefinitions();
        if (!isset($definitions[$slot])) {
            return;
        }

        $lp = MarketingLandingPage::with('product')->find($this->editingLandingPageId);
        if (!$lp) {
            return;
        }

        $this->resetValidation();
        $this->loadProfileIntoForm($lp, $slot);
    }

    private function loadProfileIntoForm(MarketingLandingPage $lp, string $slot): void
    {
        $definitions = MarketingLandingPage::getAllProfileDefinitions();
        $validSlot = isset($definitions[$slot]) ? $slot : 'standard';
        $this->selectedProfileSlot = $validSlot;

        if ($validSlot === 'standard') {
            $this->editProfileEnabled = true;
            $this->editTitle = $lp->title ?: '';
            $this->editHeadline = $lp->headline ?: '';
            $this->editSalesCopy = $lp->sales_copy ?: '';
            $this->editCtaText = $lp->cta_text ?: '';
            return;
        }

        $storedProfiles = is_array($lp->seasonal_profiles) ? $lp->seasonal_profiles : [];
        $entry = isset($storedProfiles[$validSlot]) && is_array($storedProfiles[$validSlot])
            ? $storedProfiles[$validSlot]
            : null;

        if ($entry && (!empty($entry['headline']) || !empty($entry['sales_copy']))) {
            $this->editProfileEnabled = array_key_exists('enabled', $entry) ? (bool) $entry['enabled'] : true;
            $this->editTitle = (string) ($entry['title'] ?? ($lp->title ?: ''));
            $this->editHeadline = (string) ($entry['headline'] ?? '');
            $this->editSalesCopy = (string) ($entry['sales_copy'] ?? '');
            $this->editCtaText = (string) ($entry['cta_text'] ?? ($lp->cta_text ?: ''));
            return;
        }

        $defaults = $lp->product
            ? MarketingLandingPage::generateSeasonalProfileCopy($lp->product, $validSlot)
            : [
                'title'      => $lp->title ?: '',
                'headline'   => $lp->headline ?: '',
                'sales_copy' => $lp->sales_copy ?: '',
                'cta_text'   => $lp->cta_text ?: '',
            ];

        $this->editProfileEnabled = $entry ? (bool) ($entry['enabled'] ?? true) : true;
        $this->editTitle = $defaults['title'];
        $this->editHeadline = $defaults['headline'];
        $this->editSalesCopy = $defaults['sales_copy'];
        $this->editCtaText = $defaults['cta_text'];
    }

    /**
     * Füllt im geöffneten Modal die Felder des aktuell gewählten Profils intelligent aus den Produktdaten vor.
     */
    public function applySmartDefaultsInModal(): void
    {
        $this->ensureStaffAccess();

        if (!$this->editingLandingPageId) {
            return;
        }

        $lp = MarketingLandingPage::with('product')->find($this->editingLandingPageId);
        if (!$lp || !$lp->product) {
            return;
        }

        $defaults = ($this->selectedProfileSlot === 'standard')
            ? MarketingLandingPage::generateDefaultCopy($lp->product)
            : MarketingLandingPage::generateSeasonalProfileCopy($lp->product, $this->selectedProfileSlot);

        $this->editTitle = $defaults['title'];
        $this->editHeadline = $defaults['headline'];
        $this->editSalesCopy = $defaults['sales_copy'];
        $this->editCtaText = $defaults['cta_text'];
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingLandingPageId = null;
        $this->selectedProfileSlot = 'standard';
        $this->resetValidation();
    }

    /**
     * Speichert das aktuell gewählte Profil, ohne das Modal zu schließen (ideal zum Durcharbeiten mehrerer Feiertage).
     */
    public function saveCurrentProfileOnly(): void
    {
        $this->persistCurrentProfile(false);
    }

    /**
     * Speichert das aktuell gewählte Profil + Status und schließt das Modal.
     */
    public function saveEdit()
    {
        $this->persistCurrentProfile(true);
    }

    private function persistCurrentProfile(bool $closeModal): void
    {
        $this->ensureStaffAccess();
        $this->validate();

        if (!$this->editingLandingPageId) {
            return;
        }

        $lp = MarketingLandingPage::with('product')->findOrFail($this->editingLandingPageId);

        $cleanTitle = trim(str_replace(' – ', ': ', $this->editTitle));
        $cleanHeadline = trim(str_replace(' – ', ': ', $this->editHeadline));
        $cleanSalesCopy = MarketingLandingPage::sanitizeLegalCopy($this->editSalesCopy);
        $cleanCta = trim(str_replace(' – ', ' ', $this->editCtaText));

        if ($this->selectedProfileSlot === 'standard') {
            $lp->update([
                'title'      => $cleanTitle,
                'headline'   => $cleanHeadline,
                'sales_copy' => $cleanSalesCopy,
                'cta_text'   => $cleanCta,
                'status'     => $this->editStatus,
            ]);
            $savedLabel = 'Standard (Zeitlos)';
        } else {
            $profiles = is_array($lp->seasonal_profiles) ? $lp->seasonal_profiles : [];
            if ($lp->product && empty($profiles)) {
                $profiles = MarketingLandingPage::generateAllSeasonalProfiles($lp->product, [], false);
            }

            $profiles[$this->selectedProfileSlot] = [
                'enabled'    => (bool) $this->editProfileEnabled,
                'title'      => $cleanTitle,
                'headline'   => $cleanHeadline,
                'sales_copy' => $cleanSalesCopy,
                'cta_text'   => $cleanCta,
            ];

            $lp->update([
                'seasonal_profiles' => $profiles,
                'status'            => $this->editStatus,
            ]);
            $savedLabel = $this->selectedProfileSlot;
        }

        if ($closeModal) {
            $this->closeEditModal();
        }

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => "Profil „{$savedLabel}“ erfolgreich gespeichert!",
        ]);
    }

    /**
     * Toggles between active and draft status.
     */
    public function toggleStatus($landingPageId)
    {
        $this->ensureStaffAccess();

        $lp = MarketingLandingPage::findOrFail($landingPageId);
        $lp->status = ($lp->status === 'active') ? 'draft' : 'active';
        $lp->save();

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Status auf ' . ($lp->status === 'active' ? 'Aktiv' : 'Entwurf') . ' gesetzt.',
        ]);
    }

    /**
     * Deletes a landing page and any custom override blade file.
     */
    public function deleteLandingPage($landingPageId)
    {
        $this->ensureStaffAccess();

        $lp = MarketingLandingPage::findOrFail($landingPageId);
        $productName = $lp->product ? $lp->product->name : $lp->title;

        $safeSlug = Str::slug((string) $lp->slug);
        if ($safeSlug !== '' && Auth::guard('admin')->check()) {
            $customBlade = resource_path('views/frontend/pages/landingpages/' . $safeSlug . '.blade.php');
            if (File::exists($customBlade)) {
                File::delete($customBlade);
            }
        }

        $lp->delete();

        $this->dispatch('notify', [
            'type'    => 'warning',
            'message' => 'Landing Page für „' . $productName . '“ wurde zurückgesetzt.',
        ]);
    }

    /**
     * Optional advanced feature: creates an individual blade override file if developer wants custom HTML.
     */
    public function createCustomOverrideFile($productId)
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren dürfen Blade-Template-Dateien im Dateisystem anlegen.');
        }

        try {
            $product = Product::findOrFail($productId);
            $slug = Str::slug((string) $product->slug);
            if ($slug === '') {
                $this->actionError = 'Ungültiger Produkt-Slug.';
                return;
            }
            $bladePath = resource_path('views/frontend/pages/landingpages/' . $slug . '.blade.php');

            $stubPath = resource_path('views/stubs/landing-page-template.stub');
            $content = File::exists($stubPath) ? File::get($stubPath) : '';

            if (!File::exists(dirname($bladePath))) {
                File::makeDirectory(dirname($bladePath), 0755, true);
            }
            File::put($bladePath, $content);

            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => 'Individuelle Blade-Datei unter /landingpages/' . $slug . '.blade.php angelegt!',
            ]);
        } catch (\Exception $e) {
            $this->actionError = 'Fehler beim Erstellen der Datei: ' . $e->getMessage();
        }
    }

    /**
     * Entfernt ein manuelles Blade-Override, damit wieder das zentrale dynamische Master-Template greift.
     */
    public function deleteCustomOverrideFile($productId): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren dürfen Blade-Template-Dateien im Dateisystem entfernen.');
        }

        $product = Product::findOrFail($productId);
        $slug = Str::slug((string) $product->slug);
        if ($slug === '') {
            return;
        }

        $bladePath = resource_path('views/frontend/pages/landingpages/' . $slug . '.blade.php');
        if (File::exists($bladePath)) {
            File::delete($bladePath);
        }

        $this->dispatch('notify', [
            'type'    => 'info',
            'message' => 'Individueller Override entfernt. Das Produkt nutzt wieder das dynamische Master-Template.',
        ]);
    }

    public function render()
    {
        $query = Product::with([
            'landingPage',
            'categories',
            'tierPrices',
            'templates' => fn($q) => $q->where('is_active', true),
        ])
        ->withCount([
            'reviews as approved_reviews_count' => fn($q) => $q->where('status', 'approved'),
        ])
        ->withAvg([
            'reviews as approved_reviews_avg' => fn($q) => $q->where('status', 'approved'),
        ], 'rating')
        ->where('slug', 'not like', 'draft-%');

        if ($this->search !== '') {
            $escapedSearch = addcslashes(trim($this->search), '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('name', 'like', '%' . $escapedSearch . '%')
                  ->orWhere('slug', 'like', '%' . $escapedSearch . '%')
                  ->orWhereHas('landingPage', function ($lpq) use ($escapedSearch) {
                      $lpq->where('title', 'like', '%' . $escapedSearch . '%')
                          ->orWhere('headline', 'like', '%' . $escapedSearch . '%');
                  });
            });
        }

        if ($this->filterType !== 'all') {
            $query->where('type', $this->filterType);
        }

        if ($this->filterStatus === 'active') {
            $query->whereHas('landingPage', fn($lpq) => $lpq->where('status', 'active'));
        } elseif ($this->filterStatus === 'draft') {
            $query->where(function ($q) {
                $q->whereDoesntHave('landingPage')
                  ->orWhereHas('landingPage', fn($lpq) => $lpq->where('status', 'draft'));
            });
        }

        $products = $query->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        // Besuchs-Statistiken für /l/{slug} laden
        $visitCountsTotal = [];
        $visitCounts30d = [];
        try {
            $visitCountsTotal = PageVisit::where('path', 'like', '/l/%')
                ->selectRaw('path, count(*) as total')
                ->groupBy('path')
                ->pluck('total', 'path')
                ->all();

            $visitCounts30d = PageVisit::where('path', 'like', '/l/%')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('path, count(*) as total')
                ->groupBy('path')
                ->pluck('total', 'path')
                ->all();
        } catch (\Throwable $e) {
            // Fehlertoleranz
        }

        $activeTheme = app(ConfiguratorService::class)->getCurrentActiveTheme();
        $autopilotEnabled = app(HolidayOfferAutopilotService::class)->isEnabled();
        $editingLandingPage = $this->editingLandingPageId
            ? MarketingLandingPage::with(['product.templates' => fn($q) => $q->where('is_active', true)])->find($this->editingLandingPageId)
            : null;
        $profileDefinitions = MarketingLandingPage::getAllProfileDefinitions();

        return view('livewire.shop.marketing.marketing-landing-pages', [
            'products'           => $products,
            'visitCountsTotal'   => $visitCountsTotal,
            'visitCounts30d'     => $visitCounts30d,
            'activeTheme'        => $activeTheme,
            'autopilotEnabled'   => $autopilotEnabled,
            'editingLandingPage' => $editingLandingPage,
            'profileDefinitions' => $profileDefinitions,
        ]);
    }
}

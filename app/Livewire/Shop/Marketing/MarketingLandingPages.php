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

    // Modal state for editing
    public bool $showEditModal = false;
    #[\Livewire\Attributes\Locked]
    public ?string $editingLandingPageId = null;
    public string $editTitle = '';
    public string $editHeadline = '';
    public string $editSalesCopy = '';
    public string $editCtaText = '';
    public string $editStatus = 'active';

    protected function rules(): array
    {
        return [
            'editTitle'     => 'required|string|max:255',
            'editHeadline'  => 'nullable|string|max:255',
            'editSalesCopy' => 'nullable|string|max:5000',
            'editCtaText'   => 'nullable|string|max:100',
            'editStatus'    => 'required|in:active,draft',
        ];
    }

    private function ensureStaffAccess(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
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

        // Automatische Synchronisation für alle Produkte (inkl. neu angelegter Produkte)
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
     * Synchronisiert alle Produkte und aktualisiert auf Wunsch die Marketingtexte anhand der aktuellen Produktdaten.
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
                ? "Alle {$syncedCount} Landing Pages wurden mit frischen Produkttexten synchronisiert!"
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
     * Generiert die Marketingtexte (Titel, Headline, Verkaufstext, CTA) einer einzelnen Landing Page
     * intelligent aus den aktuellen Produktdaten neu.
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
            'message' => 'Marketingtexte für „' . $lp->product->name . '“ wurden aus den Produktdaten neu generiert.',
        ]);
    }

    /**
     * Opens the edit modal for a specific landing page.
     */
    public function openEditModal($landingPageId)
    {
        $this->ensureStaffAccess();

        $lp = MarketingLandingPage::findOrFail($landingPageId);
        $this->editingLandingPageId = $lp->id;
        $this->editTitle = $lp->title ?: '';
        $this->editHeadline = $lp->headline ?: '';
        $this->editSalesCopy = $lp->sales_copy ?: '';
        $this->editCtaText = $lp->cta_text ?: '';
        $this->editStatus = $lp->status ?: 'active';
        $this->showEditModal = true;
    }

    /**
     * Füllt im geöffneten Modal die Felder mit den intelligenten Produkt-Standardtexten.
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

        $defaults = MarketingLandingPage::generateDefaultCopy($lp->product);
        $this->editTitle = $defaults['title'];
        $this->editHeadline = $defaults['headline'];
        $this->editSalesCopy = $defaults['sales_copy'];
        $this->editCtaText = $defaults['cta_text'];
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingLandingPageId = null;
        $this->resetValidation();
    }

    /**
     * Saves the modified landing page copy and status.
     */
    public function saveEdit()
    {
        $this->ensureStaffAccess();
        $this->validate();

        if (!$this->editingLandingPageId) {
            return;
        }

        $lp = MarketingLandingPage::findOrFail($this->editingLandingPageId);
        $lp->update([
            'title'      => $this->editTitle,
            'headline'   => $this->editHeadline,
            'sales_copy' => $this->editSalesCopy,
            'cta_text'   => $this->editCtaText,
            'status'     => $this->editStatus,
        ]);

        $this->closeEditModal();
        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Landing Page Inhalte erfolgreich aktualisiert!',
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

        // Delete custom blade file if one exists (nur für Administratoren und gültige Slugs)
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
            ? MarketingLandingPage::with('product')->find($this->editingLandingPageId)
            : null;

        return view('livewire.shop.marketing.marketing-landing-pages', [
            'products'           => $products,
            'visitCountsTotal'   => $visitCountsTotal,
            'visitCounts30d'     => $visitCounts30d,
            'activeTheme'        => $activeTheme,
            'autopilotEnabled'   => $autopilotEnabled,
            'editingLandingPage' => $editingLandingPage,
        ]);
    }
}

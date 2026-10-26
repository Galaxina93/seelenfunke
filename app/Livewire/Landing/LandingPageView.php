<?php

namespace App\Livewire\Landing;

use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Models\Tracking\PageVisit;
use App\Services\ConfiguratorService;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.frontend_layout')]
class LandingPageView extends Component
{
    #[\Livewire\Attributes\Locked]
    public MarketingLandingPage $landingPage;

    public ?string $requestedHoliday = null;
    public ?string $requestedTemplateId = null;

    public function mount($slug)
    {
        $cleanSlug = trim((string) $slug);

        $landingPage = MarketingLandingPage::with([
            'product.categories',
            'product.tierPrices',
            'product.templates' => fn($q) => $q->where('is_active', true),
        ])->where('slug', $cleanSlug)->first();

        // Fallback für neu angelegte Produkte, deren Landing Page noch nicht initialisiert wurde
        if (!$landingPage && $cleanSlug !== '' && !str_starts_with($cleanSlug, 'draft-')) {
            $product = Product::where('slug', $cleanSlug)->first();
            if ($product) {
                $landingPage = MarketingLandingPage::syncForProduct($product);
                $landingPage?->load([
                    'product.categories',
                    'product.tierPrices',
                    'product.templates' => fn($q) => $q->where('is_active', true),
                ]);
            }
        }

        if (!$landingPage) {
            abort(404);
        }

        $this->landingPage = $landingPage;

        // Security check: Draft landing pages or inactive products must not be publicly visible to customers/guests/external partners
        $isStaff = auth()->guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isEmployee()
            || \App\Services\AI\AiAuthHelper::isAdmin();

        if (!$isStaff) {
            if ($this->landingPage->status !== 'active') {
                abort(404);
            }
            if (!$this->landingPage->product || $this->landingPage->product->status !== 'active') {
                abort(404);
            }
        }

        $rawHoliday = trim((string) request()->query('holiday', ''));
        $this->requestedHoliday = $rawHoliday !== '' ? mb_substr($rawHoliday, 0, 60) : null;

        $rawTemplateId = trim((string) (request()->query('t') ?: request()->query('template', '')));
        $this->requestedTemplateId = $rawTemplateId !== '' ? mb_substr($rawTemplateId, 0, 64) : null;

        // Tracker logic (DSGVO-konform mit App-Key-Salt und Längenbegrenzung)
        try {
            $pathFilter = '/l/' . mb_substr($cleanSlug, 0, 180);
            $ipHash = hash('sha256', (string) Request::ip() . config('app.key'));

            $alreadyVisited = PageVisit::where('path', $pathFilter)
                ->where('ip_hash', $ipHash)
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            if (!$alreadyVisited) {
                $safeUrl = preg_replace('/([?&](?:token|signature|password|code|key)=)[^&]+/i', '$1[REDACTED]', (string) Request::fullUrl());
                $safeReferer = Request::header('referer')
                    ? preg_replace('/([?&](?:token|signature|password|code|key)=)[^&]+/i', '$1[REDACTED]', (string) Request::header('referer'))
                    : null;

                PageVisit::create([
                    'session_id' => mb_substr((string) (session()->getId() ?: 'no-session'), 0, 100),
                    'ip_hash'    => $ipHash,
                    'url'        => mb_substr((string) $safeUrl, 0, 500),
                    'path'       => $pathFilter,
                    'method'     => mb_substr((string) Request::method(), 0, 10),
                    'user_agent' => Request::userAgent() ? mb_substr((string) Request::userAgent(), 0, 500) : null,
                    'referer'    => $safeReferer ? mb_substr((string) $safeReferer, 0, 500) : null,
                ]);
            }
        } catch (\Exception $e) {
            // Fehlertoleranz
        }
    }

    public function render()
    {
        $product = $this->landingPage->product;

        if (!$product) {
            abort(404);
        }

        $product->loadMissing([
            'categories',
            'tierPrices',
            'templates' => fn($q) => $q->where('is_active', true),
        ]);

        $product->loadCount([
            'reviews as approved_reviews_count' => fn($q) => $q->where('status', 'approved'),
        ]);
        $product->loadAvg([
            'reviews as approved_reviews_avg' => fn($q) => $q->where('status', 'approved'),
        ], 'rating');

        $approvedReviews = $product->reviews()
            ->with('customer')
            ->where('status', 'approved')
            ->orderByDesc('rating')
            ->latest()
            ->limit(3)
            ->get();

        $activeTemplates = $product->templates->where('is_active', true)->values();

        // 1. Prüfe auf explizite Vorlage (?t=...) oder saisonale Vorlage
        $selectedTemplate = null;
        if ($this->requestedTemplateId) {
            $selectedTemplate = $activeTemplates->firstWhere('id', $this->requestedTemplateId);
        }

        $seasonalTemplate = $selectedTemplate
            ?: ($product->isAutoSeasonalMainImageEnabled() ? $product->resolveSeasonalTemplate($this->requestedHoliday) : null);

        // 2. Hauptbild (Hero) auflösen
        if ($selectedTemplate && !empty($selectedTemplate->preview_image)) {
            $heroImageMeta = [
                'path'            => $selectedTemplate->preview_image,
                'url'             => ProductTemplate::resolveImageUrl($selectedTemplate->preview_image),
                'is_ai_generated' => (bool) ($selectedTemplate->is_ai_generated ?? true),
                'label'           => $selectedTemplate->name,
            ];
        } else {
            $heroImageMeta = $product->resolveMainImageMeta($this->requestedHoliday, true);
        }

        // 3. Interaktive Hero-Medien-Galerie aufbauen (Saisonbild + Standard-Galerie ohne Duplikate)
        $heroGallery = [];
        if (!empty($heroImageMeta['url'])) {
            $heroGallery[] = [
                'type'            => 'image',
                'url'             => $heroImageMeta['url'],
                'path'            => $heroImageMeta['path'] ?? null,
                'alt'             => $heroImageMeta['label'] ?? $product->name,
                'label'           => $seasonalTemplate ? ($seasonalTemplate->holiday ?: $seasonalTemplate->name) : ($heroImageMeta['label'] ?? null),
                'is_ai_generated' => (bool) ($heroImageMeta['is_ai_generated'] ?? false),
                'is_seasonal'     => $seasonalTemplate !== null,
                'template_id'     => $seasonalTemplate?->id,
            ];
        }

        foreach ($product->media_gallery ?? [] as $media) {
            if (!is_array($media) || empty($media['path'])) {
                continue;
            }
            $mType = $media['type'] ?? 'image';
            $mUrl = $mType === 'video'
                ? asset('storage/' . ltrim((string) $media['path'], '/'))
                : ProductTemplate::resolveImageUrl($media['path']);

            if (!$mUrl) {
                continue;
            }

            $alreadyIncluded = collect($heroGallery)->contains(fn($g) => $g['url'] === $mUrl || (!empty($g['path']) && $g['path'] === $media['path']));
            if (!$alreadyIncluded) {
                $heroGallery[] = [
                    'type'            => $mType,
                    'url'             => $mUrl,
                    'path'            => $media['path'],
                    'alt'             => $media['alt'] ?? $product->name,
                    'label'           => $media['holiday'] ?? null,
                    'is_ai_generated' => filter_var($media['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_seasonal'     => !empty($media['holiday']) && mb_strtolower((string) $media['holiday']) !== 'standard',
                    'template_id'     => null,
                ];
            }
        }

        // 4. Detailbild für Sektion 3 (bewusst anderes Bild als Hero, falls vorhanden)
        $excludeForDetail = !empty($heroImageMeta['path']) ? [$heroImageMeta['path']] : [];
        $detailImageMeta = $product->resolveMainImageMeta(null, false, $excludeForDetail);
        if (empty($detailImageMeta['url'])) {
            $detailImageMeta = $heroImageMeta;
        }

        // 5. Sortiere Vorlagen so, dass die aktuell aktive Saison-/Feiertagsvorlage an erster Stelle steht
        $sortedTemplates = $activeTemplates->sortByDesc(function (ProductTemplate $tpl) use ($seasonalTemplate) {
            return ($seasonalTemplate && $tpl->id === $seasonalTemplate->id) ? 1 : 0;
        })->values();

        // 6. Aktive Jahreszeit / Feiertags-Kontext
        $activeTheme = app(ConfiguratorService::class)->getCurrentActiveTheme();

        // 7. Dynamisches Produkt-Profil (USPs, Badges, Story-Checkliste)
        $dynamicProfile = MarketingLandingPage::buildDynamicProfile($product, $heroImageMeta, $seasonalTemplate);

        // 8. Primäre Ziel-URL (bei expliziter Vorlagen-Kampagne direkt mit ?t=...)
        $primaryCtaUrl = $selectedTemplate
            ? route('product.show', ['product' => $product->slug, 't' => $selectedTemplate->id])
            : route('product.show', $product->slug);

        // 9. Cross-Selling Empfehlungen
        $crossSell = $product->resolveCrossSellProducts(4);

        // Relation auf dem Public-Model entfernen, damit Livewires wire:snapshot keine internen Product-Attribute serialisiert
        $this->landingPage->unsetRelation('product');

        return view('livewire.landing.landing-page-view', [
            'product'          => $product,
            'heroImageMeta'    => $heroImageMeta,
            'detailImageMeta'  => $detailImageMeta,
            'heroGallery'      => $heroGallery,
            'seasonalTemplate' => $seasonalTemplate,
            'sortedTemplates'  => $sortedTemplates,
            'activeTheme'      => $activeTheme,
            'dynamicProfile'   => $dynamicProfile,
            'primaryCtaUrl'    => $primaryCtaUrl,
            'approvedReviews'  => $approvedReviews,
            'crossSell'        => $crossSell,
        ]);
    }
}


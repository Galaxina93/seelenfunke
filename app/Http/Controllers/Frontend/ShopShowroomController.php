<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceShopMaintenanceMode;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Delivery\DeliverySetting;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\LavaParkourService;
use App\Services\Gamification\LobbyInstanceService;
use App\Services\Shop\ShowroomMultiplayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ShopShowroomController extends Controller
{
    /**
     * Read-Only JSON-Endpunkt für den begehbaren 3D-Louvre-Showroom auf /shop.
     * Liefert aktive Produkte inkl. komprimierter .glb-Modelle, Galerie (inkl. Maßbild),
     * exakt 3 Vorteilen sowie das Funki-3D-Modell des Besuchers (Kunde Lv. 1..10, Gast Lv. 2).
     */
    public function index(
        Request $request,
        ShowroomMultiplayerService $multiplayer,
        LavaParkourService $lavaParkour
    ): JsonResponse {
        if ($maintenanceResp = $this->checkMaintenanceOrLock($request)) {
            return $maintenanceResp;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        $tamper = LobbyInstanceService::inspectRequestTampering(
            $request->all(),
            $request->path(),
            $customer !== null ? ('customer:' . $customer->id) : null
        );

        if ($tamper['has_explicit_cheat']) {
            return $this->tamperRejectedResponse();
        }

        $products = $this->resolveCachedShowroomProducts();
        $multiplayerPayload = $multiplayer->buildBootstrapPayload($customer);
        $playerFunki = $this->resolvePlayerFunki($multiplayerPayload);
        $lavaParkourPayload = $lavaParkour->buildShowroomPayload($customer);
        $environmentPayload = $this->resolveShowroomEnvironment();
        $playerPayload = $this->resolveShowroomPlayer(
            $customer,
            $multiplayerPayload,
            (bool) ($lavaParkourPayload['master_key_unlocked'] ?? false)
        );

        return response()->json([
            'success' => true,
            'showroom_style' => 'louvre_black_gold',
            'total_products' => count($products),
            'environment' => $environmentPayload,
            'player' => $playerPayload,
            'player_funki' => $playerFunki,
            'multiplayer' => $multiplayerPayload,
            'lava_parkour' => $lavaParkourPayload,
            'products' => $products,
        ], 200, $this->noStoreHeaders());
    }

    /**
     * 5-Hz Multi-User State-Sync für eingeloggte Kunden im 12er-Louvre-Raum (Gäste strikt 403 Solo).
     */
    public function sync(Request $request, ShowroomMultiplayerService $multiplayer): JsonResponse
    {
        if ($maintenanceResp = $this->checkMaintenanceOrLock($request)) {
            return $maintenanceResp;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $tamper = LobbyInstanceService::inspectRequestTampering(
            $request->all(),
            $request->path(),
            $customer !== null ? ('customer:' . $customer->id) : null
        );

        if ($customer === null) {
            return response()->json([
                'success' => false,
                'message' => 'Gäste erkunden den Showroom im Einzelmodus.',
            ], 403, $this->noStoreHeaders());
        }

        if ($tamper['has_explicit_cheat']) {
            return $this->tamperRejectedResponse();
        }

        if (!$multiplayer->isMultiplayerEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Der Mehrspielermodus ist aktuell deaktiviert.',
            ], 403, $this->noStoreHeaders());
        }

        $syncInput = $request->only([
            'x',
            'y',
            'z',
            'rotY',
            'rot_y',
            'speed',
            'zone',
            'jumping',
            'weaponActionSeq',
            'soloMode',
            'emote',
        ]);

        $sanctumAccess = LobbyInstanceService::evaluateSanctumAccess($customer);
        $rawRequestedZone = isset($syncInput['zone']) && is_string($syncInput['zone'])
            ? strtolower(trim($syncInput['zone']))
            : 'lobby';
        $wasSanctumClamped = false;

        if (in_array($rawRequestedZone, ['heroes', 'lava'], true) && !$sanctumAccess['can_enter_sanctums']) {
            $syncInput['zone'] = 'gallery';
            if (array_key_exists('z', $syncInput) && LobbyInstanceService::isValidFiniteNumeric($syncInput['z']) && (float) $syncInput['z'] < -14.0) {
                $syncInput['z'] = -14.0;
            }
            $wasSanctumClamped = true;
        }

        $payload = $multiplayer->syncPlayerState($customer, $syncInput);
        $payload['can_enter_sanctums'] = $sanctumAccess['can_enter_sanctums'];
        $payload['sanctum_block_reason'] = $sanctumAccess['sanctum_block_reason'];

        if ($wasSanctumClamped) {
            $payload['zone'] = 'lobby';
            $payload['sanctum_clamped'] = true;
        }

        return response()->json($payload, 200, $this->noStoreHeaders());
    }

    /**
     * Moderierter Raum-Chat mit 4-stufigem TikTok-Basis-Wort-, Link- & PII-Filter (Gäste strikt 403).
     */
    public function chat(Request $request, ShowroomMultiplayerService $multiplayer): JsonResponse
    {
        if ($maintenanceResp = $this->checkMaintenanceOrLock($request)) {
            return $maintenanceResp;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $tamper = LobbyInstanceService::inspectRequestTampering(
            $request->all(),
            $request->path(),
            $customer !== null ? ('customer:' . $customer->id) : null
        );

        if ($customer === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nur eingeloggte Kunden können den Showroom-Chat nutzen.',
            ], 403, $this->noStoreHeaders());
        }

        if ($tamper['has_explicit_cheat']) {
            return $this->tamperRejectedResponse();
        }

        if (!$multiplayer->isMultiplayerEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Der Showroom-Chat ist aktuell deaktiviert.',
            ], 403, $this->noStoreHeaders());
        }

        $rawMessage = $request->input('message', '');
        if (!is_string($rawMessage)) {
            LobbyInstanceService::logTamperThrottled('customer:' . $customer->id, $request->path(), 'non_string_chat_message');

            return response()->json([
                'success' => false,
                'code' => 'invalid_payload',
                'message' => 'Ungültiges Nachrichtenformat.',
            ], 422, $this->noStoreHeaders());
        }

        $result = $multiplayer->postChatMessage($customer, $rawMessage);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? 'blocked',
                'message' => $result['message'] ?? 'Nachricht blockiert.',
            ], (int) ($result['status'] ?? 422), $this->noStoreHeaders());
        }

        return response()->json([
            'success' => true,
            'room_id' => $result['room_id'],
            'entry' => $result['entry'],
        ], 200, $this->noStoreHeaders());
    }

    /**
     * 1-Klick Spieler-Meldung im Raum-Chat (2 Meldungen -> 24h Shadow-Mute).
     */
    public function report(Request $request, ShowroomMultiplayerService $multiplayer): JsonResponse
    {
        if ($maintenanceResp = $this->checkMaintenanceOrLock($request)) {
            return $maintenanceResp;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $tamper = LobbyInstanceService::inspectRequestTampering(
            $request->all(),
            $request->path(),
            $customer !== null ? ('customer:' . $customer->id) : null
        );

        if ($customer === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nur eingeloggte Kunden können Meldungen absenden.',
            ], 403, $this->noStoreHeaders());
        }

        if ($tamper['has_explicit_cheat']) {
            return $this->tamperRejectedResponse();
        }

        if (!$multiplayer->isMultiplayerEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Der Mehrspielermodus ist aktuell deaktiviert.',
            ], 403, $this->noStoreHeaders());
        }

        $rawTarget = $request->input('target_player_id', '');
        if (!is_string($rawTarget)) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültige Spieler-Kennung.',
            ], 422, $this->noStoreHeaders());
        }

        $result = $multiplayer->reportPlayerByPublicId($customer, $rawTarget);

        return response()->json([
            'success' => $result['success'],
            'reports_count' => $result['reports_count'] ?? 0,
            'shadow_muted' => $result['shadow_muted'] ?? false,
            'message' => $result['message'] ?? 'Meldung verarbeitet.',
        ], (int) ($result['status'] ?? 200), $this->noStoreHeaders());
    }

    private function tamperRejectedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'tamper_rejected',
            'message' => 'Unzulässige Anfrageparameter erkannt.',
        ], 422, $this->noStoreHeaders());
    }

    private function checkMaintenanceOrLock(Request $request): ?JsonResponse
    {
        $isMaintenance = filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN)
            || filter_var(shop_setting('shop_maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);

        if (!EnforceShopMaintenanceMode::isShopLive($request) || ($isMaintenance && !Auth::guard('admin')->check())) {
            return response()->json([
                'success' => false,
                'message' => 'Mein Seelenfunke befindet sich aktuell im Wartungsmodus.',
            ], 503, [
                'Retry-After' => '3600',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-Robots-Tag' => 'noindex, noarchive',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCachedShowroomProducts(): array
    {
        $baseScope = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact');

        $activeCount = (int) (clone $baseScope)->count();
        $latestStamp = (string) ((clone $baseScope)->max('updated_at') ?? '0');
        $isSmallBusiness = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);
        $taxRateKey = (string) shop_setting('default_tax_rate', 19);
        $freeThreshold = (int) shop_setting('shipping_free_threshold', 5000);
        $shippingCost = (int) shop_setting('shipping_cost', 490);

        $cacheKey = 'sf_louvre_showroom_v1_' . md5(implode('|', [
            $activeCount,
            $latestStamp,
            $isSmallBusiness ? 'sb1' : 'sb0',
            $taxRateKey,
            $freeThreshold,
            $shippingCost,
            now()->format('Y-m-d'),
        ]));

        return Cache::remember($cacheKey, 120, function () use ($isSmallBusiness, $freeThreshold, $shippingCost) {
            $items = Product::where('status', 'active')
                ->where('slug', '!=', 'seelenfunke-ingame-artifact')
                ->with([
                    'categories',
                    'tierPrices',
                    'supplier',
                    'templates' => fn ($q) => $q->where('is_active', true),
                ])
                ->orderByRaw("CASE WHEN three_d_model_path IS NOT NULL AND three_d_model_path != '' THEN 0 ELSE 1 END ASC")
                ->orderByRaw("CASE WHEN type = 'physical' THEN 0 ELSE 1 END ASC")
                ->latest()
                ->get();

            $mapped = [];
            foreach ($items as $product) {
                $mapped[] = $this->mapProductForShowroom($product, $isSmallBusiness, $freeThreshold, $shippingCost);
            }

            return $mapped;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProductForShowroom(
        Product $product,
        bool $isSmallBusiness,
        int $freeThresholdCents,
        int $shippingCostCents
    ): array {
        $isVoucher = $product->slug === 'geschenkgutschein' || ((int) $product->price === 0 && $product->is_personalizable);
        $grossCents = $isVoucher && (int) $product->gross_price <= 0
            ? 500
            : (int) $product->gross_price;

        $priceFormatted = $isVoucher && (int) $product->price === 0
            ? 'ab 5,00 €'
            : number_format($grossCents / 100, 2, ',', '.') . ' €';

        $taxNote = $isSmallBusiness
            ? 'Keine MwSt. gem. § 19 UStG'
            : ($product->tax_included ? 'inkl. MwSt.' : 'zzgl. MwSt.');

        if ($product->type === 'digital') {
            $shippingNote = 'Sofort-Download (versandkostenfrei)';
        } elseif ($product->type === 'service') {
            $shippingNote = 'Dienstleistung (kein Versand)';
        } elseif ($grossCents >= $freeThresholdCents) {
            $shippingNote = 'Kostenloser Versand';
        } else {
            $shippingNote = 'zzgl. ' . number_format($shippingCostCents / 100, 2, ',', '.') . ' € Versand';
        }

        $rawDesc = $product->short_description ?: Str::limit(trim(strip_tags((string) $product->description)), 180);
        $cleanDesc = $this->cleanText((string) $rawDesc);

        $firstCategory = $product->categories->first();
        $categoryName = $this->cleanText((string) ($firstCategory?->name ?: $product->collection_line_label));

        $deliveryLabel = $this->resolveDeliveryLabel($product);
        $galleryImages = $this->resolveGalleryImages($product);
        $previewImageUrl = $galleryImages[0]['url'] ?? asset('shop/projekt/logo/mein-seelenfunke-logo.png');
        $glbUrl = $this->resolveGlbUrl($product);

        return [
            'id' => (string) $product->id,
            'name' => $this->cleanText((string) $product->name),
            'slug' => (string) $product->slug,
            'type' => (string) $product->type,
            'collection_world' => $product->resolveCollectionWorld(),
            'is_personalizable' => (bool) $product->is_personalizable,
            'is_available' => $product->isAvailable(),
            'can_direct_add_to_cart' => !$product->is_personalizable && $product->slug !== 'geschenkgutschein' && $product->isAvailable(),
            'price_cents' => $grossCents,
            'price_formatted' => $priceFormatted,
            'tax_shipping_label' => $taxNote . ' · ' . $shippingNote,
            'short_description' => $cleanDesc,
            'category_name' => $categoryName,
            'delivery_label' => $deliveryLabel,
            'product_url' => '/produkt/' . $product->slug,
            'glb_url' => $glbUrl,
            'material_hint' => $this->resolveMaterialHint($product),
            'dimensions_mm' => [
                'width' => max(0, (int) ($product->width ?? 0)),
                'height' => max(0, (int) ($product->height ?? 0)),
                'length' => max(0, (int) ($product->length ?? 0)),
            ],
            'preview_image_url' => $previewImageUrl,
            'gallery_images' => $galleryImages,
            'benefits' => $this->resolveProductBenefits($product),
        ];
    }

    private function resolveDeliveryLabel(Product $product): string
    {
        if ($product->slug === 'geschenkgutschein') {
            return 'Sofort-PDF oder gravierte Karte (3 bis 5 Werktage)';
        }
        if ($product->type === 'digital') {
            return 'Sofort-Download nach Zahlungseingang';
        }
        if ($product->type === 'service') {
            return 'Termin nach persönlicher Vereinbarung';
        }

        try {
            $raw = DeliverySetting::getCurrentDeliveryText($product);
        } catch (\Throwable $e) {
            $raw = 'Lieferzeit: 3-5 Werktage';
        }

        return $this->cleanText($raw, ' bis ');
    }

    private function resolveGlbUrl(Product $product): ?string
    {
        $rawPath = trim((string) ($product->three_d_model_path ?? ''));
        if ($rawPath === '' || str_contains($rawPath, '..') || str_contains($rawPath, "\0") || str_contains($rawPath, '\\')) {
            return null;
        }

        $cleanRel = ltrim((string) preg_replace('#^/?storage/#', '', $rawPath), '/');
        if (!str_ends_with(strtolower($cleanRel), '.glb')) {
            return null;
        }

        if (str_starts_with($cleanRel, 'shop/')) {
            $pubDisk = public_path($cleanRel);
            $ver = is_file($pubDisk) ? ('?v=' . filemtime($pubDisk)) : '';

            return '/' . $cleanRel . $ver;
        }

        $storageDisk = storage_path('app/public/' . $cleanRel);
        $ver = is_file($storageDisk) ? ('?v=' . filemtime($storageDisk)) : '';

        return '/storage/' . $cleanRel . $ver;
    }

    /**
     * @return array<int, array{url: string, alt: string, is_ai_generated: bool, is_dimension_sheet: bool}>
     */
    private function resolveGalleryImages(Product $product): array
    {
        $regularImages = [];
        $dimensionImages = [];
        $seenUrls = [];

        $mainMeta = $product->resolveMainImageMeta(null, $product->isAutoSeasonalMainImageEnabled());
        if (!empty($mainMeta['url'])) {
            $mainUrl = (string) $mainMeta['url'];
            $seenUrls[$mainUrl] = true;
            $regularImages[] = [
                'url' => $mainUrl,
                'alt' => $this->cleanText((string) ($mainMeta['label'] ?? $product->name)),
                'is_ai_generated' => (bool) ($mainMeta['is_ai_generated'] ?? false),
                'is_dimension_sheet' => false,
            ];
        }

        $gallery = is_array($product->media_gallery) ? $product->media_gallery : [];
        foreach ($gallery as $media) {
            $path = null;
            $alt = (string) $product->name;
            $isAi = false;
            $type = 'image';

            if (is_array($media)) {
                $type = (string) ($media['type'] ?? 'image');
                $path = isset($media['path']) ? trim((string) $media['path']) : null;
                $bindingLabel = $product->resolveMediaVariantBindingLabel($media['variant_binding'] ?? null);
                $alt = (string) ($media['alt'] ?? ($bindingLabel ? ($product->name . ' · ' . $bindingLabel) : $product->name));
                $isAi = filter_var($media['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_string($media) && trim($media) !== '') {
                $path = trim($media);
            }

            if ($type !== 'image' || empty($path)) {
                continue;
            }

            $url = ProductTemplate::resolveImageUrl($path);
            if (!$url || isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;

            $haystack = mb_strtolower($path . ' ' . $alt);
            $isDimension = str_contains($haystack, 'massbild')
                || str_contains($haystack, 'maßbild')
                || str_contains($haystack, 'massblatt')
                || str_contains($haystack, 'maßblatt')
                || str_contains($haystack, 'dimensions')
                || str_contains($haystack, 'abmessung');

            $entry = [
                'url' => (string) $url,
                'alt' => $this->cleanText($alt),
                'is_ai_generated' => $isAi && !$isDimension,
                'is_dimension_sheet' => $isDimension,
            ];

            if ($isDimension) {
                $dimensionImages[] = $entry;
            } else {
                $regularImages[] = $entry;
            }
        }

        $combined = array_merge($regularImages, $dimensionImages);

        if (empty($combined)) {
            $fallbackUrl = asset('shop/projekt/logo/mein-seelenfunke-logo.png');
            $combined[] = [
                'url' => $fallbackUrl,
                'alt' => $this->cleanText((string) $product->name),
                'is_ai_generated' => false,
                'is_dimension_sheet' => false,
            ];
        }

        return $combined;
    }

    /**
     * Exakt 3 kurze, abmahnsichere Seelenfunke-Vorteile ohne KI-Gedankenstriche (Baustein 2 & 5).
     *
     * @return array<int, string>
     */
    private function resolveProductBenefits(Product $product): array
    {
        $city = trim((string) shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'))) ?: 'Gifhorn';

        if ($product->slug === 'geschenkgutschein') {
            return [
                'Sofort-PDF oder gravierte Aluminiumkarte wählbar',
                'Frei einlösbar im gesamten Manufaktur-Sortiment',
                "Liebevoll gestaltet in Deutschland (Manufaktur {$city})",
            ];
        }

        if ($product->type === 'digital') {
            return [
                'Sofortiger Download direkt nach Zahlungseingang',
                'Dauerhaft im persönlichen Kundenkonto abrufbar',
                "Konzipiert in Deutschland (Manufaktur {$city})",
            ];
        }

        if ($product->type === 'service') {
            return [
                "Persönliche Betreuung aus unserer Manufaktur {$city}",
                'Individuelle Abstimmung auf dein Wunschprojekt',
                'Transparente Festpreise ohne versteckte Kosten',
            ];
        }

        $attrs = is_array($product->attributes) ? $product->attributes : [];
        $materialRaw = mb_strtolower(trim((string) ($attrs['Material'] ?? '') . ' ' . $product->slug . ' ' . $product->name));

        if ($product->isAtelierLine()) {
            $secondBenefit = str_contains($materialRaw, '925') || str_contains($materialRaw, 'silber')
                ? 'Massives 925er Sterling Silber mit präziser Gravur'
                : 'Echtes 585er und 333er Gold mit feinster Lasergravur';

            return [
                "Handveredelt in Deutschland (Manufaktur {$city})",
                $secondBenefit,
                'Sorgfältige Qualitätskontrolle vor Versand',
            ];
        }

        if (str_contains($materialRaw, '14 karat') || str_contains($materialRaw, 'ion-plating') || in_array($product->slug, ['goldenes-seelen-herz', 'seelen-pfote-hundemarke'], true)) {
            $secondBenefit = '14 Karat vergoldeter Edelstahl (Ion-Plating)';
        } elseif (str_contains($materialRaw, 'k9') || $product->slug === 'seelen-kristall') {
            $secondBenefit = 'Optisch reines K9-Kristallglas mit 3D-Lasergravur';
        } elseif (str_contains($materialRaw, 'schiefer') || $product->slug === 'seelen-talisman') {
            $secondBenefit = 'Echter Naturschiefer mit feiner Kontrastgravur';
        } elseif ($product->is_personalizable) {
            $secondBenefit = 'Präzise Lasergravur individuell nach Wunsch';
        } else {
            $secondBenefit = 'Ausgewählte Manufaktur-Qualität mit langlebigem Finish';
        }

        return [
            "Handveredelt in Deutschland (Manufaktur {$city})",
            $secondBenefit,
            'Sorgfältige Qualitätskontrolle vor Versand',
        ];
    }

    private function resolveMaterialHint(Product $product): string
    {
        $slug = strtolower((string) $product->slug);
        $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
        $matType = strtolower((string) ($settings['material_type'] ?? ''));
        $attrs = is_array($product->attributes) ? $product->attributes : [];
        $matText = mb_strtolower((string) ($attrs['Material'] ?? '') . ' ' . $product->name);

        if ($slug === 'seelen-kristall' || str_contains($matText, 'k9')) {
            return 'crystal';
        }
        if ($matType === 'glass' || str_contains($matText, 'glas')) {
            return 'glass';
        }
        if (str_contains($matText, 'schiefer') || $slug === 'seelen-talisman') {
            return 'slate';
        }
        if (str_contains($matText, 'silber') || str_contains($matText, 'aluminium') || in_array($slug, ['id-armband-silber-herz', 'seelen-anhaenger'], true)) {
            return 'silver';
        }
        if (str_contains($matText, 'gold') || in_array($slug, ['goldenes-seelen-herz', 'echtgold-seelen-herz', 'id-armband-gold-herz', 'seelen-pfote-hundemarke'], true)) {
            return 'gold';
        }

        return 'metal';
    }

    /**
     * Ermittelt das Funki-3D-Modell des Besuchers (eingeloggter Kunde: Lv. 1..10 inkl. Waffe, Gast: Lv. 2).
     *
     * @param array<string, mixed> $multiplayerPayload
     * @return array<string, mixed>
     */
    private function resolvePlayerFunki(array $multiplayerPayload = []): array
    {
        $levelModels = $this->resolveFunkiLevelModelsMap();

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if ($customer !== null) {
            $resolvedLevel = 1;

            if (Schema::hasTable('customer_gamifications')) {
                $profile = CustomerGamification::where('customer_id', $customer->id)->first();
                if ($profile) {
                    $sparks = (int) ($profile->funken_total_earned ?? 0);
                    $rawLevel = (int) ($profile->level ?: GameConfig::getLevelForLifetimeSparks($sparks));
                    $resolvedLevel = min(GameConfig::MAX_LEVEL, max(1, $rawLevel));
                }
            }

            $cfg = GameConfig::getLevelConfig($resolvedLevel);
            $modelRel = ltrim((string) ($cfg['model_path'] ?? 'shop/customer/gamification/models/funki_lvl_1_rags.glb'), '/');
            $modelUrl = '/' . $modelRel;

            return [
                'is_guest' => false,
                'level' => $resolvedLevel,
                'name' => $this->cleanText((string) ($cfg['name'] ?? ('Funki · Stufe ' . $resolvedLevel))),
                'display_name' => $multiplayerPayload['display_name'] ?? null,
                'player_id' => $multiplayerPayload['player_id'] ?? null,
                'weapon' => $multiplayerPayload['weapon'] ?? null,
                'model_url' => $modelUrl,
                'glb_url' => $modelUrl,
                'level_models' => $levelModels,
            ];
        }

        $guestLevel = 2;
        $guestCfg = GameConfig::getLevelConfig($guestLevel);
        $guestModelRel = ltrim((string) ($guestCfg['model_path'] ?? 'shop/customer/gamification/models/funki_lvl_2_basic.glb'), '/');
        $guestModelUrl = '/' . $guestModelRel;

        return [
            'is_guest' => true,
            'level' => $guestLevel,
            'name' => 'Gast-Funki · Stufe 2',
            'display_name' => null,
            'player_id' => null,
            'weapon' => null,
            'model_url' => $guestModelUrl,
            'glb_url' => $guestModelUrl,
            'level_models' => $levelModels,
        ];
    }

    /**
     * Berechnet Jahreszeit, aktive Feiertage und Server-Uhrzeit in Europe/Berlin fuer den 3D-Showroom.
     *
     * @return array{
     *     season_key: string,
     *     season_label: string,
     *     holiday_key: string|null,
     *     holiday_label: string|null,
     *     server_time: string
     * }
     */
    private function resolveShowroomEnvironment(): array
    {
        $now = Carbon::now('Europe/Berlin');
        $month = (int) $now->month;
        $day = (int) $now->day;
        $year = (int) $now->year;

        [$seasonKey, $seasonLabel] = match (true) {
            in_array($month, [3, 4, 5], true) => ['spring', 'Frühling'],
            in_array($month, [6, 7, 8], true) => ['summer', 'Sommer'],
            in_array($month, [9, 10, 11], true) => ['autumn', 'Herbst'],
            default => ['winter', 'Winter'],
        };

        $holidayKey = null;
        $holidayLabel = null;
        $todayStart = $now->copy()->startOfDay();
        $easter = $this->computeEasterDateBerlin($year);
        $mothersDay = Carbon::create($year, 5, 1, 0, 0, 0, 'Europe/Berlin')->nthOfMonth(2, Carbon::SUNDAY)->startOfDay();
        $fathersDay = $easter->copy()->addDays(39)->startOfDay();

        if (($month === 12 && $day >= 30) || ($month === 1 && $day <= 4)) {
            $holidayKey = 'neujahr';
            $holidayLabel = 'Neujahr';
        } elseif ($month === 12 && $day >= 1 && $day <= 26) {
            $holidayKey = 'weihnachten';
            $holidayLabel = 'Weihnachten';
        } elseif ($month === 2 && $day >= 7 && $day <= 14) {
            $holidayKey = 'valentinstag';
            $holidayLabel = 'Valentinstag';
        } elseif ($todayStart->betweenIncluded($easter->copy()->subDays(7), $easter->copy()->addDay())) {
            $holidayKey = 'ostern';
            $holidayLabel = 'Ostern';
        } elseif ($todayStart->betweenIncluded($mothersDay->copy()->subDays(4), $mothersDay)) {
            $holidayKey = 'muttertag';
            $holidayLabel = 'Muttertag';
        } elseif ($todayStart->betweenIncluded($fathersDay->copy()->subDays(3), $fathersDay)) {
            $holidayKey = 'vatertag';
            $holidayLabel = 'Vatertag';
        } elseif ($month === 10 && $day >= 24 && $day <= 31) {
            $holidayKey = 'halloween';
            $holidayLabel = 'Halloween';
        }

        return [
            'season_key' => $seasonKey,
            'season_label' => $seasonLabel,
            'holiday_key' => $holidayKey,
            'holiday_label' => $holidayLabel,
            'server_time' => $now->format('H:i'),
        ];
    }

    private function computeEasterDateBerlin(int $year): Carbon
    {
        $k = intdiv($year, 100);
        $m = 15 + intdiv((3 * $k + 3), 4) - intdiv((8 * $k + 13), 25);
        $s = 2 - intdiv((3 * $k + 3), 4);
        $a = $year % 19;
        $d = (19 * $a + $m) % 30;
        $r = intdiv($d, 29) + (intdiv($d, 28) - intdiv($d, 29)) * intdiv($a, 11);
        $og = 21 + $d - $r;
        $sz = 7 - (($year + intdiv($year, 4) + $s) % 7);
        $oe = 7 - (($og - $sz) % 7);

        return Carbon::create($year, 3, 1, 0, 0, 0, 'Europe/Berlin')->addDays($og + $oe - 1)->startOfDay();
    }

    /**
     * Erzeugt den strukturierten Spieler-Block fuer GET /shop/showroom-data (Zero-PII).
     *
     * @param array<string, mixed> $multiplayerPayload
     * @return array<string, mixed>
     */
    private function resolveShowroomPlayer(
        ?Customer $customer,
        array $multiplayerPayload,
        bool $masterKeyUnlocked
    ): array {
        $giftVoucherUrl = '/produkt/geschenkgutschein';
        $levelTitles = GameConfig::getLevelTitles();
        $sanctumAccess = LobbyInstanceService::evaluateSanctumAccess($customer);

        if ($customer !== null) {
            $profile = Schema::hasTable('customer_gamifications')
                ? CustomerGamification::where('customer_id', $customer->id)->first()
                : null;

            $gamificationActive = $sanctumAccess['gamification_active'];
            $heroMetrics = LobbyInstanceService::calculateHeroRankingMetrics($profile);
            $funkenBalance = max(0, (int) ($profile?->funken_balance ?? 0));
            $funkenTotalEarned = $heroMetrics['sparks_total'];
            $rawLevel = (int) ($profile?->level ?: GameConfig::getLevelForLifetimeSparks($funkenTotalEarned));
            $resolvedLevel = min(GameConfig::MAX_LEVEL, max(1, $rawLevel));

            $cfg = GameConfig::getLevelConfig($resolvedLevel);
            $modelRel = ltrim((string) ($cfg['model_path'] ?? 'shop/customer/gamification/models/funki_lvl_1_rags.glb'), '/');
            $modelUrl = '/' . $modelRel;
            $levelTitle = $this->cleanText((string) ($levelTitles[$resolvedLevel]['name'] ?? ('Stufe ' . $resolvedLevel)));

            $rawDisplayName = trim((string) ($multiplayerPayload['display_name'] ?? ''));
            $safeName = $rawDisplayName !== ''
                ? $this->cleanText($rawDisplayName)
                : $this->cleanText((string) ($cfg['name'] ?? ('Funki · Stufe ' . $resolvedLevel)));

            return [
                'is_authenticated' => true,
                'has_completed_order' => $sanctumAccess['has_completed_order'],
                'gamification_active' => $gamificationActive,
                'can_enter_sanctums' => $sanctumAccess['can_enter_sanctums'],
                'sanctum_block_reason' => $sanctumAccess['sanctum_block_reason'],
                'login_url' => $sanctumAccess['login_url'],
                'dashboard_url' => $sanctumAccess['dashboard_url'],
                'name' => $safeName,
                'level' => $resolvedLevel,
                'level_title' => $levelTitle,
                'model_url' => $modelUrl,
                'funken_balance' => $funkenBalance,
                'funken_total_earned' => $funkenTotalEarned,
                'hero_score' => $heroMetrics['hero_score'],
                'hero_score_formatted' => number_format($heroMetrics['hero_score'], 0, ',', '.'),
                'magma_highest_level' => $heroMetrics['magma_highest_level'],
                'magma_cleared_count' => $heroMetrics['magma_cleared_count'],
                'magma_prestige_points' => $heroMetrics['magma_prestige_points'],
                'master_key_unlocked' => $masterKeyUnlocked,
                'gift_voucher_url' => $giftVoucherUrl,
            ];
        }

        $guestLevel = 2;
        $guestCfg = GameConfig::getLevelConfig($guestLevel);
        $guestModelRel = ltrim((string) ($guestCfg['model_path'] ?? 'shop/customer/gamification/models/funki_lvl_2_basic.glb'), '/');
        $guestModelUrl = '/' . $guestModelRel;
        $guestTitle = $this->cleanText((string) ($levelTitles[$guestLevel]['name'] ?? 'Lichtträger-Lehrling'));

        return [
            'is_authenticated' => false,
            'has_completed_order' => false,
            'gamification_active' => false,
            'can_enter_sanctums' => false,
            'sanctum_block_reason' => $sanctumAccess['sanctum_block_reason'],
            'login_url' => $sanctumAccess['login_url'],
            'dashboard_url' => $sanctumAccess['dashboard_url'],
            'name' => 'Gast-Funki',
            'level' => $guestLevel,
            'level_title' => $guestTitle,
            'model_url' => $guestModelUrl,
            'funken_balance' => 0,
            'funken_total_earned' => 0,
            'hero_score' => 0,
            'hero_score_formatted' => '0',
            'magma_highest_level' => 0,
            'magma_cleared_count' => 0,
            'magma_prestige_points' => 0,
            'master_key_unlocked' => false,
            'gift_voucher_url' => $giftVoucherUrl,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveFunkiLevelModelsMap(): array
    {
        $allLevels = GameConfig::getFunkiLevels();
        $map = [];
        for ($lvl = 1; $lvl <= GameConfig::MAX_LEVEL; $lvl++) {
            $cfg = $allLevels[$lvl] ?? [];
            $rel = ltrim((string) ($cfg['model_path'] ?? ('shop/customer/gamification/models/funki_lvl_' . $lvl . '.glb')), '/');
            $map[$lvl] = '/' . $rel;
        }

        return $map;
    }

    private function cleanText(string $value, string $dashReplacement = ' · '): string
    {
        $clean = str_replace(
            [' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' ', "\u{2013}", "\u{2014}"],
            [$dashReplacement, $dashReplacement, '-', '-'],
            trim($value)
        );

        return preg_replace('/\s+/u', ' ', $clean) ?? $clean;
    }
}

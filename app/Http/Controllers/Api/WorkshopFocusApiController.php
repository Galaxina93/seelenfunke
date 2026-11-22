<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\DhlService;
use App\Services\Export\FileDownloadService;
use App\Services\Laser\XtoolLaserCockpitService;
use App\Services\Order\OrderProductionBatchService;
use App\Services\System\FunkenwerkPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WorkshopFocusApiController extends Controller
{
    public function __construct(
        private readonly OrderProductionBatchService $batchService,
        private readonly XtoolLaserCockpitService $laserService,
        private readonly FileDownloadService $exportService,
        private readonly FunkenwerkPrintService $printService
    ) {
    }

    /**
     * GET /api/shop/workshop-focus
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $batchProductId = trim((string) $request->query('batch_product_id', '')) ?: null;
        $requestedSide = $request->query('side') === 'back' ? 'back' : 'front';

        $payload = $this->buildFocusPayload($batchProductId, null, $requestedSide);

        return response()->json($payload)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * POST /api/shop/workshop-focus/send-laser
     */
    public function sendLaser(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'order_id' => 'required|string',
            'item_id' => 'required|string',
            'side' => 'nullable|string|in:front,back',
            'center_x_mm' => 'nullable|numeric|min:0|max:200',
            'center_y_mm' => 'nullable|numeric|min:0|max:200',
            'angle_deg' => 'nullable|numeric|min:-180|max:180',
        ]);

        $order = OrderOrder::with('items.product')->findOrFail($data['order_id']);

        if ($order->payment_status !== 'paid' || in_array($order->status, ['cancelled', 'refunded'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Nur bezahlte, aktive Bestellungen koennen an den Laser uebertragen werden.',
            ], 422);
        }

        /** @var OrderOrderItem|null $item */
        $item = $order->items->firstWhere('id', $data['item_id']);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Bestellposition gehoert nicht zu dieser Bestellung.',
            ], 404);
        }

        if ($order->status === 'pending') {
            $order->update(['status' => 'processing']);
            $order->syncAutonomousWorkshopSummary();
        }

        $side = ($data['side'] ?? 'front') === 'back' ? 'back' : 'front';
        $jig = $this->resolveJigMetadata($item->product, $item);

        $centerXMm = isset($data['center_x_mm'])
            ? (float) $data['center_x_mm']
            : ($jig['has_jig'] ? (float) $jig['fixed_x_mm'] : null);
        $centerYMm = isset($data['center_y_mm'])
            ? (float) $data['center_y_mm']
            : ($jig['has_jig'] ? (float) $jig['fixed_y_mm'] : null);
        $angleDeg = isset($data['angle_deg'])
            ? (float) $data['angle_deg']
            : ($jig['has_jig'] ? (float) $jig['fixed_angle_deg'] : 0.0);

        $job = $this->laserService->enqueueOrSendLaserJob(
            $item->id,
            $side,
            'engrave',
            $centerXMm,
            $centerYMm,
            $angleDeg
        );

        $publicDisk = Storage::disk('public');
        $xfBytes = $publicDisk->exists('bestellungen/xtool/latest_job.xf')
            ? (string) $publicDisk->get('bestellungen/xtool/latest_job.xf')
            : '';

        return response()->json([
            'success' => true,
            'job_id' => (string) $job['id'],
            'sent_direct' => (bool) ($job['sent_direct'] ?? false),
            'xf_base64' => base64_encode($xfBytes),
            'center_x_mm' => (float) $job['center_x_mm'],
            'center_y_mm' => (float) $job['center_y_mm'],
            'angle_deg' => (float) $job['angle_deg'],
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    /**
     * POST /api/shop/workshop-focus/finish-laser-item
     */
    public function finishLaserItem(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'order_id' => 'required|string',
            'item_id' => 'required|string',
            'job_id' => 'nullable|string|max:100',
            'batch_product_id' => 'nullable|string',
        ]);

        $order = OrderOrder::with('items.product')->findOrFail($data['order_id']);

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Stornierte oder erstattete Bestellungen koennen nicht bearbeitet werden.',
            ], 422);
        }

        /** @var OrderOrderItem|null $item */
        $item = $order->items->firstWhere('id', $data['item_id']);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Bestellposition nicht gefunden.',
            ], 404);
        }

        if (!empty($data['job_id'])) {
            $this->laserService->acknowledgeJob((string) $data['job_id'], 'completed');
        }

        $item->markAllChecklistTasks(true);

        if (!in_array($order->status, ['shipped', 'completed'], true)) {
            $order->update(['status' => 'processing']);
        }
        $order->refresh();
        $order->load(['items.product.supplier', 'shipments']);
        $order->syncAutonomousWorkshopSummary();

        $batchProductId = trim((string) ($data['batch_product_id'] ?? ''));
        if ($batchProductId === '' && !empty($item->product_id)) {
            $batchProductId = (string) $item->product_id;
        }

        $focusPayload = $this->buildFocusPayload($batchProductId ?: null, (string) $order->id);

        return response()->json(array_merge(
            ['success' => true],
            $focusPayload
        ))->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    /**
     * POST /api/shop/workshop-focus/toggle-check
     */
    public function toggleCheck(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'order_id' => 'required|string',
            'key' => 'required|string|in:cleaned,gift_box,gift_wrap,thank_you_card,fragile_sticker',
            'checked' => 'nullable|boolean',
        ]);

        $order = OrderOrder::with('items.product')->findOrFail($data['order_id']);
        $forceState = array_key_exists('checked', $data) && $data['checked'] !== null
            ? (bool) $data['checked']
            : null;

        $order->togglePackingCheck((string) $data['key'], $forceState);
        $order->refresh();
        $order->load('items.product');
        $order->syncAutonomousWorkshopSummary();

        return response()->json([
            'success' => true,
            'checklist' => $order->resolvePackingChecklistItems(),
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    /**
     * POST /api/shop/workshop-focus/ship-and-next
     */
    public function shipAndNext(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'order_id' => 'required|string',
            'batch_product_id' => 'nullable|string',
        ]);

        $order = OrderOrder::with(['items.product', 'shipments'])->findOrFail($data['order_id']);

        if (in_array($order->status, ['cancelled', 'refunded'], true) || $order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Nur bezahlte, aktive Bestellungen koennen versendet werden.',
            ], 422);
        }

        foreach ($order->items as $item) {
            if (!$item->is_completed) {
                $item->markAllChecklistTasks(true);
            }
        }

        $order->refresh();
        $order->load(['items.product', 'shipments']);
        $checklist = $order->markAllPackingChecks(true);

        $dhlReady = false;
        $dhlMessage = null;
        $trackingNumber = null;
        $labelPdfBase64 = null;
        $labelUrl = null;

        $existingShipment = $order->shipments
            ->filter(fn ($s) => !empty($s->shipping_label_path))
            ->last();

        if (!$existingShipment && !$order->isOnlyDigital()) {
            $dhlService = app(DhlService::class);
            $apiKey = trim((string) config('services.dhl.api_key', env('DHL_API_KEY', '')));
            $apiUser = trim((string) config('services.dhl.api_user', env('DHL_API_USER', '')));
            $isMockedDhl = get_class($dhlService) !== DhlService::class;

            if (!$isMockedDhl && ($apiKey === '' || $apiUser === '')) {
                $dhlReady = false;
                $dhlMessage = 'DHL-Zugangsdaten nicht hinterlegt (Manueller Versand / Testmodus).';
            } else {
                try {
                    $box = $order->resolveRecommendedShippingBox();
                    $pkgType = $box['sku'] === 'PKG-ENV-A1' ? 'envelope' : 'box';
                    $pkgCount = max(1, (int) $box['package_count']);
                    $weightKg = $order->calculateDhlWeight($pkgCount, $pkgType);

                    $dhlService->createLabels($order, $pkgCount, $weightKg);
                    $order->load('shipments');
                } catch (\Throwable $e) {
                    Log::warning('WorkshopFocus ship-and-next DHL fallback: ' . $e->getMessage());
                    $dhlReady = false;
                    $dhlMessage = 'DHL-Versandlabel konnte nicht automatisch erstellt werden: ' . $e->getMessage();
                }
            }
        }

        $shipment = $order->shipments
            ->filter(fn ($s) => !empty($s->shipping_label_path))
            ->last() ?: $order->shipments->last();

        if ($shipment) {
            $trackingNumber = (string) ($shipment->tracking_number ?? '') ?: null;
            if (!empty($shipment->shipping_label_path)) {
                $pdfBytes = $this->printService->readLabelPdfBytes((string) $shipment->shipping_label_path);
                if (is_string($pdfBytes) && $pdfBytes !== '') {
                    $labelPdfBase64 = base64_encode($pdfBytes);
                    $dhlReady = true;
                    $dhlMessage = 'DHL-Versandlabel bereit.';

                    try {
                        $this->printService->enqueueShipmentLabel($shipment);
                    } catch (\Throwable) {
                        // Optionaler Print-Queue-Eintrag
                    }
                }
            }

            if ($trackingNumber !== null) {
                $labelUrl = 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=' . urlencode($trackingNumber);
            }
        }

        if ($dhlMessage === null) {
            $dhlMessage = $order->isOnlyDigital()
                ? 'Digitaler Auftrag ohne physisches DHL-Label.'
                : 'Bestellung als versendet markiert (ohne hinterlegtes DHL-Label).';
        }

        $order->update(['status' => 'shipped']);
        $order->syncAutonomousWorkshopSummary();

        $batchProductId = trim((string) ($data['batch_product_id'] ?? ''));
        if ($batchProductId === '' && $order->items->isNotEmpty()) {
            $batchProductId = (string) ($order->items->first()?->product_id ?? '');
        }

        $nextFocus = $this->buildFocusPayload($batchProductId ?: null);

        return response()->json([
            'success' => true,
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'status' => 'shipped',
            'dhl_ready' => $dhlReady,
            'dhl_message' => $dhlMessage,
            'tracking_number' => $trackingNumber,
            'label_pdf_base64' => $labelPdfBase64,
            'label_url' => $labelUrl,
            'checklist' => $checklist,
            'next_focus' => $nextFocus,
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    /**
     * Baut das Fokus-Payload fuer den aktuell wichtigsten, sofort ausfuehrbaren Auftrag.
     */
    protected function buildFocusPayload(
        ?string $batchProductId = null,
        ?string $preferredOrderId = null,
        string $requestedSide = 'front'
    ): array {
        $openOrders = OrderOrder::query()
            ->with(['items.product.supplier', 'shipments'])
            ->whereIn('status', ['pending', 'processing'])
            ->get();

        $queue = $this->batchService->buildSmartQueue($openOrders, $batchProductId);
        $analyses = $queue['analyses'];

        $readyOrders = $queue['ranked_orders']->filter(function (OrderOrder $order) use ($analyses): bool {
            if ($order->payment_status !== 'paid') {
                return false;
            }

            $a = $analyses[(string) $order->id] ?? null;
            if (!is_array($a) || empty($a['is_paid'])) {
                return false;
            }

            if (!in_array($a['stage'] ?? '', ['ready_laser', 'ready_pack', 'ready_ship'], true)) {
                return false;
            }

            if (
                !empty($a['photo_issue_items'])
                || !empty($a['pending_supplier_items'])
                || !empty($a['waiting_supplier_items'])
                || !empty($a['critical_stock_items'])
            ) {
                return false;
            }

            return true;
        })->values();

        $openReadyCount = $readyOrders->count();
        if ($readyOrders->isEmpty()) {
            return [
                'has_order' => false,
                'open_ready_count' => 0,
                'phase' => 'laser',
                'order' => null,
            ];
        }

        /** @var OrderOrder|null $focusOrder */
        $focusOrder = null;
        if ($preferredOrderId !== null && $preferredOrderId !== '') {
            $focusOrder = $readyOrders->first(fn (OrderOrder $o) => (string) $o->id === $preferredOrderId);
        }
        if (!$focusOrder) {
            $focusOrder = $readyOrders->first(
                fn (OrderOrder $o) => $o->status === 'processing' && (($analyses[(string) $o->id]['stage'] ?? '') === 'ready_ship')
            );
        }
        if (!$focusOrder) {
            $focusOrder = $readyOrders->first();
        }

        $focusAnalysis = $analyses[(string) $focusOrder->id] ?? [];
        $stage = (string) ($focusAnalysis['stage'] ?? 'ready_laser');
        $phase = ($stage === 'ready_laser') ? 'laser' : 'packing';

        /** @var OrderOrderItem|null $activeItem */
        $activeItem = null;
        if ($phase === 'laser') {
            $recommendedItemId = (string) ($focusAnalysis['recommended_item']['item_id'] ?? '');
            if ($recommendedItemId !== '') {
                $activeItem = $focusOrder->items->firstWhere('id', $recommendedItemId);
            }
        }
        if (!$activeItem) {
            $activeItem = $focusOrder->items->first(fn (OrderOrderItem $i) => !$i->is_completed)
                ?: $focusOrder->items->first();
        }

        $activeItemPayload = $activeItem
            ? $this->buildActiveItemPayload($focusOrder, $activeItem, $requestedSide)
            : null;

        $recommendedBox = $focusOrder->resolveRecommendedShippingBox();

        return [
            'has_order' => true,
            'open_ready_count' => $openReadyCount,
            'phase' => $phase,
            'order' => [
                'id' => (string) $focusOrder->id,
                'order_number' => (string) $focusOrder->order_number,
                'status' => (string) $focusOrder->status,
                'active_item' => $activeItemPayload,
                'packaging' => [
                    'box_name' => (string) $recommendedBox['box_name'],
                    'box_dimensions' => (string) $recommendedBox['box_dimensions'],
                    'checklist' => $focusOrder->resolvePackingChecklistItems(),
                ],
            ],
        ];
    }

    /**
     * Erzeugt das active_item-Objekt inkl. 3D-Vorrichtungs-Metadaten (jig) und Laser-Koordinaten.
     */
    protected function buildActiveItemPayload(OrderOrder $order, OrderOrderItem $item, string $requestedSide = 'front'): array
    {
        $hasBackSideContent = $this->batchService->isDualSidedItem($item);
        $effectiveSide = ($requestedSide === 'back' && $hasBackSideContent) ? 'back' : 'front';

        $itemChecklist = $item->resolveManufacturingChecklist();
        $customerNote = (string) ($itemChecklist['notes']['text'] ?? '');

        $jig = $this->resolveJigMetadata($item->product, $item);
        $camInfo = $this->laserService->syncCameraFromHostOrLaser(null, false);

        if ($jig['has_jig']) {
            $centerXMm = (float) $jig['fixed_x_mm'];
            $centerYMm = (float) $jig['fixed_y_mm'];
            $angleDeg = (float) $jig['fixed_angle_deg'];
        } else {
            try {
                $detected = $this->laserService->detectBlankPositionOnBed(
                    $item->product,
                    is_array($item->configuration) ? $item->configuration : [],
                    $effectiveSide,
                    false
                );
                $centerXMm = (float) $detected['center_x_mm'];
                $centerYMm = (float) $detected['center_y_mm'];
                $angleDeg = (float) $detected['angle_deg'];
            } catch (\Throwable) {
                $centerXMm = 100.0;
                $centerYMm = 100.0;
                $angleDeg = 0.0;
            }
        }

        [$blankWidthMm, $blankHeightMm] = $this->laserService->resolvePhysicalBlankDimensionsMm(
            $item->product,
            is_array($item->configuration) ? $item->configuration : []
        );
        $contourDPath = sprintf(
            'M 0.000 0.000 L %.3f 0.000 L %.3f %.3f L 0.000 %.3f Z',
            $blankWidthMm,
            $blankWidthMm,
            $blankHeightMm,
            $blankHeightMm
        );
        $engravePreviewUrl = '';
        $engravePreviewDataUrl = '';

        try {
            $itemConf = is_array($item->configuration) ? $item->configuration : [];
            $productCfg = is_string($item->product?->configurator_settings)
                ? (json_decode($item->product->configurator_settings, true) ?: [])
                : ($item->product?->configurator_settings ?? []);
            $calibration = $this->exportService->resolveEngravingWorkingAreaCalibration($item->product, $productCfg, $itemConf, false);

            $confForCache = $itemConf;
            unset(
                $confForCache['laser_cockpit_coordinates'],
                $confForCache['manufacturing_checklist'],
                $confForCache['laser_parameter_overrides'],
                $confForCache['laser_cockpit_parameters'],
                $confForCache['laser_camera_light_mode']
            );
            $effectiveLaserGuide = $item->product
                ? $this->laserService->resolveEffectiveLaserParameters($item, $effectiveSide)
                : [];

            $cacheHash = sha1(
                'v8-cockpit-photo-layers|'
                . $item->id . '|'
                . $effectiveSide . '|'
                . ($item->updated_at?->timestamp ?? 0) . '|'
                . ($item->product?->updated_at?->timestamp ?? 0) . '|'
                . ($effectiveLaserGuide['material'] ?? '') . '|'
                . json_encode($calibration) . '|'
                . json_encode($confForCache)
            );
            $publicDisk = Storage::disk('public');
            $relPng = "bestellungen/xtool/previews/{$cacheHash}.png";
            $relMeta = "bestellungen/xtool/previews/{$cacheHash}.json";

            $loadedFromCache = false;
            if (!app()->runningUnitTests() && $publicDisk->exists($relPng) && $publicDisk->exists($relMeta)) {
                $cachedMeta = json_decode((string) $publicDisk->get($relMeta), true);
                $cachedPngBytes = (string) $publicDisk->get($relPng);
                if (
                    is_array($cachedMeta)
                    && isset($cachedMeta['widthMm'], $cachedMeta['heightMm'], $cachedMeta['ghostContourDPath'])
                    && $cachedPngBytes !== ''
                ) {
                    $blankWidthMm = (float) $cachedMeta['widthMm'];
                    $blankHeightMm = (float) $cachedMeta['heightMm'];
                    $contourDPath = (string) $cachedMeta['ghostContourDPath'];
                    $engravePreviewUrl = asset('storage/' . $relPng);
                    $engravePreviewDataUrl = 'data:image/png;base64,' . base64_encode($cachedPngBytes);
                    $loadedFromCache = true;
                }
            }

            if (!$loadedFromCache) {
                $designPayload = $this->exportService->buildLaserDesignPayload($item, $effectiveSide, true);
                $blankWidthMm = (float) $designPayload['widthMm'];
                $blankHeightMm = (float) $designPayload['heightMm'];
                $contourDPath = (string) $designPayload['ghostContourDPath'];
                $engravePreviewDataUrl = (string) $designPayload['previewDataUrl'];
                $engravePreviewUrl = $engravePreviewDataUrl;

                if (!empty($designPayload['cockpitPreviewPngBytes'])) {
                    $publicDisk->put($relPng, $designPayload['cockpitPreviewPngBytes']);
                    $publicDisk->put($relMeta, (string) json_encode([
                        'widthMm'           => $blankWidthMm,
                        'heightMm'          => $blankHeightMm,
                        'contourShape'      => $designPayload['contourShape'] ?? 'rect',
                        'isDarkSurface'     => $designPayload['isDarkSurface'] ?? false,
                        'ghostContourDPath' => $contourDPath,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $engravePreviewUrl = asset('storage/' . $relPng);
                }
            }

            $savedSide = $itemConf['laser_cockpit_coordinates'][$effectiveSide] ?? null;
            if (is_array($savedSide)) {
                if (isset($savedSide['w_mm']) && is_numeric($savedSide['w_mm']) && (float) $savedSide['w_mm'] > 0.5) {
                    $blankWidthMm = (float) $savedSide['w_mm'];
                }
                if (isset($savedSide['h_mm']) && is_numeric($savedSide['h_mm']) && (float) $savedSide['h_mm'] > 0.5) {
                    $blankHeightMm = (float) $savedSide['h_mm'];
                }
            }
        } catch (\Throwable) {
            // Fallback fuer unpersonalisierte Lagerartikel ohne Laserflaeche
        }

        $productImageUrl = (string) ($item->resolveDisplayImageUrl()
            ?: ($item->product ? $item->product->resolveMainImageUrl() : ''));

        return [
            'id' => (string) $item->id,
            'product_id' => (string) ($item->product_id ?? ''),
            'product_name' => (string) ($item->product_name ?: ($item->product->name ?? 'Artikel')),
            'quantity' => max(1, (int) $item->quantity),
            'completed_quantity' => (int) ($item->completed_quantity ?? ($item->is_completed ? $item->quantity : 0)),
            'product_image_url' => $productImageUrl,
            'side' => $effectiveSide,
            'has_back_side_content' => $hasBackSideContent,
            'customer_note' => $customerNote,
            'jig' => $jig,
            'laser' => [
                'bed_width_mm' => (float) XtoolLaserCockpitService::BED_WIDTH_MM,
                'bed_height_mm' => (float) XtoolLaserCockpitService::BED_HEIGHT_MM,
                'blank_width_mm' => round($blankWidthMm, 2),
                'blank_height_mm' => round($blankHeightMm, 2),
                'center_x_mm' => round($centerXMm, 1),
                'center_y_mm' => round($centerYMm, 1),
                'angle_deg' => round($angleDeg, 1),
                'contour_d_path' => $contourDPath,
                'engrave_preview_url' => $engravePreviewUrl,
                'engrave_preview_data_url' => $engravePreviewDataUrl,
                'portal_camera_url' => (string) ($camInfo['camera_url'] ?? ''),
            ],
        ];
    }

    /**
     * Ermittelt, ob fuer das Produkt bereits eine physische 3D-Bambu-Klickplatte mit Fix-Koordinaten existiert.
     *
     * @return array{has_jig: bool, label: string, fixed_x_mm: float|null, fixed_y_mm: float|null, fixed_angle_deg: float}
     */
    public function resolveJigMetadata(?Product $product = null, ?OrderOrderItem $item = null): array
    {
        $cfg = ($product && is_array($product->configurator_settings))
            ? $product->configurator_settings
            : [];

        $slug = mb_strtolower((string) ($product->slug ?? ''));
        $name = mb_strtolower((string) ($item?->product_name ?: ($product->name ?? '')));

        $hasExplicitJig = !empty($cfg['xtool_has_jig'])
            || (isset($cfg['xtool_jig_x_mm']) && is_numeric($cfg['xtool_jig_x_mm']) && isset($cfg['xtool_jig_y_mm']) && is_numeric($cfg['xtool_jig_y_mm']));

        $isSeelenKristall = $slug === 'seelen-kristall'
            || str_starts_with($slug, 'seelen-kristall')
            || str_contains($name, 'seelen kristall')
            || str_contains($name, 'seelen-kristall');

        if ($hasExplicitJig || $isSeelenKristall) {
            $fixedX = (isset($cfg['xtool_jig_x_mm']) && is_numeric($cfg['xtool_jig_x_mm']))
                ? round((float) $cfg['xtool_jig_x_mm'], 1)
                : 100.0;
            $fixedY = (isset($cfg['xtool_jig_y_mm']) && is_numeric($cfg['xtool_jig_y_mm']))
                ? round((float) $cfg['xtool_jig_y_mm'], 1)
                : 95.0;
            $fixedAngle = (isset($cfg['xtool_jig_angle_deg']) && is_numeric($cfg['xtool_jig_angle_deg']))
                ? round((float) $cfg['xtool_jig_angle_deg'], 1)
                : 0.0;
            $label = !empty($cfg['xtool_jig_label'])
                ? (string) $cfg['xtool_jig_label']
                : ($isSeelenKristall ? 'Seelen Kristall' : (string) ($product->name ?? '3D-Vorrichtung'));

            return [
                'has_jig' => true,
                'label' => $label,
                'fixed_x_mm' => $fixedX,
                'fixed_y_mm' => $fixedY,
                'fixed_angle_deg' => $fixedAngle,
            ];
        }

        return [
            'has_jig' => false,
            'label' => '',
            'fixed_x_mm' => null,
            'fixed_y_mm' => null,
            'fixed_angle_deg' => 0.0,
        ];
    }

    /**
     * GET /api/shop/workshop-focus/laser-relay/poll?laser_online=1|0
     */
    public function pollLaserRelay(Request $request): JsonResponse
    {
        $this->authorizeLaserRelay($request);

        $laserOnline = $request->has('laser_online')
            ? $request->boolean('laser_online')
            : null;
        $laserIp = $request->query('laser_ip');
        $workTelemetry = $request->has('work_mode') ? [
            'mode'         => (string) $request->query('work_mode', ''),
            'sub_mode'     => (string) $request->query('work_submode', ''),
            'desc'         => (string) $request->query('work_desc', ''),
            'progress_pct' => max(0, min(100, (int) $request->query('work_progress', 0))),
            'worktime_sec' => max(0, (int) $request->query('work_time_sec', 0)),
        ] : null;

        $job = $this->laserService->claimNextPendingRelayJob(
            $laserOnline,
            is_string($laserIp) ? $laserIp : null,
            $workTelemetry
        );

        return response()->json([
            'success' => true,
            'job' => $job,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * POST /api/shop/workshop-focus/laser-relay/complete
     */
    public function completeLaserRelay(Request $request): JsonResponse
    {
        $this->authorizeLaserRelay($request);

        $data = $request->validate([
            'job_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{6,80}$/'],
            'ok' => 'required|boolean',
            'laser_online' => 'nullable|boolean',
            'state' => 'nullable|string|max:80',
            'error' => 'nullable|string|max:500',
            'warped_jpeg_base64' => 'nullable|string|max:16000000',
            'raw_jpeg_base64' => 'nullable|string|max:24000000',
            'focus_mm' => 'nullable|numeric|min:0|max:250',
            'raw_z_mm' => 'nullable|numeric|min:0|max:260',
        ]);

        $completed = $this->laserService->completeRelayJob((string) $data['job_id'], $data);
        if ($completed === null) {
            return response()->json([
                'success' => false,
                'message' => 'Relay-Job nicht gefunden oder bereits abgelaufen.',
            ], 404)->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->json([
            'success' => true,
            'job_id' => (string) $completed['job_id'],
            'status' => 'completed',
            'camera_saved' => (bool) ($completed['result']['camera_saved'] ?? false),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * POST /api/shop/workshop-focus/laser-relay/heartbeat
     */
    public function heartbeatLaserRelay(Request $request): JsonResponse
    {
        $this->authorizeLaserRelay($request);

        $data = $request->validate([
            'laser_online' => 'nullable|boolean',
            'laser_ip' => 'nullable|string|max:45',
        ]);

        $hb = $this->laserService->recordMiniPcRelayHeartbeat(
            $request->has('laser_online') ? $request->boolean('laser_online') : null,
            $data['laser_ip'] ?? null
        );

        return response()->json([
            'success' => true,
            'heartbeat' => $hb,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function authorizeLaserRelay(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Nicht authentifiziert.');
        }

        if (!$user instanceof Admin) {
            abort(403, 'Nur Administratoren duerfen die Werkstatt-Laser-Bruecke bedienen.');
        }

        if ($request->bearerToken()) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken((string) $request->bearerToken());
            if ($pat) {
                if ($pat->expires_at && $pat->expires_at->isPast()) {
                    abort(401, 'Token abgelaufen.');
                }

                if (
                    !$pat->can('*')
                    && !$pat->can('ai:funkira')
                    && !$pat->can('monitor:pulse')
                    && !$pat->can('orders:manage')
                ) {
                    abort(403, 'Unzureichende Token-Berechtigung fuer die Werkstatt-Laser-Bruecke.');
                }
            }
        }

        if (
            method_exists($user, 'currentAccessToken')
            && $user->currentAccessToken()
            && !$user->tokenCan('*')
            && !$user->tokenCan('ai:funkira')
            && !$user->tokenCan('monitor:pulse')
            && !$user->tokenCan('orders:manage')
        ) {
            abort(403, 'Unzureichende Token-Berechtigung fuer die Werkstatt-Laser-Bruecke.');
        }
    }

    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            abort(403, 'Nur Administratoren duerfen Bestellungen verwalten.');
        }

        if (
            method_exists($user, 'currentAccessToken')
            && $user->currentAccessToken()
            && !$user->tokenCan('*')
            && !$user->tokenCan('orders:manage')
        ) {
            abort(403, 'Unzureichende Token-Berechtigung fuer den Werkstatt-Fokus-Leitstand.');
        }
    }
}

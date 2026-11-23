<?php

namespace App\Services\Export;

use App\Models\Admin\Admin;
use App\Models\Order\OrderOrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class FileDownloadService
{
    public function downloadHealthTreatmentPlanPdf($planId)
    {
        $isAdmin = \Illuminate\Support\Facades\Auth::guard('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin();

        if (!$isAdmin) {
            abort(403, 'Zugriff verweigert.');
        }

        $plan = \App\Models\Ai\AiHealthTreatmentPlan::with('items')->findOrFail($planId);

        $pdf = Pdf::loadView('global.pdf.health-treatment-plan', [
            'plan' => $plan,
        ])->setOption('isRemoteEnabled', false);
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Behandlungsplan_' . (Str::slug($plan->title) ?: 'Plan') . '.pdf');
    }

    public function downloadProductAnalyticsFullReportPdf()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert.');
        }

        $lossesData = [
            'this_month' => \App\Models\Product\ProductLoss::where('created_at', '>=', now()->startOfMonth())->sum('cost_value') / 100,
            'total' => \App\Models\Product\ProductLoss::sum('cost_value') / 100,
            'recent' => \App\Models\Product\ProductLoss::with('product')->latest()->take(50)->get(),
        ];

        $pdf = Pdf::loadView('global.pdf.product-analytics-report', [
            'combinedData' => \App\Livewire\Shop\Product\ProductAnalytics::getCombinedAnalyticsData(),
            'lossesData' => $lossesData,
            'date' => now()->format('d.m.Y H:i')
        ]);

        $pdf->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', false);

        $finalFilename = now()->format('Y-m-d_H-i') . '_Produkt_Analyse_Gesamtbericht.pdf';
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $finalFilename);
    }

    public function downloadProductAnalyticsLucidPdf()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert.');
        }

        $lucidData = \App\Livewire\Shop\Product\ProductAnalytics::getLucidData();
        $pdf = Pdf::loadView('global.pdf.product-analytics-lucid', [
            'lucidData' => $lucidData,
            'date' => now()->format('d.m.Y H:i')
        ]);

        $pdf->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', false);

        $finalFilename = now()->format('Y-m-d_H-i') . '_LUCID_Jahresbericht_' . $lucidData['year'] . '.pdf';
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $finalFilename);
    }

    public function downloadNicheCrawlerPdf($runId = null)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert.');
        }

        $top40 = collect();
        $aiRecommendation = null;
        $aiAgentName = 'KI-Agent';
        $docTitle = 'Marktanalyse: Top 40 Nischen-Produkte';
        $filenamePrefix = 'Top40-Nischen-Produkte';

        if ($runId) {
            $run = \App\Models\Product\ProductNicheCrawlerRun::findOrFail($runId);
            $allData = is_array($run->products_data) ? collect($run->products_data) : collect(json_decode($run->products_data, true));
            $top40 = $allData->sortByDesc('niche_score')->take(40)->values();
            $top40 = $top40->map(function($item) { return (object)$item; });
            $aiRecommendation = $run->ai_recommendation;

            if ($run->ai_agent_id) {
                $agent = \App\Models\Ai\AiAgent::find($run->ai_agent_id);
                if ($agent) $aiAgentName = $agent->name;
            }

            $docTitle = 'Crawler Anfrage: ' . $run->name;
            $filenamePrefix = Str::slug($run->name) . '_Crawler_Ergebnis';
        } else {
            $top40 = \App\Models\Product\ProductNicheItem::orderBy('niche_score', 'desc')->take(40)->get();
        }

        if ($top40->isEmpty()) abort(404, 'Keine Produkte gefunden.');

        $pdf = Pdf::loadView('global.pdf.top5-niche-products', [
            'products' => $top40,
            'aiRecommendation' => $aiRecommendation,
            'aiAgentName' => $aiAgentName,
            'docTitle' => $docTitle,
            'date' => now()->format('d.m.Y H:i')
        ])->setOption('isRemoteEnabled', false);

        $finalFilename = now()->format('Y-m-d_H-i') . '_' . $filenamePrefix . '.pdf';
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $finalFilename);
    }

    public function downloadMailAttachment($id)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Unautorisierter Zugriff auf E-Mail-Anhänge.');
        }

        $attachment = \App\Models\Management\Mail\MailAttachment::findOrFail($id);
        
        $path = str_replace('\\', '/', (string) $attachment->path);
        $allowedPrefixes = ['leitung/crm/mail-attachments/', 'mail-attachments/', 'temp/mail-attachments/'];
        $hasAllowedPrefix = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $hasAllowedPrefix = true;
                break;
            }
        }

        if (
            !$path
            || !$hasAllowedPrefix
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
        ) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        if (!Storage::exists($path)) {
            abort(404, 'Datei im Tresor nicht gefunden.');
        }

        $rawFullPath = Storage::path($path);
        if (is_link($rawFullPath)) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        $storageRoot = realpath(Storage::path(''));
        $resolvedPath = realpath($rawFullPath);
        if (
            $storageRoot === false
            || $resolvedPath === false
            || ($resolvedPath !== $storageRoot && !str_starts_with($resolvedPath, rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))
        ) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        $safeMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'
        ];
        $detectedMime = strtolower((string) (@Storage::mimeType($path) ?: $attachment->content_type ?: 'application/octet-stream'));
        $isSafeInline = in_array($detectedMime, $safeMimes, true);
        $contentType = $isSafeInline ? $detectedMime : 'application/octet-stream';
        $disposition = $isSafeInline ? 'inline' : 'attachment';
        $cleanFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($attachment->filename ?: 'attachment')) ?: 'attachment';

        return response()->streamDownload(function () use ($path) {
            $stream = Storage::readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $cleanFilename, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition . '; filename="' . $cleanFilename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadTaxExport($filename)
    {
        $employeeUser = Auth::guard('employee')->user();
        $isTaxAdvisor = $employeeUser && method_exists($employeeUser, 'isTaxAdvisor') && $employeeUser->isTaxAdvisor();

        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin() && !$isTaxAdvisor) {
            abort(403, 'Unautorisierter Zugriff auf Steuerexporte.');
        }

        $safeName = basename($filename);

        // Restrict filename characters (alphanumeric, underscores, hyphens, dots)
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $safeName)) {
            abort(404, 'Ungültiger Dateiname.');
        }

        // Restrict extension to safe export types
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'zip', 'xlsx', 'pdf', 'txt', 'xml'], true)) {
            abort(404, 'Ungültiges Dateiformat.');
        }

        $path = storage_path('app/buchhaltung/tax_exports/' . $safeName);

        if (!File::exists($path) || is_link($path)) {
            abort(404, 'Die Datei existiert nicht mehr im Tresor.');
        }

        return response()->download($path);
    }

    public function downloadLaserSvg($itemId, $side = 'front')
    {
        if ($itemId instanceof OrderOrderItem || $itemId instanceof \App\Models\Order\OrderQuoteRequestItem) {
            $item = $itemId;
        } else {
            $item = OrderOrderItem::find($itemId)
                ?? \App\Models\Order\OrderQuoteRequestItem::with(['product', 'quoteRequest'])->findOrFail($itemId);
        }

        $isAdminOrEmployee = \Illuminate\Support\Facades\Auth::guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();
        $isCustomerOwner = false;
        if (\Illuminate\Support\Facades\Auth::guard('customer')->check()) {
            $customer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
            if ($item instanceof OrderOrderItem && $item->order && (string)$item->order->customer_id === (string)$customer->id) {
                $isCustomerOwner = true;
            } elseif ($item instanceof \App\Models\Order\OrderQuoteRequestItem && $item->quoteRequest && strtolower((string) $item->quoteRequest->email) === strtolower((string) $customer->email)) {
                $isCustomerOwner = true;
            }
        }
        if (!$isAdminOrEmployee && !$isCustomerOwner && !request()->hasValidSignature()) {
            abort(403, 'Zugriff verweigert.');
        }

        $product = $item->product;
        $config = $item->configuration ?? [];

        $logos = $side === 'back' 
            ? ($config['logos_back'] ?? $config['back']['logos'] ?? []) 
            : ($config['logos'] ?? $config['front']['logos'] ?? []);
        $texts = $side === 'back' 
            ? ($config['texts_back'] ?? $config['back']['texts'] ?? []) 
            : ($config['texts'] ?? $config['front']['texts'] ?? []);

        $productConfig = $product
            ? (is_string($product->configurator_settings) ? json_decode($product->configurator_settings, true) : ($product->configurator_settings ?? []))
            : [];
        $productConfig = $this->applyBackSideMirrorToProductConfig(is_array($productConfig) ? $productConfig : [], (string) $side);

        // Fallback & Aufbereitung für Schnell-Personalisierung / Neuling-Modus (custom_fields_data)
        $hasNonEmptyTexts = collect($texts)->contains(fn($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $rawCustomFieldsSvg = !empty($config['custom_fields_data']) && is_array($config['custom_fields_data'])
            ? $config['custom_fields_data']
            : (!empty($config['custom_fields']) && is_array($config['custom_fields']) ? $config['custom_fields'] : []);
        $isCustomFieldsConfig = (($config['personalization_mode'] ?? null) === 'custom_fields') || !empty($rawCustomFieldsSvg);
        if ($side !== 'back' && !empty($rawCustomFieldsSvg) && (!$hasNonEmptyTexts || ($isCustomFieldsConfig && empty($logos)))) {
            if (($productConfig['area_shape'] ?? 'rect') === 'custom' && !empty($productConfig['custom_points']) && is_array($productConfig['custom_points'])) {
                $xs = array_map(fn($p) => (float) ($p['x'] ?? 50), $productConfig['custom_points']);
                $ys = array_map(fn($p) => (float) ($p['y'] ?? 50), $productConfig['custom_points']);
                $areaLeft = min($xs);
                $areaTop = min($ys);
                $areaWidth = max(8.0, max($xs) - $areaLeft);
                $areaHeight = max(8.0, max($ys) - $areaTop);
            } else {
                $areaLeft = isset($productConfig['area_left']) ? (float) $productConfig['area_left'] : 10.0;
                $areaTop = isset($productConfig['area_top']) ? (float) $productConfig['area_top'] : 10.0;
                $areaWidth = isset($productConfig['area_width']) ? (float) $productConfig['area_width'] : 80.0;
                $areaHeight = isset($productConfig['area_height']) ? (float) $productConfig['area_height'] : 80.0;
            }
            $centerX = round($areaLeft + ($areaWidth / 2.0), 2);
            $centerY = round($areaTop + ($areaHeight / 2.0), 2);

            $defaultSymbolX = $centerX;
            $defaultSymbolY = round($areaTop + ($areaHeight * 0.36), 2);
            $defaultSymbolSize = round(max(32.0, min(110.0, min($areaWidth, $areaHeight) * 1.15)), 1);
            $defaultTextX = $centerX;
            $defaultTextY = round($areaTop + ($areaHeight * 0.64), 2);
            $defaultTextSize = 0.85;

            $pkgKey = (string) ($productConfig['template_package'] ?? ($product->slug ?? ''));
            if ($pkgKey === 'seelen-anhaenger') {
                $centerX = 50.0;
                $centerY = 72.0;
                $defaultSymbolX = 50.0;
                $defaultSymbolY = 65.5;
                $defaultSymbolSize = 38.0;
                $defaultTextX = 50.0;
                $defaultTextY = 76.0;
                $defaultTextSize = 0.72;
            } elseif ($pkgKey === 'seelen-pfote-hundemarke') {
                $centerX = 41.0;
                $centerY = 50.0;
                $defaultSymbolX = 41.0;
                $defaultSymbolY = 44.5;
                $defaultSymbolSize = 34.0;
                $defaultTextX = 41.0;
                $defaultTextY = 54.5;
                $defaultTextSize = 0.70;
            } elseif ($pkgKey === 'seelen-talisman') {
                $centerX = 50.0;
                $centerY = 66.0;
                $defaultSymbolX = 50.0;
                $defaultSymbolY = 56.0;
                $defaultSymbolSize = 46.0;
                $defaultTextX = 50.0;
                $defaultTextY = 73.0;
                $defaultTextSize = 0.74;
            } elseif (in_array($pkgKey, ['goldenes-seelen-herz', 'echtgold-seelen-herz'], true)) {
                $centerX = 50.0;
                $centerY = 57.0;
                $defaultSymbolX = 50.0;
                $defaultSymbolY = 49.0;
                $defaultSymbolSize = 42.0;
                $defaultTextX = 50.0;
                $defaultTextY = 61.5;
                $defaultTextSize = 0.82;
            } elseif (in_array($pkgKey, ['id-armband-silber-herz', 'id-armband-gold-herz'], true)) {
                $centerX = 57.0;
                $centerY = 50.0;
                $defaultSymbolX = 57.0;
                $defaultSymbolY = 46.5;
                $defaultSymbolSize = 22.0;
                $defaultTextX = 57.0;
                $defaultTextY = 53.0;
                $defaultTextSize = 0.56;
            } elseif ($pkgKey === 'seelen-kristall') {
                $centerX = 50.0;
                $centerY = 45.6;
                $defaultSymbolX = 50.0;
                $defaultSymbolY = 32.0;
                $defaultSymbolSize = 80.0;
                $defaultTextX = 50.0;
                $defaultTextY = 55.5;
                $defaultTextSize = 1.20;
            } elseif ($pkgKey === 'weizenglas-personalisiert') {
                $centerX = 50.0;
                $centerY = 47.0;
                $defaultSymbolX = 50.0;
                $defaultSymbolY = 35.0;
                $defaultSymbolSize = 72.0;
                $defaultTextX = 50.0;
                $defaultTextY = 55.0;
                $defaultTextSize = 1.05;
            }

            $symbolX = isset($productConfig['beginner_symbol_x']) && $productConfig['beginner_symbol_x'] !== '' && is_numeric($productConfig['beginner_symbol_x'])
                ? round((float) $productConfig['beginner_symbol_x'], 2)
                : $defaultSymbolX;
            $symbolY = isset($productConfig['beginner_symbol_y']) && $productConfig['beginner_symbol_y'] !== '' && is_numeric($productConfig['beginner_symbol_y'])
                ? round((float) $productConfig['beginner_symbol_y'], 2)
                : $defaultSymbolY;
            $symbolSize = isset($productConfig['beginner_symbol_size']) && $productConfig['beginner_symbol_size'] !== '' && is_numeric($productConfig['beginner_symbol_size'])
                ? round((float) $productConfig['beginner_symbol_size'], 1)
                : $defaultSymbolSize;

            $textX = isset($productConfig['beginner_text_x']) && $productConfig['beginner_text_x'] !== '' && is_numeric($productConfig['beginner_text_x'])
                ? round((float) $productConfig['beginner_text_x'], 2)
                : $defaultTextX;
            $textY = isset($productConfig['beginner_text_y']) && $productConfig['beginner_text_y'] !== '' && is_numeric($productConfig['beginner_text_y'])
                ? round((float) $productConfig['beginner_text_y'], 2)
                : $defaultTextY;
            $textSize = isset($productConfig['beginner_text_size']) && $productConfig['beginner_text_size'] !== '' && is_numeric($productConfig['beginner_text_size'])
                ? round((float) $productConfig['beginner_text_size'], 2)
                : $defaultTextSize;

            $symbolVectorMap = [
                'herz' => '/storage/produkte/configurator/vectors/Liebe/herz.svg',
                'pfote' => '/storage/produkte/configurator/vectors/Tiere/pfotenabdruck.svg',
                'unendlichkeit' => '/storage/produkte/configurator/vectors/Liebe/unendlichkeit.svg',
                'stern' => '/storage/produkte/configurator/vectors/Symbole/stern.svg',
                'schutzengel' => '/storage/produkte/configurator/vectors/Symbole/schutzengel.svg',
                'engel' => '/storage/produkte/configurator/vectors/Symbole/schutzengel.svg',
                'lebensbaum' => '/storage/produkte/configurator/vectors/Natur/lebensbaum.svg',
                'baum' => '/storage/produkte/configurator/vectors/Natur/lebensbaum.svg',
                'kleeblatt' => '/storage/produkte/configurator/vectors/Symbole/kleeblatt.svg',
                'klee' => '/storage/produkte/configurator/vectors/Symbole/kleeblatt.svg',
                'krone' => '/storage/produkte/configurator/vectors/Symbole/krone.svg',
                'anker' => '/storage/produkte/configurator/vectors/Symbole/anker.svg',
            ];

            $chosenFont = 'Arial';
            $customLines = [];
            $matchedSymbolUrl = null;

            foreach ($rawCustomFieldsSvg as $cfdKey => $cfd) {
                if (is_scalar($cfd)) {
                    $cfd = [
                        'id'    => (string) $cfdKey,
                        'type'  => str_contains(mb_strtolower((string) $cfdKey), 'font') ? 'font' : 'text',
                        'value' => (string) $cfd,
                    ];
                }
                if (!is_array($cfd)) {
                    continue;
                }
                $cfdVal = trim(str_replace(["\r\n", "\r"], "\n", (string) ($cfd['value'] ?? '')));
                if ($cfdVal === '') {
                    continue;
                }
                $cfdValLower = mb_strtolower($cfdVal);
                if (($cfd['type'] ?? 'text') === 'font') {
                    $chosenFont = $cfdVal;
                } elseif (!in_array($cfdValLower, ['kein symbol', 'keine', 'nein', '-'], true)) {
                    $foundVector = null;
                    foreach ($symbolVectorMap as $symKey => $symUrl) {
                        if (str_starts_with($cfdValLower, $symKey) || str_contains($cfdValLower, $symKey)) {
                            $foundVector = $symUrl;
                            break;
                        }
                    }
                    if ($foundVector !== null && $matchedSymbolUrl === null) {
                        $matchedSymbolUrl = $foundVector;
                    } else {
                        $customLines[] = $cfdVal;
                    }
                }
            }

            if ($matchedSymbolUrl !== null && empty($logos)) {
                $logos = [[
                    'url' => $matchedSymbolUrl,
                    'x' => $symbolX,
                    'y' => $symbolY,
                    'size' => $symbolSize,
                    'rotation' => 0,
                ]];
            }

            if (!empty($customLines)) {
                $maxCharsPerLine = max(12, min(28, (int) round($areaWidth * 0.56)));
                $wrappedLines = [];
                foreach (explode("\n", implode("\n", $customLines)) as $rawLine) {
                    $trimmedLine = trim($rawLine);
                    if ($trimmedLine === '') {
                        continue;
                    }
                    if (mb_strlen($trimmedLine) > $maxCharsPerLine && str_contains($trimmedLine, ' ')) {
                        foreach (explode("\n", wordwrap($trimmedLine, $maxCharsPerLine, "\n", true)) as $subLine) {
                            $subLine = trim($subLine);
                            if ($subLine !== '') {
                                $wrappedLines[] = $subLine;
                            }
                        }
                    } else {
                        $wrappedLines[] = $trimmedLine;
                    }
                }
                $finalBeginnerText = implode("\n", array_slice($wrappedLines, 0, 5));
                $lineCount = substr_count($finalBeginnerText, "\n") + 1;
                $effectiveTextSize = $lineCount === 2
                    ? round($textSize * 0.90, 2)
                    : ($lineCount === 3 ? round($textSize * 0.80, 2) : ($lineCount >= 4 ? round($textSize * 0.72, 2) : $textSize));

                $texts = [[
                    'text' => $finalBeginnerText,
                    'font' => $chosenFont,
                    'align' => 'center',
                    'x' => $textX,
                    'y' => $matchedSymbolUrl !== null ? $textY : $centerY,
                    'size' => $effectiveTextSize,
                    'rotation' => 0,
                ]];
            }
        }

        $overlayType = $config['overlay_type'] ?? ($productConfig['overlay_type'] ?? 'plane');

        // Physische Maße holen:
        // 1. Priorität: Dynamisch aus dem Varianten-Namen (z.B. "180x200 mm")
        // 2. Priorität: Aus den xTool-Parametern aus Schritt 4 (Backend Produkt-Konfiguration)
        // 3. Fallback: Aus den Produktstammdaten ($product->width / $product->height) oder 100x100mm
        $widthMm = null;
        $heightMm = null;

        if (!empty($config['variant_name'])) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX×]\s*(\d+(?:[.,]\d+)?)(?!\s*[xX×\d])\s*(mm|cm)\b/iu', $config['variant_name'], $matches)) {
                $unitFactor = strtolower($matches[3]) === 'cm' ? 10.0 : 1.0;
                $widthMm = floatval(str_replace(',', '.', $matches[1])) * $unitFactor;
                $heightMm = floatval(str_replace(',', '.', $matches[2])) * $unitFactor;
            } elseif (preg_match('/(\d{2,4})\s*[xX×]\s*(\d{2,4})(?!\s*[xX×\d])/u', $config['variant_name'], $matches)) {
                $widthMm = floatval($matches[1]);
                $heightMm = floatval($matches[2]);
            }
        }

        if (empty($widthMm) || empty($heightMm)) {
            if ($overlayType === 'plane') {
                if (!empty($productConfig['xtool_plane_width'])) {
                    $widthMm = floatval($productConfig['xtool_plane_width']);
                }
                if (!empty($productConfig['xtool_plane_height'])) {
                    $heightMm = floatval($productConfig['xtool_plane_height']);
                }
            } elseif ($overlayType === 'cylinder') {
                if (!empty($productConfig['xtool_d_top'])) {
                    $widthMm = floatval($productConfig['xtool_d_top']);
                }
                if (!empty($productConfig['xtool_height'])) {
                    $heightMm = floatval($productConfig['xtool_height']);
                }
            }
        }

        $calibration = $this->resolvePhysicalBlankCalibration($product, $productConfig, $config, true);
        $widthMm = $calibration['widthMm'];
        $heightMm = $calibration['heightMm'];

        $workingAreaPolygonPct = $this->buildWorkingAreaPolygonPercent($productConfig);
        if (!empty($calibration['plateBoxPct'])) {
            [$workingAreaPolygonPct, $logos, $texts] = $this->mapShopConfigOntoPhysicalBlank(
                $calibration['plateBoxPct'],
                $workingAreaPolygonPct,
                $logos,
                $texts
            );
        }
        $clipPointsSvg = implode(' ', array_map(function (array $pt) use ($widthMm, $heightMm) {
            $px = number_format(($pt['x'] / 100.0) * $widthMm, 4, '.', '');
            $py = number_format(($pt['y'] / 100.0) * $heightMm, 4, '.', '');
            return "{$px},{$py}";
        }, $workingAreaPolygonPct));

        // SVG Header initialisieren (inkl. xmlns:xcs für native xTool Studio / Creative Space Erkennung)
        $svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
        $svg .= '<svg width="'.$widthMm.'mm" height="'.$heightMm.'mm" viewBox="0 0 '.$widthMm.' '.$heightMm.'" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xcs="https://www.xtool.com/pages/software">' . "\n";

        if ($clipPointsSvg !== '') {
            $svg .= "  <defs>\n";
            $svg .= '    <clipPath id="workingAreaClip">' . "\n";
            $svg .= '      <polygon points="' . $clipPointsSvg . '" />' . "\n";
            $svg .= "    </clipPath>\n";
            $svg .= "  </defs>\n";
        }

        // EMBED FONTS
        $usedFonts = [];
        if (!empty($texts)) {
            foreach ($texts as $textItem) {
                if (empty(trim($textItem['text'] ?? ''))) continue;
                $fontFamily = $textItem['font'] ?? 'Arial';
                if (!in_array($fontFamily, ['Arial', 'Times New Roman', 'Verdana', 'Courier New', 'Georgia'])) {
                    $usedFonts[$fontFamily] = true;
                }
            }
        }

        if (!empty($usedFonts)) {
            $svg .= "  <style>\n";
            foreach (array_keys($usedFonts) as $fontFamily) {
                $slug = Str::slug($fontFamily);
                $safeCssFontFamily = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', (string) $fontFamily);
                $srcParts = [];

                $ttfPath = $this->resolveTtfFontPath((string) $fontFamily);
                if ($ttfPath && is_file($ttfPath)) {
                    $ttfBase64 = base64_encode((string) file_get_contents($ttfPath));
                    $srcParts[] = "url('data:font/ttf;charset=utf-8;base64,{$ttfBase64}') format('truetype')";
                }

                $fontFiles = collect(File::files(public_path('fonts')))->filter(function($file) use ($slug) {
                    return Str::startsWith($file->getFilename(), $slug . '-');
                });

                if ($fontFiles->isNotEmpty()) {
                    $bestFont = $fontFiles->first(function($file) {
                        return str_contains($file->getFilename(), '700') || str_contains($file->getFilename(), 'regular') || str_contains($file->getFilename(), '400');
                    }) ?? $fontFiles->first();

                    $base64 = base64_encode((string) file_get_contents($bestFont->getPathname()));
                    $srcParts[] = "url('data:font/woff2;charset=utf-8;base64,{$base64}') format('woff2')";
                }

                if (!empty($srcParts)) {
                    $srcCss = implode(",\n           ", $srcParts);
                    foreach (['normal', 'bold'] as $fw) {
                        $svg .= "    @font-face {\n";
                        $svg .= "      font-family: '{$safeCssFontFamily}';\n";
                        $svg .= "      src: {$srcCss};\n";
                        $svg .= "      font-weight: {$fw};\n";
                        $svg .= "      font-style: normal;\n";
                        $svg .= "    }\n";
                    }
                }
            }
            $svg .= "  </style>\n";
        }

        // HINWEIS: Kein roter Hilfsrahmen (<rect stroke="#FF0000">) mehr im SVG,
        // da xTool Studio / XCS sonst standardmäßig eine aktive Liniengravur daraus macht.
        if ($clipPointsSvg !== '') {
            $svg .= '  <g clip-path="url(#workingAreaClip)">' . "\n";
        }

        // Logos vor dem SVG-Aufbau exakt nach Konfigurator-zIndex (Default 10 + idx) sortieren,
        // damit Vektor-Symbole mit höherem zIndex (z. B. weihnachtsbaum.svg zIndex=31) über dem Porträtfoto (zIndex=11) liegen.
        if (!empty($logos) && is_array($logos)) {
            $indexedLogos = [];
            foreach (array_values($logos) as $origIdx => $lEntry) {
                if (!is_array($lEntry)) {
                    continue;
                }
                $z = (isset($lEntry['zIndex']) && is_numeric($lEntry['zIndex']))
                    ? (float) $lEntry['zIndex']
                    : (float) (10 + $origIdx);
                $indexedLogos[] = ['z' => $z, 'idx' => $origIdx, 'logo' => $lEntry];
            }
            usort($indexedLogos, static fn (array $a, array $b): int => ($a['z'] <=> $b['z']) ?: ($a['idx'] <=> $b['idx']));
            $logos = array_column($indexedLogos, 'logo');
        }

        $textsBakedIntoRaster = false;
        $lastRasterLogoIndex = null;
        if (!empty($logos)) {
            foreach ($logos as $lIdx => $lCandidate) {
                $candUrl = (string) ($lCandidate['url'] ?? $lCandidate['value'] ?? $lCandidate['src'] ?? '');
                if ($candUrl !== '' && !str_ends_with(strtolower(parse_url($candUrl, PHP_URL_PATH) ?? $candUrl), '.svg') && !str_starts_with($candUrl, 'data:image/svg')) {
                    $lastRasterLogoIndex = $lIdx;
                }
            }
        }

        // BILDER & LOGOS RENDER (Base64)
        if (!empty($logos)) {
            foreach ($logos as $logoIdx => $logo) {
                $x = (((float) ($logo['x'] ?? 50)) / 100) * $widthMm;
                $y = (((float) ($logo['y'] ?? 50)) / 100) * $heightMm;
                $rot = (float) ($logo['rotation'] ?? 0);
                // Skalierungs-Logik identisch zum JS Canvas
                $logoWidth = (((float) ($logo['size'] ?? 100)) / 500) * $widthMm;

                $url = $logo['url'] ?? $logo['value'] ?? $logo['src'] ?? null;
                if (!$url && empty($logo['value'])) continue;

                $resolvedLogoSource = $this->resolveEffectiveLogoSourceForLaser($logo, $item, is_array($config) ? $config : []);
                $data = $resolvedLogoSource['bytes'];
                $ext = $resolvedLogoSource['ext'];
                $isStudioAdjustedLogo = (bool) ($resolvedLogoSource['is_adjusted'] ?? false);
                $studioAdjustmentMeta = is_array($resolvedLogoSource['adjustment'] ?? null) ? $resolvedLogoSource['adjustment'] : null;

                if ($data === null && $url && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower((string) $url), 'https://')) {
                    $parsedHost = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
                    $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
                    $resolvedIps = $parsedHost !== '' ? (@gethostbynamel($parsedHost) ?: []) : [];
                    $allIpsPublic = !empty($resolvedIps) && collect($resolvedIps)->every(
                        fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                    );
                    $candidateExt = strtolower(pathinfo((string) parse_url((string) $url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    if ($parsedHost !== '' && $parsedHost === $appHost && $allIpsPublic && in_array($candidateExt, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                        try {
                            $remoteResp = \Illuminate\Support\Facades\Http::timeout(5)->withoutRedirecting()->get((string) $url);
                            if ($remoteResp->successful() && strlen($remoteResp->body()) <= 10 * 1024 * 1024) {
                                $ext = $candidateExt;
                                $data = $remoteResp->body();
                            }
                        } catch (\Throwable) {
                        }
                    }
                }

                if ($data !== null) {
                    if ($ext === 'svg') {
                        // SVG strikt per DOMDocument-Allowlist bereinigen (kein SSRF, kein XSS, keine fremden Namespaces)
                        $cleanSvgDoc = \App\Support\SafeHtmlAndSvgSanitizer::sanitizeSvg((string) $data);
                        if ($cleanSvgDoc === '') {
                            continue;
                        }
                        // SVG inlinen, aber OHNE <svg> Tag, da XCS verschachtelte SVGs ignoriert!
                        $innerSvg = preg_replace('/<\?xml.*?\?>/is', '', $cleanSvgDoc);

                        $viewBoxW = 24; $viewBoxH = 24; $vX = 0; $vY = 0;
                        if (preg_match('/<svg[^>]*viewBox="([^"]+)"/i', $innerSvg, $m)) {
                            $vParts = preg_split('/[\s,]+/', trim($m[1]));
                            if (count($vParts) >= 4) {
                                $vX = floatval($vParts[0]);
                                $vY = floatval($vParts[1]);
                                $viewBoxW = floatval($vParts[2]);
                                $viewBoxH = floatval($vParts[3]);
                            }
                        } elseif (preg_match('/<svg[^>]*width="([0-9\.]+)[a-z]*"/i', $innerSvg, $mw) && preg_match('/<svg[^>]*height="([0-9\.]+)[a-z]*"/i', $innerSvg, $mh)) {
                            $viewBoxW = floatval($mw[1]);
                            $viewBoxH = floatval($mh[1]);
                        }

                        // Nur den echten Vektor-Inhalt extrahieren
                        if (preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $innerSvg, $m)) {
                            $innerSvg = $m[1];
                        } else {
                            $innerSvg = preg_replace('/<svg[^>]*>/is', '', $innerSvg);
                            $innerSvg = str_replace('</svg>', '', $innerSvg);
                        }

                        // XCS KOMPATIBILITÄT: Alles aggressiv auf SCHWARZ mappen und störende Elemente entfernen!
                        // Entferne <style>, <defs> und <title> Blöcke komplett, da XCS sie oft als Artefakte interpretiert
                        $innerSvg = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $innerSvg);
                        $innerSvg = preg_replace('/<defs[^>]*>.*?<\/defs>/is', '', $innerSvg);
                        $innerSvg = preg_replace('/<title[^>]*>.*?<\/title>/is', '', $innerSvg);
                        
                        // Entferne id, class, data-* Attribute die ggf. Styles laden
                        $innerSvg = preg_replace('/\s(?:id|class|data-[a-zA-Z0-9\-]+)="[^"]*"/i', '', $innerSvg);

                        // Entferne alte fill/stroke Attribute komplett, damit wir saubere Elemente haben
                        $innerSvg = preg_replace('/\sfill="[^"]*"/i', '', $innerSvg);
                        $innerSvg = preg_replace('/\sstroke="[^"]*"/i', '', $innerSvg);

                        // XCS INHERITANCE BUG FIX: 
                        // XCS liest fill="#000000" auf dem <g> Tag oft nicht richtig aus. 
                        // Wir müssen JEDES einzelne grafische Element hartcodiert auf schwarz zwingen!
                        $innerSvg = preg_replace(
                            '/<(path|circle|rect|ellipse|polygon|polyline)([^>]*)>/i', 
                            '<$1 fill="#000000"$2>', 
                            $innerSvg
                        );

                        $fillAttr = ''; // Wird nicht mehr als Failsafe auf <g> benötigt

                        $scaleS = $logoWidth / max(1, $viewBoxW);
                        $cx = $vX + ($viewBoxW / 2);
                        $cy = $vY + ($viewBoxH / 2);

                        $svg .= '  <g transform="translate('.number_format($x,4,'.','').', '.number_format($y,4,'.','').') rotate('.number_format($rot,4,'.','').')">' . "\n";
                        $svg .= '    <g transform="scale('.number_format($scaleS,6,'.','').') translate('.number_format(-$cx,6,'.','').', '.number_format(-$cy,6,'.','').')"' . $fillAttr . '>' . "\n";
                        $svg .= $innerSvg . "\n";
                        $svg .= '    </g>' . "\n";
                        $svg .= '  </g>' . "\n";

                        continue; // base64 <image> wird übersprungen
                    } else {
                        $textsForThisRaster = ($logoIdx === $lastRasterLogoIndex && is_array($texts)) ? $texts : [];
                        $svgLogoPhotoProfile = $this->resolveLaserPhotoProfile($product, is_array($productConfig) ? $productConfig : []);
                        if ($isStudioAdjustedLogo) {
                            $svgLogoPhotoProfile['skip_halftone_processing'] = true;
                            $svgLogoPhotoProfile['studio_dither_mode'] = strtolower((string) ($studioAdjustmentMeta['dither_mode'] ?? 'jarvis'));
                        }
                        $clippedRaster = $this->clipAndNormalizeRasterLogoForLaser(
                            $data,
                            $ext,
                            $logo,
                            (float) $widthMm,
                            (float) $heightMm,
                            $workingAreaPolygonPct,
                            $textsForThisRaster,
                            false,
                            $svgLogoPhotoProfile
                        );

                        if ($clippedRaster !== null) {
                            if (!empty($textsForThisRaster)) {
                                $textsBakedIntoRaster = true;
                            }
                            $url = 'data:image/png;base64,' . base64_encode($clippedRaster['pngData']);
                            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8');
                            $fmtX = number_format((float) $clippedRaster['xMm'], 4, '.', '');
                            $fmtY = number_format((float) $clippedRaster['yMm'], 4, '.', '');
                            $fmtW = number_format((float) $clippedRaster['widthMm'], 4, '.', '');
                            $fmtH = number_format((float) $clippedRaster['heightMm'], 4, '.', '');
                            $svg .= '  <image x="'.$fmtX.'" y="'.$fmtY.'" width="'.$fmtW.'" height="'.$fmtH.'" data-view-type="laser" xcs:image="true" href="'.$safeUrl.'" xlink:href="'.$safeUrl.'" preserveAspectRatio="none" />' . "\n";
                            continue;
                        }

                        [$normalizedData, $normalizedType] = $this->normalizeRasterImageForLaser($data, $ext);
                        $url = 'data:image/' . $normalizedType . ';base64,' . base64_encode($normalizedData);
                    }
                } else {
                    continue;
                }

                $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $halfW = number_format(((float) $logoWidth) / 2, 4, '.', '');
                $fmtW = number_format((float) $logoWidth, 4, '.', '');
                $fmtX = number_format((float) $x, 4, '.', '');
                $fmtY = number_format((float) $y, 4, '.', '');
                $fmtRot = number_format((float) $rot, 4, '.', '');

                $svg .= '  <g transform="translate('.$fmtX.', '.$fmtY.') rotate('.$fmtRot.')">' . "\n";
                $svg .= '    <image x="-'.$halfW.'" y="-'.$halfW.'" width="'.$fmtW.'" height="'.$fmtW.'" data-view-type="laser" xcs:image="true" href="'.$safeUrl.'" xlink:href="'.$safeUrl.'" preserveAspectRatio="xMidYMid meet" />' . "\n";
                $svg .= '  </g>' . "\n";
            }
        }

        // TEXTE RENDER (Im SVG immer als echte schwarze Vektor-Textebene für xTool Studio exportieren;
        // darunterliegende Fotos erhalten in clipAndNormalizeRasterLogoForLaser automatisch einen Weiß-Knockout)
        if (!empty($texts) && !$textsBakedIntoRaster) {
            foreach ($texts as $textItem) {
                // Verhindert Rendering von leeren Textboxen
                if (empty(trim($textItem['text'] ?? ''))) continue;

                $x = (((float) ($textItem['x'] ?? 50)) / 100) * $widthMm;
                $y = (((float) ($textItem['y'] ?? 50)) / 100) * $heightMm;
                $rot = (float) ($textItem['rotation'] ?? 0);
                $fontFamily = $textItem['font'] ?? 'Arial';
                $align = $textItem['align'] ?? 'center';

                $fontSizeMm = ((float) ($textItem['size'] ?? 1)) * ($widthMm / 25);

                $textAnchor = match($align) {
                    'left' => 'start',
                    'right' => 'end',
                    default => 'middle'
                };

                $svg .= '  <g transform="translate('.$x.', '.$y.') rotate('.$rot.')">' . "\n";

                $lines = explode("\n", $textItem['text'] ?? '');
                $lineHeight = $fontSizeMm * 1.15;
                $totalHeight = (count($lines) - 1) * $lineHeight;
                $startY = -$totalHeight / 2;

                $safeFontFamily = htmlspecialchars((string) $fontFamily, ENT_QUOTES, 'UTF-8');
                $safeFontSize = (float) $fontSizeMm;
                $curve = max(-100.0, min(100.0, (float) ($textItem['curve'] ?? 0)));
                // Wichtig für xTool Studio: Kein stroke="#FFFFFF" am <text>-Element setzen, da xTool Studio
                // Elemente mit stroke automatisch als "Liniengravur (1%, 20mm/s, ! Parameter ändern)" einstuft.
                $strokeAttr = '';

                if (abs($curve) >= 0.5) {
                    $ttfForMeasure = $this->resolveTtfFontPath((string) $fontFamily);
                    $curvedChars = $this->computeCurvedTextArcChars((string) ($textItem['text'] ?? ''), $safeFontSize, $curve, $ttfForMeasure);
                    $baselineOffset = number_format($safeFontSize * 0.41, 4, '.', '');
                    foreach ($curvedChars as $c) {
                        if (trim((string) $c['char']) === '') {
                            continue;
                        }
                        $cx = number_format((float) $c['x'], 4, '.', '');
                        $cy = number_format((float) $c['y'], 4, '.', '');
                        $cdeg = number_format((float) $c['deg'], 4, '.', '');
                        $svg .= '    <text x="0" y="'.$baselineOffset.'" transform="translate('.$cx.', '.$cy.') rotate('.$cdeg.')" font-family="'.$safeFontFamily.'" font-size="'.$safeFontSize.'" font-weight="bold" fill="#000000"'.$strokeAttr.' text-anchor="middle">'.htmlspecialchars((string) $c['char'], ENT_QUOTES, 'UTF-8').'</text>' . "\n";
                    }
                } else {
                    foreach ($lines as $line) {
                        $svg .= '    <text x="0" y="'.($startY + ($safeFontSize * 0.41)).'" font-family="'.$safeFontFamily.'" font-size="'.$safeFontSize.'" font-weight="bold" fill="#000000"'.$strokeAttr.' text-anchor="'.$textAnchor.'">'.htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8').'</text>' . "\n";
                        $startY += $lineHeight;
                    }
                }

                $svg .= '  </g>' . "\n";
            }
        }

        if ($clipPointsSvg !== '') {
            $svg .= "  </g>\n";
        }

        $svg .= '</svg>';

        $safeOrderNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($item->order->order_number ?? 'Angebot')) ?: 'Angebot';
        $safeItemId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $item->id) ?: '0';
        $filename = 'xTool-F2-Druckdatei-' . $safeOrderNumber . '-Pos-' . $safeItemId . '-' . ($side === 'back' ? 'Rueckseite' : 'Vorderseite') . '.svg';

        return response()->streamDownload(function() use ($svg) {
            echo $svg;
        }, $filename, [
            'Content-Type' => 'image/svg+xml',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Lädt das auf den Arbeitsbereich (z. B. 72-Punkt-Herz) hart maskierte und
     * exakt auf die Bounding-Box zugeschnittene Gravur-PNG für xTool Studio herunter.
     */
    public function downloadLaserPng($itemId, $side = 'front')
    {
        $item = ($itemId instanceof OrderOrderItem)
            ? $itemId
            : OrderOrderItem::with(['product', 'order'])->findOrFail($itemId);

        $isAdminOrEmployee = \Illuminate\Support\Facades\Auth::guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();
        $isCustomerOwner = false;
        if (\Illuminate\Support\Facades\Auth::guard('customer')->check()) {
            $customer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
            if ($item->order && (string) $item->order->customer_id === (string) $customer->id) {
                $isCustomerOwner = true;
            }
        }
        if (!$isAdminOrEmployee && !$isCustomerOwner && !request()->hasValidSignature()) {
            abort(403, 'Zugriff verweigert.');
        }

        $config = $item->configuration ?? [];
        $product = $item->product;

        $logos = $side === 'back' ? ($config['logos_back'] ?? []) : ($config['logos'] ?? []);
        $texts = $side === 'back'
            ? ($config['texts_back'] ?? $config['back']['texts'] ?? [])
            : ($config['texts'] ?? $config['front']['texts'] ?? []);
        if (empty($logos) && $side === 'front' && !empty($config['logo_storage_path'])) {
            $logos = [[
                'url' => '/storage/' . ltrim($config['logo_storage_path'], '/'),
                'x' => 50,
                'y' => 50,
                'size' => 100,
                'rotation' => 0,
            ]];
        }

        $productConfig = $product
            ? (is_string($product->configurator_settings)
                ? json_decode($product->configurator_settings, true)
                : ($product->configurator_settings ?? []))
            : [];
        $overlayType = $config['overlay_type'] ?? ($productConfig['overlay_type'] ?? 'plane');

        $widthMm = null;
        $heightMm = null;
        if (!empty($config['variant_name'])) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX×]\s*(\d+(?:[.,]\d+)?)(?!\s*[xX×\d])\s*(mm|cm)\b/iu', $config['variant_name'], $matches)) {
                $unitFactor = strtolower($matches[3]) === 'cm' ? 10.0 : 1.0;
                $widthMm = floatval(str_replace(',', '.', $matches[1])) * $unitFactor;
                $heightMm = floatval(str_replace(',', '.', $matches[2])) * $unitFactor;
            } elseif (preg_match('/(\d{2,4})\s*[xX×]\s*(\d{2,4})(?!\s*[xX×\d])/u', $config['variant_name'], $matches)) {
                $widthMm = floatval($matches[1]);
                $heightMm = floatval($matches[2]);
            }
        }
        if (empty($widthMm) || empty($heightMm)) {
            if ($overlayType === 'plane') {
                if (!empty($productConfig['xtool_plane_width'])) {
                    $widthMm = floatval($productConfig['xtool_plane_width']);
                }
                if (!empty($productConfig['xtool_plane_height'])) {
                    $heightMm = floatval($productConfig['xtool_plane_height']);
                }
            } elseif ($overlayType === 'cylinder') {
                if (!empty($productConfig['xtool_d_top'])) {
                    $widthMm = floatval($productConfig['xtool_d_top']);
                }
                if (!empty($productConfig['xtool_height'])) {
                    $heightMm = floatval($productConfig['xtool_height']);
                }
            }
        }
        $calibration = $this->resolvePhysicalBlankCalibration($product, $productConfig, $config, true);
        $widthMm = $calibration['widthMm'];
        $heightMm = $calibration['heightMm'];

        $workingAreaPolygonPct = $this->buildWorkingAreaPolygonPercent($productConfig);
        if (!empty($calibration['plateBoxPct'])) {
            [$workingAreaPolygonPct, $logos, $texts] = $this->mapShopConfigOntoPhysicalBlank(
                $calibration['plateBoxPct'],
                $workingAreaPolygonPct,
                $logos,
                $texts
            );
        }
        $pngOut = null;

        // Falls neben einem Rasterfoto auch Vektor-Symbole (z. B. weihnachtsbaum.svg mit zIndex=31) enthalten sind,
        // rendern wir über die gemeinsame zIndex-Stage-Engine und schneiden exakt auf die Tight-Bounding-Box zu.
        $hasSvgInLogos = false;
        $hasRasterInLogos = false;
        foreach ($logos as $lCheck) {
            $uCheck = strtolower((string) ($lCheck['url'] ?? $lCheck['value'] ?? ''));
            if ($uCheck === '') {
                continue;
            }
            if (str_ends_with((string) parse_url($uCheck, PHP_URL_PATH), '.svg') || str_starts_with($uCheck, 'data:image/svg')) {
                $hasSvgInLogos = true;
            } else {
                $hasRasterInLogos = true;
            }
        }

        if ($hasSvgInLogos && $hasRasterInLogos) {
            $pngPhotoProfile = $this->resolveLaserPhotoProfile($product, is_array($productConfig) ? $productConfig : []);
            $stageRendered = $this->renderFullStageLaserPng(
                (float) $widthMm,
                (float) $heightMm,
                $workingAreaPolygonPct,
                $logos,
                is_array($texts) ? $texts : [],
                $item,
                (bool) $pngPhotoProfile['is_dark_surface'],
                $pngPhotoProfile
            );
            $stageGd = @imagecreatefromstring($stageRendered['pngBytes'] ?? '');
            if ($stageGd) {
                $sw = imagesx($stageGd);
                $sh = imagesy($stageGd);
                $tMinX = $sw;
                $tMaxX = -1;
                $tMinY = $sh;
                $tMaxY = -1;
                for ($sy = 0; $sy < $sh; $sy++) {
                    for ($sx = 0; $sx < $sw; $sx++) {
                        if (((imagecolorat($stageGd, $sx, $sy) >> 24) & 0x7F) < 126) {
                            if ($sx < $tMinX) $tMinX = $sx;
                            if ($sx > $tMaxX) $tMaxX = $sx;
                            if ($sy < $tMinY) $tMinY = $sy;
                            if ($sy > $tMaxY) $tMaxY = $sy;
                        }
                    }
                }
                if ($tMaxX >= $tMinX && $tMaxY >= $tMinY) {
                    $cw = $tMaxX - $tMinX + 1;
                    $ch = $tMaxY - $tMinY + 1;
                    $cropGd = imagecreatetruecolor($cw, $ch);
                    imagealphablending($cropGd, false);
                    imagesavealpha($cropGd, true);
                    $cTrans = imagecolorallocatealpha($cropGd, 255, 255, 255, 127);
                    imagefill($cropGd, 0, 0, $cTrans);
                    imagecopy($cropGd, $stageGd, 0, 0, $tMinX, $tMinY, $cw, $ch);
                    ob_start();
                    imagepng($cropGd, null, 3);
                    $pngOut = (string) ob_get_clean();
                    imagedestroy($cropGd);
                }
                imagedestroy($stageGd);
            }
        }

        if (!$pngOut) {
        foreach ($logos as $logoItem) {
            if (!is_array($logoItem)) {
                continue;
            }
            $resolvedPngLogo = $this->resolveEffectiveLogoSourceForLaser($logoItem, $item, is_array($config) ? $config : []);
            $data = $resolvedPngLogo['bytes'];
            $ext = $resolvedPngLogo['ext'];

            if ($data !== null && $ext !== 'svg') {
                $pngLogoProfile = $this->resolveLaserPhotoProfile($product, is_array($productConfig) ? $productConfig : []);
                if (!empty($resolvedPngLogo['is_adjusted'])) {
                    $pngLogoProfile['skip_halftone_processing'] = true;
                    $pngLogoProfile['studio_dither_mode'] = strtolower((string) ($resolvedPngLogo['adjustment']['dither_mode'] ?? 'jarvis'));
                }
                $clipped = $this->clipAndNormalizeRasterLogoForLaser(
                    $data,
                    $ext,
                    $logoItem,
                    (float) $widthMm,
                    (float) $heightMm,
                    $workingAreaPolygonPct,
                    is_array($texts) ? $texts : [],
                    false,
                    $pngLogoProfile
                );
                if ($clipped !== null) {
                    $pngOut = $clipped['pngData'];
                    break;
                }
                [$normalizedData] = $this->normalizeRasterImageForLaser($data, $ext);
                $pngOut = $normalizedData;
                break;
            }
        }
        }

        if (!$pngOut) {
            return null;
        }

        $safeOrderNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($item->order->order_number ?? 'Angebot')) ?: 'Angebot';
        $safeItemId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $item->id) ?: '0';
        $filename = 'xTool-Gravurbild-' . $safeOrderNumber . '-Pos-' . $safeItemId . '-' . ($side === 'back' ? 'Rueckseite' : 'Vorderseite') . '.png';

        return response()->streamDownload(function() use ($pngOut) {
            echo $pngOut;
        }, $filename, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Lädt ein fix-und-fertig konfiguriertes xTool Studio v1.10.15 Projekt (.xcs / xcs-workspace-v2)
     * inkl. Produkt-Laserparametern, Fokus-Abstand, Gravur-Motiv und cyanfarbener 1:1 Geister-Kontur herunter.
     */
    public function downloadLaserXcs(
        $itemId,
        $side = 'front',
        ?float $centerXMm = null,
        ?float $centerYMm = null,
        float $angleDeg = 0.0,
        ?float $widthMm = null,
        ?float $heightMm = null
    ) {
        $this->authorizeLaserExportAccess($itemId);

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $xcsData = $cockpit->generateXcsProjectBytes($itemId, (string) $side, $centerXMm, $centerYMm, $angleDeg, $widthMm, $heightMm);

        return response()->streamDownload(function () use ($xcsData) {
            echo $xcsData['bytes'];
        }, $xcsData['filename'], [
            'Content-Type'           => 'application/octet-stream',
            'Content-Disposition'    => 'attachment; filename="' . $xcsData['filename'] . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Lädt das direkte xTool F2 Ultra UV Druckpaket (.xf POSIX TAR-Archiv) herunter.
     */
    public function downloadLaserXf(
        $itemId,
        $side = 'front',
        ?float $centerXMm = null,
        ?float $centerYMm = null,
        float $angleDeg = 0.0,
        ?float $widthMm = null,
        ?float $heightMm = null
    ) {
        $this->authorizeLaserExportAccess($itemId);

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $xfData = $cockpit->generateXfPackageBytes($itemId, (string) $side, $centerXMm, $centerYMm, $angleDeg, $widthMm, $heightMm);

        return response()->streamDownload(function () use ($xfData) {
            echo $xfData['bytes'];
        }, $xfData['filename'], [
            'Content-Type'           => 'application/x-tar',
            'Content-Disposition'    => 'attachment; filename="' . $xfData['filename'] . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function resolveAndAuthorizeLaserItem($itemId, bool $skipAuthCheck = false)
    {
        if ($itemId instanceof OrderOrderItem
            || $itemId instanceof \App\Models\Order\OrderQuoteRequestItem
            || $itemId instanceof \App\Models\Product\LaserStudioProject) {
            $item = $itemId;
        } else {
            $item = OrderOrderItem::with(['product', 'order'])->find($itemId)
                ?? \App\Models\Order\OrderQuoteRequestItem::with(['product', 'quoteRequest'])->find($itemId)
                ?? (\Illuminate\Support\Facades\Schema::hasTable('laser_studio_projects')
                    ? \App\Models\Product\LaserStudioProject::with('product')->find($itemId)
                    : null);
            if (!$item) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(OrderOrderItem::class, [$itemId]);
            }
        }

        if (!$skipAuthCheck) {
            $this->authorizeLaserExportAccess($item);
        }

        return $item;
    }

    protected function authorizeLaserExportAccess($itemOrId): void
    {
        $item = ($itemOrId instanceof OrderOrderItem
            || $itemOrId instanceof \App\Models\Order\OrderQuoteRequestItem
            || $itemOrId instanceof \App\Models\Product\LaserStudioProject)
            ? $itemOrId
            : (OrderOrderItem::with('order')->find($itemOrId)
                ?? \App\Models\Order\OrderQuoteRequestItem::with('quoteRequest')->find($itemOrId)
                ?? (\Illuminate\Support\Facades\Schema::hasTable('laser_studio_projects')
                    ? \App\Models\Product\LaserStudioProject::find($itemOrId)
                    : null));

        if (!$item) {
            abort(404);
        }

        $isAdminOrEmployee = \Illuminate\Support\Facades\Auth::guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();
        $isCustomerOwner = false;
        if (\Illuminate\Support\Facades\Auth::guard('customer')->check()) {
            $customer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
            if ($item instanceof OrderOrderItem && $item->order && (string) $item->order->customer_id === (string) $customer->id) {
                $isCustomerOwner = true;
            } elseif ($item instanceof \App\Models\Order\OrderQuoteRequestItem && $item->quoteRequest && strtolower((string) $item->quoteRequest->email) === strtolower((string) $customer->email)) {
                $isCustomerOwner = true;
            }
        }

        if (!$isAdminOrEmployee && !$isCustomerOwner && !request()->hasValidSignature()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    /**
     * Bereitet die kompletten Geometrie-, Kontur- und Bitmap-Daten einer Bestellposition
     * für das Live-Laser-Cockpit sowie den .xcs / .xf Export auf.
     *
     * @return array{
     *   item: mixed,
     *   product: ?\App\Models\Product\Product,
     *   side: string,
     *   widthMm: float,
     *   heightMm: float,
     *   workingAreaPolygonPct: array<int, array{x: float, y: float}>,
     *   ghostContourDPath: string,
     *   stageEngravePngBytes: string,
     *   stagePixelWidth: int,
     *   stagePixelHeight: int,
     *   previewDataUrl: string
     * }
     */
    public function buildLaserDesignPayload($itemId, string $side = 'front', bool $skipAuthCheck = false): array
    {
        $item = $this->resolveAndAuthorizeLaserItem($itemId, $skipAuthCheck);
        $product = $item->product;
        $config = $item->configuration ?? [];

        $logos = $side === 'back'
            ? ($config['logos_back'] ?? $config['back']['logos'] ?? [])
            : ($config['logos'] ?? $config['front']['logos'] ?? []);
        $texts = $side === 'back'
            ? ($config['texts_back'] ?? $config['back']['texts'] ?? [])
            : ($config['texts'] ?? $config['front']['texts'] ?? []);

        if (empty($logos) && $side === 'front' && !empty($config['logo_storage_path'])) {
            $logos = [[
                'url'      => '/storage/' . ltrim((string) $config['logo_storage_path'], '/'),
                'x'        => 50,
                'y'        => 50,
                'size'     => 100,
                'rotation' => 0,
            ]];
        }

        $productConfig = ($product && empty($config['is_laser_studio_project']))
            ? (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : ($product->configurator_settings ?? []))
            : (!empty($config['is_laser_studio_project'])
                ? (function () use ($item, $product, $config, $side): array {
                    if ($item instanceof \App\Models\Product\LaserStudioProject && $item->mode !== 'product_blank') {
                        return $item->toVirtualConfiguratorProduct($side)->configurator_settings ?? [];
                    }
                    if ($product) {
                        $pCfg = is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : ($product->configurator_settings ?? []);
                        return is_array($pCfg) ? $pCfg : [];
                    }
                    return [
                        'overlay_type' => 'plane',
                        'area_shape' => (($config['studio_contour_shape'] ?? 'rect') === 'circle') ? 'circle' : 'rect',
                        'area_left' => 0.0,
                        'area_top' => 0.0,
                        'area_width' => 100.0,
                        'area_height' => 100.0,
                        'xtool_plane_width' => (float) ($config['studio_width_mm'] ?? 200.0),
                        'xtool_plane_height' => (float) ($config['studio_height_mm'] ?? 200.0),
                    ];
                })()
                : []);
        if (empty($config['is_laser_studio_project']) || (($item->mode ?? '') === 'product_blank')) {
            $productConfig = $this->applyBackSideMirrorToProductConfig(is_array($productConfig) ? $productConfig : [], $side);
        }

        // Aufbereitung für Schnell-Personalisierung / Neuling-Modus (custom_fields_data)
        $hasNonEmptyTexts = collect($texts)->contains(fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $rawCustomFields = !empty($config['custom_fields_data']) && is_array($config['custom_fields_data'])
            ? $config['custom_fields_data']
            : (!empty($config['custom_fields']) && is_array($config['custom_fields']) ? $config['custom_fields'] : []);
        $isCustomFieldsConfig = (($config['personalization_mode'] ?? null) === 'custom_fields') || !empty($rawCustomFields);

        if (($productConfig['area_shape'] ?? 'rect') === 'custom' && !empty($productConfig['custom_points']) && is_array($productConfig['custom_points'])) {
            $xs = array_map(fn ($p) => (float) ($p['x'] ?? 50), $productConfig['custom_points']);
            $ys = array_map(fn ($p) => (float) ($p['y'] ?? 50), $productConfig['custom_points']);
            $areaLeft = min($xs);
            $areaTop = min($ys);
            $areaWidth = max(8.0, max($xs) - $areaLeft);
            $areaHeight = max(8.0, max($ys) - $areaTop);
        } else {
            $areaLeft = isset($productConfig['area_left']) ? (float) $productConfig['area_left'] : 10.0;
            $areaTop = isset($productConfig['area_top']) ? (float) $productConfig['area_top'] : 10.0;
            $areaWidth = isset($productConfig['area_width']) ? (float) $productConfig['area_width'] : 80.0;
            $areaHeight = isset($productConfig['area_height']) ? (float) $productConfig['area_height'] : 80.0;
        }
        $defaultAreaCenterX = round($areaLeft + ($areaWidth / 2.0), 2);
        $defaultAreaCenterY = round($areaTop + ($areaHeight / 2.0), 2);

        if ($side !== 'back' && !empty($rawCustomFields) && (!$hasNonEmptyTexts || ($isCustomFieldsConfig && empty($logos)))) {
            $centerX = $defaultAreaCenterX;
            $centerY = $defaultAreaCenterY;

            $defaultSymbolX = $centerX;
            $defaultSymbolY = round($areaTop + ($areaHeight * 0.36), 2);
            $defaultSymbolSize = round(max(32.0, min(110.0, min($areaWidth, $areaHeight) * 1.15)), 1);
            $defaultTextX = $centerX;
            $defaultTextY = round($areaTop + ($areaHeight * 0.64), 2);
            $defaultTextSize = 0.85;

            $pkgKey = (string) ($productConfig['template_package'] ?? ($product->slug ?? ''));
            if ($pkgKey === 'seelen-anhaenger') {
                $centerX = 50.0; $centerY = 72.0; $defaultSymbolX = 50.0; $defaultSymbolY = 65.5; $defaultSymbolSize = 38.0; $defaultTextX = 50.0; $defaultTextY = 76.0; $defaultTextSize = 0.72;
            } elseif ($pkgKey === 'seelen-pfote-hundemarke') {
                $centerX = 41.0; $centerY = 50.0; $defaultSymbolX = 41.0; $defaultSymbolY = 44.5; $defaultSymbolSize = 34.0; $defaultTextX = 41.0; $defaultTextY = 54.5; $defaultTextSize = 0.70;
            } elseif ($pkgKey === 'seelen-talisman') {
                $centerX = 50.0; $centerY = 66.0; $defaultSymbolX = 50.0; $defaultSymbolY = 56.0; $defaultSymbolSize = 46.0; $defaultTextX = 50.0; $defaultTextY = 73.0; $defaultTextSize = 0.74;
            } elseif (in_array($pkgKey, ['goldenes-seelen-herz', 'echtgold-seelen-herz'], true)) {
                $centerX = 50.0; $centerY = 57.0; $defaultSymbolX = 50.0; $defaultSymbolY = 49.0; $defaultSymbolSize = 42.0; $defaultTextX = 50.0; $defaultTextY = 61.5; $defaultTextSize = 0.82;
            } elseif (in_array($pkgKey, ['id-armband-silber-herz', 'id-armband-gold-herz'], true)) {
                $centerX = 57.0; $centerY = 50.0; $defaultSymbolX = 57.0; $defaultSymbolY = 46.5; $defaultSymbolSize = 22.0; $defaultTextX = 57.0; $defaultTextY = 53.0; $defaultTextSize = 0.56;
            } elseif ($pkgKey === 'seelen-kristall') {
                $centerX = 50.0; $centerY = 45.6; $defaultSymbolX = 50.0; $defaultSymbolY = 32.0; $defaultSymbolSize = 80.0; $defaultTextX = 50.0; $defaultTextY = 55.5; $defaultTextSize = 1.20;
            } elseif ($pkgKey === 'weizenglas-personalisiert') {
                $centerX = 50.0; $centerY = 47.0; $defaultSymbolX = 50.0; $defaultSymbolY = 35.0; $defaultSymbolSize = 72.0; $defaultTextX = 50.0; $defaultTextY = 55.0; $defaultTextSize = 1.05;
            }

            $symbolX = isset($productConfig['beginner_symbol_x']) && $productConfig['beginner_symbol_x'] !== '' && is_numeric($productConfig['beginner_symbol_x']) ? round((float) $productConfig['beginner_symbol_x'], 2) : $defaultSymbolX;
            $symbolY = isset($productConfig['beginner_symbol_y']) && $productConfig['beginner_symbol_y'] !== '' && is_numeric($productConfig['beginner_symbol_y']) ? round((float) $productConfig['beginner_symbol_y'], 2) : $defaultSymbolY;
            $symbolSize = isset($productConfig['beginner_symbol_size']) && $productConfig['beginner_symbol_size'] !== '' && is_numeric($productConfig['beginner_symbol_size']) ? round((float) $productConfig['beginner_symbol_size'], 1) : $defaultSymbolSize;
            $textX = isset($productConfig['beginner_text_x']) && $productConfig['beginner_text_x'] !== '' && is_numeric($productConfig['beginner_text_x']) ? round((float) $productConfig['beginner_text_x'], 2) : $defaultTextX;
            $textY = isset($productConfig['beginner_text_y']) && $productConfig['beginner_text_y'] !== '' && is_numeric($productConfig['beginner_text_y']) ? round((float) $productConfig['beginner_text_y'], 2) : $defaultTextY;
            $textSize = isset($productConfig['beginner_text_size']) && $productConfig['beginner_text_size'] !== '' && is_numeric($productConfig['beginner_text_size']) ? round((float) $productConfig['beginner_text_size'], 2) : $defaultTextSize;

            $symbolVectorMap = [
                'herz' => '/storage/produkte/configurator/vectors/Liebe/herz.svg',
                'pfote' => '/storage/produkte/configurator/vectors/Tiere/pfotenabdruck.svg',
                'unendlichkeit' => '/storage/produkte/configurator/vectors/Liebe/unendlichkeit.svg',
                'stern' => '/storage/produkte/configurator/vectors/Symbole/stern.svg',
                'schutzengel' => '/storage/produkte/configurator/vectors/Symbole/schutzengel.svg',
                'engel' => '/storage/produkte/configurator/vectors/Symbole/schutzengel.svg',
                'lebensbaum' => '/storage/produkte/configurator/vectors/Natur/lebensbaum.svg',
                'baum' => '/storage/produkte/configurator/vectors/Natur/lebensbaum.svg',
                'kleeblatt' => '/storage/produkte/configurator/vectors/Symbole/kleeblatt.svg',
                'klee' => '/storage/produkte/configurator/vectors/Symbole/kleeblatt.svg',
                'krone' => '/storage/produkte/configurator/vectors/Symbole/krone.svg',
                'anker' => '/storage/produkte/configurator/vectors/Symbole/anker.svg',
            ];

            $chosenFont = 'Arial';
            $customLines = [];
            $matchedSymbolUrl = null;

            foreach ($rawCustomFields as $cfdKey => $cfd) {
                if (is_scalar($cfd)) {
                    $cfd = [
                        'id'    => (string) $cfdKey,
                        'type'  => str_contains(mb_strtolower((string) $cfdKey), 'font') ? 'font' : 'text',
                        'value' => (string) $cfd,
                    ];
                }
                if (!is_array($cfd)) continue;
                $cfdVal = trim(str_replace(["\r\n", "\r"], "\n", (string) ($cfd['value'] ?? '')));
                if ($cfdVal === '') continue;
                $cfdValLower = mb_strtolower($cfdVal);
                if (($cfd['type'] ?? 'text') === 'font') {
                    $chosenFont = $cfdVal;
                } elseif (!in_array($cfdValLower, ['kein symbol', 'keine', 'nein', '-'], true)) {
                    $foundVector = null;
                    foreach ($symbolVectorMap as $symKey => $symUrl) {
                        if (str_starts_with($cfdValLower, $symKey) || str_contains($cfdValLower, $symKey)) {
                            $foundVector = $symUrl;
                            break;
                        }
                    }
                    if ($foundVector !== null && $matchedSymbolUrl === null) {
                        $matchedSymbolUrl = $foundVector;
                    } else {
                        $customLines[] = $cfdVal;
                    }
                }
            }

            if ($matchedSymbolUrl !== null && empty($logos)) {
                $logos = [[
                    'url'      => $matchedSymbolUrl,
                    'x'        => $symbolX,
                    'y'        => $symbolY,
                    'size'     => $symbolSize,
                    'rotation' => 0,
                ]];
            }

            if (!empty($customLines) && !$hasNonEmptyTexts) {
                $maxCharsPerLine = max(12, min(28, (int) round($areaWidth * 0.56)));
                $wrappedLines = [];
                foreach (explode("\n", implode("\n", $customLines)) as $rawLine) {
                    $trimmedLine = trim($rawLine);
                    if ($trimmedLine === '') continue;
                    if (mb_strlen($trimmedLine) > $maxCharsPerLine && str_contains($trimmedLine, ' ')) {
                        foreach (explode("\n", wordwrap($trimmedLine, $maxCharsPerLine, "\n", true)) as $subLine) {
                            $subLine = trim($subLine);
                            if ($subLine !== '') $wrappedLines[] = $subLine;
                        }
                    } else {
                        $wrappedLines[] = $trimmedLine;
                    }
                }
                $finalBeginnerText = implode("\n", array_slice($wrappedLines, 0, 5));
                $lineCount = substr_count($finalBeginnerText, "\n") + 1;
                $effectiveTextSize = $lineCount === 2 ? round($textSize * 0.90, 2) : ($lineCount === 3 ? round($textSize * 0.80, 2) : ($lineCount >= 4 ? round($textSize * 0.72, 2) : $textSize));

                $texts = [[
                    'text'     => $finalBeginnerText,
                    'font'     => $chosenFont,
                    'align'    => 'center',
                    'x'        => $textX,
                    'y'        => $matchedSymbolUrl !== null ? $textY : $centerY,
                    'size'     => $effectiveTextSize,
                    'rotation' => 0,
                ]];
            }
        } elseif (!$hasNonEmptyTexts && $side !== 'back' && !empty($config['text']) && trim((string) $config['text']) !== '') {
            $texts = [[
                'text'     => trim((string) $config['text']),
                'font'     => (string) ($config['font'] ?? 'Montserrat'),
                'align'    => 'center',
                'x'        => $defaultAreaCenterX,
                'y'        => $defaultAreaCenterY,
                'size'     => 1.0,
                'rotation' => 0,
            ]];
        }

        $overlayType = $config['overlay_type'] ?? ($productConfig['overlay_type'] ?? 'plane');
        $widthMm = null;
        $heightMm = null;

        if (!empty($config['variant_name'])) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX×]\s*(\d+(?:[.,]\d+)?)(?!\s*[xX×\d])\s*(mm|cm)\b/iu', (string) $config['variant_name'], $matches)) {
                $unitFactor = strtolower($matches[3]) === 'cm' ? 10.0 : 1.0;
                $widthMm = (float) str_replace(',', '.', $matches[1]) * $unitFactor;
                $heightMm = (float) str_replace(',', '.', $matches[2]) * $unitFactor;
            } elseif (preg_match('/(\d{2,4})\s*[xX×]\s*(\d{2,4})(?!\s*[xX×\d])/u', (string) $config['variant_name'], $matches)) {
                $widthMm = (float) $matches[1];
                $heightMm = (float) $matches[2];
            }
        }

        if (empty($widthMm) || empty($heightMm)) {
            if ($overlayType === 'plane') {
                if (!empty($productConfig['xtool_plane_width'])) $widthMm = (float) $productConfig['xtool_plane_width'];
                if (!empty($productConfig['xtool_plane_height'])) $heightMm = (float) $productConfig['xtool_plane_height'];
            } elseif ($overlayType === 'cylinder') {
                if (!empty($productConfig['xtool_d_top'])) $widthMm = (float) $productConfig['xtool_d_top'];
                if (!empty($productConfig['xtool_height'])) $heightMm = (float) $productConfig['xtool_height'];
            }
        }

        $workingAreaCal = $this->resolveEngravingWorkingAreaCalibration(
            $product,
            $productConfig,
            $config,
            $overlayType === 'cylinder'
        );

        $workingAreaPolygonPct = $this->buildWorkingAreaPolygonPercent($productConfig);
        if (!empty($workingAreaCal['plateBoxPct'])) {
            [$workingAreaPolygonPct, $logos, $texts] = $this->mapShopConfigOntoPhysicalBlank(
                $workingAreaCal['plateBoxPct'],
                $workingAreaPolygonPct,
                $logos,
                $texts
            );
        }

        // Bündige 1:1 Projektion auf die echte Gravurfläche (workingArea = 0..100 % => 0,0 mm Lücke zum blauen Rahmen)
        [$workingAreaPolygonPct, $logos, $texts] = $this->mapShopConfigOntoPhysicalBlank(
            $workingAreaCal['areaBoxInBlankPct'],
            $workingAreaPolygonPct,
            $logos,
            $texts
        );

        $effectiveSideForMaterial = ($side === 'back') ? 'back' : 'front';
        $overrideMaterial = trim((string) (
            $config['laser_cockpit_parameters'][$effectiveSideForMaterial]['material']
            ?? $config['laser_parameter_overrides'][$effectiveSideForMaterial]['material']
            ?? $config['laser_cockpit_parameters']['front']['material']
            ?? $config['laser_parameter_overrides']['front']['material']
            ?? ''
        ));
        $effectiveProductConfig = $productConfig;
        if ($overrideMaterial !== '') {
            $effectiveProductConfig['xtool_material'] = $overrideMaterial;
            $effectiveProductConfig['xtool_material_override'] = $overrideMaterial;
        }

        $widthMm = (float) $workingAreaCal['engraveWidthMm'];
        $heightMm = (float) $workingAreaCal['engraveHeightMm'];
        $contourShape = (string) $workingAreaCal['contourShape'];
        $isDarkSurface = $this->isFrostedLightEngravingSurface($product, $effectiveProductConfig);

        // Exakt EINE bündige 1:1 Kontur der Gravurfläche in Millimetern (0..widthMm x 0..heightMm)
        $ghostContourDPath = $this->buildSingleOuterBlankContourDPath(
            $contourShape,
            $widthMm,
            $heightMm,
            $workingAreaPolygonPct,
            $productConfig
        );

        $rendered = $this->renderFullStageLaserPng(
            $widthMm,
            $heightMm,
            $workingAreaPolygonPct,
            $logos,
            $texts,
            $item,
            $isDarkSurface,
            $this->resolveLaserPhotoProfile($product, $effectiveProductConfig, $isDarkSurface)
        );

        $photoAdjustmentsHash = $this->computePhotoAdjustmentsCacheFingerprint(is_array($config) ? $config : []);

        return [
            'item'                   => $item,
            'product'                => $product,
            'side'                   => $side,
            'widthMm'                => round($widthMm, 3),
            'heightMm'               => round($heightMm, 3),
            'blankWidthMm'           => round((float) $workingAreaCal['blankWidthMm'], 3),
            'blankHeightMm'          => round((float) $workingAreaCal['blankHeightMm'], 3),
            'centerOffsetXMm'        => round((float) $workingAreaCal['centerOffsetXMm'], 3),
            'centerOffsetYMm'        => round((float) $workingAreaCal['centerOffsetYMm'], 3),
            'contourShape'           => $contourShape,
            'isDarkSurface'          => $isDarkSurface,
            'workingAreaPolygonPct'  => $workingAreaPolygonPct,
            'ghostContourDPath'      => $ghostContourDPath,
            'stageEngravePngBytes'   => $rendered['pngBytes'],
            'cockpitPreviewPngBytes' => $rendered['previewPngBytes'],
            'stagePixelWidth'        => $rendered['pixelWidth'],
            'stagePixelHeight'       => $rendered['pixelHeight'],
            'previewDataUrl'         => 'data:image/png;base64,' . base64_encode($rendered['previewPngBytes']),
            'photoLayerPngBytes'     => $rendered['photoLayerPngBytes'] ?? null,
            'vectorLayerPngBytes'    => $rendered['vectorLayerPngBytes'] ?? null,
            'photoLayerInfo'         => $rendered['photoLayerInfo'] ?? null,
            'textKnockoutPixels'     => (int) ($rendered['knockoutPixels'] ?? 0),
            'stagePxPerMm'           => (float) ($rendered['pxPerMm'] ?? 0.0),
            'photoAdjustmentsHash'   => $photoAdjustmentsHash,
        ];
    }

    /**
     * Berechnet einen deterministischen Cache-Fingerprint über alle Einträge in configuration['laser_photo_adjustments']
     * inkl. Dateigröße, Änderungszeit und Inhalts-Hash von edited_path auf Storage::disk('public'),
     * damit sich die Live-Cockpit-Vorschau (<sha1>.png) nach jedem Zahnrad-Speichern sofort aktualisiert.
     */
    public function computePhotoAdjustmentsCacheFingerprint(array $itemConfig = []): string
    {
        $adjs = $itemConfig['laser_photo_adjustments'] ?? null;
        if (!is_array($adjs) || empty($adjs)) {
            return '';
        }

        $disk = Storage::disk('public');
        $parts = [];
        ksort($adjs);
        foreach ($adjs as $fileKey => $adj) {
            if (!is_array($adj)) {
                continue;
            }
            $editedFileSig = 'none';
            if (!empty($adj['edited_path']) && is_string($adj['edited_path'])) {
                $rel = \App\Services\Laser\LaserPhotoPreprocessor::normalizePublicRelPath($adj['edited_path']);
                if ($rel !== '' && $disk->exists($rel)) {
                    $abs = $disk->path($rel);
                    clearstatcache(true, $abs);
                    $editedFileSig = $rel . ':' . (@filesize($abs) ?: 0) . ':' . (@filemtime($abs) ?: 0) . ':' . (@sha1_file($abs) ?: '');
                }
            }
            $parts[] = (string) $fileKey . '=' . json_encode($adj) . '|' . $editedFileSig;
        }

        return empty($parts) ? '' : sha1(implode('||', $parts));
    }

    /**
     * Foto-Substrat-Profil für die physikalisch kalibrierte Foto-Pipeline (LaserPhotoPreprocessor):
     * Produktwert 'xtool_photo_substrate_polarity' (white_on_dark / dark_on_light) mit Fallback auf die Oberflächen-Erkennung.
     *
     * @return array{polarity: string, is_dark_surface: bool, dpi: int}
     */
    public function resolveLaserPhotoProfile($product = null, array $productConfig = [], ?bool $isDarkSurface = null): array
    {
        $isDark = $isDarkSurface ?? $this->isFrostedLightEngravingSurface($product, $productConfig);
        $rawPolarity = trim((string) ($productConfig['xtool_photo_substrate_polarity'] ?? ''));
        $overrideMat = mb_strtolower(trim((string) ($productConfig['xtool_material_override'] ?? '')));
        // Materialwechsel im Cockpit (z. B. Edelstahl -> Schiefer / Glas) hat Vorrang vor dem Produktstandard
        if ($overrideMat !== '') {
            $rawPolarity = preg_match('/(glas|kristall|k9)/u', $overrideMat) ? 'frost_on_glass' : '';
        } elseif ($rawPolarity === '' || $rawPolarity === 'white_on_dark') {
            $matType = strtolower(trim((string) ($productConfig['material_type'] ?? '')));
            $slug = strtolower(trim((string) ($product->slug ?? '')));
            $pkg = strtolower(trim((string) ($productConfig['template_package'] ?? '')));
            if (
                in_array($matType, ['glass', 'crystal'], true)
                || str_starts_with($slug, 'seelen-kristall')
                || str_starts_with($pkg, 'seelen-kristall')
                || str_starts_with($slug, 'weizenglas')
                || str_starts_with($pkg, 'weizenglas')
            ) {
                $rawPolarity = 'frost_on_glass';
            }
        }

        $rawDpi = $productConfig['xtool_bitmap_dpi'] ?? ($productConfig['xtool_dpi'] ?? 600);
        $resolvedDpi = is_numeric($rawDpi) && (int) $rawDpi >= 50 ? (int) $rawDpi : 600;

        return [
            'polarity'        => \App\Services\Laser\LaserPhotoPreprocessor::normalizePolarity($rawPolarity, $isDark),
            'is_dark_surface' => $isDark,
            'dpi'             => $resolvedDpi,
        ];
    }

    /**
     * Prüft, ob die Lasergravur auf dem Medium visuell hell/milchig-weiß erscheint (true: Glas, K9-Kristall,
     * Schiefer, schwarz eloxiertes Aluminium) oder als dunkle Anlass-/Einbrandgravur auf hellem Grund (false:
     * vergoldeter Edelstahl, Silber, Gold-Vermeil, Messing, Holz).
     */
    public function isFrostedLightEngravingSurface($product = null, array $productConfig = []): bool
    {
        $overrideMat = mb_strtolower(trim((string) ($productConfig['xtool_material_override'] ?? '')));
        if ($overrideMat !== '') {
            if (preg_match('/(glas|kristall|k9|schiefer|aluminium)/u', $overrideMat)) {
                return true;
            }
            if (preg_match('/(edelstahl|silber|gold|messing|holz|leder)/u', $overrideMat)) {
                return false;
            }
        }

        $explicitStyle = strtolower(trim((string) ($productConfig['engraving_style'] ?? $productConfig['engraving_Style'] ?? '')));
        if ($explicitStyle === 'dark') {
            return false;
        }
        if (in_array($explicitStyle, ['light', 'frosted', 'white'], true)) {
            return true;
        }

        $materialType = strtolower(trim((string) ($productConfig['material_type'] ?? '')));
        if (in_array($materialType, ['glass', 'crystal', 'slate', 'black_aluminum'], true)) {
            return true;
        }
        if (in_array($materialType, ['silver', 'gold', 'wood', 'brass', 'stainless_steel'], true)) {
            return false;
        }

        $slug = strtolower(trim((string) ($product->slug ?? '')));
        $pkg = strtolower(trim((string) ($productConfig['template_package'] ?? '')));
        foreach ([$pkg, $slug] as $key) {
            if ($key === '') {
                continue;
            }
            if (
                str_starts_with($key, 'seelen-talisman')
                || str_starts_with($key, 'seelen-pfote')
                || str_starts_with($key, 'goldenes-seelen-herz')
                || str_starts_with($key, 'echtgold-seelen-herz')
                || str_starts_with($key, 'id-armband-')
            ) {
                return false;
            }
            if (
                str_starts_with($key, 'seelen-kristall')
                || str_starts_with($key, 'der-seelen-kristall')
                || str_starts_with($key, 'weizenglas')
                || str_starts_with($key, 'seelen-anhaenger')
                || str_starts_with($key, 'geschenkgutschein')
            ) {
                return true;
            }
        }

        $xtoolMat = mb_strtolower(trim((string) ($productConfig['xtool_material'] ?? '') . ' ' . (string) ($product->name ?? '')));
        if (preg_match('/(glas|kristall|k9|schiefer|aluminium)/u', $xtoolMat)) {
            return true;
        }
        if (preg_match('/(edelstahl|silber|gold|messing|holz)/u', $xtoolMat)) {
            return false;
        }

        $analysis = app(\App\Services\ConfiguratorService::class)->analyzeOverlayImage(
            $product->preview_image_path ?? null,
            $productConfig
        );

        return (bool) ($analysis['is_dark_surface'] ?? false);
    }

    /**
     * Ermittelt neben den physischen Rohlings-Außenmaßen (blankWidthMm x blankHeightMm) die exakte innere
     * Gravurfläche (engraveWidthMm x engraveHeightMm) inklusive Versatz (centerOffsetXMm / centerOffsetYMm)
     * relativ zur Rohlingsmitte, damit der blaue Cockpit-Rahmen zu 100 % bündig an der Gravurfläche anliegt.
     *
     * @return array{
     *   blankWidthMm: float,
     *   blankHeightMm: float,
     *   engraveWidthMm: float,
     *   engraveHeightMm: float,
     *   centerOffsetXMm: float,
     *   centerOffsetYMm: float,
     *   areaBoxInBlankPct: array{left: float, top: float, width: float, height: float},
     *   plateBoxPct: ?array{left: float, top: float, width: float, height: float},
     *   contourShape: string,
     *   blankContourShape: string
     * }
     */
    public function resolveEngravingWorkingAreaCalibration(
        $product = null,
        array $productConfig = [],
        array $itemConfig = [],
        bool $unrollCylinder = false
    ): array {
        $calibration = $this->resolvePhysicalBlankCalibration($product, $productConfig, $itemConfig, $unrollCylinder);
        $blankWidthMm = (float) $calibration['widthMm'];
        $blankHeightMm = (float) $calibration['heightMm'];
        $plateBoxPct = $calibration['plateBoxPct'] ?? null;
        $blankContourShape = (string) ($calibration['contourShape'] ?? 'rect');

        $polyInBlank = $this->buildWorkingAreaPolygonPercent($productConfig);
        if (!empty($plateBoxPct)) {
            [$polyInBlank] = $this->mapShopConfigOntoPhysicalBlank($plateBoxPct, $polyInBlank, [], []);
        }

        if (!empty($polyInBlank)) {
            $xs = array_column($polyInBlank, 'x');
            $ys = array_column($polyInBlank, 'y');
            $minXPct = max(0.0, min($xs));
            $maxXPct = min(100.0, max($xs));
            $minYPct = max(0.0, min($ys));
            $maxYPct = min(100.0, max($ys));
        } else {
            $minXPct = 0.0;
            $maxXPct = 100.0;
            $minYPct = 0.0;
            $maxYPct = 100.0;
        }

        $spanXPct = max(1.0, $maxXPct - $minXPct);
        $spanYPct = max(1.0, $maxYPct - $minYPct);

        $engraveWidthMm = max(1.0, ($spanXPct / 100.0) * $blankWidthMm);
        $engraveHeightMm = max(1.0, ($spanYPct / 100.0) * $blankHeightMm);

        $centerXPct = ($minXPct + $maxXPct) * 0.5;
        $centerYPct = ($minYPct + $maxYPct) * 0.5;
        $centerOffsetXMm = (($centerXPct - 50.0) / 100.0) * $blankWidthMm;
        $centerOffsetYMm = (($centerYPct - 50.0) / 100.0) * $blankHeightMm;

        $areaShape = (string) ($productConfig['area_shape'] ?? 'rect');
        if ($areaShape === 'circle' || $blankContourShape === 'circle') {
            $contourShape = 'circle';
        } elseif ($areaShape === 'custom') {
            $contourShape = 'custom_polygon';
        } elseif (in_array($blankContourShape, ['rounded_card', 'rounded_bar'], true) && ($spanXPct >= 98.0 && $spanYPct >= 98.0 || !empty($itemConfig['is_laser_studio_project']))) {
            $contourShape = $blankContourShape;
        } else {
            $contourShape = 'rect';
        }

        $calResult = [
            'blankWidthMm'      => $blankWidthMm,
            'blankHeightMm'     => $blankHeightMm,
            'engraveWidthMm'    => $engraveWidthMm,
            'engraveHeightMm'   => $engraveHeightMm,
            'centerOffsetXMm'   => $centerOffsetXMm,
            'centerOffsetYMm'   => $centerOffsetYMm,
            'areaBoxInBlankPct' => [
                'left'   => $minXPct,
                'top'    => $minYPct,
                'width'  => $spanXPct,
                'height' => $spanYPct,
            ],
            'plateBoxPct'       => $plateBoxPct,
            'contourShape'      => $contourShape,
            'blankContourShape' => $blankContourShape,
        ];

        $adjFingerprint = $this->computePhotoAdjustmentsCacheFingerprint($itemConfig);
        if ($adjFingerprint !== '') {
            $calResult['photoAdjustmentsHash'] = $adjFingerprint;
        }

        return $calResult;
    }

    /**
     * Ermittelt die millimetergenaue 1:1 Rohlings-Kalibrierung (physische Außenmaße in mm,
     * Sub-Box des Rohlings im 2D-Shop-Overlay in % sowie den exakten Außenkontur-Typ).
     *
     * @return array{widthMm: float, heightMm: float, plateBoxPct: ?array{left: float, top: float, width: float, height: float}, contourShape: string}
     */
    public function resolvePhysicalBlankCalibration(
        $product = null,
        array $productConfig = [],
        array $itemConfig = [],
        bool $unrollCylinder = false
    ): array {
        if (!empty($itemConfig['is_laser_studio_project'])
            && !$product
            && !empty($itemConfig['studio_width_mm'])
            && !empty($itemConfig['studio_height_mm'])) {
            $studioShape = (string) ($itemConfig['studio_contour_shape'] ?? 'rect');
            if (!in_array($studioShape, ['rect', 'rounded_card', 'circle'], true)) {
                $studioShape = 'rect';
            }
            $sW = round(max(3.0, min(200.0, (float) $itemConfig['studio_width_mm'])), 2);
            $sH = round(max(3.0, min(200.0, (float) $itemConfig['studio_height_mm'])), 2);
            $areaW = (float) ($productConfig['area_width'] ?? 100.0);
            $areaH = (float) ($productConfig['area_height'] ?? 100.0);
            $isSubBoxOnBed = ($sW < 198.0 || $sH < 198.0) && ($areaW < 99.5 || $areaH < 99.5);

            return [
                'widthMm' => $isSubBoxOnBed ? 200.0 : $sW,
                'heightMm' => $isSubBoxOnBed ? 200.0 : $sH,
                'plateBoxPct' => null,
                'contourShape' => $studioShape,
            ];
        }

        $overlayType = (string) ($productConfig['overlay_type'] ?? 'plane');
        $slug = strtolower(trim((string) ($product->slug ?? '')));
        $templatePkg = strtolower(trim((string) ($productConfig['template_package'] ?? '')));

        $catalogProfiles = [
            'seelen-kristall' => [
                'widthMm'      => 161.5,
                'heightMm'     => 161.5,
                // Konzentrisch um die 2D-Scheibenmitte (50.00 %, 45.60 %): bildet den 68.0 %-Konfigurator-Arbeitskreis
                // maßstabsgetreu auf 82.93 % (= Ø 133.93 mm, 13.79 mm Randabstand je Seite) innerhalb des Ø 161.5 mm
                // blauen Außenrahmens ab, also mit ~2.8 mm Sicherheitsabstand vor dem ~11.0 mm breiten geschliffenen
                // Facettenkranz (Ø ~139.5 mm Innenfacette) und oberhalb der unteren Kristallsockel-Klebekante.
                'plateBoxPct'  => ['left' => 9.00, 'top' => 4.60, 'width' => 82.00, 'height' => 82.00],
                'contourShape' => 'circle',
            ],
            'seelen-anhaenger' => [
                'widthMm'      => 45.0,
                'heightMm'     => 40.0,
                'plateBoxPct'  => ['left' => 25.875, 'top' => 52.25, 'width' => 48.25, 'height' => 44.25],
                'contourShape' => 'heart_anhaenger',
            ],
            'seelen-talisman' => [
                'widthMm'      => 28.0,
                'heightMm'     => 50.0,
                'plateBoxPct'  => ['left' => 33.875, 'top' => 40.25, 'width' => 32.25, 'height' => 54.75],
                'contourShape' => 'dogtag_talisman',
            ],
            'seelen-pfote-hundemarke' => [
                'widthMm'      => 40.0,
                'heightMm'     => 22.0,
                'plateBoxPct'  => ['left' => 10.0, 'top' => 29.75, 'width' => 80.0, 'height' => 40.5],
                'contourShape' => 'bone_pfote',
            ],
            'goldenes-seelen-herz' => [
                'widthMm'      => 25.0,
                'heightMm'     => 22.0,
                'plateBoxPct'  => ['left' => 17.5, 'top' => 35.125, 'width' => 65.0, 'height' => 59.125],
                'contourShape' => 'heart_goldenes',
            ],
            'echtgold-seelen-herz' => [
                'widthMm'      => 13.0,
                'heightMm'     => 14.0,
                'plateBoxPct'  => ['left' => 17.5, 'top' => 35.125, 'width' => 65.0, 'height' => 59.125],
                'contourShape' => 'heart_goldenes',
            ],
            'id-armband-silber-herz' => [
                'widthMm'      => 24.0,
                'heightMm'     => 5.5,
                'plateBoxPct'  => ['left' => 17.0, 'top' => 42.5, 'width' => 66.0, 'height' => 15.0],
                'contourShape' => 'rounded_bar',
            ],
            'id-armband-gold-herz' => [
                'widthMm'      => 24.0,
                'heightMm'     => 5.5,
                'plateBoxPct'  => ['left' => 17.0, 'top' => 42.5, 'width' => 66.0, 'height' => 15.0],
                'contourShape' => 'rounded_bar',
            ],
            'geschenkgutschein' => [
                'widthMm'      => 86.0,
                'heightMm'     => 54.0,
                'plateBoxPct'  => null,
                'contourShape' => 'rounded_card',
            ],
        ];

        $matchedProfile = null;
        if ($templatePkg !== '' && isset($catalogProfiles[$templatePkg])) {
            $matchedProfile = $catalogProfiles[$templatePkg];
        } elseif ($slug !== '' && isset($catalogProfiles[$slug])) {
            $matchedProfile = $catalogProfiles[$slug];
        } elseif (str_starts_with($slug, 'der-seelen-kristall') || str_starts_with($slug, 'seelen-kristall')) {
            $matchedProfile = $catalogProfiles['seelen-kristall'];
        } elseif (!empty($itemConfig['is_gift_voucher'])) {
            $matchedProfile = $catalogProfiles['geschenkgutschein'];
        }

        $widthMm = null;
        $heightMm = null;

        if (!empty($itemConfig['variant_name'])) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX×]\s*(\d+(?:[.,]\d+)?)(?!\s*[xX×\d])\s*(mm|cm)\b/iu', (string) $itemConfig['variant_name'], $matches)) {
                $unitFactor = strtolower($matches[3]) === 'cm' ? 10.0 : 1.0;
                $widthMm = (float) str_replace(',', '.', $matches[1]) * $unitFactor;
                $heightMm = (float) str_replace(',', '.', $matches[2]) * $unitFactor;
            } elseif (preg_match('/(\d{2,4})\s*[xX×]\s*(\d{2,4})(?!\s*[xX×\d])/u', (string) $itemConfig['variant_name'], $matches)) {
                $widthMm = (float) $matches[1];
                $heightMm = (float) $matches[2];
            }
        }

        if (empty($widthMm) || empty($heightMm)) {
            if ($overlayType === 'plane') {
                if (!empty($productConfig['xtool_plane_width'])) {
                    $widthMm = (float) $productConfig['xtool_plane_width'];
                }
                if (!empty($productConfig['xtool_plane_height'])) {
                    $heightMm = (float) $productConfig['xtool_plane_height'];
                }
            } elseif ($overlayType === 'cylinder') {
                if (!empty($productConfig['xtool_d_top'])) {
                    $widthMm = (float) $productConfig['xtool_d_top'];
                }
                if (!empty($productConfig['xtool_height'])) {
                    $heightMm = (float) $productConfig['xtool_height'];
                }
            }
        }

        if ((empty($widthMm) || empty($heightMm)) && $matchedProfile !== null) {
            $widthMm = $widthMm ?: (float) $matchedProfile['widthMm'];
            $heightMm = $heightMm ?: (float) $matchedProfile['heightMm'];
        }

        // Kalibrierte Scheibengeometrie für "Der Seelen Kristall":
        // Gesamthöhe inkl. Standfuß ist 180 mm, der runde K9-Kristallkorpus selbst hat exakt Ø 161,5 mm.
        if (
            $matchedProfile !== null
            && ($matchedProfile['contourShape'] ?? null) === 'circle'
            && abs((float) ($matchedProfile['widthMm'] ?? 0) - 161.5) < 0.05
            && abs((float) $widthMm - 180.0) < 0.5
            && abs((float) $heightMm - 180.0) < 0.5
        ) {
            $widthMm = 161.5;
            $heightMm = 161.5;
        }

        if (!empty($itemConfig['is_gift_voucher']) || $slug === 'geschenkgutschein') {
            $widthMm = 86.0;
            $heightMm = 54.0;
        }

        $widthMm = $widthMm ?: (float) ($product->width ?? 100.0);
        $heightMm = $heightMm ?: (float) ($product->height ?? 100.0);

        $plateBoxPct = null;
        if (!empty($productConfig['xtool_plate_box_pct']) && is_array($productConfig['xtool_plate_box_pct'])) {
            $pb = $productConfig['xtool_plate_box_pct'];
            if (!empty($pb['width']) && !empty($pb['height'])) {
                $pbLeft = (float) ($pb['left'] ?? 0.0);
                $pbTop = (float) ($pb['top'] ?? 0.0);
                $pbWidth = (float) $pb['width'];
                $pbHeight = (float) $pb['height'];

                // Veraltete 78.24%-, 74.20%- oder 71.54%-Vorlagen beim Seelen-Kristall automatisch auf 82.00%
                // (Ø 133.93 mm Arbeitskreis konzentrisch innerhalb des Ø 161.5 mm Kristall-Außenrahmens) heben
                $isLegacyCrystalBox = $matchedProfile !== null
                    && ($matchedProfile['contourShape'] ?? null) === 'circle'
                    && abs((float) ($matchedProfile['widthMm'] ?? 0.0) - 161.5) < 0.05
                    && (
                        (abs($pbWidth - 78.24) < 0.30 && abs($pbLeft - 10.88) < 0.30)
                        || (abs($pbWidth - 74.20) < 0.30 && abs($pbLeft - 12.90) < 0.30)
                        || (abs($pbWidth - 71.54) < 0.30 && abs($pbLeft - 14.23) < 0.30)
                    );

                $plateBoxPct = $isLegacyCrystalBox
                    ? $matchedProfile['plateBoxPct']
                    : [
                        'left'   => $pbLeft,
                        'top'    => $pbTop,
                        'width'  => $pbWidth,
                        'height' => $pbHeight,
                    ];
            }
        } elseif ($matchedProfile !== null && !empty($matchedProfile['plateBoxPct'])) {
            $plateBoxPct = $matchedProfile['plateBoxPct'];
        } elseif ($overlayType === 'plane' && $matchedProfile === null) {
            $plateBoxPct = $this->detectOverlayAlphaBoundingBoxPct($product, $productConfig, (float) $widthMm, (float) $heightMm);
        }

        if ($overlayType === 'cylinder') {
            if ($unrollCylinder) {
                $widthMm = $widthMm * M_PI;
            }
            $plateBoxPct = null;
        } elseif ($overlayType === 'plane' && $plateBoxPct === null && ($productConfig['area_shape'] ?? '') === 'circle' && abs($widthMm - $heightMm) > 0.01) {
            $stageSpanMm = min($widthMm, $heightMm);
            $widthMm = $stageSpanMm;
            $heightMm = $stageSpanMm;
        }

        $contourShape = (string) ($productConfig['xtool_contour_shape'] ?? ($matchedProfile['contourShape'] ?? 'auto'));
        if ($contourShape === 'auto') {
            $areaShape = (string) ($productConfig['area_shape'] ?? 'rect');
            if ($areaShape === 'circle') {
                $contourShape = 'circle';
            } elseif ($areaShape === 'custom') {
                $contourShape = 'custom_polygon';
            } else {
                $contourShape = 'rect';
            }
        }

        return [
            'widthMm'      => (float) $widthMm,
            'heightMm'     => (float) $heightMm,
            'plateBoxPct'  => $plateBoxPct,
            'contourShape' => $contourShape,
        ];
    }

    /**
     * Erkennt für generische flache Produkte ohne festes Katalogprofil automatisch die sichtbare
     * Rohlings-Sub-Box (in %) innerhalb eines freigestellten transparenten Konfigurator-Overlays (overlay.png).
     *
     * @return ?array{left: float, top: float, width: float, height: float}
     */
    protected function detectOverlayAlphaBoundingBoxPct(
        $product,
        array $productConfig,
        float $widthMm,
        float $heightMm
    ): ?array {
        $previewPath = trim((string) ($product->preview_image_path ?? ''));
        if ($previewPath === '' || !str_ends_with(strtolower($previewPath), '.png')) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if (!$disk->exists($previewPath)) {
                return null;
            }
            $fullPath = $disk->path($previewPath);
        } catch (\Throwable) {
            return null;
        }

        if (!is_file($fullPath)) {
            return null;
        }

        static $alphaBoxCache = [];
        $cacheKey = $fullPath . '|' . (@filemtime($fullPath) ?: 0);

        if (!array_key_exists($cacheKey, $alphaBoxCache)) {
            $alphaBoxCache[$cacheKey] = null;
            $rawBytes = @file_get_contents($fullPath);
            $gd = $rawBytes ? @imagecreatefromstring($rawBytes) : false;
            if ($gd !== false) {
                $w = imagesx($gd);
                $h = imagesy($gd);
                if ($w >= 16 && $h >= 16) {
                    $c0 = (imagecolorat($gd, 0, 0) >> 24) & 0x7F;
                    $c1 = (imagecolorat($gd, $w - 1, 0) >> 24) & 0x7F;
                    $c2 = (imagecolorat($gd, 0, $h - 1) >> 24) & 0x7F;
                    $c3 = (imagecolorat($gd, $w - 1, $h - 1) >> 24) & 0x7F;

                    if ($c0 >= 120 && $c1 >= 120 && $c2 >= 120 && $c3 >= 120) {
                        $minX = $w;
                        $maxX = -1;
                        $minY = $h;
                        $maxY = -1;
                        $step = ($w > 512 || $h > 512) ? 2 : 1;

                        for ($y = 0; $y < $h; $y += $step) {
                            for ($x = 0; $x < $w; $x += $step) {
                                $a = (imagecolorat($gd, $x, $y) >> 24) & 0x7F;
                                if ($a < 115) {
                                    if ($x < $minX) {
                                        $minX = $x;
                                    }
                                    if ($x > $maxX) {
                                        $maxX = $x;
                                    }
                                    if ($y < $minY) {
                                        $minY = $y;
                                    }
                                    if ($y > $maxY) {
                                        $maxY = $y;
                                    }
                                }
                            }
                        }

                        if ($maxX >= $minX && $maxY >= $minY) {
                            $alphaBoxCache[$cacheKey] = [
                                'left'   => round(($minX / $w) * 100.0, 3),
                                'top'    => round(($minY / $h) * 100.0, 3),
                                'width'  => round((($maxX - $minX + 1) / $w) * 100.0, 3),
                                'height' => round((($maxY - $minY + 1) / $h) * 100.0, 3),
                            ];
                        }
                    }
                }
                imagedestroy($gd);
            }
        }

        $bbox = $alphaBoxCache[$cacheKey];
        if ($bbox === null) {
            return null;
        }

        if (($bbox['width'] >= 96.5 && $bbox['height'] >= 96.5) || $bbox['width'] < 10.0 || $bbox['height'] < 10.0) {
            return null;
        }

        if ($widthMm > 0.1 && $heightMm > 0.1) {
            $physRatio = $widthMm / $heightMm;
            $boxRatio = $bbox['width'] / max(0.1, $bbox['height']);
            if (abs(log($boxRatio / $physRatio)) > 0.25) {
                return null;
            }
        }

        $poly = $this->buildWorkingAreaPolygonPercent($productConfig);
        if (!empty($poly)) {
            $xs = array_column($poly, 'x');
            $ys = array_column($poly, 'y');
            if (
                min($xs) < ($bbox['left'] - 2.5)
                || max($xs) > ($bbox['left'] + $bbox['width'] + 2.5)
                || min($ys) < ($bbox['top'] - 2.5)
                || max($ys) > ($bbox['top'] + $bbox['height'] + 2.5)
            ) {
                return null;
            }
        }

        return $bbox;
    }

    /**
     * Transformiert Koordinaten & Größen von der quadratischen 2D-Shop-Stage (0..100 %)
     * maßstabsgetreu auf den physischen Rohling (0..100 % entspricht 0..widthMm x 0..heightMm).
     *
     * @param array{left: float, top: float, width: float, height: float} $plateBoxPct
     * @return array{0: array, 1: array, 2: array}
     */
    public function mapShopConfigOntoPhysicalBlank(
        array $plateBoxPct,
        array $workingAreaPolygonPct,
        array $logos,
        array $texts
    ): array {
        $boxLeft = (float) ($plateBoxPct['left'] ?? 0.0);
        $boxTop = (float) ($plateBoxPct['top'] ?? 0.0);
        $boxWidth = max(1.0, (float) ($plateBoxPct['width'] ?? 100.0));
        $boxHeight = max(1.0, (float) ($plateBoxPct['height'] ?? 100.0));
        $scaleFromShopWidth = 100.0 / $boxWidth;

        $mappedPolygon = array_map(static function (array $pt) use ($boxLeft, $boxTop, $boxWidth, $boxHeight): array {
            return [
                'x' => max(0.0, min(100.0, ((((float) ($pt['x'] ?? 50.0)) - $boxLeft) / $boxWidth) * 100.0)),
                'y' => max(0.0, min(100.0, ((((float) ($pt['y'] ?? 50.0)) - $boxTop) / $boxHeight) * 100.0)),
            ];
        }, $workingAreaPolygonPct);

        $mappedLogos = [];
        foreach ($logos as $logo) {
            if (!is_array($logo)) {
                continue;
            }
            $mappedLogo = $logo;
            $mappedLogo['x'] = ((((float) ($logo['x'] ?? 50.0)) - $boxLeft) / $boxWidth) * 100.0;
            $mappedLogo['y'] = ((((float) ($logo['y'] ?? 50.0)) - $boxTop) / $boxHeight) * 100.0;
            $mappedLogo['size'] = ((float) ($logo['size'] ?? 100.0)) * $scaleFromShopWidth;
            $mappedLogos[] = $mappedLogo;
        }

        $mappedTexts = [];
        foreach ($texts as $textItem) {
            if (!is_array($textItem)) {
                continue;
            }
            $mappedText = $textItem;
            $mappedText['x'] = ((((float) ($textItem['x'] ?? 50.0)) - $boxLeft) / $boxWidth) * 100.0;
            $mappedText['y'] = ((((float) ($textItem['y'] ?? 50.0)) - $boxTop) / $boxHeight) * 100.0;
            $mappedText['size'] = ((float) ($textItem['size'] ?? 1.0)) * $scaleFromShopWidth;
            $mappedTexts[] = $mappedText;
        }

        return [$mappedPolygon, $mappedLogos, $mappedTexts];
    }

    /**
     * Erzeugt EXAKT EINEN geschlossenen 1:1 SVG-Pfad (0..widthMm x 0..heightMm) für die physische Außenkante des Rohlings.
     */
    public function buildSingleOuterBlankContourDPath(
        string $contourShape,
        float $widthMm,
        float $heightMm,
        array $workingAreaPolygonPct = [],
        array $productConfig = []
    ): string {
        $ptsMm = [];

        $normalizePixelPointsToMm = static function (array $rawPts) use ($widthMm, $heightMm): array {
            if (empty($rawPts)) {
                return [];
            }
            $xs = array_column($rawPts, 0);
            $ys = array_column($rawPts, 1);
            $minX = min($xs);
            $maxX = max($xs);
            $minY = min($ys);
            $maxY = max($ys);
            $spanX = max(0.001, $maxX - $minX);
            $spanY = max(0.001, $maxY - $minY);

            return array_map(static fn (array $p): array => [
                (($p[0] - $minX) / $spanX) * $widthMm,
                (($p[1] - $minY) / $spanY) * $heightMm,
            ], $rawPts);
        };

        $sampleCubic = static function (array &$target, array $p0, array $p1, array $p2, array $p3, int $steps): void {
            for ($i = 1; $i <= $steps; $i++) {
                $t = $i / $steps;
                $mt = 1.0 - $t;
                $target[] = [
                    $mt * $mt * $mt * $p0[0] + 3.0 * $mt * $mt * $t * $p1[0] + 3.0 * $mt * $t * $t * $p2[0] + $t * $t * $t * $p3[0],
                    $mt * $mt * $mt * $p0[1] + 3.0 * $mt * $mt * $t * $p1[1] + 3.0 * $mt * $t * $t * $p2[1] + $t * $t * $t * $p3[1],
                ];
            }
        };

        if ($contourShape === 'circle') {
            $cx = $widthMm * 0.5;
            $cy = $heightMm * 0.5;
            $rx = $widthMm * 0.5;
            $ry = $heightMm * 0.5;
            $steps = 72;
            for ($i = 0; $i < $steps; $i++) {
                $theta = ($i / $steps) * 2.0 * M_PI;
                $ptsMm[] = [$cx + $rx * cos($theta), $cy + $ry * sin($theta)];
            }
        } elseif ($contourShape === 'heart_anhaenger') {
            // Auf 0,061 mm RMS vermessene symmetrische 3-Segment-Kubik-Bezier-Kurve des Seelenanhänger-Herzens
            // im 800x800 Shop-Stage-%-Raum (area_top: 54.05, area_left: 28.07, area_width: 43.86, area_height: 40.38):
            // 72 Punkte ohne Duplikate an der oberen V-Kerbe (Index 0) und unteren Spitze (Index 36).
            $innerPct = [[50.00, 59.47]];
            // Rechte Hälfte (50 + Delta-x): V-Kerbe -> Lobe-Scheitel -> Äußere Wange -> Untere Spitze
            $sampleCubic($innerPct, [50.00, 59.47], [51.82, 58.81], [54.29, 54.05], [60.40, 54.05], 12);
            $sampleCubic($innerPct, [60.40, 54.05], [66.78, 54.05], [71.93, 58.97], [71.93, 65.78], 12);
            $sampleCubic($innerPct, [71.93, 65.78], [71.93, 75.40], [61.58, 86.69], [50.00, 94.43], 12);
            // Gespiegelte linke Hälfte (50 - Delta-x): Untere Spitze -> Äußere Wange -> Lobe-Scheitel -> V-Kerbe
            $sampleCubic($innerPct, [50.00, 94.43], [38.42, 86.69], [28.07, 75.40], [28.07, 65.78], 12);
            $sampleCubic($innerPct, [28.07, 65.78], [28.07, 58.97], [33.22, 54.05], [39.60, 54.05], 12);
            $sampleCubic($innerPct, [39.60, 54.05], [45.71, 54.05], [48.18, 58.81], [50.00, 59.47], 12);
            array_pop($innerPct);

            $ptsMm = $normalizePixelPointsToMm($innerPct);
        } elseif ($contourShape === 'heart_goldenes') {
            $rawPts = [[400.0, 311.0]];
            $sampleCubic($rawPts, [400.0, 311.0], [420.0, 290.0], [456.0, 281.0], [496.0, 281.0], 14);
            $sampleCubic($rawPts, [496.0, 281.0], [594.0, 281.0], [660.0, 338.0], [660.0, 436.0], 20);
            $sampleCubic($rawPts, [660.0, 436.0], [660.0, 512.0], [611.0, 583.0], [553.0, 645.0], 18);
            $sampleCubic($rawPts, [553.0, 645.0], [510.0, 687.0], [485.0, 706.0], [464.0, 720.0], 12);
            $sampleCubic($rawPts, [464.0, 720.0], [442.0, 734.5], [420.0, 754.0], [400.0, 754.0], 12);
            $sampleCubic($rawPts, [400.0, 754.0], [380.0, 754.0], [358.0, 734.5], [336.0, 720.0], 12);
            $sampleCubic($rawPts, [336.0, 720.0], [315.0, 706.0], [290.0, 687.0], [247.0, 645.0], 12);
            $sampleCubic($rawPts, [247.0, 645.0], [189.0, 583.0], [140.0, 512.0], [140.0, 436.0], 18);
            $sampleCubic($rawPts, [140.0, 436.0], [140.0, 338.0], [206.0, 281.0], [304.0, 281.0], 20);
            $sampleCubic($rawPts, [304.0, 281.0], [344.0, 281.0], [380.0, 290.0], [400.0, 311.0], 14);
            array_pop($rawPts);
            $ptsMm = $normalizePixelPointsToMm($rawPts);
        } elseif ($contourShape === 'dogtag_talisman') {
            $rawPts = [];
            $rArc = 176.0;
            $cyTop = 498.0;
            $aTopStart = atan2(378.0 - $cyTop, 271.0 - 400.0);
            $aTopEnd = atan2(378.0 - $cyTop, 529.0 - 400.0);
            for ($i = 0; $i <= 24; $i++) {
                $a = $aTopStart + ($aTopEnd - $aTopStart) * ($i / 24);
                $rawPts[] = [400.0 + $rArc * cos($a), $cyTop + $rArc * sin($a)];
            }
            $cyBot = 584.0;
            $aBotStart = atan2(704.0 - $cyBot, 529.0 - 400.0);
            $aBotEnd = atan2(704.0 - $cyBot, 271.0 - 400.0);
            for ($i = 0; $i <= 24; $i++) {
                $a = $aBotStart + ($aBotEnd - $aBotStart) * ($i / 24);
                $rawPts[] = [400.0 + $rArc * cos($a), $cyBot + $rArc * sin($a)];
            }
            $ptsMm = $normalizePixelPointsToMm($rawPts);
        } elseif ($contourShape === 'bone_pfote') {
            $rawPts = [[342.0, 282.0]];
            $sampleCubic($rawPts, [342.0, 282.0], [358.0, 282.0], [364.0, 238.0], [400.0, 238.0], 10);
            $sampleCubic($rawPts, [400.0, 238.0], [436.0, 238.0], [442.0, 282.0], [458.0, 282.0], 10);
            $rawPts[] = [516.0, 282.0];
            $sampleCubic($rawPts, [516.0, 282.0], [552.0, 282.0], [574.0, 238.0], [618.0, 238.0], 12);
            $sampleCubic($rawPts, [618.0, 238.0], [676.0, 238.0], [720.0, 274.0], [720.0, 326.0], 12);
            $sampleCubic($rawPts, [720.0, 326.0], [720.0, 366.0], [704.5, 384.0], [704.5, 400.0], 10);
            $sampleCubic($rawPts, [704.5, 400.0], [704.5, 416.0], [720.0, 434.0], [720.0, 474.0], 10);
            $sampleCubic($rawPts, [720.0, 474.0], [720.0, 526.0], [676.0, 562.0], [618.0, 562.0], 12);
            $sampleCubic($rawPts, [618.0, 562.0], [574.0, 562.0], [552.0, 518.0], [516.0, 518.0], 12);
            $rawPts[] = [284.0, 518.0];
            $sampleCubic($rawPts, [284.0, 518.0], [248.0, 518.0], [226.0, 562.0], [182.0, 562.0], 12);
            $sampleCubic($rawPts, [182.0, 562.0], [124.0, 562.0], [80.0, 526.0], [80.0, 474.0], 12);
            $sampleCubic($rawPts, [80.0, 474.0], [80.0, 434.0], [95.5, 416.0], [95.5, 400.0], 10);
            $sampleCubic($rawPts, [95.5, 400.0], [95.5, 384.0], [80.0, 366.0], [80.0, 326.0], 10);
            $sampleCubic($rawPts, [80.0, 326.0], [80.0, 274.0], [124.0, 238.0], [182.0, 238.0], 12);
            $sampleCubic($rawPts, [182.0, 238.0], [226.0, 238.0], [248.0, 282.0], [284.0, 282.0], 12);
            $ptsMm = $normalizePixelPointsToMm($rawPts);
        } elseif ($contourShape === 'rounded_bar' || $contourShape === 'rounded_card') {
            $r = $contourShape === 'rounded_bar'
                ? min(1.0, min($widthMm, $heightMm) * 0.22)
                : min(3.18, min($widthMm, $heightMm) * 0.12);
            $corners = [
                [$widthMm - $r, $r, -M_PI * 0.5, 0.0],
                [$widthMm - $r, $heightMm - $r, 0.0, M_PI * 0.5],
                [$r, $heightMm - $r, M_PI * 0.5, M_PI],
                [$r, $r, M_PI, M_PI * 1.5],
            ];
            foreach ($corners as [$cx, $cy, $a0, $a1]) {
                for ($i = 0; $i <= 8; $i++) {
                    $a = $a0 + ($a1 - $a0) * ($i / 8);
                    $ptsMm[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
                }
            }
        } elseif ($contourShape === 'custom_polygon' && !empty($workingAreaPolygonPct)) {
            $rawPts = array_map(static fn (array $pt): array => [
                (float) ($pt['x'] ?? 0.0),
                (float) ($pt['y'] ?? 0.0),
            ], $workingAreaPolygonPct);
            $ptsMm = $normalizePixelPointsToMm($rawPts);
        }

        if (count($ptsMm) < 3) {
            return sprintf('M 0.000 0.000 L %.3f 0.000 L %.3f %.3f L 0.000 %.3f Z', $widthMm, $widthMm, $heightMm, $heightMm);
        }

        $dParts = [];
        foreach ($ptsMm as $idx => [$mx, $my]) {
            $px = number_format($mx, 3, '.', '');
            $py = number_format($my, 3, '.', '');
            $dParts[] = ($idx === 0 ? 'M ' : 'L ') . "{$px} {$py}";
        }

        return implode(' ', $dParts) . ' Z';
    }

    /**
     * Rendert die komplette Gravur-Bühne (Fotos + Vektor-Symbole + TTF-Schriften)
     * maßstabsgetreu sowohl als physische Laser-Bitmap (pngBytes: Schwarz = Laser feuert)
     * als auch als visuelles WYSIWYG-Kamera-Overlay (previewPngBytes: positiv milchig-weiß auf
     * dunklen/transparenten Medien bzw. dunkel auf echt transparentem Hintergrund bei hellen Medien).
     *
     * @return array{pngBytes: string, previewPngBytes: string, pixelWidth: int, pixelHeight: int}
     */
    protected function renderFullStageLaserPng(
        float $widthMm,
        float $heightMm,
        array $workingAreaPolygonPct,
        array $logos,
        array $texts,
        $item = null,
        bool $isDarkSurface = false,
        ?array $photoProfile = null
    ): array {
        $minDimMm = max(1.0, min($widthMm, $heightMm));
        $maxDimMm = max(1.0, max($widthMm, $heightMm));
        $pxPerMm = min(32.0, max(12.5, max(1680.0 / $maxDimMm, 140.0 / $minDimMm)));
        $stageW = max(32, (int) round($widthMm * $pxPerMm));
        $stageH = max(32, (int) round($heightMm * $pxPerMm));

        $canvas = imagecreatetruecolor($stageW, $stageH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $trans = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $trans);

        // Multi-Layer-Trennung (.xcs / .xf): Kundenfotos auf eigenem Foto-Bitmap-Layer, Texte & Vektorsymbole
        // auf eigenem Vektor-Layer mit identischer Geometrie (gleiche Stage-Pixel, gleiche Maske, gleiche zIndex-Reihenfolge)
        $isLayered = $photoProfile !== null;
        $photoCanvas = null;
        $vectorCanvas = null;
        $photoTrans = null;
        $vectorTrans = null;
        $hasPhotoLayer = false;
        $hasVectorLayer = false;
        $photoLayerInfo = null;
        if ($isLayered) {
            $photoCanvas = imagecreatetruecolor($stageW, $stageH);
            imagealphablending($photoCanvas, false);
            imagesavealpha($photoCanvas, true);
            $photoTrans = imagecolorallocatealpha($photoCanvas, 255, 255, 255, 127);
            imagefill($photoCanvas, 0, 0, $photoTrans);

            $vectorCanvas = imagecreatetruecolor($stageW, $stageH);
            imagealphablending($vectorCanvas, false);
            imagesavealpha($vectorCanvas, true);
            $vectorTrans = imagecolorallocatealpha($vectorCanvas, 255, 255, 255, 127);
            imagefill($vectorCanvas, 0, 0, $vectorTrans);
        }

        $overlayTrackImg = imagecreatetruecolor($stageW, $stageH);
        $trackZero = imagecolorallocate($overlayTrackImg, 0, 0, 0);
        imagefill($overlayTrackImg, 0, 0, $trackZero);

        $maskImg = imagecreatetruecolor($stageW, $stageH);
        $maskBlack = imagecolorallocate($maskImg, 0, 0, 0);
        $maskWhite = imagecolorallocate($maskImg, 255, 255, 255);
        imagefill($maskImg, 0, 0, $maskBlack);

        $flatMaskPoly = [];
        foreach ($workingAreaPolygonPct as $pt) {
            $flatMaskPoly[] = (int) round(($pt['x'] / 100.0) * ($stageW - 1));
            $flatMaskPoly[] = (int) round(($pt['y'] / 100.0) * ($stageH - 1));
        }
        if (count($flatMaskPoly) >= 6) {
            imagefilledpolygon($maskImg, $flatMaskPoly, $maskWhite);
        } else {
            imagefill($maskImg, 0, 0, $maskWhite);
        }

        // Gemeinsame zIndex-Sortierung von Logos (Default 10 + idx) und Texten (Default 30 + idx)
        // exakt identisch zur Kunden-Konfigurator-Engine (scripts_frontend_2.blade.php)
        $sortedQueue = $this->buildSortedLaserLayerQueue($logos, $texts);
        $itemConfigForLogos = (is_object($item) && is_array($item->configuration ?? null)) ? $item->configuration : [];

        foreach ($sortedQueue as $entry) {
            if ($entry['kind'] === 'logo') {
                $logo = $entry['data'];
                $url = (string) ($logo['url'] ?? $logo['value'] ?? $logo['src'] ?? '');
                if ($url === '' && empty($logo['value'])) {
                    continue;
                }

                $resolvedSource = $this->resolveEffectiveLogoSourceForLaser($logo, $item, $itemConfigForLogos);
                $rawBytes = $resolvedSource['bytes'];
                $ext = $resolvedSource['ext'];
                if ($rawBytes === null) {
                    continue;
                }

                if ($ext === 'svg') {
                    $this->drawSvgLogoOntoStageCanvas($canvas, $rawBytes, $logo, $widthMm, $heightMm, $stageW, $stageH, true, $overlayTrackImg);
                    if ($isLayered) {
                        $this->drawSvgLogoOntoStageCanvas($vectorCanvas, $rawBytes, $logo, $widthMm, $heightMm, $stageW, $stageH, true, null);
                        $hasVectorLayer = true;
                    }
                } else {
                    $entryPhotoProfile = $photoProfile;
                    if (!empty($resolvedSource['is_adjusted'])) {
                        $entryPhotoProfile = is_array($photoProfile) ? $photoProfile : ['polarity' => $isDarkSurface ? 'white_on_dark' : 'dark_on_light', 'is_dark_surface' => $isDarkSurface, 'dpi' => 600];
                        $entryPhotoProfile['skip_halftone_processing'] = true;
                        $entryPhotoProfile['studio_dither_mode'] = strtolower((string) ($resolvedSource['adjustment']['dither_mode'] ?? 'jarvis'));
                    }
                    $clipped = $this->clipAndNormalizeRasterLogoForLaser(
                        $rawBytes,
                        $ext,
                        $logo,
                        $widthMm,
                        $heightMm,
                        $workingAreaPolygonPct,
                        [],
                        false,
                        $entryPhotoProfile
                    );
                    if ($clipped !== null) {
                        $partImg = @imagecreatefromstring($clipped['pngData']);
                        if ($partImg) {
                            $dstX = (int) round(($clipped['xMm'] / $widthMm) * $stageW);
                            $dstY = (int) round(($clipped['yMm'] / $heightMm) * $stageH);
                            $dstW = max(1, (int) round(($clipped['widthMm'] / $widthMm) * $stageW));
                            $dstH = max(1, (int) round(($clipped['heightMm'] / $heightMm) * $stageH));
                            imagealphablending($canvas, true);
                            imagecopyresampled($canvas, $partImg, $dstX, $dstY, 0, 0, $dstW, $dstH, imagesx($partImg), imagesy($partImg));
                            imagealphablending($canvas, false);

                            if ($isLayered) {
                                imagealphablending($photoCanvas, true);
                                imagecopyresampled($photoCanvas, $partImg, $dstX, $dstY, 0, 0, $dstW, $dstH, imagesx($partImg), imagesy($partImg));
                                imagealphablending($photoCanvas, false);
                                $hasPhotoLayer = true;
                                $meta = is_array($clipped['photoMeta'] ?? null) ? $clipped['photoMeta'] : [];
                                if ($photoLayerInfo === null || ($meta['class'] ?? '') === 'halftone') {
                                    $photoLayerInfo = $meta;
                                }
                            }

                            // Falls ein Foto per höherem zIndex über einer älteren Vektor-/Textebene liegt, Track dort zurücksetzen
                            $clX0 = max(0, $dstX);
                            $clX1 = min($stageW - 1, $dstX + $dstW - 1);
                            $clY0 = max(0, $dstY);
                            $clY1 = min($stageH - 1, $dstY + $dstH - 1);
                            $pw = max(1, imagesx($partImg));
                            $ph = max(1, imagesy($partImg));
                            for ($py = $clY0; $py <= $clY1; $py++) {
                                $sy = min($ph - 1, max(0, (int) floor((($py - $dstY) / max(1, $dstH)) * $ph)));
                                for ($px = $clX0; $px <= $clX1; $px++) {
                                    $sx = min($pw - 1, max(0, (int) floor((($px - $dstX) / max(1, $dstW)) * $pw)));
                                    if (((imagecolorat($partImg, $sx, $sy) >> 24) & 0x7F) < 120) {
                                        imagesetpixel($overlayTrackImg, $px, $py, $trackZero);
                                        if ($isLayered) {
                                            // Foto liegt per zIndex über älterer Text-/Vektorebene: dort nicht doppelt lasern
                                            imagesetpixel($vectorCanvas, $px, $py, $vectorTrans);
                                        }
                                    }
                                }
                            }

                            imagedestroy($partImg);
                        }
                    }
                }
            } elseif ($entry['kind'] === 'text') {
                $this->drawTextItemOntoStageCanvas($canvas, $entry['data'], $widthMm, $stageW, $stageH, $overlayTrackImg);
                if ($isLayered) {
                    $rawTextForLayer = trim((string) ($entry['data']['text'] ?? ''));
                    if ($rawTextForLayer !== '' && $rawTextForLayer !== 'Text eingeben...') {
                        $this->drawTextItemOntoStageCanvas($vectorCanvas, $entry['data'], $widthMm, $stageW, $stageH, null);
                        $hasVectorLayer = true;
                    }
                }
            }
        }

        $previewCanvas = imagecreatetruecolor($stageW, $stageH);
        imagealphablending($previewCanvas, false);
        imagesavealpha($previewCanvas, true);
        $previewTrans = imagecolorallocatealpha($previewCanvas, 255, 255, 255, 127);
        imagefill($previewCanvas, 0, 0, $previewTrans);

        $frostedWhiteLut = [];
        $darkHaloLut = [];
        $darkEngraveLut = [];
        $frostedToneBuckets = [];
        for ($b = 0; $b < 8; $b++) {
            $tTone = $b / 7.0;
            $tr = (int) round(184.0 + (64.0 * $tTone));
            $tg = (int) round(192.0 + (58.0 * $tTone));
            $tb = (int) round(204.0 + (49.0 * $tTone));
            $frostedToneBuckets[$b] = [];
            for ($a = 0; $a <= 127; $a++) {
                $frostedToneBuckets[$b][$a] = imagecolorallocatealpha($previewCanvas, $tr, $tg, $tb, $a);
            }
        }
        for ($a = 0; $a <= 127; $a++) {
            $frostedWhiteLut[$a] = $frostedToneBuckets[7][$a];
            $darkHaloLut[$a] = imagecolorallocatealpha($previewCanvas, 15, 23, 42, $a);
            $darkEngraveLut[$a] = imagecolorallocatealpha($previewCanvas, 22, 19, 15, $a);
        }

        // Schritt A: Hart an der Arbeitsbereich-Maske (workingAreaPolygonPct) clippen & Roh-Dichtefeld speicherschonend als 1-Byte-Strings erfassen
        static $chrLut = null;
        static $inv255Lut = null;
        static $opaqueRawChrLut = null;
        if ($chrLut === null) {
            $chrLut = [];
            $inv255Lut = [];
            $opaqueRawChrLut = [];
            for ($i = 0; $i <= 255; $i++) {
                $chrLut[$i] = chr($i);
                $inv255Lut[$i] = $i / 255.0;
                $rawVal = $i < 246 ? max(0.0, min(1.0, (246.0 - $i) / 224.0)) : 0.0;
                $opaqueRawChrLut[$i] = chr((int) round($rawVal * 255.0));
            }
        }

        $totalStagePx = $stageW * $stageH;
        $rawBytes = str_repeat("\x00", $totalStagePx);
        $maskBytes = str_repeat("\x00", $totalStagePx);

        for ($y = 0; $y < $stageH; $y++) {
            $rowOff = $y * $stageW;
            for ($x = 0; $x < $stageW; $x++) {
                if ((imagecolorat($maskImg, $x, $y) & 0xFF) === 0) {
                    imagesetpixel($canvas, $x, $y, $trans);
                    if ($isLayered) {
                        imagesetpixel($photoCanvas, $x, $y, $photoTrans);
                        imagesetpixel($vectorCanvas, $x, $y, $vectorTrans);
                    }
                    continue;
                }

                $idx = $rowOff + $x;
                $maskBytes[$idx] = "\x01";
                $c = imagecolorat($canvas, $x, $y);
                $gdAlpha = ($c >> 24) & 0x7F;
                $gray = ($c >> 16) & 0xFF;

                if ($gdAlpha < 126 && $gray < 246) {
                    if ($gdAlpha === 0) {
                        $rawBytes[$idx] = $opaqueRawChrLut[$gray];
                    } else {
                        $opacity = (127 - $gdAlpha) / 127.0;
                        $r8 = (int) round(max(0.0, min(1.0, ((246.0 - $gray) / 224.0) * $opacity)) * 255.0);
                        $rawBytes[$idx] = $chrLut[$r8];
                    }
                }
            }
        }

        // Schritt B: 3x3 Halbton-Glättung für geditherte Punktraster auf gepackten Bytes (0 Vollbild-PHP-Arrays)
        $smoothBytes = $rawBytes;
        $zeroRowStr = str_repeat("\x00", $stageW);
        for ($y = 1; $y < $stageH - 1; $y++) {
            $rPrev = ($y - 1) * $stageW;
            $rCurr = $y * $stageW;
            $rNext = ($y + 1) * $stageW;
            if (
                substr($rawBytes, $rCurr, $stageW) === $zeroRowStr
                && substr($rawBytes, $rPrev, $stageW) === $zeroRowStr
                && substr($rawBytes, $rNext, $stageW) === $zeroRowStr
            ) {
                continue;
            }
            for ($x = 1; $x < $stageW - 1; $x++) {
                $idx = $rCurr + $x;
                if ($maskBytes[$idx] === "\x00") {
                    continue;
                }
                $sum9 = ord($rawBytes[$rPrev + $x - 1]) + ord($rawBytes[$rPrev + $x]) + ord($rawBytes[$rPrev + $x + 1])
                    + ord($rawBytes[$idx - 1]) + (ord($rawBytes[$idx]) << 1) + ord($rawBytes[$idx + 1])
                    + ord($rawBytes[$rNext + $x - 1]) + ord($rawBytes[$rNext + $x]) + ord($rawBytes[$rNext + $x + 1]);
                if ($sum9 > 0) {
                    $smoothBytes[$idx] = $chrLut[intdiv($sum9 + 5, 10)];
                }
            }
        }

        // Schritt C: Differenzierte WYSIWYG-Cockpit-Vorschau mit feinem Gravur-Kontrast & dunkler Mikro-Kontur an Motivkanten
        for ($y = 0; $y < $stageH; $y++) {
            $rowOff = $y * $stageW;
            $yM1 = max(0, $y - 1) * $stageW;
            $yP1 = min($stageH - 1, $y + 1) * $stageW;
            for ($x = 0; $x < $stageW; $x++) {
                $idx = $rowOff + $x;
                if ($maskBytes[$idx] === "\x00") {
                    continue;
                }

                $rByte = ord($rawBytes[$idx]);
                $sByte = ord($smoothBytes[$idx]);
                $ov = imagecolorat($overlayTrackImg, $x, $y);

                if ($rByte === 0 && $sByte === 0 && $ov === 0) {
                    if (!$isDarkSurface) {
                        continue;
                    }
                    $xM1 = max(0, $x - 1);
                    $xP1 = min($stageW - 1, $x + 1);
                    if (
                        ord($smoothBytes[$rowOff + $xM1]) < 16
                        && ord($smoothBytes[$rowOff + $xP1]) < 16
                        && ord($smoothBytes[$yM1 + $x]) < 16
                        && ord($smoothBytes[$yP1 + $x]) < 16
                    ) {
                        continue;
                    }
                }

                $glyphCov = ($ov >> 16) & 0xFF;
                $haloCov = ($ov >> 8) & 0xFF;

                $rawStrength = $inv255Lut[$rByte];
                $smoothStrength = $inv255Lut[$sByte];
                $effStrength = ($rawStrength * 0.42) + ($smoothStrength * 0.58);

                $xM1 = max(0, $x - 1);
                $xP1 = min($stageW - 1, $x + 1);
                $nLeft = $inv255Lut[ord($smoothBytes[$rowOff + $xM1])];
                $nRight = $inv255Lut[ord($smoothBytes[$rowOff + $xP1])];
                $nUp = $inv255Lut[ord($smoothBytes[$yM1 + $x])];
                $nDown = $inv255Lut[ord($smoothBytes[$yP1 + $x])];
                $edgeGrad = abs($nRight - $nLeft) + abs($nDown - $nUp);
                $neighborMax = max($nLeft, $nRight, $nUp, $nDown);

                if ($isDarkSurface) {
                    if ($glyphCov > 20) {
                        $visOpacity = min(0.97, max($effStrength * 0.90, ($glyphCov / 235.0) * 0.97));
                        $pAlpha = max(0, min(127, (int) round(127.0 * (1.0 - $visOpacity))));
                        imagesetpixel($previewCanvas, $x, $y, $frostedWhiteLut[$pAlpha]);
                    } elseif ($haloCov > 20) {
                        $haloWeight = ($haloCov / 255.0) * ($effStrength > 0.02 ? 0.72 : 0.50);
                        $pAlpha = max(0, min(127, (int) round(127.0 * (1.0 - $haloWeight))));
                        imagesetpixel($previewCanvas, $x, $y, $darkHaloLut[$pAlpha]);
                    } elseif ($edgeGrad > 0.14 && $smoothStrength < ($neighborMax - 0.06) && $effStrength < 0.30) {
                        // Dunkle Mikro-Kontur an Augen, Haaren und Motivkanten für klaren Kontrast auf glänzendem Kamerabild
                        $microWeight = min(0.56, $edgeGrad * 0.78);
                        $pAlpha = max(0, min(126, (int) round(127.0 * (1.0 - $microWeight))));
                        imagesetpixel($previewCanvas, $x, $y, $darkHaloLut[$pAlpha]);
                    } elseif ($effStrength > 0.012) {
                        // Abgestufte Silber-Gradation statt flächiger RGB(248,250,253) Nebel-Wand bei mittleren Dichten
                        $toneNorm = min(1.0, max(0.0, ($effStrength - 0.04) / 0.48));
                        $bucket = max(0, min(7, (int) round($toneNorm * 7.0)));
                        if ($effStrength < 0.52) {
                            $visOpacity = pow($effStrength / 0.52, 1.28) * 0.48;
                        } else {
                            $visOpacity = min(0.88, 0.48 + ((($effStrength - 0.52) / 0.48) * 0.40));
                        }
                        $visOpacity = min(0.90, $visOpacity + min(0.10, $edgeGrad * 0.16));
                        $pAlpha = max(0, min(126, (int) round(127.0 * (1.0 - $visOpacity))));
                        imagesetpixel($previewCanvas, $x, $y, $frostedToneBuckets[$bucket][$pAlpha]);
                    }
                } else {
                    if ($glyphCov > 20) {
                        $visOpacity = min(0.95, max($effStrength * 0.92, ($glyphCov / 235.0) * 0.95));
                        $pAlpha = max(0, min(127, (int) round(127.0 * (1.0 - $visOpacity))));
                        imagesetpixel($previewCanvas, $x, $y, $darkEngraveLut[$pAlpha]);
                    } elseif ($haloCov > 20 && $effStrength > 0.03) {
                        $reduced = $effStrength * max(0.0, 1.0 - (($haloCov / 255.0) * 0.85));
                        if ($reduced > 0.01) {
                            $pAlpha = max(0, min(126, (int) round(127.0 * (1.0 - ($reduced * 0.90)))));
                            imagesetpixel($previewCanvas, $x, $y, $darkEngraveLut[$pAlpha]);
                        }
                    } elseif ($effStrength > 0.010) {
                        $visOpacity = min(0.93, (pow($effStrength, 1.12) * 0.88) + min(0.08, $edgeGrad * 0.18));
                        $pAlpha = max(0, min(126, (int) round(127.0 * (1.0 - $visOpacity))));
                        imagesetpixel($previewCanvas, $x, $y, $darkEngraveLut[$pAlpha]);
                    }
                }
            }
        }
        unset($rawBytes, $smoothBytes, $maskBytes);
        imagedestroy($maskImg);
        imagedestroy($overlayTrackImg);
        unset($maskImg, $overlayTrackImg);

        ob_start();
        imagepng($canvas, null, 3);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($canvas);
        unset($canvas);

        ob_start();
        imagepng($previewCanvas, null, 3);
        $previewPngBytes = (string) ob_get_clean();
        imagedestroy($previewCanvas);
        unset($previewCanvas);

        $photoLayerPngBytes = null;
        $vectorLayerPngBytes = null;
        $knockoutPixels = 0;
        if ($isLayered) {
            if ($hasPhotoLayer && $hasVectorLayer) {
                // Lesbarkeits-Schutzsaum (~0,45 mm, 0 % Foto-Laserung) um Buchstaben & Symbole über dem Kundenfoto
                $knockoutPixels = app(\App\Services\Laser\LaserPhotoPreprocessor::class)->applyTextKnockoutHalo(
                    $photoCanvas,
                    $vectorCanvas,
                    max(1, (int) round(\App\Services\Laser\LaserPhotoPreprocessor::TEXT_KNOCKOUT_MM * $pxPerMm)),
                    max(1, (int) round(\App\Services\Laser\LaserPhotoPreprocessor::TEXT_KNOCKOUT_SOFT_MM * $pxPerMm))
                );
            }
            if ($hasPhotoLayer) {
                ob_start();
                imagepng($photoCanvas, null, 3);
                $photoLayerPngBytes = (string) ob_get_clean();
            }
            if ($hasVectorLayer) {
                ob_start();
                imagepng($vectorCanvas, null, 3);
                $vectorLayerPngBytes = (string) ob_get_clean();
            }
            imagedestroy($photoCanvas);
            imagedestroy($vectorCanvas);
            unset($photoCanvas, $vectorCanvas);
        }

        return [
            'pngBytes'            => $pngBytes,
            'previewPngBytes'     => $previewPngBytes,
            'pixelWidth'          => $stageW,
            'pixelHeight'         => $stageH,
            'pxPerMm'             => $pxPerMm,
            'photoLayerPngBytes'  => $photoLayerPngBytes,
            'vectorLayerPngBytes' => $vectorLayerPngBytes,
            'photoLayerInfo'      => $photoLayerInfo,
            'knockoutPixels'      => $knockoutPixels,
        ];
    }

    /**
     * Sortiert Logos (Default zIndex = 10 + idx) und Texte (Default zIndex = 30 + idx) in einer gemeinsamen
     * Zeichen-Warteschlange exakt analog zum 2D/3D-Shop-Konfigurator.
     *
     * @return array<int, array{kind: string, index: int, z: float, order: int, data: array}>
     */
    protected function buildSortedLaserLayerQueue(array $logos, array $texts): array
    {
        $queue = [];
        foreach (array_values($logos) as $idx => $logo) {
            if (!is_array($logo)) {
                continue;
            }
            $z = (isset($logo['zIndex']) && is_numeric($logo['zIndex']))
                ? (float) $logo['zIndex']
                : (float) (10 + $idx);
            $queue[] = [
                'kind'  => 'logo',
                'index' => $idx,
                'z'     => $z,
                'order' => $idx,
                'data'  => $logo,
            ];
        }

        foreach (array_values($texts) as $idx => $textItem) {
            if (!is_array($textItem)) {
                continue;
            }
            $z = (isset($textItem['zIndex']) && is_numeric($textItem['zIndex']))
                ? (float) $textItem['zIndex']
                : (float) (30 + $idx);
            $queue[] = [
                'kind'  => 'text',
                'index' => $idx,
                'z'     => $z,
                'order' => 10000 + $idx,
                'data'  => $textItem,
            ];
        }

        usort($queue, static function (array $a, array $b): int {
            $cmp = $a['z'] <=> $b['z'];
            return $cmp !== 0 ? $cmp : ($a['order'] <=> $b['order']);
        });

        return $queue;
    }

    /**
     * Berechnet den exakten vertikalen Baseline-Offset (in Pixeln relativ zur Zeilenmitte) analog zu
     * HTML5 Canvas textBaseline = 'middle' + yOffset = fontSize * 0.12 in scripts_frontend_2.blade.php.
     */
    protected function computeCanvasTextBaselineOffsetPx(float $ptSize, float $fontSizePx, string $ttfPath): float
    {
        if (function_exists('imagettfbbox')) {
            $emBbox = @imagettfbbox($ptSize, 0.0, $ttfPath, 'Äg');
            if ($emBbox) {
                $emMiddleToBaseline = -(($emBbox[7] + $emBbox[1]) / 2.0);
                return $emMiddleToBaseline + ($fontSizePx * 0.12);
            }
        }

        return $fontSizePx * 0.41;
    }

    /**
     * Zeichnet ein einzelnes Text-Element (gerade oder gebogen) an seiner exakten zIndex-Position auf das Stage-Canvas,
     * inklusive lokalem weißen Kontrast-Halo auf darunterliegenden opaken Foto-Pixeln.
     */
    protected function drawTextItemOntoStageCanvas($canvas, array $textItem, float $widthMm, int $stageW, int $stageH, $overlayTrackImg = null): void
    {
        if (!function_exists('imagettftext')) {
            return;
        }

        $rawText = trim((string) ($textItem['text'] ?? ''));
        if ($rawText === '' || $rawText === 'Text eingeben...') {
            return;
        }

        $preferBold = !empty($textItem['bold'])
            || strtolower(trim((string) ($textItem['weight'] ?? $textItem['fontWeight'] ?? ''))) === 'bold'
            || (int) ($textItem['fontWeight'] ?? 400) >= 600;
        $ttfPath = $this->resolveTtfFontPath((string) ($textItem['font'] ?? 'Montserrat'), $preferBold);
        if (!$ttfPath || !is_file($ttfPath)) {
            return;
        }

        $txPx = (((float) ($textItem['x'] ?? 50)) / 100.0) * $stageW;
        $tyPx = (((float) ($textItem['y'] ?? 50)) / 100.0) * $stageH;
        $rawSize = (float) ($textItem['size'] ?? 1.0);
        $fontSizeMm = ($rawSize / 25.0) * $widthMm;
        $fontSizePx = ($fontSizeMm / max(0.001, $widthMm)) * $stageW;
        $ptSize = max(4.0, $fontSizePx * 0.75);

        $tRotDeg = (float) ($textItem['rotation'] ?? 0);
        $gdAngle = -$tRotDeg;
        $rad = deg2rad($tRotDeg);
        $cosA = cos($rad);
        $sinA = sin($rad);
        $align = (string) ($textItem['align'] ?? 'center');
        $curve = max(-100.0, min(100.0, (float) ($textItem['curve'] ?? 0)));
        $haloRadius = max(2, min(8, (int) round($fontSizePx * 0.08)));
        $baseOffsetPx = $this->computeCanvasTextBaselineOffsetPx($ptSize, $fontSizePx, $ttfPath);

        $glyphMask = imagecreatetruecolor($stageW, $stageH);
        $haloMask = imagecreatetruecolor($stageW, $stageH);
        if (!$glyphMask || !$haloMask) {
            if ($glyphMask) imagedestroy($glyphMask);
            if ($haloMask) imagedestroy($haloMask);
            return;
        }

        $gmBlack = imagecolorallocate($glyphMask, 0, 0, 0);
        $gmWhite = imagecolorallocate($glyphMask, 255, 255, 255);
        imagefill($glyphMask, 0, 0, $gmBlack);

        $hmBlack = imagecolorallocate($haloMask, 0, 0, 0);
        $hmWhite = imagecolorallocate($haloMask, 255, 255, 255);
        imagefill($haloMask, 0, 0, $hmBlack);

        $bMinX = $stageW;
        $bMaxX = -1;
        $bMinY = $stageH;
        $bMaxY = -1;
        $rendered = false;

        $expandBounds = static function (array $bbox, int $originX, int $originY) use (&$bMinX, &$bMaxX, &$bMinY, &$bMaxY, $haloRadius, $stageW, $stageH): void {
            $xs = [$bbox[0], $bbox[2], $bbox[4], $bbox[6]];
            $ys = [$bbox[1], $bbox[3], $bbox[5], $bbox[7]];
            $pad = $haloRadius + 4;
            $x0 = max(0, (int) floor($originX + min($xs)) - $pad);
            $x1 = min($stageW - 1, (int) ceil($originX + max($xs)) + $pad);
            $y0 = max(0, (int) floor($originY + min($ys)) - $pad);
            $y1 = min($stageH - 1, (int) ceil($originY + max($ys)) + $pad);
            if ($x0 < $bMinX) $bMinX = $x0;
            if ($x1 > $bMaxX) $bMaxX = $x1;
            if ($y0 < $bMinY) $bMinY = $y0;
            if ($y1 > $bMaxY) $bMaxY = $y1;
        };

        if (abs($curve) >= 0.5) {
            $curvedChars = $this->computeCurvedTextArcChars($rawText, $fontSizePx, $curve, $ttfPath);
            foreach ($curvedChars as $c) {
                $ch = (string) $c['char'];
                if (trim($ch) === '') {
                    continue;
                }
                $chBbox = @imagettfbbox($ptSize, 0.0, $ttfPath, $ch);
                if (!$chBbox) {
                    continue;
                }
                $localBaseX = -(($chBbox[0] + $chBbox[2]) / 2.0);
                $localBaseY = $baseOffsetPx;
                $cosC = cos((float) $c['rad']);
                $sinC = sin((float) $c['rad']);
                $dx = (float) $c['x'] + ($localBaseX * $cosC) - ($localBaseY * $sinC);
                $dy = (float) $c['y'] + ($localBaseX * $sinC) + ($localBaseY * $cosC);
                $drawX = (int) round($txPx + ($dx * $cosA) - ($dy * $sinA));
                $drawY = (int) round($tyPx + ($dx * $sinA) + ($dy * $cosA));
                $charGdAngle = -($tRotDeg + (float) $c['deg']);

                for ($hy = -$haloRadius; $hy <= $haloRadius; $hy++) {
                    for ($hx = -$haloRadius; $hx <= $haloRadius; $hx++) {
                        if (($hx * $hx) + ($hy * $hy) > ($haloRadius * $haloRadius) + 1) {
                            continue;
                        }
                        @imagettftext($haloMask, $ptSize, $charGdAngle, $drawX + $hx, $drawY + $hy, $hmWhite, $ttfPath, $ch);
                    }
                }
                $drawnBox = @imagettftext($glyphMask, $ptSize, $charGdAngle, $drawX, $drawY, $gmWhite, $ttfPath, $ch);
                if (is_array($drawnBox)) {
                    $expandBounds([
                        $drawnBox[0] - $drawX, $drawnBox[1] - $drawY,
                        $drawnBox[2] - $drawX, $drawnBox[3] - $drawY,
                        $drawnBox[4] - $drawX, $drawnBox[5] - $drawY,
                        $drawnBox[6] - $drawX, $drawnBox[7] - $drawY,
                    ], $drawX, $drawY);
                }
                $rendered = true;
            }
        } else {
            $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
            $numLines = count($lines);
            $lineHeightPx = $fontSizePx * 1.15;
            $totalBlockH = ($numLines - 1) * $lineHeightPx;

            foreach ($lines as $lineIdx => $lineText) {
                $lineText = trim($lineText);
                if ($lineText === '') {
                    continue;
                }
                $bbox = @imagettfbbox($ptSize, 0.0, $ttfPath, $lineText);
                if (!$bbox) {
                    continue;
                }

                $lineCenterY = $tyPx - ($totalBlockH / 2.0) + ($lineIdx * $lineHeightPx);
                $unrotY = $lineCenterY + $baseOffsetPx;
                if ($align === 'left') {
                    $unrotX = $txPx - $bbox[0];
                } elseif ($align === 'right') {
                    $unrotX = $txPx - $bbox[2];
                } else {
                    $unrotX = $txPx - (($bbox[0] + $bbox[2]) / 2.0);
                }
                $dx = $unrotX - $txPx;
                $dy = $unrotY - $tyPx;
                $drawX = (int) round($txPx + ($dx * $cosA) - ($dy * $sinA));
                $drawY = (int) round($tyPx + ($dx * $sinA) + ($dy * $cosA));

                for ($hy = -$haloRadius; $hy <= $haloRadius; $hy++) {
                    for ($hx = -$haloRadius; $hx <= $haloRadius; $hx++) {
                        if (($hx * $hx) + ($hy * $hy) > ($haloRadius * $haloRadius) + 1) {
                            continue;
                        }
                        @imagettftext($haloMask, $ptSize, $gdAngle, $drawX + $hx, $drawY + $hy, $hmWhite, $ttfPath, $lineText);
                    }
                }
                $drawnBox = @imagettftext($glyphMask, $ptSize, $gdAngle, $drawX, $drawY, $gmWhite, $ttfPath, $lineText);
                if (is_array($drawnBox)) {
                    $expandBounds([
                        $drawnBox[0] - $drawX, $drawnBox[1] - $drawY,
                        $drawnBox[2] - $drawX, $drawnBox[3] - $drawY,
                        $drawnBox[4] - $drawX, $drawnBox[5] - $drawY,
                        $drawnBox[6] - $drawX, $drawnBox[7] - $drawY,
                    ], $drawX, $drawY);
                }
                $rendered = true;
            }
        }

        if ($rendered && $bMaxX >= $bMinX && $bMaxY >= $bMinY) {
            imagealphablending($canvas, false);
            $grayLut = [];
            for ($g = 0; $g <= 255; $g++) {
                $grayLut[$g] = imagecolorallocatealpha($canvas, $g, $g, $g, 0);
            }
            $alphaBlackLut = [];
            for ($a = 0; $a <= 127; $a++) {
                $alphaBlackLut[$a] = imagecolorallocatealpha($canvas, 0, 0, 0, $a);
            }

            for ($y = $bMinY; $y <= $bMaxY; $y++) {
                for ($x = $bMinX; $x <= $bMaxX; $x++) {
                    $gVal = imagecolorat($glyphMask, $x, $y) & 0xFF;
                    $hVal = imagecolorat($haloMask, $x, $y) & 0xFF;
                    if ($gVal === 0 && $hVal === 0) {
                        continue;
                    }

                    $existing = imagecolorat($canvas, $x, $y);
                    $exAlpha = ($existing >> 24) & 0x7F;

                    if ($gVal > 20) {
                        if ($exAlpha < 126) {
                            $glyphAlpha = min(1.0, $gVal / 220.0);
                            $gray = max(0, min(255, (int) round(255.0 * (1.0 - $glyphAlpha))));
                            imagesetpixel($canvas, $x, $y, $grayLut[$gray]);
                        } else {
                            // Auf transparentem Grund scharf mit echtem Alpha-Anti-Aliasing zeichnen
                            $gdAlpha = max(0, min(127, (int) round(127.0 * (1.0 - min(1.0, $gVal / 240.0)))));
                            imagesetpixel($canvas, $x, $y, $alphaBlackLut[$gdAlpha]);
                        }
                        if ($overlayTrackImg) {
                            $prevGlyph = (imagecolorat($overlayTrackImg, $x, $y) >> 16) & 0xFF;
                            $newGlyph = max($prevGlyph, $gVal);
                            imagesetpixel($overlayTrackImg, $x, $y, ($newGlyph << 16));
                        }
                    } elseif ($hVal > 20) {
                        if ($exAlpha < 126) {
                            // Kontrast-Halo in der physischen Laser-Bitmap ausschließlich auf darunterliegenden opaken Pixeln (z. B. Porträtfoto)
                            $exGray = $existing & 0xFF;
                            $haloStrength = min(0.90, ($hVal / 255.0) * 0.90);
                            $blendedGray = max(0, min(255, (int) round(($exGray * (1.0 - $haloStrength)) + (255.0 * $haloStrength))));
                            imagesetpixel($canvas, $x, $y, $grayLut[$blendedGray]);
                        }
                        if ($overlayTrackImg) {
                            $prevTrack = imagecolorat($overlayTrackImg, $x, $y);
                            $prevGlyph = ($prevTrack >> 16) & 0xFF;
                            if ($prevGlyph <= 20) {
                                $prevHalo = ($prevTrack >> 8) & 0xFF;
                                $newHalo = max($prevHalo, $hVal);
                                imagesetpixel($overlayTrackImg, $x, $y, ($prevGlyph << 16) | ($newHalo << 8));
                            }
                        }
                    }
                }
            }
        }

        imagedestroy($glyphMask);
        imagedestroy($haloMask);
    }

    /**
     * Löst für ein Logo-Element entweder das manuell im Laser-Foto-Studio gespeicherte Bild
     * (configuration['laser_photo_adjustments'][fileKey]['edited_path']) oder automatisch das echte
     * unberührte Originalfoto (_raw via resolveOriginalPhotoRelPath) inkl. EXIF-Korrektur auf.
     *
     * @return array{bytes: ?string, ext: ?string, is_adjusted: bool, adjustment: ?array<string, mixed>}
     */
    protected function resolveEffectiveLogoSourceForLaser(array $logo, $item = null, array $itemConfig = []): array
    {
        if (empty($itemConfig) && is_object($item) && is_array($item->configuration ?? null)) {
            $itemConfig = $item->configuration;
        }

        $url = trim((string) ($logo['url'] ?? $logo['value'] ?? $logo['src'] ?? ''));
        $rawVal = trim((string) ($logo['value'] ?? ''));
        $isSvgCandidate = str_starts_with(strtolower($url), 'data:image/svg')
            || str_ends_with(strtolower((string) (parse_url($url, PHP_URL_PATH) ?? $url)), '.svg')
            || str_ends_with(strtolower($rawVal), '.svg');

        if (!$isSvgCandidate) {
            $activeAdj = \App\Services\Laser\LaserPhotoPreprocessor::findActivePhotoAdjustment($logo, $itemConfig);
            if ($activeAdj !== null && !empty($activeAdj['resolved_edited_rel_path'])) {
                $adjDither = strtolower(trim((string) ($activeAdj['dither_mode'] ?? 'jarvis')));
                if (in_array($adjDither, ['jarvis', 'stucki'], true)) {
                    $origRelCand = !empty($activeAdj['original_path']) && is_string($activeAdj['original_path'])
                        ? \App\Services\Laser\LaserPhotoPreprocessor::normalizePublicRelPath($activeAdj['original_path'])
                        : '';
                    if ($origRelCand === '' || !Storage::disk('public')->exists($origRelCand)) {
                        $lookupKey = $rawVal !== '' ? $rawVal : $url;
                        if ($lookupKey !== '' && !str_starts_with($lookupKey, 'data:')) {
                            $origRelCand = \App\Services\Laser\LaserPhotoPreprocessor::normalizePublicRelPath(
                                \App\Services\Laser\LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($lookupKey, $itemConfig)
                            );
                        }
                    }
                    if ($origRelCand !== '' && Storage::disk('public')->exists($origRelCand)) {
                        $origAbsForStudio = Storage::disk('public')->path($origRelCand);
                        if (@is_file($origAbsForStudio)) {
                            $continuousBytes = \App\Services\Laser\LaserPhotoPreprocessor::renderStudioContinuousTonePngBytes(
                                $origAbsForStudio,
                                $activeAdj,
                                2200
                            );
                            if ($continuousBytes !== '') {
                                return [
                                    'bytes'       => $continuousBytes,
                                    'ext'         => 'png',
                                    'is_adjusted' => true,
                                    'adjustment'  => $activeAdj,
                                ];
                            }
                        }
                    }
                }

                $editedAbsPath = Storage::disk('public')->path((string) $activeAdj['resolved_edited_rel_path']);
                if (@is_file($editedAbsPath)) {
                    $editedBytes = @file_get_contents($editedAbsPath);
                    if (is_string($editedBytes) && $editedBytes !== '') {
                        return [
                            'bytes'       => $editedBytes,
                            'ext'         => 'png',
                            'is_adjusted' => true,
                            'adjustment'  => $activeAdj,
                        ];
                    }
                }
            }

            // Kein manuelles edited_path: zuerst echtes Originalfoto (_raw) auf Storage::disk('public') prüfen
            $candidateKey = $rawVal !== '' ? $rawVal : $url;
            if ($candidateKey !== '' && !str_starts_with($candidateKey, 'data:')) {
                $origRel = \App\Services\Laser\LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($candidateKey, $itemConfig);
                $origNorm = \App\Services\Laser\LaserPhotoPreprocessor::normalizePublicRelPath($origRel);
                if ($origNorm !== '' && Storage::disk('public')->exists($origNorm)) {
                    $origAbs = Storage::disk('public')->path($origNorm);
                    $origExt = strtolower(pathinfo($origAbs, PATHINFO_EXTENSION));
                    if (in_array($origExt, ['jpg', 'jpeg', 'png', 'webp'], true) && @is_file($origAbs)) {
                        if (in_array($origExt, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
                            $exif = @exif_read_data($origAbs);
                            $orient = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
                            if (in_array($orient, [3, 6, 8], true)) {
                                $orientedGd = \App\Services\Laser\LaserPhotoPreprocessor::loadOrientedGdImageFromPath($origAbs, 1600);
                                if ($orientedGd) {
                                    ob_start();
                                    imagepng($orientedGd, null, 3);
                                    $orientedPng = (string) ob_get_clean();
                                    imagedestroy($orientedGd);
                                    if ($orientedPng !== '') {
                                        return [
                                            'bytes'       => $orientedPng,
                                            'ext'         => 'png',
                                            'is_adjusted' => false,
                                            'adjustment'  => null,
                                        ];
                                    }
                                }
                            }
                        }
                        $origBytes = @file_get_contents($origAbs);
                        if (is_string($origBytes) && $origBytes !== '') {
                            return [
                                'bytes'       => $origBytes,
                                'ext'         => $origExt,
                                'is_adjusted' => false,
                                'adjustment'  => null,
                            ];
                        }
                    }
                }
            }
        }

        [$safeBytes, $safeExt] = $this->loadSafeLogoBytesAndExt($url !== '' ? $url : $rawVal, $item);

        // Security-Log im LFI-Fall analog zum bisherigen Verhalten
        $checkPath = rawurldecode((string) (parse_url($url !== '' ? $url : $rawVal, PHP_URL_PATH) ?? ''));
        if ($safeBytes === null && (str_contains($checkPath, '..') || str_contains($checkPath, "\0") || str_contains($checkPath, '\\'))) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:lfi_attempt_blocked',
                'LFI / Path-Traversal im Laser-Export blockiert',
                'Manipulierter Logo-Pfad im Laser-Export abgewiesen: ' . substr($checkPath, 0, 120),
                'error',
                ['order_item_id' => is_object($item) ? ($item->id ?? null) : null, 'path' => substr($checkPath, 0, 120)]
            );
        }

        return [
            'bytes'       => $safeBytes,
            'ext'         => $safeExt,
            'is_adjusted' => false,
            'adjustment'  => null,
        ];
    }

    /**
     * Lädt eine Logo-/Symbol-Datei sicher unter Beachtung der erlaubten Medien-Wurzeln.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function loadSafeLogoBytesAndExt(string $url, $item = null): array
    {
        if (str_starts_with($url, 'data:image')) {
            if (preg_match('/^data:image\/([a-zA-Z0-9\+\-]+);base64,(.*)$/s', $url, $matches)) {
                $ext = strtolower($matches[1]);
                if ($ext === 'svg+xml') $ext = 'svg';
                if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                    $decoded = base64_decode($matches[2]);
                    return [$decoded !== false ? $decoded : null, $ext];
                }
            }
            return [null, null];
        }

        $urlPath = rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? $url));
        if (str_contains($urlPath, '..') || str_contains($urlPath, "\0") || str_contains($urlPath, '\\')) {
            return [null, null];
        }

        if (str_contains($urlPath, '/storage/')) {
            $relativePath = ltrim(substr($urlPath, strpos($urlPath, '/storage/') + 9), '/');
            if ($relativePath !== '' && !str_contains($relativePath, '..') && Storage::disk('public')->exists($relativePath)) {
                $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                    return [(string) Storage::disk('public')->get($relativePath), $ext];
                }
            }
            $localPath = storage_path('app/public/' . $relativePath);
        } elseif (str_starts_with(ltrim($urlPath, '/'), 'laser-studio/') || str_starts_with(ltrim($urlPath, '/'), 'bestellungen/') || str_starts_with(ltrim($urlPath, '/'), 'produkte/')) {
            $relativePath = ltrim($urlPath, '/');
            if (!str_contains($relativePath, '..') && Storage::disk('public')->exists($relativePath)) {
                $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
                if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                    return [(string) Storage::disk('public')->get($relativePath), $ext];
                }
            }
            $localPath = storage_path('app/public/' . $relativePath);
        } elseif (str_contains($urlPath, '/images/')) {
            $relativePath = substr($urlPath, strpos($urlPath, '/images/') + 8);
            $localPath = public_path('shopverwaltung/images/' . ltrim($relativePath, '/'));
        } elseif (str_contains($urlPath, '/shop/')) {
            $relativePath = substr($urlPath, strpos($urlPath, '/shop/') + 6);
            $localPath = public_path('shop/' . ltrim($relativePath, '/'));
        } else {
            $localPath = public_path(ltrim($urlPath, '/'));
        }

        if (!$localPath || !file_exists($localPath)) {
            $baseName = basename($urlPath);
            $storageVectorPath = storage_path('app/public/produkte/configurator/vectors/' . $baseName);
            $vectorPath = public_path('shop/product/configurator/vectors/' . $baseName);
            if (file_exists($storageVectorPath)) {
                $localPath = $storageVectorPath;
            } elseif (file_exists($vectorPath)) {
                $localPath = $vectorPath;
            } else {
                foreach ([storage_path('app/public/produkte/configurator/vectors'), public_path('shop/product/configurator/vectors')] as $vDir) {
                    if (File::isDirectory($vDir)) {
                        foreach (File::allFiles($vDir) as $vFile) {
                            if ($vFile->getFilename() === $baseName) {
                                $localPath = $vFile->getPathname();
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if ($localPath && file_exists($localPath) && is_file($localPath)) {
            $realLocalPath = realpath($localPath);
            $allowedRoots = array_filter([
                realpath(storage_path('app/public')),
                realpath(public_path('shop')),
                realpath(public_path('shopverwaltung/images')),
            ]);
            if ($realLocalPath) {
                foreach ($allowedRoots as $root) {
                    if (str_starts_with($realLocalPath, $root . DIRECTORY_SEPARATOR)) {
                        $ext = strtolower(pathinfo($realLocalPath, PATHINFO_EXTENSION));
                        if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                            return [(string) file_get_contents($realLocalPath), $ext];
                        }
                    }
                }
            }
        }

        return [null, null];
    }

    /**
     * Zeichnet ein SVG-Vektorsymbol (inkl. <path>, <circle>, <ellipse>, <rect>, <polygon>, <polyline>,
     * SVG-Arcs A/a, fill-rule evenodd/nonzero Aussparungen und optionalem weißen Kontrast-Halo über Fotos)
     * maßstabsgetreu in das GD-Canvas.
     */
    protected function drawSvgLogoOntoStageCanvas(
        $canvas,
        string $svgRaw,
        array $logo,
        float $widthMm,
        float $heightMm,
        int $stageW,
        int $stageH,
        bool $withContrastHaloOverOpaque = true,
        $overlayTrackImg = null
    ): void {
        $cleanSvg = \App\Support\SafeHtmlAndSvgSanitizer::sanitizeSvg($svgRaw);
        if ($cleanSvg === '') {
            return;
        }

        $vX = 0.0;
        $vY = 0.0;
        $viewW = 24.0;
        $viewH = 24.0;
        if (preg_match('/<svg[^>]*viewBox="([^"]+)"/i', $cleanSvg, $m)) {
            $parts = preg_split('/[\s,]+/', trim($m[1])) ?: [];
            if (count($parts) >= 4) {
                $vX = (float) $parts[0];
                $vY = (float) $parts[1];
                $viewW = max(1.0, (float) $parts[2]);
                $viewH = max(1.0, (float) $parts[3]);
            }
        } elseif (
            preg_match('/<svg[^>]*\bwidth="([0-9\.]+)[a-z%]*"/i', $cleanSvg, $mw)
            && preg_match('/<svg[^>]*\bheight="([0-9\.]+)[a-z%]*"/i', $cleanSvg, $mh)
        ) {
            $viewW = max(1.0, (float) $mw[1]);
            $viewH = max(1.0, (float) $mh[1]);
        }

        $cxPx = (((float) ($logo['x'] ?? 50)) / 100.0) * $stageW;
        $cyPx = (((float) ($logo['y'] ?? 50)) / 100.0) * $stageH;
        $logoWidthPx = (((float) ($logo['size'] ?? 100)) / 500.0) * $stageW;
        $scale = $logoWidthPx / max(1.0, $viewW);
        $rotRad = deg2rad((float) ($logo['rotation'] ?? 0));
        $cosR = cos($rotRad);
        $sinR = sin($rotRad);
        $vbCenterX = $vX + ($viewW / 2.0);
        $vbCenterY = $vY + ($viewH / 2.0);

        $shapeElements = $this->extractSvgShapeSubPolygonGroups($cleanSvg);
        if (empty($shapeElements)) {
            return;
        }

        $transformedGroups = [];
        $minX = $stageW;
        $maxX = -1;
        $minY = $stageH;
        $maxY = -1;

        foreach ($shapeElements as $group) {
            $tSubPolys = [];
            foreach ($group['subPolys'] as $polyPts) {
                if (count($polyPts) < 3) {
                    continue;
                }
                $tPts = [];
                foreach ($polyPts as [$px, $py]) {
                    $lx = ($px - $vbCenterX) * $scale;
                    $ly = ($py - $vbCenterY) * $scale;
                    $rx = $cxPx + ($lx * $cosR) - ($ly * $sinR);
                    $ry = $cyPx + ($lx * $sinR) + ($ly * $cosR);
                    $tPts[] = [$rx, $ry];
                    $ix0 = (int) floor($rx);
                    $ix1 = (int) ceil($rx);
                    $iy0 = (int) floor($ry);
                    $iy1 = (int) ceil($ry);
                    if ($ix0 < $minX) $minX = $ix0;
                    if ($ix1 > $maxX) $maxX = $ix1;
                    if ($iy0 < $minY) $minY = $iy0;
                    if ($iy1 > $maxY) $maxY = $iy1;
                }
                if (count($tPts) >= 3) {
                    $tSubPolys[] = $tPts;
                }
            }
            if (!empty($tSubPolys)) {
                $transformedGroups[] = [
                    'fillRule' => $group['fillRule'],
                    'subPolys' => $tSubPolys,
                ];
            }
        }

        if (empty($transformedGroups) || $maxX < 0 || $maxY < 0 || $minX >= $stageW || $minY >= $stageH) {
            return;
        }

        $haloRadius = $withContrastHaloOverOpaque
            ? max(2, min(7, (int) round(min($stageW, $stageH) * 0.0042)))
            : 0;
        $pad = $haloRadius + 2;

        $bMinX = max(0, $minX - $pad);
        $bMaxX = min($stageW - 1, $maxX + $pad);
        $bMinY = max(0, $minY - $pad);
        $bMaxY = min($stageH - 1, $maxY + $pad);
        $bw = $bMaxX - $bMinX + 1;
        $bh = $bMaxY - $bMinY + 1;
        if ($bw <= 0 || $bh <= 0) {
            return;
        }

        static $chrLutSvg = null;
        if ($chrLutSvg === null) {
            $chrLutSvg = [];
            for ($i = 0; $i <= 255; $i++) {
                $chrLutSvg[$i] = chr($i);
            }
        }

        $totalPx = $bw * $bh;
        $finalMask = str_repeat("\x00", $totalPx);
        $subBuf = imagecreatetruecolor($bw, $bh);
        if (!$subBuf) {
            return;
        }
        $subBlack = imagecolorallocate($subBuf, 0, 0, 0);
        $subWhite = imagecolorallocate($subBuf, 255, 255, 255);

        foreach ($transformedGroups as $group) {
            $isEvenOdd = ($group['fillRule'] === 'evenodd');
            $subCount = count($group['subPolys']);

            if ($subCount === 1) {
                imagefilledrectangle($subBuf, 0, 0, $bw - 1, $bh - 1, $subBlack);
                $flat = [];
                foreach ($group['subPolys'][0] as [$rx, $ry]) {
                    $flat[] = (int) round($rx - $bMinX);
                    $flat[] = (int) round($ry - $bMinY);
                }
                if (count($flat) >= 6) {
                    @imagefilledpolygon($subBuf, $flat, $subWhite);
                    for ($ly = 0; $ly < $bh; $ly++) {
                        $rowOff = $ly * $bw;
                        for ($lx = 0; $lx < $bw; $lx++) {
                            if ((imagecolorat($subBuf, $lx, $ly) & 0xFF) > 0) {
                                $finalMask[$rowOff + $lx] = "\x01";
                            }
                        }
                    }
                }
                continue;
            }

            // Mehrere Sub-Pfade (z. B. weihnachtsbaum.svg mit 6 Kugel-Aussparungen oder brautpaar.svg Innenkleid):
            // Exakte W3C evenodd- bzw. nonzero-Winding-Auswertung auf gepacktem 1-Byte-String (Offset 128)
            $accum = str_repeat("\x80", $totalPx);
            foreach ($group['subPolys'] as $tPts) {
                imagefilledrectangle($subBuf, 0, 0, $bw - 1, $bh - 1, $subBlack);
                $flat = [];
                $signedArea2 = 0.0;
                $m = count($tPts);
                for ($k = 0; $k < $m; $k++) {
                    [$x0, $y0] = $tPts[$k];
                    [$x1, $y1] = $tPts[($k + 1) % $m];
                    $signedArea2 += ($x0 * $y1) - ($x1 * $y0);
                    $flat[] = (int) round($x0 - $bMinX);
                    $flat[] = (int) round($y0 - $bMinY);
                }
                if (count($flat) < 6) {
                    continue;
                }
                $sign = ($signedArea2 >= 0.0) ? 1 : -1;
                @imagefilledpolygon($subBuf, $flat, $subWhite);

                if ($isEvenOdd) {
                    for ($ly = 0; $ly < $bh; $ly++) {
                        $rowOff = $ly * $bw;
                        for ($lx = 0; $lx < $bw; $lx++) {
                            if ((imagecolorat($subBuf, $lx, $ly) & 0xFF) > 0) {
                                $idx = $rowOff + $lx;
                                $accum[$idx] = ($accum[$idx] === "\x80") ? "\x81" : "\x80";
                            }
                        }
                    }
                } else {
                    for ($ly = 0; $ly < $bh; $ly++) {
                        $rowOff = $ly * $bw;
                        for ($lx = 0; $lx < $bw; $lx++) {
                            if ((imagecolorat($subBuf, $lx, $ly) & 0xFF) > 0) {
                                $idx = $rowOff + $lx;
                                $accum[$idx] = $chrLutSvg[max(1, min(254, ord($accum[$idx]) + $sign))];
                            }
                        }
                    }
                }
            }

            for ($idx = 0; $idx < $totalPx; $idx++) {
                if ($accum[$idx] !== "\x80") {
                    $finalMask[$idx] = "\x01";
                }
            }
            unset($accum);
        }

        imagedestroy($subBuf);

        // Schnelle 2-Pass Chamfer-3-4 Distanztransformation auf gepacktem 1-Byte-String (Inf = 250)
        $dist = str_repeat("\xFA", $totalPx);
        for ($idx = 0; $idx < $totalPx; $idx++) {
            if ($finalMask[$idx] === "\x01") {
                $dist[$idx] = "\x00";
            }
        }
        unset($finalMask);

        if ($haloRadius > 0) {
            for ($ly = 0; $ly < $bh; $ly++) {
                $rowOff = $ly * $bw;
                for ($lx = 0; $lx < $bw; $lx++) {
                    $idx = $rowOff + $lx;
                    $d = ord($dist[$idx]);
                    if ($d === 0) {
                        continue;
                    }
                    if ($lx > 0) {
                        $cand = ord($dist[$idx - 1]) + 3;
                        if ($cand < $d) $d = $cand;
                    }
                    if ($ly > 0) {
                        $up = $idx - $bw;
                        $candUp = ord($dist[$up]) + 3;
                        if ($candUp < $d) $d = $candUp;
                        if ($lx > 0) {
                            $candUl = ord($dist[$up - 1]) + 4;
                            if ($candUl < $d) $d = $candUl;
                        }
                        if ($lx + 1 < $bw) {
                            $candUr = ord($dist[$up + 1]) + 4;
                            if ($candUr < $d) $d = $candUr;
                        }
                    }
                    $dist[$idx] = $chrLutSvg[$d];
                }
            }

            for ($ly = $bh - 1; $ly >= 0; $ly--) {
                $rowOff = $ly * $bw;
                for ($lx = $bw - 1; $lx >= 0; $lx--) {
                    $idx = $rowOff + $lx;
                    $d = ord($dist[$idx]);
                    if ($d === 0) {
                        continue;
                    }
                    if ($lx + 1 < $bw) {
                        $cand = ord($dist[$idx + 1]) + 3;
                        if ($cand < $d) $d = $cand;
                    }
                    if ($ly + 1 < $bh) {
                        $dn = $idx + $bw;
                        $candDn = ord($dist[$dn]) + 3;
                        if ($candDn < $d) $d = $candDn;
                        if ($lx + 1 < $bw) {
                            $candDr = ord($dist[$dn + 1]) + 4;
                            if ($candDr < $d) $d = $candDr;
                        }
                        if ($lx > 0) {
                            $candDl = ord($dist[$dn - 1]) + 4;
                            if ($candDl < $d) $d = $candDl;
                        }
                    }
                    $dist[$idx] = $chrLutSvg[$d];
                }
            }
        }

        imagealphablending($canvas, false);
        $grayLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $grayLut[$g] = imagecolorallocatealpha($canvas, $g, $g, $g, 0);
        }
        $blackOpaque = $grayLut[0];
        $maxHaloDist = $haloRadius * 3;

        for ($ly = 0; $ly < $bh; $ly++) {
            $gy = $bMinY + $ly;
            $rowOff = $ly * $bw;
            for ($lx = 0; $lx < $bw; $lx++) {
                $d = ord($dist[$rowOff + $lx]);
                if ($d > $maxHaloDist) {
                    continue;
                }
                $gx = $bMinX + $lx;
                if ($d === 0) {
                    imagesetpixel($canvas, $gx, $gy, $blackOpaque);
                    if ($overlayTrackImg) {
                        imagesetpixel($overlayTrackImg, $gx, $gy, (255 << 16));
                    }
                } elseif ($haloRadius > 0) {
                    $existing = imagecolorat($canvas, $gx, $gy);
                    $exAlpha = ($existing >> 24) & 0x7F;
                    $falloff = 1.0 - (($d - 1.0) / max(1.0, (float) $maxHaloDist));
                    $haloStrength = max(0.0, min(0.92, 0.45 + ($falloff * 0.50)));
                    if ($exAlpha < 126) {
                        $exGray = $existing & 0xFF;
                        $blended = max(0, min(255, (int) round(($exGray * (1.0 - $haloStrength)) + (255.0 * $haloStrength))));
                        imagesetpixel($canvas, $gx, $gy, $grayLut[$blended]);
                    }
                    if ($overlayTrackImg) {
                        $prevTrack = imagecolorat($overlayTrackImg, $gx, $gy);
                        $prevGlyph = ($prevTrack >> 16) & 0xFF;
                        if ($prevGlyph <= 20) {
                            $prevHalo = ($prevTrack >> 8) & 0xFF;
                            $hVal = max(0, min(255, (int) round($haloStrength * 255.0)));
                            $newHalo = max($prevHalo, $hVal);
                            imagesetpixel($overlayTrackImg, $gx, $gy, ($prevGlyph << 16) | ($newHalo << 8));
                        }
                    }
                }
            }
        }
    }

    /**
     * Extrahiert sämtliche grafischen Grundformen (<path>, <circle>, <ellipse>, <rect>, <polygon>, <polyline>)
     * eines bereinigten SVG-Dokuments in Dokumentreihenfolge als Sub-Polygon-Gruppen.
     *
     * @return array<int, array{fillRule: string, subPolys: array<int, array<int, array{0: float, 1: float}>>}>
     */
    protected function extractSvgShapeSubPolygonGroups(string $cleanSvg): array
    {
        $groups = [];
        $svgDefaultFillRule = preg_match('/<svg[^>]*\bfill-rule="\s*evenodd\s*"/i', $cleanSvg) ? 'evenodd' : 'nonzero';

        if (!preg_match_all('/<(path|circle|ellipse|rect|polygon|polyline)\b([^>]*)\/?>/i', $cleanSvg, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $attrFloat = static function (string $attrs, string $name, float $default = 0.0): float {
            if (preg_match('/\b' . preg_quote($name, '/') . '="\s*(-?\d*\.?\d+(?:e[-+]?\d+)?)/i', $attrs, $m)) {
                return (float) $m[1];
            }
            return $default;
        };

        foreach ($matches as $tagMatch) {
            $tag = strtolower($tagMatch[1]);
            $attrs = $tagMatch[2];

            if (preg_match('/\bfill="\s*none\s*"/i', $attrs) && !preg_match('/\bstroke="/i', $attrs)) {
                continue;
            }

            $fillRule = $svgDefaultFillRule;
            if (preg_match('/\bfill-rule="\s*(evenodd|nonzero)\s*"/i', $attrs, $frm)) {
                $fillRule = strtolower($frm[1]);
            }

            if ($tag === 'path') {
                if (preg_match('/\bd="([^"]+)"/i', $attrs, $dm)) {
                    $subPolys = $this->sampleSvgPathToPolygons($dm[1]);
                    if (!empty($subPolys)) {
                        $groups[] = ['fillRule' => $fillRule, 'subPolys' => $subPolys];
                    }
                }
            } elseif ($tag === 'circle') {
                $cx = $attrFloat($attrs, 'cx', 0.0);
                $cy = $attrFloat($attrs, 'cy', 0.0);
                $r = $attrFloat($attrs, 'r', 0.0);
                if ($r > 0.01) {
                    $pts = [];
                    for ($s = 0; $s < 48; $s++) {
                        $a = (2.0 * M_PI * $s) / 48.0;
                        $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
                    }
                    $groups[] = ['fillRule' => 'nonzero', 'subPolys' => [$pts]];
                }
            } elseif ($tag === 'ellipse') {
                $cx = $attrFloat($attrs, 'cx', 0.0);
                $cy = $attrFloat($attrs, 'cy', 0.0);
                $rx = $attrFloat($attrs, 'rx', 0.0);
                $ry = $attrFloat($attrs, 'ry', 0.0);
                if ($rx > 0.01 && $ry > 0.01) {
                    $pts = [];
                    for ($s = 0; $s < 48; $s++) {
                        $a = (2.0 * M_PI * $s) / 48.0;
                        $pts[] = [$cx + $rx * cos($a), $cy + $ry * sin($a)];
                    }
                    $groups[] = ['fillRule' => 'nonzero', 'subPolys' => [$pts]];
                }
            } elseif ($tag === 'rect') {
                $x = $attrFloat($attrs, 'x', 0.0);
                $y = $attrFloat($attrs, 'y', 0.0);
                $w = $attrFloat($attrs, 'width', 0.0);
                $h = $attrFloat($attrs, 'height', 0.0);
                if ($w > 0.01 && $h > 0.01) {
                    $pts = [
                        [$x, $y],
                        [$x + $w, $y],
                        [$x + $w, $y + $h],
                        [$x, $y + $h],
                    ];
                    $groups[] = ['fillRule' => 'nonzero', 'subPolys' => [$pts]];
                }
            } elseif ($tag === 'polygon' || $tag === 'polyline') {
                if (preg_match('/\bpoints="([^"]+)"/i', $attrs, $pm)) {
                    preg_match_all('/-?\d*\.?\d+(?:e[-+]?\d+)?/i', $pm[1], $nums);
                    $coords = $nums[0] ?? [];
                    $pts = [];
                    for ($k = 0; $k + 1 < count($coords); $k += 2) {
                        $pts[] = [(float) $coords[$k], (float) $coords[$k + 1]];
                    }
                    if (count($pts) >= 3) {
                        $groups[] = ['fillRule' => $fillRule, 'subPolys' => [$pts]];
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * Konvertiert einen SVG-Pfad-String (M, L, H, V, C, S, Q, T, A, Z in absolut/relativ) in geschlossene Punkt-Polygone.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    protected function sampleSvgPathToPolygons(string $dPath): array
    {
        preg_match_all('/([MmLlHhVvCcSsQqTtAaZz])|(-?\d*\.?\d+(?:e[-+]?\d+)?)/i', $dPath, $tokens);
        if (empty($tokens[0])) {
            return [];
        }

        $polys = [];
        $currentPoly = [];
        $cx = 0.0;
        $cy = 0.0;
        $startX = 0.0;
        $startY = 0.0;
        $prevCtrlX = 0.0;
        $prevCtrlY = 0.0;
        $cmd = '';
        $items = $tokens[0];
        $n = count($items);
        $i = 0;

        while ($i < $n) {
            $tok = $items[$i];
            if (ctype_alpha($tok)) {
                $cmd = $tok;
                $i++;
                if ($cmd === 'Z' || $cmd === 'z') {
                    if (count($currentPoly) >= 3) {
                        $polys[] = $currentPoly;
                    }
                    $currentPoly = [];
                    $cx = $startX;
                    $cy = $startY;
                    $prevCtrlX = $cx;
                    $prevCtrlY = $cy;
                    continue;
                }
            }

            $isRel = ctype_lower($cmd);
            $upper = strtoupper($cmd);

            if ($upper === 'M') {
                if ($i + 1 >= $n) break;
                if (count($currentPoly) >= 3) {
                    $polys[] = $currentPoly;
                }
                $x = (float) $items[$i++];
                $y = (float) $items[$i++];
                $cx = $isRel ? ($cx + $x) : $x;
                $cy = $isRel ? ($cy + $y) : $y;
                $startX = $cx;
                $startY = $cy;
                $currentPoly = [[$cx, $cy]];
                $prevCtrlX = $cx;
                $prevCtrlY = $cy;
                $cmd = $isRel ? 'l' : 'L';
            } elseif ($upper === 'L') {
                if ($i + 1 >= $n) break;
                $x = (float) $items[$i++];
                $y = (float) $items[$i++];
                $cx = $isRel ? ($cx + $x) : $x;
                $cy = $isRel ? ($cy + $y) : $y;
                $currentPoly[] = [$cx, $cy];
                $prevCtrlX = $cx;
                $prevCtrlY = $cy;
            } elseif ($upper === 'H') {
                if ($i >= $n) break;
                $x = (float) $items[$i++];
                $cx = $isRel ? ($cx + $x) : $x;
                $currentPoly[] = [$cx, $cy];
                $prevCtrlX = $cx;
                $prevCtrlY = $cy;
            } elseif ($upper === 'V') {
                if ($i >= $n) break;
                $y = (float) $items[$i++];
                $cy = $isRel ? ($cy + $y) : $y;
                $currentPoly[] = [$cx, $cy];
                $prevCtrlX = $cx;
                $prevCtrlY = $cy;
            } elseif ($upper === 'C') {
                if ($i + 5 >= $n) break;
                $x1 = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y1 = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                $x2 = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y2 = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                $x = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                for ($s = 1; $s <= 10; $s++) {
                    $t = $s / 10.0;
                    $mt = 1.0 - $t;
                    $bx = ($mt * $mt * $mt * $cx) + (3.0 * $mt * $mt * $t * $x1) + (3.0 * $mt * $t * $t * $x2) + ($t * $t * $t * $x);
                    $by = ($mt * $mt * $mt * $cy) + (3.0 * $mt * $mt * $t * $y1) + (3.0 * $mt * $t * $t * $y2) + ($t * $t * $t * $y);
                    $currentPoly[] = [$bx, $by];
                }
                $prevCtrlX = $x2;
                $prevCtrlY = $y2;
                $cx = $x;
                $cy = $y;
            } elseif ($upper === 'S') {
                if ($i + 3 >= $n) break;
                $x1 = 2.0 * $cx - $prevCtrlX;
                $y1 = 2.0 * $cy - $prevCtrlY;
                $x2 = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y2 = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                $x = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                for ($s = 1; $s <= 10; $s++) {
                    $t = $s / 10.0;
                    $mt = 1.0 - $t;
                    $bx = ($mt * $mt * $mt * $cx) + (3.0 * $mt * $mt * $t * $x1) + (3.0 * $mt * $t * $t * $x2) + ($t * $t * $t * $x);
                    $by = ($mt * $mt * $mt * $cy) + (3.0 * $mt * $mt * $t * $y1) + (3.0 * $mt * $t * $t * $y2) + ($t * $t * $t * $y);
                    $currentPoly[] = [$bx, $by];
                }
                $prevCtrlX = $x2;
                $prevCtrlY = $y2;
                $cx = $x;
                $cy = $y;
            } elseif ($upper === 'Q') {
                if ($i + 3 >= $n) break;
                $x1 = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y1 = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                $x = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                for ($s = 1; $s <= 8; $s++) {
                    $t = $s / 8.0;
                    $mt = 1.0 - $t;
                    $bx = ($mt * $mt * $cx) + (2.0 * $mt * $t * $x1) + ($t * $t * $x);
                    $by = ($mt * $mt * $cy) + (2.0 * $mt * $t * $y1) + ($t * $t * $y);
                    $currentPoly[] = [$bx, $by];
                }
                $prevCtrlX = $x1;
                $prevCtrlY = $y1;
                $cx = $x;
                $cy = $y;
            } elseif ($upper === 'T') {
                if ($i + 1 >= $n) break;
                $x1 = 2.0 * $cx - $prevCtrlX;
                $y1 = 2.0 * $cy - $prevCtrlY;
                $x = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y = (float) $items[$i++] + ($isRel ? $cy : 0.0);
                for ($s = 1; $s <= 8; $s++) {
                    $t = $s / 8.0;
                    $mt = 1.0 - $t;
                    $bx = ($mt * $mt * $cx) + (2.0 * $mt * $t * $x1) + ($t * $t * $x);
                    $by = ($mt * $mt * $cy) + (2.0 * $mt * $t * $y1) + ($t * $t * $y);
                    $currentPoly[] = [$bx, $by];
                }
                $prevCtrlX = $x1;
                $prevCtrlY = $y1;
                $cx = $x;
                $cy = $y;
            } elseif ($upper === 'A') {
                if ($i + 6 >= $n) break;
                $rx = (float) $items[$i++];
                $ry = (float) $items[$i++];
                $xAxisRot = (float) $items[$i++];
                $largeArcFlag = ((int) round((float) $items[$i++])) !== 0;
                $sweepFlag = ((int) round((float) $items[$i++])) !== 0;
                $x = (float) $items[$i++] + ($isRel ? $cx : 0.0);
                $y = (float) $items[$i++] + ($isRel ? $cy : 0.0);

                $arcPts = $this->sampleSvgArcToPoints($cx, $cy, $rx, $ry, $xAxisRot, $largeArcFlag, $sweepFlag, $x, $y);
                foreach ($arcPts as $ap) {
                    $currentPoly[] = $ap;
                }
                $prevCtrlX = $x;
                $prevCtrlY = $y;
                $cx = $x;
                $cy = $y;
            } else {
                $i++;
            }
        }

        if (count($currentPoly) >= 3) {
            $polys[] = $currentPoly;
        }

        return $polys;
    }

    /**
     * Berechnet Zwischenpunkte auf einem elliptischen SVG-Bogen (A/a) exakt nach W3C SVG 1.1 F.6.5/F.6.6.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected function sampleSvgArcToPoints(
        float $x1,
        float $y1,
        float $rx,
        float $ry,
        float $phiDeg,
        bool $largeArc,
        bool $sweep,
        float $x2,
        float $y2
    ): array {
        if (hypot($x2 - $x1, $y2 - $y1) < 1e-6) {
            return [];
        }

        $rx = abs($rx);
        $ry = abs($ry);
        if ($rx < 1e-6 || $ry < 1e-6) {
            return [[$x2, $y2]];
        }

        $phi = deg2rad(fmod($phiDeg, 360.0));
        $cosPhi = cos($phi);
        $sinPhi = sin($phi);

        $dx2 = ($x1 - $x2) / 2.0;
        $dy2 = ($y1 - $y2) / 2.0;
        $x1p = ($cosPhi * $dx2) + ($sinPhi * $dy2);
        $y1p = (-$sinPhi * $dx2) + ($cosPhi * $dy2);

        $lambda = (($x1p * $x1p) / ($rx * $rx)) + (($y1p * $y1p) / ($ry * $ry));
        if ($lambda > 1.0) {
            $scaleR = sqrt($lambda);
            $rx *= $scaleR;
            $ry *= $scaleR;
        }

        $rx2 = $rx * $rx;
        $ry2 = $ry * $ry;
        $x1p2 = $x1p * $x1p;
        $y1p2 = $y1p * $y1p;

        $num = max(0.0, ($rx2 * $ry2) - ($rx2 * $y1p2) - ($ry2 * $x1p2));
        $den = max(1e-9, ($rx2 * $y1p2) + ($ry2 * $x1p2));
        $coef = (($largeArc === $sweep) ? -1.0 : 1.0) * sqrt($num / $den);

        $cxp = $coef * (($rx * $y1p) / $ry);
        $cyp = $coef * (-($ry * $x1p) / $rx);

        $acx = ($cosPhi * $cxp) - ($sinPhi * $cyp) + (($x1 + $x2) / 2.0);
        $acy = ($sinPhi * $cxp) + ($cosPhi * $cyp) + (($y1 + $y2) / 2.0);

        $vecAngle = static function (float $ux, float $uy, float $vx, float $vy): float {
            $dot = ($ux * $vx) + ($uy * $vy);
            $len = max(1e-9, hypot($ux, $uy) * hypot($vx, $vy));
            $ang = acos(max(-1.0, min(1.0, $dot / $len)));
            return (($ux * $vy) - ($uy * $vx) < 0.0) ? -$ang : $ang;
        };

        $ux = ($x1p - $cxp) / $rx;
        $uy = ($y1p - $cyp) / $ry;
        $vx = (-$x1p - $cxp) / $rx;
        $vy = (-$y1p - $cyp) / $ry;

        $theta1 = $vecAngle(1.0, 0.0, $ux, $uy);
        $dTheta = $vecAngle($ux, $uy, $vx, $vy);
        if (!$sweep && $dTheta > 0.0) {
            $dTheta -= 2.0 * M_PI;
        } elseif ($sweep && $dTheta < 0.0) {
            $dTheta += 2.0 * M_PI;
        }

        $steps = max(8, min(36, (int) ceil(abs($dTheta) / (M_PI / 12.0))));
        $pts = [];
        for ($s = 1; $s <= $steps; $s++) {
            $ang = $theta1 + ($dTheta * ($s / $steps));
            $cosA = cos($ang);
            $sinA = sin($ang);
            $pts[] = [
                ($cosPhi * $rx * $cosA) - ($sinPhi * $ry * $sinA) + $acx,
                ($sinPhi * $rx * $cosA) + ($cosPhi * $ry * $sinA) + $acy,
            ];
        }

        return $pts;
    }

    /**
     * Erzeugt das Arbeitsbereich-Polygon (in 0..100 % Stage-Koordinaten) exakt analog
     * zur 2D/3D-Vorschau (inkl. eckenerhaltender Chaikin-Glättung für Custom-Polygone wie das Herz).
     *
     * @return array<int, array{x: float, y: float}>
     */
    public function buildWorkingAreaPolygonPercent(array $productConfig): array
    {
        $areaShape = (string) ($productConfig['area_shape'] ?? 'rect');
        $areaLeft = (float) ($productConfig['area_left'] ?? 0);
        $areaTop = (float) ($productConfig['area_top'] ?? 0);
        $areaWidth = (float) ($productConfig['area_width'] ?? 100);
        $areaHeight = (float) ($productConfig['area_height'] ?? 100);

        if ($areaShape === 'custom' && !empty($productConfig['custom_points']) && is_array($productConfig['custom_points'])) {
            $pts = [];
            foreach ($productConfig['custom_points'] as $p) {
                if (is_array($p) && isset($p['x'], $p['y']) && is_numeric($p['x']) && is_numeric($p['y'])) {
                    $pts[] = [
                        'x' => (float) $p['x'],
                        'y' => (float) $p['y'],
                    ];
                }
            }

            if (count($pts) >= 3) {
                $initialCount = count($pts);
                $maxEdgeLen = 0.0;
                for ($i = 0; $i < $initialCount; $i++) {
                    $nextPt = $pts[($i + 1) % $initialCount];
                    $edgeLen = hypot($nextPt['x'] - $pts[$i]['x'], $nextPt['y'] - $pts[$i]['y']);
                    if ($edgeLen > $maxEdgeLen) {
                        $maxEdgeLen = $edgeLen;
                    }
                }

                // Bereits fein abgetastete analytische Bezier-Konturen (z. B. 72-Punkte-Herz):
                // 1:1 übernehmen, damit weder V-Kerbe/Spitze verrundet noch die konvexen oberen Lobe-Bögen nach innen abgeflacht werden.
                if ($initialCount >= 48 && $maxEdgeLen <= 4.5) {
                    return $pts;
                }

                $isSharpFeatureAt = static function (array $ring, int $idx, int $len): bool {
                    $pPrev = $ring[($idx - 1 + $len) % $len];
                    $pCurr = $ring[$idx];
                    $pNext = $ring[($idx + 1) % $len];
                    $v1x = $pCurr['x'] - $pPrev['x'];
                    $v1y = $pCurr['y'] - $pPrev['y'];
                    $v2x = $pNext['x'] - $pCurr['x'];
                    $v2y = $pNext['y'] - $pCurr['y'];
                    $l1 = hypot($v1x, $v1y);
                    $l2 = hypot($v2x, $v2y);
                    $cosA = ($l1 > 0.001 && $l2 > 0.001) ? (($v1x * $v2x + $v1y * $v2y) / ($l1 * $l2)) : 1.0;

                    if ($cosA < 0.65) {
                        return true;
                    }

                    // Vertikale Symmetrie-Kerbe (V-Notch oben) oder scharfe untere Spitze exakt eckig halten
                    if ((($v1y > 0.02 && $v2y < -0.02) || ($v1y < -0.02 && $v2y > 0.02)) && $cosA < 0.88) {
                        return true;
                    }

                    return false;
                };

                // Interpolierende 4-Punkt-Subdivision für grobe Stützpunkt-Polygone:
                // Behält alle Scheitel, V-Kerben und Spitzen exakt bei und wölbt konvexe Bögen ohne Chaikin-Schrumpfung.
                $current = $pts;
                for ($pass = 0; $pass < 2; $pass++) {
                    $n = count($current);
                    $sharpFlags = [];
                    for ($i = 0; $i < $n; $i++) {
                        $sharpFlags[$i] = $isSharpFeatureAt($current, $i, $n);
                    }

                    $refined = [];
                    for ($i = 0; $i < $n; $i++) {
                        $iPrev = ($i - 1 + $n) % $n;
                        $iNext = ($i + 1) % $n;
                        $iNext2 = ($i + 2) % $n;

                        $p0 = $current[$i];
                        $p1 = $current[$iNext];
                        $refined[] = $p0;

                        $segLen = hypot($p1['x'] - $p0['x'], $p1['y'] - $p0['y']);
                        if ($segLen <= 1.2) {
                            continue;
                        }

                        if ($sharpFlags[$i] || $sharpFlags[$iNext]) {
                            $refined[] = [
                                'x' => 0.5 * ($p0['x'] + $p1['x']),
                                'y' => 0.5 * ($p0['y'] + $p1['y']),
                            ];
                        } else {
                            $pMinus = $current[$iPrev];
                            $p2 = $current[$iNext2];
                            $refined[] = [
                                'x' => 0.5625 * ($p0['x'] + $p1['x']) - 0.0625 * ($pMinus['x'] + $p2['x']),
                                'y' => 0.5625 * ($p0['y'] + $p1['y']) - 0.0625 * ($pMinus['y'] + $p2['y']),
                            ];
                        }
                    }
                    $current = $refined;
                }

                return $current;
            }
        }

        if ($areaShape === 'circle') {
            $cx = $areaLeft + ($areaWidth / 2.0);
            $cy = $areaTop + ($areaHeight / 2.0);
            $rx = $areaWidth / 2.0;
            $ry = $areaHeight / 2.0;
            $pts = [];
            for ($i = 0; $i < 72; $i++) {
                $angle = (2.0 * M_PI * $i) / 72.0;
                $pts[] = [
                    'x' => $cx + $rx * cos($angle),
                    'y' => $cy + $ry * sin($angle),
                ];
            }
            return $pts;
        }

        return [
            ['x' => $areaLeft, 'y' => $areaTop],
            ['x' => $areaLeft + $areaWidth, 'y' => $areaTop],
            ['x' => $areaLeft + $areaWidth, 'y' => $areaTop + $areaHeight],
            ['x' => $areaLeft, 'y' => $areaTop + $areaHeight],
        ];
    }

    /**
     * Löst einen Konfigurator-Schriftnamen (inkl. aller 5 System-Fonts Arial, Times New Roman,
     * Verdana, Courier New, Georgia sowie Google-Fonts wie Dancing Script / Playfair Display)
     * auf die lokale .ttf-Schriftdatei in public/fonts/ttf/ auf (Standard-Gewicht 400).
     */
    public function resolveTtfFontPath(string $fontFamily, bool $preferBold = false): ?string
    {
        $primaryName = trim((string) explode(',', $fontFamily)[0], " \t\n\r\0\x0B\"'");
        $lowerRaw = strtolower($fontFamily);
        $isBold = $preferBold
            || str_contains($lowerRaw, 'bold')
            || (bool) preg_match('/\b(700|800|900)\b/', $lowerRaw);
        $wantsItalic = str_contains($lowerRaw, 'italic');

        $slug = \Illuminate\Support\Str::slug($primaryName);
        $ttfDir = public_path('fonts/ttf');

        $systemFontMap = [
            'arial'           => ['regular' => 'arial-liberation-regular.ttf', 'bold' => 'arial-liberation-700.ttf'],
            'helvetica'       => ['regular' => 'arial-liberation-regular.ttf', 'bold' => 'arial-liberation-700.ttf'],
            'sans-serif'      => ['regular' => 'arial-liberation-regular.ttf', 'bold' => 'arial-liberation-700.ttf'],
            'times-new-roman' => ['regular' => 'times-new-roman-liberation-regular.ttf', 'bold' => 'times-new-roman-liberation-700.ttf'],
            'times'           => ['regular' => 'times-new-roman-liberation-regular.ttf', 'bold' => 'times-new-roman-liberation-700.ttf'],
            'serif'           => ['regular' => 'times-new-roman-liberation-regular.ttf', 'bold' => 'times-new-roman-liberation-700.ttf'],
            'verdana'         => ['regular' => 'verdana-dejavu-regular.ttf', 'bold' => 'verdana-dejavu-700.ttf'],
            'courier-new'     => ['regular' => 'courier-new-liberation-regular.ttf', 'bold' => 'courier-new-liberation-700.ttf'],
            'courier'         => ['regular' => 'courier-new-liberation-regular.ttf', 'bold' => 'courier-new-liberation-700.ttf'],
            'monospace'       => ['regular' => 'courier-new-liberation-regular.ttf', 'bold' => 'courier-new-liberation-700.ttf'],
            'georgia'         => ['regular' => 'georgia-dejavu-regular.ttf', 'bold' => 'georgia-dejavu-700.ttf'],
        ];

        if ($slug !== '' && isset($systemFontMap[$slug])) {
            $preferredKey = $isBold ? 'bold' : 'regular';
            $secondaryKey = $isBold ? 'regular' : 'bold';
            foreach ([$systemFontMap[$slug][$preferredKey], $systemFontMap[$slug][$secondaryKey]] as $sysFile) {
                $candidate = $ttfDir . '/' . $sysFile;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        if ($slug !== '' && is_dir($ttfDir)) {
            $matches = glob($ttfDir . '/' . $slug . '-*.ttf') ?: [];
            if (!empty($matches)) {
                $filtered = array_values(array_filter($matches, static function (string $path) use ($wantsItalic): bool {
                    $base = strtolower(basename($path));
                    $isItalicFile = str_contains($base, 'italic');
                    return $wantsItalic ? true : !$isItalicFile;
                }));
                if (empty($filtered)) {
                    $filtered = $matches;
                }

                if ($isBold) {
                    foreach ($filtered as $m) {
                        $b = strtolower(basename($m));
                        if (str_contains($b, '-700.ttf') || str_contains($b, '-bold.ttf')) {
                            return $m;
                        }
                    }
                    foreach ($filtered as $m) {
                        $b = strtolower(basename($m));
                        if (str_contains($b, '-regular.ttf') || str_contains($b, '-400.ttf')) {
                            return $m;
                        }
                    }
                } else {
                    foreach ($filtered as $m) {
                        $b = strtolower(basename($m));
                        if (str_contains($b, '-regular.ttf') || str_contains($b, '-400.ttf')) {
                            return $m;
                        }
                    }
                    foreach ($filtered as $m) {
                        $b = strtolower(basename($m));
                        if (str_contains($b, '-700.ttf') || str_contains($b, '-bold.ttf')) {
                            return $m;
                        }
                    }
                }

                return $filtered[0];
            }
        }

        $fallbacks = $isBold
            ? [
                public_path('fonts/ttf/montserrat-v31-latin-700.ttf'),
                public_path('fonts/ttf/montserrat-v31-latin-regular.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ]
            : [
                public_path('fonts/ttf/montserrat-v31-latin-regular.ttf'),
                public_path('fonts/ttf/montserrat-v31-latin-700.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ];

        foreach ($fallbacks as $fb) {
            if (is_file($fb)) {
                return $fb;
            }
        }

        return null;
    }

    /**
     * Berechnet die Kreisbogen-Geometrie (Position x, y und Rotation deg/rad je Buchstabe)
     * maßstabsunabhängig (sowohl für SVG in mm als auch für Raster-Bitmaps in px).
     *
     * @return array<int, array{char: string, x: float, y: float, deg: float, rad: float, charW: float}>
     */
    protected function computeCurvedTextArcChars(string $rawText, float $fontSizeUnit, float $curve, ?string $ttfPath = null): array
    {
        $curve = max(-100.0, min(100.0, $curve));
        if (abs($curve) < 0.5 || trim($rawText) === '' || $fontSizeUnit <= 0.0) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
        $hasTtf = $ttfPath && is_file($ttfPath) && function_exists('imagettfbbox');
        $refPt = 75.0; // entspricht 100px CSS/Canvas-Schriftgröße bei 96 DPI
        $guardW = 0.0;

        if ($hasTtf) {
            $guardBbox = @imagettfbbox($refPt, 0.0, $ttfPath, '||');
            if ($guardBbox) {
                $guardW = abs((float) $guardBbox[2] - (float) $guardBbox[0]);
            } else {
                $hasTtf = false;
            }
        }

        $measure = function (string $s) use ($hasTtf, $refPt, $ttfPath, $guardW, $fontSizeUnit): float {
            if ($s === '') {
                return 0.0;
            }
            if ($hasTtf && $ttfPath) {
                $bbox = @imagettfbbox($refPt, 0.0, $ttfPath, '|' . $s . '|');
                if ($bbox) {
                    $w100 = max(0.0, abs((float) $bbox[2] - (float) $bbox[0]) - $guardW);
                    return $w100 * ($fontSizeUnit / 100.0);
                }
            }
            return mb_strlen($s, 'UTF-8') * $fontSizeUnit * 0.58;
        };

        $lineWidths = array_map(fn ($l) => $measure($l !== '' ? $l : ' '), $lines);
        $maxLineW = max(array_merge([$fontSizeUnit * 1.5], $lineWidths));
        $thetaMax = (abs($curve) / 100.0) * M_PI;
        $rBase = $maxLineW / max(0.01, $thetaMax);
        $cy = ($curve > 0.0 ? 1.0 : -1.0) * $rBase * ((1.0 + cos($thetaMax / 2.0)) / 2.0);
        $lineHeight = $fontSizeUnit * 1.15;
        $numLines = count($lines);

        $chars = [];
        foreach ($lines as $lineIdx => $line) {
            $charsArr = preg_split('//u', (string) $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (empty($charsArr)) {
                continue;
            }

            $lineW = $lineWidths[$lineIdx] ?? 0.0;
            $deltaY = ($lineIdx - (($numLines - 1) / 2.0)) * $lineHeight;
            $rLine = max($fontSizeUnit * 0.8, $curve > 0.0 ? ($rBase - $deltaY) : ($rBase + $deltaY));

            $prefixStr = '';
            foreach ($charsArr as $ch) {
                $prefixW = $measure($prefixStr);
                $charW = $measure($ch);
                $prefixStr .= $ch;

                $u = $prefixW + ($charW / 2.0) - ($lineW / 2.0);
                $alpha = $u / $rLine;
                $x = $rLine * sin($alpha);
                $y = ($curve > 0.0) ? ($cy - $rLine * cos($alpha)) : ($cy + $rLine * cos($alpha));
                $rad = ($curve > 0.0) ? $alpha : -$alpha;
                $deg = $rad * (180.0 / M_PI);

                $chars[] = [
                    'char'  => $ch,
                    'x'     => $x,
                    'y'     => $y,
                    'deg'   => $deg,
                    'rad'   => $rad,
                    'charW' => $charW,
                ];
            }
        }

        return $chars;
    }

    /**
     * Rendert ein Kunden-Rasterbild in seinen exakten Stage-Koordinaten (x, y, size, rotation),
     * maskiert es hart an der Arbeitsbereich-Kontur (custom_points / circle / rect) mit
     * 100 % Transparenz (PNG Alpha = 0 / GD Alpha = 127) außerhalb der Kontur und
     * opakem Graustufenbild (PNG Alpha = 255 / GD Alpha = 0) innerhalb der Kontur.
     * Falls zusätzlich Textelemente ($texts) übergeben werden, werden diese mit ihrer exakten
     * .ttf-Schriftart und hellem Kontrast-Halo direkt in die maskierte Laser-Bitmap eingebrannt.
     *
     * Da xTool Studio SVG-<clipPath> auf eingebetteten <image>-Bitmaps ignoriert, verhindert
     * diese direkte Pixel-Maskierung zuverlässig, dass überstehende Bildbereiche (z. B. wenn
     * der Kunde sein Foto großzieht) auf den Rand, die Öse oder das Laserbett gelasert werden.
     *
     * @return array{pngData: string, xMm: float, yMm: float, widthMm: float, heightMm: float, pixelWidth: int, pixelHeight: int}|null
     */
    public function clipAndNormalizeRasterLogoForLaser(
        string $binaryData,
        ?string $ext,
        array $logo,
        float $widthMm,
        float $heightMm,
        array $workingAreaPolygonPct,
        array $texts = [],
        bool $knockoutOnlyTexts = false,
        ?array $photoProfile = null
    ): ?array {
        if (!function_exists('imagecreatefromstring') || $binaryData === '' || empty($workingAreaPolygonPct)) {
            return null;
        }

        $src = @imagecreatefromstring($binaryData);
        if (!$src) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);
            return null;
        }

        // Physische Position & Größe des Logos auf der Stage (mit echtem Seitenverhältnis analog zur 2D/3D-Vorschau)
        $cxMm = (((float) ($logo['x'] ?? 50)) / 100.0) * $widthMm;
        $cyMm = (((float) ($logo['y'] ?? 50)) / 100.0) * $heightMm;
        $rotDeg = (float) ($logo['rotation'] ?? 0);
        $logoW_mm = (((float) ($logo['size'] ?? 100)) / 500.0) * $widthMm;
        $logoH_mm = $logoW_mm * ($srcH / max(1, $srcW));

        // Bounding-Box des Arbeitsbereichs in Millimetern
        $xsMm = array_map(fn(array $p) => ($p['x'] / 100.0) * $widthMm, $workingAreaPolygonPct);
        $ysMm = array_map(fn(array $p) => ($p['y'] / 100.0) * $heightMm, $workingAreaPolygonPct);
        $minX_mm = max(0.0, min($xsMm));
        $maxX_mm = min($widthMm, max($xsMm));
        $minY_mm = max(0.0, min($ysMm));
        $maxY_mm = min($heightMm, max($ysMm));
        $areaW_mm = max(1.0, $maxX_mm - $minX_mm);
        $areaH_mm = max(1.0, $maxY_mm - $minY_mm);

        // Hochauflösendes Arbeitsbereich-Canvas (bis 60 px/mm, max ~1200 px Kantenlänge auch bei kleinen Anhängern)
        $pxPerMm = min(60.0, max(12.0, 1200.0 / max($areaW_mm, $areaH_mm)));
        $canvasW = max(64, (int) round($areaW_mm * $pxPerMm));
        $canvasH = max(64, (int) round($areaH_mm * $pxPerMm));

        // 1. Polygon-Maske des erlaubten Arbeitsbereichs zeichnen
        $maskImg = imagecreatetruecolor($canvasW, $canvasH);
        if (!$maskImg) {
            imagedestroy($src);
            return null;
        }
        $maskBlack = imagecolorallocate($maskImg, 0, 0, 0);
        $maskWhite = imagecolorallocate($maskImg, 255, 255, 255);
        imagefill($maskImg, 0, 0, $maskBlack);

        $flatPoly = [];
        foreach ($workingAreaPolygonPct as $pt) {
            $ptX_mm = ($pt['x'] / 100.0) * $widthMm;
            $ptY_mm = ($pt['y'] / 100.0) * $heightMm;
            $flatPoly[] = (int) round((($ptX_mm - $minX_mm) / $areaW_mm) * ($canvasW - 1));
            $flatPoly[] = (int) round((($ptY_mm - $minY_mm) / $areaH_mm) * ($canvasH - 1));
        }

        if (count($flatPoly) >= 6) {
            imagefilledpolygon($maskImg, $flatPoly, $maskWhite);
        } else {
            imagefill($maskImg, 0, 0, $maskWhite);
        }

        // 2. Kundenbild (ggf. gedreht) maßstabsgetreu in den Arbeitsbereich-Puffer projizieren
        $workSrc = $src;
        $workSrcW = $srcW;
        $workSrcH = $srcH;
        $workLogoW_mm = $logoW_mm;
        $workLogoH_mm = $logoH_mm;

        if (abs($rotDeg) > 0.05 && function_exists('imagerotate')) {
            imagealphablending($src, false);
            imagesavealpha($src, true);
            $rotTrans = imagecolorallocatealpha($src, 0, 0, 0, 127);
            $rotated = @imagerotate($src, -$rotDeg, $rotTrans);
            if ($rotated) {
                $workSrc = $rotated;
                $workSrcW = imagesx($rotated);
                $workSrcH = imagesy($rotated);
                $workLogoW_mm = $logoW_mm * ($workSrcW / max(1, $srcW));
                $workLogoH_mm = $logoH_mm * ($workSrcH / max(1, $srcH));
            }
        }

        $stageBuf = imagecreatetruecolor($canvasW, $canvasH);
        if (!$stageBuf) {
            if ($workSrc !== $src) {
                imagedestroy($workSrc);
            }
            imagedestroy($src);
            imagedestroy($maskImg);
            return null;
        }
        imagealphablending($stageBuf, false);
        imagesavealpha($stageBuf, true);
        $stageTrans = imagecolorallocatealpha($stageBuf, 0, 0, 0, 127);
        imagefill($stageBuf, 0, 0, $stageTrans);

        $dstX = (int) round((($cxMm - ($workLogoW_mm / 2.0) - $minX_mm) / $areaW_mm) * $canvasW);
        $dstY = (int) round((($cyMm - ($workLogoH_mm / 2.0) - $minY_mm) / $areaH_mm) * $canvasH);
        $dstW = max(1, (int) round(($workLogoW_mm / $areaW_mm) * $canvasW));
        $dstH = max(1, (int) round(($workLogoH_mm / $areaH_mm) * $canvasH));

        // Symmetrischer Mikro-Bleed-Ausgleich (max. 1,5 %) ausschließlich für nahezu randfüllende Fotos (>= 96 %),
        // ohne jemals das vom Kunden gewählte Bildzentrum ($origCenterX / $origCenterY) zu verschieben!
        $maxSnapGapX = max(1, (int) round($canvasW * 0.015));
        $maxSnapGapY = max(1, (int) round($canvasH * 0.015));
        $leftGap = $dstX;
        $rightGap = $canvasW - ($dstX + $dstW);
        $topGap = $dstY;
        $botGap = $canvasH - ($dstY + $dstH);

        $origCenterX = $dstX + ($dstW * 0.5);
        $origCenterY = $dstY + ($dstH * 0.5);

        $scaleBleedX = 1.0;
        if ($dstW >= $canvasW * 0.96 && $leftGap > 0 && $leftGap <= $maxSnapGapX && $rightGap > 0 && $rightGap <= $maxSnapGapX) {
            $halfSpanNeededX = max($origCenterX + 2.0, ($canvasW + 2.0) - $origCenterX);
            $scaleBleedX = ($halfSpanNeededX * 2.0) / max(1.0, (float) $dstW);
        }

        $scaleBleedY = 1.0;
        if ($dstH >= $canvasH * 0.96 && $topGap > 0 && $topGap <= $maxSnapGapY && $botGap > 0 && $botGap <= $maxSnapGapY) {
            $halfSpanNeededY = max($origCenterY + 2.0, ($canvasH + 2.0) - $origCenterY);
            $scaleBleedY = ($halfSpanNeededY * 2.0) / max(1.0, (float) $dstH);
        }

        $scaleBleed = max(1.0, $scaleBleedX, $scaleBleedY);
        if ($scaleBleed > 1.0 && $scaleBleed <= 1.04) {
            $newDstW = (int) ceil($dstW * $scaleBleed);
            $newDstH = (int) ceil($dstH * $scaleBleed);
            $dstX = (int) round($origCenterX - ($newDstW * 0.5));
            $dstY = (int) round($origCenterY - ($newDstH * 0.5));
            $dstW = $newDstW;
            $dstH = $newDstH;
        }

        imagecopyresampled($stageBuf, $workSrc, $dstX, $dstY, 0, 0, $dstW, $dstH, $workSrcW, $workSrcH);
        if ($workSrc !== $src) {
            imagedestroy($workSrc);
        }
        imagedestroy($src);

        // 3. Kontur-Maskierung + Normalisierung auf opake Graustufen (innerhalb) & Alpha-0 (außerhalb)
        $dst = imagecreatetruecolor($canvasW, $canvasH);
        if (!$dst) {
            imagedestroy($stageBuf);
            imagedestroy($maskImg);
            return null;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefill($dst, 0, 0, $transparent);

        $grayLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $grayLut[$g] = imagecolorallocatealpha($dst, $g, $g, $g, 0);
        }

        $tightMinX = $canvasW;
        $tightMaxX = -1;
        $tightMinY = $canvasH;
        $tightMaxY = -1;

        $scanStartX = max(0, $dstX);
        $scanEndX = min($canvasW - 1, $dstX + $dstW);
        $scanStartY = max(0, $dstY);
        $scanEndY = min($canvasH - 1, $dstY + $dstH);

        $hist = array_fill(0, 256, 0);
        $totalMaskedPixels = 0;
        $maxRgbSeen = 0;
        $strongMaskPixels = 0;

        for ($y = $scanStartY; $y <= $scanEndY; $y++) {
            for ($x = $scanStartX; $x <= $scanEndX; $x++) {
                // Außerhalb der grünen Arbeitsbereich-Kontur -> 100 % transparent (wird niemals gelasert!)
                if ((imagecolorat($maskImg, $x, $y) & 0xFF) === 0) {
                    continue;
                }

                $c = imagecolorat($stageBuf, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 126) {
                    continue;
                }

                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;

                if ($a === 0) {
                    $gray = (($r * 77) + ($g * 150) + ($b * 29) + 128) >> 8;
                } else {
                    $opacity = (127 - $a) / 127.0;
                    $lum = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
                    $gray = max(0, min(255, (int) round(($lum * $opacity) + (255.0 * (1.0 - $opacity)))));
                }

                imagesetpixel($dst, $x, $y, $grayLut[$gray]);
                $hist[$gray]++;
                $totalMaskedPixels++;
                if ($r > $maxRgbSeen) $maxRgbSeen = $r;
                if ($g > $maxRgbSeen) $maxRgbSeen = $g;
                if ($b > $maxRgbSeen) $maxRgbSeen = $b;
                if ($a < 16) $strongMaskPixels++;

                if ($x < $tightMinX) $tightMinX = $x;
                if ($x > $tightMaxX) $tightMaxX = $x;
                if ($y < $tightMinY) $tightMinY = $y;
                if ($y > $tightMaxY) $tightMaxY = $y;
            }
        }

        imagedestroy($stageBuf);

        // 3b. Automatische Laser-Porträt-Aufbereitung (HDR-Tiefenentzerrung + Mikrokontrast/Kantenschärfung nach xTool-Referenz):
        // Verhindert, dass großflächige Hautpartien (Stirn, Wangen, Hände) im Negativ zu blinden tiefschwarzen Blöcken (0)
        // zulaufen, hält Gesichts-Halbtöne fein abgestuft im Bereich 40..160 und schärft lokale Details (Haare, Augen,
        // Zähne, Konturen) über ein zweistufiges Integralbild (Medium-HDR + Fine Unsharp Mask) gestochen scharf nach.
        $distinctGreys = 0;
        for ($g = 0; $g <= 255; $g++) {
            if ($hist[$g] > 0) {
                $distinctGreys++;
            }
        }

        // 3a. Physik-kalibrierte Foto-Pipeline (LaserPhotoPreprocessor), sobald das Substrat-Profil bekannt ist:
        // Halbton-Fotos erhalten P2..P98 Tonwertspreizung, Unsharp Mask und die UV-Dot-Gain-Kurve der Polarität
        // (white_on_dark: Gamma 1,90 + Highlight-Deckel 78 %, dark_on_light: k^1,40 * 0,82).
        // Silhouetten / Strich-Logos bleiben randscharf unverändert (Schwellenwert).
        $photoMeta = ['class' => 'legacy', 'encoding' => null, 'polarity' => null, 'mean_density' => null];
        $skipAutoHalftone = is_array($photoProfile) && !empty($photoProfile['skip_halftone_processing']);

        if ($skipAutoHalftone && $totalMaskedPixels > 0 && $tightMaxX >= $tightMinX) {
            $photoPolarity = \App\Services\Laser\LaserPhotoPreprocessor::normalizePolarity(
                (string) ($photoProfile['polarity'] ?? ''),
                (bool) ($photoProfile['is_dark_surface'] ?? false)
            );
            $studioDither = strtolower(trim((string) ($photoProfile['studio_dither_mode'] ?? 'jarvis')));
            if ($studioDither === 'greyscale') {
                $studioDither = 'grayscale';
            }
            if ($studioDither === 'threshold') {
                for ($y = $tightMinY; $y <= $tightMaxY; $y++) {
                    for ($x = $tightMinX; $x <= $tightMaxX; $x++) {
                        $c = imagecolorat($dst, $x, $y);
                        if ((($c >> 24) & 0x7F) >= 126) {
                            continue;
                        }
                        $bitG = ($c & 0xFF) < 128 ? 0 : 255;
                        imagesetpixel($dst, $x, $y, $grayLut[$bitG]);
                    }
                }
            }
            $photoMeta = [
                'class'              => $studioDither === 'threshold' ? 'silhouette' : 'halftone',
                'encoding'           => 'studio_adjusted',
                'polarity'           => $photoPolarity,
                'studio_adjusted'    => true,
                'studio_dither_mode' => $studioDither,
                'mean_density'       => null,
            ];
        } elseif ($photoProfile !== null && $totalMaskedPixels > 0 && $tightMaxX >= $tightMinX) {
            $photoPolarity = \App\Services\Laser\LaserPhotoPreprocessor::normalizePolarity(
                (string) ($photoProfile['polarity'] ?? ''),
                (bool) ($photoProfile['is_dark_surface'] ?? false)
            );
            $photoEncoding = \App\Services\Laser\LaserPhotoPreprocessor::detectSourceEncoding(
                $maxRgbSeen,
                $totalMaskedPixels,
                $strongMaskPixels,
                $photoPolarity
            );
            $photoClass = \App\Services\Laser\LaserPhotoPreprocessor::classifyHistogram($hist, $totalMaskedPixels);
            $photoMeta = [
                'class'        => $photoClass,
                'encoding'     => $photoEncoding,
                'polarity'     => $photoPolarity,
                'mean_density' => null,
            ];

            if ($photoClass === 'halftone') {
                $profileDpi = isset($photoProfile['dpi']) && is_numeric($photoProfile['dpi'])
                    ? (int) $photoProfile['dpi']
                    : 600;
                $photoStats = app(\App\Services\Laser\LaserPhotoPreprocessor::class)->processHalftoneInPlace(
                    $dst,
                    $tightMinX,
                    $tightMinY,
                    $tightMaxX,
                    $tightMaxY,
                    $photoPolarity,
                    $photoEncoding,
                    $pxPerMm,
                    $profileDpi
                );
                $photoMeta['mean_density'] = $photoStats['mean_density'];
                $photoMeta['p_low'] = $photoStats['p_low'];
                $photoMeta['p_high'] = $photoStats['p_high'];
            }
        } elseif ($distinctGreys > 12 && $totalMaskedPixels > 100 && $tightMaxX >= $tightMinX) {
            $lowTarget = max(1, (int) round($totalMaskedPixels * 0.005));
            $highTarget = max(1, (int) round($totalMaskedPixels * 0.995));
            $cum = 0;
            $pLow = 0;
            $pHigh = 255;
            for ($g = 0; $g <= 255; $g++) {
                $cum += $hist[$g];
                if ($cum >= $lowTarget) {
                    $pLow = $g;
                    break;
                }
            }
            $cum = 0;
            for ($g = 0; $g <= 255; $g++) {
                $cum += $hist[$g];
                if ($cum >= $highTarget) {
                    $pHigh = $g;
                    break;
                }
            }

            if ($pHigh - $pLow >= 15) {
                $stretchMap = [];
                $span = (float) max(1, $pHigh - $pLow);
                for ($g = 0; $g <= 255; $g++) {
                    if ($g <= $pLow) {
                        $stretchMap[$g] = 36.0;
                    } elseif ($g >= $pHigh) {
                        $stretchMap[$g] = 246.0;
                    } else {
                        $norm = ($g - $pLow) / $span;
                        // Sanfte Gradation (Gamma 0.85) mit Sockel 36 statt 0, damit Stirn/Wangen/Hände niemals schwarz zulaufen
                        $stretchMap[$g] = 36.0 + (pow($norm, 0.85) * 210.0);
                    }
                }

                $bw = $tightMaxX - $tightMinX + 1;
                $bh = $tightMaxY - $tightMinY + 1;

                // Schritt 1: Sanfte Basis-Gradation (36..246, Gamma 0.85) in-place + C-Puffer ($baseImg) anlegen
                $baseImg = imagecreatetruecolor($bw, $bh);
                if ($baseImg) {
                    $bgNeutral = imagecolorallocate($baseImg, 128, 128, 128);
                    imagefill($baseImg, 0, 0, $bgNeutral);

                    $baseLut = [];
                    for ($g = 0; $g <= 255; $g++) {
                        $baseLut[$g] = imagecolorallocate($baseImg, $g, $g, $g);
                    }

                    for ($y = $tightMinY; $y <= $tightMaxY; $y++) {
                        $ly = $y - $tightMinY;
                        for ($x = $tightMinX; $x <= $tightMaxX; $x++) {
                            $c = imagecolorat($dst, $x, $y);
                            if ((($c >> 24) & 0x7F) >= 126) {
                                continue;
                            }
                            $mappedG = (int) round($stretchMap[$c & 0xFF]);
                            imagesetpixel($baseImg, $x - $tightMinX, $ly, $baseLut[$mappedG]);
                        }
                    }

                    // Schritt 2: Niederfrequentes lokales Umgebungslicht ($blurMed) als glatter Gauß-Puffer in C berechnen
                    $scaleDiv = 10;
                    $tinyW = max(8, (int) round($bw / $scaleDiv));
                    $tinyH = max(8, (int) round($bh / $scaleDiv));
                    $midW = max(16, (int) round($bw / 3));
                    $midH = max(16, (int) round($bh / 3));
                    $tinyImg = imagecreatetruecolor($tinyW, $tinyH);
                    $midImg = imagecreatetruecolor($midW, $midH);
                    $blurMed = imagecreatetruecolor($bw, $bh);

                    if ($tinyImg && $midImg && $blurMed) {
                        imagecopyresampled($tinyImg, $baseImg, 0, 0, 0, 0, $tinyW, $tinyH, $bw, $bh);
                        if (function_exists('imagefilter')) {
                            @imagefilter($tinyImg, IMG_FILTER_GAUSSIAN_BLUR);
                            @imagefilter($tinyImg, IMG_FILTER_GAUSSIAN_BLUR);
                        }
                        imagecopyresampled($midImg, $tinyImg, 0, 0, 0, 0, $midW, $midH, $tinyW, $tinyH);
                        if (function_exists('imagefilter')) {
                            @imagefilter($midImg, IMG_FILTER_GAUSSIAN_BLUR);
                        }
                        imagecopyresampled($blurMed, $midImg, 0, 0, 0, 0, $bw, $bh, $midW, $midH);
                        imagedestroy($tinyImg);
                        imagedestroy($midImg);

                        // Schritt 3: Lokale HDR-Tiefenaufhellung + 5x5 Unsharp-Mask Kantenschärfung (Haare, Augen, Zähne, Nähte)
                        $maxLx = $bw - 1;
                        $maxLy = $bh - 1;

                        for ($ly = 0; $ly < $bh; $ly++) {
                            $y = $tightMinY + $ly;
                            $lyM2 = max(0, $ly - 2);
                            $lyP2 = min($maxLy, $ly + 2);

                            for ($lx = 0; $lx < $bw; $lx++) {
                                $x = $tightMinX + $lx;
                                $c = imagecolorat($dst, $x, $y);
                                if ((($c >> 24) & 0x7F) >= 126) {
                                    continue;
                                }

                                $baseVal = (float) (imagecolorat($baseImg, $lx, $ly) & 0xFF);
                                $meanMed = (float) (imagecolorat($blurMed, $lx, $ly) & 0xFF);

                                $lxM2 = max(0, $lx - 2);
                                $lxP2 = min($maxLx, $lx + 2);
                                $n1 = imagecolorat($baseImg, $lxM2, $ly) & 0xFF;
                                $n2 = imagecolorat($baseImg, $lxP2, $ly) & 0xFF;
                                $n3 = imagecolorat($baseImg, $lx, $lyM2) & 0xFF;
                                $n4 = imagecolorat($baseImg, $lx, $lyP2) & 0xFF;
                                $meanFine = ($n1 + $n2 + $n3 + $n4) * 0.25;

                                // Großflächige dunkle Regionen (Stirn, Wangen, Hände mit meanMed < 100) sanft anheben,
                                // damit der Jarvis-Dither dort feine Schattierung statt eines schwarzen Blocks setzt.
                                $shadowLift = ($meanMed < 100.0) ? ((100.0 - $meanMed) * 0.48) : 0.0;

                                $diffMed = $baseVal - $meanMed;
                                $diffFine = $baseVal - $meanFine;

                                $enhanced = $baseVal + $shadowLift + ($diffMed * 0.55) + ($diffFine * 1.45);
                                $newG = max(22, min(248, (int) round($enhanced)));

                                imagesetpixel($dst, $x, $y, $grayLut[$newG]);
                            }
                        }

                        imagedestroy($blurMed);
                    } else {
                        if ($tinyImg) imagedestroy($tinyImg);
                        if ($midImg) imagedestroy($midImg);
                        if ($blurMed) imagedestroy($blurMed);
                    }

                    imagedestroy($baseImg);
                }
            }
        }

        // 4. Textelemente ($texts) maßstabsgetreu mit Kontrast-Halo in die maskierte Laser-Bitmap einbrennen
        if (!empty($texts) && function_exists('imagettftext')) {
            $glyphMask = imagecreatetruecolor($canvasW, $canvasH);
            $haloMask = imagecreatetruecolor($canvasW, $canvasH);

            if ($glyphMask && $haloMask) {
                $gmBlack = imagecolorallocate($glyphMask, 0, 0, 0);
                $gmWhite = imagecolorallocate($glyphMask, 255, 255, 255);
                imagefill($glyphMask, 0, 0, $gmBlack);

                $hmBlack = imagecolorallocate($haloMask, 0, 0, 0);
                $hmWhite = imagecolorallocate($haloMask, 255, 255, 255);
                imagefill($haloMask, 0, 0, $hmBlack);

                $textRendered = false;

                foreach ($texts as $textItem) {
                    $rawText = trim((string) ($textItem['text'] ?? ''));
                    if ($rawText === '' || $rawText === 'Text eingeben...') {
                        continue;
                    }

                    $ttfPath = $this->resolveTtfFontPath((string) ($textItem['font'] ?? 'Montserrat'));
                    if (!$ttfPath || !is_file($ttfPath)) {
                        continue;
                    }

                    $txMm = (((float) ($textItem['x'] ?? 50)) / 100.0) * $widthMm;
                    $tyMm = (((float) ($textItem['y'] ?? 50)) / 100.0) * $heightMm;
                    $txPx = (($txMm - $minX_mm) / $areaW_mm) * $canvasW;
                    $tyPx = (($tyMm - $minY_mm) / $areaH_mm) * $canvasH;

                    $rawSize = (float) ($textItem['size'] ?? 1.5);
                    $fontSizeMm = ($rawSize / 25.0) * $widthMm;
                    $fontSizePx = ($fontSizeMm / $areaW_mm) * $canvasW;
                    $ptSize = max(4.0, $fontSizePx * 0.75);

                    $tRotDeg = (float) ($textItem['rotation'] ?? 0);
                    $gdAngle = -$tRotDeg;
                    $rad = deg2rad($tRotDeg);
                    $cosA = cos($rad);
                    $sinA = sin($rad);

                    $align = (string) ($textItem['align'] ?? 'center');
                    $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
                    $numLines = count($lines);
                    $lineHeightPx = $fontSizePx * 1.15;
                    $totalBlockH = ($numLines - 1) * $lineHeightPx;

                    $haloRadius = max(2, min(8, (int) round($fontSizePx * 0.08)));
                    $curve = max(-100.0, min(100.0, (float) ($textItem['curve'] ?? 0)));
                    $baseOffsetPx = $this->computeCanvasTextBaselineOffsetPx($ptSize, $fontSizePx, $ttfPath);

                    if (abs($curve) >= 0.5) {
                        $curvedChars = $this->computeCurvedTextArcChars($rawText, $fontSizePx, $curve, $ttfPath);
                        foreach ($curvedChars as $c) {
                            $ch = (string) $c['char'];
                            if (trim($ch) === '') {
                                continue;
                            }

                            $chBbox = @imagettfbbox($ptSize, 0.0, $ttfPath, $ch);
                            if (!$chBbox) {
                                continue;
                            }

                            $localBaseX = -(($chBbox[0] + $chBbox[2]) / 2.0);
                            $localBaseY = $baseOffsetPx;

                            $cosC = cos((float) $c['rad']);
                            $sinC = sin((float) $c['rad']);
                            $dx = (float) $c['x'] + ($localBaseX * $cosC) - ($localBaseY * $sinC);
                            $dy = (float) $c['y'] + ($localBaseX * $sinC) + ($localBaseY * $cosC);

                            $drawX = (int) round($txPx + ($dx * $cosA) - ($dy * $sinA));
                            $drawY = (int) round($tyPx + ($dx * $sinA) + ($dy * $cosA));
                            $charGdAngle = -($tRotDeg + (float) $c['deg']);

                            for ($hy = -$haloRadius; $hy <= $haloRadius; $hy++) {
                                for ($hx = -$haloRadius; $hx <= $haloRadius; $hx++) {
                                    if (($hx * $hx) + ($hy * $hy) > ($haloRadius * $haloRadius) + 1) {
                                        continue;
                                    }
                                    @imagettftext($haloMask, $ptSize, $charGdAngle, $drawX + $hx, $drawY + $hy, $hmWhite, $ttfPath, $ch);
                                }
                            }

                            @imagettftext($glyphMask, $ptSize, $charGdAngle, $drawX, $drawY, $gmWhite, $ttfPath, $ch);
                            $textRendered = true;
                        }
                    } else {
                        foreach ($lines as $lineIdx => $lineText) {
                            $lineText = trim($lineText);
                            if ($lineText === '') {
                                continue;
                            }

                            $bbox = @imagettfbbox($ptSize, 0.0, $ttfPath, $lineText);
                            if (!$bbox) {
                                continue;
                            }

                            $lineCenterY = $tyPx - ($totalBlockH / 2.0) + ($lineIdx * $lineHeightPx);
                            $unrotY = $lineCenterY + $baseOffsetPx;

                            if ($align === 'left') {
                                $unrotX = $txPx - $bbox[0];
                            } elseif ($align === 'right') {
                                $unrotX = $txPx - $bbox[2];
                            } else {
                                $unrotX = $txPx - (($bbox[0] + $bbox[2]) / 2.0);
                            }

                            $dx = $unrotX - $txPx;
                            $dy = $unrotY - $tyPx;
                            $drawX = (int) round($txPx + ($dx * $cosA) - ($dy * $sinA));
                            $drawY = (int) round($tyPx + ($dx * $sinA) + ($dy * $cosA));

                            // Kontrast-Halo rund um die Schrift zeichnen
                            for ($hy = -$haloRadius; $hy <= $haloRadius; $hy++) {
                                for ($hx = -$haloRadius; $hx <= $haloRadius; $hx++) {
                                    if (($hx * $hx) + ($hy * $hy) > ($haloRadius * $haloRadius) + 1) {
                                        continue;
                                    }
                                    @imagettftext($haloMask, $ptSize, $gdAngle, $drawX + $hx, $drawY + $hy, $hmWhite, $ttfPath, $lineText);
                                }
                            }

                            // Eigentliche Glyphen zeichnen
                            @imagettftext($glyphMask, $ptSize, $gdAngle, $drawX, $drawY, $gmWhite, $ttfPath, $lineText);
                            $textRendered = true;
                        }
                    }
                }

                if ($textRendered) {
                    for ($y = 0; $y < $canvasH; $y++) {
                        for ($x = 0; $x < $canvasW; $x++) {
                            // Auch Text wird hart an der Arbeitsbereich-Kontur maskiert
                            if ((imagecolorat($maskImg, $x, $y) & 0xFF) === 0) {
                                continue;
                            }

                            $gVal = imagecolorat($glyphMask, $x, $y) & 0xFF;
                            $hVal = imagecolorat($haloMask, $x, $y) & 0xFF;

                            if ($gVal === 0 && $hVal === 0) {
                                continue;
                            }

                            if ($knockoutOnlyTexts) {
                                // Im SVG-Export wird der Text als echte schwarze Vektor-Ebene (<text fill="#000000">)
                                // ausgegeben. Im darunterliegenden Foto stanzen wir Glyphen + Halo als Reinweiß (255)
                                // frei, damit das Foto nicht über die gestochen scharfe Vektorschrift lasert.
                                if ($gVal > 20 || $hVal > 20) {
                                    $existing = imagecolorat($dst, $x, $y);
                                    $exAlpha = ($existing >> 24) & 0x7F;
                                    if ($exAlpha < 126) {
                                        $exGray = $existing & 0xFF;
                                        $koStrength = ($gVal > 20) ? 1.0 : min(0.95, ($hVal / 255.0) * 0.95);
                                        $blendedGray = (int) round(($exGray * (1.0 - $koStrength)) + (255.0 * $koStrength));
                                        imagesetpixel($dst, $x, $y, $grayLut[max(0, min(255, $blendedGray))]);
                                    }
                                }
                                continue;
                            }

                            if ($gVal > 20) {
                                // Buchstaben-Kern: Tiefschwarz (0) mit sanftem Anti-Aliasing am Glyphenrand
                                $glyphAlpha = min(1.0, $gVal / 220.0);
                                $gray = (int) round(255.0 * (1.0 - $glyphAlpha));
                                $gray = max(0, min(255, $gray));
                                imagesetpixel($dst, $x, $y, $grayLut[$gray]);
                            } elseif ($hVal > 20) {
                                // Kontrast-Halo: Helles Schutzband (255), damit sich die Schrift klar vom Foto abhebt
                                $existing = imagecolorat($dst, $x, $y);
                                $exAlpha = ($existing >> 24) & 0x7F;
                                $exGray = ($exAlpha >= 126) ? 255 : ($existing & 0xFF);
                                $haloStrength = min(0.88, ($hVal / 255.0) * 0.88);
                                $blendedGray = (int) round(($exGray * (1.0 - $haloStrength)) + (255.0 * $haloStrength));
                                $blendedGray = max(0, min(255, $blendedGray));
                                imagesetpixel($dst, $x, $y, $grayLut[$blendedGray]);
                            }

                            if ($x < $tightMinX) $tightMinX = $x;
                            if ($x > $tightMaxX) $tightMaxX = $x;
                            if ($y < $tightMinY) $tightMinY = $y;
                            if ($y > $tightMaxY) $tightMaxY = $y;
                        }
                    }
                }
            }

            if ($glyphMask) imagedestroy($glyphMask);
            if ($haloMask) imagedestroy($haloMask);
        }

        imagedestroy($maskImg);

        if ($tightMaxX < $tightMinX || $tightMaxY < $tightMinY) {
            imagedestroy($dst);
            return null;
        }

        $cropW = max(1, $tightMaxX - $tightMinX + 1);
        $cropH = max(1, $tightMaxY - $tightMinY + 1);

        $cropped = imagecreatetruecolor($cropW, $cropH);
        if (!$cropped) {
            imagedestroy($dst);
            return null;
        }
        // Außerhalb des Arbeitsbereich-Polygons (z. B. runder Kreis beim Seelenkristall oder Herz beim Anhänger)
        // zwingend 100 % transparent (Alpha 127) lassen, damit in xTool Studio keine weißen Quadrat-Ecken
        // über dem Kamerabild des Rohlings liegen. Innerhalb des Polygons sind alle Pixel 100 % opak (Alpha 0).
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $cropTrans = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
        imagefill($cropped, 0, 0, $cropTrans);
        imagecopy($cropped, $dst, 0, 0, $tightMinX, $tightMinY, $cropW, $cropH);
        imagedestroy($dst);

        ob_start();
        imagepng($cropped, null, 3);
        $pngData = ob_get_clean();
        imagedestroy($cropped);

        if (!is_string($pngData) || $pngData === '') {
            return null;
        }

        $outX_mm = $minX_mm + (($tightMinX / $canvasW) * $areaW_mm);
        $outY_mm = $minY_mm + (($tightMinY / $canvasH) * $areaH_mm);
        $outW_mm = ($cropW / $canvasW) * $areaW_mm;
        $outH_mm = ($cropH / $canvasH) * $areaH_mm;

        return [
            'pngData'     => $pngData,
            'xMm'         => $outX_mm,
            'yMm'         => $outY_mm,
            'widthMm'     => $outW_mm,
            'heightMm'    => $outH_mm,
            'pixelWidth'  => $cropW,
            'pixelHeight' => $cropH,
            'photoMeta'   => $photoMeta,
        ];
    }

    /**
     * Wandelt hochgeladene Rasterbilder (die im Shop-Konfigurator als schwarze Alpha-Maske
     * RGB(0,0,0) + Alpha gespeichert wurden) für den xTool-SVG-Export in ein opakes
     * Echte-Graustufen-PNG (RGB 0..255 auf weißem Hintergrund, ohne Alpha-Kanal) um.
     * Dadurch funktioniert "Invertieren" (z. B. für Klares Glas / Schiefer) in xTool Studio
     * sofort fehlerfrei, ohne dass das Bild weiß verschwindet oder zum schwarzen Block wird.
     *
     * @return array{0: string, 1: string}
     */
    protected function normalizeRasterImageForLaser(string $binaryData, ?string $ext = null): array
    {
        $fallbackType = preg_replace('/[^a-z0-9]/i', '', ($ext === 'jpg') ? 'jpeg' : ($ext ?: 'png')) ?: 'png';

        if (!function_exists('imagecreatefromstring') || $binaryData === '') {
            return [$binaryData, $fallbackType];
        }

        $src = @imagecreatefromstring($binaryData);
        if (!$src) {
            return [$binaryData, $fallbackType];
        }

        $width = imagesx($src);
        $height = imagesy($src);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($src);
            return [$binaryData, $fallbackType];
        }

        $dst = imagecreatetruecolor($width, $height);
        if (!$dst) {
            imagedestroy($src);
            return [$binaryData, $fallbackType];
        }

        // Weißer Hintergrund + Alpha-Blending aktivieren, keinen Alpha-Kanal speichern (echtes 24-Bit RGB Graustufenbild)
        imagealphablending($dst, false);
        imagesavealpha($dst, false);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);

        imagealphablending($dst, true);
        imagecopy($dst, $src, 0, 0, 0, 0, $width, $height);

        if (function_exists('imagefilter')) {
            @imagefilter($dst, IMG_FILTER_GRAYSCALE);
        }

        ob_start();
        imagepng($dst, null, 3);
        $pngData = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (!is_string($pngData) || $pngData === '') {
            return [$binaryData, $fallbackType];
        }

        return [$pngData, 'png'];
    }

    /**
     * Spiegelt bei flachen (nicht-zylindrischen) zweiseitigen Produkten für die Rückseite ('back')
     * die Arbeitsfläche horizontal (x -> 100 - x), da Durchbrüche wie das Herzloch beim Armband oder
     * das Pfotenloch bei der Hundemarke beim Umdrehen des Rohlings um die Y-Achse auf die gegenüberliegende
     * Seite wandern.
     */
    protected function applyBackSideMirrorToProductConfig(array $productConfig, string $side): array
    {
        if ($side !== 'back') {
            return $productConfig;
        }
        $overlayType = (string) ($productConfig['overlay_type'] ?? 'plane');
        if ($overlayType === 'cylinder') {
            return $productConfig;
        }

        if (isset($productConfig['area_left'], $productConfig['area_width'])) {
            $left = (float) $productConfig['area_left'];
            $width = (float) $productConfig['area_width'];
            $productConfig['area_left'] = round(max(0.0, min(100.0, 100.0 - $left - $width)), 4);
        }

        if (!empty($productConfig['custom_points']) && is_array($productConfig['custom_points'])) {
            $mirrored = [];
            foreach (array_reverse($productConfig['custom_points']) as $pt) {
                if (!is_array($pt) || !isset($pt['x'], $pt['y'])) {
                    continue;
                }
                $pt['x'] = round(100.0 - (float) $pt['x'], 4);
                $mirrored[] = $pt;
            }
            $productConfig['custom_points'] = $mirrored;
        }

        return $productConfig;
    }
}

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

        // Fallback & Aufbereitung für Schnell-Personalisierung / Neuling-Modus (custom_fields_data)
        $hasNonEmptyTexts = collect($texts)->contains(fn($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $isCustomFieldsConfig = (($config['personalization_mode'] ?? null) === 'custom_fields') || !empty($config['custom_fields_data']);
        if ($side !== 'back' && !empty($config['custom_fields_data']) && is_array($config['custom_fields_data']) && (!$hasNonEmptyTexts || ($isCustomFieldsConfig && empty($logos)))) {
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

            foreach ($config['custom_fields_data'] as $cfd) {
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
                if (!$url) continue;

                $localPath = null;
                $data = null;
                $ext = null;

                // Falls es eine Base64-URL ist
                if (str_starts_with($url, 'data:image')) {
                    if (preg_match('/^data:image\/([a-zA-Z0-9\+\-]+);base64,(.*)$/', $url, $matches)) {
                        $ext = strtolower($matches[1]);
                        if ($ext === 'svg+xml') $ext = 'svg';
                        if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                            $data = base64_decode($matches[2]);
                        }
                    }
                } else {
                    $urlPath = rawurldecode(parse_url($url, PHP_URL_PATH) ?? $url);

                    if (str_contains($urlPath, '..') || str_contains($urlPath, "\0") || str_contains($urlPath, '\\')) {
                        \App\Services\System\SystemSecurityService::logEvent(
                            'security:lfi_attempt_blocked',
                            'LFI / Path-Traversal im Laser-SVG-Export blockiert',
                            "Manipulierter Logo-Pfad im SVG-Export abgewiesen: " . substr((string) $urlPath, 0, 120),
                            'error',
                            ['order_item_id' => $item->id, 'path' => substr((string) $urlPath, 0, 120)]
                        );
                        continue;
                    }

                    if (str_contains($urlPath, '/storage/')) {
                        $relativePath = substr($urlPath, strpos($urlPath, '/storage/') + 9);
                        $localPath = storage_path('app/public/' . ltrim($relativePath, '/'));
                    } elseif (str_contains($urlPath, '/images/')) {
                        $relativePath = substr($urlPath, strpos($urlPath, '/images/') + 8);
                        $localPath = public_path('shopverwaltung/images/' . ltrim($relativePath, '/'));
                    } elseif (str_contains($urlPath, '/shop/')) {
                        $relativePath = substr($urlPath, strpos($urlPath, '/shop/') + 6);
                        $localPath = public_path('shop/' . ltrim($relativePath, '/'));
                    } else {
                        // Letzter Ausweg: Versuche, den Dateipfad relativ zum public-Ordner aufzulösen
                        $localPath = public_path(ltrim($urlPath, '/'));
                    }

                    // Fallback-Suche: Falls Datei nicht am direkten Pfad liegt (z.B. verschoben oder alter Pfad)
                    if (!$localPath || !file_exists($localPath)) {
                        $baseName = basename($urlPath);
                        $storageVectorPath = storage_path('app/public/produkte/configurator/vectors/' . $baseName);
                        $vectorPath = public_path('shop/product/configurator/vectors/' . $baseName);
                        if (file_exists($storageVectorPath)) {
                            $localPath = $storageVectorPath;
                        } elseif (file_exists($vectorPath)) {
                            $localPath = $vectorPath;
                        } else {
                            // Rekursive Suche in beiden Vektoren-Verzeichnissen (Storage & Public)
                            $searchDirs = [
                                storage_path('app/public/produkte/configurator/vectors'),
                                public_path('shop/product/configurator/vectors'),
                            ];
                            foreach ($searchDirs as $vectorsDir) {
                                if (File::isDirectory($vectorsDir)) {
                                    $allVectors = File::allFiles($vectorsDir);
                                    foreach ($allVectors as $vFile) {
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
                        $isWithinAllowedRoot = false;
                        if ($realLocalPath) {
                            foreach ($allowedRoots as $root) {
                                if (str_starts_with($realLocalPath, $root . DIRECTORY_SEPARATOR)) {
                                    $isWithinAllowedRoot = true;
                                    break;
                                }
                            }
                        }
                        $ext = strtolower(pathinfo($realLocalPath ?: $localPath, PATHINFO_EXTENSION));
                        if ($isWithinAllowedRoot && in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                            $data = file_get_contents($realLocalPath);
                        } else {
                            \App\Services\System\SystemSecurityService::logEvent(
                                'security:lfi_attempt_blocked',
                                'Unzulässiger Dateizugriff im SVG-Export blockiert',
                                "Zugriff auf Pfad außerhalb erlaubter Medienverzeichnisse verweigert.",
                                'error',
                                ['order_item_id' => $item->id, 'ext' => $ext]
                            );
                        }
                    } elseif (filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://')) {
                        $parsedHost = strtolower((string) parse_url($url, PHP_URL_HOST));
                        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
                        $resolvedIps = $parsedHost !== '' ? (@gethostbynamel($parsedHost) ?: []) : [];
                        $allIpsPublic = !empty($resolvedIps) && collect($resolvedIps)->every(
                            fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                        );
                        $candidateExt = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                        if ($parsedHost !== '' && $parsedHost === $appHost && $allIpsPublic && in_array($candidateExt, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                            try {
                                $remoteResp = \Illuminate\Support\Facades\Http::timeout(5)->withoutRedirecting()->get($url);
                                if ($remoteResp->successful() && strlen($remoteResp->body()) <= 10 * 1024 * 1024) {
                                    $ext = $candidateExt;
                                    $data = $remoteResp->body();
                                }
                            } catch (\Throwable) {
                            }
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
                        $clippedRaster = $this->clipAndNormalizeRasterLogoForLaser(
                            $data,
                            $ext,
                            $logo,
                            (float) $widthMm,
                            (float) $heightMm,
                            $workingAreaPolygonPct,
                            $textsForThisRaster
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
                    $baselineOffset = number_format($safeFontSize * 0.35, 4, '.', '');
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
                        $svg .= '    <text x="0" y="'.($startY + ($safeFontSize * 0.35)).'" font-family="'.$safeFontFamily.'" font-size="'.$safeFontSize.'" font-weight="bold" fill="#000000"'.$strokeAttr.' text-anchor="'.$textAnchor.'">'.htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8').'</text>' . "\n";
                        $startY += $lineHeight;
                    }
                }

                $svg .= '  </g>' . "\n";
            }
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

        foreach ($logos as $logoItem) {
            $url = $logoItem['url'] ?? '';
            if (empty($url)) continue;

            $data = null;
            $ext = null;

            if (str_starts_with($url, 'data:image/')) {
                if (preg_match('/^data:image\/(\w+)(?:\+\w+)?;base64,(.+)$/s', $url, $matches)) {
                    $ext = strtolower($matches[1]);
                    if ($ext !== 'svg') {
                        $data = base64_decode($matches[2]);
                    }
                }
            } else {
                $urlPath = rawurldecode(parse_url($url, PHP_URL_PATH) ?? $url);
                if (str_contains($urlPath, '..') || str_contains($urlPath, "\0") || str_contains($urlPath, '\\')) {
                    continue;
                }
                if (str_contains($urlPath, '/storage/')) {
                    $relativePath = substr($urlPath, strpos($urlPath, '/storage/') + 9);
                    $localPath = storage_path('app/public/' . ltrim($relativePath, '/'));
                } elseif (str_contains($urlPath, '/images/')) {
                    $relativePath = substr($urlPath, strpos($urlPath, '/images/') + 8);
                    $localPath = public_path('shopverwaltung/images/' . ltrim($relativePath, '/'));
                } elseif (str_contains($urlPath, '/shop/')) {
                    $relativePath = substr($urlPath, strpos($urlPath, '/shop/') + 6);
                    $localPath = public_path('shop/' . ltrim($relativePath, '/'));
                } else {
                    $localPath = public_path(ltrim($urlPath, '/'));
                }

                if ($localPath && file_exists($localPath) && is_file($localPath)) {
                    $realLocalPath = realpath($localPath);
                    $allowedRoots = array_filter([
                        realpath(storage_path('app/public')),
                        realpath(public_path('shop')),
                        realpath(public_path('shopverwaltung/images')),
                    ]);
                    $isWithinAllowedRoot = false;
                    if ($realLocalPath) {
                        foreach ($allowedRoots as $root) {
                            if (str_starts_with($realLocalPath, $root . DIRECTORY_SEPARATOR)) {
                                $isWithinAllowedRoot = true;
                                break;
                            }
                        }
                    }
                    $ext = strtolower(pathinfo($realLocalPath ?: $localPath, PATHINFO_EXTENSION));
                    if ($isWithinAllowedRoot && in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                        $data = file_get_contents($realLocalPath);
                    }
                }
            }

            if ($data !== null && $ext !== 'svg') {
                $clipped = $this->clipAndNormalizeRasterLogoForLaser(
                    $data,
                    $ext,
                    $logoItem,
                    (float) $widthMm,
                    (float) $heightMm,
                    $workingAreaPolygonPct,
                    is_array($texts) ? $texts : []
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
        float $angleDeg = 0.0
    ) {
        $this->authorizeLaserExportAccess($itemId);

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $xcsData = $cockpit->generateXcsProjectBytes($itemId, (string) $side, $centerXMm, $centerYMm, $angleDeg);

        return response()->streamDownload(function () use ($xcsData) {
            echo $xcsData['bytes'];
        }, $xcsData['filename'], [
            'Content-Type' => 'application/octet-stream',
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
        float $angleDeg = 0.0
    ) {
        $this->authorizeLaserExportAccess($itemId);

        $cockpit = app(\App\Services\Laser\XtoolLaserCockpitService::class);
        $xfData = $cockpit->generateXfPackageBytes($itemId, (string) $side, $centerXMm, $centerYMm, $angleDeg);

        return response()->streamDownload(function () use ($xfData) {
            echo $xfData['bytes'];
        }, $xfData['filename'], [
            'Content-Type' => 'application/x-tar',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function resolveAndAuthorizeLaserItem($itemId, bool $skipAuthCheck = false)
    {
        if ($itemId instanceof OrderOrderItem || $itemId instanceof \App\Models\Order\OrderQuoteRequestItem) {
            $item = $itemId;
        } else {
            $item = OrderOrderItem::with(['product', 'order'])->find($itemId)
                ?? \App\Models\Order\OrderQuoteRequestItem::with(['product', 'quoteRequest'])->findOrFail($itemId);
        }

        if (!$skipAuthCheck) {
            $this->authorizeLaserExportAccess($item);
        }

        return $item;
    }

    protected function authorizeLaserExportAccess($itemOrId): void
    {
        $item = ($itemOrId instanceof OrderOrderItem || $itemOrId instanceof \App\Models\Order\OrderQuoteRequestItem)
            ? $itemOrId
            : (OrderOrderItem::with('order')->find($itemOrId)
                ?? \App\Models\Order\OrderQuoteRequestItem::with('quoteRequest')->findOrFail($itemOrId));

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

        $productConfig = $product
            ? (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : ($product->configurator_settings ?? []))
            : [];

        // Aufbereitung für Schnell-Personalisierung / Neuling-Modus (custom_fields_data)
        $hasNonEmptyTexts = collect($texts)->contains(fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '');
        $isCustomFieldsConfig = (($config['personalization_mode'] ?? null) === 'custom_fields') || !empty($config['custom_fields_data']);
        if ($side !== 'back' && !empty($config['custom_fields_data']) && is_array($config['custom_fields_data']) && (!$hasNonEmptyTexts || ($isCustomFieldsConfig && empty($logos)))) {
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

            foreach ($config['custom_fields_data'] as $cfd) {
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

            if (!empty($customLines)) {
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
        } elseif (empty($texts) && !empty($config['text']) && trim((string) $config['text']) !== '') {
            $texts = [[
                'text'     => trim((string) $config['text']),
                'font'     => (string) ($config['font'] ?? 'Montserrat'),
                'align'    => 'center',
                'x'        => 50,
                'y'        => 50,
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

        $calibration = $this->resolvePhysicalBlankCalibration($product, $productConfig, $config, $overlayType === 'cylinder');
        $widthMm = (float) $calibration['widthMm'];
        $heightMm = (float) $calibration['heightMm'];

        $workingAreaPolygonPct = $this->buildWorkingAreaPolygonPercent($productConfig);
        if (!empty($calibration['plateBoxPct'])) {
            [$workingAreaPolygonPct, $logos, $texts] = $this->mapShopConfigOntoPhysicalBlank(
                $calibration['plateBoxPct'],
                $workingAreaPolygonPct,
                $logos,
                $texts
            );
        }

        // Exakt EINE 1:1 Außenkontur des physischen Rohlings in Millimetern (kein doppelter Rahmen!)
        $ghostContourDPath = $this->buildSingleOuterBlankContourDPath(
            (string) ($calibration['contourShape'] ?? 'auto'),
            $widthMm,
            $heightMm,
            $workingAreaPolygonPct,
            $productConfig
        );

        $rendered = $this->renderFullStageLaserPng($widthMm, $heightMm, $workingAreaPolygonPct, $logos, $texts, $item);

        return [
            'item'                  => $item,
            'product'               => $product,
            'side'                  => $side,
            'widthMm'               => round($widthMm, 3),
            'heightMm'              => round($heightMm, 3),
            'workingAreaPolygonPct' => $workingAreaPolygonPct,
            'ghostContourDPath'     => $ghostContourDPath,
            'stageEngravePngBytes'  => $rendered['pngBytes'],
            'stagePixelWidth'       => $rendered['pixelWidth'],
            'stagePixelHeight'      => $rendered['pixelHeight'],
            'previewDataUrl'        => 'data:image/png;base64,' . base64_encode($rendered['pngBytes']),
        ];
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
        $overlayType = (string) ($productConfig['overlay_type'] ?? 'plane');
        $slug = strtolower(trim((string) ($product->slug ?? '')));
        $templatePkg = strtolower(trim((string) ($productConfig['template_package'] ?? '')));

        $catalogProfiles = [
            'seelen-kristall' => [
                'widthMm'      => 161.5,
                'heightMm'     => 161.5,
                'plateBoxPct'  => ['left' => 10.88, 'top' => 6.48, 'width' => 78.24, 'height' => 78.24],
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
                $plateBoxPct = [
                    'left'   => (float) ($pb['left'] ?? 0.0),
                    'top'    => (float) ($pb['top'] ?? 0.0),
                    'width'  => (float) $pb['width'],
                    'height' => (float) $pb['height'],
                ];
            }
        } elseif ($matchedProfile !== null && !empty($matchedProfile['plateBoxPct'])) {
            $plateBoxPct = $matchedProfile['plateBoxPct'];
        }

        if ($overlayType === 'cylinder') {
            if ($unrollCylinder) {
                $widthMm = $widthMm * M_PI;
            }
            $plateBoxPct = null;
        } elseif ($overlayType === 'plane' && $plateBoxPct === null && in_array($productConfig['area_shape'] ?? '', ['custom', 'circle'], true)) {
            $stageSpanMm = (($productConfig['area_shape'] ?? '') === 'circle')
                ? min($widthMm, $heightMm)
                : max($widthMm, $heightMm);
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
            $innerPct = [
                [50.0, 59.45], [51.13, 58.73], [52.23, 58.01], [53.34, 57.29], [54.54, 56.57], [55.82, 55.84],
                [57.26, 55.12], [58.83, 54.52], [60.42, 54.19], [62.68, 54.48], [64.54, 55.02], [66.13, 55.74],
                [67.49, 56.57], [68.67, 57.6], [69.63, 58.73], [70.38, 59.97], [70.95, 61.31], [71.41, 62.75],
                [71.7, 64.3], [71.86, 65.85], [71.86, 67.39], [71.66, 68.94], [71.35, 70.59], [70.87, 72.34],
                [70.21, 74.1], [69.35, 75.95], [68.31, 77.81], [67.12, 79.67], [65.72, 81.52], [64.07, 83.38],
                [62.27, 85.23], [60.21, 87.09], [57.94, 88.95], [55.57, 90.7], [53.47, 92.25], [51.55, 93.55],
                [50.0, 94.52], [48.45, 93.55], [46.53, 92.25], [44.43, 90.7], [42.06, 88.95], [39.79, 87.09],
                [37.73, 85.23], [35.93, 83.38], [34.28, 81.52], [32.88, 79.67], [31.68, 77.81], [30.65, 75.95],
                [29.79, 74.1], [29.13, 72.34], [28.65, 70.59], [28.34, 68.94], [28.14, 67.39], [28.14, 65.85],
                [28.3, 64.3], [28.59, 62.75], [29.04, 61.31], [29.62, 59.97], [30.37, 58.73], [31.33, 57.6],
                [32.51, 56.57], [33.87, 55.74], [35.46, 55.02], [37.32, 54.48], [39.58, 54.19], [41.17, 54.52],
                [42.74, 55.12], [44.18, 55.84], [45.46, 56.57], [46.66, 57.29], [47.77, 58.01], [48.87, 58.73],
            ];
            $innerPx = array_map(static fn (array $p): array => [$p[0] * 8.0, $p[1] * 8.0], $innerPct);
            $n = count($innerPx);
            $rawPts = [];
            for ($i = 0; $i < $n; $i++) {
                $prev = $innerPx[($i - 1 + $n) % $n];
                $curr = $innerPx[$i];
                $next = $innerPx[($i + 1) % $n];
                $dx = $next[0] - $prev[0];
                $dy = $next[1] - $prev[1];
                $len = hypot($dx, $dy) ?: 1.0;
                $ox = $curr[0] + ($dy / $len) * 16.0;
                $oy = $curr[1] + (-$dx / $len) * 15.8;
                if ($i === 0) {
                    $ox = 400.0;
                    $oy = 445.0;
                } elseif ($i === 36) {
                    $ox = 400.0;
                    $oy = 772.0;
                }
                $rawPts[] = [max(207.0, min(593.0, $ox)), max(418.0, min(772.0, $oy))];
            }
            $ptsMm = $normalizePixelPointsToMm($rawPts);
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
            foreach ($workingAreaPolygonPct as $pt) {
                $ptsMm[] = [
                    (((float) ($pt['x'] ?? 0.0)) / 100.0) * $widthMm,
                    (((float) ($pt['y'] ?? 0.0)) / 100.0) * $heightMm,
                ];
            }
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
     * maßstabsgetreu auf ein transparentes Stage-PNG (widthMm x heightMm).
     *
     * @return array{pngBytes: string, pixelWidth: int, pixelHeight: int}
     */
    protected function renderFullStageLaserPng(
        float $widthMm,
        float $heightMm,
        array $workingAreaPolygonPct,
        array $logos,
        array $texts,
        $item = null
    ): array {
        $minDimMm = max(1.0, min($widthMm, $heightMm));
        $maxDimMm = max(1.0, max($widthMm, $heightMm));
        $pxPerMm = min(32.0, max(10.0, max(800.0 / $maxDimMm, 132.0 / $minDimMm)));
        $stageW = max(32, (int) round($widthMm * $pxPerMm));
        $stageH = max(32, (int) round($heightMm * $pxPerMm));

        $canvas = imagecreatetruecolor($stageW, $stageH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $trans = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $trans);

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

        $textsBakedIntoPhoto = false;
        $lastRasterIdx = null;
        foreach ($logos as $idx => $lCand) {
            $u = (string) ($lCand['url'] ?? $lCand['value'] ?? $lCand['src'] ?? '');
            if ($u !== '' && !str_ends_with(strtolower((string) parse_url($u, PHP_URL_PATH)), '.svg') && !str_starts_with($u, 'data:image/svg')) {
                $lastRasterIdx = $idx;
            }
        }

        foreach ($logos as $logoIdx => $logo) {
            $url = (string) ($logo['url'] ?? $logo['value'] ?? $logo['src'] ?? '');
            if ($url === '') continue;

            [$rawBytes, $ext] = $this->loadSafeLogoBytesAndExt($url, $item);
            if ($rawBytes === null) continue;

            if ($ext === 'svg') {
                $this->drawSvgLogoOntoStageCanvas($canvas, $rawBytes, $logo, $widthMm, $heightMm, $stageW, $stageH);
            } else {
                $textsForRaster = ($logoIdx === $lastRasterIdx) ? $texts : [];
                $clipped = $this->clipAndNormalizeRasterLogoForLaser(
                    $rawBytes,
                    $ext,
                    $logo,
                    $widthMm,
                    $heightMm,
                    $workingAreaPolygonPct,
                    $textsForRaster
                );
                if ($clipped !== null) {
                    if (!empty($textsForRaster)) {
                        $textsBakedIntoPhoto = true;
                    }
                    $partImg = @imagecreatefromstring($clipped['pngData']);
                    if ($partImg) {
                        $dstX = (int) round(($clipped['xMm'] / $widthMm) * $stageW);
                        $dstY = (int) round(($clipped['yMm'] / $heightMm) * $stageH);
                        $dstW = max(1, (int) round(($clipped['widthMm'] / $widthMm) * $stageW));
                        $dstH = max(1, (int) round(($clipped['heightMm'] / $heightMm) * $stageH));
                        imagealphablending($canvas, true);
                        imagecopyresampled($canvas, $partImg, $dstX, $dstY, 0, 0, $dstW, $dstH, imagesx($partImg), imagesy($partImg));
                        imagealphablending($canvas, false);
                        imagedestroy($partImg);
                    }
                }
            }
        }

        // Falls Texte noch nicht in ein Foto eingebrannt wurden: direkt als gestochen scharfe schwarze TTF-Schrift zeichnen
        if (!empty($texts) && !$textsBakedIntoPhoto && function_exists('imagettftext')) {
            imagealphablending($canvas, true);
            $blackOpaque = imagecolorallocatealpha($canvas, 0, 0, 0, 0);

            foreach ($texts as $textItem) {
                $rawText = trim((string) ($textItem['text'] ?? ''));
                if ($rawText === '' || $rawText === 'Text eingeben...') continue;

                $ttfPath = $this->resolveTtfFontPath((string) ($textItem['font'] ?? 'Montserrat'));
                if (!$ttfPath || !is_file($ttfPath)) continue;

                $txPx = (((float) ($textItem['x'] ?? 50)) / 100.0) * $stageW;
                $tyPx = (((float) ($textItem['y'] ?? 50)) / 100.0) * $stageH;
                $rawSize = (float) ($textItem['size'] ?? 1.0);
                $fontSizeMm = ($rawSize / 25.0) * $widthMm;
                $fontSizePx = ($fontSizeMm / $widthMm) * $stageW;
                $ptSize = max(5.0, $fontSizePx * 0.75);

                $tRotDeg = (float) ($textItem['rotation'] ?? 0);
                $gdAngle = -$tRotDeg;
                $rad = deg2rad($tRotDeg);
                $cosA = cos($rad);
                $sinA = sin($rad);
                $align = (string) ($textItem['align'] ?? 'center');
                $curve = max(-100.0, min(100.0, (float) ($textItem['curve'] ?? 0)));

                if (abs($curve) >= 0.5) {
                    $curvedChars = $this->computeCurvedTextArcChars($rawText, $fontSizePx, $curve, $ttfPath);
                    foreach ($curvedChars as $c) {
                        $ch = (string) $c['char'];
                        if (trim($ch) === '') continue;
                        $chBbox = @imagettfbbox($ptSize, 0.0, $ttfPath, $ch);
                        if (!$chBbox) continue;
                        $localBaseX = -(($chBbox[0] + $chBbox[2]) / 2.0);
                        $localBaseY = $fontSizePx * 0.33;
                        $cosC = cos((float) $c['rad']);
                        $sinC = sin((float) $c['rad']);
                        $dx = (float) $c['x'] + ($localBaseX * $cosC) - ($localBaseY * $sinC);
                        $dy = (float) $c['y'] + ($localBaseX * $sinC) + ($localBaseY * $cosC);
                        $drawX = (int) round($txPx + ($dx * $cosA) - ($dy * $sinA));
                        $drawY = (int) round($tyPx + ($dx * $sinA) + ($dy * $cosA));
                        $charGdAngle = -($tRotDeg + (float) $c['deg']);
                        @imagettftext($canvas, $ptSize, $charGdAngle, $drawX, $drawY, $blackOpaque, $ttfPath, $ch);
                    }
                } else {
                    $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
                    $numLines = count($lines);
                    $lineHeightPx = $fontSizePx * 1.15;
                    $totalBlockH = ($numLines - 1) * $lineHeightPx;

                    foreach ($lines as $lineIdx => $lineText) {
                        $lineText = trim($lineText);
                        if ($lineText === '') continue;
                        $bbox = @imagettfbbox($ptSize, 0.0, $ttfPath, $lineText);
                        if (!$bbox) continue;

                        $lineCenterY = $tyPx - ($totalBlockH / 2.0) + ($lineIdx * $lineHeightPx);
                        $unrotY = $lineCenterY + ($fontSizePx * 0.33);
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
                        @imagettftext($canvas, $ptSize, $gdAngle, $drawX, $drawY, $blackOpaque, $ttfPath, $lineText);
                    }
                }
            }
            imagealphablending($canvas, false);
        }

        // Hart an der Arbeitsbereich-Maske (workingAreaPolygonPct) clippen
        for ($y = 0; $y < $stageH; $y++) {
            for ($x = 0; $x < $stageW; $x++) {
                if ((imagecolorat($maskImg, $x, $y) & 0xFF) === 0) {
                    imagesetpixel($canvas, $x, $y, $trans);
                }
            }
        }
        imagedestroy($maskImg);

        ob_start();
        imagepng($canvas, null, 6);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($canvas);

        return [
            'pngBytes'    => $pngBytes,
            'pixelWidth'  => $stageW,
            'pixelHeight' => $stageH,
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
            $relativePath = substr($urlPath, strpos($urlPath, '/storage/') + 9);
            $localPath = storage_path('app/public/' . ltrim($relativePath, '/'));
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
     * Zeichnet ein SVG-Vektorsymbol (z. B. herz.svg, pfotenabdruck.svg, stern.svg) maßstabsgetreu in das GD-Canvas.
     */
    protected function drawSvgLogoOntoStageCanvas($canvas, string $svgRaw, array $logo, float $widthMm, float $heightMm, int $stageW, int $stageH): void
    {
        $cleanSvg = \App\Support\SafeHtmlAndSvgSanitizer::sanitizeSvg($svgRaw);
        if ($cleanSvg === '') {
            return;
        }

        $vX = 0.0; $vY = 0.0; $viewW = 24.0; $viewH = 24.0;
        if (preg_match('/<svg[^>]*viewBox="([^"]+)"/i', $cleanSvg, $m)) {
            $parts = preg_split('/[\s,]+/', trim($m[1])) ?: [];
            if (count($parts) >= 4) {
                $vX = (float) $parts[0];
                $vY = (float) $parts[1];
                $viewW = max(1.0, (float) $parts[2]);
                $viewH = max(1.0, (float) $parts[3]);
            }
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

        imagealphablending($canvas, true);
        $black = imagecolorallocatealpha($canvas, 0, 0, 0, 0);

        if (preg_match_all('/\bd="([^"]+)"/i', $cleanSvg, $pathMatches)) {
            foreach ($pathMatches[1] as $dStr) {
                $subPolys = $this->sampleSvgPathToPolygons($dStr);
                foreach ($subPolys as $polyPts) {
                    if (count($polyPts) < 3) continue;
                    $flat = [];
                    foreach ($polyPts as [$px, $py]) {
                        $lx = ($px - $vbCenterX) * $scale;
                        $ly = ($py - $vbCenterY) * $scale;
                        $rx = $cxPx + ($lx * $cosR) - ($ly * $sinR);
                        $ry = $cyPx + ($lx * $sinR) + ($ly * $cosR);
                        $flat[] = (int) round($rx);
                        $flat[] = (int) round($ry);
                    }
                    if (count($flat) >= 6) {
                        @imagefilledpolygon($canvas, $flat, $black);
                    }
                }
            }
        }
        imagealphablending($canvas, false);
    }

    /**
     * Konvertiert einen SVG-Pfad-String (M, L, H, V, C, S, Q, Z in absolut/relativ) in geschlossene Punkt-Polygone.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    protected function sampleSvgPathToPolygons(string $dPath): array
    {
        preg_match_all('/([MmLlHhVvCcSsQqZz])|(-?\d*\.?\d+(?:e[-+]?\d+)?)/i', $dPath, $tokens);
        if (empty($tokens[0])) {
            return [];
        }

        $polys = [];
        $currentPoly = [];
        $cx = 0.0; $cy = 0.0;
        $startX = 0.0; $startY = 0.0;
        $prevCtrlX = 0.0; $prevCtrlY = 0.0;
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
                $current = $pts;
                for ($pass = 0; $pass < 3; $pass++) {
                    $smoothed = [];
                    $n = count($current);
                    for ($i = 0; $i < $n; $i++) {
                        $pPrev = $current[($i - 1 + $n) % $n];
                        $pCurr = $current[$i];
                        $pNext = $current[($i + 1) % $n];

                        $v1x = $pCurr['x'] - $pPrev['x'];
                        $v1y = $pCurr['y'] - $pPrev['y'];
                        $v2x = $pNext['x'] - $pCurr['x'];
                        $v2y = $pNext['y'] - $pCurr['y'];
                        $l1 = hypot($v1x, $v1y);
                        $l2 = hypot($v2x, $v2y);
                        $cosA = ($l1 > 0.001 && $l2 > 0.001) ? (($v1x * $v2x + $v1y * $v2y) / ($l1 * $l2)) : 1.0;

                        if ($cosA < 0.55) {
                            $smoothed[] = $pCurr;
                        } else {
                            $t1 = $l1 > 0.001 ? min(0.25, 1.2 / $l1) : 0.25;
                            $t2 = $l2 > 0.001 ? min(0.25, 1.2 / $l2) : 0.25;
                            $smoothed[] = [
                                'x' => (1.0 - $t1) * $pCurr['x'] + $t1 * $pPrev['x'],
                                'y' => (1.0 - $t1) * $pCurr['y'] + $t1 * $pPrev['y'],
                            ];
                            $smoothed[] = [
                                'x' => (1.0 - $t2) * $pCurr['x'] + $t2 * $pNext['x'],
                                'y' => (1.0 - $t2) * $pCurr['y'] + $t2 * $pNext['y'],
                            ];
                        }
                    }
                    $current = $smoothed;
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
     * Löst einen Konfigurator-Schriftnamen (z. B. "Dancing Script", "Playfair Display")
     * auf die lokale .ttf-Schriftdatei in public/fonts/ttf/ auf.
     */
    protected function resolveTtfFontPath(string $fontFamily): ?string
    {
        $slug = \Illuminate\Support\Str::slug($fontFamily);
        $ttfDir = public_path('fonts/ttf');

        if ($slug !== '' && is_dir($ttfDir)) {
            $matches = glob($ttfDir . '/' . $slug . '-*.ttf') ?: [];
            if (!empty($matches)) {
                foreach ($matches as $m) {
                    if (str_contains(basename($m), '-700.ttf') || str_contains(basename($m), '-bold.ttf')) {
                        return $m;
                    }
                }
                foreach ($matches as $m) {
                    if (str_contains(basename($m), '-regular.ttf')) {
                        return $m;
                    }
                }
                return $matches[0];
            }
        }

        $fallbacks = [
            public_path('fonts/ttf/montserrat-v31-latin-700.ttf'),
            public_path('fonts/ttf/montserrat-v31-latin-regular.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
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
        bool $knockoutOnlyTexts = false
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

        // Kanten-Bleed-Ausgleich für Vollflächen-Fotos:
        // Wenn der Kunde ein Foto fast über die gesamte Breite/Höhe des Arbeitsbereichs gezogen hat,
        // verdeckt im 2D/3D-Editor oft der 2px Auswahlrahmen oder der dunkle Rohling-Untergrund einen minimalen
        // Spalt (z. B. 0,4 % / wenige Pixel) am äußersten linken/rechten Bogen. Ohne Ausgleich würde die
        // rechteckige Bildkante den äußeren Herzbogen im SVG senkrecht abschneiden.
        $maxSnapGapX = (int) round($canvasW * 0.065);
        $maxSnapGapY = (int) round($canvasH * 0.065);
        $leftGap = $dstX;
        $rightGap = $canvasW - ($dstX + $dstW);
        $topGap = $dstY;
        $botGap = $canvasH - ($dstY + $dstH);

        $targetLeft = ($leftGap > 0 && $leftGap <= $maxSnapGapX && $dstW >= $canvasW * 0.85) ? -2 : $dstX;
        $targetRight = ($rightGap > 0 && $rightGap <= $maxSnapGapX && $dstW >= $canvasW * 0.85) ? ($canvasW + 2) : ($dstX + $dstW);
        $targetTop = ($topGap > 0 && $topGap <= $maxSnapGapY && $dstH >= $canvasH * 0.85) ? -2 : $dstY;
        $targetBot = ($botGap > 0 && $botGap <= $maxSnapGapY && $dstH >= $canvasH * 0.85) ? ($canvasH + 2) : ($dstY + $dstH);

        $neededW = $targetRight - $targetLeft;
        $neededH = $targetBot - $targetTop;
        $scaleBleed = max(1.0, $neededW / max(1, $dstW), $neededH / max(1, $dstH));

        if ($scaleBleed > 1.0 && $scaleBleed <= 1.15) {
            $newDstW = (int) ceil($dstW * $scaleBleed);
            $newDstH = (int) ceil($dstH * $scaleBleed);
            $centerX = ($targetLeft + $targetRight) * 0.5;
            $centerY = ($targetTop + $targetBot) * 0.5;
            $dstX = (int) round($centerX - ($newDstW * 0.5));
            $dstY = (int) round($centerY - ($newDstH * 0.5));
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

                $opacity = (127 - $a) / 127.0;
                $lum = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
                $gray = (int) round(($lum * $opacity) + (255.0 * (1.0 - $opacity)));
                $gray = max(0, min(255, $gray));

                imagesetpixel($dst, $x, $y, $grayLut[$gray]);
                $hist[$gray]++;
                $totalMaskedPixels++;

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

        if ($distinctGreys > 12 && $totalMaskedPixels > 100 && $tightMaxX >= $tightMinX) {
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
                            $localBaseY = $fontSizePx * 0.33;

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
                            $unrotY = $lineCenterY + ($fontSizePx * 0.33);

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
        imagepng($cropped, null, 6);
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
        imagepng($dst, null, 6);
        $pngData = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if (!is_string($pngData) || $pngData === '') {
            return [$binaryData, $fallbackType];
        }

        return [$pngData, 'png'];
    }
}

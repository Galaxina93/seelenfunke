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

        if (!Storage::exists($path) || is_link(Storage::path($path))) {
            abort(404, 'Datei im Tresor nicht gefunden.');
        }

        $safeMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'
        ];
        $contentType = $attachment->content_type ?: 'application/octet-stream';
        $disposition = in_array(strtolower($contentType), $safeMimes, true) ? 'inline' : 'attachment';
        $cleanFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($attachment->filename ?: 'attachment')) ?: 'attachment';

        return response()->streamDownload(function () use ($path) {
            echo Storage::get($path);
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
        $item = ($itemId instanceof OrderOrderItem) ? $itemId : OrderOrderItem::findOrFail($itemId);

        $isAdminOrEmployee = \Illuminate\Support\Facades\Auth::guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();
        $isCustomerOwner = false;
        if (\Illuminate\Support\Facades\Auth::guard('customer')->check()) {
            $customer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
            if ($item->order && (string)$item->order->customer_id === (string)$customer->id) {
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

        $productConfig = is_string($product->configurator_settings) ? json_decode($product->configurator_settings, true) : ($product->configurator_settings ?? []);

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
                $centerY = 50.0;
                $defaultSymbolX = 50.0;
                $defaultSymbolY = 35.0;
                $defaultSymbolSize = 80.0;
                $defaultTextX = 50.0;
                $defaultTextY = 58.0;
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

        $widthMm = $widthMm ?: floatval($product->width ?? 100);
        $heightMm = $heightMm ?: floatval($product->height ?? 100);

        // Zylindrische (Runde) Flächen ausrollen: Breite (Durchmesser) zu Umfang umrechnen
        if ($overlayType === 'cylinder') {
            $widthMm = $widthMm * pi();
        } elseif ($overlayType === 'plane' && (($productConfig['area_shape'] ?? '') === 'custom')) {
            // Auf der 1:1 quadratischen 2D/3D-Arbeitsfläche (aspect-square) sind X-% und Y-% isotrop
            // (1 % Breite == 1 % Höhe). Damit Freiform-Polygone (wie das Herz beim Seelenanhänger)
            // und Vollflächen-Fotos beim Laser-Export nicht durch unterschiedliche Rohling-Außenmaße
            // (z. B. 45x40 mm) verzerrt/gestaucht werden, bilden wir die 1:1-Stage isotrop ab.
            $stageSpanMm = max($widthMm, $heightMm);
            $widthMm = $stageSpanMm;
            $heightMm = $stageSpanMm;
        }

        $workingAreaPolygonPct = $this->buildWorkingAreaPolygonPercent($productConfig);
        $clipPointsSvg = implode(' ', array_map(function (array $pt) use ($widthMm, $heightMm) {
            $px = number_format(($pt['x'] / 100.0) * $widthMm, 4, '.', '');
            $py = number_format(($pt['y'] / 100.0) * $heightMm, 4, '.', '');
            return "{$px},{$py}";
        }, $workingAreaPolygonPct));

        // SVG Header initialisieren
        $svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
        $svg .= '<svg width="'.$widthMm.'mm" height="'.$heightMm.'mm" viewBox="0 0 '.$widthMm.' '.$heightMm.'" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' . "\n";

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
                    } elseif (filter_var($url, FILTER_VALIDATE_URL)) {
                        $parsedUrl = parse_url($url);
                        $host = $parsedUrl['host'] ?? '';
                        $scheme = strtolower($parsedUrl['scheme'] ?? '');

                        if (
                            in_array($scheme, ['http', 'https'], true)
                            && !empty($host)
                            && !in_array(strtolower($host), ['localhost', '0.0.0.0', '::1'], true)
                            && !str_ends_with(strtolower($host), '.local')
                            && !str_ends_with(strtolower($host), '.internal')
                        ) {
                            $resolvedIps = @gethostbynamel($host) ?: [];
                            $allPublicIps = !empty($resolvedIps);
                            foreach ($resolvedIps as $ip) {
                                $isPublicIp = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                                if (!$isPublicIp || $ip === '127.0.0.1' || str_starts_with($ip, '169.254.') || str_starts_with($ip, '127.')) {
                                    $allPublicIps = false;
                                    break;
                                }
                            }

                            if ($allPublicIps) {
                                try {
                                    $resp = \Illuminate\Support\Facades\Http::timeout(5)->withoutRedirecting()->get($url);
                                    if ($resp->successful()) {
                                        $data = $resp->body();
                                        $ext = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
                                        if (!$ext && $resp->header('Content-Type')) {
                                            $ct = $resp->header('Content-Type');
                                            if (str_contains($ct, 'svg')) $ext = 'svg';
                                            elseif (str_contains($ct, 'png')) $ext = 'png';
                                            elseif (str_contains($ct, 'jpeg') || str_contains($ct, 'jpg')) $ext = 'jpg';
                                        }
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::warning("Could not fetch remote logo for SVG export: " . $url . " - " . $e->getMessage());
                                }
                            }
                        }
                    }
                }

                if ($data !== null) {
                    if ($ext === 'svg') {
                        // SVG inlinen, aber OHNE <svg> Tag, da XCS verschachtelte SVGs ignoriert!
                        $innerSvg = preg_replace('/<\?xml.*?\?>/is', '', $data);
                        $innerSvg = preg_replace('/<!--.*?-->/is', '', $innerSvg);
                        $innerSvg = preg_replace('/<!DOCTYPE.*?>/is', '', $innerSvg);

                        // XSS / Malicious Elements Prevention: Strip script, iframe, object, foreignObject, embed
                        $innerSvg = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $innerSvg);
                        $innerSvg = preg_replace('/<script\b[^>]*\/?>/is', '', $innerSvg);
                        $innerSvg = preg_replace('/<(foreignObject|iframe|object|embed)\b[^>]*>(.*?)<\/\1>/is', '', $innerSvg);
                        $innerSvg = preg_replace('/<(foreignObject|iframe|object|embed)\b[^>]*\/?>/is', '', $innerSvg);
                        $innerSvg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $innerSvg);
                        $innerSvg = preg_replace('/(?:xlink:)?href\s*=\s*["\']\s*(?:javascript|data:text\/html|vbscript):[^"\']*["\']/i', '', $innerSvg);

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
                            $svg .= '  <image x="'.$fmtX.'" y="'.$fmtY.'" width="'.$fmtW.'" height="'.$fmtH.'" href="'.$safeUrl.'" xlink:href="'.$safeUrl.'" preserveAspectRatio="none" />' . "\n";
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
                $svg .= '    <image x="-'.$halfW.'" y="-'.$halfW.'" width="'.$fmtW.'" height="'.$fmtW.'" href="'.$safeUrl.'" xlink:href="'.$safeUrl.'" preserveAspectRatio="xMidYMid meet" />' . "\n";
                $svg .= '  </g>' . "\n";
            }
        }

        // TEXTE RENDER (Als Text-Vektor, sofern nicht bereits pixelgenau ins Raster-Gravurbild eingebrannt)
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
                $strokeAttr = !empty($logos)
                    ? ' stroke="#FFFFFF" stroke-width="'.number_format($safeFontSize * 0.22, 4, '.', '').'" stroke-linejoin="round" stroke-linecap="round" paint-order="stroke fill"'
                    : '';
                foreach ($lines as $line) {
                    $svg .= '    <text x="0" y="'.($startY + ($safeFontSize * 0.35)).'" font-family="'.$safeFontFamily.'" font-size="'.$safeFontSize.'" font-weight="bold" fill="#000000"'.$strokeAttr.' text-anchor="'.$textAnchor.'">'.htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8').'</text>' . "\n";
                    $startY += $lineHeight;
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

        $productConfig = is_string($product->configurator_settings)
            ? json_decode($product->configurator_settings, true)
            : ($product->configurator_settings ?? []);
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
        $widthMm = $widthMm ?: floatval($product->width ?? 100);
        $heightMm = $heightMm ?: floatval($product->height ?? 100);
        if ($overlayType === 'cylinder') {
            $widthMm = $widthMm * pi();
        } elseif ($overlayType === 'plane' && (($productConfig['area_shape'] ?? '') === 'custom')) {
            $stageSpanMm = max($widthMm, $heightMm);
            $widthMm = $stageSpanMm;
            $heightMm = $stageSpanMm;
        }

        $workingAreaPolygonPct = $this->buildWorkingAreaPolygonPercent($productConfig);
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
        array $texts = []
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

                if ($x < $tightMinX) $tightMinX = $x;
                if ($x > $tightMaxX) $tightMaxX = $x;
                if ($y < $tightMinY) $tightMinY = $y;
                if ($y > $tightMaxY) $tightMaxY = $y;
            }
        }

        imagedestroy($stageBuf);

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

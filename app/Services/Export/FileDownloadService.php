<?php

namespace App\Services\Export;

use App\Models\Order\OrderOrderItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class FileDownloadService
{
    public function downloadHealthTreatmentPlanPdf($planId)
    {
        $plan = \App\Models\Ai\AiHealthTreatmentPlan::with('items')->findOrFail($planId);
        $pdf = Pdf::loadView('global.pdf.health-treatment-plan', [
            'plan' => $plan,
        ]);
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Behandlungsplan_' . Str::slug($plan->title) . '.pdf');
    }

    public function downloadProductAnalyticsFullReportPdf()
    {
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

        $pdf->setPaper('a4', 'landscape');

        $finalFilename = now()->format('Y-m-d_H-i') . '_Produkt_Analyse_Gesamtbericht.pdf';
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $finalFilename);
    }

    public function downloadProductAnalyticsLucidPdf()
    {
        $lucidData = \App\Livewire\Shop\Product\ProductAnalytics::getLucidData();
        $pdf = Pdf::loadView('global.pdf.product-analytics-lucid', [
            'lucidData' => $lucidData,
            'date' => now()->format('d.m.Y H:i')
        ]);

        $pdf->setPaper('a4', 'portrait');

        $finalFilename = now()->format('Y-m-d_H-i') . '_LUCID_Jahresbericht_' . $lucidData['year'] . '.pdf';
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $finalFilename);
    }

    public function downloadNicheCrawlerPdf($runId = null)
    {
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
        ]);

        $finalFilename = now()->format('Y-m-d_H-i') . '_' . $filenamePrefix . '.pdf';
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $finalFilename);
    }

    public function downloadMailAttachment($id)
    {
        $attachment = \App\Models\Management\Mail\MailAttachment::findOrFail($id);
        
        // Security check is done by injecting the service in authenticated components natively
        $path = $attachment->path;
        if (!Storage::exists($path)) {
            abort(404, 'Datei im Tresor nicht gefunden.');
        }

        return response()->streamDownload(function () use ($path) {
            echo Storage::get($path);
        }, $attachment->filename, [
            'Content-Type' => $attachment->content_type,
            'Content-Disposition' => 'inline; filename="' . $attachment->filename . '"'
        ]);
    }

    public function downloadTaxExport($filename)
    {
        $path = storage_path('app/buchhaltung/tax_exports/' . $filename);

        if (!File::exists($path)) {
            abort(404, 'Die Datei existiert nicht mehr im Tresor.');
        }

        return response()->download($path);
    }

    public function downloadLaserSvg($itemId, $side = 'front')
    {
        $item = OrderOrderItem::findOrFail($itemId); // Ggf. Namespace anpassen
        $product = $item->product;
        $config = $item->configuration;

        $logos = $side === 'back' ? ($config['logos_back'] ?? []) : ($config['logos'] ?? []);
        $texts = $side === 'back' ? ($config['texts_back'] ?? []) : ($config['texts'] ?? []);

        // Physische Maße des Glases holen (Fallback auf 100x100mm)
        $widthMm = floatval($product->width ?? 100);
        $heightMm = floatval($product->height ?? 100);

        // NEU: Dynamische Abmessungen aus dem Varianten-Namen extrahieren!
        if (!empty($config['variant_name'])) {
            // Sucht nach Mustern wie "180x200" oder "180 x 200" in "Transparent - 180x200x40 mm..."
            if (preg_match('/(\d+)\s*[xX]\s*(\d+)/', $config['variant_name'], $matches)) {
                $widthMm = floatval($matches[1]);
                $heightMm = floatval($matches[2]);
            }
        }

        // SVG Header initialisieren
        $svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
        $svg .= '<svg width="'.$widthMm.'mm" height="'.$heightMm.'mm" viewBox="0 0 '.$widthMm.' '.$heightMm.'" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' . "\n";

        // HILFSLINIE: Äußerer Rahmen (hilft der Laserschutzbeauftragten beim Framing / Einmessen)
        $svg .= '  ' . "\n";
        $svg .= '  <rect x="0" y="0" width="'.$widthMm.'" height="'.$heightMm.'" fill="none" stroke="#FF0000" stroke-width="0.1" />' . "\n";

        // BILDER & LOGOS RENDER (Base64)
        if (!empty($logos)) {
            foreach ($logos as $logo) {
                $x = ($logo['x'] / 100) * $widthMm;
                $y = ($logo['y'] / 100) * $heightMm;
                $rot = $logo['rotation'] ?? 0;
                // Skalierungs-Logik identisch zum JS Canvas
                $logoWidth = (($logo['size'] ?? 100) / 500) * $widthMm;

                $url = $logo['url'];
                $localPath = null;

                // Falls es keine Base64-URL ist, sondern ein lokaler Pfad, wandeln wir es für die SVG um
                if (!str_starts_with($url, 'data:image')) {
                    $urlPath = parse_url($url, PHP_URL_PATH);

                    if (str_contains($urlPath, '/storage/')) {
                        $relativePath = substr($urlPath, strpos($urlPath, '/storage/') + 9);
                        $localPath = storage_path('app/public/' . $relativePath);
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

                    if ($localPath && file_exists($localPath)) {
                        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                        $data = file_get_contents($localPath);

                        if ($ext === 'svg') {
                            // SVG inlinen, aber OHNE <svg> Tag, da XCS verschachtelte SVGs ignoriert!
                            $innerSvg = preg_replace('/<\?xml.*?\?>/is', '', $data);
                            $innerSvg = preg_replace('/<!--.*?-->/is', '', $innerSvg);
                            $innerSvg = preg_replace('/<!DOCTYPE.*?>/is', '', $innerSvg);

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
                            $type = ($ext === 'jpg') ? 'jpeg' : $ext;
                            $url = 'data:image/' . $type . ';base64,' . base64_encode($data);
                        }
                    }
                }

                $svg .= '  <g transform="translate('.$x.', '.$y.') rotate('.$rot.')">' . "\n";
                $svg .= '    <image x="-'.($logoWidth/2).'" y="-'.($logoWidth/2).'" width="'.$logoWidth.'" height="'.$logoWidth.'" href="'.$url.'" preserveAspectRatio="xMidYMid meet" />' . "\n";
                $svg .= '  </g>' . "\n";
            }
        }

        // TEXTE RENDER (Als Text-Vektor)
        if (!empty($texts)) {
            foreach ($texts as $textItem) {
                // Verhindert Rendering von leeren Textboxen
                if (empty(trim($textItem['text'] ?? ''))) continue;

                $x = ($textItem['x'] / 100) * $widthMm;
                $y = ($textItem['y'] / 100) * $heightMm;
                $rot = $textItem['rotation'] ?? 0;
                $fontFamily = $textItem['font'] ?? 'Arial';
                $align = $textItem['align'] ?? 'center';

                $fontSizeMm = ($textItem['size'] ?? 1) * ($widthMm / 25);

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

                foreach ($lines as $line) {
                    $svg .= '    <text x="0" y="'.($startY + ($fontSizeMm * 0.35)).'" font-family="'.$fontFamily.'" font-size="'.$fontSizeMm.'" font-weight="bold" fill="#000000" text-anchor="'.$textAnchor.'">'.htmlspecialchars($line).'</text>' . "\n";
                    $startY += $lineHeight;
                }

                $svg .= '  </g>' . "\n";
            }
        }

        $svg .= '</svg>';

        $filename = 'xTool-F2-Druckdatei-' . ($item->order->order_number ?? 'Angebot') . '-Pos-' . $item->id . '-' . ($side === 'back' ? 'Rueckseite' : 'Vorderseite') . '.svg';

        return response()->streamDownload(function() use ($svg) {
            echo $svg;
        }, $filename, ['Content-Type' => 'image/svg+xml']);
    }
}

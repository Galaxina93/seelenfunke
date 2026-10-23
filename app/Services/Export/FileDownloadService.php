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
        $isAdmin = \Illuminate\Support\Facades\Auth::guard('admin')->check();
        $user = \Illuminate\Support\Facades\Auth::user();

        $plan = \App\Models\Ai\AiHealthTreatmentPlan::with('items')->findOrFail($planId);

        if (!$isAdmin && (!$user || (string)$user->id !== (string)$plan->user_id)) {
            abort(403, 'Zugriff verweigert.');
        }

        $pdf = Pdf::loadView('global.pdf.health-treatment-plan', [
            'plan' => $plan,
        ])->setOption('isRemoteEnabled', false);
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Behandlungsplan_' . Str::slug($plan->title) . '.pdf');
    }

    public function downloadProductAnalyticsFullReportPdf()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check()) {
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
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check()) {
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
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check()) {
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
        if (!Auth::guard('admin')->check() && !(Auth::check() && Auth::user() instanceof Admin)) {
            abort(403, 'Unautorisierter Zugriff auf E-Mail-Anhänge.');
        }

        $attachment = \App\Models\Management\Mail\MailAttachment::findOrFail($id);
        
        $path = $attachment->path;
        if (!$path || str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        if (!Storage::exists($path)) {
            abort(404, 'Datei im Tresor nicht gefunden.');
        }

        $safeMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'
        ];
        $contentType = $attachment->content_type ?: 'application/octet-stream';
        $disposition = in_array(strtolower($contentType), $safeMimes, true) ? 'inline' : 'attachment';
        $cleanFilename = str_replace(['"', "\r", "\n"], '', basename($attachment->filename ?: 'attachment'));

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
        if (!Auth::guard('admin')->check() && !(Auth::check() && Auth::user() instanceof Admin)) {
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

        if (!File::exists($path)) {
            abort(404, 'Die Datei existiert nicht mehr im Tresor.');
        }

        return response()->download($path);
    }

    public function downloadLaserSvg($itemId, $side = 'front')
    {
        $item = ($itemId instanceof OrderOrderItem) ? $itemId : OrderOrderItem::findOrFail($itemId);

        $isAdminOrEmployee = \Illuminate\Support\Facades\Auth::guard('admin')->check() || \Illuminate\Support\Facades\Auth::guard('employee')->check();
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
        $overlayType = $config['overlay_type'] ?? ($productConfig['overlay_type'] ?? 'plane');

        // Physische Maße holen:
        // 1. Priorität: Dynamisch aus dem Varianten-Namen (z.B. "180x200 mm")
        // 2. Priorität: Aus den xTool-Parametern aus Schritt 4 (Backend Produkt-Konfiguration)
        // 3. Fallback: Aus den Produktstammdaten ($product->width / $product->height) oder 100x100mm
        $widthMm = null;
        $heightMm = null;

        if (!empty($config['variant_name'])) {
            if (preg_match('/(\d+)\s*[xX]\s*(\d+)/', $config['variant_name'], $matches)) {
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
        }

        // SVG Header initialisieren
        $svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
        $svg .= '<svg width="'.$widthMm.'mm" height="'.$heightMm.'mm" viewBox="0 0 '.$widthMm.' '.$heightMm.'" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' . "\n";

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
                $fontFiles = collect(File::files(public_path('fonts')))->filter(function($file) use ($slug) {
                    return Str::startsWith($file->getFilename(), $slug . '-');
                });

                if ($fontFiles->isNotEmpty()) {
                    $bestFont = $fontFiles->first(function($file) {
                        return str_contains($file->getFilename(), 'regular') || str_contains($file->getFilename(), '400');
                    }) ?? $fontFiles->first();

                    $base64 = base64_encode(file_get_contents($bestFont->getPathname()));
                    $safeCssFontFamily = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', (string) $fontFamily);
                    $svg .= "    @font-face {\n";
                    $svg .= "      font-family: '{$safeCssFontFamily}';\n";
                    $svg .= "      src: url('data:font/woff2;charset=utf-8;base64,{$base64}') format('woff2');\n";
                    $svg .= "      font-weight: normal;\n";
                    $svg .= "      font-style: normal;\n";
                    $svg .= "    }\n";
                }
            }
            $svg .= "  </style>\n";
        }

        // HILFSLINIE: Äußerer Rahmen (hilft der Laserschutzbeauftragten beim Framing / Einmessen)
        $svg .= '  ' . "\n";
        $svg .= '  <rect x="0" y="0" width="'.$widthMm.'" height="'.$heightMm.'" fill="none" stroke="#FF0000" stroke-width="0.1" />' . "\n";

        // BILDER & LOGOS RENDER (Base64)
        if (!empty($logos)) {
            foreach ($logos as $logo) {
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
                        $type = preg_replace('/[^a-z0-9]/i', '', ($ext === 'jpg') ? 'jpeg' : ($ext ?: 'png'));
                        $url = 'data:image/' . $type . ';base64,' . base64_encode($data);
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

        // TEXTE RENDER (Als Text-Vektor)
        if (!empty($texts)) {
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
                foreach ($lines as $line) {
                    $svg .= '    <text x="0" y="'.($startY + ($safeFontSize * 0.35)).'" font-family="'.$safeFontFamily.'" font-size="'.$safeFontSize.'" font-weight="bold" fill="#000000" text-anchor="'.$textAnchor.'">'.htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8').'</text>' . "\n";
                    $startY += $lineHeight;
                }

                $svg .= '  </g>' . "\n";
            }
        }

        $svg .= '</svg>';

        $filename = 'xTool-F2-Druckdatei-' . ($item->order->order_number ?? 'Angebot') . '-Pos-' . $item->id . '-' . ($side === 'back' ? 'Rueckseite' : 'Vorderseite') . '.svg';

        return response()->streamDownload(function() use ($svg) {
            echo $svg;
        }, $filename, [
            'Content-Type' => 'image/svg+xml',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

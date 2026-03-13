<?php

use App\Models\Order\OrderItem;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin'])->group(function () {

    // Dashboard
    Route::get('/admin/dashboard', function () {
    return view('backend.admin.pages.dashboard');
    })->name('admin.dashboard');

    // Funki
    Route::get('/admin/funki', function () {
        return view('backend.admin.pages.funki');
    })->name('admin.funki');
    Route::get('/admin/funki-routine', function () {
        return view('backend.admin.pages.funki-routine');
    })->name('admin.funki-routine');
    Route::get('/admin/funki-todos', function () {
        return view('backend.admin.pages.funki-todos');
    })->name('admin.funki-todos');
    Route::get('/admin/funki-kalender', function () {
        return view('backend.admin.pages.funki-kalender');
    })->name('admin.funki-kalender');
    Route::get('/admin/company-map', function () {
        return view('backend.admin.pages.company-map');
    })->name('admin.partials');
    Route::get('/admin/tickets', function () {
        return view('backend.admin.pages.tickets');
    })->name('admin.tickets');
    Route::get('/admin/knowledge_base', function () {
        return view('backend.admin.pages.knowledge-base');
    })->name('admin.knowledge_base');



    // Benutzerverwaltung
    Route::get('/admin/user-management', function () {
        return view('backend.admin.pages.user-management');
    })->name('admin.user-management');

    // Benutzerverwaltung
    Route::get('/admin/right-management', function () {
        return view('backend.admin.pages.right-management');
    })->name('admin.right-management');



    // Shop
    Route::get('/admin/products', function () {
        return view('backend.admin.pages.products');
    })->name('admin.products');

    Route::get('/admin/product-templates', function () {
        return view('backend.admin.pages.product-templates');
    })->name('admin.product-templates');

    Route::get('/admin/reviews', function () {
        return view('backend.admin.pages.product-control-reviews');
    })->name('admin.product-control-reviews');

    Route::get('/admin/invoices', function () {
        return view('backend.admin.pages.invoices');
    })->name('admin.invoices');

    Route::get('/admin/credit-management', function () {
        return view('backend.admin.pages.credit-management');
    })->name('admin.credit-management');

    Route::get('/admin/orders', function () {
        return view('backend.admin.pages.orders');
    })->name('admin.orders');

    Route::get('/admin/quote-requests', function () {
        return view('backend.admin.pages.quote-requests');
    })->name('admin.quote-requests');

    Route::get('/admin/financial-evaluation', function () {
        return view('backend.admin.pages.financial-evaluation');
    })->name('admin.financial-evaluation');

    Route::get('/admin/financial-liquidity-planning', function () {
        return view('backend.admin.pages.financial-liquidity-planning');
    })->name('admin.financial-liquidity-planning');

    Route::get('/admin/financial-banks', function () {
        return view('backend.admin.pages.financial-banks');
    })->name('admin.financial-banks');

    Route::get('/admin/financial-fix-costs', function () {
        return view('backend.admin.pages.financial-fix-costs');
    })->name('admin.financial-fix-costs');

    Route::get('/admin/financial-variable-costs', function () {
        return view('backend.admin.pages.financial-variable-costs');
    })->name('admin.financial-variable-costs');

    Route::get('/admin/financial-tax', function () {
        return view('backend.admin.pages.financial-tax');
    })->name('admin.financial-tax');

    Route::get('/admin/configuration', function () {
        return view('backend.admin.pages.configuration');
    })->name('admin.configuration');

    Route::get('/admin/funkira-log', function () {
        return view('backend.admin.pages.funkira-log');
    })->name('admin.funkira-log');

    Route::get('/admin/funkira-structure', function () {
        return view('backend.admin.pages.funkira-structure');
    })->name('admin.funkira-structure');

    Route::get('/admin/funkira-methods', function () {
        return view('backend.admin.pages.funkira-methods');
    })->name('admin.funkira-methods');

    Route::get('/admin/funkira-genui', function () {
        return view('backend.admin.pages.funkira-genui');
    })->name('admin.funkira-genui');

    Route::get('/admin/person-profiles', function () {
        return view('backend.admin.pages.person-profile');
    })->name('admin.person-profiles');

    Route::get('/admin/blog', function () {
        return view('backend.admin.pages.blog');
    })->name('admin.blog');

    Route::get('/admin/voucher', function () {
        return view('backend.admin.pages.voucher');
    })->name('admin.voucher');

    Route::get('/admin/newsletter', function () {
        return view('backend.admin.pages.newsletter');
    })->name('admin.newsletter');



    Route::get('/admin/orders/laser-file/{itemId}', function (Illuminate\Http\Request $request, $itemId) {
        $item = OrderItem::findOrFail($itemId); // Ggf. Namespace anpassen
        $product = $item->product;
        $config = $item->configuration;
        $side = $request->query('side', 'front');

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
                        $localPath = public_path('images/' . ltrim($relativePath, '/'));
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

                            // Nur den echten Vektor-Inhalt (die <path> Elemente) extrahieren
                            if (preg_match('/<svg[^>]*>(.*?)<\/svg>/is', $innerSvg, $m)) {
                                $innerSvg = $m[1];
                            } else {
                                $innerSvg = preg_replace('/<svg[^>]*>/is', '', $innerSvg);
                                $innerSvg = str_replace('</svg>', '', $innerSvg);
                            }

                            // XCS BUGFIX: XCS Laser-Software ignoriert weiße Füllungen (#FFFFFF oder white) für Gravuren komplett.
                            // Wir müssen die Farben auf schwarz (#000000) für Gravur umschreiben.
                            $innerSvg = preg_replace('/fill="#[fF]{3,6}"/i', 'fill="#000000"', $innerSvg);
                            $innerSvg = preg_replace('/fill="white"/i', 'fill="#000000"', $innerSvg);
                            $innerSvg = preg_replace('/fill="none"/i', 'fill="#000000"', $innerSvg); // Manchmal sind sie unsichtbar, erzwinge Füllung.

                            // Failsafe: Falls in der Datei kein "fill=" deklariert ist, sicherheitshalber ins <g> Tag packen.
                            $fillAttr = !str_contains($innerSvg, 'fill=') ? ' fill="#000000"' : '';

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

    })->name('admin.orders.laserfile');

    Route::get('/admin/funki/tax-export/{filename}', function ($filename) {
        $path = storage_path('app/tax_exports/' . $filename);

        if (!File::exists($path)) {
            abort(404, 'Die Datei existiert nicht mehr im Tresor.');
        }

        return response()->download($path);
    })->name('admin.tax-export.download'); // Falls du eine auth:admin Middleware nutzt, hänge sie hier noch an ->middleware('auth:admin')


});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/admin/password-reset/{token}', function ($token) {
        return view('global/pages/password/password-reset', ['token' => $token]);
    });
});


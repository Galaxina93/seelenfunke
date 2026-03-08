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

    Route::get('/admin/blog', function () {
        return view('backend.admin.pages.blog');
    })->name('admin.blog');

    Route::get('/admin/voucher', function () {
        return view('backend.admin.pages.voucher');
    })->name('admin.voucher');

    Route::get('/admin/newsletter', function () {
        return view('backend.admin.pages.newsletter');
    })->name('admin.newsletter');



    Route::get('/admin/orders/laser-file/{itemId}', function ($itemId) {
        $item = OrderItem::findOrFail($itemId); // Ggf. Namespace anpassen
        $product = $item->product;
        $config = $item->configuration;

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
        if (!empty($config['logos'])) {
            foreach ($config['logos'] as $logo) {
                $x = ($logo['x'] / 100) * $widthMm;
                $y = ($logo['y'] / 100) * $heightMm;
                $rot = $logo['rotation'] ?? 0;
                // Skalierungs-Logik identisch zum JS Canvas
                $logoWidth = (($logo['size'] ?? 100) / 500) * $widthMm;

                $url = $logo['url'];

                // Falls es keine Base64-URL ist, sondern ein lokaler Pfad, wandeln wir es für die SVG um
                if (!str_starts_with($url, 'data:image')) {
                    $path = storage_path('app/public/' . str_replace('storage/', '', parse_url($url, PHP_URL_PATH)));
                    if (file_exists($path)) {
                        $type = pathinfo($path, PATHINFO_EXTENSION);
                        $data = file_get_contents($path);
                        $url = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    }
                }

                $svg .= '  <g transform="translate('.$x.', '.$y.') rotate('.$rot.')">' . "\n";
                $svg .= '    <image x="-'.($logoWidth/2).'" y="-'.($logoWidth/2).'" width="'.$logoWidth.'" href="'.$url.'" preserveAspectRatio="xMidYMid meet" />' . "\n";
                $svg .= '  </g>' . "\n";
            }
        }

        // TEXTE RENDER (Als Text-Vektor)
        if (!empty($config['texts'])) {
            foreach ($config['texts'] as $textItem) {
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

        $filename = 'xTool-F2-Druckdatei-' . ($item->order->order_number ?? 'Angebot') . '-Pos-' . $item->id . '.svg';

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


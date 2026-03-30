<?php

use App\Models\Order\OrderOrderItem;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin'])->group(function () {

    // Dashboard
    Route::get('/admin/dashboard', \App\Livewire\Shop\Master\MasterAnalytics::class)->name('admin.dashboard');
    Route::get('/admin/master/analytics', \App\Livewire\Shop\Master\MasterAnalytics::class)->name('admin.master-analytics');

    Route::get('/admin/routine', \App\Livewire\Shop\Management\ManagementRoutine::class)->name('admin.routine');
    Route::get('/admin/tasks', \App\Livewire\Shop\Management\ManagementTask::class)->name('admin.tasks');
    Route::get('/admin/calender', \App\Livewire\Shop\Management\ManagementCalender::class)->name('admin.calender');
    Route::get('/admin/company-map', \App\Livewire\Shop\System\SystemCompanyMap::class)->name('admin.company-map');
    Route::get('/admin/support-tickets', \App\Livewire\Shop\Support\SupportTicket::class)->name('admin.support-tickets');
    Route::get('/admin/support-chats', \App\Livewire\Shop\Support\SupportChatAnalytics::class)->name('admin.support-chats');
    Route::get('/admin/support-contact-form', \App\Livewire\Shop\Support\SupportContactFormComponent::class)->name('admin.support-contact-form');


    // AI AGENT UNVIVERSE
    Route::get('/admin/ai-analytics', \App\Livewire\Shop\Ai\AiAnalytics::class)->name('admin.ai-analytics');
    Route::get('/admin/ceo/gesundheit', \App\Livewire\Shop\Management\ManagementHealth::class)->name('ceo.gesundheit');
    Route::get('/admin/ceo/gesundheit/plan/{id}/pdf', function ($id) {
        $plan = \App\Models\Ai\AiHealthTreatmentPlan::with('items')->findOrFail($id);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('global.pdf.health-treatment-plan', [
            'plan' => $plan,
        ]);
        return $pdf->download('Behandlungsplan_' . \Illuminate\Support\Str::slug($plan->title) . '.pdf');
    })->name('ceo.gesundheit.plan.pdf');

    Route::get('/admin/global-logs', \App\Livewire\Shop\System\SystemLogs::class)->name('admin.global-logs');
    Route::get('/admin/ai-genui', \App\Livewire\Shop\Ai\AiVisualizationRegistry::class)->name('admin.ai-genui');
    Route::get('/admin/person-profiles', \App\Livewire\Shop\Management\ManagementPersonProfiles::class)->name('admin.person-profiles');

    Route::get('/admin/rollen', \App\Livewire\Shop\Ai\AiRoleManager::class)->name('admin.rollen');

    Route::get('/admin/agenten', \App\Livewire\Shop\Ai\AiAgentManager::class)->name('admin.ai-agents');

    Route::get('/admin/organigramm', \App\Livewire\Shop\Ai\AiCompanyStructure::class)->name('admin.ai-company-structure');
    Route::get('/admin/ki-agenten/{id}', \App\Livewire\Shop\Ai\AiAgentEditor::class)->name('admin.ai-agents.editor');
    Route::get('/admin/externe-agenten/{id}', \App\Livewire\Shop\Ai\ExternalAgentEditor::class)->name('admin.external-agents.editor');
    Route::get('/admin/ai-genui', \App\Livewire\Shop\Ai\AiVisualizationRegistry::class)->name('admin.ai-genui');
    Route::get('/admin/ai-chat', \App\Livewire\Shop\Ai\AiChat::class)->name('admin.ai-chat');
    Route::get('/admin/ai-knowledge_base', \App\Livewire\Shop\Ai\AiKnowledgeBase::class)->name('admin.ai-knowledge_base');
    Route::get('/admin/system-info', \App\Livewire\Shop\System\SystemInfo::class)->name('admin.system-info');

    // Benutzerverwaltung
    Route::get('/admin/user-management', \App\Livewire\Shop\System\SystemUserManagement::class)->name('admin.user-management');

    // Benutzerverwaltung




    // Shop
    Route::get('/admin/products', \App\Livewire\Shop\Product\ProductCreate::class)->name('admin.products');

    Route::get('/admin/product-analytics', \App\Livewire\Shop\Product\ProductAnalytics::class)->name('admin.product-analytics');

    Route::get('/admin/product-analytics/export/full-report', function () {
        $lossesData = [
            'this_month' => \App\Models\Product\ProductLoss::where('created_at', '>=', now()->startOfMonth())->sum('cost_value') / 100,
            'total' => \App\Models\Product\ProductLoss::sum('cost_value') / 100,
            'recent' => \App\Models\Product\ProductLoss::with('product')->latest()->take(50)->get(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('global.pdf.product-analytics-report', [
            'combinedData' => \App\Livewire\Shop\Product\ProductAnalytics::getCombinedAnalyticsData(),
            'lossesData' => $lossesData,
            'date' => now()->format('d.m.Y H:i')
        ]);

        // Settings for PDF
        $pdf->setPaper('a4', 'landscape');

        $finalFilename = now()->format('Y-m-d_H-i') . '_Produkt_Analyse_Gesamtbericht.pdf';
        return $pdf->download($finalFilename);
    })->name('admin.product-analytics.export.full');

    Route::get('/admin/product-analytics/export/lucid', function () {
        $lucidData = \App\Livewire\Shop\Product\ProductAnalytics::getLucidData();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('global.pdf.product-analytics-lucid', [
            'lucidData' => $lucidData,
            'date' => now()->format('d.m.Y H:i')
        ]);

        $pdf->setPaper('a4', 'portrait');

        $finalFilename = now()->format('Y-m-d_H-i') . '_LUCID_Jahresbericht_' . $lucidData['year'] . '.pdf';
        return $pdf->download($finalFilename);
    })->name('admin.product-analytics.export.lucid');

    Route::get('/admin/product-packaging', \App\Livewire\Shop\Product\ProductPackagingConfigurator::class)->name('admin.product-packaging');

    Route::get('/admin/product-fracture', \App\Livewire\Shop\Product\ProductFracture::class)->name('admin.product-fracture');

    Route::get('/admin/product-suppliers', \App\Livewire\Shop\Product\ProductSuppliers::class)->name('admin.product-suppliers');

    Route::get('/admin/products/nischen-scout', \App\Livewire\Shop\Product\ProductNicheScanner::class)->name('admin.products.niche');

    Route::get('/admin/products/nischen-scout/pdf', function () {
        $runId = request('product_niche_crawler_run_id');
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
            $filenamePrefix = \Illuminate\Support\Str::slug($run->name) . '_Crawler_Ergebnis';
        } else {
            $top40 = \App\Models\Product\ProductNicheItem::orderBy('niche_score', 'desc')->take(40)->get();
        }

        if ($top40->isEmpty()) abort(404, 'Keine Produkte gefunden.');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('global.pdf.top5-niche-products', [
            'products' => $top40,
            'aiRecommendation' => $aiRecommendation,
            'aiAgentName' => $aiAgentName,
            'docTitle' => $docTitle,
            'date' => now()->format('d.m.Y H:i')
        ]);

        $finalFilename = now()->format('Y-m-d_H-i') . '_' . $filenamePrefix . '.pdf';
        return $pdf->download($finalFilename);
    })->name('shop.pdf.top5-niche');

    Route::get('/admin/product-templates', \App\Livewire\Shop\Product\ProductTemplates::class)->name('admin.product-templates');

    Route::get('/admin/reviews', \App\Livewire\Shop\Product\ProductControlReviews::class)->name('admin.product-control-reviews');

    Route::get('/admin/invoices', \App\Livewire\Shop\Accounting\AccountingInvoice::class)->name('admin.invoices');

    Route::get('/admin/credit-management', \App\Livewire\Shop\Accounting\AccountingCredit::class)->name('admin.credit-management');

    Route::get('/admin/orders/analytics', \App\Livewire\Shop\Order\OrderAnalytics::class)->name('admin.orders.analytics');
    Route::get('/admin/orders', \App\Livewire\Shop\Order\OrderOverview::class)->name('admin.orders');

    Route::get('/admin/quote-requests', \App\Livewire\Shop\Order\OrderQuoteRequests::class)->name('admin.quote-requests');

    Route::get('/admin/widerruf', \App\Livewire\Shop\Order\OrderRevocations::class)->name('admin.widerruf');

    Route::get('/admin/financial-analytics', \App\Livewire\Shop\Accounting\AccountingAnalytics::class)->name('admin.financial-analytics');

    Route::get('/admin/financial-liquidity-planning', \App\Livewire\Shop\Accounting\AccountingLiquidity::class)->name('admin.financial-liquidity-planning');

    Route::get('/admin/financial-banks', \App\Livewire\Shop\Accounting\AccountingBank::class)->name('admin.financial-banks');

    Route::get('/admin/financial-fix-costs', \App\Livewire\Shop\Accounting\AccountingFixCosts::class)->name('admin.financial-fix-costs');

    Route::get('/admin/financial-variable-costs', \App\Livewire\Shop\Accounting\AccountingVariableCosts::class)->name('admin.financial-variable-costs');

    Route::get('/admin/financial-tax', \App\Livewire\Shop\Accounting\AccountingTax::class)->name('admin.financial-tax');

    Route::get('/admin/configuration', \App\Livewire\Shop\System\SystemShopConfig::class)->name('admin.configuration');

    Route::get('/admin/marketing/analytics', \App\Livewire\Shop\Marketing\MarketingAnalytics::class)->name('admin.marketing-analytics');

    Route::get('/admin/blog', \App\Livewire\Shop\Marketing\MarketingBlog::class)->name('admin.blog');

    Route::get('/admin/voucher', \App\Livewire\Shop\Marketing\MarketingVoucher::class)->name('admin.voucher');

    Route::get('/admin/newsletter', \App\Livewire\Shop\Marketing\MarketingNewsletter::class)->name('admin.newsletter');

    Route::get('/admin/inbox', \App\Livewire\Shop\Management\ManagementEMails::class)->name('admin.inbox');

    Route::get('/admin/inbox/attachment/{id}', function ($id) {
        $attachment = \App\Models\Management\Mail\MailAttachment::findOrFail($id);

        // Security check handled securely by 'auth:admin' middleware
        $path = $attachment->path;
        if (!\Illuminate\Support\Facades\Storage::exists($path)) {
            abort(404, 'Datei im Tresor nicht gefunden.');
        }

        return response()->file(\Illuminate\Support\Facades\Storage::path($path), [
            'Content-Type' => $attachment->content_type,
            'Content-Disposition' => 'inline; filename="' . $attachment->filename . '"'
        ]);
    })->name('crm.mail-attachment');



    Route::get('/admin/orders/laser-file/{itemId}', function (Illuminate\Http\Request $request, $itemId) {
        $item = OrderOrderItem::findOrFail($itemId); // Ggf. Namespace anpassen
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
        return view('auth.password-reset', ['token' => $token]);
    });
});


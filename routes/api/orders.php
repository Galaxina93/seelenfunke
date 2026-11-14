<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Order\OrderOrder;

Route::group(['middleware' => [function ($request, $next) {
    if (!$request->user() instanceof \App\Models\Admin\Admin) {
        abort(403, 'Nur Admins dürfen Bestellungen verwalten.');
    }
    return $next($request);
}]], function () {

    // Get all orders
    Route::get('/shop/orders', function (Request $request) {
        $status = $request->query('status');
        $search = $request->query('search');
        
        $query = OrderOrder::with(['items.product']);
        
        if ($search) {
            $searchTerm = '%' . addcslashes((string) $search, '%_\\') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('order_number', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('billing_address->last_name', 'like', $searchTerm)
                    ->orWhere('billing_address->first_name', 'like', $searchTerm)
                    ->orWhere('billing_address->company', 'like', $searchTerm);
            });
        }
        
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $query->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC")
            ->orderBy('is_express', 'desc')
            ->orderBy('created_at', 'asc');
        
        $orders = $query->paginate(20);
        
        // Fetch absolute priority order from the database (not affected by search/status filters)
        $prio = OrderOrder::with(['items.product'])
            ->whereIn('status', ['pending', 'processing'])
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC")
            ->orderBy('is_express', 'desc')
            ->orderBy('created_at', 'asc')
            ->first();

        $priorityOrderData = null;
        if ($prio) {
            // Generate priority tip text
            $missingItems = [];
            $isOnlyStandard = true;

            foreach ($prio->items as $item) {
                if ($item->product) {
                    if (method_exists($item->product, 'isPersonalizable') && $item->product->isPersonalizable()) {
                        $isOnlyStandard = false;
                    }
                    
                    if ($prio->is_express && $item->product->track_quantity) {
                        if ($item->quantity > $item->product->quantity && !$item->product->continue_selling_when_out_of_stock) {
                            $missingItems[] = $item->product->name;
                        }
                    }
                }
            }

            $standardMessage = '';
            if (method_exists($prio, 'isOnlyDigital') && $prio->isOnlyDigital()) {
                $standardMessage = "\n\n⚡ DIGITALE BEREITSTELLUNG:\nAusschließlich digitale Produkte! Die Auslieferung erfolgt vollautomatisch.";
            } elseif ($isOnlyStandard) {
                $standardMessage = "\n\n⚡ SCHNELLE NUMMER:\nAusschließlich Lagerware! Keine Personalisierung/Laser-Arbeit nötig. Einfach aus dem Regal nehmen, verpacken, Label drauf und ab die Post!";
            }

            if ($prio->is_express) {
                if (count($missingItems) > 0) {
                    $tip = 'Lagerbestand Kritisch! ' . count($missingItems) . ' Artikel für diesen Express-Versand fehlen physisch. Bitte sofort prüfen!' . $standardMessage;
                } else {
                    $tip = 'Lagerbestand gesichert! Dieser Express-Versand ist komplett auf Lager und kann sofort abgewickelt werden.' . $standardMessage;
                }
            } else {
                $tip = 'Dies ist der älteste offene Auftrag im System. Arbeite ihn zügig ab, um die Wartezeiten gering zu halten.' . $standardMessage;
            }

            $priorityOrderData = [
                'id' => $prio->id,
                'order_number' => $prio->order_number,
                'email' => $prio->email,
                'customer_name' => $prio->customer_name,
                'status' => $prio->status,
                'status_color' => $prio->status_color,
                'payment_status' => $prio->payment_status,
                'payment_status_color' => $prio->payment_status_color,
                'payment_method' => $prio->payment_method,
                'total_price' => $prio->total_price,
                'created_at' => $prio->created_at->toIso8601String(),
                'item_count' => $prio->items->sum('quantity'),
                'is_express' => (bool)$prio->is_express,
                'priority_tip' => $tip,
            ];
        }
        
        // Transform orders to include customer name, status color, etc.
        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'email' => $order->email,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'status_color' => $order->status_color,
                'payment_status' => $order->payment_status,
                'payment_status_color' => $order->payment_status_color,
                'payment_method' => $order->payment_method,
                'total_price' => $order->total_price, // in cents
                'created_at' => $order->created_at->toIso8601String(),
                'item_count' => $order->items->sum('quantity'),
                'is_express' => (bool)$order->is_express,
            ];
        });
        
        $formatGdprShortName = static function (?string $firstName, ?string $lastName, ?string $fallbackName = null): string {
            $first = trim((string) $firstName);
            $last = trim((string) $lastName);
            if ($first === '' && $last === '' && !empty($fallbackName)) {
                $parts = preg_split('/\s+/', trim((string) $fallbackName)) ?: [];
                $first = $parts[0] ?? '';
                $last = count($parts) > 1 ? (string) end($parts) : '';
            }
            if ($first === '') {
                return 'Kunde';
            }
            $firstClean = mb_substr($first, 0, 18);
            if ($last !== '') {
                $initial = mb_strtoupper(mb_substr($last, 0, 1));
                return $firstClean . ' ' . $initial . '.';
            }
            return $firstClean;
        };

        $topCustomerRows = OrderOrder::query()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where(function ($q) {
                $q->whereNotNull('customer_id')
                  ->orWhereRaw("TRIM(COALESCE(email, '')) != ''");
            })
            ->selectRaw("COALESCE(NULLIF(customer_id, ''), LOWER(TRIM(email))) as customer_key, SUM(total_price) as total_cents, COUNT(*) as orders_count, MAX(id) as sample_order_id")
            ->groupByRaw("COALESCE(NULLIF(customer_id, ''), LOWER(TRIM(email)))")
            ->orderByDesc('total_cents')
            ->orderByDesc('orders_count')
            ->limit(3)
            ->get();

        $sampleOrderIds = $topCustomerRows->pluck('sample_order_id')->filter()->all();
        $sampleOrders = !empty($sampleOrderIds)
            ? OrderOrder::whereIn('id', $sampleOrderIds)
                ->get(['id', 'billing_address', 'email', 'customer_id'])
                ->keyBy('id')
            : collect();

        $topCustomers = [];
        foreach ($topCustomerRows as $aggRow) {
            $sample = $sampleOrders->get($aggRow->sample_order_id);
            $billing = ($sample && is_array($sample->billing_address)) ? $sample->billing_address : [];
            $topCustomers[] = [
                'display_name' => $formatGdprShortName(
                    $billing['first_name'] ?? null,
                    $billing['last_name'] ?? null,
                    $sample ? ($sample->customer_name ?? null) : null
                ),
                'total_cents' => (int) $aggRow->total_cents,
                'orders_count' => (int) $aggRow->orders_count,
            ];
        }

        $responseData = $orders->toArray();
        $responseData['priority_order'] = $priorityOrderData;
        $responseData['open_orders_count'] = OrderOrder::whereNotIn('status', ['completed', 'cancelled', 'refunded', 'shipped'])->count();
        $responseData['open_carts_count'] = \App\Models\Cart\Cart::whereHas('items')->count();
        $responseData['top_customers'] = $topCustomers;
        $responseData['latest_order_id'] = (string) (OrderOrder::max('id') ?? '');
        
        return response()->json($responseData);
    });

    // Get order details
    Route::get('/shop/orders/{id}', function ($id) {
        $order = OrderOrder::with(['items.product', 'shipments'])->findOrFail($id);
        
        return response()->json([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'email' => $order->email,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'status_color' => $order->status_color,
            'payment_status' => $order->payment_status,
            'payment_status_color' => $order->payment_status_color,
            'payment_method' => $order->payment_method,
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'volume_discount' => $order->volume_discount,
            'coupon_code' => $order->coupon_code,
            'discount_amount' => $order->discount_amount,
            'subtotal_price' => $order->subtotal_price,
            'tax_amount' => $order->tax_amount,
            'shipping_price' => $order->shipping_price,
            'total_price' => $order->total_price,
            'notes' => $order->notes,
            'is_express' => $order->is_express,
            'express_price' => $order->express_price,
            'calculated_dhl_weight' => $order->calculateDhlWeight(),
            'dhl_product_weight_grams' => $order->getDhlWeightDetails()['product_weight_grams'],
            'dhl_packaging_weight_grams' => $order->getDhlWeightDetails()['packaging_weight_grams'],
            'created_at' => $order->created_at->toIso8601String(),
            'items' => $order->items->map(function ($item) {
                $productImage = null;
                if ($item->product) {
                    $path = null;
                    if (is_array($item->product->media_gallery) && count($item->product->media_gallery) > 0) {
                        $path = $item->product->media_gallery[0]['path'] ?? null;
                    }
                    if (!$path) {
                        $path = $item->product->preview_image_path;
                    }
                    if ($path) {
                        if (preg_match('/^https?:\/\//', $path)) {
                            $productImage = $path;
                        } else {
                            $productImage = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
                        }
                    }
                }
                return [
                    'id' => $item->id,
                    'product_name' => $item->product ? $item->product->name : ($item->product_name ?? 'Gelöschtes Produkt'),
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'configuration' => $item->configuration,
                    'product_image' => $productImage,
                ];
            }),
            'shipments' => $order->shipments,
            'tracking_number' => $order->tracking_number,
        ]);
    });

    // Update order status
    Route::post('/shop/orders/{id}/status', function (Request $request, $id) {
        $data = $request->validate([
            'status' => 'required|in:pending,processing,shipped,completed,cancelled,refunded',
        ]);
        
        $order = OrderOrder::with('items.product')->findOrFail($id);
        $previousStatus = $order->status;
        $previousPaymentStatus = $order->payment_status;

        if (in_array($previousStatus, ['cancelled', 'refunded'], true) && !in_array($data['status'], ['cancelled', 'refunded'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Stornierte oder erstattete Bestellungen können nicht reaktiviert werden.',
            ], 422);
        }

        // Geschäftslogische Rückabwicklung bei Stornierung oder Erstattung:
        // Vor dem Überschreiben von $order->status ausführen, damit handleCancellation() nicht am eigenen Früh-Exit scheitert.
        if (in_array($data['status'], ['cancelled', 'refunded'], true) && !in_array($previousStatus, ['cancelled', 'refunded'], true)) {
            $order->handleCancellation('API-Statusänderung', $previousStatus, $previousPaymentStatus);
        }

        $order->status = $data['status'];
        $order->save();
        
        return response()->json([
            'success' => true,
            'status' => $order->status,
            'status_color' => $order->status_color,
        ]);
    });

    // Generate DHL Label
    Route::post('/shop/orders/{id}/dhl-label', function (Request $request, $id) {
        $order = OrderOrder::findOrFail($id);

        if (in_array($order->status, ['cancelled', 'refunded'], true) || $order->payment_status === 'refunded') {
            return response()->json([
                'success' => false,
                'message' => 'Für stornierte oder erstattete Bestellungen können keine DHL-Versandlabels erstellt werden.'
            ], 400);
        }
        
        if ($order->isOnlyDigital()) {
            return response()->json([
                'success' => false,
                'message' => 'Für rein digitale Bestellungen können keine DHL-Versandlabels erstellt werden.'
            ], 400);
        }
        
        $data = $request->validate([
            'package_count' => 'required|integer|min:1|max:30',
            'weight_per_package' => 'required|numeric|min:0.1|max:31.5',
        ]);
        
        try {
            $dhlService = new \App\Services\DhlService();
            $shipments = $dhlService->createLabels($order, (int)$data['package_count'], (float)$data['weight_per_package']);
            
            // Auto update order status to shipped
            $order->update(['status' => 'shipped']);
            
            return response()->json([
                'success' => true,
                'message' => 'DHL Versandlabel(s) erfolgreich erstellt.',
                'tracking_number' => $order->refresh()->tracking_number,
                'shipments' => $order->shipments,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('DHL Label API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'DHL Versandlabel konnte nicht erstellt werden. Bitte prüfe die Versanddaten.'
            ], 500);
        }
    });

    // --- FUNKENWERK NETZWERK-LABELDRUCKER (MUNBYN ITPP941AP) QUEUE API ---
    $registerPrinterQueueRoutes = static function (string $prefix): void {
        Route::prefix($prefix)->middleware('throttle:120,1')->group(function () {
            Route::get('/jobs', function (Request $request, \App\Services\System\FunkenwerkPrintService $printService) {
                $includePdf = $request->boolean('include_pdf', true);
                return response()->json([
                    'success' => true,
                    'data' => $printService->getQueueState($includePdf),
                ])->withHeaders([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            });

            Route::post('/jobs/test', function (Request $request, \App\Services\System\FunkenwerkPrintService $printService) {
                $data = $request->validate([
                    'printer_ip' => 'nullable|string|max:45',
                ]);
                $ip = \App\Services\System\FunkenwerkPrintService::sanitizePrivateLanIp($data['printer_ip'] ?? '192.168.188.149');
                $job = $printService->enqueueTestLabel($ip);
                return response()->json([
                    'success' => true,
                    'message' => 'Test-Label in die Funkenwerk-Druckwarteschlange eingereiht.',
                    'job' => $job,
                ])->withHeaders(['Cache-Control' => 'no-store, private']);
            });

            Route::post('/jobs/clear', function (Request $request, \App\Services\System\FunkenwerkPrintService $printService) {
                $printService->clearQueue($request->boolean('include_pending', false));
                return response()->json([
                    'success' => true,
                    'message' => 'Druck-Historie bereinigt.',
                ])->withHeaders(['Cache-Control' => 'no-store, private']);
            });

            Route::post('/jobs/{jobId}/ack', function (Request $request, string $jobId, \App\Services\System\FunkenwerkPrintService $printService) {
                $data = $request->validate([
                    'status' => 'required|string|in:completed,failed,printing',
                    'error_message' => 'nullable|string|max:500',
                    'printer_ip' => 'nullable|string|max:45',
                ]);

                $job = $printService->acknowledgeJob(
                    $jobId,
                    $data['status'],
                    $data['error_message'] ?? null,
                    $data['printer_ip'] ?? null
                );

                return response()->json([
                    'success' => $job !== null,
                    'job' => $job,
                ], $job !== null ? 200 : 404)->withHeaders(['Cache-Control' => 'no-store, private']);
            })->whereUuid('jobId');

            Route::post('/jobs/{jobId}/requeue', function (string $jobId, \App\Services\System\FunkenwerkPrintService $printService) {
                $job = $printService->requeueJob($jobId);
                return response()->json([
                    'success' => $job !== null,
                    'job' => $job,
                ], $job !== null ? 200 : 404)->withHeaders(['Cache-Control' => 'no-store, private']);
            })->whereUuid('jobId');
        });
    };

    $registerPrinterQueueRoutes('printer');
    $registerPrinterQueueRoutes('funki/printer');

    Route::middleware('throttle:120,1')->group(function () {
        Route::post('/shop/orders/{id}/print-label', function (string $id, \App\Services\System\FunkenwerkPrintService $printService) {
            try {
                $order = OrderOrder::with(['items.product', 'shipments'])->findOrFail($id);
                $jobs = $printService->enqueueOrderLabel($order);
                return response()->json([
                    'success' => true,
                    'message' => count($jobs) . ' Etikett(en) an Funkenwerk übergeben.',
                    'jobs' => $jobs,
                ])->withHeaders(['Cache-Control' => 'no-store, private']);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422)->withHeaders(['Cache-Control' => 'no-store, private']);
            }
        })->whereUuid('id');
    });
});



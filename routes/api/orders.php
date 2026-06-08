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
            $searchTerm = '%' . $search . '%';
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
        
        $responseData = $orders->toArray();
        $responseData['priority_order'] = $priorityOrderData;
        
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
        
        $order = OrderOrder::findOrFail($id);
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
});


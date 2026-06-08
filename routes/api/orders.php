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
        $query = OrderOrder::with(['items.product'])->orderBy('created_at', 'desc');
        
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        
        $orders = $query->paginate(20);
        
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
            ];
        });
        
        return response()->json($orders);
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
            'created_at' => $order->created_at->toIso8601String(),
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product ? $item->product->name : ($item->product_name ?? 'Gelöschtes Produkt'),
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'configuration' => $item->configuration,
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


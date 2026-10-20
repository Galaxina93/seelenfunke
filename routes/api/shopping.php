<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementShoppingCategory;
use App\Models\Management\ManagementShoppingItem;

Route::prefix('funki/shopping')->group(function () {

    // --- CATEGORIES ---

    Route::get('/categories', function () {
        $categories = ManagementShoppingCategory::where('is_archived', false)
            ->orderBy('sort_order')
            ->get();
        return response()->json(['success' => true, 'data' => $categories]);
    });

    Route::post('/categories', function (Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string'
        ]);

        $category = ManagementShoppingCategory::create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? 'shopping-cart',
            'sort_order' => ManagementShoppingCategory::max('sort_order') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $category]);
    });

    Route::delete('/categories/{id}', function ($id) {
        $category = ManagementShoppingCategory::findOrFail($id);
        // Move items to un-categorized
        ManagementShoppingItem::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();
        return response()->json(['success' => true]);
    });

    // --- ITEMS ---

    Route::get('/items', function (Request $request) {
        $query = ManagementShoppingItem::with('category');

        if ($request->has('status') && in_array($request->status, ['needed', 'stocked'])) {
            $query->where('status', $request->status);
        }

        $items = $query->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    });

    Route::post('/items', function (Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|uuid',
            'quantity' => 'nullable|integer|min:1'
        ]);

        $cleanName = trim($data['name']);
        $quantity = $data['quantity'] ?? 1;
        $existing = ManagementShoppingItem::where('name', 'like', $cleanName)->first();

        if ($existing) {
            $existing->status = 'needed';
            $existing->quantity = $quantity;
            if (isset($data['category_id'])) {
                $existing->category_id = $data['category_id'];
            }
            $existing->save();
            $item = $existing;
        } else {
            $item = ManagementShoppingItem::create([
                'name' => $cleanName,
                'category_id' => $data['category_id'] ?? null,
                'quantity' => $quantity,
                'status' => 'needed',
            ]);
        }

        $item->load('category');
        return response()->json(['success' => true, 'data' => $item]);
    });

    Route::put('/items/{id}', function (Request $request, $id) {
        $item = ManagementShoppingItem::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|integer|min:1',
            'category_id' => 'nullable|uuid'
        ]);

        if (isset($data['name'])) {
            $item->name = trim($data['name']);
        }
        if (isset($data['quantity'])) {
            $item->quantity = max(1, (int)$data['quantity']);
        }
        if (array_key_exists('category_id', $data)) {
            $item->category_id = $data['category_id'];
        }
        $item->save();
        $item->load('category');

        return response()->json(['success' => true, 'data' => $item]);
    });

    Route::patch('/items/{id}/quantity', function (Request $request, $id) {
        $item = ManagementShoppingItem::findOrFail($id);
        $data = $request->validate([
            'delta' => 'sometimes|integer',
            'quantity' => 'sometimes|integer|min:1'
        ]);

        if (isset($data['delta'])) {
            $item->quantity = max(1, ($item->quantity ?: 1) + (int)$data['delta']);
        } elseif (isset($data['quantity'])) {
            $item->quantity = max(1, (int)$data['quantity']);
        }
        $item->save();
        $item->load('category');

        return response()->json(['success' => true, 'data' => $item]);
    });

    Route::put('/items/{id}/toggle', function ($id) {
        $item = ManagementShoppingItem::findOrFail($id);
        
        if ($item->status === 'needed') {
            $item->status = 'stocked';
            $item->last_purchased_at = now();
            $item->purchase_count++;
        } else {
            $item->status = 'needed';
        }
        $item->save();

        return response()->json(['success' => true, 'data' => $item]);
    });

    Route::delete('/items/{id}', function ($id) {
        ManagementShoppingItem::destroy($id);
        return response()->json(['success' => true]);
    });

});

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
            'category_id' => 'nullable|uuid'
        ]);

        $cleanName = trim($data['name']);
        $existing = ManagementShoppingItem::where('name', 'like', $cleanName)->first();

        if ($existing) {
            $existing->status = 'needed';
            if (isset($data['category_id'])) {
                $existing->category_id = $data['category_id'];
            }
            $existing->save();
            $item = $existing;
        } else {
            $item = ManagementShoppingItem::create([
                'name' => $cleanName,
                'category_id' => $data['category_id'] ?? null,
                'status' => 'needed',
            ]);
        }

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

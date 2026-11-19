<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function state(WishlistService $wishlistService): JsonResponse
    {
        $payload = $wishlistService->buildStatePayload();

        return response()->json([
            'ok' => true,
            'count' => $payload['count'],
            'product_ids' => $payload['product_ids'],
            'preview_items' => $payload['preview_items'],
        ]);
    }

    public function toggle(Request $request, WishlistService $wishlistService): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'string', 'max:64'],
            'active' => ['nullable', 'boolean'],
            'client_seq' => ['nullable', 'integer', 'min:0'],
        ]);

        $productId = trim((string) $validated['product_id']);
        $targetActive = array_key_exists('active', $validated) && $validated['active'] !== null
            ? (bool) $validated['active']
            : null;

        $activeNow = $wishlistService->setWishlisted($productId, $targetActive);
        $payload = $wishlistService->buildStatePayload();

        return response()->json([
            'ok' => true,
            'product_id' => $productId,
            'active' => $activeNow,
            'client_seq' => isset($validated['client_seq']) ? (int) $validated['client_seq'] : null,
            'count' => $payload['count'],
            'product_ids' => $payload['product_ids'],
            'preview_items' => $payload['preview_items'],
        ]);
    }
}

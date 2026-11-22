<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Services\AI\AiAuthHelper;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductMediaController extends Controller
{
    /**
     * Liefert geschützte saisonale Produktbilder ausschließlich dann aus,
     * wenn das Bild aktuell aktiv im Shop verwendet wird, für eine eigene
     * Bestellung/Warenkorb-Position benötigt wird oder ein berechtigter Staff-Zugriff vorliegt.
     */
    public function showSeasonal(Request $request, Product $product, string $token): BinaryFileResponse
    {
        $matchedPath = $this->resolveAuthorizedPathByToken($product, $token);
        if ($matchedPath === null) {
            abort(404);
        }

        $isStaff = auth()->guard('admin')->check()
            || AiAuthHelper::isAdmin()
            || AiAuthHelper::isEmployee();

        $cacheControl = 'private, no-store, max-age=0';

        if (!$isStaff) {
            if ($product->status !== 'active') {
                abort(404);
            }

            $activeMainMeta = $product->isAutoSeasonalMainImageEnabled()
                ? $product->resolveMainImageMeta(null, true)
                : null;

            $isActiveStorefrontImage = is_array($activeMainMeta)
                && !empty($activeMainMeta['path'])
                && $activeMainMeta['path'] === $matchedPath;

            if ($isActiveStorefrontImage) {
                $cacheControl = 'private, no-cache, must-revalidate';
            } elseif (!$this->hasCustomerPurchaseOrCartAccess($product, $matchedPath)) {
                abort(404);
            }
        }

        $baseRoot = realpath(Storage::disk('local')->path(trim(ProductTemplate::PRIVATE_SEASONAL_PREFIX, '/')));
        $absPath = realpath(Storage::disk('local')->path($matchedPath));
        if ($baseRoot === false || $absPath === false || !Str::startsWith($absPath, $baseRoot . DIRECTORY_SEPARATOR) || !is_file($absPath)) {
            abort(404);
        }

        $mimeType = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = @finfo_file($finfo, $absPath);
                @finfo_close($finfo);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    $mimeType = $detected;
                }
            }
        }

        return response()->file($absPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveAuthorizedPathByToken(Product $product, string $token): ?string
    {
        $settings = is_array($product->configurator_settings) ? $product->configurator_settings : [];
        $seasonalMap = isset($settings['seasonal_images']) && is_array($settings['seasonal_images'])
            ? $settings['seasonal_images']
            : [];

        foreach ($seasonalMap as $entry) {
            $rawPath = is_array($entry) ? ($entry['path'] ?? null) : (is_string($entry) ? $entry : null);
            if (!is_string($rawPath) || trim($rawPath) === '') {
                continue;
            }

            $clean = ltrim(str_replace('\\', '/', trim($rawPath)), '/');
            if (
                !Str::startsWith($clean, ProductTemplate::PRIVATE_SEASONAL_PREFIX)
                || str_contains($clean, '..')
                || str_contains($clean, "\0")
            ) {
                continue;
            }

            $expectedToken = ProductTemplate::makeSeasonalMediaToken((string) $product->slug, $clean);
            if (hash_equals($expectedToken, $token) && Storage::disk('local')->exists($clean)) {
                return $clean;
            }
        }

        return null;
    }

    private function hasCustomerPurchaseOrCartAccess(Product $product, string $matchedPath): bool
    {
        try {
            $cart = app(CartService::class)->getCart(false);
            if ($cart) {
                $cart->loadMissing('items');
                foreach ($cart->items as $cartItem) {
                    if ((string) $cartItem->product_id !== (string) $product->id) {
                        continue;
                    }
                    $purchasedPath = $cartItem->configuration['purchased_image_path'] ?? null;
                    if (is_string($purchasedPath) && $purchasedPath === $matchedPath) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            // Fehlertoleranz im Session-/Cart-Fallback
        }

        $customerId = auth()->guard('customer')->id();
        $sessionOrderId = session('last_order_id');

        if ($customerId || $sessionOrderId) {
            $ordersQuery = OrderOrderItem::query()
                ->where('product_id', $product->id)
                ->with('order');

            $ordersQuery->whereHas('order', function ($q) use ($customerId, $sessionOrderId) {
                $q->where(function ($inner) use ($customerId, $sessionOrderId) {
                    if ($customerId) {
                        $inner->where('customer_id', $customerId);
                    }
                    if ($sessionOrderId) {
                        $customerId ? $inner->orWhere('id', $sessionOrderId) : $inner->where('id', $sessionOrderId);
                    }
                });
            });

            foreach ($ordersQuery->get() as $orderItem) {
                $conf = is_array($orderItem->configuration) ? $orderItem->configuration : [];
                if (($conf['purchased_image_path'] ?? null) === $matchedPath) {
                    return true;
                }
                if ($orderItem->resolveDisplayImagePath() === $matchedPath) {
                    return true;
                }
            }
        }

        return false;
    }
}

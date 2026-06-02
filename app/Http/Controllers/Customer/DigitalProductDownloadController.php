<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderOrderItem;
use App\Services\AI\AiAuthHelper;
use Illuminate\Support\Facades\Storage;

class DigitalProductDownloadController extends Controller
{
    /**
     * Download a digital product file associated with an order item.
     *
     * @param  string  $itemId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($itemId)
    {
        // 1. Fetch item with order and product
        $item = OrderOrderItem::with(['order', 'product'])->findOrFail($itemId);

        // 2. Authorization check
        if (AiAuthHelper::isAdmin()) {
            // OK
        } elseif (AiAuthHelper::isCustomer() && AiAuthHelper::getCustomerId() === $item->order->customer_id) {
            // OK
        } else {
            abort(403, 'Zugriff verweigert.');
        }

        // 3. Validation
        if (!$item->product || $item->product->type !== 'digital' || empty($item->product->digital_download_path)) {
            abort(404, 'Dieses Produkt ist nicht als Download verfügbar.');
        }

        // 4. File existence check
        if (!Storage::disk('local')->exists($item->product->digital_download_path)) {
            abort(404, 'Die angeforderte Datei wurde auf dem Server nicht gefunden.');
        }

        // 5. Trigger download
        return Storage::disk('local')->download(
            $item->product->digital_download_path,
            $item->product->digital_filename ?? 'Download.zip'
        );
    }
}

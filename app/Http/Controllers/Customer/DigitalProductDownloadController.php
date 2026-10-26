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

        // 2. Authorization check (nur Admin oder interner Mitarbeiter, keine externen Partner/Influencer)
        $isAdminOrEmployee = AiAuthHelper::isAdmin()
            || auth()->guard('admin')->check()
            || AiAuthHelper::isEmployee();

        $customerUser = auth()->guard('customer')->user();
        $isVerifiedCustomerOwner = AiAuthHelper::isCustomer()
            && $customerUser
            && $customerUser->hasVerifiedEmail()
            && $item->order
            && $item->order->customer_id !== null
            && (string) AiAuthHelper::getCustomerId() === (string) $item->order->customer_id;

        if ($isAdminOrEmployee) {
            // OK
        } elseif ($isVerifiedCustomerOwner) {
            // OK
        } elseif (request()->hasValidSignature()) {
            // OK via signed route
        } else {
            abort(403, 'Zugriff verweigert.');
        }

        // 3. Payment & Order Status check: Downloads stehen erst nach Bezahlung zur Verfügung und sind bei stornierten/erstatteten Bestellungen gesperrt
        if (
            !$isAdminOrEmployee
            && (
                !$item->order
                || $item->order->payment_status !== 'paid'
                || in_array($item->order->status, ['cancelled', 'refunded'], true)
            )
        ) {
            abort(403, 'Der Download steht erst nach vollständigem Zahlungseingang zur Verfügung.');
        }

        // 4. Validation
        if (!$item->product || $item->product->type !== 'digital' || empty($item->product->digital_download_path)) {
            abort(404, 'Dieses Produkt ist nicht als Download verfügbar.');
        }

        $downloadPath = ltrim(str_replace('\\', '/', (string) $item->product->digital_download_path), '/');
        if (
            $downloadPath === ''
            || str_contains($downloadPath, '..')
            || str_contains($downloadPath, "\0")
            || (!str_starts_with($downloadPath, 'produkte/products-secure/') && !str_starts_with($downloadPath, 'products-secure/'))
        ) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        // 5. File existence & symlink check
        if (
            !Storage::disk('local')->exists($downloadPath)
            || is_link(Storage::disk('local')->path($downloadPath))
        ) {
            abort(404, 'Die angeforderte Datei wurde auf dem Server nicht gefunden.');
        }

        // 6. Trigger download with hardened headers
        $rawFilename = (string) ($item->product->digital_filename ?: 'Download.zip');
        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($rawFilename)) ?: 'Download.zip';

        return Storage::disk('local')->download(
            $downloadPath,
            $safeFilename,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }
}

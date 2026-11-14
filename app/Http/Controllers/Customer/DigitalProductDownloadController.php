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

        // 4. Resolve target file (either attached product document via ?doc=... or pure digital product file)
        $requestedDocId = trim((string) request()->query('doc', ''));
        $downloadPath = '';
        $rawFilename = 'Download.pdf';

        if ($requestedDocId !== '') {
            $matchedDoc = collect($item->resolveDigitalDocuments())->firstWhere('id', $requestedDocId);
            if (!$matchedDoc || empty($matchedDoc['path'])) {
                abort(404, 'Das angeforderte digitale Dokument wurde nicht gefunden.');
            }
            $downloadPath = ltrim(str_replace('\\', '/', (string) $matchedDoc['path']), '/');
            $ext = strtolower((string) ($matchedDoc['extension'] ?? pathinfo($downloadPath, PATHINFO_EXTENSION) ?: 'pdf'));
            $titleBase = trim((string) ($matchedDoc['title'] ?? pathinfo((string) ($matchedDoc['filename'] ?? ''), PATHINFO_FILENAME) ?: 'Dokument'));
            $rawFilename = $titleBase . '.' . $ext;
        } elseif ($item->product && $item->product->type === 'digital' && !empty($item->product->digital_download_path)) {
            $downloadPath = ltrim(str_replace('\\', '/', (string) $item->product->digital_download_path), '/');
            $rawFilename = (string) ($item->product->digital_filename ?: 'Download.zip');
        } else {
            $attachedDocs = $item->resolveDigitalDocuments();
            if (count($attachedDocs) === 1 && !empty($attachedDocs[0]['path'])) {
                $matchedDoc = $attachedDocs[0];
                $downloadPath = ltrim(str_replace('\\', '/', (string) $matchedDoc['path']), '/');
                $ext = strtolower((string) ($matchedDoc['extension'] ?? pathinfo($downloadPath, PATHINFO_EXTENSION) ?: 'pdf'));
                $titleBase = trim((string) ($matchedDoc['title'] ?? 'Dokument'));
                $rawFilename = $titleBase . '.' . $ext;
            } else {
                abort(404, 'Dieses Produkt ist nicht als Download verfügbar.');
            }
        }

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
        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($rawFilename)) ?: 'Download.pdf';

        return Storage::disk('local')->download(
            $downloadPath,
            $safeFilename,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }

    /**
     * Download an individually provided customer document uploaded by Admin.
     *
     * @param  string  $customerId
     * @param  string  $docId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadCustomerDocument(string $customerId, string $docId)
    {
        $customer = \App\Models\Customer\Customer::withTrashed()->findOrFail($customerId);

        $isAdminOrEmployee = AiAuthHelper::isAdmin()
            || auth()->guard('admin')->check()
            || AiAuthHelper::isEmployee();

        $customerUser = auth()->guard('customer')->user();
        $isCustomerOwner = AiAuthHelper::isCustomer()
            && $customerUser
            && $customerUser->hasVerifiedEmail()
            && (string) AiAuthHelper::getCustomerId() === (string) $customer->id;

        if (!$isAdminOrEmployee && !$isCustomerOwner && !request()->hasValidSignature()) {
            abort(403, 'Zugriff verweigert.');
        }

        $matchedDoc = collect($customer->getAdminUploadedDocuments())->firstWhere('id', $docId);
        if (!$matchedDoc || empty($matchedDoc['path'])) {
            abort(404, 'Das angeforderte Dokument wurde nicht gefunden.');
        }

        $downloadPath = ltrim(str_replace('\\', '/', (string) $matchedDoc['path']), '/');
        if (
            $downloadPath === ''
            || str_contains($downloadPath, '..')
            || str_contains($downloadPath, "\0")
            || !str_starts_with($downloadPath, 'produkte/products-secure/customer-files/' . $customer->id . '/')
        ) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        if (
            !Storage::disk('local')->exists($downloadPath)
            || is_link(Storage::disk('local')->path($downloadPath))
        ) {
            abort(404, 'Die angeforderte Datei wurde auf dem Server nicht gefunden.');
        }

        $ext = strtolower((string) ($matchedDoc['extension'] ?? pathinfo($downloadPath, PATHINFO_EXTENSION) ?: 'pdf'));
        $titleBase = trim((string) ($matchedDoc['title'] ?? pathinfo((string) ($matchedDoc['filename'] ?? ''), PATHINFO_FILENAME) ?: 'Dokument'));
        $rawFilename = $titleBase . '.' . $ext;
        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($rawFilename)) ?: 'Dokument.pdf';

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

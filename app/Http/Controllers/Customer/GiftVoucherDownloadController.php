<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Services\AI\AiAuthHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class GiftVoucherDownloadController extends Controller
{
    /**
     * Download the gift voucher PDF.
     *
     * @param  MarketingGiftVoucher  $voucher
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(MarketingGiftVoucher $voucher)
    {
        $voucher->loadMissing('orderItem.order');
        $item = $voucher->orderItem;

        // 1. Check if user is logged in as admin or employee
        $isAdminOrEmployee = AiAuthHelper::isAdmin()
            || Auth::guard('admin')->check()
            || Auth::guard('employee')->check();

        if (!$isAdminOrEmployee) {
            $authorized = false;

            // 2a. Valid temporary signed URL (e.g. Sofort-Download on Checkout Success page or Order Mail)
            if (request()->hasValidSignature()) {
                $authorized = true;
            } else {
                // 2b. Logged-in customer who owns the voucher (buyer or verified recipient)
                $user = Auth::guard('customer')->user();

                if ($user && $user->hasVerifiedEmail()) {
                    $isBuyer = $item
                        && $item->order
                        && $item->order->customer_id !== null
                        && (string) $item->order->customer_id === (string) $user->id;

                    $isRecipient = !empty($voucher->recipient_email)
                        && strcasecmp(trim((string) $voucher->recipient_email), trim((string) $user->email)) === 0;

                    if ($isBuyer || $isRecipient) {
                        $authorized = true;
                    }
                }
            }

            if (!$authorized) {
                abort(403, 'Zugriff verweigert.');
            }

            // 3. Payment & Activation status check (enforced for ALL non-admin accesses, including signed URLs)
            if (!$voucher->is_active) {
                abort(403, 'Dieser Gutschein ist nicht aktiv oder wurde storniert.');
            }

            if (
                $item
                && (
                    !$item->order
                    || $item->order->payment_status !== 'paid'
                    || in_array($item->order->status, ['cancelled', 'refunded'], true)
                )
            ) {
                abort(403, 'Der Gutschein steht erst nach vollständigem Zahlungseingang zum Download bereit.');
            }
        }

        // 4. Generate PDF on the fly (disable remote asset execution to prevent SSRF)
        $pdf = Pdf::loadView('pdf.marketing-gift-voucher', [
            'voucher' => $voucher,
        ])->setOptions(['isRemoteEnabled' => false]);

        $safeCode = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $voucher->code) ?: 'Gutschein';
        $filename = 'Geschenkgutschein-' . $safeCode . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingGiftVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class GiftVoucherDownloadController extends Controller
{
    /**
     * Download the gift voucher PDF.
     *
     * @param  MarketingGiftVoucher  $voucher
     * @return \Illuminate\Http\Response
     */
    public function download(MarketingGiftVoucher $voucher)
    {
        // 1. Check if user is logged in as admin or employee
        $isAdminOrEmployee = Auth::guard('admin')->check() || Auth::guard('employee')->check();

        if (!$isAdminOrEmployee) {
            // 2. Otherwise, check if user is logged in as the customer who owns the voucher
            $user = Auth::guard('customer')->user();
            
            if (!$user) {
                abort(403, 'Zugriff verweigert.');
            }

            // Security check: Either the buyer (who purchased the voucher) or the recipient (e.g. referral/milestone reward or recipient email)
            $isBuyer = false;
            $item = $voucher->orderItem;
            if ($item && $item->order && $item->order->customer_id === $user->id) {
                $isBuyer = true;
            }

            $isRecipient = false;
            if (!empty($voucher->recipient_email) && strcasecmp(trim($voucher->recipient_email), trim($user->email)) === 0) {
                $isRecipient = true;
            }

            if (!$isBuyer && !$isRecipient) {
                abort(403, 'Zugriff verweigert.');
            }

            if (!$voucher->is_active) {
                abort(403, 'Dieser Gutschein ist noch nicht aktiviert.');
            }

            if ($item && $item->order && $item->order->payment_status !== 'paid') {
                abort(403, 'Der Gutschein steht erst nach vollständigem Zahlungseingang zum Download bereit.');
            }
        }

        // Generate PDF on the fly (disable remote asset execution to prevent SSRF)
        $pdf = Pdf::loadView('pdf.marketing-gift-voucher', [
            'voucher' => $voucher,
        ])->setOptions(['isRemoteEnabled' => false]);

        return $pdf->download('Geschenkgutschein-' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $voucher->code) . '.pdf');
    }
}

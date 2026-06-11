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

            // Security check: Only the buyer (customer) who matches the order customer_id
            $item = $voucher->orderItem;
            if (!$item || !$item->order || $item->order->customer_id !== $user->id) {
                abort(403, 'Zugriff verweigert.');
            }
        }

        // Generate PDF on the fly
        $pdf = Pdf::loadView('pdf.marketing-gift-voucher', [
            'voucher' => $voucher,
        ]);

        return $pdf->download('Geschenkgutschein-' . $voucher->code . '.pdf');
    }
}

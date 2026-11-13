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

        // 1. Check if user is logged in as admin or internal employee
        $isAdminOrEmployee = AiAuthHelper::isAdmin()
            || Auth::guard('admin')->check()
            || AiAuthHelper::isEmployee();

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

                // 2c. Logged-in Influencer who received this specific voucher as a Creator payout (+20% bonus)
                $employeeUser = Auth::guard('employee')->user();
                if (!$authorized && $employeeUser && $employeeUser->isInfluencer()) {
                    $isCreatorRecipient = !empty($voucher->recipient_email)
                        && strcasecmp(trim((string) $voucher->recipient_email), trim((string) $employeeUser->email)) === 0;
                    if ($isCreatorRecipient) {
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

        $safeCode = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $voucher->code) ?: 'Gutschein';
        $cardColor = ($voucher->card_color ?? ($item->configuration['card_color'] ?? 'gold')) === 'black' ? 'black' : 'gold';

        // 4a. Optionaler Laser-SVG-Export (86 x 54 mm Gold-/Schwarz-Aluminiumkarte für xTool Studio) für Admin / Produktion
        if ($isAdminOrEmployee && request()->query('format') === 'laser_svg') {
            $svgContent = view('pdf.marketing-gift-voucher-laser-svg', [
                'voucher' => $voucher,
                'cardColor' => $cardColor,
            ])->render();

            $colorTag = $cardColor === 'black' ? 'SCHWARZ' : 'GOLD';
            $svgFilename = 'Laser-Gutschein-' . $colorTag . '-86x54mm-' . $safeCode . '.svg';

            return response(ltrim($svgContent), 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $svgFilename . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        // 4b. Generate PDF on the fly (disable remote asset execution to prevent SSRF)
        $pdf = Pdf::loadView('pdf.marketing-gift-voucher', [
            'voucher' => $voucher,
            'cardColor' => $cardColor,
        ])->setOptions(['isRemoteEnabled' => false]);

        $filename = 'Geschenkgutschein-' . $safeCode . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}

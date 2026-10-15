<?php

namespace App\Http\Controllers;

use App\Models\Customer\Customer;
use Illuminate\Http\Request;

class CartReminderUnsubscribeController extends Controller
{
    /**
     * Verarbeitet den One-Click-Widerspruch gegen Warenkorb-Erinnerungen (§ 7 Abs. 3 UWG / Art. 21 DSGVO).
     */
    public function unsubscribe(Request $request, string $id, string $hash)
    {
        $customer = Customer::with('profile')->find($id);

        if (!$customer || !$customer->profile) {
            return view('frontend.cart.reminder-unsubscribed', [
                'status' => 'error',
                'message' => 'Der angeforderte Abmeldelink ist ungültig oder das Kundenkonto existiert nicht mehr.',
            ]);
        }

        // Kryptografische Prüfung des Hashs zur Verhinderung unautorisierter Abmeldungen
        if (!hash_equals($customer->getCartReminderUnsubscribeHash(), $hash)) {
            return view('frontend.cart.reminder-unsubscribed', [
                'status' => 'error',
                'message' => 'Dieser Abmeldelink ist ungültig oder wurde manipuliert.',
            ]);
        }

        $alreadyUnsubscribed = $customer->profile->cart_reminder_unsubscribed_at !== null;

        if (!$alreadyUnsubscribed) {
            $customer->profile->update([
                'cart_reminder_unsubscribed_at' => now(),
            ]);
        }

        return view('frontend.cart.reminder-unsubscribed', [
            'status' => 'success',
            'customer' => $customer,
            'alreadyUnsubscribed' => $alreadyUnsubscribed,
        ]);
    }
}

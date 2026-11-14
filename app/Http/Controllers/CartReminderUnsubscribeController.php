<?php

namespace App\Http\Controllers;

use App\Models\Cart\Cart;
use App\Models\Customer\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartReminderUnsubscribeController extends Controller
{
    /**
     * Öffnet den gespeicherten Warenkorb aus der Erinnerungs-E-Mail.
     * Ist die 2-Stunden-Sitzung nach 24h bereits abgelaufen, wird die Ziel-URL (/warenkorb)
     * und die E-Mail-Adresse freundlich für den Kunden-Login vorgemerkt (ohne unsicheren Auto-Login).
     */
    public function resume(Request $request, string $id, string $hash)
    {
        $cart = Cart::with('customer')->find($id);

        if (!$cart || !$cart->customer_id || !$cart->customer || (method_exists($cart->customer, 'trashed') && $cart->customer->trashed())) {
            return redirect()->route('cart');
        }

        if (!hash_equals($cart->getReminderResumeHash(), $hash)) {
            return redirect()->route('cart');
        }

        if (Auth::guard('customer')->check()) {
            return redirect()->route('cart');
        }

        session([
            'url.intended' => route('cart', [], false),
            'login_prefill_email' => $cart->customer->email,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Schön, dass du wieder da bist! Melde dich kurz an, damit wir deinen gespeicherten Warenkorb direkt für dich öffnen können.');
    }

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

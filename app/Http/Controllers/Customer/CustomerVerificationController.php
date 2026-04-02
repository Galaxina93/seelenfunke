<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class CustomerVerificationController extends Controller
{
    /**
     * Mark the authenticated customer's email address as verified.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @param  string  $hash
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verify(Request $request, $id, $hash)
    {
        $user = Customer::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Ungültiger oder abgelaufener Link.');
        }

        // SICHERHEITS-FIX: Wir updaten gezielt alle Profile dieses Kunden,
        // falls durch alte Tests Duplikate in der Datenbank hängen!
        CustomerProfile::where('customer_id', $user->id)
            ->update(['email_verified_at' => now()]);

        if (! $user->hasVerifiedEmail()) {
            event(new Verified($user));
        }

        return redirect()->route('login')->with('status', 'Deine E-Mail-Adresse wurde erfolgreich bestätigt! Du kannst dich jetzt einloggen.');
    }

    /**
     * Check if a request has a valid signature manually without middleware if needed,
     * or rely on the route middleware ['signed']. We use route middleware here.
     */
}

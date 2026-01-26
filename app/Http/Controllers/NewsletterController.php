<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function verify($token)
    {
        $subscriber = NewsletterSubscriber::where('verification_token', $token)->first();

        if (!$subscriber) {
            return redirect()->route('newsletter.page')->with('error', 'Dieser Bestätigungslink ist ungültig oder abgelaufen.');
        }

        $subscriber->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verification_token' => null, // Token entwerten
        ]);

        return redirect()->route('newsletter.page')->with('verified', 'Vielen Dank! Deine E-Mail-Adresse wurde erfolgreich bestätigt.');
    }
}

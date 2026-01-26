<?php

namespace App\Livewire\Shop;

use App\Mail\NewsletterVerificationMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Illuminate\Support\Str;

class NewsletterSignup extends Component
{
    public $email = '';
    public $privacy_accepted = false;
    public $success = false;

    protected $rules = [
        'email' => 'required|email|unique:newsletter_subscribers,email',
        'privacy_accepted' => 'accepted', // Laravel Validation Rule: Muss "true", "yes", "1" sein
    ];

    protected $messages = [
        'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
        'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
        'email.unique' => 'Du bist bereits für den Newsletter angemeldet.',
        'privacy_accepted.accepted' => 'Bitte akzeptiere die Datenschutzbestimmungen.',
    ];

    public function subscribe()
    {
        // 1. Validierung (Nimm 'unique' hier RAUS, damit wir manuell weitermachen können)
        $this->validate([
            'email' => 'required|email', // KEIN 'unique' hier!
            'privacy_accepted' => 'accepted',
        ]);

        // 2. Prüfen, ob es den Nutzer schon gibt
        $subscriber = NewsletterSubscriber::where('email', $this->email)->first();

        if (! $subscriber) {
            // FALL 1: Nutzer ist neu -> Anlegen
            $subscriber = NewsletterSubscriber::create([
                'email' => $this->email,
                'ip_address' => request()->ip(),
                'privacy_accepted' => true,
                'is_verified' => false,
                'verification_token' => Str::random(32),
            ]);
        } else {
            // FALL 2: Nutzer existiert schon
            // Wir aktualisieren nur IP und Datenschutz, lassen den Rest aber intakt
            $subscriber->update([
                'ip_address' => request()->ip(),
                'privacy_accepted' => true,
            ]);

            // Optional: Wenn er noch NICHT verifiziert war, neues Token generieren
            if (! $subscriber->is_verified) {
                $subscriber->update(['verification_token' => Str::random(32)]);
            }
        }

        // 3. E-Mail senden (passiert in beiden Fällen)
        // Nur senden, wenn noch nicht verifiziert. Wenn schon verifiziert, sparen wir uns die Mail.
        if (! $subscriber->is_verified) {
            try {
                Mail::to($this->email)->send(new NewsletterVerificationMail($subscriber));
            } catch (\Exception $e) {
                // Fehler ins Log schreiben (storage/logs/laravel.log)
                \Illuminate\Support\Facades\Log::error('Newsletter Versand Fehler: ' . $e->getMessage());

                // Optional: Fehler direkt anzeigen (nur zum Testen!)
                // $this->addError('email', 'Versandfehler: ' . $e->getMessage());
                // return;
            }
        }

        // 4. Erfolgsmeldung anzeigen
        $this->success = true;
        $this->reset(['email', 'privacy_accepted']);
    }

    public function render()
    {
        return view('livewire.shop.newsletter-signup');
    }
}

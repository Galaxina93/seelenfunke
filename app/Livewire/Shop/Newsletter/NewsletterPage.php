<?php

namespace App\Livewire\Shop\Newsletter;

use App\Mail\NewsletterVerificationMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class NewsletterPage extends Component
{
    public $email = '';
    public $privacy_accepted = false;

    // UI State
    public $activeTab = 'subscribe'; // 'subscribe' oder 'unsubscribe'
    public $successMessage = '';

    protected $rules = [
        'email' => 'required|email',
        'privacy_accepted' => 'accepted',
    ];

    protected $messages = [
        'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
        'email.email' => 'Das ist keine gültige E-Mail-Adresse.',
        'privacy_accepted.accepted' => 'Bitte stimme den Datenschutzbestimmungen zu.',
    ];

    // --- ANMELDEN ---
    public function subscribe()
    {
        $this->validate([
            'email' => 'required|email|unique:newsletter_subscribers,email',
            'privacy_accepted' => 'accepted'
        ], [
            'email.unique' => 'Diese E-Mail ist bereits registriert. Prüfe ggf. deinen Spam-Ordner für die Bestätigung.'
        ]);

        $token = Str::random(32);

        $subscriber = NewsletterSubscriber::create([
            'email' => $this->email,
            'ip_address' => request()->ip(),
            'privacy_accepted' => true,
            'is_verified' => false,
            'verification_token' => $token
        ]);

        // E-Mail senden
        try {
            Mail::to($this->email)->send(new NewsletterVerificationMail($subscriber));
            $this->successMessage = 'Fast geschafft! Wir haben dir eine Bestätigungs-E-Mail gesendet.';
            $this->reset(['email', 'privacy_accepted']);
        } catch (\Exception $e) {
            $this->addError('email', 'E-Mail konnte nicht gesendet werden. Bitte versuche es später.');
        }
    }

    // --- ABMELDEN ---
    public function unsubscribe()
    {
        // Für Abmeldung ist Datenschutz-Häkchen technisch nicht zwingend, aber E-Mail Validation
        $this->validate(['email' => 'required|email']);

        $subscriber = NewsletterSubscriber::where('email', $this->email)->first();

        if ($subscriber) {
            // Hard Delete oder Soft Delete, je nach Migration.
            // Bei SoftDelete (falls im Model aktiviert) bleibt Historie erhalten.
            $subscriber->delete();

            $this->successMessage = 'Du wurdest erfolgreich aus dem Verteiler ausgetragen. Schade, dass du gehst!';
        } else {
            // Aus Sicherheitsgründen (Enumeration Attacks) geben wir oft auch "Erfolg" zurück,
            // oder eine neutrale Meldung. Hier sind wir ehrlich:
            $this->addError('email', 'Diese E-Mail-Adresse ist uns nicht bekannt.');
        }

        $this->reset(['email']);
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->reset(['email', 'privacy_accepted', 'successMessage']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.shop.newsletter.newsletter-page');
    }
}

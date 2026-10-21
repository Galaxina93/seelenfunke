<?php

namespace App\Livewire\Shop\Marketing;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Mail\NewsletterVerificationMail;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class MarketingNewsletterPage extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

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
            'email' => 'required|email',
            'privacy_accepted' => 'accepted'
        ]);

        $subscriber = MarketingNewsletterSubscriber::where('email', $this->email)->first();

        if ($subscriber && $subscriber->is_verified) {
            $this->addError('email', 'Diese E-Mail-Adresse ist bereits für unseren Newsletter angemeldet.');
            return;
        }

        $token = Str::random(32);

        if (!$subscriber) {
            $subscriber = MarketingNewsletterSubscriber::create([
                'email' => $this->email,
                'ip_address' => request()->ip(),
                'privacy_accepted' => true,
                'is_verified' => false,
                'verification_token' => $token
            ]);
        } else {
            $subscriber->update([
                'ip_address' => request()->ip(),
                'privacy_accepted' => true,
                'verification_token' => $token,
            ]);
        }

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
        $this->validate(['email' => 'required|email']);

        $subscriber = MarketingNewsletterSubscriber::where('email', $this->email)->first();

        if ($subscriber) {
            $subscriber->delete();
        }

        // Einheitliche Erfolgsmeldung zum Schutz vor User-Enumeration
        $this->successMessage = 'Falls diese E-Mail-Adresse in unserem Verteiler eingetragen war, wurde sie erfolgreich abgemeldet.';

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
        return view('livewire.shop.marketing.marketing-newsletter-page')->layout('components.layouts.frontend_layout');
    }
}

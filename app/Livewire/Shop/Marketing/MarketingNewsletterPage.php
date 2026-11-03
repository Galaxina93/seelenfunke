<?php

namespace App\Livewire\Shop\Marketing;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Mail\NewsletterVerificationMail;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

class MarketingNewsletterPage extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Marketing';

    public $email = '';
    public $privacy_accepted = false;

    // Differenzierte E-Mail-Auswahl beim Abmelden / Anpassen
    public bool $unsub_newsletter = true;
    public bool $unsub_birthday = true;
    public bool $unsub_anniversary = true;

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

    /**
     * Im Frontend nutzt die Seelenpost-Verwaltung immer das warme Manufaktur-Gold (#C5A059)
     * statt des internen Backend-Lila der Marketing-Abteilung.
     */
    public function getThemeColorHexProperty(): string
    {
        return '#C5A059';
    }

    public function mount(): void
    {
        self::ensurePreferenceColumns();

        $tab = request()->query('tab');
        $type = request()->query('type');
        $queryEmail = request()->query('email');

        if ($tab === 'unsubscribe' || in_array($type, ['birthday', 'anniversary', 'newsletter', 'all'], true)) {
            $this->activeTab = 'unsubscribe';
        }

        if (is_string($queryEmail) && filter_var(trim($queryEmail), FILTER_VALIDATE_EMAIL)) {
            $this->email = trim($queryEmail);
        }

        if ($type === 'birthday') {
            $this->unsub_newsletter = false;
            $this->unsub_birthday = true;
            $this->unsub_anniversary = false;
        } elseif ($type === 'anniversary') {
            $this->unsub_newsletter = false;
            $this->unsub_birthday = false;
            $this->unsub_anniversary = true;
        } elseif ($type === 'newsletter') {
            $this->unsub_newsletter = true;
            $this->unsub_birthday = false;
            $this->unsub_anniversary = false;
        }
    }

    public static function ensurePreferenceColumns(): void
    {
        try {
            if (Schema::hasTable('customer_profiles') && !Schema::hasColumn('customer_profiles', 'receive_birthday_emails')) {
                Schema::table('customer_profiles', function (Blueprint $table) {
                    if (!Schema::hasColumn('customer_profiles', 'receive_birthday_emails')) {
                        $table->boolean('receive_birthday_emails')->default(true);
                    }
                    if (!Schema::hasColumn('customer_profiles', 'receive_anniversary_emails')) {
                        $table->boolean('receive_anniversary_emails')->default(true);
                    }
                });
            }

            if (Schema::hasTable('marketing_newsletter_subscribers') && !Schema::hasColumn('marketing_newsletter_subscribers', 'receive_newsletter')) {
                Schema::table('marketing_newsletter_subscribers', function (Blueprint $table) {
                    if (!Schema::hasColumn('marketing_newsletter_subscribers', 'receive_newsletter')) {
                        $table->boolean('receive_newsletter')->default(true);
                    }
                    if (!Schema::hasColumn('marketing_newsletter_subscribers', 'receive_birthday_emails')) {
                        $table->boolean('receive_birthday_emails')->default(true);
                    }
                    if (!Schema::hasColumn('marketing_newsletter_subscribers', 'receive_anniversary_emails')) {
                        $table->boolean('receive_anniversary_emails')->default(true);
                    }
                });
            }
        } catch (\Throwable $e) {
            // Nicht-blockierend
        }
    }

    // --- ANMELDEN ---
    public function subscribe()
    {
        $this->validate([
            'email' => 'required|email|max:255',
            'privacy_accepted' => 'accepted',
        ]);

        $ip = request()->ip();
        $throttleKey = 'newsletter_page_sub_' . $ip;
        try {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
                \App\Services\System\SystemSecurityService::logEvent(
                    'security:newsletter_flood',
                    'Newsletter-Seiten-Flooding gedrosselt',
                    "Zu viele Newsletter-Anmeldungen von IP {$ip}.",
                    'warning',
                    ['ip_address' => $ip]
                );
                $this->addError('email', "Zu viele Anfragen. Bitte warte {$seconds} Sekunden.");
                return;
            }
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 600);
        } catch (\Throwable $e) {
            // Falls ein flüchtiger File-Cache-Verzeichnis-Lock auftritt, Anmeldung nicht blockieren
        }

        self::ensurePreferenceColumns();

        $subscriber = MarketingNewsletterSubscriber::where('email', $this->email)->first();

        if ($subscriber && $subscriber->is_verified) {
            $subscriber->update([
                'receive_newsletter' => true,
                'receive_birthday_emails' => true,
                'receive_anniversary_emails' => true,
            ]);
            $this->syncCustomerEmailPreferences($this->email, true, true);

            // Schutz vor User-Enumeration: Gleiche Erfolgsmeldung ohne erneuten Mail-Versand
            $this->successMessage = 'Fast geschafft! Wir haben dir eine Bestätigungs-E-Mail gesendet.';
            $this->reset(['email', 'privacy_accepted']);
            return;
        }

        $token = Str::random(32);

        if (!$subscriber) {
            $subscriber = MarketingNewsletterSubscriber::create([
                'email' => $this->email,
                'ip_address' => request()->ip(),
                'privacy_accepted' => true,
                'is_verified' => false,
                'verification_token' => $token,
                'receive_newsletter' => true,
                'receive_birthday_emails' => true,
                'receive_anniversary_emails' => true,
            ]);
        } else {
            $subscriber->update([
                'ip_address' => request()->ip(),
                'privacy_accepted' => true,
                'verification_token' => $token,
                'receive_newsletter' => true,
                'receive_birthday_emails' => true,
                'receive_anniversary_emails' => true,
            ]);
        }

        $this->syncCustomerEmailPreferences($this->email, true, true);

        // E-Mail senden
        try {
            Mail::to($this->email)->send(new NewsletterVerificationMail($subscriber));
            $this->successMessage = 'Fast geschafft! Wir haben dir eine Bestätigungs-E-Mail gesendet.';
            $this->reset(['email', 'privacy_accepted']);
        } catch (\Exception $e) {
            $this->addError('email', 'E-Mail konnte nicht gesendet werden. Bitte versuche es später.');
        }
    }

    // --- DIFFERENZIERT ODER KOMPLETT ABMELDEN ---
    public function unsubscribe()
    {
        $this->validate(['email' => 'required|email|max:255']);

        if (!$this->unsub_newsletter && !$this->unsub_birthday && !$this->unsub_anniversary) {
            $this->addError('unsub_selection', 'Bitte wähle mindestens eine E-Mail-Kategorie aus oder klicke auf „Alles komplett abstellen“.');
            return;
        }

        $ip = request()->ip();
        $throttleKey = 'newsletter_unsub_' . $ip;
        try {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
                $this->addError('email', "Zu viele Anfragen. Bitte warte {$seconds} Sekunden.");
                return;
            }
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 600);
        } catch (\Throwable $e) {
            // Falls ein flüchtiger File-Cache-Verzeichnis-Lock auftritt, Abmeldung nicht blockieren
        }

        self::ensurePreferenceColumns();

        $subscriber = MarketingNewsletterSubscriber::where('email', $this->email)->first();

        if ($subscriber) {
            if ($this->unsub_newsletter) {
                // Eigentliche News abbestellt -> Abonnent aus dem Newsletter-Verteiler löschen
                $subscriber->delete();
            } else {
                // Nur Geburtstags- oder Jubiläums-Mails abbestellt, eigentlicher Newsletter bleibt aktiv
                $updates = [];
                if ($this->unsub_birthday) {
                    $updates['receive_birthday_emails'] = false;
                }
                if ($this->unsub_anniversary) {
                    $updates['receive_anniversary_emails'] = false;
                }
                if (!empty($updates)) {
                    $subscriber->update($updates);
                }
            }
        }

        // Kundenprofil synchronisieren (Geburtstags-Funke & Vorfreude-Funken zum Kauf-Jubiläum)
        $normalizedEmail = trim((string) $this->email);
        if (\Illuminate\Support\Facades\Auth::guard('customer')->check()
            && strcasecmp(trim((string) \Illuminate\Support\Facades\Auth::guard('customer')->user()->email), $normalizedEmail) === 0) {
            $this->syncCustomerEmailPreferences(
                $normalizedEmail,
                $this->unsub_birthday ? false : null,
                $this->unsub_anniversary ? false : null
            );
        }

        // Einheitliche Erfolgsmeldung zum Schutz vor User-Enumeration
        $this->successMessage = 'Falls diese E-Mail-Adresse in unserem Verteiler eingetragen war, wurde sie erfolgreich abgemeldet.';

        $this->reset(['email']);
    }

    /**
     * Komfort-Aktion: Alle 3 Kategorien (News, Geburtstags-Funke, Kauf-Jubiläum) auf einmal blockieren.
     */
    public function unsubscribeAll(): void
    {
        $this->unsub_newsletter = true;
        $this->unsub_birthday = true;
        $this->unsub_anniversary = true;
        $this->unsubscribe();
    }

    private function syncCustomerEmailPreferences(string $email, ?bool $receiveBirthday, ?bool $receiveAnniversary): void
    {
        try {
            $normalizedEmail = trim($email);
            if (!\Illuminate\Support\Facades\Auth::guard('customer')->check()
                || strcasecmp(trim((string) \Illuminate\Support\Facades\Auth::guard('customer')->user()->email), $normalizedEmail) !== 0) {
                return;
            }

            $customer = Customer::where('email', $normalizedEmail)->first();
            if (!$customer) {
                return;
            }

            $profile = $customer->profile ?: $customer->profile()->firstOrCreate([]);
            $changes = [];

            if ($receiveBirthday !== null) {
                $oldVal = (bool) ($profile->receive_birthday_emails ?? true);
                if ($oldVal !== $receiveBirthday) {
                    $profile->receive_birthday_emails = $receiveBirthday;
                    $changes['receive_birthday_emails'] = ['old' => $oldVal, 'new' => $receiveBirthday];
                }
            }

            if ($receiveAnniversary !== null) {
                $oldVal = (bool) ($profile->receive_anniversary_emails ?? true);
                if ($oldVal !== $receiveAnniversary) {
                    $profile->receive_anniversary_emails = $receiveAnniversary;
                    $changes['receive_anniversary_emails'] = ['old' => $oldVal, 'new' => $receiveAnniversary];
                }
            }

            if (!empty($changes)) {
                $profile->save();
                $customer->setRelation('profile', $profile);
                app(\App\Services\Gamification\GamificationService::class)->recordUserProfileChangeLog(
                    $customer,
                    $changes,
                    'Kunde (Seelenpost-Verwaltung)'
                );
            }
        } catch (\Throwable $e) {
            // Nicht-blockierend
        }
    }

    public function switchTab($tab)
    {
        if (!in_array($tab, ['subscribe', 'unsubscribe'], true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->reset(['email', 'privacy_accepted', 'successMessage']);
        $this->resetValidation();
    }

    public function render()
    {
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $city = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
        return view('livewire.shop.marketing.marketing-newsletter-page')
            ->title("Seelenfunken-Post Newsletter & Exklusive Vorteile | {$companyName}")
            ->layout('components.layouts.frontend_layout', [
                'meta_description' => "Melde dich für die Seelenfunken-Post an: Erhalte exklusive Einblicke in unsere Manufaktur in {$city}, Geschenk-Inspirationen und limitierte Aktionen.",
                'canonical' => route('newsletter.page'),
            ]);
    }
}

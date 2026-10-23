<?php

namespace App\Livewire\Frontend;

use App\Traits\handleMailsTrait;
use Livewire\Component;

class ContactForm extends Component
{
    use handleMailsTrait;

    // WICHTIG: Variablen initialisieren
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';
    public string $message = '';
    public bool $data_protection = false;
    public string $honey_name = ''; // Honeypot für Spam-Bots

    public function render()
    {
        return view('livewire.frontend.contact-form');
    }

    public function sending(): void
    {
        // 1. Honeypot Check: Bots füllen das unsichtbare Feld aus
        if (!empty($this->honey_name)) {
            \Illuminate\Support\Facades\Log::warning("Spam-Bot Kontaktformular blockiert (Honeypot ausgelöst) von IP: " . request()->ip());
            \App\Services\System\SystemSecurityService::logEvent(
                'security:honeypot_triggered',
                'Spam-Bot am Kontaktformular abgefangen',
                "Honeypot-Feld im Kontaktformular wurde von IP " . request()->ip() . " ausgefüllt.",
                'warning',
                ['ip_address' => request()->ip()]
            );
            $this->reset(['first_name', 'last_name', 'email', 'phone', 'message', 'data_protection', 'honey_name']);
            session()->flash('message', 'Vielen Dank! Deine Anfrage wurde erfolgreich empfangen. Du erhältst in Kürze eine Bestätigung mit deiner Ticketnummer per E-Mail.');
            return;
        }

        // 2. Rate-Limiting gegen Flooding & E-Mail-Bombing
        $ip = request()->ip();
        $throttleKey = 'contact_form_send_' . $ip;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);
            \App\Services\System\SystemSecurityService::logEvent(
                'security:rate_limit_exceeded',
                'Kontaktformular Rate-Limit ausgelöst',
                "Zu viele Kontaktanfragen von IP {$ip}.",
                'warning',
                ['ip_address' => $ip]
            );
            session()->flash('message', "Zu viele Anfragen von diesem Anschluss. Bitte warte {$minutes} Minute(n).");
            return;
        }

        // 3. Sicherheits-Validierung, bevor Mails gesendet werden
        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|min:5|max:5000',
            'data_protection' => 'accepted'
        ]);

        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 600);

        // 1. Speichere das Ticket nativ in der Datenbank
        $request = \App\Models\Support\SupportContactRequest::create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => 'Kontaktanfrage über Webseite',
            'category' => 'contact_form',
            'status' => 'new',
            'message' => $this->message,
        ]);

        // 2. Initiale Nachricht anhängen (damit eine saubere Chat-Historie startet)
        $request->messages()->create([
            'sender_type' => 'customer',
            'message' => $this->message,
        ]);

        // 3. Bestätigungs-Mail inkl. Ticket-Nummer direkt an den Kunden senden!
        $emailData = [
            'to' => $this->email,
            'subject' => 'Eingangsbestätigung: Deine Anfrage (' . $request->ticket_number . ') wurde empfangen',
            'viewTemplate' => 'global.mails.contact-form-confirmation',
            'ticket_number' => $request->ticket_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];

        try {
            $this->sendMail($emailData);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ContactForm Mail Error: ' . $e->getMessage());
        }

        // Sauberes Zurücksetzen aller Werte nach erfolgreichem Versand
        $this->reset(['first_name', 'last_name', 'email', 'phone', 'message', 'data_protection']);

        session()->flash('message', 'Vielen Dank! Deine Anfrage wurde erfolgreich empfangen. Du erhältst in Kürze eine Bestätigung mit deiner Ticketnummer per E-Mail.');
    }
}

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

    public function render()
    {
        return view('livewire.frontend.contact-form');
    }

    public function sending(): void
    {
        // Sicherheits-Validierung, bevor Mails gesendet werden
        $this->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'message' => 'required',
            'data_protection' => 'accepted'
        ]);

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

<?php

namespace App\Livewire\Global\Widgets;

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
        return view('livewire.global.widgets.contact-form');
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

        $emailData = [
            'to' => env('MAIL_FROM_ADDRESS', 'kontakt@mein-seelenfunke.de'),
            'subject' => 'Kontaktanfrage über Webseite',
            'viewTemplate' => 'global.mails.contact-form',
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message
        ];

        $this->sendMail($emailData);

        // Sauberes Zurücksetzen aller Werte nach erfolgreichem Versand
        $this->reset(['first_name', 'last_name', 'email', 'phone', 'message', 'data_protection']);

        session()->flash('message', 'Ihre Nachricht wurde erfolgreich versendet.');
    }
}

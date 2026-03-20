<?php

namespace App\Livewire\Frontend;

use App\Mail\RevocationConfirmationMail;
use App\Models\Shop\Revocation\Revocation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class RevocationForm extends Component
{
    public $name = '';
    public $email = '';
    public $order_number = '';
    public $items = ''; // Optional
    
    public $isSubmitted = false;

    protected $rules = [
        'name' => 'required|string|min:2|max:255',
        'email' => 'required|email|max:255',
        'order_number' => 'required|string|min:3|max:50',
        'items' => 'nullable|string|max:1000',
    ];

    protected $messages = [
        'name.required' => 'Bitte geben Sie Ihren vollständigen Namen an.',
        'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
        'email.email' => 'Die E-Mail-Adresse ist ungültig.',
        'order_number.required' => 'Bitte geben Sie die Bestellnummer an, um den Vertrag zuzuordnen.',
    ];

    public function submitRevocation()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'order_number' => $this->order_number,
            'items' => $this->items,
            'timestamp' => now()->format('d.m.Y H:i:s'),
        ];

        try {
            // In der Datenbank speichern
            Revocation::create($data);

            // Gesetzlich vorgeschriebene Eingangsbestätigung (Mail an Kunde)
            Mail::to($this->email)->send(new RevocationConfirmationMail($data));
            
            // Intern für das Protokoll speichern/benachrichtigen (Mail an Betreiber)
            $adminEmail = shop_setting('owner_email') ?? 'kontakt@mein-seelenfunke.de';
            Mail::to($adminEmail)->send(new \App\Mail\RevocationAdminNotificationMail($data));
            
            Log::info("Widerruf eingegangen und in DB gespeichert für Bestellung: {$this->order_number} von {$this->email}");

        } catch (\Exception $e) {
            Log::error("Widerrufsbestätigung konnte nicht gesendet werden: " . $e->getMessage());
        }

        $this->isSubmitted = true;
    }

    public function render()
    {
        return view('livewire.frontend.revocation-form');
    }
}

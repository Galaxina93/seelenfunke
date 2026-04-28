<?php

namespace App\Livewire\Shop\Order;

use App\Mail\RevocationMailToCustomer;
use App\Models\Order\OrderRevocation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;

class OrderRevocationForm extends Component
{
    use WithFileUploads;
    public $name = '';
    public $email = '';
    public $order_number = '';
    public $items = ''; // Optional
    public $attachments = []; // Optional

    public $isSubmitted = false;

    protected $rules = [
        'name' => 'required|string|min:2|max:255',
        'email' => 'required|email|max:255',
        'order_number' => 'required|string|min:3|max:50',
        'items' => 'nullable|string|max:1000',
        'attachments' => 'nullable|array|max:2',
        'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
    ];

    protected $messages = [
        'name.required' => 'Bitte geben Sie Ihren vollständigen Namen an.',
        'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
        'email.email' => 'Die E-Mail-Adresse ist ungültig.',
        'order_number.required' => 'Bitte geben Sie die Bestellnummer an, um den Vertrag zuzuordnen.',
        'attachments.max' => 'Es sind maximal 2 Dateien erlaubt. Überzählige Dateien wurden automatisch entfernt.',
        'attachments.*.mimes' => 'Nur JPG, PNG oder PDF Dateien sind erlaubt.',
        'attachments.*.max' => 'Eine Datei darf maximal 5 MB groß sein.',
        'attachments.*.uploaded' => 'Upload fehlgeschlagen: Die Datei ist zu groß oder beschädigt.',
        'attachments.0.uploaded' => 'Fehler bei Datei 1: Die Datei ist zu groß oder das Format wird nicht unterstützt.',
        'attachments.1.uploaded' => 'Fehler bei Datei 2: Die Datei ist zu groß oder das Format wird nicht unterstützt.',
        'attachments.2.uploaded' => 'Fehler bei Datei 3: Die Datei ist zu groß oder das Format wird nicht unterstützt.',
        'attachments.3.uploaded' => 'Fehler bei Datei 4: Die Datei ist zu groß oder das Format wird nicht unterstützt.',
        'attachments.4.uploaded' => 'Fehler bei Datei 5: Die Datei ist zu groß oder das Format wird nicht unterstützt.',
    ];

    public function updatedAttachments()
    {
        // 1. Array-Limit von maximal 2 Dateien prüfen
        if (is_array($this->attachments) && count($this->attachments) > 2) {
            $this->addError('attachments', 'Es sind maximal 2 Dateien erlaubt. Überzählige Dateien wurden automatisch entfernt.');
            $this->attachments = array_slice($this->attachments, 0, 2);
        }

        // 2. Einzelne Dateien prüfen und sofort aussortieren, falls fehlerhaft
        $validAttachments = [];
        foreach ($this->attachments as $index => $file) {
            $isValid = true;

            // Check: 5MB in Bytes = 5242880
            if ($file->getSize() > 5242880) {
                $this->addError("attachments", "Datei '" . $file->getClientOriginalName() . "' ist zu groß (Max. 5 MB) und wurde entfernt.");
                $isValid = false;
            } else {
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                    $this->addError("attachments", "Format von '" . $file->getClientOriginalName() . "' ungültig (nur JPG, PNG, PDF). Wurde entfernt.");
                    $isValid = false;
                }
            }

            // Nur behalten, wenn sie unsere Anforderungen zu 100% erfüllt
            if ($isValid) {
                $validAttachments[] = $file;
            }
        }

        // 3. State updaten: Die Vorschau rendert jetzt nur noch die gültigen Bilder!
        $this->attachments = $validAttachments;
    }

    public function removeAttachment($index)
    {
        if (isset($this->attachments[$index])) {
            unset($this->attachments[$index]);
            $this->attachments = array_values($this->attachments);
        }
    }

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
            $revocation = OrderRevocation::create($data);

            // Datei-Uploads verarbeiten (Try/Catch in Try/Catch für non-fatal Errors)
            try {
                if (!empty($this->attachments)) {
                    $savedAttachments = [];
                    foreach ($this->attachments as $file) {
                        // Speichere in storage/app/bestellungen/private/revocations/{id} gesichert ab
                        $path = $file->store("bestellungen/private/revocations/{$revocation->id}", 'local');
                        if ($path) {
                            $savedAttachments[] = $path;
                        }
                    }
                    if (count($savedAttachments) > 0) {
                        $revocation->update(['attachments' => $savedAttachments]);
                        $data['attachments'] = $savedAttachments; // Für Mails falls nötig
                    }
                }
            } catch (\Exception $fileEx) {
                Log::error("Widerruf Dateien konnten nicht gespeichert werden: " . $fileEx->getMessage());
                // Widerruf ist trotzdem gültig, wir brechen hier nicht ab.
            }

            // Gesetzlich vorgeschriebene Eingangsbestätigung (Mail an Kunde)
            Mail::to($this->email)->send(new RevocationMailToCustomer($data));

            // Intern für das Protokoll speichern/benachrichtigen (Mail an Betreiber)
            $adminEmail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
            Mail::to($adminEmail)->send(new \App\Mail\RevocationMailToAdmin($data));

            Log::info("Widerruf eingegangen und in DB gespeichert für Bestellung: {$this->order_number} von {$this->email}");

        } catch (\Exception $e) {
            Log::error("Widerrufsbestätigung konnte nicht gesendet/gespeichert werden: " . $e->getMessage());
        }

        $this->isSubmitted = true;
    }

    public function render()
    {
        return view('livewire.shop.order.order-revocation-form');
    }
}

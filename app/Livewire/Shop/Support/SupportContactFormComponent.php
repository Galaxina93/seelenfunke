<?php

namespace App\Livewire\Shop\Support;

use App\Models\Support\SupportContactRequest;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Traits\handleMailsTrait;

#[Layout('components.layouts.backend_layout')]
class SupportContactFormComponent extends Component
{
    use WithPagination;
    use handleMailsTrait; // Zum Versenden der Admin-Antworten an den Kunden

    public $statusFilter = '';
    public $search = '';
    public $selectedRequestId = null;
    public $replyMessage = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updateStatus($id, $newStatus)
    {
        $validStatuses = ['new', 'in_progress', 'waiting_for_customer', 'resolved'];
        if (in_array($newStatus, $validStatuses)) {
            SupportContactRequest::where('id', $id)->update(['status' => $newStatus]);
        }
    }

    public function openRequest($id)
    {
        if ($this->selectedRequestId === $id) {
            $this->closeRequest();
            return;
        }

        $this->selectedRequestId = $id;
        $req = SupportContactRequest::find($id);
        if ($req && $req->status === 'new') {
            $req->update(['status' => 'in_progress']);
        }
    }

    public function closeRequest()
    {
        $this->selectedRequestId = null;
        $this->replyMessage = '';
    }

    public function insertCannedResponse($type)
    {
        $req = SupportContactRequest::find($this->selectedRequestId);
        if (!$req) return;

        $name = $req->first_name;

        if ($type === 'busy') {
            $this->replyMessage = "Hallo {$name},\n\nvielen Dank für deine Nachricht!\nAufgrund eines sehr hohen Aufkommens an Anfragen verzögert sich unsere Antwortzeit momentan leicht. Wir haben dein Anliegen aber auf dem Schirm und melden uns in Kürze ausführlich bei dir.\n\nHerzliche Grüße,\nDein Seelenfunke Team";
        } elseif ($type === 'details') {
            $this->replyMessage = "Hallo {$name},\n\ndanke für deine Anfrage.\nUm dir bestmöglich und schnell weiterhelfen zu können, benötigen wir noch ein paar kleine Details von dir. Kannst du uns hierzu noch [BITTE AUSFÜLLEN] verraten?\n\nHerzliche Grüße,\nDein Seelenfunke Team";
        } elseif ($type === 'calculator') {
            $url = url('/kalkulator');
            $this->replyMessage = "Hallo {$name},\n\nwir können dir dein Projekt sehr gerne umsetzen!\nAm schnellsten und genauesten geht es, wenn du unseren interaktiven Gravur-Kalkulator nutzt. Dort siehst du auch sofort den Live-Preis:\n\n👉 {$url}\n\nBei Rückfragen antworte einfach auf diese E-Mail.\n\nHerzliche Grüße,\nDein Seelenfunke Team";
        }
    }

    public function sendReply()
    {
        $this->validate(['replyMessage' => 'required']);

        $req = SupportContactRequest::findOrFail($this->selectedRequestId);
        
        // 1. Speichern in der DB
        $req->messages()->create([
            'sender_type' => 'admin',
            'message' => $this->replyMessage
        ]);

        $req->update(['status' => 'waiting_for_customer']);

        // 2. Offizielle E-Mail Antwort an den Kunden senden mit Zitat-Verlauf
        $emailData = [
            'to' => $req->email,
            'subject' => 'Re: Deine Anfrage (' . $req->ticket_number . ')',
            'viewTemplate' => 'global.mails.contact-form-reply', // Muss erstellt werden falls gewünscht
            'ticket_number' => $req->ticket_number,
            'first_name' => $req->first_name,
            'last_name' => $req->last_name,
            'replyMessage' => $this->replyMessage
        ];

        try {
            $this->sendMail($emailData);
            $this->dispatch('saved-reply');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ContactForm Reply Error: ' . $e->getMessage());
            $this->dispatch('error-reply');
        }
        
        $this->replyMessage = '';
    }
    
    public function markResolved()
    {
        if ($this->selectedRequestId) {
            SupportContactRequest::where('id', $this->selectedRequestId)->update(['status' => 'resolved']);
            $this->closeRequest();
            $this->dispatch('request-resolved');
        }
    }

    public function render()
    {
        $query = SupportContactRequest::query()->orderBy('created_at', 'desc');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('ticket_number', 'like', "%{$this->search}%")
                  ->orWhere('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('subject', 'like', "%{$this->search}%");
            });
        }

        $requests = $query->paginate(15);
        $selectedRequest = $this->selectedRequestId ? SupportContactRequest::with('messages')->find($this->selectedRequestId) : null;

        return view('livewire.shop.support.support-contact-form-component', [
            'requests' => $requests,
            'selectedRequest' => $selectedRequest
        ]);
    }
}

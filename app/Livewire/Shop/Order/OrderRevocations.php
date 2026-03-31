<?php

namespace App\Livewire\Shop\Order;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Order\OrderRevocation;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class OrderRevocations extends Component
{
    use WithPagination, WithDepartmentTheming;

    protected string $themingDepartment = 'Bestellungen';

    public function markAsProcessed($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update([
            'status' => 'processed'
        ]);

        \Illuminate\Support\Facades\Mail::to($revocation->email)
            ->send(new \App\Mail\Order\RevocationProcessedMail($revocation));

        $this->dispatch('toast', message: 'Abwicklung abgeschlossen und Erfolgs-Mail gesendet.', type: 'success');
    }

    public function markAsPending($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update(['status' => 'pending']);
        $this->dispatch('toast', message: 'Vorgang wieder geöffnet.', type: 'info');
    }

    public function markLegalCheck($id, $type)
    {
        if (!in_array($type, ['personalized', 'standard'])) return;

        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update([
            'legal_check_at' => now(),
            'product_type' => $type
        ]);
        
        $typeLabel = $type === 'personalized' ? 'Personalisierte Ware' : 'Standard Produkt';
        $this->dispatch('toast', message: 'Rechtliche Prüfung abgeschlossen (' . $typeLabel . ').', type: 'success');
    }

    public function undoLegalCheck($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update([
            'legal_check_at' => null,
            'product_type' => null,
            'customer_notified_at' => null,
            'status' => 'pending',
            'rejection_reason' => null
        ]);
        $this->dispatch('toast', message: 'Rechtliche Prüfung zurückgesetzt.', type: 'info');
    }

    public function markCustomerNotified($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update(['customer_notified_at' => now()]);
        $this->dispatch('toast', message: 'Kundenkommunikation protokolliert.', type: 'success');
    }

    public function undoCustomerNotified($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update([
            'customer_notified_at' => null,
            'status' => 'pending', // Just in case it was declined
            'rejection_reason' => null
        ]);
        $this->dispatch('toast', message: 'Kundenkommunikation zurückgesetzt.', type: 'info');
    }

    public function rejectRevocation($id, $reason)
    {
        if (!in_array($reason, ['personalized', 'damaged', 'expired', 'other'])) {
            $reason = 'other';
        }

        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update([
            'customer_notified_at' => now(),
            'status' => 'declined', // We set the overall status to declined, meaning it is done.
            'rejection_reason' => $reason
        ]);
        
        \Illuminate\Support\Facades\Mail::to($revocation->email)
            ->send(new \App\Mail\Order\RevocationRejectedMail($revocation));

        $this->dispatch('toast', message: 'Widerruf offiziell abgelehnt. Kunde wurde per E-Mail benachrichtigt.', type: 'error');
    }

    public function deleteRevocation($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        
        if (!empty($revocation->attachments)) {
            \Illuminate\Support\Facades\Storage::disk('private')->deleteDirectory("revocations/{$revocation->id}");
        }
        
        $revocation->delete();
        $this->dispatch('toast', message: 'Widerruf vollständig gelöscht.', type: 'info');
    }

    public function render()
    {
        $revocations = OrderRevocation::latest()->paginate(10);

        return view('livewire.shop.order.order-revocations', [
            'revocations' => $revocations
        ]);
    }
}

<?php

namespace App\Livewire\Shop\Order;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Order\OrderRevocation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class OrderRevocations extends Component
{
    use WithPagination, WithDepartmentTheming;

    public string $themingDepartment = 'Bestellungen';

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function markAsProcessed($id)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $revocation = OrderRevocation::findOrFail($id);
        if ($revocation->status === 'processed') {
            return;
        }
        $revocation->update([
            'status' => 'processed'
        ]);

        // Verknüpfte Bestellung und zugehörige Rechnungen GoBD-konform stornieren (nur bei verifizierter E-Mail-Übereinstimmung)
        $order = $revocation->linked_order;
        $orderCancelled = false;
        $emailMismatchWarning = false;
        if ($order) {
            $revEmail = trim((string) $revocation->email);
            $orderEmail = trim((string) $order->email);
            $customerEmail = trim((string) ($order->customer->email ?? ''));
            $emailMatches = $revEmail !== '' && (strcasecmp($revEmail, $orderEmail) === 0 || ($customerEmail !== '' && strcasecmp($revEmail, $customerEmail) === 0));

            if ($emailMatches) {
                $previousStatus = $order->status;
                $previousPaymentStatus = $order->payment_status;

                if (!in_array($order->status, ['cancelled', 'refunded'], true) && $order->payment_status !== 'refunded') {
                    $order->handleCancellation('Widerruf akzeptiert und abgewickelt', $previousStatus, $previousPaymentStatus);
                }

                $updateData = [
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Widerruf akzeptiert und abgewickelt',
                ];
                if ($previousPaymentStatus === 'paid') {
                    $updateData['payment_status'] = 'refunded';
                }
                // OrderOrder::booted() static::updated storniert aktive Rechnungen (inkl. GoBD-Stornobeleg) automatisch
                $order->update($updateData);

                $invoiceService = app(\App\Services\InvoiceService::class);
                $remainingActiveInvoices = $order->invoices()
                    ->where('type', 'invoice')
                    ->whereNotIn('status', ['cancelled', 'draft'])
                    ->get();
                foreach ($remainingActiveInvoices as $inv) {
                    try {
                        $invoiceService->cancelInvoice($inv);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Revocation invoice cancellation warning: ' . $e->getMessage());
                    }
                }

                $orderCancelled = true;
            } else {
                $emailMismatchWarning = true;
            }
        }

        \Illuminate\Support\Facades\Mail::to($revocation->email)
            ->send(new \App\Mail\Order\RevocationProcessedMail($revocation));

        if ($orderCancelled) {
            $toastMsg = 'Abwicklung abgeschlossen, Bestellung & Rechnung GoBD-konform storniert und Erfolgs-Mail gesendet.';
        } elseif ($emailMismatchWarning) {
            $toastMsg = 'Widerruf abgeschlossen & Mail gesendet (Hinweis: E-Mail wich von der Bestellung ab, bitte Bestellung manuell prüfen).';
        } else {
            $toastMsg = 'Widerruf abgeschlossen & Mail gesendet (Hinweis: Keine passende Bestellung zur Nummer gefunden).';
        }

        $this->dispatch('toast', message: $toastMsg, type: 'success');
    }

    public function markAsPending($id)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update(['status' => 'pending']);
        $this->dispatch('toast', message: 'Vorgang wieder geöffnet.', type: 'info');
    }

    public function markLegalCheck($id, $type)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

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
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

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
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update(['customer_notified_at' => now()]);
        $this->dispatch('toast', message: 'Kundenkommunikation protokolliert.', type: 'success');
    }

    public function undoCustomerNotified($id)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

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
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

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
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren dürfen gesetzliche Widerrufsbelege endgültig löschen.');
        }

        $revocation = OrderRevocation::findOrFail($id);
        
        if (!empty($revocation->attachments)) {
            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("bestellungen/private/revocations/{$revocation->id}");
            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("revocations/{$revocation->id}");
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

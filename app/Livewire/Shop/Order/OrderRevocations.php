<?php

namespace App\Livewire\Shop\Order;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Order\OrderRevocation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class OrderRevocations extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Bestellungen';

    public function markAsProcessed($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update(['status' => 'processed']);
    }

    public function markAsPending($id)
    {
        $revocation = OrderRevocation::findOrFail($id);
        $revocation->update(['status' => 'pending']);
    }

    public function render()
    {
        $revocations = OrderRevocation::latest()->get();

        return view('livewire.shop.order.order-revocations', [
            'revocations' => $revocations
        ]);
    }
}

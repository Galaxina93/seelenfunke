<?php

namespace App\Livewire\Shop\Order;

use Livewire\Attributes\Layout;

use App\Models\Order\Revocation\Revocation;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class OrderRevocations extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Bestellungen';

    public function markAsProcessed($id)
    {
        $revocation = Revocation::findOrFail($id);
        $revocation->update(['status' => 'processed']);
    }

    public function markAsPending($id)
    {
        $revocation = Revocation::findOrFail($id);
        $revocation->update(['status' => 'pending']);
    }

    public function render()
    {
        $revocations = Revocation::latest()->get();

        return view('livewire.shop.order.order-revocations', [
            'revocations' => $revocations
        ]);
    }
}

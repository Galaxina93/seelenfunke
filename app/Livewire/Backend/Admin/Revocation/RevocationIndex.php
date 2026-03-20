<?php

namespace App\Livewire\Backend\Admin\Revocation;

use App\Models\Shop\Revocation\Revocation;
use Livewire\Component;

class RevocationIndex extends Component
{
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

        return view('livewire.backend.admin.revocation.revocation-index', [
            'revocations' => $revocations
        ]);
    }
}

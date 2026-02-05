<?php

namespace App\Livewire\Shop\newsletter;

use App\Models\NewsletterSubscriber;
use Livewire\Component;
use Livewire\WithPagination;

class Newsletter extends Component
{
    use WithPagination;

    public $search = '';

    public function delete($id)
    {
        NewsletterSubscriber::find($id)->delete();
        session()->flash('success', 'Abonnent gelöscht.');
    }

    public function render()
    {
        $subscribers = NewsletterSubscriber::query()
            ->where('email', 'like', '%' . $this->search . '%')
            ->latest()
            ->paginate(10);

        return view('livewire.shop.newsletter.newsletter', [
            'subscribers' => $subscribers
        ]);
    }
}

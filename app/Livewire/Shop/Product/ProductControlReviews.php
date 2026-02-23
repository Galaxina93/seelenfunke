<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\ProductReview;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class ProductControlReviews extends Component
{
    use WithPagination;

    public $filterStatus = 'pending';
    public $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function approve($id)
    {
        ProductReview::findOrFail($id)->update(['status' => 'approved']);
        session()->flash('success', 'Bewertung wurde erfolgreich veröffentlicht.');
    }

    public function reject($id)
    {
        ProductReview::findOrFail($id)->update(['status' => 'rejected']);
        session()->flash('warning', 'Bewertung wurde abgelehnt und versteckt.');
    }

    public function deleteReview($id)
    {
        $review = ProductReview::findOrFail($id);

        if (!empty($review->media)) {
            foreach ($review->media as $media) {
                Storage::disk('public')->delete($media);
            }
        }

        $review->delete();
        session()->flash('danger', 'Bewertung wurde endgültig gelöscht.');
    }

    public function render()
    {
        $query = ProductReview::with(['product', 'customer'])->latest();

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('content', 'like', '%' . $this->search . '%')
                    ->orWhereHas('customer', function($c) {
                        $c->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('product', function($p) {
                        $p->where('name', 'like', '%' . $this->search . '%');
                    });
            });
        }

        return view('livewire.shop.product.product-control-reviews', [
            'reviews' => $query->paginate(15)
        ]);
    }
}

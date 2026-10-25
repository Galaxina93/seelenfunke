<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

use App\Models\Product\ProductReview;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ProductControlReviews extends Component
{
    use WithPagination, WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    #[Url]
    public $filterStatus = 'pending';

    #[Url]
    public $search = '';

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        ProductReview::findOrFail($id)->update(['status' => 'approved']);
        session()->flash('success', 'Bewertung wurde erfolgreich veröffentlicht.');
    }

    public function reject($id)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        ProductReview::findOrFail($id)->update(['status' => 'rejected']);
        session()->flash('warning', 'Bewertung wurde abgelehnt und versteckt.');
    }

    public function deleteReview($id)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check()) {
            abort(403);
        }

        $review = ProductReview::findOrFail($id);

        if (!empty($review->media) && is_array($review->media)) {
            foreach ($review->media as $media) {
                if (is_string($media) && str_starts_with($media, 'produkte/reviews/') && !str_contains($media, '..') && Storage::disk('public')->exists($media)) {
                    Storage::disk('public')->delete($media);
                }
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
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function($q) use ($escapedSearch) {
                $q->where('title', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('content', 'like', '%' . $escapedSearch . '%')
                    ->orWhereHas('customer', function($c) use ($escapedSearch) {
                        $c->where('first_name', 'like', '%' . $escapedSearch . '%')
                            ->orWhere('last_name', 'like', '%' . $escapedSearch . '%');
                    })
                    ->orWhereHas('product', function($p) use ($escapedSearch) {
                        $p->where('name', 'like', '%' . $escapedSearch . '%');
                    });
            });
        }

        return view('livewire.shop.product.product-control-reviews', [
            'reviews' => $query->paginate(15)
        ]);
    }
}

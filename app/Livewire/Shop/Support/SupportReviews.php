<?php

namespace App\Livewire\Shop\Support;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Support\SupportExperienceReview;
use App\Services\AI\AiAuthHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class SupportReviews extends Component
{
    use WithDepartmentTheming;
    use WithPagination;

    public string $themingDepartment = 'Support';

    #[Url]
    public string $filterStatus = 'all';

    #[Url]
    public string $filterArea = 'all';

    #[Url]
    public string $filterRating = 'all';

    #[Url]
    public string $search = '';

    #[Locked]
    public ?string $detailReviewId = null;

    public function boot(): void
    {
        $this->ensureAuthorized();
    }

    public function mount(): void
    {
        $this->ensureAuthorized();

        if (!in_array($this->filterStatus, ['all', 'open', 'done'], true)) {
            $this->filterStatus = 'all';
        }

        if (!in_array($this->filterArea, array_merge(['all'], SupportExperienceReview::AREAS), true)) {
            $this->filterArea = 'all';
        }

        if (!in_array($this->filterRating, ['all', 'low', '1', '2', '3', '4', '5'], true)) {
            $this->filterRating = 'all';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterArea(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRating(): void
    {
        $this->resetPage();
    }

    public function openDetails(string $id): void
    {
        $this->ensureAuthorized();

        $review = SupportExperienceReview::find($id);
        if (!$review) {
            $this->detailReviewId = null;
            return;
        }

        $this->detailReviewId = (string) $review->id;
    }

    public function closeDetails(): void
    {
        $this->detailReviewId = null;
    }

    public function toggleStatus(string $id): void
    {
        $this->ensureAuthorized();

        $review = SupportExperienceReview::findOrFail($id);
        $nextStatus = $review->status === SupportExperienceReview::STATUS_OPEN
            ? SupportExperienceReview::STATUS_DONE
            : SupportExperienceReview::STATUS_OPEN;

        $review->update([
            'status' => $nextStatus,
            'resolved_at' => $nextStatus === SupportExperienceReview::STATUS_DONE ? now() : null,
        ]);
    }

    public function deleteReview(string $id): void
    {
        $this->ensureAuthorized();

        $review = SupportExperienceReview::find($id);
        if (!$review) {
            return;
        }

        if ($this->detailReviewId === (string) $review->id) {
            $this->detailReviewId = null;
        }

        $review->delete();
    }

    protected function ensureAuthorized(): void
    {
        if (Auth::guard('admin')->check() || AiAuthHelper::isAdmin()) {
            return;
        }

        if (AiAuthHelper::isExternalPartner() || !AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function render()
    {
        $this->ensureAuthorized();

        $areaKpis = [];
        $openLowRatingCount = 0;

        if (Schema::hasTable('support_experience_reviews')) {
            foreach (SupportExperienceReview::AREAS as $area) {
                $count = SupportExperienceReview::where('area', $area)->count();
                $avg = $count > 0
                    ? round((float) SupportExperienceReview::where('area', $area)->avg('rating'), 1)
                    : 0.0;

                $areaKpis[$area] = [
                    'area' => $area,
                    'label' => SupportExperienceReview::areaLabel($area),
                    'avg' => $avg,
                    'count' => $count,
                ];
            }

            $openLowRatingCount = SupportExperienceReview::where('status', SupportExperienceReview::STATUS_OPEN)->count();
        } else {
            foreach (SupportExperienceReview::AREAS as $area) {
                $areaKpis[$area] = [
                    'area' => $area,
                    'label' => SupportExperienceReview::areaLabel($area),
                    'avg' => 0.0,
                    'count' => 0,
                ];
            }
        }

        $query = SupportExperienceReview::with('customer')->latest('updated_at');

        if ($this->filterStatus !== 'all' && in_array($this->filterStatus, ['open', 'done'], true)) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterArea !== 'all' && in_array($this->filterArea, SupportExperienceReview::AREAS, true)) {
            $query->where('area', $this->filterArea);
        }

        if ($this->filterRating === 'low') {
            $query->where('rating', '<=', 3);
        } elseif (in_array($this->filterRating, ['1', '2', '3', '4', '5'], true)) {
            $query->where('rating', (int) $this->filterRating);
        }

        $trimmedSearch = trim($this->search);
        if ($trimmedSearch !== '') {
            $escaped = addcslashes($trimmedSearch, '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('comment', 'like', '%' . $escaped . '%')
                    ->orWhere('product_name', 'like', '%' . $escaped . '%')
                    ->orWhereHas('customer', function ($cq) use ($escaped) {
                        $cq->withTrashed()
                            ->where('first_name', 'like', '%' . $escaped . '%')
                            ->orWhere('last_name', 'like', '%' . $escaped . '%')
                            ->orWhere('email', 'like', '%' . $escaped . '%');
                    });
            });
        }

        $detailReview = $this->detailReviewId
            ? SupportExperienceReview::with('customer')->find($this->detailReviewId)
            : null;

        return view('livewire.shop.support.support-reviews', [
            'areaKpis' => $areaKpis,
            'openLowRatingCount' => $openLowRatingCount,
            'reviews' => $query->paginate(20),
            'detailReview' => $detailReview,
        ]);
    }
}

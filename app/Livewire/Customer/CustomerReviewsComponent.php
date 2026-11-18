<?php

namespace App\Livewire\Customer;

use App\Livewire\Traits\ManagesCustomerProductReviewForm;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\Support\SupportExperienceReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.customer_layout')]
class CustomerReviewsComponent extends Component
{
    use ManagesCustomerProductReviewForm;

    #[Url]
    public string $activeTab = 'products';

    #[Locked]
    public ?string $targetProductId = null;

    /**
     * @var array<string, int>
     */
    public array $experienceRatings = [
        SupportExperienceReview::AREA_CONFIGURATOR => 0,
        SupportExperienceReview::AREA_FUNKENREICH => 0,
        SupportExperienceReview::AREA_CALCULATOR => 0,
    ];

    /**
     * @var array<string, string>
     */
    public array $experienceComments = [
        SupportExperienceReview::AREA_CONFIGURATOR => '',
        SupportExperienceReview::AREA_FUNKENREICH => '',
        SupportExperienceReview::AREA_CALCULATOR => '',
    ];

    /**
     * @var array<string, bool>
     */
    public array $experienceSaved = [
        SupportExperienceReview::AREA_CONFIGURATOR => false,
        SupportExperienceReview::AREA_FUNKENREICH => false,
        SupportExperienceReview::AREA_CALCULATOR => false,
    ];

    public function boot(): void
    {
        $this->resolveEligibleCustomerOrAbort();
    }

    public function mount(): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        if (!in_array($this->activeTab, ['products', 'experience'], true)) {
            $this->activeTab = 'products';
        }

        $this->loadExperienceState($customer);
    }

    public function updatedActiveTab(): void
    {
        if (!in_array($this->activeTab, ['products', 'experience'], true)) {
            $this->activeTab = 'products';
        }
    }

    public function switchTab(string $tab): void
    {
        if (in_array($tab, ['products', 'experience'], true)) {
            $this->activeTab = $tab;
            $this->resetErrorBag();
        }
    }

    public function startCreateProductReview(string $productId): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        $unreviewedProducts = $this->getUnreviewedPurchasedPhysicalProducts($customer);
        if (!$unreviewedProducts->contains(fn (Product $p) => (string) $p->id === $productId)) {
            return;
        }

        $this->resetReviewFormState();
        $this->targetProductId = $productId;
    }

    public function startEditProductReview(string $reviewId): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        $review = ProductReview::where('id', $reviewId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$review) {
            return;
        }

        $this->loadReviewIntoForm($review);
        $this->targetProductId = (string) $review->product_id;
    }

    public function cancelEdit(): void
    {
        $this->resetReviewFormState();
        $this->targetProductId = null;
    }

    public function cancelEditProductReview(): void
    {
        $this->cancelEdit();
    }

    public function cancelCreateProductReview(): void
    {
        $this->cancelEdit();
    }

    public function submitReview(): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        if (!$this->targetProductId) {
            return;
        }

        if ($this->isEditing) {
            if (
                !$this->userReviewId
                || !ProductReview::where('id', $this->userReviewId)
                    ->where('customer_id', $customer->id)
                    ->where('product_id', $this->targetProductId)
                    ->exists()
            ) {
                $this->cancelEdit();
                return;
            }
        } else {
            $eligibleProduct = $this->getUnreviewedPurchasedPhysicalProducts($customer)
                ->first(fn (Product $p) => (string) $p->id === $this->targetProductId);

            if (!$eligibleProduct) {
                $this->cancelEdit();
                return;
            }
        }

        $saved = $this->persistCustomerProductReview($this->targetProductId);
        if ($saved !== null) {
            $this->targetProductId = null;
        }
    }

    public function deleteProductReview(string $reviewId): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        $review = ProductReview::where('id', $reviewId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$review) {
            return;
        }

        $wasActiveTarget = ((string) $this->userReviewId === (string) $review->id)
            || ((string) $this->targetProductId === (string) $review->product_id);

        $this->destroyCustomerProductReview($review);

        if ($wasActiveTarget) {
            $this->targetProductId = null;
        }
    }

    public function rateExperience(string $area, int $rating): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        if (!in_array($area, SupportExperienceReview::AREAS, true) || $rating < 1 || $rating > 5) {
            return;
        }

        $deviceType = SupportExperienceReview::detectDeviceType(request()->userAgent());
        $this->experienceRatings[$area] = $rating;

        if ($rating >= 4) {
            $saved = SupportExperienceReview::upsertForCustomer(
                customer: $customer,
                area: $area,
                rating: $rating,
                comment: null,
                commentProvided: true,
                keepExistingComment: false,
                deviceType: $deviceType,
            );

            $this->experienceRatings[$area] = (int) $saved->rating;
            $this->experienceComments[$area] = '';
            $this->experienceSaved[$area] = true;
            return;
        }

        $saved = SupportExperienceReview::upsertForCustomer(
            customer: $customer,
            area: $area,
            rating: $rating,
            comment: null,
            commentProvided: false,
            keepExistingComment: true,
            deviceType: $deviceType,
        );

        $this->experienceRatings[$area] = (int) $saved->rating;
        $this->experienceComments[$area] = (string) ($saved->comment ?? '');
        $this->experienceSaved[$area] = false;
    }

    public function saveExperienceFeedback(string $area): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        if (!in_array($area, SupportExperienceReview::AREAS, true)) {
            return;
        }

        $rating = (int) ($this->experienceRatings[$area] ?? 0);
        if ($rating < 1 || $rating > 5) {
            return;
        }

        $deviceType = SupportExperienceReview::detectDeviceType(request()->userAgent());
        $rawComment = (string) ($this->experienceComments[$area] ?? '');

        $saved = SupportExperienceReview::upsertForCustomer(
            customer: $customer,
            area: $area,
            rating: $rating,
            comment: $rawComment,
            commentProvided: true,
            keepExistingComment: false,
            deviceType: $deviceType,
        );

        $this->experienceRatings[$area] = (int) $saved->rating;
        $this->experienceComments[$area] = (string) ($saved->comment ?? '');
        $this->experienceSaved[$area] = true;
    }

    protected function resolveEligibleCustomerOrAbort(): Customer
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (!$customer instanceof Customer || !SupportExperienceReview::isCustomerEligible($customer)) {
            abort(403, 'Zugriff nur für verifizierte Kunden.');
        }

        return $customer;
    }

    protected function loadExperienceState(Customer $customer): void
    {
        if (!Schema::hasTable('support_experience_reviews')) {
            return;
        }

        $existing = SupportExperienceReview::where('customer_id', $customer->id)
            ->get()
            ->keyBy('area');

        foreach (SupportExperienceReview::AREAS as $area) {
            /** @var SupportExperienceReview|null $row */
            $row = $existing->get($area);
            $this->experienceRatings[$area] = $row ? (int) $row->rating : 0;
            $this->experienceComments[$area] = $row ? (string) ($row->comment ?? '') : '';
            $this->experienceSaved[$area] = false;
        }
    }

    /**
     * @return Collection<int, Product>
     */
    protected function getUnreviewedPurchasedPhysicalProducts(Customer $customer): Collection
    {
        $reviewedProductIds = ProductReview::where('customer_id', $customer->id)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->all();

        $revokedOrderNumbers = [];
        if (Schema::hasTable('order_revocations')) {
            $revokedOrderNumbers = OrderRevocation::where('status', '!=', 'rejected')
                ->whereNotNull('order_number')
                ->pluck('order_number')
                ->map(fn ($num) => strtoupper(trim(ltrim((string) $num, '#'))))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $orders = OrderOrder::with(['items.product'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['completed', 'shipped', 'processing', 'pending'])
            ->where('payment_status', 'paid')
            ->latest()
            ->get()
            ->reject(function (OrderOrder $order) use ($revokedOrderNumbers) {
                $normalized = strtoupper(trim(ltrim((string) $order->order_number, '#')));
                return $normalized !== '' && in_array($normalized, $revokedOrderNumbers, true);
            });

        $productsById = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product instanceof Product) {
                    continue;
                }
                if (($product->type ?? 'physical') !== 'physical') {
                    continue;
                }
                $pid = (string) $product->id;
                if (in_array($pid, $reviewedProductIds, true) || isset($productsById[$pid])) {
                    continue;
                }
                $productsById[$pid] = $product;
            }
        }

        return collect(array_values($productsById));
    }

    public function render()
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        $productReviews = ProductReview::with('product')
            ->where('customer_id', $customer->id)
            ->latest()
            ->get();

        $unreviewedProducts = $this->getUnreviewedPurchasedPhysicalProducts($customer);

        $activeFormProductName = null;
        if ($this->targetProductId) {
            $matchedFromReviews = $productReviews
                ->first(fn (ProductReview $r) => (string) $r->product_id === $this->targetProductId)
                ?->product;
            $matchedFromUnreviewed = $unreviewedProducts
                ->first(fn (Product $p) => (string) $p->id === $this->targetProductId);

            $activeFormProductName = $matchedFromReviews?->name
                ?? $matchedFromUnreviewed?->name
                ?? Product::find($this->targetProductId)?->name;
        }

        $experienceCards = [
            [
                'area' => SupportExperienceReview::AREA_CONFIGURATOR,
                'title' => SupportExperienceReview::AREA_LABELS[SupportExperienceReview::AREA_CONFIGURATOR],
                'icon' => 'adjustments',
            ],
            [
                'area' => SupportExperienceReview::AREA_FUNKENREICH,
                'title' => SupportExperienceReview::AREA_LABELS[SupportExperienceReview::AREA_FUNKENREICH],
                'icon' => 'sparkles',
            ],
            [
                'area' => SupportExperienceReview::AREA_CALCULATOR,
                'title' => SupportExperienceReview::AREA_LABELS[SupportExperienceReview::AREA_CALCULATOR],
                'icon' => 'calculator',
            ],
        ];

        return view('livewire.customer.customer-reviews-component', [
            'productReviews' => $productReviews,
            'unreviewedProducts' => $unreviewedProducts,
            'activeFormProductName' => $activeFormProductName,
            'experienceCards' => $experienceCards,
        ]);
    }
}

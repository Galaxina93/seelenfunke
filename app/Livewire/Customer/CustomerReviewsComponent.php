<?php

namespace App\Livewire\Customer;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\Support\SupportExperienceReview;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.customer_layout')]
class CustomerReviewsComponent extends Component
{
    #[Url]
    public string $activeTab = 'products';

    #[Locked]
    public ?string $editingProductReviewId = null;

    public int $editProductRating = 5;
    public string $editProductTitle = '';
    public string $editProductContent = '';

    #[Locked]
    public ?string $creatingProductId = null;

    public int $newProductRating = 5;
    public string $newProductTitle = '';
    public string $newProductContent = '';

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

    public function startEditProductReview(string $reviewId): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        $review = ProductReview::where('id', $reviewId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$review) {
            return;
        }

        $this->resetErrorBag();
        $this->creatingProductId = null;
        $this->editingProductReviewId = (string) $review->id;
        $this->editProductRating = max(1, min(5, (int) $review->rating));
        $this->editProductTitle = (string) ($review->title ?? '');
        $this->editProductContent = (string) $review->content;
    }

    public function cancelEditProductReview(): void
    {
        $this->editingProductReviewId = null;
        $this->editProductRating = 5;
        $this->editProductTitle = '';
        $this->editProductContent = '';
        $this->resetErrorBag();
    }

    public function saveProductReview(): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        if (!$this->editingProductReviewId) {
            return;
        }

        $review = ProductReview::where('id', $this->editingProductReviewId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $this->validate([
            'editProductRating' => ['required', 'integer', 'min:1', 'max:5'],
            'editProductTitle' => ['nullable', 'string', 'max:255'],
            'editProductContent' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'editProductRating.required' => 'Bitte wähle eine Sterne-Bewertung aus.',
            'editProductContent.required' => 'Bitte schreibe mindestens 10 Zeichen.',
            'editProductContent.min' => 'Bitte schreibe mindestens 10 Zeichen.',
        ]);

        $cleanTitle = trim(strip_tags($this->editProductTitle));
        $cleanContent = trim(strip_tags($this->editProductContent));
        if (mb_strlen($cleanContent) < 10) {
            $this->addError('editProductContent', 'Bitte schreibe mindestens 10 Zeichen.');
            return;
        }

        $newRating = max(1, min(5, (int) $this->editProductRating));
        $hasChanged = ((int) $review->rating !== $newRating)
            || (trim((string) ($review->title ?? '')) !== $cleanTitle)
            || (trim((string) $review->content) !== $cleanContent);

        $targetStatus = ($hasChanged || $review->status === 'rejected') ? 'pending' : $review->status;

        $review->update([
            'rating' => $newRating,
            'title' => $cleanTitle !== '' ? $cleanTitle : null,
            'content' => $cleanContent,
            'status' => $targetStatus,
        ]);

        Cache::forget('google_reviews');

        $profile = CustomerGamification::where('customer_id', $customer->id)->first();
        if ($profile) {
            app(GamificationService::class)->syncDynamicTitles($profile);
        }

        $this->cancelEditProductReview();
        session()->flash('reviews_notice', $targetStatus === 'pending'
            ? 'Bewertung aktualisiert und zur Prüfung eingereicht.'
            : 'Bewertung gespeichert.');
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

        if (!empty($review->media) && is_array($review->media)) {
            foreach ($review->media as $media) {
                if (
                    is_string($media)
                    && str_starts_with($media, 'produkte/reviews/')
                    && !str_contains($media, '..')
                    && Storage::disk('public')->exists($media)
                ) {
                    Storage::disk('public')->delete($media);
                }
            }
        }

        $reviewSnapshot = clone $review;
        $review->delete();

        Cache::forget('google_reviews');
        app(GamificationService::class)->reverseReviewFunken($reviewSnapshot);

        if ($this->editingProductReviewId === $reviewId) {
            $this->cancelEditProductReview();
        }

        session()->flash('reviews_notice', 'Produktbewertung gelöscht.');
    }

    public function startCreateProductReview(string $productId): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        $unreviewedProducts = $this->getUnreviewedPurchasedPhysicalProducts($customer);
        if (!$unreviewedProducts->contains(fn (Product $p) => (string) $p->id === $productId)) {
            return;
        }

        $this->resetErrorBag();
        $this->editingProductReviewId = null;
        $this->creatingProductId = $productId;
        $this->newProductRating = 5;
        $this->newProductTitle = '';
        $this->newProductContent = '';
    }

    public function cancelCreateProductReview(): void
    {
        $this->creatingProductId = null;
        $this->newProductRating = 5;
        $this->newProductTitle = '';
        $this->newProductContent = '';
        $this->resetErrorBag();
    }

    public function submitNewProductReview(): void
    {
        $customer = $this->resolveEligibleCustomerOrAbort();

        if (!$this->creatingProductId) {
            return;
        }

        $eligibleProduct = $this->getUnreviewedPurchasedPhysicalProducts($customer)
            ->first(fn (Product $p) => (string) $p->id === $this->creatingProductId);

        if (!$eligibleProduct) {
            $this->cancelCreateProductReview();
            return;
        }

        $this->validate([
            'newProductRating' => ['required', 'integer', 'min:1', 'max:5'],
            'newProductTitle' => ['nullable', 'string', 'max:255'],
            'newProductContent' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'newProductRating.required' => 'Bitte wähle eine Sterne-Bewertung aus.',
            'newProductContent.required' => 'Bitte schreibe mindestens 10 Zeichen.',
            'newProductContent.min' => 'Bitte schreibe mindestens 10 Zeichen.',
        ]);

        $cleanTitle = trim(strip_tags($this->newProductTitle));
        $cleanContent = trim(strip_tags($this->newProductContent));
        if (mb_strlen($cleanContent) < 10) {
            $this->addError('newProductContent', 'Bitte schreibe mindestens 10 Zeichen.');
            return;
        }

        if (ProductReview::where('customer_id', $customer->id)->where('product_id', $eligibleProduct->id)->exists()) {
            $this->cancelCreateProductReview();
            return;
        }

        ProductReview::create([
            'product_id' => $eligibleProduct->id,
            'customer_id' => $customer->id,
            'rating' => max(1, min(5, (int) $this->newProductRating)),
            'title' => $cleanTitle !== '' ? $cleanTitle : null,
            'content' => $cleanContent,
            'is_anonymous' => false,
            'media' => [],
            'status' => 'pending',
        ]);

        Cache::forget('google_reviews');
        $this->cancelCreateProductReview();
        session()->flash('reviews_notice', 'Produktbewertung eingereicht.');
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
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('payment_status', '!=', 'refunded')
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhere('status', 'completed');
            })
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
            'experienceCards' => $experienceCards,
        ]);
    }
}

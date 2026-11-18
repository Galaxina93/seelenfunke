<?php

namespace App\Livewire\Shop\Product;

use App\Livewire\Traits\ManagesCustomerProductReviewForm;
use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\System\SystemBlockedIp;
use App\Services\System\SystemSecurityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ProductReviews extends Component
{
    use WithPagination, WithDepartmentTheming, ManagesCustomerProductReviewForm;

    public string $themingDepartment = 'Sales';

    #[Locked]
    public Product $product;

    public $filterRating = null;

    public $loginEmail = '';
    public $loginPassword = '';
    public $loginRemember = false;
    public $loginError = '';

    public function mount(Product $product)
    {
        $this->product = $product;
    }

    public function filterByRating($rating)
    {
        $rating = (int) $rating;
        if ($rating < 1 || $rating > 5 || $this->filterRating === $rating) {
            $this->filterRating = null;
        } else {
            $this->filterRating = $rating;
        }
        $this->resetPage();
    }

    public function loginUser()
    {
        $this->loginError = '';
        $ip = request()->ip();

        if (SystemBlockedIp::isBlocked($ip)) {
            $this->loginError = 'Der Zugriff von deiner IP-Adresse wurde vorübergehend gesperrt.';
            return;
        }

        $throttleKey = 'review_login_' . $ip;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->loginError = "Zu viele Anmeldeversuche. Bitte warte {$seconds} Sekunden.";
            return;
        }

        $this->validate([
            'loginEmail' => 'required|email',
            'loginPassword' => 'required',
        ], [
            'loginEmail.required' => 'E-Mail ist erforderlich.',
            'loginEmail.email' => 'Ungültige E-Mail-Adresse.',
            'loginPassword.required' => 'Passwort ist erforderlich.',
        ]);

        $candidate = Customer::withTrashed()->where('email', $this->loginEmail)->first();

        if (!$candidate || !Auth::guard('customer')->validate(['email' => $this->loginEmail, 'password' => $this->loginPassword])) {
            RateLimiter::hit($throttleKey, 300);
            SystemSecurityService::recordLoginAttempt($this->loginEmail, $ip, false, 'product_reviews');
            $this->loginError = 'Zugangsdaten nicht korrekt.';
            return;
        }

        if (method_exists($candidate, 'trashed') && $candidate->trashed()) {
            RateLimiter::hit($throttleKey, 300);
            SystemSecurityService::recordLoginAttempt($this->loginEmail, $ip, false, 'product_reviews');
            $this->loginError = 'Dieser Account wurde deaktiviert.';
            return;
        }

        if (method_exists($candidate, 'hasVerifiedEmail') && !$candidate->hasVerifiedEmail()) {
            RateLimiter::hit($throttleKey, 300);
            SystemSecurityService::recordLoginAttempt($this->loginEmail, $ip, false, 'product_reviews_unverified');
            $this->loginError = 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in deinem Postfach.';
            return;
        }

        if ($candidate->profile && $candidate->profile->two_factor_is_active) {
            $this->redirect(route('login', ['redirect' => urlencode(url()->current() . '#kundenbewertungen')]));
            return;
        }

        if (Auth::guard('customer')->attempt(['email' => $this->loginEmail, 'password' => $this->loginPassword], $this->loginRemember)) {
            RateLimiter::clear($throttleKey);
            SystemSecurityService::recordLoginAttempt($this->loginEmail, $ip, true, 'product_reviews');
            session()->regenerate();

            $loggedInUser = Auth::guard('customer')->user();
            $permissions = [];

            if (method_exists($loggedInUser, 'roles')) {
                $permissions = $loggedInUser->roles->flatMap(fn ($role) => $role->permissions)->pluck('name', 'name')->all();
            }

            session(['permissions' => $permissions]);

            $this->js("window.location.hash = 'kundenbewertungen'; window.location.reload();");
        } else {
            $this->loginError = 'Zugangsdaten nicht korrekt.';
        }
    }

    public function editReview(): void
    {
        $review = ProductReview::where('product_id', $this->product->id)
            ->where('customer_id', Auth::guard('customer')->id())
            ->first();

        if ($review) {
            $this->loadReviewIntoForm($review);
        }
    }

    public function deleteReview(): void
    {
        $review = ProductReview::where('product_id', $this->product->id)
            ->where('customer_id', Auth::guard('customer')->id())
            ->first();

        if ($review) {
            $this->destroyCustomerProductReview($review);
        } else {
            session()->flash('error', 'Bewertung konnte nicht gefunden werden.');
        }
    }

    public function cancelEdit(): void
    {
        $this->resetReviewFormState();
    }

    public function submitReview(): void
    {
        $this->persistCustomerProductReview((string) $this->product->id);
    }

    public function render()
    {
        $hasReviewed = false;
        $userReview = null;
        $canReview = false;

        if (Auth::guard('customer')->check()) {
            $customerId = (string) Auth::guard('customer')->id();

            $userReview = ProductReview::where('product_id', $this->product->id)
                ->where('customer_id', $customerId)
                ->with([
                    'customer:id,first_name,last_name,created_at',
                    'customer.profile:id,customer_id,photo_path',
                    'customer.gamification',
                ])
                ->first();

            if ($userReview) {
                $hasReviewed = true;
            }

            $canReview = $this->customerHasPurchasedProduct($customerId, (string) $this->product->id);
        }

        $allApproved = ProductReview::where('product_id', $this->product->id)
            ->where('status', 'approved')
            ->select('rating')
            ->get();

        $totalCount = $allApproved->count();
        $averageRating = $totalCount > 0 ? round((float) $allApproved->avg('rating'), 1) : 0.0;
        $breakdown = [
            5 => ['count' => 0, 'percent' => 0],
            4 => ['count' => 0, 'percent' => 0],
            3 => ['count' => 0, 'percent' => 0],
            2 => ['count' => 0, 'percent' => 0],
            1 => ['count' => 0, 'percent' => 0],
        ];

        if ($totalCount > 0) {
            foreach ($allApproved as $r) {
                if (isset($breakdown[$r->rating])) {
                    $breakdown[$r->rating]['count']++;
                }
            }
            foreach ($breakdown as $stars => $data) {
                $breakdown[$stars]['percent'] = round(($data['count'] / $totalCount) * 100);
            }
        }

        $query = ProductReview::where('product_id', $this->product->id)
            ->where('status', 'approved')
            ->with([
                'customer:id,first_name,last_name,created_at',
                'customer.profile:id,customer_id,photo_path',
                'customer.gamification',
            ]);

        if ($userReview) {
            $query->where('id', '!=', $userReview->id);
        }

        if ($this->filterRating) {
            $query->where('rating', $this->filterRating);
        }

        $reviews = $query->latest()->paginate(5);

        return view('livewire.shop.product.product-reviews', [
            'reviews' => $reviews,
            'averageRating' => $averageRating,
            'totalReviews' => $totalCount,
            'breakdown' => $breakdown,
            'hasReviewed' => $hasReviewed,
            'userReview' => $userReview,
            'canReview' => $canReview,
        ]);
    }
}

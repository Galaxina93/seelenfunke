<?php

namespace App\Livewire\Shop\Product;

use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\Session;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

class ProductReviews extends Component
{
    use WithPagination, WithFileUploads;

    public Product $product;
    public $rating = 5;
    public $title = '';
    public $content = '';

    public $isEditing = false;
    public $userReviewId = null;

    public $filterRating = null;

    public $newMedia = [];
    public $accumulatedMedia = [];
    public $existingMedia = [];

    public $loginEmail = '';
    public $loginPassword = '';
    public $loginRemember = false;
    public $loginError = '';

    protected $rules = [
        'rating' => 'required|integer|min:1|max:5',
        'title' => 'nullable|string|max:255',
        'content' => 'required|string|min:10|max:1000',
    ];

    protected $messages = [
        'content.required' => 'Bitte schreibe ein paar Worte zu deiner Erfahrung.',
        'content.min' => 'Die Bewertung muss mindestens 10 Zeichen lang sein.',
        'rating.required' => 'Bitte wähle eine Sterne-Bewertung aus.',
        'newMedia.*.max' => 'Eine Datei darf maximal 10 MB groß sein.',
        'newMedia.*.mimes' => 'Erlaubte Formate: JPG, PNG, WEBP, MP4, MOV.',
    ];

    public function mount(Product $product)
    {
        $this->product = $product;
    }

    public function filterByRating($rating)
    {
        if ($this->filterRating === $rating) {
            $this->filterRating = null;
        } else {
            $this->filterRating = $rating;
        }
        $this->resetPage();
    }

    public function loginUser()
    {
        $this->validate([
            'loginEmail' => 'required|email',
            'loginPassword' => 'required',
        ], [
            'loginEmail.required' => 'E-Mail ist erforderlich.',
            'loginEmail.email' => 'Ungültige E-Mail-Adresse.',
            'loginPassword.required' => 'Passwort ist erforderlich.'
        ]);

        $candidate = Customer::withTrashed()->where('email', $this->loginEmail)->first();

        if (!$candidate || !Auth::guard('customer')->validate(['email' => $this->loginEmail, 'password' => $this->loginPassword])) {
            $this->loginError = 'Zugangsdaten nicht korrekt.';
            return;
        }

        if (method_exists($candidate, 'trashed') && $candidate->trashed()) {
            $this->loginError = 'Dieser Account wurde deaktiviert.';
            return;
        }

        if (method_exists($candidate, 'hasVerifiedEmail') && !$candidate->hasVerifiedEmail()) {
            $this->loginError = 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in deinem Postfach.';
            return;
        }

        if ($candidate->profile && $candidate->profile->two_factor_is_active) {
            $this->redirect(route('login', ['redirect' => urlencode(url()->current() . '#kundenbewertungen')]));
            return;
        }

        if (Auth::guard('customer')->attempt(['email' => $this->loginEmail, 'password' => $this->loginPassword], $this->loginRemember)) {
            session()->regenerate();

            $loggedInUser = Auth::guard('customer')->user();
            $permissions = [];

            if (method_exists($loggedInUser, 'roles')) {
                $permissions = $loggedInUser->roles->flatMap(fn($role) => $role->permissions)->pluck('name', 'name')->all();
            }

            session(['permissions' => $permissions]);

            if (class_exists(Session::class)) {
                $this->setBrowserSession($loggedInUser);
            }

            $this->js("window.location.hash = 'kundenbewertungen'; window.location.reload();");

        } else {
            $this->loginError = 'Zugangsdaten nicht korrekt.';
        }
    }

    public function setBrowserSession($user)
    {
        $sessionId = session()->getId();
        $payload = base64_encode(serialize(session()->all()));

        $userAgent = request()->userAgent();
        $browser = 'Unknown';
        $os = 'Unknown';
        $deviceType = 'Desktop';

        if (preg_match('/(Windows|Mac|Linux)/i', $userAgent, $osMatches)) {
            $os = $osMatches[1];
            $deviceType = 'Desktop';
        } elseif (preg_match('/(Android|iPhone|iPad)/i', $userAgent, $osMatches)) {
            $os = $osMatches[1];
            $deviceType = 'Mobile';
        }

        if (preg_match('/(Chrome|Firefox|Safari|Opera|MSIE|Edg|Trident)/i', $userAgent, $browserMatches)) {
            $browser = $browserMatches[1];
        }

        if ($browser == 'MSIE' || $browser == 'Trident') $browser = 'Internet Explorer';
        elseif ($browser == 'Edg') $browser = 'Edge';

        $shortenedUserAgent = $os . ' - ' . $browser;

        $sessionData = [
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => $shortenedUserAgent,
            'payload' => $payload,
            'device_type' => $deviceType,
            'last_activity' => time(),
        ];

        Session::updateOrInsert(['user_id' => $user->id, 'ip_address' => request()->ip()], $sessionData);
    }

    public function updatedNewMedia()
    {
        $this->validate([
            'newMedia.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,mov|max:10240',
        ]);

        $totalFiles = count($this->existingMedia) + count($this->accumulatedMedia) + count($this->newMedia);

        if ($totalFiles > 3) {
            $this->addError('newMedia', 'Du kannst maximal 3 Dateien hochladen.');
            $this->newMedia = [];
            return;
        }

        foreach ($this->newMedia as $file) {
            $this->accumulatedMedia[] = $file;
        }

        $this->newMedia = [];
    }

    public function removeAccumulatedMedia($index)
    {
        if (isset($this->accumulatedMedia[$index])) {
            unset($this->accumulatedMedia[$index]);
            $this->accumulatedMedia = array_values($this->accumulatedMedia);
        }
    }

    public function removeExistingMedia($index)
    {
        if (isset($this->existingMedia[$index])) {
            unset($this->existingMedia[$index]);
            $this->existingMedia = array_values($this->existingMedia);
        }
    }

    public function editReview()
    {
        $review = ProductReview::where('product_id', $this->product->id)
            ->where('customer_id', Auth::guard('customer')->id())
            ->first();

        if ($review) {
            $this->rating = $review->rating;
            $this->title = $review->title;
            $this->content = $review->content;
            $this->existingMedia = $review->media ?? [];
            $this->userReviewId = $review->id;
            $this->isEditing = true;
        }
    }

    public function deleteReview()
    {
        $review = ProductReview::where('product_id', $this->product->id)
            ->where('customer_id', Auth::guard('customer')->id())
            ->first();

        if ($review) {
            if (!empty($review->media)) {
                foreach ($review->media as $media) {
                    if(Storage::disk('public')->exists($media)) {
                        Storage::disk('public')->delete($media);
                    }
                }
            }

            $review->delete();

            $this->cancelEdit();
            session()->flash('success', 'Deine Bewertung wurde erfolgreich gelöscht.');
            $this->dispatch('review-added');
        } else {
            session()->flash('error', 'Bewertung konnte nicht gefunden werden.');
        }
    }

    public function cancelEdit()
    {
        $this->reset(['rating', 'title', 'content', 'userReviewId', 'isEditing', 'newMedia', 'accumulatedMedia', 'existingMedia']);
        $this->rating = 5;
    }

    // Keine Parameter in der Funktion = keine Livewire Inject-Abstürze
    public function submitReview()
    {
        $this->validate();

        if (!Auth::guard('customer')->check()) {
            session()->flash('error', 'Bitte logge dich ein, um eine Bewertung abzugeben.');
            return;
        }

        $customerId = Auth::guard('customer')->id();

        $hasPurchased = Order::where('customer_id', $customerId)
            ->whereIn('status', ['completed', 'shipped', 'processing', 'pending'])
            ->where('payment_status', 'paid')
            ->whereHas('items', function ($query) {
                $query->where('product_id', $this->product->id);
            })->exists();

        if (!$hasPurchased) {
            session()->flash('error', 'Du kannst nur Produkte bewerten, die du auch bei uns gekauft hast.');
            return;
        }

        $mediaPaths = $this->existingMedia;

        foreach ($this->accumulatedMedia as $file) {
            $mime = $file->getMimeType();

            if (str_contains($mime, 'image')) {
                $filename = \Illuminate\Support\Str::random(40) . '.jpg';
                $path = 'reviews/' . $filename;

                $image = Image::make($file->getRealPath())->orientate()->encode('jpg', 80);
                Storage::disk('public')->put($path, (string) $image);

                $mediaPaths[] = $path;
            } else {
                $mediaPaths[] = $file->store('reviews', 'public');
            }
        }

        $hasNewMedia = count($this->accumulatedMedia) > 0;
        $totalMedia = count($mediaPaths) > 0;

        $targetStatus = $totalMedia ? 'pending' : 'approved';

        if ($this->isEditing && $this->userReviewId) {
            $review = ProductReview::where('id', $this->userReviewId)
                ->where('customer_id', $customerId)
                ->first();

            if ($review) {
                $oldMedia = $review->media ?? [];
                $removedMedia = array_diff($oldMedia, $this->existingMedia);
                foreach ($removedMedia as $rm) {
                    Storage::disk('public')->delete($rm);
                }

                if ($review->status === 'rejected' || $hasNewMedia) {
                    $targetStatus = $totalMedia ? 'pending' : 'approved';
                } else {
                    $targetStatus = $review->status;
                }

                $review->update([
                    'rating' => $this->rating,
                    'title' => $this->title,
                    'content' => $this->content,
                    'media' => $mediaPaths,
                    'status' => $targetStatus
                ]);

                $msg = $targetStatus === 'pending' ? 'Deine Bewertung wurde aktualisiert und wird von uns geprüft.' : 'Deine Bewertung wurde erfolgreich aktualisiert.';
                session()->flash('success', $msg);
                $this->cancelEdit();
            }
        } else {
            $existing = ProductReview::where('product_id', $this->product->id)
                ->where('customer_id', $customerId)
                ->first();

            if ($existing) {
                session()->flash('error', 'Du hast dieses Produkt bereits bewertet. Vielen Dank für dein Feedback!');
                return;
            }

            ProductReview::create([
                'product_id' => $this->product->id,
                'customer_id' => $customerId,
                'rating' => $this->rating,
                'title' => $this->title,
                'content' => $this->content,
                'media' => $mediaPaths,
                'status' => $targetStatus,
            ]);

            // TITEL UPDATE: Sicher über den Laravel Service Container auflösen
            $user = Customer::find($customerId);
            if ($user) {
                $gameService = app(GamificationService::class);
                $profile = $gameService->getProfile($user);
                $gameService->incrementTitleProgress($profile, 'botschafter', 1);
            }

            $this->reset(['rating', 'title', 'content', 'accumulatedMedia', 'existingMedia', 'newMedia']);
            $this->rating = 5;

            $msg = $targetStatus === 'pending' ? 'Vielen Dank! Da du Bilder/Videos angehängt hast, schalten wir deine Bewertung nach einer kurzen Prüfung frei.' : 'Vielen Dank für deine Bewertung! Sie hilft uns und anderen Kunden sehr.';
            session()->flash('success', $msg);
        }

        $this->dispatch('review-added');
    }

    public function render()
    {
        $hasReviewed = false;
        $userReview = null;
        $canReview = false;

        if (Auth::guard('customer')->check()) {
            $customerId = Auth::guard('customer')->id();

            $userReview = ProductReview::where('product_id', $this->product->id)
                ->where('customer_id', $customerId)
                ->first();

            if ($userReview) {
                $hasReviewed = true;
            }

            $canReview = Order::where('customer_id', $customerId)
                ->whereIn('status', ['completed', 'shipped', 'processing', 'pending'])
                ->where('payment_status', 'paid')
                ->whereHas('items', function ($query) {
                    $query->where('product_id', $this->product->id);
                })->exists();
        }

        $allApproved = ProductReview::where('product_id', $this->product->id)
            ->where('status', 'approved')
            ->get();

        $totalCount = $allApproved->count();
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
            ->where('status', 'approved');

        if ($userReview) {
            $query->where('id', '!=', $userReview->id);
        }

        if ($this->filterRating) {
            $query->where('rating', $this->filterRating);
        }

        $reviews = $query->latest()->paginate(5);

        return view('livewire.shop.product.product-reviews', [
            'reviews' => $reviews,
            'averageRating' => $this->product->average_rating,
            'totalReviews' => $this->product->review_count,
            'breakdown' => $breakdown,
            'hasReviewed' => $hasReviewed,
            'userReview' => $userReview,
            'canReview' => $canReview,
        ]);
    }
}

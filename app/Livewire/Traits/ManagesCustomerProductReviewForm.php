<?php

namespace App\Livewire\Traits;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderRevocation;
use App\Models\Product\ProductReview;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesCustomerProductReviewForm
{
    use WithFileUploads;

    public const MAX_MEDIA_FILES = 3;
    public const MAX_VIDEO_FILES = 1;
    public const MAX_IMAGE_KB = 5120; // 5 MB Eingangslimit (wird automatisch auf ~80-140 KB WebP komprimiert)
    public const MAX_VIDEO_KB = 8192; // 8 MB Limit für max. 1 kurzes Video
    public const MAX_IMAGE_DIMENSION = 1600;
    public const MAX_SOURCE_PIXELS = 36000000; // 36 MP Schutz gegen Decompression-Bombs

    public $rating = 5;
    public $title = '';
    public $content = '';
    public bool $isAnonymous = false;
    public bool $acceptReviewTerms = false;

    #[Locked]
    public $isEditing = false;

    #[Locked]
    public $userReviewId = null;

    public $newMedia = [];
    public $accumulatedMedia = [];

    #[Locked]
    public $existingMedia = [];

    protected function reviewValidationRules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|min:10|max:1000',
            'isAnonymous' => 'boolean',
            'acceptReviewTerms' => 'accepted',
            'accumulatedMedia' => 'nullable|array|max:' . self::MAX_MEDIA_FILES,
            'accumulatedMedia.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,mov|max:' . self::MAX_VIDEO_KB,
        ];
    }

    protected function reviewValidationMessages(): array
    {
        return [
            'content.required' => 'Bitte schreibe ein paar Worte zu deiner Erfahrung.',
            'content.min' => 'Die Bewertung muss mindestens 10 Zeichen lang sein.',
            'rating.required' => 'Bitte wähle eine Sterne-Bewertung aus.',
            'acceptReviewTerms.accepted' => 'Bitte bestätige die Hinweise zur Veröffentlichung und den Bildrechten.',
            'newMedia.*.max' => 'Bilder dürfen maximal 5 MB und Videos maximal 8 MB groß sein.',
            'newMedia.*.mimes' => 'Erlaubte Formate: JPG, PNG, WEBP (Bilder) sowie MP4, MOV (max. 1 Video).',
            'accumulatedMedia.*.max' => 'Bilder dürfen maximal 5 MB und Videos maximal 8 MB groß sein.',
            'accumulatedMedia.*.mimes' => 'Erlaubte Formate: JPG, PNG, WEBP (Bilder) sowie MP4, MOV (max. 1 Video).',
        ];
    }

    public function updatedNewMedia(): void
    {
        $this->resetErrorBag(['newMedia', 'newMedia.*']);

        $this->validate([
            'newMedia.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,mov|max:' . self::MAX_VIDEO_KB,
        ], $this->reviewValidationMessages());

        $existingCount = is_array($this->existingMedia) ? count($this->existingMedia) : 0;
        $accumulatedCount = is_array($this->accumulatedMedia) ? count($this->accumulatedMedia) : 0;
        $incomingCount = is_array($this->newMedia) ? count($this->newMedia) : 0;
        $totalFiles = $existingCount + $accumulatedCount + $incomingCount;

        if ($totalFiles > self::MAX_MEDIA_FILES) {
            $this->addError('newMedia', 'Du kannst maximal ' . self::MAX_MEDIA_FILES . ' Dateien (Bilder / max. 1 Video) hochladen.');
            $this->newMedia = [];
            return;
        }

        $videoCount = $this->countCurrentVideos();
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedVideoMimes = ['video/mp4', 'video/quicktime'];

        foreach ((array) $this->newMedia as $file) {
            if (!$file instanceof TemporaryUploadedFile) {
                continue;
            }

            $realPath = $file->getRealPath();
            if (!$realPath || !is_file($realPath)) {
                $this->addError('newMedia', 'Die Datei konnte nicht sicher gelesen werden.');
                continue;
            }

            $clientExt = strtolower(pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
            $mime = function_exists('finfo_open')
                ? strtolower((string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath))
                : strtolower((string) $file->getMimeType());
            $sizeBytes = (int) (@filesize($realPath) ?: $file->getSize());

            if (in_array($mime, $allowedImageMimes, true) && in_array($clientExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                if ($sizeBytes > self::MAX_IMAGE_KB * 1024) {
                    $this->addError('newMedia', 'Ein einzelnes Bild darf maximal 5 MB groß sein (wird nach dem Upload automatisch komprimiert).');
                    continue;
                }

                $imgInfo = @getimagesize($realPath);
                if (
                    !is_array($imgInfo)
                    || empty($imgInfo[0])
                    || empty($imgInfo[1])
                    || !in_array($imgInfo[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
                ) {
                    $this->addError('newMedia', 'Ungültige oder beschädigte Bilddatei.');
                    continue;
                }

                if (((int) $imgInfo[0] * (int) $imgInfo[1]) > self::MAX_SOURCE_PIXELS) {
                    $this->addError('newMedia', 'Die Bildauflösung ist zu hoch (max. 36 Megapixel).');
                    continue;
                }

                $this->accumulatedMedia[] = $file;
            } elseif (in_array($mime, $allowedVideoMimes, true) && in_array($clientExt, ['mp4', 'mov'], true)) {
                if ($videoCount >= self::MAX_VIDEO_FILES) {
                    $this->addError('newMedia', 'Pro Bewertung ist maximal 1 kurzes Video erlaubt (max. 8 MB).');
                    continue;
                }

                if ($sizeBytes > self::MAX_VIDEO_KB * 1024) {
                    $this->addError('newMedia', 'Das Video darf maximal 8 MB groß sein.');
                    continue;
                }

                if (!self::isValidVideoContainer($realPath)) {
                    $this->addError('newMedia', 'Das Video-Format ist ungültig oder manipuliert.');
                    continue;
                }

                $videoCount++;
                $this->accumulatedMedia[] = $file;
            } else {
                $this->addError('newMedia', 'Nicht unterstütztes Dateiformat.');
            }
        }

        $this->newMedia = [];
    }

    protected function countCurrentVideos(): int
    {
        $count = 0;
        if (is_array($this->existingMedia)) {
            foreach ($this->existingMedia as $path) {
                $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'mov', 'webm'], true)) {
                    $count++;
                }
            }
        }
        if (is_array($this->accumulatedMedia)) {
            foreach ($this->accumulatedMedia as $file) {
                if (is_object($file) && method_exists($file, 'getMimeType')) {
                    $mime = strtolower((string) $file->getMimeType());
                    if (str_starts_with($mime, 'video/')) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    /**
     * Prüft binär die Container-Signatur (Magic Bytes / ISO Base Media ftyp / QuickTime)
     * und schützt vor eingeschleusten PHP-/Script-Payloads.
     */
    protected static function isValidVideoContainer(string $realPath): bool
    {
        $fp = @fopen($realPath, 'rb');
        if (!$fp) {
            return false;
        }
        $header = (string) @fread($fp, 512);
        @fclose($fp);

        if (strlen($header) < 12) {
            return false;
        }

        $lowerHeader = strtolower($header);
        if (str_contains($lowerHeader, '<?php') || str_contains($lowerHeader, '<script') || str_starts_with($lowerHeader, '#!')) {
            return false;
        }

        // ISO Base Media / MP4 / QuickTime Atom an Offset 4 ('ftyp', 'moov', 'wide', 'mdat', 'free', 'skip', 'pnot')
        $boxType = substr($header, 4, 4);
        return in_array($boxType, ['ftyp', 'moov', 'wide', 'mdat', 'free', 'skip', 'pnot'], true);
    }

    public function removeAccumulatedMedia($index): void
    {
        $index = (int) $index;
        if (isset($this->accumulatedMedia[$index])) {
            unset($this->accumulatedMedia[$index]);
            $this->accumulatedMedia = array_values($this->accumulatedMedia);
        }
    }

    public function removeExistingMedia($index): void
    {
        $index = (int) $index;
        if (isset($this->existingMedia[$index])) {
            unset($this->existingMedia[$index]);
            $this->existingMedia = array_values($this->existingMedia);
        }
    }

    public function loadReviewIntoForm(ProductReview $review): void
    {
        $this->resetErrorBag();
        $this->rating = max(1, min(5, (int) $review->rating));
        $this->title = (string) ($review->title ?? '');
        $this->content = (string) $review->content;
        $this->isAnonymous = (bool) ($review->is_anonymous ?? false);
        $this->acceptReviewTerms = true;
        $this->existingMedia = is_array($review->media) ? array_values($review->media) : [];
        $this->newMedia = [];
        $this->accumulatedMedia = [];
        $this->userReviewId = $review->id;
        $this->isEditing = true;
    }

    public function resetReviewFormState(): void
    {
        $this->reset([
            'rating',
            'title',
            'content',
            'isAnonymous',
            'acceptReviewTerms',
            'userReviewId',
            'isEditing',
            'newMedia',
            'accumulatedMedia',
            'existingMedia',
        ]);
        $this->rating = 5;
        $this->isAnonymous = false;
        $this->acceptReviewTerms = false;
        $this->resetErrorBag();
    }

    protected function customerHasPurchasedProduct(string $customerId, string $productId): bool
    {
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

        $matchingOrders = OrderOrder::where('customer_id', $customerId)
            ->whereIn('status', ['completed', 'shipped', 'processing', 'pending'])
            ->where('payment_status', 'paid')
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->get(['id', 'order_number']);

        if ($matchingOrders->isEmpty()) {
            return false;
        }

        if (empty($revokedOrderNumbers)) {
            return true;
        }

        return $matchingOrders->contains(function (OrderOrder $order) use ($revokedOrderNumbers) {
            $normalized = strtoupper(trim(ltrim((string) $order->order_number, '#')));
            return $normalized === '' || !in_array($normalized, $revokedOrderNumbers, true);
        });
    }

    public function persistCustomerProductReview(string $productId): ?ProductReview
    {
        $this->validate($this->reviewValidationRules(), $this->reviewValidationMessages());

        if (!Auth::guard('customer')->check()) {
            session()->flash('error', 'Bitte logge dich ein, um eine Bewertung abzugeben.');
            return null;
        }

        $customerId = (string) Auth::guard('customer')->id();

        $submitThrottleKey = 'review_submit_' . $customerId;
        if (RateLimiter::tooManyAttempts($submitThrottleKey, 6)) {
            $seconds = RateLimiter::availableIn($submitThrottleKey);
            session()->flash('error', "Bitte warte {$seconds} Sekunden, bevor du erneut speicherst.");
            return null;
        }
        RateLimiter::hit($submitThrottleKey, 60);

        if (!$this->customerHasPurchasedProduct($customerId, $productId)) {
            session()->flash('error', 'Du kannst nur Produkte bewerten, die du auch bei uns gekauft hast.');
            return null;
        }

        if (!$this->isEditing && ProductReview::where('product_id', $productId)->where('customer_id', $customerId)->exists()) {
            session()->flash('error', 'Du hast dieses Produkt bereits bewertet. Vielen Dank für dein Feedback!');
            return null;
        }

        // Bestehende Medien bei Bearbeitung vorab validieren, um Gesamt- und Video-Limit exakt zu prüfen
        $validExistingMedia = [];
        $oldMedia = [];
        $editingReviewModel = null;

        if ($this->isEditing && $this->userReviewId) {
            $editingReviewModel = ProductReview::where('id', $this->userReviewId)
                ->where('product_id', $productId)
                ->where('customer_id', $customerId)
                ->first();

            if (!$editingReviewModel) {
                session()->flash('error', 'Bewertung konnte nicht gefunden werden.');
                return null;
            }

            $oldMedia = is_array($editingReviewModel->media) ? $editingReviewModel->media : [];
            $validExistingMedia = array_values(array_intersect(
                is_array($this->existingMedia) ? $this->existingMedia : [],
                $oldMedia
            ));
        }

        $accumulatedList = is_array($this->accumulatedMedia) ? $this->accumulatedMedia : [];
        if ((count($validExistingMedia) + count($accumulatedList)) > self::MAX_MEDIA_FILES) {
            $this->addError('newMedia', 'Es sind maximal ' . self::MAX_MEDIA_FILES . ' Dateien pro Bewertung erlaubt.');
            return null;
        }

        $videoCount = 0;
        foreach ($validExistingMedia as $emPath) {
            if (in_array(strtolower(pathinfo((string) $emPath, PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm'], true)) {
                $videoCount++;
            }
        }

        $newMediaPaths = [];
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedVideoMimes = ['video/mp4', 'video/quicktime'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'];

        foreach ($accumulatedList as $file) {
            if (!$file instanceof TemporaryUploadedFile) {
                continue;
            }
            $clientExt = strtolower(pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($clientExt, $allowedExts, true)) {
                continue;
            }
            $realPath = $file->getRealPath();
            if (!$realPath || !is_file($realPath)) {
                continue;
            }

            $mime = function_exists('finfo_open')
                ? strtolower((string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath))
                : strtolower((string) $file->getMimeType());
            $sizeBytes = (int) (@filesize($realPath) ?: $file->getSize());

            if (in_array($mime, $allowedImageMimes, true) && in_array($clientExt, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                if ($sizeBytes > self::MAX_IMAGE_KB * 1024) {
                    continue;
                }

                $imgInfo = @getimagesize($realPath);
                if (
                    !is_array($imgInfo)
                    || empty($imgInfo[0])
                    || empty($imgInfo[1])
                    || !in_array($imgInfo[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
                    || ((int) $imgInfo[0] * (int) $imgInfo[1]) > self::MAX_SOURCE_PIXELS
                ) {
                    continue;
                }

                $useWebp = function_exists('imagewebp');
                $targetExt = $useWebp ? 'webp' : 'jpg';
                $filename = Str::random(40) . '.' . $targetExt;
                $path = 'produkte/reviews/' . $filename;

                // Orientieren, auf max. 1600x1600 px skalieren (ohne Upscaling) & komplett neu als WebP/JPG encodieren
                // -> vernichtet jegliche Polyglot-Payloads, entfernt GPS/EXIF-Metadaten (DSGVO) und spart ~95% Speicherplatz
                $image = Image::make($realPath)
                    ->orientate()
                    ->resize(self::MAX_IMAGE_DIMENSION, self::MAX_IMAGE_DIMENSION, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                    ->encode($targetExt, 78);

                Storage::disk('public')->put($path, (string) $image);

                $newMediaPaths[] = $path;
            } elseif (in_array($mime, $allowedVideoMimes, true) && in_array($clientExt, ['mp4', 'mov'], true)) {
                if ($videoCount >= self::MAX_VIDEO_FILES || $sizeBytes > self::MAX_VIDEO_KB * 1024 || !self::isValidVideoContainer($realPath)) {
                    continue;
                }
                $videoCount++;
                $safeVideoFilename = Str::random(40) . '.' . $clientExt;
                $newMediaPaths[] = $file->storeAs('produkte/reviews', $safeVideoFilename, 'public');
            }
        }

        $hasNewMedia = count($newMediaPaths) > 0;
        $savedReview = null;

        if ($this->isEditing && $this->userReviewId && $editingReviewModel) {
            $removedMedia = array_diff($oldMedia, $validExistingMedia);
            foreach ($removedMedia as $rm) {
                if (
                    is_string($rm)
                    && str_starts_with($rm, 'produkte/reviews/')
                    && !str_contains($rm, '..')
                    && Storage::disk('public')->exists($rm)
                ) {
                    Storage::disk('public')->delete($rm);
                }
            }

            $mediaPaths = array_merge($validExistingMedia, $newMediaPaths);

            if ($editingReviewModel->status === 'rejected' || $hasNewMedia) {
                $targetStatus = 'pending';
            } else {
                $targetStatus = $editingReviewModel->status;
            }

            $editingReviewModel->update([
                'rating' => (int) $this->rating,
                'title' => $this->title,
                'content' => $this->content,
                'is_anonymous' => (bool) $this->isAnonymous,
                'media' => $mediaPaths,
                'status' => $targetStatus,
            ]);

            $awardedSparks = 0;
            if ($targetStatus === 'approved') {
                $awardedSparks = app(GamificationService::class)->rewardReviewFunken($editingReviewModel->fresh() ?? $editingReviewModel);
            } else {
                $profile = CustomerGamification::where('customer_id', $customerId)->first();
                if ($profile) {
                    app(GamificationService::class)->syncDynamicTitles($profile);
                }
            }

            $msg = $targetStatus === 'pending'
                ? 'Deine Bewertung wurde aktualisiert und wird von uns geprüft.'
                : ('Deine Bewertung wurde erfolgreich aktualisiert.' . ($awardedSparks > 0 ? " Dir wurden +{$awardedSparks} ✨ Seelenfunken gutgeschrieben!" : ''));
            session()->flash('success', $msg);
            if ($awardedSparks > 0) {
                $this->dispatch('sparks-awarded');
            }

            $savedReview = $editingReviewModel->fresh() ?? $editingReviewModel;
            $this->resetReviewFormState();
        } else {
            // Neue Reviews können keine extern vorgegebenen Bestandsmedien übernehmen
            $mediaPaths = $newMediaPaths;
            $totalMedia = count($mediaPaths) > 0;
            $targetStatus = $totalMedia ? 'pending' : 'approved';

            $existing = ProductReview::where('product_id', $productId)
                ->where('customer_id', $customerId)
                ->first();

            if ($existing) {
                session()->flash('error', 'Du hast dieses Produkt bereits bewertet. Vielen Dank für dein Feedback!');
                return null;
            }

            $createdReview = ProductReview::create([
                'product_id' => $productId,
                'customer_id' => $customerId,
                'rating' => (int) $this->rating,
                'title' => $this->title,
                'content' => $this->content,
                'is_anonymous' => (bool) $this->isAnonymous,
                'media' => $mediaPaths,
                'status' => $targetStatus,
            ]);

            $awardedSparks = 0;
            if ($targetStatus === 'approved') {
                $awardedSparks = app(GamificationService::class)->rewardReviewFunken($createdReview);
            } else {
                $user = Customer::find($customerId);
                if ($user) {
                    $profile = CustomerGamification::where('customer_id', $user->id)
                        ->where('is_active', true)
                        ->first();
                    if ($profile) {
                        app(GamificationService::class)->syncDynamicTitles($profile);
                    }
                }
            }

            $this->resetReviewFormState();

            if ($targetStatus === 'pending') {
                $msg = 'Vielen Dank! Da du Bilder/Videos angehängt hast, schalten wir deine Bewertung nach einer kurzen Prüfung frei (inkl. +' . GameConfig::REVIEW_SPARK_REWARD . ' ✨ Seelenfunken Belohnung).';
            } elseif ($awardedSparks > 0) {
                $msg = 'Vielen Dank für deine ehrliche Bewertung! Als Dankeschön wurden dir +' . $awardedSparks . ' ✨ Seelenfunken gutgeschrieben.';
                $this->dispatch('sparks-awarded');
            } else {
                $msg = 'Vielen Dank für deine Bewertung! Sie hilft uns und anderen Kunden sehr.';
            }
            session()->flash('success', $msg);
            $savedReview = $createdReview;
        }

        Cache::forget('google_reviews');
        $this->dispatch('review-added');

        return $savedReview;
    }

    public function destroyCustomerProductReview(ProductReview $review): void
    {
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

        $this->resetReviewFormState();
        session()->flash('success', 'Deine Bewertung wurde erfolgreich gelöscht.');
        $this->dispatch('review-added');
        $this->dispatch('sparks-awarded');
    }
}

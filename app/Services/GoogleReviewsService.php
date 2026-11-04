<?php

namespace App\Services;

use App\Models\Product\ProductReview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GoogleReviewsService
{
    public function getReviews(): array
    {
        $apiKey = (string) config('services.google.places_key', '');
        $placeId = (string) config('services.google.place_id', '');

        $hasValidGoogleConfig = $apiKey !== ''
            && $placeId !== ''
            && !str_starts_with($placeId, 'deine-place-id')
            && !str_starts_with($apiKey, 'dein-');

        if (!$hasValidGoogleConfig) {
            return $this->getFallbackReviews();
        }

        $cached = Cache::get('google_reviews');
        if (is_array($cached) && isset($cached['source']) && $cached['source'] === 'google' && (!empty($cached['reviews']) || !empty($cached['total_ratings']))) {
            return $cached;
        }

        try {
            $response = Http::timeout(3)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'reviews,rating,user_ratings_total',
                'key' => $apiKey,
                'language' => 'de'
            ]);

            if ($response->successful()) {
                $result = $response->json()['result'] ?? [];

                if (!empty($result) && (!empty($result['reviews']) || !empty($result['user_ratings_total']))) {
                    // DSGVO Art. 6 Abs. 1: Externe Avatar-URLs (googleusercontent.com) entfernen,
                    // damit beim Seitenaufruf keine Besucher-IPs ohne Einwilligung an Google übertragen werden.
                    $sanitizedReviews = array_map(function (array $rev) {
                        unset($rev['profile_photo_url']);
                        return $rev;
                    }, $result['reviews'] ?? []);

                    $data = [
                        'source' => 'google',
                        'reviews' => $sanitizedReviews,
                        'rating' => $result['rating'] ?? null,
                        'total_ratings' => $result['user_ratings_total'] ?? 0,
                    ];
                    Cache::put('google_reviews', $data, 86400);
                    return $data;
                }
            }

            Log::warning('Google Places API Fehler oder leeres Ergebnis: ' . $response->status());
            return $this->getFallbackReviews();
        } catch (\Exception $e) {
            $safeMsg = \App\Services\System\SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
            Log::error('Google Reviews Exception: ' . $safeMsg);
            return $this->getFallbackReviews();
        }
    }

    /**
     * Rechtssicherer Fallback gem. § 5b Abs. 3 UWG & Anhang zu § 3 Abs. 3 UWG (Nr. 23b / 23c):
     * Keine fiktiven Platzhalter-Rezensionen! Stattdessen werden ausschließlich echte,
     * verifizierte und freigegebene Shop-Kundenbewertungen geladen. Liegen noch keine vor,
     * wird ein ehrlicher Leerzustand zurückgegeben.
     */
    public function getFallbackReviews(): array
    {
        try {
            if (!Schema::hasTable('product_reviews')) {
                return [
                    'source' => 'none',
                    'rating' => null,
                    'total_ratings' => 0,
                    'reviews' => [],
                ];
            }

            $approvedQuery = ProductReview::where('status', 'approved');
            $totalRatings = (int) $approvedQuery->count();

            if ($totalRatings === 0) {
                return [
                    'source' => 'none',
                    'rating' => null,
                    'total_ratings' => 0,
                    'reviews' => [],
                ];
            }

            $avgRating = round((float) ProductReview::where('status', 'approved')->avg('rating'), 1);
            $shopReviews = ProductReview::with(['customer', 'product'])
                ->where('status', 'approved')
                ->latest()
                ->limit(16)
                ->get();

            $mapped = $shopReviews->map(function (ProductReview $rev) {
                $authorName = 'Verifizierter Käufer';
                if (!$rev->is_anonymous && $rev->customer) {
                    $first = trim((string) $rev->customer->first_name);
                    $lastInitial = mb_substr(trim((string) $rev->customer->last_name), 0, 1);
                    if ($first !== '') {
                        $authorName = $lastInitial !== '' ? ($first . ' ' . $lastInitial . '.') : $first;
                    }
                }

                $mediaItems = [];
                if (is_array($rev->media)) {
                    foreach ($rev->media as $mediaPath) {
                        if (is_string($mediaPath) && trim($mediaPath) !== '' && !str_contains($mediaPath, '..')) {
                            $cleanPath = ltrim(trim($mediaPath), '/');
                            $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
                            $isVideo = in_array($ext, ['mp4', 'mov', 'webm'], true);
                            $mediaItems[] = [
                                'url' => '/storage/' . $cleanPath,
                                'type' => $isVideo ? 'video' : 'image',
                            ];
                        }
                    }
                }

                return [
                    'author_name' => $authorName,
                    'rating' => (int) $rev->rating,
                    'title' => $rev->title ? trim((string) $rev->title) : null,
                    'text' => trim(($rev->title ? $rev->title . ' – ' : '') . (string) $rev->content),
                    'content_only' => trim((string) $rev->content),
                    'time' => $rev->created_at ? $rev->created_at->timestamp : time(),
                    'product_name' => $rev->product?->name,
                    'product_url' => $rev->product ? route('product.show', $rev->product->slug, false) : null,
                    'verified_purchase' => true,
                    'media' => $mediaItems,
                ];
            })->values()->all();

            return [
                'source' => 'shop',
                'rating' => $avgRating,
                'total_ratings' => $totalRatings,
                'reviews' => $mapped,
            ];
        } catch (\Throwable $e) {
            return [
                'source' => 'none',
                'rating' => null,
                'total_ratings' => 0,
                'reviews' => [],
            ];
        }
    }
}

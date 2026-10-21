<?php

namespace Tests\Feature\Marketing;

use App\Services\GoogleReviewsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleReviewsServiceSecurityTest extends TestCase
{
    public function test_get_reviews_returns_fallback_when_unconfigured()
    {
        Config::set('services.google.places_key', null);
        Config::set('services.google.place_id', null);

        $service = new GoogleReviewsService();
        $reviews = $service->getReviews();

        $this->assertArrayHasKey('rating', $reviews);
        $this->assertArrayHasKey('reviews', $reviews);
        $this->assertCount(8, $reviews['reviews']);
        $this->assertEquals(4.9, $reviews['rating']);
    }

    public function test_get_reviews_queries_places_api_and_caches_result()
    {
        Cache::forget('google_reviews');

        Config::set('services.google.places_key', 'mock_google_key');
        Config::set('services.google.place_id', 'ChIJN1t_tDeuEmsRUsoyG83frY4');

        Http::fake([
            'https://maps.googleapis.com/*' => Http::response([
                'result' => [
                    'rating' => 5.0,
                    'user_ratings_total' => 42,
                    'reviews' => [
                        [
                            'author_name' => 'Echte Kundin',
                            'rating' => 5,
                            'text' => 'Wunderbare Geschenke!'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new GoogleReviewsService();
        $reviews = $service->getReviews();

        $this->assertEquals(5.0, $reviews['rating']);
        $this->assertEquals(42, $reviews['total_ratings']);
        $this->assertCount(1, $reviews['reviews']);
        $this->assertEquals('Echte Kundin', $reviews['reviews'][0]['author_name']);

        // Cached value is retrieved on second call without hitting HTTP again
        Http::fake([
            'https://maps.googleapis.com/*' => Http::response([], 500)
        ]);
        $cachedReviews = $service->getReviews();
        $this->assertEquals(5.0, $cachedReviews['rating']);
    }
}

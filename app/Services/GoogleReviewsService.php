<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleReviewsService
{
    public function getReviews()
    {
        // --- TEMPORÄRER TEST-MODUS (Löschen, sobald Google freigeschaltet ist) ---
        return [
            'rating' => 4.9,
            'total_ratings' => 128,
            'reviews' => [
                [
                    'author_name' => 'Anna Müller',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Anna+Müller&background=random',
                    'rating' => 5,
                    'text' => 'Eine wunderbare Erfahrung! Ich habe mich sehr aufgehoben gefühlt. Die Atmosphäre ist einzigartig und sehr beruhigend.',
                    'time' => time() - 100000,
                ],
                [
                    'author_name' => 'Markus Weber',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Markus+Weber&background=random',
                    'rating' => 5,
                    'text' => 'Absolut empfehlenswert. Professionell und einfühlsam. Ich komme gerne wieder.',
                    'time' => time() - 500000,
                ],
                [
                    'author_name' => 'Lisa S.',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Lisa+S&background=random',
                    'rating' => 4,
                    'text' => 'Sehr nett und kompetent. Einen Stern Abzug nur, weil es schwer war, einen Termin zu finden, aber das spricht ja für die Qualität!',
                    'time' => time() - 2000000,
                ],
                [
                    'author_name' => 'Julia K.',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Julia+K&background=random',
                    'rating' => 5,
                    'text' => 'Endlich habe ich jemanden gefunden, der wirklich zuhört. Die Sitzung hat mir unglaublich viel Kraft gegeben.',
                    'time' => time() - 250000,
                ],
                [
                    'author_name' => 'Thomas Bauer',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Thomas+Bauer&background=random',
                    'rating' => 5,
                    'text' => 'Ein echter Seelenfunke! Die Räumlichkeiten sind toll und die Beratung war sehr zielführend.',
                    'time' => time() - 60000,
                ],
                [
                    'author_name' => 'Sarah L.',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Sarah+L&background=random',
                    'rating' => 5,
                    'text' => 'Ich war erst skeptisch, aber schon nach den ersten Minuten habe ich mich wohlgefühlt. Danke für alles!',
                    'time' => time() - 800000,
                ],
                [
                    'author_name' => 'Michael Fuchs',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Michael+Fuchs&background=random',
                    'rating' => 5,
                    'text' => 'Top Beratung, sehr freundlich und kompetent. Kann ich jedem nur ans Herz legen.',
                    'time' => time() - 1200000,
                ],
                [
                    'author_name' => 'Christina P.',
                    'profile_photo_url' => 'https://ui-avatars.com/api/?name=Christina+P&background=random',
                    'rating' => 5,
                    'text' => 'Hier tankt man wirklich Energie auf. Ich bin begeistert von der Ruhe und der Professionalität.',
                    'time' => time() - 300000,
                ],
            ]
        ];


        /* // Wir cachen die Antwort für 24 Stunden (86400 Sekunden)
         return Cache::remember('google_reviews', 86400, function () {

             $apiKey = config('services.google.places_key');
             $placeId = config('services.google.place_id');

             if (!$apiKey || !$placeId) {
                 return [];
             }

             try {
                 // Wir nutzen die Places Details API
                 // fields=reviews ruft die Bewertungen ab
                 // fields=rating ruft die Durchschnittsbewertung ab
                 // fields=user_ratings_total ruft die Anzahl der Bewertungen ab
                 $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                     'place_id' => $placeId,
                     'fields' => 'reviews,rating,user_ratings_total',
                     'key' => $apiKey,
                     'language' => 'de' // Bewertungen auf Deutsch bevorzugen
                 ]);

                 if ($response->successful()) {
                     $result = $response->json()['result'] ?? [];

                     // Wir geben ein Array zurück mit den Reviews und der Gesamtbewertung
                     return [
                         'reviews' => $result['reviews'] ?? [],
                         'rating' => $result['rating'] ?? 0,
                         'total_ratings' => $result['user_ratings_total'] ?? 0,
                     ];
                 }

                 Log::error('Google Places API Fehler: ' . $response->body());
                 return [];

             } catch (\Exception $e) {
                 Log::error('Google Reviews Exception: ' . $e->getMessage());
                 return [];
             }
         });*/
    }
}

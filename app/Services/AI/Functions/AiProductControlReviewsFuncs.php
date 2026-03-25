<?php

namespace App\Services\AI\Functions;

use App\Models\Product\ProductReview;
use Illuminate\Support\Facades\Storage;

trait AiProductControlReviewsFuncs
{
    /**
     * Define the Product Reviews moderation tools for the Analyst Agent
     */
    public static function getAiProductControlReviewsFuncsSchema(): array
    {
        return [
            [
                'name' => 'review_get_list',
                'description' => 'Liefert eine strukturierte Liste von Shop-Kundenbewertungen. Standardmäßig wird nach Status "pending" gefiltert, um ungelesene/unmoderierte Bewertungen abzurufen. Beinhaltet Produktname, Kundenname, Sterne-Vergabe und den Bewertungstext.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Optional: Filter nach Status ("pending", "approved", "rejected"). Standard ist "pending" für neu eintreffende Reviews.', 'enum' => ['pending', 'approved', 'rejected']],
                        'limit' => ['type' => 'integer', 'description' => 'Optional: Maximale Anzahl an erfassten Bewertungen (default: 15).']
                    ]
                ],
                'callable' => [self::class, 'executeGetList']
            ],
            [
                'name' => 'review_moderate',
                'description' => 'Entscheidet über das Schicksal einer Bewertung. "approved" schaltet sie öffentlich für alle Shop-Kunden sichtbar. "rejected" lehnt sie ab (z.B. wegen Spam oder Beleidigung) und versteckt sie.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'review_id' => ['type' => 'integer', 'description' => 'Die ID der Kundenbewertung aus dem review_get_list Aufruf.'],
                        'action' => ['type' => 'string', 'description' => 'Muss exakt "approved" oder "rejected" sein.', 'enum' => ['approved', 'rejected']]
                    ],
                    'required' => ['review_id', 'action']
                ],
                'callable' => [self::class, 'executeModerate']
            ],
            [
                'name' => 'review_delete',
                'description' => 'Löscht eine Bewertung endgültig aus der Datenbank, inklusive eventuell angehängter Kunden-Bilder auf dem App-Server.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'review_id' => ['type' => 'integer', 'description' => 'Die ID der zu löschenden Bewertung.']
                    ],
                    'required' => ['review_id']
                ],
                'callable' => [self::class, 'executeDelete']
            ]
        ];
    }

    public static function executeGetList(array $args)
    {
        try {
            $status = $args['status'] ?? 'pending';
            $limit = isset($args['limit']) ? min((int)$args['limit'], 50) : 15;

            $query = ProductReview::with(['product', 'customer'])->latest();

            if (!empty($status)) {
                $query->where('status', $status);
            }

            $reviews = $query->take($limit)->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'status' => $r->status,
                    'rating' => $r->rating,
                    'title' => $r->title,
                    'content' => $r->content,
                    'product_name' => $r->product ? $r->product->name : 'Unbekanntes Produkt',
                    'customer_name' => $r->customer ? trim($r->customer->first_name . ' ' . $r->customer->last_name) : 'Gast/Anonym',
                    'created_at' => $r->created_at->format('Y-m-d H:i')
                ];
            });

            return [
                'status' => 'success',
                'filter' => $status,
                'count' => $reviews->count(),
                'reviews' => $reviews->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeModerate(array $args)
    {
        try {
            if (empty($args['review_id']) || empty($args['action'])) {
                return ['status' => 'error', 'message' => 'Review ID und Action (approved/rejected) fehlen.'];
            }

            $review = ProductReview::find($args['review_id']);
            if (!$review) return ['status' => 'error', 'message' => 'Bewertung existiert nicht oder wurde bereits gelöscht.'];

            if (!in_array($args['action'], ['approved', 'rejected'])) {
                return ['status' => 'error', 'message' => 'Ungültige Action. Erlaubt sind nur "approved" oder "rejected".'];
            }

            $review->update(['status' => $args['action']]);

            return [
                'status' => 'success',
                'message' => "Die Bewertung #{$review->id} wurde als '{$args['action']}' markiert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Moderation: ' . $e->getMessage()];
        }
    }

    public static function executeDelete(array $args)
    {
        try {
            if (empty($args['review_id'])) return ['status' => 'error', 'message' => 'Review ID fehlt.'];

            $review = ProductReview::find($args['review_id']);
            if (!$review) return ['status' => 'error', 'message' => 'Bewertung existiert nicht oder wurde bereits gelöscht.'];

            // Clean up attached media to save server disk space
            if (!empty($review->media) && is_array($review->media)) {
                foreach ($review->media as $media) {
                    Storage::disk('public')->delete($media);
                }
            }

            $review->delete();

            return [
                'status' => 'success',
                'message' => "Bewertung #{$args['review_id']} mitsamt assoziierter Medien erfolgreich gelöscht."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }
}

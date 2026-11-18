<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Support\SupportExperienceReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExperienceReviewController extends Controller
{
    public function upsert(Request $request): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if (!$customer instanceof Customer || !SupportExperienceReview::isCustomerEligible($customer)) {
            return response()->json([
                'ok' => false,
                'message' => 'Zugriff nur für verifizierte Kundenkonten erlaubt.',
            ], 403);
        }

        $validated = $request->validate([
            'area' => ['required', 'string', Rule::in(SupportExperienceReview::AREAS)],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:4000'],
            'product_id' => ['nullable', 'string', 'max:64'],
            'keep_existing_comment' => ['nullable', 'boolean'],
        ]);

        $commentProvided = $request->exists('comment') && $request->input('comment') !== null;
        $keepExistingComment = $request->boolean('keep_existing_comment');
        $deviceType = SupportExperienceReview::detectDeviceType($request->userAgent());

        $review = SupportExperienceReview::upsertForCustomer(
            customer: $customer,
            area: (string) $validated['area'],
            rating: (int) $validated['rating'],
            comment: $commentProvided ? (string) $request->input('comment') : null,
            commentProvided: $commentProvided,
            keepExistingComment: $keepExistingComment,
            deviceType: $deviceType,
            productId: isset($validated['product_id']) ? (string) $validated['product_id'] : null,
        );

        return response()->json([
            'ok' => true,
            'review' => [
                'id' => $review->id,
                'area' => $review->area,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'status' => $review->status,
            ],
        ]);
    }
}

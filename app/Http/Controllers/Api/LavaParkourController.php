<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\Gamification\LavaParkourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LavaParkourController extends Controller
{
    public function start(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return $this->guestUnauthorizedResponse();
        }

        $result = $lavaParkour->startRun($customer);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function checkpoint(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return $this->guestUnauthorizedResponse();
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
            'stage' => ['required', 'integer', 'in:1,2'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'z' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültiger Lauf.',
            ], 422, $this->securityHeaders());
        }

        $result = $lavaParkour->recordCheckpoint(
            $customer,
            (string) $request->input('run_id'),
            (int) $request->input('stage'),
            (float) $request->input('x'),
            (float) $request->input('y'),
            (float) $request->input('z')
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function claim(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer === null) {
            return $this->guestUnauthorizedResponse();
        }

        // Hinweis: Etwaige manipulierte Felder wie amount oder sparks im Request-Body werden strikt ignoriert.
        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
            'claim_token' => ['required', 'string', 'max:256'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'z' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültiger Lauf.',
            ], 422, $this->securityHeaders());
        }

        $result = $lavaParkour->claimChest(
            $customer,
            (string) $request->input('run_id'),
            (string) $request->input('claim_token'),
            (float) $request->input('x'),
            (float) $request->input('y'),
            (float) $request->input('z')
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    private function guestUnauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'guest' => true,
            'message' => 'Bitte melde dich an, um täglich 5 Funken zu sammeln.',
        ], 401, $this->securityHeaders());
    }

    /**
     * @return array<string, string>
     */
    private function securityHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}

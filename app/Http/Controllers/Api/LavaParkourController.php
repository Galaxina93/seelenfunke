<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\Gamification\LavaParkourService;
use App\Services\Gamification\LobbyInstanceService;
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
        $tamper = LobbyInstanceService::inspectRequestTampering(
            $request->all(),
            $request->path(),
            $customer !== null ? ('customer:' . $customer->id) : null
        );

        if ($customer === null) {
            return $this->guestUnauthorizedResponse();
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
        }

        $result = $lavaParkour->startRun($customer);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function checkpoint(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $tamper = LobbyInstanceService::inspectRequestTampering(
            $request->all(),
            $request->path(),
            $customer !== null ? ('customer:' . $customer->id) : null
        );

        if ($customer === null) {
            return $this->guestUnauthorizedResponse();
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
            'stage' => ['required', 'integer', 'in:1,2'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'z' => ['required', 'numeric'],
        ]);

        if (
            $validator->fails()
            || !LobbyInstanceService::isValidFiniteNumeric($request->input('x'))
            || !LobbyInstanceService::isValidFiniteNumeric($request->input('y'))
            || !LobbyInstanceService::isValidFiniteNumeric($request->input('z'))
        ) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
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
        $tamper = LobbyInstanceService::inspectRequestTampering(
            $request->all(),
            $request->path(),
            $customer !== null ? ('customer:' . $customer->id) : null
        );

        if ($customer === null) {
            return $this->guestUnauthorizedResponse();
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
        }

        // Manipulierte Felder wie amount, sparks oder level im Request-Body werden protokolliert und strikt ignoriert.
        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
            'claim_token' => ['required', 'string', 'max:256'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'z' => ['required', 'numeric'],
        ]);

        if (
            $validator->fails()
            || !LobbyInstanceService::isValidFiniteNumeric($request->input('x'))
            || !LobbyInstanceService::isValidFiniteNumeric($request->input('y'))
            || !LobbyInstanceService::isValidFiniteNumeric($request->input('z'))
        ) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
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

    private function invalidRunJsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ungültiger Lauf.',
        ], 422, $this->securityHeaders());
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

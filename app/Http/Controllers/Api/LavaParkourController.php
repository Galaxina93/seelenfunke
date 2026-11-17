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
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $level = 1;
        if ($request->has('level') && $request->input('level') !== null && $request->input('level') !== '') {
            $rawLevel = $request->input('level');
            if (is_array($rawLevel) || is_object($rawLevel) || is_bool($rawLevel) || !LobbyInstanceService::isValidFiniteNumeric($rawLevel)) {
                $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

                return $this->invalidRunJsonResponse('Ungültige Magma-Stufe.');
            }

            $validator = Validator::make(['level' => $rawLevel], [
                'level' => ['integer', 'min:1', 'max:' . LavaParkourService::MAX_MAGMA_LEVEL],
            ]);

            if ($validator->fails()) {
                return $this->invalidRunJsonResponse('Ungültige Magma-Stufe.');
            }

            $level = (int) $rawLevel;
        }

        $result = $lavaParkour->startRun($customer, $level);

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
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat']) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
        }

        if ($tamper['has_invalid_coords']) {
            return $this->invalidRunJsonResponse();
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['nullable', 'string', 'max:128'],
            'stage' => ['required', 'integer', 'in:1,2'],
            'level' => ['nullable', 'integer', 'min:1', 'max:' . LavaParkourService::MAX_MAGMA_LEVEL],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse();
        }

        $coords = $this->extractRoomRelativeCoordinates($request);
        if ($coords === null) {
            return $this->invalidRunJsonResponse();
        }

        $validated = $validator->validated();
        $requestedLevel = isset($validated['level']) ? (int) $validated['level'] : null;

        $result = $lavaParkour->recordCheckpoint(
            $customer,
            (string) ($validated['run_id'] ?? ''),
            (int) $validated['stage'],
            $coords['rel_x'],
            $coords['rel_y'],
            $coords['rel_z'],
            $requestedLevel
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
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat']) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return $this->invalidRunJsonResponse();
        }

        if ($tamper['has_invalid_coords']) {
            return $this->invalidRunJsonResponse();
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        // Manipulierte Felder wie amount, sparks oder rewarded_sparks im Request-Body werden protokolliert und strikt ignoriert.
        $validator = Validator::make($request->all(), [
            'run_id' => ['nullable', 'string', 'max:128'],
            'claim_token' => ['nullable', 'string', 'max:160'],
            'level' => ['nullable', 'integer', 'min:1', 'max:' . LavaParkourService::MAX_MAGMA_LEVEL],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse();
        }

        $coords = $this->extractRoomRelativeCoordinates($request);
        if ($coords === null) {
            return $this->invalidRunJsonResponse();
        }

        $validated = $validator->validated();
        $requestedLevel = isset($validated['level']) ? (int) $validated['level'] : null;

        $result = $lavaParkour->claimChest(
            $customer,
            (string) ($validated['run_id'] ?? ''),
            (string) ($validated['claim_token'] ?? ''),
            $coords['rel_x'],
            $coords['rel_y'],
            $coords['rel_z'],
            $requestedLevel
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    /**
     * Akzeptiert sowohl raum-relative Koordinaten (rel_x, rel_y, rel_z) als auch rueckwaertskompatibel
     * die bisherigen Raum-1-Weltkoordinaten (x, y, z -> x - 12.0, y, z - (-2.0)).
     *
     * @return array{rel_x: float, rel_y: float, rel_z: float}|null
     */
    private function extractRoomRelativeCoordinates(Request $request): ?array
    {
        $hasRelX = $request->has('rel_x') && $request->input('rel_x') !== null && $request->input('rel_x') !== '';

        if ($hasRelX) {
            $rawRelX = $request->input('rel_x');
            $rawRelY = ($request->has('rel_y') && $request->input('rel_y') !== null && $request->input('rel_y') !== '')
                ? $request->input('rel_y')
                : $request->input('y');
            $rawRelZ = $request->input('rel_z');

            if (
                !LobbyInstanceService::isValidFiniteNumeric($rawRelX)
                || !LobbyInstanceService::isValidFiniteNumeric($rawRelY)
                || !LobbyInstanceService::isValidFiniteNumeric($rawRelZ)
            ) {
                return null;
            }

            return [
                'rel_x' => (float) $rawRelX,
                'rel_y' => (float) $rawRelY,
                'rel_z' => (float) $rawRelZ,
            ];
        }

        $rawX = $request->input('x');
        $rawY = $request->input('y');
        $rawZ = $request->input('z');

        if (
            !LobbyInstanceService::isValidFiniteNumeric($rawX)
            || !LobbyInstanceService::isValidFiniteNumeric($rawY)
            || !LobbyInstanceService::isValidFiniteNumeric($rawZ)
        ) {
            return null;
        }

        return [
            'rel_x' => (float) $rawX - LavaParkourService::ROOM1_ORIGIN_X,
            'rel_y' => (float) $rawY,
            'rel_z' => (float) $rawZ - LavaParkourService::ROOM1_ORIGIN_Z,
        ];
    }

    private function invalidRunJsonResponse(string $message = 'Ungültiger Lauf.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422, $this->securityHeaders());
    }

    private function guestForbiddenResponse(LavaParkourService $lavaParkour): JsonResponse
    {
        $denied = $lavaParkour->checkSanctumAccess(null);

        return response()->json(
            $denied['body'] ?? [
                'success' => false,
                'guest' => true,
                'code' => 'login_required',
                'sanctum_block_reason' => 'login_required',
                'login_url' => '/login',
                'dashboard_url' => '/dashboard',
                'message' => 'Bitte melde dich an, um die Halle der Helden oder das Magma-Level zu betreten.',
            ],
            $denied['status'] ?? 403,
            $this->securityHeaders()
        );
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

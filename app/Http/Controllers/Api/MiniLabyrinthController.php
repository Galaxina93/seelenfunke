<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\Gamification\LobbyInstanceService;
use App\Services\Gamification\MiniLabyrinthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MiniLabyrinthController extends Controller
{
    public function start(Request $request, MiniLabyrinthService $miniLabyrinth): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if ($blocked = $this->guardAgainstManipulation($request, $miniLabyrinth, $customer)) {
            return $blocked;
        }

        if ($customer === null) {
            return $this->guestUnauthorizedResponse($miniLabyrinth);
        }

        $result = $miniLabyrinth->startRun($customer);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function checkpoint(Request $request, MiniLabyrinthService $miniLabyrinth): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if ($blocked = $this->guardAgainstManipulation($request, $miniLabyrinth, $customer)) {
            return $blocked;
        }

        if ($customer === null) {
            return $this->guestUnauthorizedResponse($miniLabyrinth);
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
            'x' => ['required', 'numeric'],
            'y' => ['nullable', 'numeric'],
            'z' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            $miniLabyrinth->invalidateRun($customer);

            return response()->json([
                'success' => false,
                'message' => 'Ungültiger Lauf.',
            ], 422, $this->securityHeaders());
        }

        $rawY = $request->input('y');
        $y = ($rawY !== null && $rawY !== '') ? (float) $rawY : 0.0;

        $result = $miniLabyrinth->recordCheckpoint(
            $customer,
            (string) $request->input('run_id'),
            (float) $request->input('x'),
            $y,
            (float) $request->input('z')
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function claim(Request $request, MiniLabyrinthService $miniLabyrinth): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if ($blocked = $this->guardAgainstManipulation($request, $miniLabyrinth, $customer)) {
            return $blocked;
        }

        if ($customer === null) {
            return $this->guestUnauthorizedResponse($miniLabyrinth);
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
            'claim_token' => ['required', 'string', 'max:256'],
            'x' => ['required', 'numeric'],
            'y' => ['nullable', 'numeric'],
            'z' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            $miniLabyrinth->invalidateRun($customer);

            return response()->json([
                'success' => false,
                'message' => 'Ungültiger Lauf.',
            ], 422, $this->securityHeaders());
        }

        $rawY = $request->input('y');
        $y = ($rawY !== null && $rawY !== '') ? (float) $rawY : 0.0;

        $result = $miniLabyrinth->claimReward(
            $customer,
            (string) $request->input('run_id'),
            (string) $request->input('claim_token'),
            (float) $request->input('x'),
            $y,
            (float) $request->input('z')
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    private function guardAgainstManipulation(
        Request $request,
        MiniLabyrinthService $miniLabyrinth,
        ?Customer $customer
    ): ?JsonResponse {
        $all = $request->all();

        if (LobbyInstanceService::containsForbiddenPayloadKeys($all)) {
            $miniLabyrinth->invalidateRun($customer);

            return response()->json([
                'success' => false,
                'ok' => false,
                'error' => 'forbidden_payload',
                'message' => 'Unzulässige Konsolen-Anfrage blockiert.',
            ], 422, $this->securityHeaders());
        }

        foreach (['x', 'y', 'z'] as $coordKey) {
            if (!array_key_exists($coordKey, $all) || $all[$coordKey] === null || $all[$coordKey] === '') {
                continue;
            }

            $val = $all[$coordKey];
            if (is_array($val) || is_object($val) || is_bool($val) || !is_numeric($val) || !is_finite((float) $val)) {
                $miniLabyrinth->invalidateRun($customer);

                return response()->json([
                    'success' => false,
                    'ok' => false,
                    'error' => 'forbidden_payload',
                    'message' => 'Unzulässige Konsolen-Anfrage blockiert.',
                ], 422, $this->securityHeaders());
            }
        }

        return null;
    }

    private function guestUnauthorizedResponse(MiniLabyrinthService $miniLabyrinth): JsonResponse
    {
        $variant = $miniLabyrinth->dailyVariant();

        return response()->json([
            'success' => false,
            'guest' => true,
            'variant' => $variant,
            'grid' => $miniLabyrinth->getVariantLayout($variant),
            'entrance' => [
                'x' => MiniLabyrinthService::ENTRANCE_X,
                'z' => MiniLabyrinthService::ENTRANCE_Z,
            ],
            'cp1' => [
                'x' => MiniLabyrinthService::CP1_X,
                'z' => MiniLabyrinthService::CP1_Z,
            ],
            'goal' => [
                'x' => MiniLabyrinthService::GOAL_X,
                'z' => MiniLabyrinthService::GOAL_Z,
            ],
            'message' => 'Bitte melde dich an, um täglich 3 Bonus-Funken im Mini-Labyrinth mitzunehmen.',
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\Gamification\LobbyInstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerLobbyController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const NUMERIC_STATE_KEYS = [
        'preferred_instance',
        'x',
        'y',
        'z',
        'rot_y',
        'rotY',
        'speed',
        'combo_seq',
        'comboSeq',
        'combo_stage',
        'comboStage',
        'ability_slot',
        'abilitySlot',
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_STATE_KEYS = [
        'x',
        'y',
        'z',
        'rot_y',
        'rotY',
        'speed',
        'zone',
        'combo_seq',
        'comboSeq',
        'combo_stage',
        'comboStage',
        'emote',
        'ability_slot',
        'abilitySlot',
    ];

    public function join(Request $request, LobbyInstanceService $lobby): JsonResponse
    {
        $actor = $lobby->resolveActorFromAuth();
        if ($blocked = $this->guardAgainstConsoleManipulation($request, $actor['actor_key'] ?? null)) {
            return $blocked;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $isAdmin = $customer === null
            && $actor !== null
            && str_starts_with((string) ($actor['actor_key'] ?? ''), 'admin:');

        if ($denied = $lobby->checkZoneSanctumRestriction($customer instanceof Customer ? $customer : null, $request->input('zone'), $isAdmin)) {
            if ($actor !== null) {
                $lobby->leave($actor);
            }

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        if ($actor === null) {
            return response()->json($lobby->guestJoinPayload(), 200, $this->securityHeaders());
        }

        $allowedKeys = array_merge(['preferred_instance'], self::ALLOWED_STATE_KEYS);
        $payload = $lobby->join($actor, $request->only($allowedKeys));

        return response()->json($payload, 200, $this->securityHeaders());
    }

    public function sync(Request $request, LobbyInstanceService $lobby): JsonResponse
    {
        $actor = $lobby->resolveActorFromAuth();
        if ($blocked = $this->guardAgainstConsoleManipulation($request, $actor['actor_key'] ?? null)) {
            return $blocked;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $isAdmin = $customer === null
            && $actor !== null
            && str_starts_with((string) ($actor['actor_key'] ?? ''), 'admin:');

        if ($denied = $lobby->checkZoneSanctumRestriction($customer instanceof Customer ? $customer : null, $request->input('zone'), $isAdmin)) {
            if ($actor !== null) {
                $lobby->leave($actor);
            }

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        if ($actor === null) {
            return response()->json($lobby->guestSyncPayload(), 200, $this->securityHeaders());
        }

        $payload = $lobby->sync($actor, $request->only(self::ALLOWED_STATE_KEYS));

        return response()->json($payload, 200, $this->securityHeaders());
    }

    public function switchInstance(Request $request, LobbyInstanceService $lobby): JsonResponse
    {
        $actor = $lobby->resolveActorFromAuth();
        if ($blocked = $this->guardAgainstConsoleManipulation($request, $actor['actor_key'] ?? null)) {
            return $blocked;
        }

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $isAdmin = $customer === null
            && $actor !== null
            && str_starts_with((string) ($actor['actor_key'] ?? ''), 'admin:');

        if ($denied = $lobby->checkZoneSanctumRestriction($customer instanceof Customer ? $customer : null, 'heroes', $isAdmin)) {
            if ($actor !== null) {
                $lobby->leave($actor);
            }

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $validator = Validator::make($request->all(), [
            'target_instance' => ['required', 'integer', 'min:1', 'max:' . LobbyInstanceService::MAX_INSTANCES],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Ungültige Lobby-Instanz (1 bis ' . LobbyInstanceService::MAX_INSTANCES . ').',
                'errors' => $validator->errors(),
            ], 422, $this->securityHeaders());
        }

        $result = $lobby->switchInstance(
            $actor,
            (int) $request->input('target_instance'),
            $request->only(self::ALLOWED_STATE_KEYS)
        );

        if (empty($result['ok'])) {
            return response()->json([
                'ok' => false,
                'message' => (string) ($result['message'] ?? 'Diese Instanz ist voll (20/20).'),
            ], (int) ($result['status'] ?? 409), $this->securityHeaders());
        }

        unset($result['status']);

        return response()->json($result, 200, $this->securityHeaders());
    }

    public function leave(Request $request, LobbyInstanceService $lobby): JsonResponse
    {
        $actor = $lobby->resolveActorFromAuth();
        if ($blocked = $this->guardAgainstConsoleManipulation($request, $actor['actor_key'] ?? null)) {
            return $blocked;
        }

        if ($actor !== null) {
            $lobby->leave($actor);
        }

        return response()->json([
            'ok' => true,
        ], 200, $this->securityHeaders());
    }

    private function guardAgainstConsoleManipulation(Request $request, ?string $actorKey = null): ?JsonResponse
    {
        $all = $request->all();
        $endpoint = $request->path();
        $logActor = ($actorKey !== null && $actorKey !== '')
            ? $actorKey
            : ('ip:' . (string) ($request->ip() ?: 'unknown'));

        LobbyInstanceService::inspectRequestTampering($all, $endpoint, $logActor);

        if (LobbyInstanceService::containsForbiddenPayloadKeys($all)) {
            LobbyInstanceService::logTamperThrottled($logActor, $endpoint, 'forbidden_console_keys');

            return $this->forbiddenPayloadResponse();
        }

        foreach (self::NUMERIC_STATE_KEYS as $numKey) {
            if (!array_key_exists($numKey, $all) || $all[$numKey] === null || $all[$numKey] === '') {
                continue;
            }

            $val = $all[$numKey];
            if (is_array($val) || is_object($val) || is_bool($val) || !LobbyInstanceService::isValidFiniteNumeric($val)) {
                LobbyInstanceService::logTamperThrottled($logActor, $endpoint, 'non_finite_numeric_field', [
                    'field' => $numKey,
                ]);

                return $this->forbiddenPayloadResponse();
            }
        }

        foreach (['zone', 'emote'] as $strKey) {
            if (array_key_exists($strKey, $all) && $all[$strKey] !== null && !is_string($all[$strKey])) {
                LobbyInstanceService::logTamperThrottled($logActor, $endpoint, 'non_string_state_field', [
                    'field' => $strKey,
                ]);

                return $this->forbiddenPayloadResponse();
            }
        }

        return null;
    }

    private function forbiddenPayloadResponse(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'forbidden_payload',
            'message' => 'Unzulässige Konsolen-Anfrage blockiert.',
        ], 422, $this->securityHeaders());
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

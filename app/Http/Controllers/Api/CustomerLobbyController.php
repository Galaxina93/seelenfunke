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
        'vx',
        'vz',
        'rot_y',
        'rotY',
        'speed',
        'combo_seq',
        'comboSeq',
        'combo_stage',
        'comboStage',
        'weapon_seq',
        'weaponActionSeq',
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
        'vx',
        'vz',
        'rot_y',
        'rotY',
        'speed',
        'zone',
        'combo_seq',
        'comboSeq',
        'combo_stage',
        'comboStage',
        'weapon_seq',
        'weaponActionSeq',
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

    public function chat(Request $request, LobbyInstanceService $lobby): JsonResponse
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
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        if ($actor === null) {
            $guestDenied = $lobby->checkGuestZoneRestriction('heroes');

            return response()->json($guestDenied['body'] ?? ['ok' => false, 'code' => 'login_required'], 403, $this->securityHeaders());
        }

        $rawMessage = $request->input('message', $request->input('text', ''));
        if (!is_string($rawMessage)) {
            LobbyInstanceService::logTamperThrottled((string) ($actor['actor_key'] ?? 'lobby_chat'), $request->path(), 'non_string_chat_message');

            return response()->json([
                'ok' => false,
                'success' => false,
                'code' => 'invalid_payload',
                'message' => 'Ungültiges Nachrichtenformat.',
            ], 422, $this->securityHeaders());
        }

        $result = $lobby->postChatMessage($actor, $rawMessage, true);
        $status = (int) ($result['status'] ?? (!empty($result['ok']) ? 200 : 422));
        unset($result['status']);

        return response()->json($result, $status, $this->securityHeaders());
    }

    public function report(Request $request, LobbyInstanceService $lobby): JsonResponse
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
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        if ($actor === null) {
            $guestDenied = $lobby->checkGuestZoneRestriction('heroes');

            return response()->json($guestDenied['body'] ?? ['ok' => false, 'code' => 'login_required'], 403, $this->securityHeaders());
        }

        $rawTarget = $request->input('target_peer_id', $request->input('target_player_id', $request->input('peer_id', '')));
        if (!is_string($rawTarget)) {
            LobbyInstanceService::logTamperThrottled((string) ($actor['actor_key'] ?? 'lobby_report'), $request->path(), 'non_string_report_target');

            return response()->json([
                'ok' => false,
                'success' => false,
                'code' => 'invalid_target',
                'message' => 'Ungültige Spieler-Kennung.',
            ], 422, $this->securityHeaders());
        }

        $result = $lobby->reportPeer($actor, $rawTarget);
        $status = (int) ($result['status'] ?? (!empty($result['ok']) ? 200 : 422));
        unset($result['status']);

        return response()->json($result, $status, $this->securityHeaders());
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

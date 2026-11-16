<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gamification\LobbyInstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerLobbyController extends Controller
{
    public function join(Request $request, LobbyInstanceService $lobby): JsonResponse
    {
        $actor = $lobby->resolveActorFromAuth();
        if ($actor === null) {
            return response()->json($lobby->guestJoinPayload(), 200, $this->securityHeaders());
        }

        $payload = $lobby->join(
            $actor,
            $request->only(['preferred_instance', 'x', 'z', 'rot_y', 'rotY', 'speed', 'zone'])
        );

        return response()->json($payload, 200, $this->securityHeaders());
    }

    public function sync(Request $request, LobbyInstanceService $lobby): JsonResponse
    {
        $actor = $lobby->resolveActorFromAuth();
        if ($actor === null) {
            return response()->json($lobby->guestSyncPayload(), 200, $this->securityHeaders());
        }

        $payload = $lobby->sync(
            $actor,
            $request->only(['x', 'z', 'rot_y', 'rotY', 'speed', 'zone'])
        );

        return response()->json($payload, 200, $this->securityHeaders());
    }

    public function switchInstance(Request $request, LobbyInstanceService $lobby): JsonResponse
    {
        $actor = $lobby->resolveActorFromAuth();
        if ($actor === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Bitte melde dich an, um Lobby-Instanzen zu wechseln.',
            ], 401, $this->securityHeaders());
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
            $request->only(['x', 'z', 'rot_y', 'rotY', 'speed', 'zone'])
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
        if ($actor !== null) {
            $lobby->leave($actor);
        }

        return response()->json([
            'ok' => true,
        ], 200, $this->securityHeaders());
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
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
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $tamper = LobbyInstanceService::inspectRequestTampering($request->all(), $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            if ($customer !== null) {
                $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);
            }

            return $this->invalidRunJsonResponse();
        }

        $level = 1;
        if ($request->has('level') && $request->input('level') !== null && $request->input('level') !== '') {
            $rawLevel = $request->input('level');
            if (is_array($rawLevel) || is_object($rawLevel) || is_bool($rawLevel) || !LobbyInstanceService::isValidFiniteNumeric($rawLevel)) {
                if ($customer !== null) {
                    $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);
                }

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

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'start', ['level' => $level]);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $result = $lavaParkour->startRun($customer, $level);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function commit(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $tamper = LobbyInstanceService::inspectRequestTampering($request->all(), $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            return $this->invalidRunJsonResponse();
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse('Ungültige Lauf-Kennung.');
        }

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'commit', $validator->validated());

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $result = $lavaParkour->commitRun($customer, (string) $request->input('run_id'));

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function leave(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $tamper = LobbyInstanceService::inspectRequestTampering($request->all(), $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            return $this->invalidRunJsonResponse();
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse('Ungültige Lauf-Kennung.');
        }

        $relX = null;
        if ($request->has('rel_x') && $request->input('rel_x') !== null && $request->input('rel_x') !== '') {
            $rawRelX = $request->input('rel_x');
            if (!LobbyInstanceService::isValidFiniteNumeric($rawRelX)) {
                return $this->invalidRunJsonResponse('Ungültige Positionsdaten.');
            }
            $relX = (float) $rawRelX;
        } elseif ($request->has('x') && $request->input('x') !== null && $request->input('x') !== '') {
            $rawX = $request->input('x');
            if (!LobbyInstanceService::isValidFiniteNumeric($rawX)) {
                return $this->invalidRunJsonResponse('Ungültige Positionsdaten.');
            }
            $relX = (float) $rawX - LavaParkourService::ORIGIN_X;
        }

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'leave', ['run_id' => (string) $request->input('run_id')]);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $result = $lavaParkour->leaveRun($customer, (string) $request->input('run_id'), $relX);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function hit(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $tamper = LobbyInstanceService::inspectRequestTampering($request->all(), $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat'] || $tamper['has_invalid_coords']) {
            return $this->invalidRunJsonResponse();
        }

        if ($request->has('used_shield') && is_array($request->input('used_shield'))) {
            return $this->invalidRunJsonResponse();
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['required', 'string', 'max:128'],
            'level' => ['nullable', 'integer', 'min:1', 'max:' . LavaParkourService::MAX_MAGMA_LEVEL],
            'cause' => ['nullable', 'string', 'max:64'],
            'used_shield' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse();
        }

        $validated = $validator->validated();
        $usedShield = filter_var($request->input('used_shield', false), FILTER_VALIDATE_BOOLEAN);

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'hit', [
                'run_id' => (string) $validated['run_id'],
                'used_shield' => $usedShield,
            ]);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $requestedLevel = isset($validated['level']) ? (int) $validated['level'] : null;
        $cause = isset($validated['cause']) ? (string) $validated['cause'] : null;

        $result = $lavaParkour->recordHit(
            $customer,
            (string) $validated['run_id'],
            $requestedLevel,
            $cause,
            $usedShield
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function checkpoint(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $tamper = LobbyInstanceService::inspectRequestTampering($request->all(), $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat']) {
            if ($customer !== null) {
                $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);
            }

            return $this->invalidRunJsonResponse();
        }

        if ($tamper['has_invalid_coords']) {
            return $this->invalidRunJsonResponse();
        }

        $validator = Validator::make($request->all(), [
            'run_id' => ['nullable', 'string', 'max:128'],
            'stage' => ['required', 'integer', 'in:1,2'],
            'level' => ['nullable', 'integer', 'min:1', 'max:' . LavaParkourService::MAX_MAGMA_LEVEL],
            'life_nonce' => ['nullable', 'string', 'max:128'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse();
        }

        $coords = $this->extractRoomRelativeCoordinates($request);
        if ($coords === null) {
            return $this->invalidRunJsonResponse();
        }

        $validated = $validator->validated();

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'checkpoint', $validated);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $requestedLevel = isset($validated['level']) ? (int) $validated['level'] : null;
        $lifeNonce = isset($validated['life_nonce']) ? (string) $validated['life_nonce'] : null;

        $result = $lavaParkour->recordCheckpoint(
            $customer,
            (string) ($validated['run_id'] ?? ''),
            (int) $validated['stage'],
            $coords['rel_x'],
            $coords['rel_y'],
            $coords['rel_z'],
            $requestedLevel,
            $lifeNonce
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function claim(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $tamper = LobbyInstanceService::inspectRequestTampering($request->all(), $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if ($tamper['has_explicit_cheat']) {
            if ($customer !== null) {
                $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);
            }

            return $this->invalidRunJsonResponse();
        }

        if ($tamper['has_invalid_coords']) {
            return $this->invalidRunJsonResponse();
        }

        // Manipulierte Felder wie amount, sparks oder rewarded_sparks im Request-Body werden protokolliert und strikt ignoriert.
        $validator = Validator::make($request->all(), [
            'run_id' => ['nullable', 'string', 'max:128'],
            'claim_token' => ['nullable', 'string', 'max:160'],
            'level' => ['nullable', 'integer', 'min:1', 'max:' . LavaParkourService::MAX_MAGMA_LEVEL],
            'life_nonce' => ['nullable', 'string', 'max:128'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse();
        }

        $coords = $this->extractRoomRelativeCoordinates($request);
        if ($coords === null) {
            return $this->invalidRunJsonResponse();
        }

        $validated = $validator->validated();

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'claim', $validated);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            $lavaParkour->invalidateActiveRunForCustomer((string) $customer->id);

            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $requestedLevel = isset($validated['level']) ? (int) $validated['level'] : null;
        $lifeNonce = isset($validated['life_nonce']) ? (string) $validated['life_nonce'] : null;

        $result = $lavaParkour->claimChest(
            $customer,
            (string) ($validated['run_id'] ?? ''),
            (string) ($validated['claim_token'] ?? ''),
            $coords['rel_x'],
            $coords['rel_y'],
            $coords['rel_z'],
            $requestedLevel,
            $lifeNonce
        );

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function buyPotion(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $rawAll = $request->all();
        $tamper = LobbyInstanceService::inspectRequestTampering($rawAll, $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if (
            $tamper['has_explicit_cheat']
            || $tamper['has_invalid_coords']
            || LobbyInstanceService::containsForbiddenPayloadKeys($rawAll)
        ) {
            return $this->invalidRunJsonResponse('Ungültige Anfrage.');
        }

        if ($request->has('quantity') && $request->input('quantity') !== null && $request->input('quantity') !== '') {
            $rawQty = $request->input('quantity');
            if (is_array($rawQty) || is_object($rawQty) || is_bool($rawQty) || !LobbyInstanceService::isValidFiniteNumeric($rawQty)) {
                return $this->invalidRunJsonResponse('Ungültige Menge.');
            }
        }

        $validator = Validator::make($rawAll, [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse('Ungültige Menge.');
        }

        $validated = $validator->validated();
        $qty = isset($validated['quantity']) ? (int) $validated['quantity'] : 1;

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'buy_potion', ['quantity' => $qty]);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $result = $lavaParkour->buyHealingPotion($customer, $qty);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function useItem(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $rawAll = $request->all();
        $tamper = LobbyInstanceService::inspectRequestTampering($rawAll, $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if (
            $tamper['has_explicit_cheat']
            || $tamper['has_invalid_coords']
            || LobbyInstanceService::containsForbiddenPayloadKeys($rawAll)
        ) {
            return $this->invalidRunJsonResponse('Ungültige Anfrage.');
        }

        if (is_array($request->input('item_key')) || is_array($request->input('slot'))) {
            return $this->invalidRunJsonResponse('Ungültige Anfrage.');
        }

        $validator = Validator::make($rawAll, [
            'item_key' => ['required', 'string', 'in:healing_potion'],
            'slot' => ['nullable', 'integer', 'in:1,2'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse('Ungültiger Gegenstand.');
        }

        $validated = $validator->validated();
        $slot = isset($validated['slot']) ? (int) $validated['slot'] : null;

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'use_item', $validated);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $result = $lavaParkour->useInventoryItem($customer, (string) $validated['item_key'], $slot);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    public function equipSlot(Request $request, LavaParkourService $lavaParkour): JsonResponse
    {
        [$customer, $admin, $actorKey] = $this->resolveActors();
        $rawAll = $request->all();
        $tamper = LobbyInstanceService::inspectRequestTampering($rawAll, $request->path(), $actorKey);

        if ($customer === null && $admin === null) {
            return $this->guestForbiddenResponse($lavaParkour);
        }

        if (
            $tamper['has_explicit_cheat']
            || $tamper['has_invalid_coords']
            || LobbyInstanceService::containsForbiddenPayloadKeys($rawAll)
        ) {
            return $this->invalidRunJsonResponse('Ungültige Anfrage.');
        }

        foreach (['quick_slot', 'item_key', 'from_bag_slot', 'to_bag_slot'] as $field) {
            if (array_key_exists($field, $rawAll) && (is_array($rawAll[$field]) || is_object($rawAll[$field]) || is_bool($rawAll[$field]))) {
                return $this->invalidRunJsonResponse('Ungültige Slot-Daten.');
            }
        }

        $normalizedInput = $rawAll;
        if (array_key_exists('item_key', $normalizedInput) && $normalizedInput['item_key'] === '') {
            $normalizedInput['item_key'] = null;
        }

        $validator = Validator::make($normalizedInput, [
            'quick_slot' => ['nullable', 'integer', 'in:1,2'],
            'item_key' => ['nullable', 'string', 'in:healing_potion,none'],
            'from_bag_slot' => ['nullable', 'integer', 'min:0', 'max:5'],
            'to_bag_slot' => ['nullable', 'integer', 'min:0', 'max:5'],
        ]);

        if ($validator->fails()) {
            return $this->invalidRunJsonResponse('Ungültige Slot-Daten.');
        }

        $validated = $validator->validated();
        $quickSlot = isset($validated['quick_slot']) ? (int) $validated['quick_slot'] : null;
        $rawItemKey = $validated['item_key'] ?? null;
        $itemKey = ($rawItemKey === 'healing_potion') ? 'healing_potion' : null;
        $fromBagSlot = array_key_exists('from_bag_slot', $validated) && $validated['from_bag_slot'] !== null
            ? (int) $validated['from_bag_slot']
            : null;
        $toBagSlot = array_key_exists('to_bag_slot', $validated) && $validated['to_bag_slot'] !== null
            ? (int) $validated['to_bag_slot']
            : null;

        if ($customer === null && $admin !== null) {
            $result = $lavaParkour->handleAdminPreviewAction($admin, 'equip_slot', [
                'quick_slot' => $quickSlot,
                'item_key' => $itemKey,
                'from_bag_slot' => $fromBagSlot,
                'to_bag_slot' => $toBagSlot,
            ]);

            return response()->json($result['body'], $result['status'], $this->securityHeaders());
        }

        if ($denied = $lavaParkour->checkSanctumAccess($customer)) {
            return response()->json($denied['body'], $denied['status'], $this->securityHeaders());
        }

        $result = $lavaParkour->equipInventorySlot($customer, $quickSlot, $itemKey, $fromBagSlot, $toBagSlot);

        return response()->json($result['body'], $result['status'], $this->securityHeaders());
    }

    /**
     * Konsistenter Guard-Resolver analog zu CustomerLobbyController / LobbyInstanceService::resolveActorFromAuth.
     *
     * @return array{0: ?Customer, 1: ?Admin, 2: ?string}
     */
    private function resolveActors(): array
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer instanceof Customer) {
            return [$customer, null, 'customer:' . $customer->id];
        }

        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin) {
            return [null, $admin, 'admin:' . $admin->id];
        }

        return [null, null, null];
    }

    /**
     * Akzeptiert sowohl raum-relative Koordinaten (rel_x, rel_y, rel_z) als auch rueckwaertskompatibel
     * die Raum-1-Weltkoordinaten (x, y, z -> x - ORIGIN_X, y - ORIGIN_Y, z - ORIGIN_Z).
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
            'rel_x' => (float) $rawX - LavaParkourService::ORIGIN_X,
            'rel_y' => (float) $rawY - LavaParkourService::ORIGIN_Y,
            'rel_z' => (float) $rawZ - LavaParkourService::ORIGIN_Z,
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

<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Gamification\FunkenreichSsoBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FunkenreichGatewayController extends Controller
{
    public function enter(FunkenreichSsoBridgeService $ssoBridge): RedirectResponse
    {
        if (!$ssoBridge->canCurrentVisitorEnterMmorpg()) {
            abort(403, 'Das Funkenreich befindet sich aktuell in der geschlossenen Testphase.');
        }

        $url = $ssoBridge->buildSignedEntryRedirectUrl();
        if (!$url) {
            abort(403, 'Das Funkenreich befindet sich aktuell in der geschlossenen Testphase.');
        }

        return redirect()->away($url);
    }

    public function accessStatus(Request $request, FunkenreichSsoBridgeService $ssoBridge): JsonResponse
    {
        $subType = strtolower(trim((string) ($request->query('sub_type') ?? $request->query('type') ?? '')));
        $subId = trim((string) ($request->query('sub_id') ?? $request->query('id') ?? ''));
        $tsHeader = (string) $request->header('X-Funkenreich-Timestamp', '');
        $sigHeader = (string) $request->header('X-Funkenreich-Signature', '');

        if (!$ssoBridge->verifyAccessStatusSignature($subType, $subId, $tsHeader, $sigHeader)) {
            return response()->json([
                'allowed' => false,
                'funki_level' => 0,
                'funken_balance' => 0,
                'error' => 'invalid_signature',
            ], 401);
        }

        return response()->json($ssoBridge->resolveLiveAccessStatus($subType, $subId));
    }
}

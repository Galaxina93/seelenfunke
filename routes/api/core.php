<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\System\SystemUserDevice;
use App\Services\AI\AiSupportService;

Route::get('/user', function (Request $request) {
    $user = $request->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    $type = 'customer';
    if ($user instanceof \App\Models\Admin\Admin) {
        $type = 'admin';
    } elseif ($user instanceof \App\Models\Employee\Employee) {
        $type = 'employee';
    }

    $userData = $user->toArray();
    $userData['user_type'] = $type;
    $userData['permissions'] = \App\Models\Employee\Employee::buildPermissionsForUser($user);

    return response()->json($userData);
});

Route::post('/device/register', function (Request $request) {
    $data = $request->validate([
        'fcm_token' => 'required|string|max:512',
        'device_name' => 'nullable|string|max:255',
    ]);
    $user = $request->user();
    SystemUserDevice::updateOrCreate(
        ['userable_id' => $user->id, 'userable_type' => get_class($user), 'fcm_token' => $data['fcm_token']],
        ['device_name' => $data['device_name'] ?? 'Unbekanntes Gerät']
    );
    return response()->json(['success' => true]);
});

Route::post('/logout', function (Request $request) {
    $user = $request->user();
    if ($user) {
        $fcmToken = trim((string) $request->input('fcm_token', ''));
        if ($fcmToken !== '') {
            SystemUserDevice::where('userable_id', $user->id)
                ->where('userable_type', get_class($user))
                ->where('fcm_token', $fcmToken)
                ->delete();
        }
        $token = $user->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Erfolgreich abgemeldet.',
    ]);
});

Route::get('/funki/command', function (Request $request, AiSupportService $bot) {
    if (!$request->user() instanceof \App\Models\Admin\Admin) {
        abort(403, 'Nur für die Geschäftsleitung verfügbar.');
    }
    return response()->json($bot->getUltimateCommand());
});

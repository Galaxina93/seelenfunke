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

Route::get('/funki/command', function (Request $request, AiSupportService $bot) {
    $user = $request->user();
    if (!$user instanceof \App\Models\Admin\Admin && !$user instanceof \App\Models\Employee\Employee) {
        abort(403, 'Nur für Administratoren und Mitarbeiter verfügbar.');
    }
    return response()->json($bot->getUltimateCommand());
});

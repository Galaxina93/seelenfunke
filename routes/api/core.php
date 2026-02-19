<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\UserDevice;
use App\Services\FunkiBotService;

Route::get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/device/register', function (Request $request) {
    $data = $request->validate([
        'fcm_token' => 'required|string',
        'device_name' => 'nullable|string',
    ]);
    $user = $request->user();
    UserDevice::updateOrCreate(
        ['userable_id' => $user->id, 'userable_type' => get_class($user), 'fcm_token' => $data['fcm_token']],
        ['device_name' => $data['device_name'] ?? 'Unbekanntes Gerät']
    );
    return response()->json(['success' => true]);
});

Route::get('/funki/command', function (FunkiBotService $bot) {
    return response()->json($bot->getUltimateCommand());
});

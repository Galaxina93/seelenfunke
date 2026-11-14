<?php

use App\Services\System\AndroidReleaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('funki/system/app-update')->group(function () {

    Route::get('/check', function (AndroidReleaseService $releaseService) {
        $manifest = $releaseService->getLatestManifest();

        return response()->json([
            'success' => true,
            'has_release' => $manifest !== null,
            'data' => $manifest,
        ], 200, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ]);
    })->middleware('throttle:ota-check');

    Route::get('/download', function (Request $request, AndroidReleaseService $releaseService) {
        return $releaseService->streamLatestApk($request);
    })->middleware('throttle:ota-download');

    Route::post('/publish', function (Request $request, AndroidReleaseService $releaseService) {
        $validated = $request->validate([
            'apk' => 'required|file|max:97280', // max ~95 MB in KB
            'version_code' => 'required|integer|min:1|max:100000000',
            'version_name' => ['required', 'string', 'max:32', 'regex:/^[0-9]+(\.[0-9]+){1,3}(-[A-Za-z0-9._]+)?$/'],
            'sha256' => ['nullable', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'expected_sha256' => ['nullable', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'release_notes' => 'nullable|string|max:500',
        ]);

        $uploadedFile = $request->file('apk');
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültiger Datei-Upload.',
            ], 422);
        }

        $expectedSha = $validated['sha256'] ?? $validated['expected_sha256'] ?? null;

        try {
            $manifest = $releaseService->storeVerifiedRelease(
                sourceTempFile: $uploadedFile->getRealPath(),
                versionCode: (int) $validated['version_code'],
                versionName: (string) $validated['version_name'],
                expectedSha256: $expectedSha,
                releaseNotes: $validated['release_notes'] ?? null,
                allowSameVersionRebuild: true
            );

            return response()->json([
                'success' => true,
                'message' => 'Android-Release verifiziert und im Tresor aktiviert.',
                'data' => $manifest,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    })->middleware('throttle:ota-publish');
});

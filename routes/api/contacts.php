<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementContact;

Route::prefix('funki/contacts')->group(function () {

    // 1. List Contacts
    Route::get('/', function (Request $request) {
        $query = ManagementContact::query();
        
        if ($request->has('search') && !empty($request->search)) {
            $search = '%' . addcslashes((string) $request->search, '%_\\') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('nickname', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search);
            });
        }

        $contacts = $query->orderBy('is_favorite', 'desc')
            ->orderBy('first_name', 'asc')
            ->orderBy('last_name', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $contacts]);
    });

    // 2. Create Contact
    Route::post('/', function (Request $request) {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'relation_type' => 'nullable|string|max:100',
            'birthday' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'system_instructions' => 'nullable|string|max:10000',
            'is_favorite' => 'boolean',
            'funkira_call_whitelist' => 'boolean'
        ]);

        $contact = ManagementContact::create($data);
        return response()->json(['success' => true, 'data' => $contact]);
    });

    // 3. Get Contact Details
    Route::get('/{id}', function ($id) {
        $contact = ManagementContact::findOrFail($id);
        return response()->json(['success' => true, 'data' => $contact]);
    });

    // 3b. Stream Contact Avatar Image
    Route::get('/{id}/avatar', function ($id) {
        $contact = ManagementContact::findOrFail($id);
        $rel = ltrim((string) ($contact->avatar_path ?? ''), '/');
        if (str_starts_with($rel, 'storage/')) {
            $rel = substr($rel, strlen('storage/'));
        }
        if ($rel === '' || str_contains($rel, '..') || !\Illuminate\Support\Facades\Storage::disk('public')->exists($rel)) {
            abort(404);
        }
        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($rel);
        $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($rel) ?: 'image/jpeg';
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    });

    // 3c. Upload / Update Contact Avatar Image
    Route::post('/{id}/avatar', function (Request $request, $id) {
        $contact = ManagementContact::findOrFail($id);
        $request->validate([
            'avatar' => 'required|file|max:5120',
        ]);

        $file = $request->file('avatar');
        $realPath = $file ? $file->getRealPath() : false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $realPath ? finfo_file($finfo, $realPath) : false;
        finfo_close($finfo);

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($detectedMime) || !isset($mimeToExt[$detectedMime])) {
            return response()->json(['success' => false, 'message' => 'Ungültiges Bildformat (nur JPG, PNG oder WebP).'], 422);
        }

        if (!empty($contact->avatar_path) && !str_contains((string) $contact->avatar_path, '..') && \Illuminate\Support\Facades\Storage::disk('public')->exists($contact->avatar_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($contact->avatar_path);
        }

        $storedPath = $file->storeAs(
            'leitung/person_profiles',
            \Illuminate\Support\Str::uuid()->toString() . '.' . $mimeToExt[$detectedMime],
            'public'
        );

        $contact->update(['avatar_path' => $storedPath]);
        return response()->json(['success' => true, 'data' => $contact->fresh()]);
    });

    // 4. Update Contact
    Route::put('/{id}', function (Request $request, $id) {
        $contact = ManagementContact::findOrFail($id);
        
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'relation_type' => 'nullable|string|max:100',
            'birthday' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'system_instructions' => 'nullable|string|max:10000',
            'is_favorite' => 'boolean',
            'funkira_call_whitelist' => 'boolean'
        ]);

        $contact->update($data);
        return response()->json(['success' => true, 'data' => $contact]);
    });

    // 5. Toggle Favorite
    Route::put('/{id}/favorite', function (Request $request, $id) {
        $data = $request->validate(['is_favorite' => 'required|boolean']);
        $contact = ManagementContact::findOrFail($id);
        $contact->update(['is_favorite' => $data['is_favorite']]);
        return response()->json(['success' => true]);
    });

    // 5b. Toggle Funkira Call Whitelist
    Route::put('/{id}/call-whitelist', function (Request $request, $id) {
        $data = $request->validate(['funkira_call_whitelist' => 'required|boolean']);
        $contact = ManagementContact::findOrFail($id);
        $contact->update(['funkira_call_whitelist' => $data['funkira_call_whitelist']]);
        return response()->json(['success' => true, 'data' => $contact]);
    });

    // 6. Delete Contact
    Route::delete('/{id}', function ($id) {
        ManagementContact::destroy($id);
        return response()->json(['success' => true]);
    });

});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementContact;

Route::prefix('funki/contacts')->group(function () {

    // 1. List Contacts
    Route::get('/', function (Request $request) {
        $query = ManagementContact::query();
        
        if ($request->has('search') && !empty($request->search)) {
            $search = '%' . $request->search . '%';
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
            'is_favorite' => 'boolean'
        ]);

        $contact = ManagementContact::create($data);
        return response()->json(['success' => true, 'data' => $contact]);
    });

    // 3. Get Contact Details
    Route::get('/{id}', function ($id) {
        $contact = ManagementContact::findOrFail($id);
        return response()->json(['success' => true, 'data' => $contact]);
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
            'is_favorite' => 'boolean'
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

    // 6. Delete Contact
    Route::delete('/{id}', function ($id) {
        ManagementContact::destroy($id);
        return response()->json(['success' => true]);
    });

});

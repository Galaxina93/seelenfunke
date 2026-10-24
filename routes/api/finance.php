<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Accounting\AccountingCategory;
use App\Models\Order\OrderOrder;
use Carbon\Carbon;
use Illuminate\Support\Str;

/*<!--das ist die Datei unter: routes/api/finance.php-->*/

Route::get('/funki/financials/kpis', function (Request $request) {
    $adminId = $request->user()->id;
    $now = Carbon::now();
    $month = $now->month;
    $year = $now->year;

    $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
    $fixedIncome = 0;
    $fixedExpenses = 0;
    foreach ($groups as $group) {
        foreach ($group->items as $item) {
            if (!$item->first_payment_date) continue;
            try {
                $firstPaymentDate = Carbon::parse($item->first_payment_date);
                $startMonth = $firstPaymentDate->month;
            } catch (\Exception $e) {
                continue;
            }
            $interval = $item->interval_months ?: 1;
            $diff = ($month - $startMonth);
            if ($diff < 0) $diff += 12;
            if (($diff % $interval) === 0) {
                if ($item->amount >= 0) $fixedIncome += $item->amount;
                else $fixedExpenses += $item->amount;
            }
        }
    }

    $specialIssues = AccountingSpecialIssue::where('admin_id', $adminId)
        ->whereYear('execution_date', $year)
        ->whereMonth('execution_date', $month)
        ->get();
    $specialExpenses = 0;
    foreach($specialIssues as $issue) {
        if($issue->amount < 0) $specialExpenses += $issue->amount;
    }

    $shopRevenue = OrderOrder::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total_price') / 100;

    $totalIncome = $fixedIncome + $shopRevenue;
    $totalExpenses = $fixedExpenses + $specialExpenses;
    $available = $totalIncome + $totalExpenses;

    return response()->json([
        'available' => round($available, 2),
        'shop_revenue' => round($shopRevenue, 2),
        'fixed_expenses' => round($fixedExpenses, 2),
        'special_expenses' => round($specialExpenses, 2),
        'month_label' => $now->locale('de')->monthName . ' ' . $year
    ]);
});

Route::get('/funki/financials/categories', function (Request $request) {
    $categories = AccountingCategory::where('admin_id', $request->user()->id)->orderBy('usage_count', 'desc')->pluck('name');
    if ($categories->isEmpty()) {
        return [
            "Fitness", "Werbung & Marketing", "Software & Lizenzen", "Verpackungen", 
            "Rohmaterial", "Wareneinkauf", "Arbeitsmaterial", "Sprit", "Haushalt", 
            "Kind", "Steuer", "Schmutzwasser", "Nahrungsmittel", "Feiertage", 
            "Sonstiges", "Auto", "Technik", "Friseur", "Geschenk", "Kleidung", 
            "Kosmetik", "Gesundheit", "Freizeit", "Glücksspiel"
        ];
    }
    return $categories;
});

Route::delete('/funki/financials/categories/{name}', function (Request $request, $name) {
    $decodedName = urldecode($name);

    $deleted = AccountingCategory::where('admin_id', $request->user()->id)
        ->where('name', $decodedName)
        ->delete();

    if ($deleted) {
        AccountingSpecialIssue::where('admin_id', $request->user()->id)
            ->where('category', $decodedName)
            ->update(['category' => 'Sonstiges']);
    }

    return response()->json(['success' => true]);
});

Route::post('/funki/financials/quick-entry', function (Request $request) {
    Log::info('Quick-Entry Request received', ['files_count' => count($request->allFiles())]);

    $data = $request->validate([
        'title' => 'required|string',
        'amount' => 'required',
        'category' => 'nullable|string',
        'is_business' => 'boolean',
        'date' => 'nullable|date',
        'location' => 'nullable|string'
    ]);

    $amount = (float)str_replace(',', '.', (string)$data['amount']);
    // WICHTIG: Sonderausgaben / Belegkosten sind im System stets negativ!
    if ($amount > 0) {
        $amount = -$amount;
    }

    $issue = AccountingSpecialIssue::create([
        'id' => Str::uuid(),
        'admin_id' => $request->user()->id,
        'title' => $data['title'],
        'amount' => $amount,
        'category' => $data['category'] ?? 'Sonstiges',
        'execution_date' => $data['date'] ?? now(),
        'is_business' => $data['is_business'] ?? false,
        'location' => $data['location'] ?? 'App QuickEntry',
        'file_paths' => []
    ]);

    // Mehrere Dateien verarbeiten (Array Key 'file')
    if ($request->hasFile('file')) {
        $paths = [];
        $files = is_array($request->file('file')) ? $request->file('file') : [$request->file('file')];
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->getSize() > 20 * 1024 * 1024) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = strtolower((string) $file->getMimeType());
            if (!in_array($ext, $allowedExts, true) || !in_array($mime, $allowedMimes, true)) {
                continue;
            }
            $path = $file->store('buchhaltung/receipts', 'local');
            $paths[] = $path;
        }
        $issue->update(['file_paths' => $paths]);
        Log::info('Files stored', ['paths' => $paths]);
    }

    if (!empty($data['category'])) {
        $cat = AccountingCategory::firstOrCreate(
            ['admin_id' => $request->user()->id, 'name' => $data['category']],
            ['usage_count' => 0]
        );
        $cat->increment('usage_count');
    }

    return response()->json(['success' => true, 'entry' => $issue]);
});

Route::post('/funki/financials/scan-receipt', function (Request $request) {
    if (!$request->hasFile('file')) {
        return response()->json([
            'success' => false,
            'message' => 'Keine Belegdatei hochgeladen.'
        ], 400);
    }

    $request->validate([
        'file' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
    ]);

    $file = $request->file('file');
    $autoSave = filter_var($request->input('auto_save', false), FILTER_VALIDATE_BOOLEAN);

    $categories = AccountingCategory::where('admin_id', $request->user()->id)
        ->orderBy('usage_count', 'desc')
        ->pluck('name')
        ->toArray();

    $aiResult = \App\Services\AI\ReceiptAiScannerService::analyze($file, $categories);

    if (!$aiResult) {
        return response()->json([
            'success' => false,
            'message' => 'Beleg konnte nicht automatisch erkannt werden. Bitte manuell erfassen.'
        ], 422);
    }

    if ($autoSave) {
        $path = $file->store('buchhaltung/receipts', 'local');

        $amount = (float)$aiResult['amount_formatted'];
        if ($amount > 0) {
            $amount = -$amount;
        }

        $issue = AccountingSpecialIssue::create([
            'id' => Str::uuid(),
            'admin_id' => $request->user()->id,
            'title' => $aiResult['title'],
            'amount' => $amount,
            'category' => $aiResult['category'] ?: 'Sonstiges',
            'execution_date' => $aiResult['date'] ?: now()->format('Y-m-d'),
            'is_business' => $aiResult['is_business'],
            'location' => $aiResult['location'] ?: 'App Beleg-Scan',
            'tax_rate' => $aiResult['is_business'] ? $aiResult['tax_rate'] : null,
            'invoice_number' => $aiResult['is_business'] ? $aiResult['invoice_number'] : null,
            'file_paths' => [$path]
        ]);

        if (!empty($aiResult['category'])) {
            $cat = AccountingCategory::firstOrCreate(
                ['admin_id' => $request->user()->id, 'name' => $aiResult['category']],
                ['usage_count' => 0]
            );
            $cat->increment('usage_count');
        }

        return response()->json([
            'success' => true,
            'auto_saved' => true,
            'data' => $aiResult,
            'entry' => $issue,
            'message' => "Beleg '{$aiResult['title']}' ({$aiResult['amount_formatted']} €) wurde automatisch erfasst und gebucht."
        ]);
    }

    return response()->json([
        'success' => true,
        'auto_saved' => false,
        'data' => $aiResult,
        'message' => "Beleg '{$aiResult['title']}' erkannt."
    ]);
});

Route::get('/funki/financials/variable', function (Request $request) {
    // Wir lesen das Limit aus der URL, Standard ist 50 (geclampt auf 1..200 gegen Speicher-DoS)
    $limit = max(1, min(200, (int) $request->query('limit', 50)));

    return AccountingSpecialIssue::where('admin_id', $request->user()->id)
        ->orderBy('execution_date', 'desc')
        ->orderBy('created_at', 'desc')
        ->take($limit)
        ->get();
});

Route::put('/funki/financials/variable/{id}', function (Request $request, $id) {
    $issue = AccountingSpecialIssue::where('admin_id', $request->user()->id)->findOrFail($id);

    $data = $request->validate([
        'title' => 'required|string',
        'amount' => 'required',
        'category' => 'required|string',
        'execution_date' => 'required|date',
        'is_business' => 'boolean',
        'location' => 'nullable|string'
    ]);

    // Basis-Daten aktualisieren
    $issue->update([
        'title' => $data['title'],
        'amount' => (float)str_replace(',', '.', $data['amount']),
        'category' => $data['category'],
        'execution_date' => $data['execution_date'],
        'is_business' => filter_var($data['is_business'], FILTER_VALIDATE_BOOLEAN),
        'location' => $data['location'] ?? null,
    ]);

    // DATEI-LOGIK FIX:
    // Wir prüfen auf 'file', da Laravel bei Multipart-Arrays das Key-Handling übernimmt
    if ($request->hasFile('file')) {
        $existingPaths = $issue->file_paths ?? [];
        $files = $request->file('file');

        // Sicherstellen, dass wir immer ein Array haben
        if (!is_array($files)) {
            $files = [$files];
        }

        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->getSize() > 20 * 1024 * 1024) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = strtolower((string) $file->getMimeType());
            if (!in_array($ext, $allowedExts, true) || !in_array($mime, $allowedMimes, true)) {
                continue;
            }
            $existingPaths[] = $file->store('buchhaltung/receipts', 'local');
        }

        $issue->update(['file_paths' => $existingPaths]);
        Log::info('Update: Neue Dateien hinzugefügt', ['count' => count($files)]);
    }

    return response()->json(['success' => true]);
});

Route::delete('/funki/financials/variable/{id}', function (Request $request, $id) {
    $issue = AccountingSpecialIssue::where('admin_id', $request->user()->id)->findOrFail($id);
    if (!empty($issue->file_paths) && is_array($issue->file_paths)) {
        foreach ($issue->file_paths as $filePath) {
            $pathStr = ltrim((string) $filePath, '/');
            if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with($pathStr, 'buchhaltung/') && Storage::disk('local')->exists($pathStr)) {
                Storage::disk('local')->delete($pathStr);
            }
        }
    }
    $issue->delete();
    return response()->json(['success' => true]);
});

Route::get('/funki/financials/fixed', function (Request $request) {
    return AccountingGroup::with('items')
        ->where('admin_id', $request->user()->id)
        ->orderBy('position')
        ->get();
});

Route::post('/funki/financials/groups', function (Request $request) {
    $data = $request->validate([
        'name' => 'required|string',
        'type' => 'required|in:expense,income'
    ]);

    $position = AccountingGroup::where('admin_id', $request->user()->id)->max('position') + 1;

    $group = AccountingGroup::create([
        'id' => Str::uuid(),
        'admin_id' => $request->user()->id,
        'name' => $data['name'],
        'type' => $data['type'],
        'position' => $position
    ]);
    return response()->json(['success' => true, 'group' => $group]);
});

// Gruppe löschen (Nur wenn leer)
Route::delete('/funki/financials/groups/{id}', function (Request $request, $id) {
    $group = AccountingGroup::with('items')->where('admin_id', $request->user()->id)->findOrFail($id);
    if ($group->items->count() > 0) {
        return response()->json(['error' => 'Gruppe enthält noch Kostenstellen.'], 400);
    }
    $group->delete();
    return response()->json(['success' => true]);
});

// Gruppen neu anordnen
Route::put('/funki/financials/groups/reorder', function (Request $request) {
    $data = $request->validate([
        'groups' => 'required|array',
        'groups.*.id' => 'required|string',
        'groups.*.position' => 'required|integer'
    ]);

    foreach ($data['groups'] as $groupData) {
        AccountingGroup::where('id', $groupData['id'])
            ->where('admin_id', $request->user()->id)
            ->update(['position' => $groupData['position']]);
    }
    return response()->json(['success' => true]);
});

Route::post('/funki/financials/fixed-item', function (Request $request) {
    $data = $request->validate([
        'finance_group_id' => 'required',
        'name' => 'required|string',
        'amount' => 'required',
        'interval_months' => 'required|integer',
        'first_payment_date' => 'required|date',
        'description' => 'nullable|string',
        'is_business' => 'boolean'
    ]);

    // Sicherstellen, dass die Kostenstellen-Gruppe dem aktuellen Admin gehört
    $group = AccountingGroup::where('admin_id', $request->user()->id)->findOrFail($data['finance_group_id']);

    $amount = (float)str_replace(',', '.', (string)$data['amount']);

    $item = AccountingCostItem::create([
        'id' => Str::uuid(),
        'accounting_group_id' => $group->id,
        'name' => $data['name'],
        'amount' => $amount,
        'interval_months' => (int)$data['interval_months'],
        'first_payment_date' => $data['first_payment_date'],
        'description' => $data['description'],
        'is_business' => filter_var($data['is_business'], FILTER_VALIDATE_BOOLEAN)
    ]);
    return response()->json(['success' => true, 'item' => $item]);
});

Route::put('/funki/financials/fixed-item/{id}', function (Request $request, $id) {
    $item = AccountingCostItem::whereHas('group', fn($q) => $q->where('admin_id', $request->user()->id))->findOrFail($id);

    $data = $request->validate([
        'name' => 'required|string',
        'amount' => 'required',
        'interval_months' => 'required|integer',
        'first_payment_date' => 'required|date',
        'description' => 'nullable|string',
        'is_business' => 'boolean',
        'finance_group_id' => 'nullable|string'
    ]);

    $targetGroupId = $item->accounting_group_id;
    if (!empty($data['finance_group_id'])) {
        $targetGroup = AccountingGroup::where('admin_id', $request->user()->id)->findOrFail($data['finance_group_id']);
        $targetGroupId = $targetGroup->id;
    }

    $item->update([
        'name' => $data['name'],
        'amount' => (float)str_replace(',', '.', $data['amount']),
        'interval_months' => (int)$data['interval_months'],
        'first_payment_date' => $data['first_payment_date'],
        'description' => $data['description'],
        'is_business' => filter_var($data['is_business'], FILTER_VALIDATE_BOOLEAN),
        'accounting_group_id' => $targetGroupId
    ]);

    // Datei-Update Logik
    if ($request->hasFile('file')) {
        $file = is_array($request->file('file')) ? $request->file('file')[0] : $request->file('file');
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        if (!in_array($ext, $allowedExts, true) || !in_array($mime, $allowedMimes, true) || $file->getSize() > 20 * 1024 * 1024) {
            return response()->json(['success' => false, 'message' => 'Ungültiges Dateiformat oder Datei zu groß.'], 422);
        }

        // Alte Datei löschen, falls vorhanden
        if ($item->contract_file_path && Storage::disk('local')->exists($item->contract_file_path)) {
            Storage::disk('local')->delete($item->contract_file_path);
        }

        $path = $file->store('buchhaltung/contracts', 'local');
        $item->update(['contract_file_path' => $path]);
    }

    return response()->json(['success' => true]);
});

Route::delete('/funki/financials/fixed-item/{id}', function (Request $request, $id) {
    $item = AccountingCostItem::whereHas('group', fn($q) => $q->where('admin_id', $request->user()->id))->findOrFail($id);
    if (!empty($item->contract_file_path)) {
        $pathStr = ltrim((string) $item->contract_file_path, '/');
        if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && str_starts_with($pathStr, 'buchhaltung/') && Storage::disk('local')->exists($pathStr)) {
            Storage::disk('local')->delete($pathStr);
        }
    }
    $item->delete();
    return response()->json(['success' => true]);
});

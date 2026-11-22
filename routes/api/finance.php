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
            } catch (\Exception $e) {
                continue;
            }
            if (!$firstPaymentDate->copy()->startOfMonth()->lte($now->copy()->endOfMonth())) {
                continue;
            }
            $interval = max(1, (int) ($item->interval_months ?: 1));
            $elapsedMonths = (($now->year - $firstPaymentDate->year) * 12) + ($now->month - $firstPaymentDate->month);
            if ($elapsedMonths >= 0 && ($elapsedMonths % $interval) === 0) {
                if ($item->amount >= 0) {
                    $fixedIncome += $item->amount;
                } else {
                    $fixedExpenses += $item->amount;
                }
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
        'title' => 'required|string|max:255',
        'amount' => 'required',
        'category' => 'nullable|string|max:100',
        'is_business' => 'boolean',
        'date' => 'nullable|date',
        'location' => 'nullable|string|max:255'
    ]);

    $amount = (float)str_replace(',', '.', (string)$data['amount']);
    if (!is_finite($amount) || abs($amount) > 99999999.99) {
        return response()->json(['error' => 'Ungültiger Betrag.'], 422);
    }
    // WICHTIG: Sonderausgaben / Belegkosten sind im System stets negativ!
    if ($amount > 0) {
        $amount = -$amount;
    }

    $amountCents = (int) round($amount * 100);
    $cleanAmount = round($amountCents / 100, 2);
    $execDateStr = !empty($data['date'])
        ? \Carbon\Carbon::parse((string) $data['date'])->format('Y-m-d')
        : now()->format('Y-m-d');
    $cleanTitle = str_replace([' – ', ' — '], ' - ', (string) $data['title']);
    $location = (string) ($data['location'] ?? 'App QuickEntry');
    $isBusiness = filter_var($data['is_business'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $allowOverride = filter_var($request->input('allow_duplicate_override', false), FILTER_VALIDATE_BOOLEAN);

    $uploadedFiles = [];
    if ($request->hasFile('file')) {
        $rawFiles = is_array($request->file('file')) ? array_slice($request->file('file'), 0, 10) : [$request->file('file')];
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'xml'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'text/xml', 'application/xml'];

        foreach ($rawFiles as $f) {
            if (!$f || !$f->isValid() || $f->getSize() > 20 * 1024 * 1024) {
                continue;
            }
            $ext = strtolower($f->getClientOriginalExtension());
            $mime = strtolower((string) $f->getMimeType());
            if (in_array($ext, $allowedExts, true) && in_array($mime, $allowedMimes, true)) {
                $uploadedFiles[] = $f;
            }
        }
    }

    if (!$allowOverride) {
        $dup = \App\Services\AI\BuchiReceiptPipelineService::checkDuplicate(
            adminId: $request->user()->id,
            title: $cleanTitle,
            merchant: $location,
            date: $execDateStr,
            amountCents: $amountCents,
            invoiceNumber: (string) $request->input('invoice_number', ''),
            file: $uploadedFiles[0] ?? null
        );
        if (!empty($dup['is_duplicate'])) {
            return response()->json([
                'success' => false,
                'message' => 'Dubletten-Schutz: ' . ($dup['reason'] ?? 'Beleg existiert bereits.'),
            ], 200);
        }
    }

    $paths = [];
    foreach ($uploadedFiles as $idx => $validFile) {
        $storedPath = \App\Services\AI\BuchiReceiptPipelineService::storeReceiptFile(
            file: $validFile,
            date: $execDateStr,
            merchant: $location,
            title: $cleanTitle,
            invoiceNumber: (string) $request->input('invoice_number', ''),
            fileIndex: (int) $idx
        );
        if ($storedPath) {
            $paths[] = $storedPath;
        }
    }

    $isSmallBusiness = app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness();
    $defaultTax = $isSmallBusiness ? 0 : (int) round((float) shop_setting('default_tax_rate', 19));

    $issue = AccountingSpecialIssue::create([
        'id' => Str::uuid(),
        'admin_id' => $request->user()->id,
        'title' => $cleanTitle,
        'amount' => $cleanAmount,
        'category' => $data['category'] ?? 'Sonstiges',
        'execution_date' => $execDateStr,
        'is_business' => $isBusiness,
        'tax_rate' => $isBusiness ? (int) $request->input('tax_rate', $defaultTax) : null,
        'invoice_number' => $isBusiness ? ($request->input('invoice_number') ?: null) : null,
        'location' => $location,
        'file_paths' => $paths,
        'note' => \App\Services\AI\BuchiReceiptPipelineService::withLiveMarker('Android QuickEntry'),
    ]);

    if (!empty($paths)) {
        Log::info('Files stored via BuchiReceiptPipelineService', ['paths' => $paths]);
    }

    \App\Services\AI\BuchiReceiptPipelineService::trackCategoryUsage(
        $request->user()->id,
        (string) ($data['category'] ?? 'Sonstiges'),
        $isBusiness
    );

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
        'file' => 'file|mimes:pdf,jpg,jpeg,png,webp,heic,heif,xml|max:20480',
    ]);

    $file = $request->file('file');
    $autoSave = filter_var($request->input('auto_save', false), FILTER_VALIDATE_BOOLEAN);
    $allowOverride = filter_var($request->input('allow_duplicate_override', false), FILTER_VALIDATE_BOOLEAN);

    $categories = AccountingCategory::where('admin_id', $request->user()->id)
        ->orderBy('usage_count', 'desc')
        ->pluck('name')
        ->toArray();

    $aiResult = \App\Services\AI\BuchiReceiptPipelineService::analyzeAndVerify(
        $file,
        $categories,
        $request->user()->id
    );

    if (!$aiResult) {
        return response()->json([
            'success' => false,
            'message' => 'Beleg konnte nicht automatisch erkannt werden. Bitte manuell erfassen.'
        ], 200);
    }

    $dup = $aiResult['duplicate_check'] ?? ['is_duplicate' => false];

    if ($autoSave) {
        if (!empty($dup['is_duplicate']) && !$allowOverride) {
            return response()->json([
                'success' => false,
                'auto_saved' => false,
                'data' => $aiResult,
                'message' => 'Dubletten-Schutz: ' . ($dup['reason'] ?? 'Beleg wurde bereits erfasst.'),
            ], 200);
        }

        $execDate = (string) ($aiResult['date'] ?: now()->format('Y-m-d'));
        $storedPath = \App\Services\AI\BuchiReceiptPipelineService::storeReceiptFile(
            file: $file,
            date: $execDate,
            merchant: (string) ($aiResult['merchant'] ?: $aiResult['location'] ?: 'App-Scan'),
            title: (string) $aiResult['title'],
            invoiceNumber: !empty($aiResult['is_business']) ? (string) ($aiResult['invoice_number'] ?? '') : ''
        );

        $issue = AccountingSpecialIssue::create([
            'id' => Str::uuid(),
            'admin_id' => $request->user()->id,
            'title' => $aiResult['title'],
            'amount' => $aiResult['amount'],
            'category' => $aiResult['category'] ?: 'Sonstiges',
            'execution_date' => $execDate,
            'is_business' => $aiResult['is_business'],
            'location' => $aiResult['location'] ?: ($aiResult['merchant'] ?: 'App Beleg-Scan'),
            'tax_rate' => $aiResult['is_business'] ? $aiResult['tax_rate'] : null,
            'invoice_number' => $aiResult['is_business'] ? ($aiResult['invoice_number'] ?: null) : null,
            'file_paths' => $storedPath ? [$storedPath] : [],
            'note' => \App\Services\AI\BuchiReceiptPipelineService::withLiveMarker('Android Beleg-Scan'),
        ]);

        \App\Services\AI\BuchiReceiptPipelineService::trackCategoryUsage(
            $request->user()->id,
            (string) ($aiResult['category'] ?: 'Sonstiges'),
            (bool) $aiResult['is_business']
        );

        return response()->json([
            'success' => true,
            'auto_saved' => true,
            'data' => $aiResult,
            'entry' => $issue,
            'message' => "Beleg '{$aiResult['title']}' ({$aiResult['amount_formatted']} €) wurde automatisch erfasst und seed-sicher gebucht."
        ]);
    }

    $msg = !empty($dup['is_duplicate'])
        ? ("Hinweis (Möglicher Doppelbeleg): " . ($dup['reason'] ?? 'Bereits vorhanden.'))
        : "Beleg '{$aiResult['title']}' erkannt.";

    return response()->json([
        'success' => true,
        'auto_saved' => false,
        'data' => $aiResult,
        'message' => $msg,
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
        'title' => 'required|string|max:255',
        'amount' => 'required',
        'category' => 'required|string|max:120',
        'execution_date' => 'required|date',
        'is_business' => 'boolean',
        'location' => 'nullable|string|max:255'
    ]);

    $parsedAmount = (float) str_replace(',', '.', (string) $data['amount']);
    if (!is_finite($parsedAmount) || abs($parsedAmount) > 99999999.99) {
        return response()->json(['success' => false, 'message' => 'Ungültiger Betrag.'], 422);
    }

    if ($request->has('is_expense')) {
        $isExpense = filter_var($request->input('is_expense'), FILTER_VALIDATE_BOOLEAN);
        $parsedAmount = $isExpense ? -abs($parsedAmount) : abs($parsedAmount);
    } elseif ($issue->amount < 0 && $parsedAmount > 0 && !str_starts_with(trim((string) $data['amount']), '+')) {
        // Behalte Ausgaben-Vorzeichen bei, falls es vorher eine Ausgabe war und kein explizites '+' gesendet wurde
        $parsedAmount = -abs($parsedAmount);
    }

    // Basis-Daten aktualisieren
    $issue->update([
        'title' => $data['title'],
        'amount' => round($parsedAmount, 2),
        'category' => $data['category'],
        'execution_date' => $data['execution_date'],
        'is_business' => filter_var($data['is_business'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'location' => $data['location'] ?? null,
    ]);

    // DATEI-LOGIK FIX:
    // Wir prüfen auf 'file', da Laravel bei Multipart-Arrays das Key-Handling übernimmt
    if ($request->hasFile('file')) {
        $existingPaths = is_array($issue->file_paths) ? $issue->file_paths : [];
        $files = $request->file('file');

        // Sicherstellen, dass wir immer ein Array haben (maximal 10 Dateien pro Request)
        if (!is_array($files)) {
            $files = [$files];
        }
        $files = array_slice($files, 0, 10);

        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        $execDateForPath = $issue->execution_date instanceof \Carbon\Carbon
            ? $issue->execution_date->format('Y-m-d')
            : (string) $issue->execution_date;

        foreach ($files as $idx => $file) {
            if (!$file || !$file->isValid() || $file->getSize() > 20 * 1024 * 1024) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $mime = strtolower((string) $file->getMimeType());
            if (!in_array($ext, $allowedExts, true) || !in_array($mime, $allowedMimes, true)) {
                continue;
            }
            $storedPath = \App\Services\AI\BuchiReceiptPipelineService::storeReceiptFile(
                file: $file,
                date: $execDateForPath,
                merchant: (string) ($issue->location ?: $issue->title),
                title: (string) $issue->title,
                invoiceNumber: (string) ($issue->invoice_number ?? ''),
                fileIndex: count($existingPaths) + (int) $idx
            );
            if ($storedPath) {
                $existingPaths[] = $storedPath;
            }
        }

        $issue->update(['file_paths' => array_slice($existingPaths, 0, 25)]);
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
        'name' => 'required|string|max:255',
        'amount' => 'required',
        'interval_months' => 'required|integer|min:1|max:120',
        'first_payment_date' => 'required|date',
        'description' => 'nullable|string|max:2000',
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
        'name' => 'required|string|max:255',
        'amount' => 'required',
        'interval_months' => 'required|integer|min:1|max:120',
        'first_payment_date' => 'required|date',
        'description' => 'nullable|string|max:2000',
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

        // Alte Datei sicher löschen, falls vorhanden (mit Pfad-Containment-Prüfung)
        if (!empty($item->contract_file_path)) {
            $oldPath = ltrim((string) $item->contract_file_path, '/');
            if ($oldPath !== '' && !str_contains($oldPath, '..') && !str_contains($oldPath, "\0") && str_starts_with($oldPath, 'buchhaltung/') && Storage::disk('local')->exists($oldPath)) {
                Storage::disk('local')->delete($oldPath);
            }
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

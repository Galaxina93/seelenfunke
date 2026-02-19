<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Financial\FinanceCategory;
use App\Models\Order\Order;
use Carbon\Carbon;
use Illuminate\Support\Str;

Route::get('/funki/financials/kpis', function (Request $request) {
    $adminId = $request->user()->id;
    $now = Carbon::now();
    $month = $now->month;
    $year = $now->year;

    $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();
    $fixedIncome = 0;
    $fixedExpenses = 0;
    foreach ($groups as $group) {
        foreach ($group->items as $item) {
            $startMonth = $item->first_payment_date->month;
            $interval = $item->interval_months ?: 1;
            $diff = ($month - $startMonth);
            if ($diff < 0) $diff += 12;
            if (($diff % $interval) === 0) {
                if ($item->amount >= 0) $fixedIncome += $item->amount;
                else $fixedExpenses += $item->amount;
            }
        }
    }

    $specialIssues = FinanceSpecialIssue::where('admin_id', $adminId)
        ->whereYear('execution_date', $year)
        ->whereMonth('execution_date', $month)
        ->get();
    $specialExpenses = 0;
    foreach($specialIssues as $issue) {
        if($issue->amount < 0) $specialExpenses += $issue->amount;
    }

    $shopRevenue = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
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
    return FinanceCategory::where('admin_id', $request->user()->id)->orderBy('usage_count', 'desc')->pluck('name');
});

Route::delete('/funki/financials/categories/{name}', function (Request $request, $name) {
    $decodedName = urldecode($name);

    $deleted = FinanceCategory::where('admin_id', $request->user()->id)
        ->where('name', $decodedName)
        ->delete();

    if ($deleted) {
        FinanceSpecialIssue::where('admin_id', $request->user()->id)
            ->where('category', $decodedName)
            ->update(['category' => 'Sonstiges']);
    }

    return response()->json(['success' => true]);
});

Route::post('/funki/financials/quick-entry', function (Request $request) {
    $data = $request->validate([
        'title' => 'required|string',
        'amount' => 'required',
        'category' => 'nullable|string',
        'is_business' => 'boolean',
        'date' => 'nullable|date',
        'location' => 'nullable|string'
    ]);

    $rawAmount = str_replace(',', '.', (string)$data['amount']);
    $amount = (float)$rawAmount;

    // NEU: Verarbeitung der hochgeladenen Dateien aus der App (Foto/Galerie)
    if ($request->hasFile('specialFiles')) {
        $paths = [];
        foreach ($request->file('specialFiles') as $file) {
            $path = $file->store('financials/receipts', 'public');
            $paths[] = $path;
        }
    }

    $issue = FinanceSpecialIssue::create([
        'id' => Str::uuid(),
        'admin_id' => $request->user()->id,
        'title' => $data['title'],
        'amount' => $amount,
        'category' => $data['category'] ?? 'Sonstiges',
        'execution_date' => $data['date'] ?? now(),
        'is_business' => $data['is_business'] ?? false,
        'location' => $data['location'] ?? 'App QuickEntry',
        'file_paths' => $paths
    ]);

    if (!empty($data['category'])) {
        $cat = FinanceCategory::firstOrCreate(
            ['admin_id' => $request->user()->id, 'name' => $data['category']],
            ['usage_count' => 0]
        );
        $cat->increment('usage_count');
    }
    return response()->json(['success' => true, 'entry' => $issue]);
});

Route::get('/funki/financials/variable', function (Request $request) {
    return FinanceSpecialIssue::where('admin_id', $request->user()->id)
        ->orderBy('execution_date', 'desc')
        ->take(50)
        ->get();
});

Route::put('/funki/financials/variable/{id}', function (Request $request, $id) {
    $issue = FinanceSpecialIssue::findOrFail($id);
    $data = $request->validate([
        'title' => 'required',
        'amount' => 'required'
    ]);
    $rawAmount = str_replace(',', '.', (string)$data['amount']);
    $amount = (float)$rawAmount;

    $issue->update([
        'title' => $data['title'],
        'amount' => $amount
    ]);
    return response()->json(['success' => true]);
});

Route::delete('/funki/financials/variable/{id}', function ($id) {
    FinanceSpecialIssue::destroy($id);
    return response()->json(['success' => true]);
});

Route::get('/funki/financials/fixed', function (Request $request) {
    return FinanceGroup::with('items')
        ->where('admin_id', $request->user()->id)
        ->orderBy('position')
        ->get();
});

Route::post('/funki/financials/groups', function (Request $request) {
    $data = $request->validate([
        'name' => 'required|string',
        'type' => 'required|in:expense,income'
    ]);

    $position = FinanceGroup::where('admin_id', $request->user()->id)->max('position') + 1;

    $group = FinanceGroup::create([
        'id' => Str::uuid(),
        'admin_id' => $request->user()->id,
        'name' => $data['name'],
        'type' => $data['type'],
        'position' => $position
    ]);
    return response()->json(['success' => true, 'group' => $group]);
});

// NEU: Gruppe löschen (Nur wenn leer)
Route::delete('/funki/financials/groups/{id}', function (Request $request, $id) {
    $group = FinanceGroup::with('items')->where('admin_id', $request->user()->id)->findOrFail($id);
    if ($group->items->count() > 0) {
        return response()->json(['error' => 'Gruppe enthält noch Kostenstellen.'], 400);
    }
    $group->delete();
    return response()->json(['success' => true]);
});

// NEU: Gruppen neu anordnen
Route::put('/funki/financials/groups/reorder', function (Request $request) {
    $data = $request->validate([
        'groups' => 'required|array',
        'groups.*.id' => 'required|string',
        'groups.*.position' => 'required|integer'
    ]);

    foreach ($data['groups'] as $groupData) {
        FinanceGroup::where('id', $groupData['id'])
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
        'is_business' => 'required'
    ]);

    $amount = (float)str_replace(',', '.', (string)$data['amount']);

    $item = FinanceCostItem::create([
        'id' => Str::uuid(),
        'finance_group_id' => $data['finance_group_id'],
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
    $item = FinanceCostItem::findOrFail($id);
    // Erweitert um finance_group_id für Drag & Drop
    $data = $request->validate([
        'name' => 'required',
        'amount' => 'required',
        'interval_months' => 'required|integer',
        'first_payment_date' => 'required|date',
        'description' => 'nullable|string',
        'is_business' => 'required',
        'finance_group_id' => 'nullable|string'
    ]);

    $amount = (float)str_replace(',', '.', (string)$data['amount']);

    $updateData = [
        'name' => $data['name'],
        'amount' => $amount,
        'interval_months' => (int)$data['interval_months'],
        'first_payment_date' => $data['first_payment_date'],
        'description' => $data['description'],
        'is_business' => filter_var($data['is_business'], FILTER_VALIDATE_BOOLEAN)
    ];

    if (!empty($data['finance_group_id'])) {
        $updateData['finance_group_id'] = $data['finance_group_id'];
    }

    $item->update($updateData);
    return response()->json(['success' => true]);
});

Route::delete('/funki/financials/fixed-item/{id}', function ($id) {
    FinanceCostItem::destroy($id);
    return response()->json(['success' => true]);
});

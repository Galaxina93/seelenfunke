<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\UserDevice;
use App\Services\FunkiBotService;
use App\Models\DayRoutine;
use App\Models\Todo;
use App\Models\TodoList;
use App\Models\CalendarEvent;
use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Financial\FinanceCategory;
use App\Models\Order\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Wir prüfen nacheinander alle Guards
    $guards = ['admin', 'employee', 'customer'];

    foreach ($guards as $guard) {
        if (Auth::guard($guard)->attempt($credentials)) {
            $user = Auth::guard($guard)->user();

            // Optional: Alte Tokens aufräumen
            $user->tokens()->delete();

            // Token erstellen
            $token = $user->createToken('FunkiApp-' . $guard)->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token' => $token,
                'user_type' => $guard,
                'user' => $user
            ]);
        }
    }

    return response()->json(['message' => 'Zugangsdaten ungültig.'], 401);
});

Route::middleware('auth:sanctum')->group(function () {



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

    // --- FINANZEN ---
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

    // NEU: Route zum LÖSCHEN einer Kategorie
    Route::delete('/funki/financials/categories/{name}', function (Request $request, $name) {
        $decodedName = urldecode($name);

        // Lösche die Kategorie
        $deleted = FinanceCategory::where('admin_id', $request->user()->id)
            ->where('name', $decodedName)
            ->delete();

        // Optional: Referenzen in Special Issues entfernen oder auf 'Sonstiges' setzen
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
            'amount' => 'required', // String oder Number akzeptieren für Parsing
            'category' => 'nullable|string',
            'is_business' => 'boolean',
            'date' => 'nullable|date',
            'location' => 'nullable|string'
        ]);

        // Parse Amount Logic: Wenn "-" davor, dann negativ, sonst positiv
        $rawAmount = str_replace(',', '.', (string)$data['amount']);
        $amount = (float)$rawAmount;

        $issue = FinanceSpecialIssue::create([
            'id' => Str::uuid(),
            'admin_id' => $request->user()->id,
            'title' => $data['title'],
            'amount' => $amount,
            'category' => $data['category'] ?? 'Sonstiges',
            'execution_date' => $data['date'] ?? now(),
            'is_business' => $data['is_business'] ?? false,
            'location' => $data['location'] ?? 'App QuickEntry'
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

    Route::put('/funki/financials/fixed-item/{id}', function (Request $request, $id) {
        $item = FinanceCostItem::findOrFail($id);
        $data = $request->validate([
            'name' => 'required',
            'amount' => 'required'
        ]);
        $rawAmount = str_replace(',', '.', (string)$data['amount']);
        $amount = (float)$rawAmount;

        $item->update([
            'name' => $data['name'],
            'amount' => $amount
        ]);
        return response()->json(['success' => true]);
    });

    Route::delete('/funki/financials/fixed-item/{id}', function ($id) {
        FinanceCostItem::destroy($id);
        return response()->json(['success' => true]);
    });

    // --- ROUTINE ---
    Route::get('/funki/routine', function () {
        return DayRoutine::with('steps')
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
    });

    Route::put('/funki/routine/{id}', function (Request $request, $id) {
        $routine = DayRoutine::findOrFail($id);
        $data = $request->validate([
            'title' => 'required',
            'message' => 'nullable',
            'duration_minutes' => 'required|integer',
            'start_time' => 'required'
        ]);
        $routine->update($data);
        return response()->json(['success' => true]);
    });

    // --- TODOS ---
    Route::get('/funki/todos', function () {
        // FIX für Flutter Error: Ensure title is never null
        return Todo::where('is_completed', false)
            ->orderBy('position')
            ->orderBy('created_at')
            ->get()
            ->map(function($todo) {
                $todo->title = $todo->title ?? 'Ohne Titel'; // Null Safety
                $parentTitle = null;
                if($todo->parent_id) {
                    $parent = Todo::find($todo->parent_id);
                    $parentTitle = $parent ? $parent->title : null;
                }
                $todo->parent_title = $parentTitle;
                return $todo;
            });
    });

    Route::post('/funki/todos/{id}/toggle', function ($id) {
        $todo = Todo::find($id);
        if ($todo) {
            $todo->update(['is_completed' => !$todo->is_completed]);
            return response()->json(['success' => true]);
        }
        return response()->json(['error' => 'Nicht gefunden'], 404);
    });

    Route::put('/funki/todos/{id}', function (Request $request, $id) {
        $todo = Todo::findOrFail($id);
        $data = $request->validate(['title' => 'required']);
        $todo->update(['title' => $data['title']]);
        return response()->json(['success' => true]);
    });

    Route::post('/funki/todos/{id}/subtask', function (Request $request, $id) {
        $data = $request->validate(['title' => 'required']);
        $todo = Todo::findOrFail($id);
        $todoListId = $todo->todo_list_id;

        Todo::create([
            'id' => Str::uuid(),
            'todo_list_id' => $todoListId,
            'parent_id' => $todo->id,
            'title' => $data['title']
        ]);
        return response()->json(['success' => true]);
    });

    Route::delete('/funki/todos/{id}', function($id) {
        Todo::destroy($id);
        return response()->json(['success' => true]);
    });

    // --- KALENDER (Fix: Null-Checks und Daten) ---
    Route::get('/funki/calendar', function () {
        $start = Carbon::today()->subMonths(1); // Auch etwas Rückblick laden
        $end = Carbon::today()->addYear();

        $normalEvents = CalendarEvent::whereBetween('start_date', [$start, $end])
            ->whereNull('recurrence')
            ->get();

        $expandedEvents = collect();

        foreach($normalEvents as $ev) {
            if(!$ev->start_date) continue;
            $expandedEvents->push([
                'id' => (string)$ev->id,
                'title' => $ev->title ?? 'Termin',
                'start' => $ev->start_date->toIso8601String(),
                'is_all_day' => (bool)$ev->is_all_day,
                'category' => $ev->category ?? 'general'
            ]);
        }

        $recurringTemplates = CalendarEvent::whereNotNull('recurrence')->get();
        $calcEnd = Carbon::today()->addMonths(3); // Performance Optimierung: Nicht ganzes Jahr

        foreach($recurringTemplates as $tmpl) {
            if(!$tmpl->start_date) continue;
            $simDate = $tmpl->start_date->copy();

            // Auf Startdatum in der Zukunft/Heute spulen
            if($simDate < Carbon::today()) {
                while($simDate < Carbon::today()) {
                    switch ($tmpl->recurrence) {
                        case 'daily': $simDate->addDay(); break;
                        case 'weekly': $simDate->addWeek(); break;
                        case 'monthly': $simDate->addMonth(); break;
                        case 'yearly': $simDate->addYear(); break;
                    }
                }
            }

            while($simDate <= $calcEnd) {
                if ($tmpl->recurrence_end_date && $simDate > $tmpl->recurrence_end_date) break;

                $expandedEvents->push([
                    'id' => $tmpl->id . '_' . $simDate->timestamp,
                    'title' => $tmpl->title ?? 'Serie',
                    'start' => $simDate->toIso8601String(),
                    'is_all_day' => (bool)$tmpl->is_all_day,
                    'category' => $tmpl->category ?? 'general'
                ]);

                switch ($tmpl->recurrence) {
                    case 'daily': $simDate->addDay(); break;
                    case 'weekly': $simDate->addWeek(); break;
                    case 'monthly': $simDate->addMonth(); break;
                    case 'yearly': $simDate->addYear(); break;
                }
            }
        }
        return $expandedEvents->sortBy('start')->values();
    });
});

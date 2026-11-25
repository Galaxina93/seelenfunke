<?php

use App\Http\Middleware\EnsureInternalEmployeeApiAccess;
use App\Mail\SupportTicketUpdateMailToCustomer;
use App\Models\Customer\CustomerGamification;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Employee\EmployeePayslip;
use App\Models\Employee\EmployeeTimeEntry;
use App\Models\Management\ManagementTask;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\Employee\EmployeeHrService;
use App\Services\Management\ManagementTaskTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

Route::prefix('employee')
    ->middleware([EnsureInternalEmployeeApiAccess::class])
    ->group(function () {

    $resolveActiveContract = static function (Employee $employee): ?EmployeeContract {
        if (!Schema::hasTable('employee_contracts')) {
            return null;
        }

        return EmployeeContract::with('payslips')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->latest('created_at')
            ->first();
    };

    $streamSafeStoragePdf = static function (Request $request, string $relativePath, string $allowedPrefix, string $downloadFilename) {
        if ($request->hasSession()) {
            $request->session()->save();
        }
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $cleanRel = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (
            $cleanRel === ''
            || str_contains($cleanRel, '..')
            || str_contains($cleanRel, "\0")
            || !str_starts_with($cleanRel, $allowedPrefix)
            || !Storage::disk('local')->exists($cleanRel)
        ) {
            abort(404, 'Dokument nicht gefunden.');
        }

        $fullPath = Storage::disk('local')->path($cleanRel);
        if (is_link($fullPath) || !is_file($fullPath)) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9._\-]/', '_', $downloadFilename) ?: 'Dokument.pdf';

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    };

    $buildCockpitPayload = static function (Employee $employee, EmployeeHrService $hrService, int $year, int $month) use ($resolveActiveContract): array {
        $hrService->ensureTablesExist();
        $employee->loadMissing('profile');
        $contract = $resolveActiveContract($employee);

        $ticketService = app(ManagementTaskTicketService::class);
        $empActor = $ticketService->buildEmployeeActor($employee);
        $operatorProfile = Schema::hasTable('management_task_operator_profiles')
            ? $ticketService->getOrCreateOperatorProfile(
                'employee',
                (string) $employee->id,
                $empActor['name'],
                $empActor['avatar'],
                $empActor['role_label']
            )->toModalPayload(true)
            : null;

        if (!$contract) {
            return [
                'has_contract' => false,
                'employee' => [
                    'id' => (string) $employee->id,
                    'first_name' => (string) ($employee->first_name ?? ''),
                    'last_name' => (string) ($employee->last_name ?? ''),
                    'full_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                    'email' => (string) ($employee->email ?? ''),
                ],
                'operator_profile' => $operatorProfile,
                'permissions' => $employee->buildFunkenwerkPermissionsPayload(),
            ];
        }

        $runningEntry = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->where('status', 'running')
            ->first();

        $stats = $hrService->getMonthlyStats($contract, $year, $month);

        $payslips = $contract->payslips()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get()
            ->map(fn (EmployeePayslip $slip) => [
                'id' => (string) $slip->id,
                'payslip_number' => (string) ($slip->payslip_number ?? ''),
                'period_year' => (int) $slip->period_year,
                'period_month' => (int) $slip->period_month,
                'period_label' => (string) $slip->period_label,
                'net_payout_cents' => (int) $slip->net_payout_cents,
                'net_payout_formatted' => number_format(((int) $slip->net_payout_cents) / 100, 2, ',', '.') . ' €',
                'status' => (string) ($slip->status ?? 'draft'),
            ])
            ->values()
            ->all();

        $entriesPayload = collect($stats['entries'] ?? [])->map(fn (EmployeeTimeEntry $e) => [
            'id' => (string) $e->id,
            'work_date' => $e->work_date?->format('Y-m-d') ?? '',
            'work_date_formatted' => $e->work_date?->format('d.m.Y') ?? '',
            'entry_type' => (string) ($e->entry_type ?? 'work'),
            'started_at_time' => $e->started_at?->format('H:i'),
            'ended_at_time' => $e->ended_at?->format('H:i'),
            'break_minutes' => (int) ($e->break_minutes ?? 0),
            'net_minutes' => (int) ($e->net_minutes ?? 0),
            'formatted_hours' => (string) $e->formatted_hours,
            'activity_note' => (string) ($e->activity_note ?? ''),
            'status' => (string) ($e->status ?? 'pending_approval'),
            'can_delete' => ($e->status ?? '') !== 'approved',
        ])->values()->all();

        $canAccessTasks = (bool) ($contract->can_access_tasks ?? true);
        $assignedOpenCount = 0;
        if ($canAccessTasks && Schema::hasColumn('management_tasks', 'assignee_id')) {
            $assignedOpenCount = ManagementTask::whereNull('parent_id')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->where('assignee_type', 'employee')
                ->where('assignee_id', (string) $employee->id)
                ->count();
        }

        $openOrdersCount = 0;
        $expressOrdersCount = 0;
        if ($contract->can_access_production) {
            $openOrdersCount = OrderOrder::whereIn('status', ['pending', 'processing'])->count();
            $expressOrdersCount = OrderOrder::whereIn('status', ['pending', 'processing'])
                ->where('is_express', true)
                ->count();
        }

        $supportTickets = [];
        if ($contract->can_access_support && Schema::hasTable('support_tickets')) {
            $supportTickets = SupportTicket::with('customer')
                ->whereIn('status', ['open', 'answered'])
                ->orderByDesc('updated_at')
                ->limit(30)
                ->get()
                ->map(fn (SupportTicket $t) => [
                    'id' => (string) $t->id,
                    'ticket_number' => (string) ($t->ticket_number ?? ''),
                    'subject' => (string) ($t->subject ?? ''),
                    'status' => (string) ($t->status ?? 'open'),
                    'customer_name' => trim(($t->customer->first_name ?? '') . ' ' . ($t->customer->last_name ?? '')) ?: 'Kunde',
                    'updated_at_formatted' => $t->updated_at?->format('d.m.Y H:i') ?? '',
                ])
                ->values()
                ->all();
        }

        $physicalProducts = [];
        $recentLosses = [];
        if ($contract->can_access_inventory && Schema::hasTable('products')) {
            $physicalProducts = Product::where('status', 'active')
                ->where('type', 'physical')
                ->orderBy('name')
                ->get(['id', 'name', 'quantity'])
                ->map(fn (Product $p) => [
                    'id' => (string) $p->id,
                    'name' => (string) $p->name,
                    'quantity' => (int) ($p->quantity ?? 0),
                ])
                ->values()
                ->all();

            if (Schema::hasTable('product_losses')) {
                $recentLosses = ProductLoss::with('product')
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (ProductLoss $l) => [
                        'id' => (string) $l->id,
                        'product_name' => (string) ($l->product?->name ?? 'Produkt'),
                        'quantity' => (int) ($l->quantity ?? 1),
                        'reason' => (string) ($l->reason ?? ''),
                        'created_at_formatted' => $l->created_at?->format('d.m.Y H:i') ?? '',
                    ])
                    ->values()
                    ->all();
            }
        }

        return [
            'has_contract' => true,
            'selected_year' => $year,
            'selected_month' => $month,
            'employee' => [
                'id' => (string) $employee->id,
                'first_name' => (string) ($employee->first_name ?? ''),
                'last_name' => (string) ($employee->last_name ?? ''),
                'full_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                'email' => (string) ($employee->email ?? ''),
            ],
            'contract' => [
                'id' => (string) $contract->id,
                'contract_number' => (string) ($contract->contract_number ?? ''),
                'job_title' => (string) ($contract->job_title ?? ''),
                'department' => (string) ($contract->department ?? ''),
                'weekly_hours' => (float) ($contract->weekly_hours ?? 0),
                'vacation_days_per_year' => (int) ($contract->vacation_days_per_year ?? 0),
                'vacation_days_taken' => (int) ($contract->vacation_days_taken ?? 0),
                'remaining_vacation_days' => (int) $contract->remaining_vacation_days,
                'can_access_tasks' => $canAccessTasks,
                'can_access_production' => (bool) $contract->can_access_production,
                'can_access_support' => (bool) $contract->can_access_support,
                'can_access_inventory' => (bool) $contract->can_access_inventory,
            ],
            'operator_profile' => [
                'initials' => (string) ($operatorProfile['initials'] ?? 'SF'),
                'avatar_url' => $operatorProfile['avatar_url'] ?? null,
                'rank_class' => (string) ($operatorProfile['rank_class'] ?? 'KLASSE C'),
                'title_label' => (string) ($operatorProfile['title_label'] ?? 'Team'),
                'ep_total' => (int) ($operatorProfile['ep_total'] ?? 0),
                'efficiency_score' => (int) ($operatorProfile['efficiency_score'] ?? 100),
            ],
            'running_entry' => $runningEntry ? [
                'id' => (string) $runningEntry->id,
                'started_at_iso' => $runningEntry->started_at?->toIso8601String(),
                'started_at_time' => $runningEntry->started_at?->format('H:i') ?? '',
                'activity_note' => (string) ($runningEntry->activity_note ?? ''),
            ] : null,
            'kpis' => [
                'worked_hours_formatted' => (string) ($stats['worked_hours_formatted'] ?? '0:00 Std.'),
                'worked_minutes' => (int) ($stats['worked_minutes'] ?? 0),
                'remaining_vacation_days' => (int) $contract->remaining_vacation_days,
                'vacation_days_per_year' => (int) ($contract->vacation_days_per_year ?? 0),
                'assigned_open_tasks_count' => $assignedOpenCount,
                'efficiency_score' => (int) ($operatorProfile['efficiency_score'] ?? 100),
                'open_orders_count' => $openOrdersCount,
                'express_orders_count' => $expressOrdersCount,
            ],
            'time_entries' => $entriesPayload,
            'payslips' => $payslips,
            'support_tickets' => $supportTickets,
            'physical_products' => $physicalProducts,
            'recent_losses' => $recentLosses,
            'permissions' => $employee->buildFunkenwerkPermissionsPayload(),
        ];
    };

    Route::get('/cockpit', function (Request $request, EmployeeHrService $hrService) use ($buildCockpitPayload) {
        /** @var Employee $employee */
        $employee = $request->user();
        $year = max(2020, min(2100, (int) $request->query('year', now()->year)));
        $month = max(1, min(12, (int) $request->query('month', now()->month)));

        return response()->json([
            'success' => true,
            'data' => $buildCockpitPayload($employee, $hrService, $year, $month),
        ]);
    });

    Route::post('/clock-in', function (Request $request, EmployeeHrService $hrService) use ($resolveActiveContract, $buildCockpitPayload) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            return response()->json(['success' => false, 'message' => 'Kein aktiver Arbeitsvertrag hinterlegt.'], 422);
        }

        $note = trim((string) $request->input('activity_note', 'Manufaktur & Versand')) ?: 'Manufaktur & Versand';

        try {
            $hrService->clockIn($contract, $note);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Eingestempelt.',
            'data' => $buildCockpitPayload($employee, $hrService, (int) now()->year, (int) now()->month),
        ]);
    });

    Route::post('/clock-out', function (Request $request, EmployeeHrService $hrService) use ($resolveActiveContract, $buildCockpitPayload) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            return response()->json(['success' => false, 'message' => 'Kein aktiver Arbeitsvertrag hinterlegt.'], 422);
        }

        $running = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->where('status', 'running')
            ->first();

        if (!$running) {
            return response()->json(['success' => false, 'message' => 'Keine laufende Schicht aktiv.'], 422);
        }

        $note = trim((string) $request->input('activity_note', $running->activity_note ?: 'Manufaktur & Versand')) ?: 'Manufaktur & Versand';
        $entry = $hrService->clockOut($running, null, $note);

        return response()->json([
            'success' => true,
            'message' => "Ausgestempelt ({$entry->formatted_hours}).",
            'data' => $buildCockpitPayload($employee, $hrService, (int) now()->year, (int) now()->month),
        ]);
    });

    Route::post('/time-entries', function (Request $request, EmployeeHrService $hrService) use ($resolveActiveContract, $buildCockpitPayload) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            return response()->json(['success' => false, 'message' => 'Kein aktiver Arbeitsvertrag hinterlegt.'], 422);
        }

        $validated = $request->validate([
            'work_date' => 'required|date',
            'start_time' => 'required|string|max:10',
            'end_time' => 'required|string|max:10',
            'break_minutes' => 'nullable|integer|min:0|max:240',
            'activity_note' => 'nullable|string|max:191',
        ]);

        try {
            $entry = $hrService->recordTimeEntry($contract, [
                'work_date' => $validated['work_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'break_minutes' => $validated['break_minutes'] ?? null,
                'activity_note' => trim((string) ($validated['activity_note'] ?? 'Manufaktur & Versand')) ?: 'Manufaktur & Versand',
                'auto_enforce_arbzg' => true,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $workObj = \Carbon\Carbon::parse($validated['work_date']);

        return response()->json([
            'success' => true,
            'message' => "Arbeitszeit gespeichert ({$entry->formatted_hours}).",
            'data' => $buildCockpitPayload($employee, $hrService, (int) $workObj->year, (int) $workObj->month),
        ]);
    });

    Route::post('/leave-entries', function (Request $request, EmployeeHrService $hrService) use ($resolveActiveContract, $buildCockpitPayload) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            return response()->json(['success' => false, 'message' => 'Kein aktiver Arbeitsvertrag hinterlegt.'], 422);
        }

        $validated = $request->validate([
            'entry_type' => 'required|in:vacation,sick',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'activity_note' => 'nullable|string|max:191',
        ]);

        try {
            $created = $hrService->recordLeaveEntries($contract, [
                'entry_type' => $validated['entry_type'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? $validated['start_date'],
                'activity_note' => $validated['activity_note'] ?? '',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $startObj = \Carbon\Carbon::parse($validated['start_date']);
        $count = count($created);

        return response()->json([
            'success' => true,
            'message' => $count > 0 ? "{$count} Tag(e) eingetragen." : 'Für diesen Zeitraum liegt bereits ein Eintrag vor.',
            'data' => $buildCockpitPayload($employee, $hrService, (int) $startObj->year, (int) $startObj->month),
        ]);
    });

    Route::delete('/time-entries/{id}', function (Request $request, string $id, EmployeeHrService $hrService) use ($resolveActiveContract, $buildCockpitPayload) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            abort(403);
        }

        $entry = EmployeeTimeEntry::where('id', $id)
            ->where('contract_id', $contract->id)
            ->firstOrFail();

        if ($entry->status === 'approved') {
            return response()->json(['success' => false, 'message' => 'Genehmigte Einträge können nicht gelöscht werden.'], 422);
        }

        if ($entry->work_date && $hrService->isPeriodLockedByPaidPayslip((string) $contract->id, (int) $entry->work_date->year, (int) $entry->work_date->month)) {
            return response()->json(['success' => false, 'message' => 'Dieser Abrechnungsmonat ist bereits abgeschlossen (GoBD-Sperre).'], 422);
        }

        $y = $entry->work_date ? (int) $entry->work_date->year : (int) now()->year;
        $m = $entry->work_date ? (int) $entry->work_date->month : (int) now()->month;
        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Eintrag gelöscht.',
            'data' => $buildCockpitPayload($employee, $hrService, $y, $m),
        ]);
    });

    // --- EIGENE PERSONAL-DOKUMENTE (PDF) ---
    Route::get('/documents/contract', function (Request $request, EmployeeHrService $hrService) use ($resolveActiveContract, $streamSafeStoragePdf) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            abort(404, 'Kein aktiver Arbeitsvertrag gefunden.');
        }

        if (empty($contract->contract_pdf_path) || !Storage::disk('local')->exists((string) $contract->contract_pdf_path)) {
            $hrService->generateAndStoreContractPdf($contract);
            $contract->refresh();
        }

        $cleanName = 'Arbeitsvertrag_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . '.pdf';

        return $streamSafeStoragePdf($request, (string) $contract->contract_pdf_path, 'buchhaltung/contracts/', $cleanName);
    })->middleware('throttle:30,1');

    Route::get('/documents/timesheet/{year}/{month}', function (Request $request, int $year, int $month, EmployeeHrService $hrService) use ($resolveActiveContract, $streamSafeStoragePdf) {
        if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
            abort(404);
        }
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            abort(404, 'Kein aktiver Arbeitsvertrag gefunden.');
        }

        $path = $hrService->generateAndStoreTimesheetPdf($contract, $year, $month);
        $cleanName = 'Stundenzettel_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . "_{$year}_{$month}.pdf";

        return $streamSafeStoragePdf($request, (string) $path, 'buchhaltung/receipts/', $cleanName);
    })->middleware('throttle:30,1');

    Route::get('/documents/payslip/{payslipId}', function (Request $request, string $payslipId, EmployeeHrService $hrService) use ($resolveActiveContract, $streamSafeStoragePdf) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract) {
            abort(403);
        }

        $payslip = EmployeePayslip::where('id', $payslipId)
            ->where('contract_id', $contract->id)
            ->firstOrFail();

        $path = (string) $payslip->effective_pdf_path;
        if ($path === '' || !Storage::disk('local')->exists($path)) {
            $hrService->generateAndStorePayslipPdf($payslip);
            $payslip->refresh();
            $path = (string) $payslip->effective_pdf_path;
        }

        $cleanName = 'Lohnabrechnung_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $payslip->payslip_number) . '.pdf';

        return $streamSafeStoragePdf($request, $path, 'buchhaltung/receipts/', $cleanName);
    })->middleware('throttle:30,1');

    // --- LAGER / BRUCH MELDEN (nur wenn can_access_inventory) ---
    Route::post('/inventory/loss', function (Request $request, EmployeeHrService $hrService) use ($resolveActiveContract, $buildCockpitPayload) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract || !$contract->can_access_inventory) {
            abort(403, 'Keine Berechtigung für Bruchmeldungen.');
        }

        $validated = $request->validate([
            'product_id' => 'required|string',
            'quantity' => 'required|integer|min:1|max:10000',
            'reason' => 'required|string|min:2|max:500',
        ]);

        $error = null;
        DB::transaction(function () use ($validated, &$error) {
            $product = Product::where('id', $validated['product_id'])
                ->where('type', 'physical')
                ->lockForUpdate()
                ->first();

            if (!$product) {
                $error = 'Produkt nicht gefunden.';
                return;
            }
            if ($product->track_quantity && $product->quantity < (int) $validated['quantity']) {
                $error = 'Nicht genug Bestand vorhanden.';
                return;
            }

            $costValue = ((int) ($product->purchase_price ?? 0)) * ((int) $validated['quantity']);
            ProductLoss::create([
                'product_id' => $product->id,
                'product_supplier_id' => $product->supplier_id ?? null,
                'quantity' => (int) $validated['quantity'],
                'cost_value' => $costValue,
                'reason' => trim($validated['reason']),
                'recorded_by' => null,
            ]);

            $product->reduceStock((int) $validated['quantity']);
        });

        if ($error !== null) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bruch gespeichert und Bestand angepasst.',
            'data' => $buildCockpitPayload($employee, $hrService, (int) now()->year, (int) now()->month),
        ]);
    });

    // --- KUNDEN-SUPPORT (nur wenn can_access_support) ---
    Route::get('/support/tickets/{id}', function (Request $request, string $id) use ($resolveActiveContract) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract || !$contract->can_access_support) {
            abort(403, 'Keine Berechtigung für den Kunden-Support.');
        }

        $ticket = SupportTicket::with(['messages', 'customer'])->findOrFail($id);
        SupportTicketMessage::where('support_ticket_id', $ticket->id)
            ->where('sender_type', 'customer')
            ->update(['is_read_by_admin' => true]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $ticket->id,
                'ticket_number' => (string) ($ticket->ticket_number ?? ''),
                'subject' => (string) ($ticket->subject ?? ''),
                'status' => (string) ($ticket->status ?? 'open'),
                'customer_name' => trim(($ticket->customer->first_name ?? '') . ' ' . ($ticket->customer->last_name ?? '')) ?: 'Kunde',
                'messages' => $ticket->messages->map(fn (SupportTicketMessage $m) => [
                    'id' => (string) $m->id,
                    'sender_type' => (string) ($m->sender_type ?? 'customer'),
                    'message' => (string) ($m->message ?? ''),
                    'created_at_formatted' => $m->created_at?->format('d.m.Y H:i') ?? '',
                ])->values()->all(),
            ],
        ]);
    });

    Route::post('/support/tickets/{id}/reply', function (Request $request, string $id) use ($resolveActiveContract) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract || !$contract->can_access_support) {
            abort(403, 'Keine Berechtigung für den Kunden-Support.');
        }

        $validated = $request->validate([
            'message' => 'required|string|min:2|max:10000',
        ]);

        $ticket = SupportTicket::with('customer')->findOrFail($id);
        if ($ticket->status === 'closed') {
            return response()->json(['success' => false, 'message' => 'Ticket ist bereits geschlossen.'], 422);
        }

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => trim($validated['message']),
            'is_read_by_admin' => true,
            'is_read_by_customer' => false,
        ]);

        $ticket->update(['status' => 'answered']);

        $isOnline = $ticket->customer_id && Cache::has('is_online' . $ticket->customer_id);
        if (!$isOnline && $ticket->customer) {
            $gamification = CustomerGamification::where('customer_id', $ticket->customer_id)->first();
            if (!$gamification || $gamification->ticket_emails_enabled) {
                try {
                    Mail::to($ticket->customer->email)->send(new SupportTicketUpdateMailToCustomer($ticket->customer, $ticket));
                } catch (\Throwable $e) {
                    Log::error('Employee API Support Reply Mail Error: ' . $e->getMessage());
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Antwort gesendet.']);
    });

    Route::post('/support/tickets/{id}/close', function (Request $request, string $id) use ($resolveActiveContract) {
        /** @var Employee $employee */
        $employee = $request->user();
        $contract = $resolveActiveContract($employee);
        if (!$contract || !$contract->can_access_support) {
            abort(403, 'Keine Berechtigung für den Kunden-Support.');
        }

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => 'closed']);

        return response()->json(['success' => true, 'message' => 'Ticket erledigt.']);
    });
});

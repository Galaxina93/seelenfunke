<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth:employee'])->group(function () {

    // Interne Mitarbeiter-Routen (Strikt isoliert vor Influencern / externen Partnern)
    Route::middleware(['employee_role:internal'])->group(function () {
        // Dashboard / Mitarbeiter Cockpit (Arbeitszeiten, Vertrag & Lohnabrechnungen alles auf einer Seite)
        Route::get('/employee/dashboard', \App\Livewire\Employee\EmployeeDashboard::class)
            ->name('employee.dashboard');

        // Alte Unterseiten leiten direkt auf das zentrale Mitarbeiter-Cockpit weiter
        Route::get('/employee/projects', function () {
            return redirect()->route('employee.dashboard');
        })->name('employee.projects');

        Route::get('/employee/payslips', function () {
            return redirect()->route('employee.dashboard');
        })->name('employee.payslips');

        // Eigener Arbeitsvertrag PDF (§ 2 NachwG)
        Route::get('/employee/contract/pdf', function () {
            $employee = auth('employee')->user();
            $contract = $employee?->employmentContract;
            if (!$contract || (string) $contract->employee_id !== (string) $employee->id) {
                abort(403, 'Zugriff verweigert.');
            }
            if (empty($contract->contract_pdf_path) || !Storage::disk('local')->exists((string) $contract->contract_pdf_path)) {
                app(\App\Services\Employee\EmployeeHrService::class)->generateAndStoreContractPdf($contract);
                $contract->refresh();
            }
            $path = (string) $contract->contract_pdf_path;
            if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/contracts/') || !Storage::disk('local')->exists($path)) {
                abort(404);
            }
            $fullPath = Storage::disk('local')->path($path);
            if (is_link($fullPath)) {
                abort(403);
            }
            $cleanName = 'Arbeitsvertrag_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . '.pdf';
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('employee.contract.pdf')->middleware('throttle:30,1');

        // Eigener Personalfragebogen PDF
        Route::get('/employee/questionnaire/pdf', function () {
            $employee = auth('employee')->user();
            $contract = $employee?->employmentContract;
            if (!$contract || (string) $contract->employee_id !== (string) $employee->id) {
                abort(403, 'Zugriff verweigert.');
            }
            if (empty($contract->questionnaire_pdf_path) || !Storage::disk('local')->exists((string) $contract->questionnaire_pdf_path)) {
                app(\App\Services\Employee\EmployeeHrService::class)->generateAndStoreQuestionnairePdf($contract);
                $contract->refresh();
            }
            $path = (string) $contract->questionnaire_pdf_path;
            if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/contracts/') || !Storage::disk('local')->exists($path)) {
                abort(404);
            }
            $fullPath = Storage::disk('local')->path($path);
            if (is_link($fullPath)) {
                abort(403);
            }
            $cleanName = 'Personalbogen_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . '.pdf';
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('employee.questionnaire.pdf')->middleware('throttle:30,1');

        // Eigener Monats-Stundenzettel PDF (§ 17 MiLoG)
        Route::get('/employee/timesheet/{year}/{month}/pdf', function (int $year, int $month) {
            $employee = auth('employee')->user();
            $contract = $employee?->employmentContract;
            if (!$contract || (string) $contract->employee_id !== (string) $employee->id) {
                abort(403, 'Zugriff verweigert.');
            }
            $path = app(\App\Services\Employee\EmployeeHrService::class)->generateAndStoreTimesheetPdf($contract, $year, $month);
            if (!$path || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/receipts/') || !Storage::disk('local')->exists($path)) {
                abort(404);
            }
            $fullPath = Storage::disk('local')->path($path);
            if (is_link($fullPath)) {
                abort(403);
            }
            $cleanName = 'Stundenzettel_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . "_{$year}_{$month}.pdf";
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('employee.timesheet.pdf')->middleware('throttle:30,1');

        // Eigene Gehaltsabrechnung PDF (§ 108 GewO)
        Route::get('/employee/payslip/{payslip}/pdf', function (\App\Models\Employee\EmployeePayslip $payslip) {
            $employee = auth('employee')->user();
            $contract = $employee?->employmentContract;
            if (!$contract || (string) $payslip->contract_id !== (string) $contract->id) {
                abort(403, 'Zugriff verweigert.');
            }
            $path = (string) $payslip->effective_pdf_path;
            if ($path === '' || !Storage::disk('local')->exists($path)) {
                app(\App\Services\Employee\EmployeeHrService::class)->generateAndStorePayslipPdf($payslip);
                $payslip->refresh();
                $path = (string) $payslip->effective_pdf_path;
            }
            if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/receipts/') || !Storage::disk('local')->exists($path)) {
                abort(404);
            }
            $fullPath = Storage::disk('local')->path($path);
            if (is_link($fullPath)) {
                abort(403);
            }
            $cleanName = 'Lohnabrechnung_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $payslip->payslip_number) . '.pdf';
            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('employee.payslip.pdf')->middleware('throttle:30,1');
    });

    // Influencer & Affiliate Creator Portal (Strikt isoliert auf Rolle 'influencer')
    Route::middleware(['employee_role:influencer'])->group(function () {
        Route::get('/employee/influencer/dashboard', \App\Livewire\Employee\Influencer\InfluencerDashboard::class)
            ->name('employee.influencer.dashboard');

        Route::get('/employee/influencer/payout/{payout}/pdf', function (\App\Models\Marketing\MarketingInfluencerPayout $payout) {
            $employee = auth('employee')->user();
            $influencer = $employee?->influencerProfile;

            if (!$influencer || (string) $payout->influencer_id !== (string) $influencer->id) {
                abort(403, 'Zugriff verweigert.');
            }

            if (empty($payout->pdf_path) || !Storage::disk('local')->exists((string) $payout->pdf_path)) {
                app(\App\Services\Marketing\InfluencerService::class)->generateCreditNotePdf($payout);
                $payout->refresh();
            }

            $path = (string) $payout->pdf_path;
            if (
                $path === ''
                || str_contains($path, '..')
                || str_contains($path, "\0")
                || !str_starts_with($path, 'buchhaltung/receipts/')
                || !Storage::disk('local')->exists($path)
            ) {
                abort(404, 'Beleg nicht gefunden.');
            }

            $fullPath = Storage::disk('local')->path($path);
            if (is_link($fullPath)) {
                abort(403);
            }

            $cleanName = 'Gutschrift_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $payout->credit_note_number) . '.pdf';

            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('employee.influencer.payout.pdf')->middleware('throttle:30,1');
    });

    // Steuerberater / Kanzlei Portal (Strikt isoliert auf Rolle 'tax_advisor')
    Route::middleware(['employee_role:tax_advisor'])->group(function () {
        Route::get('/employee/tax-advisor/dashboard', \App\Livewire\Employee\TaxAdvisor\TaxAdvisorDashboard::class)
            ->name('employee.tax-advisor.dashboard');

        Route::get('/employee/tax-advisor/payout/{payout}/pdf', function (\App\Models\Marketing\MarketingInfluencerPayout $payout) {
            $employee = auth('employee')->user();
            if (!$employee || !$employee->isTaxAdvisor()) {
                abort(403, 'Zugriff verweigert.');
            }

            if (empty($payout->pdf_path) || !Storage::disk('local')->exists((string) $payout->pdf_path)) {
                app(\App\Services\Marketing\InfluencerService::class)->generateCreditNotePdf($payout);
                $payout->refresh();
            }

            $path = (string) $payout->pdf_path;
            if (
                $path === ''
                || str_contains($path, '..')
                || str_contains($path, "\0")
                || !str_starts_with($path, 'buchhaltung/receipts/')
                || !Storage::disk('local')->exists($path)
            ) {
                abort(404, 'Beleg nicht gefunden.');
            }

            $fullPath = Storage::disk('local')->path($path);
            if (is_link($fullPath)) {
                abort(403);
            }

            $cleanName = 'Gutschrift_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $payout->credit_note_number) . '.pdf';

            return response()->file($fullPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        })->name('employee.tax-advisor.payout.pdf')->middleware('throttle:30,1');
    });

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/employee/password-reset/{token}', function ($token) {
        return view('auth.password-reset', ['token' => $token]);
    });
});

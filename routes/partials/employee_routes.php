<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth:employee'])->group(function () {

    // Interne Mitarbeiter-Routen (Strikt isoliert vor Influencern / externen Partnern)
    Route::middleware(['employee_role:internal'])->group(function () {
        // Dashboard
        Route::get('/employee/dashboard', function () {
            return view('backend.employee.pages.dashboard');
        })->name('employee.dashboard');

        // Projekte
        Route::get('/employee/projects', function () {
            return view('backend.employee.pages.projects');
        })->name('employee.projects');

        // Gehaltsabrechnungen (Payslips)
        Route::get('/employee/payslips', function () {
            return view('backend.employee.pages.payslips');
        })->name('employee.payslips');
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

            if (empty($payout->pdf_path)) {
                app(\App\Services\Marketing\InfluencerService::class)->generateAndStoreCreditNotePdf($payout);
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

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/employee/password-reset/{token}', function ($token) {
        return view('auth.password-reset', ['token' => $token]);
    });
});

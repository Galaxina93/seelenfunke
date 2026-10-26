<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin'])->group(function () {

    // -----------------------------------------------------------------------
    // Master & Dashboard
    // -----------------------------------------------------------------------
    Route::get('/admin/dashboard', \App\Livewire\Shop\Master\MasterAnalytics::class)->name('admin.dashboard');
    Route::get('/admin/master/analytics', \App\Livewire\Shop\Master\MasterAnalytics::class)->name('admin.master-analytics');

    // -----------------------------------------------------------------------
    // Management
    // -----------------------------------------------------------------------
    Route::get('/admin/routine', \App\Livewire\Shop\Management\ManagementRoutine::class)->name('admin.routine');
    Route::get('/admin/tasks', \App\Livewire\Shop\Management\ManagementTask::class)->name('admin.tasks');
    Route::get('/admin/shopping', \App\Livewire\Shop\Management\ManagementShoppingList::class)->name('admin.shopping');
    Route::get('/admin/calender', \App\Livewire\Shop\Management\ManagementCalender::class)->name('admin.calender');
    Route::get('/admin/calendar', \App\Livewire\Shop\Management\ManagementCalender::class)->name('admin.calendar');

    Route::get('/admin/contacts', \App\Livewire\Shop\Management\ManagementContacts::class)->name('admin.contacts');
    Route::get('/admin/employees', \App\Livewire\Shop\Management\ManagementEmployees::class)->name('admin.employees');

    Route::get('/admin/employees/contract/{contract}/pdf', function (\App\Models\Employee\EmployeeContract $contract) {
        if (empty($contract->contract_pdf_path) || !\Illuminate\Support\Facades\Storage::disk('local')->exists((string) $contract->contract_pdf_path)) {
            app(\App\Services\Employee\EmployeeHrService::class)->generateAndStoreContractPdf($contract);
            $contract->refresh();
        }
        $path = (string) $contract->contract_pdf_path;
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/contracts/') || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        if (is_link($fullPath)) {
            abort(403);
        }
        $cleanName = 'Arbeitsvertrag_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . '.pdf';
        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('admin.employees.contract.pdf');

    Route::get('/admin/employees/questionnaire/{contract}/pdf', function (\App\Models\Employee\EmployeeContract $contract) {
        if (empty($contract->questionnaire_pdf_path) || !\Illuminate\Support\Facades\Storage::disk('local')->exists((string) $contract->questionnaire_pdf_path)) {
            app(\App\Services\Employee\EmployeeHrService::class)->generateAndStoreQuestionnairePdf($contract);
            $contract->refresh();
        }
        $path = (string) $contract->questionnaire_pdf_path;
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/contracts/') || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        if (is_link($fullPath)) {
            abort(403);
        }
        $cleanName = 'Personalbogen_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . '.pdf';
        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('admin.employees.questionnaire.pdf');

    Route::get('/admin/employees/timesheet/{contract}/{year}/{month}/pdf', function (\App\Models\Employee\EmployeeContract $contract, int $year, int $month) {
        $path = app(\App\Services\Employee\EmployeeHrService::class)->generateAndStoreTimesheetPdf($contract, $year, $month);
        if (!$path || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/receipts/') || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        if (is_link($fullPath)) {
            abort(403);
        }
        $cleanName = 'Stundenzettel_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $contract->contract_number) . "_{$year}_{$month}.pdf";
        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('admin.employees.timesheet.pdf');

    Route::get('/admin/employees/payslip/{payslip}/pdf', function (\App\Models\Employee\EmployeePayslip $payslip) {
        $path = (string) $payslip->effective_pdf_path;
        if ($path === '' || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            app(\App\Services\Employee\EmployeeHrService::class)->generateAndStorePayslipPdf($payslip);
            $payslip->refresh();
            $path = (string) $payslip->effective_pdf_path;
        }
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0") || !str_starts_with($path, 'buchhaltung/receipts/') || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        if (is_link($fullPath)) {
            abort(403);
        }
        $cleanName = 'Lohnabrechnung_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $payslip->payslip_number) . '.pdf';
        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('admin.employees.payslip.pdf');
    Route::get('/admin/inbox', \App\Livewire\Shop\Management\ManagementEMails::class)->name('admin.inbox');
    Route::get('/admin/inbox/attachment/{id}', function ($id) {
        $attachment = \App\Models\Management\Mail\MailAttachment::findOrFail($id);
        $path = is_string($attachment->path) ? ltrim(str_replace('\\', '/', $attachment->path), '/') : '';
        if (
            $path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || (!str_starts_with($path, 'leitung/crm/mail-attachments/') && !str_starts_with($path, 'mail-attachments/') && !str_starts_with($path, 'temp/mail-attachments/'))
        ) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        if (\Illuminate\Support\Facades\Storage::exists($path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::path($path);
            if (is_link($fullPath)) {
                abort(403, 'Ungültiger Dateipfad.');
            }
            $safeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
            $contentType = $attachment->content_type ?: 'application/octet-stream';
            $disposition = in_array(strtolower($contentType), $safeMimes, true) ? 'inline' : 'attachment';
            $cleanFilename = str_replace(['"', "\r", "\n", "\0"], '', basename($attachment->filename ?: 'attachment'));

            return response()->file($fullPath, [
                'Content-Type' => $contentType,
                'Content-Disposition' => $disposition . '; filename="' . $cleanFilename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        abort(404);
    })->name('crm.mail-attachment');

    Route::get('/admin/inbox/compose-preview', function (\Illuminate\Http\Request $request) {
        $path = $request->query('path');
        if (!is_string($path) || !str_starts_with($path, 'temp/mail-attachments/') || str_contains($path, '..') || str_contains($path, "\0")) {
            abort(403);
        }
        if (\Illuminate\Support\Facades\Storage::exists($path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::path($path);
            if (is_link($fullPath)) {
                abort(403);
            }
            $mime = \Illuminate\Support\Facades\Storage::mimeType($path) ?: 'application/octet-stream';
            $safeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
            $disposition = in_array(strtolower($mime), $safeMimes, true) ? 'inline' : 'attachment';
            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        abort(404);
    })->name('crm.mail-compose-preview');

    // -----------------------------------------------------------------------
    // System
    // -----------------------------------------------------------------------

    Route::get('/admin/global-logs', \App\Livewire\Shop\System\SystemLogs::class)->name('admin.global-logs');
    Route::get('/admin/user-management', \App\Livewire\Shop\System\SystemUserManagement::class)->name('admin.user-management');
    Route::get('/admin/system/backups', \App\Livewire\Shop\System\SystemBackups::class)->name('admin.system.backups');
    Route::get('/admin/configuration', \App\Livewire\Shop\System\SystemShopConfig::class)->name('admin.configuration');
    Route::get('/admin/system/neural-analysis', \App\Livewire\Backend\System\SystemNeuralAnalysisIndex::class)->name('admin.system.neural-analysis');

    // -----------------------------------------------------------------------
    // Support
    // -----------------------------------------------------------------------
    Route::get('/admin/support/analytics', \App\Livewire\Shop\Support\SupportAnalytics::class)->name('admin.support.analytics');
    Route::get('/admin/support-tickets', \App\Livewire\Shop\Support\SupportTicket::class)->name('admin.support-tickets');
    Route::get('/admin/support-chats', \App\Livewire\Shop\Support\SupportChats::class)->name('admin.support-chats');
    Route::get('/admin/support-contact-form', \App\Livewire\Shop\Support\SupportContactFormComponent::class)->name('admin.support-contact-form');

    // -----------------------------------------------------------------------
    // AI Agent Universe
    // -----------------------------------------------------------------------
    Route::get('/admin/ai/analytics', \App\Livewire\Shop\Ai\AiAnalytics::class)->name('admin.ai.analytics');
    Route::get('/admin/ai/workspace', \App\Livewire\Shop\Ai\AiWorkspace::class)->name('admin.ai.workspace');
    Route::get('/admin/ai/workspace/file', function (\Illuminate\Http\Request $request) {
        $path = $request->query('path');
        if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/') || str_starts_with($path, '\\') || !str_starts_with($path, 'agenten/workspace/') || !\Illuminate\Support\Facades\Storage::disk('workspace')->exists($path)) {
            abort(404);
        }
        $fullPath = \Illuminate\Support\Facades\Storage::disk('workspace')->path($path);
        if (is_link($fullPath)) {
            abort(404);
        }
        $mime = \Illuminate\Support\Facades\Storage::disk('workspace')->mimeType($path) ?: 'application/octet-stream';
        $safeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
        $disposition = in_array(strtolower($mime), $safeMimes, true) ? 'inline' : 'attachment';
        $cleanFilename = str_replace(['"', "\r", "\n", "\0"], '', basename($path));
        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition . '; filename="' . $cleanFilename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache'
        ]);
    })->name('admin.ai.workspace.file');
    Route::get('/admin/support/telephony', \App\Livewire\Shop\Support\SupportTelephony::class)->name('admin.support.telephony');
    
    // Organigramm bleibt separat
    Route::get('/admin/organigramm', \App\Livewire\Shop\Ai\AiCompanyStructure::class)->name('admin.ai-company-structure');
    
    // Editoren für spezifische KI Agenten bleiben, da sie ID-gebunden sind
    Route::get('/admin/ki-agenten/{id}', \App\Livewire\Shop\Ai\AiAgentEditor::class)->name('admin.ai-agents.editor');

    // -----------------------------------------------------------------------
    // Products
    // -----------------------------------------------------------------------
    Route::get('/admin/products', \App\Livewire\Shop\Product\ProductCreate::class)->name('admin.products');
    Route::get('/admin/product-analytics', \App\Livewire\Shop\Product\ProductAnalytics::class)->name('admin.product-analytics');
    Route::get('/admin/product-packaging', \App\Livewire\Shop\Product\ProductPackagingConfigurator::class)->name('admin.product-packaging');
    Route::get('/admin/product-fracture', \App\Livewire\Shop\Product\ProductFracture::class)->name('admin.product-fracture');
    Route::get('/admin/product-suppliers', \App\Livewire\Shop\Product\ProductSuppliers::class)->name('admin.product-suppliers');
    Route::get('/admin/product-crawler', \App\Livewire\Shop\Product\ProductCrawler::class)->name('admin.product-crawler');
    Route::get('/admin/product-templates', \App\Livewire\Shop\Product\ProductTemplates::class)->name('admin.product-templates');
    Route::get('/admin/product-configurator', \App\Livewire\Shop\Product\ProductConfiguratorSettings::class)->name('admin.product-configurator');
    Route::get('/admin/reviews', \App\Livewire\Shop\Product\ProductControlReviews::class)->name('admin.product-control-reviews');

    // -----------------------------------------------------------------------
    // Orders
    // -----------------------------------------------------------------------
    Route::get('/admin/orders/analytics', \App\Livewire\Shop\Order\OrderAnalytics::class)->name('admin.orders.analytics');
    Route::get('/admin/orders', \App\Livewire\Shop\Order\OrderOverview::class)->name('admin.orders');
    Route::get('/admin/shopping-carts', \App\Livewire\Shop\Order\OrderShoppingCarts::class)->name('admin.shopping-carts');
    Route::get('/admin/quote-requests', \App\Livewire\Shop\Order\OrderQuoteRequests::class)->name('admin.quote-requests');
    Route::get('/admin/widerruf', \App\Livewire\Shop\Order\OrderRevocations::class)->name('admin.widerruf');
    Route::get('/admin/widerruf/file/{revocation}/{fileName}', function (\App\Models\Order\OrderRevocation $revocation, $fileName) {
        if (!is_string($fileName) || str_contains($fileName, '..') || str_contains($fileName, '/') || str_contains($fileName, '\\') || str_contains($fileName, "\0")) {
            abort(404);
        }
        $path = "bestellungen/private/revocations/{$revocation->id}/{$fileName}";
        if (!is_array($revocation->attachments) || !in_array($path, $revocation->attachments, true)) {
            $legacyPath = "revocations/{$revocation->id}/{$fileName}";
            if (is_array($revocation->attachments) && in_array($legacyPath, $revocation->attachments, true)) {
                $path = $legacyPath;
            } else {
                abort(404);
            }
        }
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
            if (is_link($fullPath)) {
                abort(404);
            }
            $mime = \Illuminate\Support\Facades\Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';
            $safeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
            $disposition = in_array(strtolower($mime), $safeMimes, true) ? 'inline' : 'attachment';
            $cleanFilename = str_replace(['"', "\r", "\n", "\0"], '', basename($fileName));
            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition . '; filename="' . $cleanFilename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        abort(404);
    })->name('admin.widerruf.file');

    // -----------------------------------------------------------------------
    // Accounting
    // -----------------------------------------------------------------------
    Route::get('/admin/accounting/receipt', function (\Illuminate\Http\Request $request) {
        $path = $request->query('path');
        if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            abort(404);
        }
        // Sicherheits-Whitelist: Nur Belege und Verträge dürfen über diesen Endpoint abgerufen werden
        if (!str_starts_with($path, 'buchhaltung/receipts/') && !str_starts_with($path, 'buchhaltung/contracts/')) {
            abort(403, 'Zugriff verweigert.');
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('local')->exists($path)
            ? 'local'
            : (\Illuminate\Support\Facades\Storage::disk('public')->exists($path) ? 'public' : null);

        if ($disk) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk($disk)->path($path);
            if (is_link($fullPath)) {
                abort(403, 'Zugriff verweigert.');
            }
            $mime = \Illuminate\Support\Facades\Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
            $safeMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];
            $disposition = in_array(strtolower($mime), $safeMimes, true) ? 'inline' : 'attachment';
            $cleanFilename = str_replace(['"', "\r", "\n", "\0"], '', basename($path));

            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition . '; filename="' . $cleanFilename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404);
    })->name('admin.accounting.receipt.show');
    
    Route::get('/admin/invoices', \App\Livewire\Shop\Accounting\AccountingInvoice::class)->name('admin.invoices');
    Route::get('/admin/credit-management', \App\Livewire\Shop\Accounting\AccountingCredit::class)->name('admin.credit-management');
    Route::get('/admin/financial-analytics', \App\Livewire\Shop\Accounting\AccountingAnalytics::class)->name('admin.financial-analytics');
    Route::get('/admin/financial-liquidity-planning', \App\Livewire\Shop\Accounting\AccountingLiquidity::class)->name('admin.financial-liquidity-planning');
    Route::get('/admin/financial-banks', \App\Livewire\Shop\Accounting\AccountingBank::class)->name('admin.financial-banks');
    Route::get('/admin/financial-fix-costs', \App\Livewire\Shop\Accounting\AccountingFixCosts::class)->name('admin.financial-fix-costs');
    Route::get('/admin/financial-variable-costs', \App\Livewire\Shop\Accounting\AccountingVariableCosts::class)->name('admin.financial-variable-costs');
    Route::get('/admin/financial-tax', \App\Livewire\Shop\Accounting\AccountingTax::class)->name('admin.financial-tax');

    // -----------------------------------------------------------------------
    // Marketing
    // -----------------------------------------------------------------------
    Route::get('/admin/marketing/analytics', \App\Livewire\Shop\Marketing\MarketingAnalytics::class)->name('admin.marketing-analytics');
    Route::get('/admin/marketing/landing-pages', \App\Livewire\Shop\Marketing\MarketingLandingPages::class)->name('admin.marketing-landing-pages');
    Route::get('/admin/marketing/herzensprojekte', \App\Livewire\Shop\Marketing\MarketingHerzensprojekte::class)->name('admin.marketing-herzensprojekte');
    Route::get('/admin/blog', \App\Livewire\Shop\Marketing\MarketingBlog::class)->name('admin.blog');
    Route::get('/admin/voucher', \App\Livewire\Shop\Marketing\MarketingVoucher::class)->name('admin.voucher');
    Route::get('/admin/newsletter', \App\Livewire\Shop\Marketing\MarketingNewsletter::class)->name('admin.newsletter');
    Route::get('/admin/marketing/linktree', \App\Livewire\Shop\Marketing\MarketingLinktree::class)->name('admin.marketing-linktree');
    Route::get('/admin/marketing/tracking', \App\Livewire\Shop\Marketing\MarketingTracking::class)->name('admin.marketing-tracking');
    Route::get('/admin/marketing/influencer', \App\Livewire\Shop\Marketing\MarketingInfluencer::class)->name('admin.marketing-influencer');

    Route::get('/admin/marketing/influencer/payout/{payout}/pdf', function (\App\Models\Marketing\MarketingInfluencerPayout $payout) {
        if (empty($payout->pdf_path) || !\Illuminate\Support\Facades\Storage::disk('local')->exists((string) $payout->pdf_path)) {
            app(\App\Services\Marketing\InfluencerService::class)->generateCreditNotePdf($payout);
            $payout->refresh();
        }

        $path = (string) $payout->pdf_path;
        if (
            $path === ''
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || !str_starts_with($path, 'buchhaltung/receipts/')
            || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)
        ) {
            abort(404, 'Beleg nicht gefunden.');
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        if (is_link($fullPath)) {
            abort(403, 'Zugriff verweigert.');
        }

        $cleanName = 'Gutschrift_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $payout->credit_note_number) . '.pdf';

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cleanName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('admin.influencer.payout.pdf');

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/admin/password-reset/{token}', function ($token) {
        return view('auth.password-reset', ['token' => $token]);
    });
});

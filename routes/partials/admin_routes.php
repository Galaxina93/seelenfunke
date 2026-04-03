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
    Route::get('/admin/calender', \App\Livewire\Shop\Management\ManagementCalender::class)->name('admin.calender');
    Route::get('/admin/ceo/gesundheit', \App\Livewire\Shop\Management\ManagementHealth::class)->name('ceo.gesundheit');
    Route::get('/admin/contacts', \App\Livewire\Shop\Management\ManagementContacts::class)->name('admin.contacts');
    Route::get('/admin/inbox', \App\Livewire\Shop\Management\ManagementEMails::class)->name('admin.inbox');
    Route::get('/admin/inbox/attachment/{id}', function ($id) {
        $attachment = \App\Models\Management\Mail\MailAttachment::findOrFail($id);
        if (\Illuminate\Support\Facades\Storage::exists($attachment->path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::path($attachment->path), [
                'Content-Type' => $attachment->content_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->filename . '"'
            ]);
        }
        abort(404);
    })->name('crm.mail-attachment');

    // -----------------------------------------------------------------------
    // System
    // -----------------------------------------------------------------------
    Route::get('/admin/company-map', \App\Livewire\Shop\System\SystemCompanyMap::class)->name('admin.company-map');
    Route::get('/admin/global-logs', \App\Livewire\Shop\System\SystemLogs::class)->name('admin.global-logs');
    Route::get('/admin/system-info', \App\Livewire\Shop\System\SystemInfo::class)->name('admin.system-info');
    Route::get('/admin/user-management', \App\Livewire\Shop\System\SystemUserManagement::class)->name('admin.user-management');
    Route::get('/admin/configuration', \App\Livewire\Shop\System\SystemShopConfig::class)->name('admin.configuration');

    // -----------------------------------------------------------------------
    // Support
    // -----------------------------------------------------------------------
    Route::get('/admin/support/analytics', \App\Livewire\Shop\Support\SupportAnalytics::class)->name('admin.support.analytics');
    Route::get('/admin/support-tickets', \App\Livewire\Shop\Support\SupportTicket::class)->name('admin.support-tickets');
    Route::get('/admin/support-chats', \App\Livewire\Shop\Support\SupportChatAnalytics::class)->name('admin.support-chats');
    Route::get('/admin/support-contact-form', \App\Livewire\Shop\Support\SupportContactFormComponent::class)->name('admin.support-contact-form');

    // -----------------------------------------------------------------------
    // AI Agent Universe
    // -----------------------------------------------------------------------
    Route::get('/admin/ai-analytics', \App\Livewire\Shop\Ai\AiAnalytics::class)->name('admin.ai-analytics');
    Route::get('/admin/ai-genui', \App\Livewire\Shop\Ai\AiVisualizationRegistry::class)->name('admin.ai-genui');
    Route::get('/admin/rollen', \App\Livewire\Shop\Ai\AiRoleManager::class)->name('admin.rollen');
    Route::get('/admin/agenten', \App\Livewire\Shop\Ai\AiAgentManager::class)->name('admin.ai-agents');
    Route::get('/admin/organigramm', \App\Livewire\Shop\Ai\AiCompanyStructure::class)->name('admin.ai-company-structure');
    Route::get('/admin/ki-agenten/{id}', \App\Livewire\Shop\Ai\AiAgentEditor::class)->name('admin.ai-agents.editor');
    Route::get('/admin/externe-agenten/{id}', \App\Livewire\Shop\Ai\ExternalAgentEditor::class)->name('admin.external-agents.editor');
    Route::get('/admin/ai-chat', \App\Livewire\Shop\Ai\AiChat::class)->name('admin.ai-chat');
    Route::get('/admin/ai-knowledge_base', \App\Livewire\Shop\Ai\AiKnowledgeBase::class)->name('admin.ai-knowledge_base');

    // -----------------------------------------------------------------------
    // Products
    // -----------------------------------------------------------------------
    Route::get('/admin/products', \App\Livewire\Shop\Product\ProductCreate::class)->name('admin.products');
    Route::get('/admin/product-analytics', \App\Livewire\Shop\Product\ProductAnalytics::class)->name('admin.product-analytics');
    Route::get('/admin/product-packaging', \App\Livewire\Shop\Product\ProductPackagingConfigurator::class)->name('admin.product-packaging');
    Route::get('/admin/product-fracture', \App\Livewire\Shop\Product\ProductFracture::class)->name('admin.product-fracture');
    Route::get('/admin/product-suppliers', \App\Livewire\Shop\Product\ProductSuppliers::class)->name('admin.product-suppliers');
    Route::get('/admin/products/nischen-scout', \App\Livewire\Shop\Product\ProductNicheScanner::class)->name('admin.products.niche');
    Route::get('/admin/product-templates', \App\Livewire\Shop\Product\ProductTemplates::class)->name('admin.product-templates');
    Route::get('/admin/reviews', \App\Livewire\Shop\Product\ProductControlReviews::class)->name('admin.product-control-reviews');

    // -----------------------------------------------------------------------
    // Orders
    // -----------------------------------------------------------------------
    Route::get('/admin/orders/analytics', \App\Livewire\Shop\Order\OrderAnalytics::class)->name('admin.orders.analytics');
    Route::get('/admin/orders', \App\Livewire\Shop\Order\OrderOverview::class)->name('admin.orders');
    Route::get('/admin/quote-requests', \App\Livewire\Shop\Order\OrderQuoteRequests::class)->name('admin.quote-requests');
    Route::get('/admin/widerruf', \App\Livewire\Shop\Order\OrderRevocations::class)->name('admin.widerruf');
    Route::get('/admin/widerruf/file/{revocation}/{fileName}', function (\App\Models\Order\OrderRevocation $revocation, $fileName) {
        $path = "revocations/{$revocation->id}/{$fileName}";
        // Check if file exists on 'private' disk (default securely stored location)
        if (\Illuminate\Support\Facades\Storage::disk('private')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('private')->path($path));
        }
        // Fallback to local if 'private' disk isn't strictly distinct
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($path));
        }
        abort(404);
    })->name('admin.widerruf.file');

    // -----------------------------------------------------------------------
    // Accounting
    // -----------------------------------------------------------------------
    Route::get('/admin/accounting/receipt', function (\Illuminate\Http\Request $request) {
        $path = $request->query('path');
        if (!$path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }
        return response()->file(storage_path('app/' . $path));
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
    Route::get('/admin/blog', \App\Livewire\Shop\Marketing\MarketingBlog::class)->name('admin.blog');
    Route::get('/admin/voucher', \App\Livewire\Shop\Marketing\MarketingVoucher::class)->name('admin.voucher');
    Route::get('/admin/newsletter', \App\Livewire\Shop\Marketing\MarketingNewsletter::class)->name('admin.newsletter');

});

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/admin/password-reset/{token}', function ($token) {
        return view('auth.password-reset', ['token' => $token]);
    });
});

<?php

namespace App\Livewire\Shop\Funki;

use App\Models\FunkiNewsletter;
use App\Models\NewsletterSubscriber;
use App\Models\FunkiVoucher;
use App\Services\FunkiBotService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FunkiBot extends Component
{
    use WithPagination;

    // -------------------------------------------------------------------------
    // 1. STEUERUNG & TABS
    // -------------------------------------------------------------------------
    public $activeTab = 'instructions'; // 'instructions', 'routine', 'funkitodo', 'automation', 'calendar'

    // Unter-Ansichten Steuerung
    public $newsletterMode = 'timeline'; // 'timeline' oder 'subscribers'
    public $voucherMode = 'timeline';    // 'timeline' oder 'list'
    public $voucherSectionMode = 'auto'; // 'auto' (Saisonal) oder 'manual' (Manuell)
    public $showNewsletterArchive = false; // Steuert die Anzeige des Archivs

    // Suche & Pagination
    public $search = '';

    // -------------------------------------------------------------------------
    // 2. EDITOR VARIABLES (Newsletter)
    // -------------------------------------------------------------------------
    public $editingTemplateId = null;
    public $edit_subject;
    public $edit_content;
    public $edit_offset;

    // -------------------------------------------------------------------------
    // 3. EDITOR VARIABLES (Voucher Automatisierung)
    // -------------------------------------------------------------------------
    public $editingVoucherId = null;
    public $edit_voucher_title;
    public $edit_voucher_trigger;
    public $edit_voucher_offset;
    public $edit_voucher_code;
    public $edit_voucher_value;
    public $edit_voucher_type = 'percent';
    public $edit_voucher_validity;
    public $edit_voucher_subject;
    public $edit_voucher_content;
    public $edit_voucher_valid_from;
    public $edit_voucher_valid_until;
    public $edit_voucher_min_order;

    // -------------------------------------------------------------------------
    // 4. EDITOR VARIABLES (Manuelle Gutscheine)
    // -------------------------------------------------------------------------
    public $isCreatingManual = false;
    public $isEditingManual = false;
    public $manualId = null;
    public $manual_code;
    public $manual_type = 'fixed';
    public $manual_value;
    public $manual_min_order_value;
    public $manual_usage_limit;
    public $manual_valid_until;
    public $manual_is_active = true;

    // -------------------------------------------------------------------------
    // COMPUTED PROPERTIES (Das "Gehirn")
    // -------------------------------------------------------------------------

    /**
     * Arbeitsanweisungen für das Dashboard (Tab 1).
     */
    public function getWorkInstructionsProperty()
    {
        $service = app(FunkiBotService::class);
        return [
            'priority_order'  => $service->getPriorityOrder(),
            'product_status'  => $service->getProductStatus(),
            'quote_status'    => $service->getQuoteStatus(),
            'invoice_status'  => $service->getInvoiceStatus(),
            'blog_status'     => $service->getBlogStatus(),
            'shipping_status' => $service->getShippingStatus(),
            'system_status'   => $service->getSystemStatus(),
        ];
    }

    /**
     * Ruft die ultimative Anweisung vom Service ab.
     * Dies wird in der View via $this->ultimateCommand genutzt.
     */
    public function getUltimateCommandProperty()
    {
        return app(FunkiBotService::class)->getUltimateCommand();
    }

    /**
     * Statistiken für den Header.
     */
    public function getStats()
    {
        return [
            'subscribers'       => NewsletterSubscriber::count(),
            'active_newsletter' => FunkiNewsletter::where('is_active', true)->count(),
            'active_vouchers'   => FunkiVoucher::where('is_active', true)->where('mode', 'auto')->count(),
            'manual_coupons'    => FunkiVoucher::where('is_active', true)->where('mode', 'manual')->count(),
        ];
    }

    // -------------------------------------------------------------------------
    // ACTIONS
    // -------------------------------------------------------------------------

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    /* --- NEWSLETTER LOGIK --- */

    public function setNewsletterMode($mode)
    {
        $this->newsletterMode = $mode;
        $this->resetPage();
        $this->cancelEdit();
    }

    public function toggleNewsletterArchive()
    {
        $this->showNewsletterArchive = !$this->showNewsletterArchive;
    }

    public function editTemplate($id)
    {
        $this->cancelEditVoucher();
        $this->cancelManualCoupon();

        $t = FunkiNewsletter::findOrFail($id);
        $this->editingTemplateId = $t->id;
        $this->edit_subject = $t->subject;
        $this->edit_content = $t->content;
        $this->edit_offset = $t->days_offset;
    }

    public function saveTemplate()
    {
        $this->validate([
            'edit_subject' => 'required|string',
            'edit_content' => 'required|string',
            'edit_offset'  => 'required|integer'
        ]);

        FunkiNewsletter::find($this->editingTemplateId)->update([
            'subject'     => $this->edit_subject,
            'content'     => $this->edit_content,
            'days_offset' => $this->edit_offset
        ]);

        $this->editingTemplateId = null;
        session()->flash('success', 'Kampagne aktualisiert.');
    }

    public function archiveTemplate($id, FunkiBotService $service)
    {
        $service->archiveNewsletter($id);
        session()->flash('success', 'Kampagne ins Archiv verschoben.');
        $this->cancelEdit();
    }

    public function restoreTemplate($id, FunkiBotService $service)
    {
        $service->restoreNewsletter($id);
        session()->flash('success', 'Kampagne reaktiviert.');
    }

    public function cancelEdit()
    {
        $this->editingTemplateId = null;
        $this->reset(['edit_subject', 'edit_content', 'edit_offset']);
    }

    public function sendTestMail()
    {
        $this->validate([
            'edit_subject' => 'required|string|min:3',
            'edit_content' => 'required|string|min:3',
        ]);

        try {
            $service = app(FunkiBotService::class);
            $recipient = $service->sendPreviewMail($this->edit_subject, $this->edit_content);
            session()->flash('test_success', 'Testmail an ' . $recipient . ' gesendet! ✨');
        } catch (\Exception $e) {
            Log::error('Funki Testmail Fehler: ' . $e->getMessage());
            session()->flash('test_error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function deleteSubscriber($id)
    {
        if (NewsletterSubscriber::destroy($id)) {
            session()->flash('success', 'Empfänger entfernt.');
        }
    }

    /* --- GUTSCHEIN LOGIK (AUTO) --- */

    public function toggleVoucherSectionMode()
    {
        $this->voucherSectionMode = ($this->voucherSectionMode === 'auto') ? 'manual' : 'auto';
        $this->cancelEditVoucher();
        $this->cancelManualCoupon();
    }

    public function toggleVoucherStatus($id)
    {
        $v = FunkiVoucher::find($id);
        if ($v) {
            $v->is_active = !$v->is_active;
            $v->save();
            $status = $v->is_active ? 'aktiviert' : 'pausiert';
            session()->flash('success', "Gutschein erfolgreich $status.");
        }
    }

    public function editVoucher($id)
    {
        $this->cancelEdit();
        $this->cancelManualCoupon();

        $v = FunkiVoucher::where('mode', 'auto')->findOrFail($id);
        $this->editingVoucherId = $v->id;
        $this->edit_voucher_title = $v->title;
        $this->edit_voucher_code = $v->code;
        $this->edit_voucher_type = $v->type;
        $this->edit_voucher_value = ($v->type === 'fixed') ? $v->value / 100 : $v->value;
        $this->edit_voucher_min_order = $v->min_order_value ? $v->min_order_value / 100 : null;
        $this->edit_voucher_valid_from = $v->valid_from ? Carbon::parse($v->valid_from)->format('Y-m-d\TH:i') : null;
        $this->edit_voucher_valid_until = $v->valid_until ? Carbon::parse($v->valid_until)->format('Y-m-d\TH:i') : null;
        $this->edit_voucher_trigger = $v->trigger_event;
        $this->edit_voucher_offset = $v->days_offset;
        $this->edit_voucher_validity = $v->validity_days;
    }

    public function saveVoucher()
    {
        $this->validate([
            'edit_voucher_title'       => 'required',
            'edit_voucher_code'        => 'required',
            'edit_voucher_value'       => 'required|numeric',
            'edit_voucher_valid_from'  => 'required|date',
            'edit_voucher_valid_until' => 'required|date|after:edit_voucher_valid_from',
        ]);

        $val = $this->edit_voucher_value;
        $dbValue = ($this->edit_voucher_type === 'fixed') ? (int)($val * 100) : (int)$val;
        $dbMinOrder = $this->edit_voucher_min_order ? (int)($this->edit_voucher_min_order * 100) : null;

        FunkiVoucher::find($this->editingVoucherId)->update([
            'title'           => $this->edit_voucher_title,
            'code'            => strtoupper($this->edit_voucher_code),
            'type'            => $this->edit_voucher_type,
            'value'           => $dbValue,
            'min_order_value' => $dbMinOrder,
            'valid_from'      => $this->edit_voucher_valid_from,
            'valid_until'     => $this->edit_voucher_valid_until,
            'days_offset'     => $this->edit_voucher_offset,
            'validity_days'   => $this->edit_voucher_validity,
            'mode'            => 'auto'
        ]);

        $this->editingVoucherId = null;
        session()->flash('success', 'Automatischer Gutschein aktualisiert.');
    }

    public function cancelEditVoucher()
    {
        $this->editingVoucherId = null;
        $this->reset([
            'edit_voucher_title', 'edit_voucher_code', 'edit_voucher_type',
            'edit_voucher_value', 'edit_voucher_min_order',
            'edit_voucher_valid_from', 'edit_voucher_valid_until',
            'edit_voucher_subject', 'edit_voucher_content',
            'edit_voucher_trigger', 'edit_voucher_offset', 'edit_voucher_validity'
        ]);
    }

    public function sendTestVoucherMail()
    {
        $this->validate([
            'edit_voucher_subject' => 'required',
            'edit_voucher_content' => 'required',
            'edit_voucher_value'   => 'required',
            'edit_voucher_code'    => 'required'
        ]);

        try {
            $service = app(FunkiBotService::class);
            $recipient = $service->sendVoucherPreviewMail(
                $this->edit_voucher_subject,
                $this->edit_voucher_content,
                $this->edit_voucher_code,
                $this->edit_voucher_value,
                $this->edit_voucher_type
            );
            session()->flash('voucher_test_success', 'Test-Gutschein an ' . $recipient . ' gesendet!');
        } catch (\Exception $e) {
            Log::error('Voucher Testmail Fehler: ' . $e->getMessage());
            session()->flash('voucher_test_error', 'Fehler: ' . $e->getMessage());
        }
    }

    /* --- GUTSCHEIN LOGIK (MANUELL) --- */

    public function createManualCoupon()
    {
        $this->cancelEditVoucher();
        $this->cancelEdit();
        $this->resetManualInput();
        $this->manual_code = strtoupper(Str::random(8));
        $this->isCreatingManual = true;
        $this->isEditingManual = false;
    }

    public function editManualCoupon($id)
    {
        $this->cancelEditVoucher();
        $this->cancelEdit();

        $v = FunkiVoucher::where('mode', 'manual')->findOrFail($id);
        $this->manualId = $v->id;
        $this->manual_code = $v->code;
        $this->manual_type = $v->type;
        $this->manual_is_active = (bool)$v->is_active;
        $this->manual_usage_limit = $v->usage_limit;
        $this->manual_value = $v->type === 'fixed' ? $v->value / 100 : $v->value;
        $this->manual_min_order_value = $v->min_order_value ? $v->min_order_value / 100 : null;
        $this->manual_valid_until = $v->valid_until
            ? (is_string($v->valid_until) ? substr($v->valid_until, 0, 10) : $v->valid_until->format('Y-m-d'))
            : null;

        $this->isCreatingManual = false;
        $this->isEditingManual = true;
    }

    public function saveManualCoupon()
    {
        $rules = [
            'manual_code'  => 'required|min:3|unique:funki_vouchers,code,' . $this->manualId,
            'manual_type'  => 'required|in:fixed,percent',
            'manual_value' => 'required|numeric|min:1',
        ];
        $this->validate($rules);

        $dbValue = ($this->manual_type === 'fixed') ? (int)($this->manual_value * 100) : (int)$this->manual_value;
        $dbMinOrder = $this->manual_min_order_value ? (int)($this->manual_min_order_value * 100) : null;

        $data = [
            'code'            => strtoupper($this->manual_code),
            'title'           => 'Manueller Code: ' . strtoupper($this->manual_code),
            'type'            => $this->manual_type,
            'is_active'       => $this->manual_is_active,
            'usage_limit'     => $this->manual_usage_limit ?: null,
            'valid_until'     => $this->manual_valid_until ?: null,
            'value'           => $dbValue,
            'min_order_value' => $dbMinOrder,
            'mode'            => 'manual',
            'valid_from'      => now(),
        ];

        FunkiVoucher::updateOrCreate(['id' => $this->manualId], $data);

        session()->flash('success', $this->isEditingManual ? 'Gutschein aktualisiert.' : 'Gutschein erstellt.');
        $this->cancelManualCoupon();
    }

    public function deleteManualCoupon($id)
    {
        FunkiVoucher::where('mode', 'manual')->findOrFail($id)->delete();
        session()->flash('success', 'Gutschein gelöscht.');
    }

    public function cancelManualCoupon()
    {
        $this->resetManualInput();
        $this->isCreatingManual = false;
        $this->isEditingManual = false;
    }

    private function resetManualInput()
    {
        $this->reset([
            'manualId', 'manual_code', 'manual_type', 'manual_value',
            'manual_min_order_value', 'manual_usage_limit',
            'manual_valid_until', 'manual_is_active'
        ]);
        $this->manual_is_active = true;
        $this->manual_type = 'fixed';
    }

    /* -------------------------------------------------------------------------
       RENDER
       ------------------------------------------------------------------------- */
    public function render(FunkiBotService $service)
    {
        $autoVouchers = FunkiVoucher::where('mode', 'auto')
            ->orderBy('valid_from')
            ->get();

        $manualCoupons = [];
        if ($this->voucherSectionMode === 'manual') {
            $manualCoupons = FunkiVoucher::where('mode', 'manual')
                ->latest()
                ->paginate(10);
        }

        return view('livewire.shop.funki.funki-bot', [
            'stats'              => $this->getStats(),
            'newsletterTimeline' => $service->getNewsletterTimeline(date('Y')),
            'autoVouchers'       => $autoVouchers,
            'subscribers'        => $this->newsletterMode === 'subscribers'
                ? NewsletterSubscriber::where('email', 'like', "%{$this->search}%")->paginate(10)
                : collect(),
            'archivedTemplates'  => $this->showNewsletterArchive
                ? FunkiNewsletter::where('is_active', false)->get()
                : collect(), // Lade nur wenn Archiv offen
            'availableEvents'    => $service->getAvailableEvents(),
            'manualCoupons'      => $manualCoupons
        ]);
    }
}

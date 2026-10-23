<?php

namespace App\Livewire\Shop\Accounting;

use Livewire\Attributes\Layout;

use App\Models\Customer\Customer;
use App\Models\Accounting\AccountingInvoice as InvoiceModel;
use App\Models\Order\OrderOrder;
use App\Services\EInvoiceService;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithPagination;
use App\Models\System\SystemLog;

#[Layout('components.layouts.backend_layout')]
class AccountingInvoice extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Buchhaltung';

    use WithPagination;

    public $search = '';
    public $filterType = '';
    public $isCreatingManual = false;
    public $selectedCustomerId = null;
    public $activeTab = 'list'; // list, e_invoices, archive

    // Feedback States
    public $saveSuccess = false;
    public $draftSuccess = false;

    // Sorting Table
    public $sortField = 'invoice_date'; // Standard-Sortierung
    public $sortDirection = 'desc';

    public $infoTexts = [
        'customer' => 'Wählen Sie einen bestehenden Kunden aus, um die Adressdaten automatisch zu befüllen.',
        'invoice_info' => 'Pflichtangaben für eine rechtssichere Rechnung gemäß GoBD.',
        'due_date' => 'Das Fälligkeitsdatum wird automatisch berechnet, wenn Sie die Tage anpassen.',
        'header_text' => 'Dieser Text erscheint oben auf der Rechnung unterhalb der Anschrift.',
        'footer_text' => 'Hier können Sie Zahlungsinformationen und Grußformeln hinterlegen.',
        'e_invoice' => 'Aktiviert das strukturierte XML-Format für elektronische Rechnungen.',
        'status_draft' => 'Ein Entwurf kann später bearbeitet werden und erhält noch keine endgültige Buchung.',
        'variables' => 'Verfügbare Platzhalter: [%ZAHLUNGSZIEL%], [%KONTAKTPERSON%], [%RECHNUNGSNUMMER%]'
    ];

    public $manualInvoice = [
        'id' => null,
        'customer_email' => '',
        'first_name' => '',
        'last_name' => '',
        'company' => '',
        'address' => '',
        'address_addition' => '',
        'city' => '',
        'postal_code' => '',
        'country' => 'DE',
        'invoice_date' => '',
        'delivery_date' => '',
        'invoice_number' => '',
        'reference_number' => '',
        'due_days' => 14,
        'due_date' => '',
        'subject' => '',
        'header_text' => "Sehr geehrte Damen und Herren,\n\nvielen Dank für Ihren Auftrag und das damit verbundene Vertrauen!\nHiermit stelle ich Ihnen die folgenden Leistungen in Rechnung:",
        'footer_text' => "Bitte überweisen Sie den Rechnungsbetrag unter Angabe der Rechnungsnummer auf das unten angegebene Konto.\nDer Rechnungsbetrag ist bis zum [%ZAHLUNGSZIEL%] fällig.\n\nMit freundlichen Grüßen\n[%KONTAKTPERSON%]",
        'is_e_invoice' => false,
        'items' => [],
        'shipping_cost' => 0,
        'discount_amount' => 0,
        'volume_discount' => 0,
    ];

    protected $listeners = ['refreshComponent' => '$refresh'];

    protected array $allowedSortFields = [
        'invoice_date',
        'invoice_number',
        'total',
        'status',
        'recipient',
        'created_at',
        'due_date',
    ];

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check()) {
            abort(403, 'Nicht autorisierter Zugriff auf die Rechnungsverwaltung.');
        }
    }

    public function updatedSelectedCustomerId($value)
    {
        if ($value) {
            $customer = Customer::with('profile')->find($value);
            if ($customer) {
                $this->manualInvoice['customer_email'] = $customer->email;
                $this->manualInvoice['first_name'] = $customer->first_name;
                $this->manualInvoice['last_name'] = $customer->last_name;
                $this->manualInvoice['company'] = $customer->company;

                // Laden aus User Profile wie gewünscht
                if ($customer->profile) {
                    $this->manualInvoice['address'] = trim(($customer->profile->street ?? '') . ' ' . ($customer->profile->house_number ?? ''));
                    $this->manualInvoice['city'] = $customer->profile->city ?? '';
                    $this->manualInvoice['postal_code'] = $customer->profile->postal ?? '';
                    $this->manualInvoice['country'] = $customer->profile->country ?? 'DE';
                }
            }
        }
    }

    public function editDraft($id)
    {
        $invoice = InvoiceModel::findOrFail($id);
        if ($invoice->status !== 'draft') {
            abort(403, 'GoBD-Schutz: Abgeschlossene Rechnungen dürfen nicht nachträglich bearbeitet werden.');
        }

        $this->manualInvoice = [
            'id' => $invoice->id,
            'customer_email' => $invoice->billing_address['email'] ?? '',
            'first_name' => $invoice->billing_address['first_name'] ?? '',
            'last_name' => $invoice->billing_address['last_name'] ?? '',
            'company' => $invoice->billing_address['company'] ?? '',
            'address' => $invoice->billing_address['address'] ?? '',
            'address_addition' => $invoice->billing_address['address_addition'] ?? '',
            'city' => $invoice->billing_address['city'] ?? '',
            'postal_code' => $invoice->billing_address['postal_code'] ?? '',
            'country' => $invoice->billing_address['country'] ?? 'DE',
            'invoice_date' => $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '',
            'delivery_date' => $invoice->delivery_date ? $invoice->delivery_date->format('Y-m-d') : '',
            'invoice_number' => $invoice->invoice_number,
            'reference_number' => $invoice->reference_number,
            'due_days' => $invoice->due_days ?? 14,
            'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '',
            'subject' => $invoice->subject,
            'header_text' => $invoice->header_text,
            'footer_text' => $invoice->footer_text,
            'is_e_invoice' => (bool)$invoice->is_e_invoice,
            'items' => collect($invoice->custom_items)->map(fn($item) => [
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] / 100,
                'tax_rate' => $item['tax_rate']
            ])->toArray(),
            'shipping_cost' => $invoice->shipping_cost / 100,
            'discount_amount' => $invoice->discount_amount / 100,
            'volume_discount' => $invoice->volume_discount / 100,
        ];

        $this->isCreatingManual = true;
    }

    public function updatedManualInvoiceDueDays($value)
    {
        $this->calculateDueDate();
    }

    public function updatedManualInvoiceInvoiceDate($value)
    {
        $this->calculateDueDate();
        if (empty($this->manualInvoice['subject'])) {
            $this->manualInvoice['subject'] = 'Rechnung Nr. ' . $this->manualInvoice['invoice_number'];
        }
    }

    public function updatedManualInvoiceInvoiceNumber($value)
    {
        $this->manualInvoice['subject'] = 'Rechnung Nr. ' . $value;
    }

    protected function calculateDueDate()
    {
        if (!empty($this->manualInvoice['invoice_date'])) {
            $date = Carbon::parse($this->manualInvoice['invoice_date']);
            $this->manualInvoice['due_date'] = $date->addDays((int)$this->manualInvoice['due_days'])->format('Y-m-d');
        }
    }

    public function toggleManualCreate()
    {
        if ($this->isCreatingManual) {
            // Wenn wir gerade im Editor sind und "Zurück" klicken:
            // Wir brechen ab, ohne zu speichern, um Validierungsfehler zu vermeiden.
            // (Wenn du Autosave willst, müsste man hier Fehler abfangen, aber Abbruch ist sauberer).
            $this->isCreatingManual = false;
            $this->resetManualInvoice();
        } else {
            // Wenn wir die Liste sehen und "Erstellen" klicken:
            $this->resetManualInvoice();
            $this->isCreatingManual = true;
        }
    }

    protected function resetManualInvoice()
    {
        $this->selectedCustomerId = null;
        $this->manualInvoice = [
            'id' => null,
            'customer_email' => '',
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address' => '',
            'address_addition' => '',
            'city' => '',
            'postal_code' => '',
            'country' => 'DE',
            'invoice_date' => now()->format('Y-m-d'),
            'delivery_date' => now()->format('Y-m-d'),
            'invoice_number' => app(InvoiceService::class)->getNextInvoiceNumber('RE'),
            'reference_number' => '',
            'due_days' => 14,
            'due_date' => '',
            'subject' => '',
            'header_text' => "Sehr geehrte Damen und Herren,\n\nvielen Dank für Ihren Auftrag und das damit verbundene Vertrauen!\nHiermit stelle ich Ihnen die folgenden Leistungen in Rechnung:",
            'footer_text' => "Bitte überweisen Sie den Rechnungsbetrag unter Angabe der Rechnungsnummer auf das unten angegebene Konto.\nDer Rechnungsbetrag ist bis zum [%ZAHLUNGSZIEL%] fällig.\n\nMit freundlichen Grüßen\n[%KONTAKTPERSON%]",
            'is_e_invoice' => false,
            'items' => [],
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
        ];
        $this->manualInvoice['subject'] = 'Rechnung Nr. ' . $this->manualInvoice['invoice_number'];
        $this->calculateDueDate();
        $this->addItem();
    }

    // AutoSave ist hier problematisch beim Tab-Wechsel oder Abbruch.
    // Besser: Explizites Speichern über Buttons.
    // Falls benötigt, hier die ID ignorieren:
    /*
    protected function autoSaveDraft()
    {
        if (!empty($this->manualInvoice['last_name'])) {
            try {
                $this->saveManualInvoice('draft', true);
            } catch (\Exception $e) {
                // Fehler ignorieren beim Autosave
            }
        }
    }
    */

    public function addItem()
    {
        $this->manualInvoice['items'][] = [
            'product_name' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'tax_rate' => (float)shop_setting('default_tax_rate', 19.0)
        ];
    }

    public function removeItem($index)
    {
        unset($this->manualInvoice['items'][$index]);
        $this->manualInvoice['items'] = array_values($this->manualInvoice['items']);
    }

    public function downloadPdf($id)
    {
        $invoice = InvoiceModel::findOrFail($id);
        $safeInvoiceNumber = basename((string) $invoice->invoice_number);

        // Prüfung ob archiviertes PDF existiert (GoBD Konformität)
        if ($safeInvoiceNumber !== '' && Storage::disk('local')->exists("buchhaltung/invoices/{$safeInvoiceNumber}.pdf")) {
            return Storage::disk('local')->download("buchhaltung/invoices/{$safeInvoiceNumber}.pdf");
        }

        $pdf = Pdf::loadView('global.mails.invoice_pdf_template', [
            'invoice' => $invoice,
            'items' => $invoice->order ? $invoice->order->items : collect($invoice->custom_items),
            'isStorno' => $invoice->status === 'cancelled' || $invoice->type === 'cancellation',
        ])->setOptions(['isRemoteEnabled' => false]);


        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "Rechnung_{$safeInvoiceNumber}.pdf");
    }

    public function generateForPaidOrders(InvoiceService $service)
    {
        $orders = OrderOrder::where('payment_status', 'paid')
            ->whereDoesntHave('invoices')
            ->get();

        if ($orders->isEmpty()) {
            session()->flash('info', 'Keine offenen bezahlten Bestellungen ohne Rechnung gefunden.');
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Keine offenen bezahlten Bestellungen ohne Rechnung gefunden.']);
            return;
        }

        $count = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                // 1. Rechnung & PDF GoBD-konform erstellen
                $service->createFromOrder($order);

                // 2. Dokumente & E-Mail an den Kunden versenden (im Hintergrund)
                \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);

                $count++;
            } catch (\Throwable $e) {
                $errors++;
                $this->logInvoiceError('bulk_generate_order', $e instanceof \Exception ? $e : new \Exception($e->getMessage()), [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number ?? null
                ]);
            }
        }

        if ($errors > 0) {
            session()->flash('warning', "$count Rechnungen generiert ($errors fehlgeschlagen).");
            $this->dispatch('notify', ['type' => 'warning', 'message' => "$count Rechnungen generiert ($errors fehlgeschlagen)."]);
        } else {
            session()->flash('success', "$count Rechnungen wurden erfolgreich generiert und E-Mails vorbereitet.");
            $this->dispatch('notify', ['type' => 'success', 'message' => "$count Rechnungen wurden erfolgreich generiert und E-Mails vorbereitet."]);
        }
    }

    public function saveManualInvoice($finalStatus = 'paid', $isAutoSave = false)
    {
        $finalStatus = in_array($finalStatus, ['draft', 'open', 'paid'], true) ? $finalStatus : 'draft';

        // 1. Die ID der aktuellen Rechnung holen (falls vorhanden)
        $currentId = $this->manualInvoice['id'] ?? null;
        if ($currentId) {
            $existingInvoice = InvoiceModel::find($currentId);
            if ($existingInvoice && $existingInvoice->status !== 'draft') {
                abort(403, 'GoBD-Schutz: Abgeschlossene Rechnungen dürfen nicht nachträglich bearbeitet werden.');
            }
        }

        // 2. Validierung
        if ($finalStatus === 'draft') {
            $this->validate([
                'manualInvoice.invoice_number' => 'required|unique:accounting_invoices,invoice_number,' . ($currentId ?: 'NULL') . ',id',
            ]);
        } else {
            // Strenge Validierung für den Abschluss
            $this->validate([
                'manualInvoice.customer_email' => 'required|email',
                'manualInvoice.first_name' => 'required',
                'manualInvoice.last_name' => 'required',
                'manualInvoice.address' => 'required',
                'manualInvoice.postal_code' => 'required',
                'manualInvoice.city' => 'required',
                'manualInvoice.invoice_date' => 'required|date',
                'manualInvoice.delivery_date' => 'required|date',
                'manualInvoice.invoice_number' => 'required|unique:accounting_invoices,invoice_number,' . ($currentId ?: 'NULL') . ',id',
                'manualInvoice.items' => 'required|array|min:1',
                'manualInvoice.items.*.product_name' => 'required',
                'manualInvoice.items.*.unit_price' => 'required|numeric',
                'manualInvoice.items.*.quantity' => 'required|numeric|min:1',
            ], [], [
                'manualInvoice.customer_email' => 'E-Mail',
                'manualInvoice.last_name' => 'Nachname',
                'manualInvoice.items' => 'Positionen',
            ]);
        }

        // Variable für PDF-Fehler (Initialisierung)
        $pdfError = null;

        try {
            DB::transaction(function () use ($finalStatus, $currentId, &$pdfError) {
            $itemsGrossSum = 0; // Summe der Brutto-Werte der Positionen
            $totalTaxAmount = 0; // Gesamte Steuer (Items + Versand)
            $items = [];

            // --- SCHRITT 1: Positionen berechnen (Basis: Eingabe ist NETTO) ---
            $isSmallBusiness = (bool)shop_setting('is_small_business', false);

            foreach ($this->manualInvoice['items'] as $item) {
                // A. Eingabewerte normalisieren
                $inputNetPrice = (float)($item['unit_price'] ?? 0); // Netto Einzelpreis Euro
                $qty = (float)($item['quantity'] ?? 0);
                $taxRate = $isSmallBusiness ? 0.0 : (float)($item['tax_rate'] ?? shop_setting('default_tax_rate', 19.0));

                // B. Berechnungen in Cent durchführen (für Präzision)
                $netUnitCent = (int)round($inputNetPrice * 100);

                // Zeile Netto Gesamt
                $lineNetTotalCent = (int)round($netUnitCent * $qty);

                // Steuer für diese Zeile (Netto * Steuersatz)
                $lineTaxCent = $isSmallBusiness ? 0 : (int)round($lineNetTotalCent * ($taxRate / 100));

                // Zeile Brutto Gesamt
                $lineGrossTotalCent = $lineNetTotalCent + $lineTaxCent;

                // C. Summen aktualisieren
                $itemsGrossSum += $lineGrossTotalCent;
                $totalTaxAmount += $lineTaxCent;

                // D. Item für DB Array vorbereiten
                $calculatedGrossUnitCent = $qty > 0 ? (int)round($lineGrossTotalCent / $qty) : 0;

                $items[] = [
                    'product_name' => $item['product_name'],
                    'quantity' => $qty,
                    'unit_price' => $calculatedGrossUnitCent, // Brutto-Einzelpreis in Cent
                    'total_price' => $lineGrossTotalCent,     // Brutto-Gesamtpreis in Cent
                    'tax_rate' => $taxRate,
                    'configuration' => $item['configuration'] ?? null
                ];
            }

            // --- SCHRITT 2: Versandkosten berechnen ---
            $shippingInputNet = (float)($this->manualInvoice['shipping_cost'] ?? 0);
            $shippingNetCent = (int)round($shippingInputNet * 100);

            // Steuer auf Versand
            $shippingTaxRate = $isSmallBusiness ? 0.0 : ((float)shop_setting('default_tax_rate', 19.0) / 100);
            $shippingTaxCent = $isSmallBusiness ? 0 : (int)round($shippingNetCent * $shippingTaxRate);
            $shippingGrossCent = $shippingNetCent + $shippingTaxCent;

            // Steuer zum Gesamt-Steuertopf hinzufügen
            $totalTaxAmount += $shippingTaxCent;


            // --- SCHRITT 3: Rabatte (Basis: Eingabe ist meist Brutto-Abzug) ---
            // Rabatte werden in der Regel als absoluter Betrag vom Endpreis abgezogen
            $discountInCent = (int)round(($this->manualInvoice['discount_amount'] ?? 0) * 100);
            $volumeDiscountInCent = (int)round(($this->manualInvoice['volume_discount'] ?? 0) * 100);
            $totalDiscountCent = $discountInCent + $volumeDiscountInCent;


            // --- SCHRITT 4: Endsummen ---
            // Total = (Summe Items Brutto + Versand Brutto) - Rabatte
            $grandTotalCent = ($itemsGrossSum + $shippingGrossCent) - $totalDiscountCent;


            // --- SCHRITT 5: Speichern in DB ---
            $invoice = InvoiceModel::updateOrCreate(
                ['id' => $currentId ?? (string) Str::uuid()],
                [
                    'invoice_number' => $this->manualInvoice['invoice_number'],
                    'reference_number' => $this->manualInvoice['reference_number'],
                    'invoice_date' => $this->manualInvoice['invoice_date'] ? Carbon::parse($this->manualInvoice['invoice_date']) : now(),
                    'delivery_date' => $this->manualInvoice['delivery_date'] ? Carbon::parse($this->manualInvoice['delivery_date']) : now(),
                    'due_date' => $this->manualInvoice['due_date'] ? Carbon::parse($this->manualInvoice['due_date']) : null,
                    'due_days' => $this->manualInvoice['due_days'],
                    'subject' => $this->manualInvoice['subject'],
                    'header_text' => $this->manualInvoice['header_text'],
                    'footer_text' => $this->manualInvoice['footer_text'],
                    'is_e_invoice' => (bool)$this->manualInvoice['is_e_invoice'],
                    'type' => 'invoice',
                    'status' => $finalStatus,
                    'customer_id' => $this->selectedCustomerId,
                    'billing_address' => [
                        'first_name' => $this->manualInvoice['first_name'],
                        'last_name' => $this->manualInvoice['last_name'],
                        'company' => $this->manualInvoice['company'],
                        'address' => $this->manualInvoice['address'],
                        'address_addition' => $this->manualInvoice['address_addition'],
                        'postal_code' => $this->manualInvoice['postal_code'],
                        'city' => $this->manualInvoice['city'],
                        'country' => $this->manualInvoice['country'],
                        'email' => $this->manualInvoice['customer_email'],
                    ],
                    // DB Felder (in Cent)
                    'subtotal' => $itemsGrossSum,         // Summe der Positionen (Brutto)
                    'shipping_cost' => $shippingGrossCent,// Versand (Brutto)
                    'discount_amount' => $discountInCent,
                    'volume_discount' => $volumeDiscountInCent,
                    'tax_amount' => $totalTaxAmount,      // Enthaltene MwSt.
                    'total' => $grandTotalCent,           // Zahlbetrag
                    'custom_items' => $items,
                ]
            );

            // ID setzen für weitere Bearbeitung im gleichen Request
            $this->manualInvoice['id'] = $invoice->id;

            // --- SCHRITT 6: Dokumente generieren (Fehlertolerant) ---

            // A) PDF (Archivierung)
            if ($finalStatus === 'paid') {
                try {
                    app(InvoiceService::class)->storePdf($invoice);
                } catch (\Exception $e) {
                    $pdfError = $e->getMessage();
                }
            }

            // B) E-Rechnung (XML)
            if ($this->manualInvoice['is_e_invoice'] && $finalStatus === 'paid') {
                try {
                    $this->processEInvoice($invoice);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('XML Generation Failed: ' . $e->getMessage());
                    // XML Fehler loggen wir nur, unterbrechen aber nicht den User-Flow
                }
            }
        });

        // --- SCHRITT 7: UI Feedback & Redirect ---
        if (!$isAutoSave) {
            if ($finalStatus === 'paid') {
                $this->saveSuccess = true;

                if ($pdfError) {
                    $this->dispatch('notify', ['type' => 'warning', 'message' => 'Rechnung erstellt, aber PDF fehlgeschlagen: ' . $pdfError]);
                } else {
                    $this->dispatch('notify', ['type' => 'success', 'message' => 'Rechnung erfolgreich abgeschlossen.']);
                }

                // Editor schließen und zur Liste zurückkehren
                $this->isCreatingManual = false;
                $this->resetManualInvoice();

            } else {
                // Bei Entwurf bleiben wir im Editor
                $this->draftSuccess = true;
                $this->dispatch('resetDraftSuccess');
                session()->flash('success', 'Entwurf gespeichert.');
            }
        }
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if (app()->environment('testing')) throw $e;
            $this->logInvoiceError('save_manual_invoice', $e, ['invoice_id' => $this->manualInvoice['id'] ?? null]);
            session()->flash('error', 'Fehler beim Speichern der Rechnung: ' . $e->getMessage());
        }
    }

    /**
     * Erstellt die XML-Datei für die E-Rechnung.
     */
    protected function processEInvoice(InvoiceModel $invoice)
    {
        try {
            $eInvoiceService = app(\App\Services\AccountingXmlInvoiceService::class);

            // XML generieren
            $xmlPath = $eInvoiceService->generate($invoice);

            \Illuminate\Support\Facades\Log::info("E-Rechnung (Native) generiert: {$xmlPath}");

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Fehler bei E-Rechnung: " . $e->getMessage());
            // Kein Abbruch für den User!
        }
    }

    /**
     * Download Action für das Frontend
     */
    public function downloadXml($id)
    {
        $invoice = InvoiceModel::findOrFail($id);
        $safeInvoiceNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice->invoice_number);
        $path = 'buchhaltung/invoices/xml/' . $safeInvoiceNumber . '.xml';

        // ... Logik wie gehabt, aber im Catch-Block ggf. auch den neuen Service nutzen:
        if (!Storage::disk('local')->exists($path)) {
            try {
                $service = app(\App\Services\AccountingXmlInvoiceService::class);
                $service->generate($invoice);
                return Storage::disk('local')->download($path);
            } catch (\Exception $e) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'XML Fehler: ' . $e->getMessage()]);
            }
        }

        return Storage::disk('local')->download($path);
    }

    // Methode zum Sortieren hinzufügen:
    public function sortBy($field)
    {
        if (!in_array($field, $this->allowedSortFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function cancelInvoice($id)
    {
        $invoice = InvoiceModel::findOrFail($id);

        if ($invoice->status === 'cancelled') {
            session()->flash('warning', 'Diese Rechnung ist bereits storniert.');
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Diese Rechnung ist bereits storniert.']);
            return;
        }

        try {
            DB::transaction(function () use ($invoice) {
                // 1. Ursprüngliche Rechnung als storniert markieren
                $invoice->update(['status' => 'cancelled']);

                // 2. Stornorechnung (Gutschrift) erstellen
                $cancellationInvoice = $invoice->replicate();
                $cancellationInvoice->invoice_number = 'ST-' . $invoice->invoice_number;
                $cancellationInvoice->type = 'cancellation';
                $cancellationInvoice->status = 'paid';
                $cancellationInvoice->invoice_date = now();
                $cancellationInvoice->parent_id = $invoice->id; // Verknüpfung zur Originalrechnung

                // Beträge ins Negative drehen für eine Gutschrift
                $cancellationInvoice->subtotal = -$invoice->subtotal;
                $cancellationInvoice->tax_amount = -$invoice->tax_amount;
                $cancellationInvoice->shipping_cost = -$invoice->shipping_cost;
                $cancellationInvoice->express_price = -$invoice->express_price;
                $cancellationInvoice->discount_amount = -$invoice->discount_amount;
                $cancellationInvoice->volume_discount = -$invoice->volume_discount;
                $cancellationInvoice->total = -$invoice->total;

                $cancellationInvoice->save();

                // Auch Stornobeleg archivieren
                app(InvoiceService::class)->storePdf($cancellationInvoice);

                // Synchronize order cancellation if order exists and is not yet cancelled
                if ($invoice->order && $invoice->order->status !== 'cancelled') {
                    $invoice->order->handleCancellation('Rechnungsstornierung ' . $invoice->invoice_number);
                    $invoice->order->update(['status' => 'cancelled']);
                }
            });

            session()->flash('success', 'Rechnung wurde erfolgreich storniert und eine Gutschrift erstellt.');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Rechnung wurde erfolgreich storniert.']);
        } catch (\Exception $e) {
            if (app()->environment('testing')) throw $e;
            $this->logInvoiceError('cancel_invoice', $e, ['invoice_id' => $id]);
            session()->flash('error', 'Fehler beim Stornieren der Rechnung: ' . $e->getMessage());
        }
    }

    private function logInvoiceError($action, \Exception $e, $payload = [])
    {
        try {
            SystemLog::create([
                'type' => 'error',
                'ai_agent_id' => null,
                'action_id' => 'admin_invoice_generation',
                'title' => "Invoice Fehler: $action",
                'status' => 'failed',
                'message' => "Fehler bei Invoice Aktion ($action): " . $e->getMessage(),
                'payload' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'payload' => $payload,
                    'trace' => $e->getTraceAsString()
                ]
            ]);
        } catch (\Throwable $logEx) {
            \Illuminate\Support\Facades\Log::error("SystemLog creation failed: " . $logEx->getMessage());
        }
    }

    /**
     * Entwurf unwiderruflich löschen
     */
    public function deleteDraft($id)
    {
        $invoice = InvoiceModel::where('id', $id)->where('status', 'draft')->firstOrFail();
        $invoice->forceDelete();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Entwurf gelöscht']);
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function downloadPdfByFilename($filename)
    {
        $safeName = basename($filename);
        if (Storage::disk('local')->exists("buchhaltung/invoices/{$safeName}")) {
            return Storage::disk('local')->download("buchhaltung/invoices/{$safeName}");
        }
    }

    public function render()
    {
        $query = InvoiceModel::query()->with(['order', 'customer', 'parent']);

        if ($this->search) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function($q) use ($escapedSearch) {
                $q->where('invoice_number', 'like', '%'.$escapedSearch.'%')
                    ->orWhere('billing_address->last_name', 'like', '%'.$escapedSearch.'%');
            });
        }

        if ($this->activeTab === 'e_invoices') {
            $query->where('is_e_invoice', true);
        }

        if ($this->filterType) {
            if ($this->filterType === 'draft') {
                $query->where('status', 'draft');
            } elseif ($this->filterType === 'cancellation') {
                $query->where(fn($q) => $q->where('type', 'cancellation')->orWhere('type', 'credit_note')->orWhere('status', 'cancelled'));
            } else {
                $query->where('type', $this->filterType);
            }
        }

        // SORTIERUNG ANWENDEN (Whitelisted gegen SQL-Injection)
        $safeSortField = in_array($this->sortField, $this->allowedSortFields, true) ? $this->sortField : 'invoice_date';
        $safeSortDir = strtolower((string) $this->sortDirection) === 'asc' ? 'asc' : 'desc';

        if ($safeSortField === 'recipient') {
            // Spezialfall für JSON-Feld (Empfänger-Nachname)
            $query->orderBy('billing_address->last_name', $safeSortDir);
        } else {
            // Standardsortierung für normale Spalten (invoice_number, total, status, invoice_date)
            $query->orderBy($safeSortField, $safeSortDir)
                ->orderBy('created_at', 'desc'); // Zweitrangige Sortierung für absolute Aktualität
        }

        $totalsPreview = [
            'net' => 0,
            'tax' => 0,
            'gross' => 0
        ];

        if($this->isCreatingManual) {
            $isSmallBusiness = (bool)shop_setting('is_small_business', false);
            foreach($this->manualInvoice['items'] as $item) {
                $inputNetPrice = (float)($item['unit_price'] ?? 0);
                $qty = (float)($item['quantity'] ?? 0);
                $taxRate = $isSmallBusiness ? 0.0 : (float)($item['tax_rate'] ?? shop_setting('default_tax_rate', 19.0));

                $lineNet = round($inputNetPrice * $qty, 2);
                $lineTax = $isSmallBusiness ? 0.0 : round($lineNet * ($taxRate / 100), 2);
                $lineGross = $lineNet + $lineTax;

                $totalsPreview['net'] += $lineNet;
                $totalsPreview['tax'] += $lineTax;
                $totalsPreview['gross'] += $lineGross;
            }

            $shippingNet = (float)($this->manualInvoice['shipping_cost'] ?? 0);
            $shippingTaxRate = $isSmallBusiness ? 0.0 : ((float)shop_setting('default_tax_rate', 19.0) / 100);
            $shippingTax = $isSmallBusiness ? 0.0 : round($shippingNet * $shippingTaxRate, 2);
            $shippingGross = $shippingNet + $shippingTax;

            $totalsPreview['net'] += $shippingNet;
            $totalsPreview['tax'] += $shippingTax;
            $totalsPreview['gross'] += $shippingGross;

            $discounts = (float)($this->manualInvoice['discount_amount'] ?? 0) + (float)($this->manualInvoice['volume_discount'] ?? 0);
            $totalsPreview['gross'] = max(0, $totalsPreview['gross'] - $discounts);
            if ($isSmallBusiness) {
                $totalsPreview['net'] = max(0, $totalsPreview['net'] - $discounts);
            }
        }

        $archivedFiles = [];

        if ($this->activeTab === 'archive') {
            $files = Storage::disk('local')->files('buchhaltung/invoices');

            foreach ($files as $file) {
                // Wir holen den Timestamp einmalig in eine Variable
                $timestamp = Storage::disk('local')->lastModified($file);

                $archivedFiles[] = [
                    'name' => basename($file),
                    'size' => round(Storage::disk('local')->size($file) / 1024, 2) . ' KB',
                    'timestamp' => $timestamp, // WICHTIG: Rohdaten für die Sortierung speichern
                    'date' => Carbon::createFromTimestamp($timestamp)->format('d.m.Y H:i')
                ];
            }

            // Hier sortieren wir das Array absteigend (neueste zuerst) basierend auf dem Timestamp
            usort($archivedFiles, function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });
        }

        $invoiceStats = [
            'paid_count' => InvoiceModel::where('type', 'invoice')->where('status', 'paid')->count(),
            'open_count' => InvoiceModel::where('type', 'invoice')->where('status', 'open')->count(),
            'draft_count' => InvoiceModel::where('status', 'draft')->count(),
            'cancelled_count' => InvoiceModel::where(fn($q) => $q->where('status', 'cancelled')->orWhereIn('type', ['cancellation', 'credit_note']))->count(),
            'open_amount' => InvoiceModel::where('type', 'invoice')->where('status', 'open')->sum('total'),
            'paid_amount' => InvoiceModel::where('type', 'invoice')->where('status', 'paid')->sum('total'),
        ];

        return view('livewire.shop.accounting.accounting-invoice', [
            'invoices' => $query->paginate(15),
            'totalsPreview' => $totalsPreview,
            'customers' => Customer::orderBy('last_name')->get(),
            'archivedFiles' => $archivedFiles,
            'invoiceStats' => $invoiceStats
        ]);
    }
}

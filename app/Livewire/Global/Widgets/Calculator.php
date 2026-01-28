<?php

namespace App\Livewire\Global\Widgets;

use App\Mail\CalcCustomer;
use App\Mail\CalcInput;
use App\Models\Customer;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

class Calculator extends Component
{
    use WithFileUploads;

    // --- STEPS ---
    public $step = 0;

    // --- STATE ---
    public $editingIndex = -1;
    public $cartItems = [];
    public $currentConfig = []; // Hält die Daten für den Configurator (Step 2)
    public $currentProduct = null;

    // --- PREISE ---
    public $gesamtKosten = 0;
    public $totalNetto = 0;
    public $totalMwst = 0;
    public $totalBrutto = 0;
    public $shippingCost = 0; // Neu: Versandkosten

    // --- KONFIGURATION VERSAND ---
    // Diese Werte könnten auch aus einer Config-Datei kommen
    const SHIPPING_COST_STANDARD = 4.90; // 4,90 € Standardversand
    const SHIPPING_FREE_THRESHOLD = 100.00; // Versandkostenfrei ab 100 €

    // --- FORMULAR ---
    public $isExpress = false;
    public $deadline = '';
    public $form = [
        'vorname' => '', 'nachname' => '', 'firma' => '',
        'email' => '', 'telefon' => '', 'anmerkung' => ''
    ];

    public $dbProducts = [];

    protected $rules = [
        'form.vorname' => 'required|string|max:100',
        'form.nachname' => 'required|string|max:100',
        'form.email' => 'required|email',
        'cartItems' => 'required|array|min:1',
    ];

    public function mount()
    {
        $this->loadProducts();

        // Session wiederherstellen
        if (session()->has('calc_cart')) {
            $this->cartItems = session('calc_cart');
            $this->calculateTotal();
            if(count($this->cartItems) > 0) $this->step = 1;
        }
        if (session()->has('calc_form')) {
            $this->form = session('calc_form');
        }
    }

    public function loadProducts()
    {
        // Relation 'tierPrices' mitladen
        $products = Product::with('tierPrices')->where('status', 'active')->get();

        $this->dbProducts = $products->map(function($p) {
            $previewPath = $p->preview_image_path;

            if (empty($previewPath) && !empty($p->media_gallery)) {
                foreach($p->media_gallery as $media) {
                    if (($media['type'] ?? '') === 'image') {
                        $previewPath = $media['path'];
                        break;
                    }
                }
            }

            $rawPrice = $p->price / 100; // Preis in Euro (Float)
            $rate = $p->tax_rate ? (float)$p->tax_rate : 19.00;
            $isGross = (bool)$p->tax_included;

            // KORREKTUR: Anzeige-Preis Logik gefixt.
            // Wenn Steuer inkludiert ist, ist der RawPrice der Bruttopreis (39.90).
            // Wenn Steuer exkludiert ist, ist RawPrice der Nettopreis.
            // Wir wollen im Katalog meist den Bruttopreis "ab X €" anzeigen oder den Basispreis.
            if ($isGross) {
                $displayPrice = $rawPrice; // 39.90
            } else {
                // Wenn Netto gespeichert, rechnen wir für die Anzeige ggf. Brutto hoch oder zeigen Netto
                // Hier zeigen wir den Preis so an, wie er gespeichert ist (Basis)
                $displayPrice = $rawPrice;
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'desc' => $p->short_description ?? 'Artikel',
                'price' => $p->price / 100,
                'price_cents' => $p->price,
                'display_price' => $displayPrice, // Korrigierter Wert
                'tax_rate' => $rate,
                'tax_included' => $isGross,

                // Staffelpreise aus Relation holen und als Array speichern
                'tier_pricing' => $p->tierPrices->map(fn($t) => [
                    'qty' => $t->qty,
                    'percent' => $t->percent
                ])->toArray(),

                'image' => !empty($p->media_gallery[0]['path']) ? 'storage/'.$p->media_gallery[0]['path'] : null,
                'preview_image' => $previewPath ? 'storage/'.$previewPath : null,
                'allow_logo' => $p->configurator_settings['allow_logo'] ?? true,
            ];
        })->keyBy('id')->toArray();
    }

    // --- NAVIGATION ---

    public function startCalculator() { $this->step = 1; }

    public function openConfig($productId)
    {
        $this->editingIndex = -1;
        $this->currentProduct = $this->dbProducts[$productId] ?? null;

        if(!$this->currentProduct) return;

        // WICHTIG: Leeres Array übergeben, damit Configurator frisch startet
        $this->currentConfig = [];

        $this->step = 2;
        $this->dispatch('scroll-top');
    }

    public function editItem($index)
    {
        if(!isset($this->cartItems[$index])) return;

        $item = $this->cartItems[$index];
        $this->editingIndex = $index;
        $this->currentProduct = $this->dbProducts[$item['product_id']] ?? null;

        if(!$this->currentProduct) return;

        // Wir übergeben die gespeicherte Konfiguration (Blob) zurück an den Configurator
        $this->currentConfig = $item['configuration'] ?? [];

        // Sicherstellen, dass die Menge korrekt übernommen wird
        $this->currentConfig['qty'] = $item['qty'];

        $this->step = 2;
        $this->dispatch('scroll-top');
    }

    public function cancelConfig() {
        $this->currentConfig = [];
        $this->step = 1;
        $this->dispatch('scroll-top');
    }

    // --- LOGIK VOM CONFIGURATOR EMPFANGEN ---

    #[On('calculator-save')]
    public function saveItemFromConfigurator($data)
    {
        $product = $this->dbProducts[$data['product_id']];

        // Row ID generieren oder behalten
        $rowId = ($this->editingIndex >= 0)
            ? $this->cartItems[$this->editingIndex]['row_id']
            : Str::uuid()->toString();

        $itemData = [
            'row_id' => $rowId,
            'product_id' => $product['id'],
            'name' => $product['name'],
            'image_ref' => $product['image'],

            // Wichtige Felder für die Berechnung/Anzeige extrahieren
            'qty' => $data['qty'],
            'text' => $data['text'] ?? '',

            // Konfiguration speichern
            'configuration' => $data,

            // Hilfsdaten für Vorschau
            'preview_ref' => $product['preview_image'] ?? $product['image']
        ];

        if ($this->editingIndex >= 0) {
            $this->cartItems[$this->editingIndex] = array_merge($this->cartItems[$this->editingIndex], $itemData);
        } else {
            $this->cartItems[] = $itemData;
        }

        $this->step = 1;
        $this->dispatch('scroll-top');
        $this->calculateTotal();
        $this->persist();
    }

    public function removeItem($index) {
        unset($this->cartItems[$index]);
        $this->cartItems = array_values($this->cartItems);
        $this->calculateTotal();
        $this->persist();
    }

    // --- PREISBERECHNUNG ---
    public function calculateTotal()
    {
        // 1. Gesamtmengen pro Produkt ermitteln (für Staffelpreise)
        $quantitiesPerProduct = [];
        foreach ($this->cartItems as $item) {
            $pid = $item['product_id'];
            if (!isset($quantitiesPerProduct[$pid])) $quantitiesPerProduct[$pid] = 0;
            $quantitiesPerProduct[$pid] += $item['qty'];
        }

        $sumNetto = 0;
        $sumMwst = 0;
        $cartSubtotalGross = 0; // Zwischensumme Brutto für Versandfreigrenze

        foreach ($this->cartItems as $index => $item) {
            $product = $this->dbProducts[$item['product_id']] ?? null;
            if(!$product) continue;

            // Staffelpreis in CENTS ermitteln (und runden!)
            $totalQty = $quantitiesPerProduct[$item['product_id']];
            $unitPriceCents = $this->getTierPriceCents($product, $totalQty);

            $rate = $product['tax_rate'];
            $isGross = $product['tax_included'];

            // Zeilensumme berechnen (Integer Math)
            $lineTotalCents = $unitPriceCents * $item['qty'];

            // Steueranteile berechnen
            if ($isGross) {
                // Brutto zu Netto
                $lineGross = $lineTotalCents;
                $lineNet  = $lineGross / (1 + ($rate / 100)); // Hier wird es Float
                $lineTax  = $lineGross - $lineNet;
                $cartSubtotalGross += ($lineGross / 100);
            } else {
                // Netto zu Brutto
                $lineNet = $lineTotalCents;
                $lineTax = $lineNet * ($rate / 100);
                $cartSubtotalGross += (($lineNet + $lineTax) / 100);
            }

            // Werte speichern für Anzeige (in Euro Float umrechnen)
            $this->cartItems[$index]['calculated_single_price'] = $unitPriceCents / 100;
            // Falls Brutto: Zeige Brutto-Summe, sonst Netto-Summe
            $this->cartItems[$index]['calculated_total'] = ($isGross ? $lineTotalCents : round($lineNet)) / 100;

            $sumNetto += $lineNet;
            $sumMwst += $lineTax;
        }

        // --- VERSANDKOSTEN BERECHNUNG ---
        // Prüfen ob Schwellwert erreicht (basierend auf Bruttowarenwert)
        if ($cartSubtotalGross >= self::SHIPPING_FREE_THRESHOLD || count($this->cartItems) === 0) {
            $this->shippingCost = 0;
        } else {
            $this->shippingCost = self::SHIPPING_COST_STANDARD;
        }

        // Versandkosten zur Summe hinzufügen
        if ($this->shippingCost > 0) {
            $shippingCents = $this->shippingCost * 100;
            // Versand hat i.d.R. 19% MwSt
            $shippingNet = $shippingCents / 1.19;
            $shippingTax = $shippingCents - $shippingNet;

            $sumNetto += $shippingNet;
            $sumMwst += $shippingTax;
        }

        // Express Zuschlag (25,00 € Netto)
        if ($this->isExpress && count($this->cartItems) > 0) {
            $expressNetto = 2500; // Cents
            $expressTax = $expressNetto * 0.19; // Annahme 19%
            $sumNetto += $expressNetto;
            $sumMwst += $expressTax;
        }

        // Endergebnisse runden und in Euro umwandeln
        $this->totalNetto = round($sumNetto) / 100;
        $this->totalMwst = round($sumMwst) / 100;
        $this->totalBrutto = round($sumNetto + $sumMwst) / 100;
        $this->gesamtKosten = $this->totalBrutto; // Wir zeigen dem Kunden die Bruttosumme als Gesamtkosten
    }

    /**
     * Berechnet den Einzelpreis in Cents basierend auf Staffelpreisen.
     * WICHTIG: Rundet den rabattierten Einzelpreis kaufmännisch.
     */
    private function getTierPriceCents($product, $qty)
    {
        // Wir nehmen den Integer Preis (Cents)
        $basePrice = $product['price_cents'];
        $tiers = $product['tier_pricing'] ?? [];

        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    // Hier passiert die Magie: Runden auf ganzen Cent VOR der Multiplikation mit Menge
                    return (int) round($basePrice - $discount);
                }
            }
        }
        return $basePrice;
    }

    // --- ABSCHLUSS & PDF ---

    public function updatedForm() { $this->persist(); }
    public function updatedIsExpress() { $this->calculateTotal(); $this->persist(); }

    public function goNext() {
        if (count($this->cartItems) == 0) {
            $this->addError('cart', 'Bitte wählen Sie Produkte aus.');
            return;
        }
        $this->step = 3;
        $this->persist();
        $this->dispatch('scroll-top');
    }

    public function goBack() { $this->step = 1; $this->dispatch('scroll-top'); }

    public function submit()
    {
        $this->validate();
        $this->calculateTotal();

        // 1. Prüfen, ob Kunde existiert (anhand E-Mail)
        $existingCustomer = Customer::where('email', $this->form['email'])->first();

        // FIX für SQL Error: Deadline muss NULL sein, wenn leer oder nicht Express
        $cleanDeadline = ($this->isExpress && !empty($this->deadline)) ? $this->deadline : null;

        // 2. Quote Request erstellen
        $quote = QuoteRequest::create([
            'quote_number' => 'AN-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'email' => $this->form['email'],
            'first_name' => $this->form['vorname'],
            'last_name' => $this->form['nachname'],
            'company' => $this->form['firma'] ?? null,
            'phone' => $this->form['telefon'] ?? null,
            'customer_id' => $existingCustomer ? $existingCustomer->id : null,
            'status' => 'open',

            // FIX: Explizites Runden vor dem Casten zu Int, um "Truncated integer" Fehler zu vermeiden
            'net_total' => (int) round($this->totalNetto * 100),
            'tax_total' => (int) round($this->totalMwst * 100),
            'gross_total' => (int) round($this->totalBrutto * 100),

            'is_express' => $this->isExpress,
            'deadline' => $cleanDeadline,
            'admin_notes' => $this->form['anmerkung'] ?? null,
        ]);

        // 3. Items speichern
        foreach($this->cartItems as $item) {
            $conf = $item['configuration'] ?? [];

            QuoteRequestItem::create([
                'quote_request_id' => $quote->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'quantity' => $item['qty'],
                'unit_price' => (int) round($item['calculated_single_price'] * 100),
                'total_price' => (int) round($item['calculated_total'] * 100),
                'configuration' => $conf,
            ]);
        }

        // Daten für PDF aufbereiten
        $finalItems = [];
        foreach($this->cartItems as $item) {
            $conf = $item['configuration'] ?? [];

            $finalItems[] = [
                'name' => $item['name'],
                'quantity' => $item['qty'],
                'single_price' => number_format($item['calculated_single_price'], 2, ',', '.'),
                'total_price' => number_format($item['calculated_total'], 2, ',', '.'),
                'config' => [
                    'text' => $conf['text'] ?? '',
                    'notes' => $conf['notes'] ?? '',
                    'font' => $conf['font'] ?? '',
                    'align' => $conf['align'] ?? '',
                    'text_pos_label' => $this->getPositionLabel($conf['text_x'] ?? 50, $conf['text_y'] ?? 50),
                    'logo_pos_label' => $this->getPositionLabel($conf['logo_x'] ?? 50, $conf['logo_y'] ?? 30),
                    'logo_storage_path' => $conf['logo_path'] ?? null,
                    'product_image_path' => $item['preview_ref'] ?? null
                ]
            ];
        }

        // Versandkosten Zeile
        if ($this->shippingCost > 0) {
            $finalItems[] = [
                'name' => 'Versand & Verpackung',
                'quantity' => 1,
                'single_price' => number_format($this->shippingCost, 2, ',', '.'),
                'total_price' => number_format($this->shippingCost, 2, ',', '.'),
                'config' => []
            ];
        }

        // Express Zeile
        if ($this->isExpress) {
            $finalItems[] = [
                'name' => 'Express-Service',
                'quantity' => 1,
                'single_price' => '25,00',
                'total_price' => '25,00',
                'config' => []
            ];
        }

        $data = [
            'contact' => $this->form,
            'items' => $finalItems,
            'total_netto' => number_format($this->totalNetto, 2, ',', '.'),
            'total_vat' => number_format($this->totalMwst, 2, ',', '.'),
            'total_gross' => number_format($this->totalBrutto, 2, ',', '.'),
            'express' => $this->isExpress,
            'deadline' => $cleanDeadline,
            'quote_number' => $quote->quote_number,
            'quote_token' => $quote->token,
            'quote_expiry' => $quote->expires_at->format('d.m.Y'),
        ];

        // PDF Generierung
        $pdf = Pdf::loadView('global.mails.calculation_pdf', ['data' => $data]);
        $filename = 'Angebot_' . Str::slug($this->form['firma'] ?: $this->form['nachname']) . '_' . time() . '.pdf';
        $path = storage_path('app/public/tmp/' . $filename);

        if (!file_exists(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, $pdf->output());

        // Mails senden
        try {
            Mail::to($this->form['email'])->send(new CalcCustomer($data, $path));
            Mail::to('kontakt@mein-seelenfunke.de')->send(new CalcInput($data, $path));
        } catch (\Exception $e) {
            \Log::error('Calculator Mail Error: ' . $e->getMessage());
        }

        @unlink($path);

        session()->forget(['calc_cart', 'calc_form']);
        $this->cartItems = [];
        $this->gesamtKosten = 0;
        $this->step = 4;
        $this->dispatch('scroll-top');
    }

    public function restartCalculator()
    {
        $this->reset(['cartItems', 'form', 'isExpress', 'deadline', 'step', 'gesamtKosten', 'shippingCost']);
        session()->forget(['calc_cart', 'calc_form']);
        $this->step = 1;
    }

    public function persist()
    {
        session()->put('calc_cart', $this->cartItems);
        session()->put('calc_form', $this->form);
    }

    private function getPositionLabel($x, $y) {
        $x = (float)$x; $y = (float)$y;
        $h = ($x < 35) ? 'Links' : (($x > 65) ? 'Rechts' : 'Mitte');
        $v = ($y < 35) ? 'Oben' : (($y > 65) ? 'Unten' : 'Mitte');

        if ($h === 'Mitte' && $v === 'Mitte') return 'Zentriert';
        if ($h === 'Mitte') return $v . ' Zentriert';
        if ($v === 'Mitte') return 'Mitte ' . $h;

        return "$v $h";
    }

    public function render()
    {
        return view('livewire.widgets.calculator', ['dbProducts' => $this->dbProducts]);
    }
}

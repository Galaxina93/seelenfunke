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
        // Wir laden nur die Basis-Daten. Die Details holt sich der Configurator selbst.
        $products = Product::where('status', 'active')->get();

        $this->dbProducts = $products->map(function($p) {
            $previewPath = $p->preview_image_path;

            // Fallback auf Galeriebild
            if (empty($previewPath) && !empty($p->media_gallery)) {
                foreach($p->media_gallery as $media) {
                    if (($media['type'] ?? '') === 'image') {
                        $previewPath = $media['path'];
                        break;
                    }
                }
            }

            // Preise vorbereiten
            $rawPrice = $p->price / 100;
            $rate = $p->tax_rate ? (float)$p->tax_rate : 19.00;
            $isGross = (bool)$p->tax_included;

            // Anzeigepreis immer Netto für B2B (oder je nach Logik)
            $displayNetto = $isGross ? $rawPrice / (1 + ($rate / 100)) : $rawPrice;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'desc' => $p->short_description ?? 'Artikel',
                'price' => $p->price / 100, // Basispreis
                'price_cents' => $p->price,
                'display_price' => $displayNetto,
                'tax_rate' => $rate,
                'tax_included' => $isGross,
                'tier_pricing' => $p->tier_pricing,
                'image' => !empty($p->media_gallery[0]['path']) ? 'storage/'.$p->media_gallery[0]['path'] : null,
                'preview_image' => $previewPath ? 'storage/'.$previewPath : null,

                // Configurator Settings (Logo erlaubt?) für die Anzeige im Katalog (Step 1)
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

        // Sicherstellen, dass die Menge korrekt übernommen wird, falls sie im Root liegt
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

        // Wir speichern das komplette $data Array vom Configurator im Key 'configuration'.
        // So ist der Calculator unabhängig von Feldern wie text_x, logo_y usw.
        $itemData = [
            'row_id' => $rowId,
            'product_id' => $product['id'],
            'name' => $product['name'],
            'image_ref' => $product['image'],

            // Wichtige Felder für die Berechnung/Anzeige extrahieren
            'qty' => $data['qty'],
            'text' => $data['text'] ?? '', // Für Vorschau im Warenkorb

            // Das Herzstück: Die gesamte Konfiguration speichern wir als Blob
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
            } else {
                // Netto zu Brutto
                $lineNet = $lineTotalCents;
                $lineTax = $lineNet * ($rate / 100);
            }

            // Werte speichern für Anzeige (in Euro Float umrechnen)
            $this->cartItems[$index]['calculated_single_price'] = $unitPriceCents / 100;
            // Falls Brutto: Zeige Brutto-Summe, sonst Netto-Summe
            $displaySumCents = $isGross ? $lineTotalCents : $lineNet; // (Anmerkung: Bei Netto hier leichte Inkonsistenz möglich, da $lineNet Float ist, aber für Anzeige OK)
            $this->cartItems[$index]['calculated_total'] = ($isGross ? $lineTotalCents : round($lineNet)) / 100;

            $sumNetto += $lineNet;
            $sumMwst += $lineTax;
        }

        // Express Zuschlag (25,00 € Netto)
        if ($this->isExpress && ($sumNetto > 0)) {
            $expressNetto = 2500; // Cents
            $expressTax = $expressNetto * 0.19; // Annahme 19%
            $sumNetto += $expressNetto;
            $sumMwst += $expressTax;
        }

        // Endergebnisse runden und in Euro umwandeln
        $this->totalNetto = round($sumNetto) / 100;
        $this->totalMwst = round($sumMwst) / 100;
        $this->totalBrutto = round($sumNetto + $sumMwst) / 100;
        $this->gesamtKosten = $this->totalNetto; // Je nach Wunsch Netto oder Brutto
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

        // 2. Quote Request erstellen
        // Token & Ablaufdatum werden automatisch durch das Model (booted Methode) generiert
        $quote = QuoteRequest::create([
            'quote_number' => 'AN-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'email' => $this->form['email'],
            'first_name' => $this->form['vorname'],
            'last_name' => $this->form['nachname'],
            'company' => $this->form['firma'] ?? null,
            'phone' => $this->form['telefon'] ?? null,
            'customer_id' => $existingCustomer ? $existingCustomer->id : null,
            'status' => 'open',

            'net_total' => (int)($this->totalNetto * 100),
            'tax_total' => (int)($this->totalMwst * 100),
            'gross_total' => (int)($this->totalBrutto * 100),

            'is_express' => $this->isExpress,
            'deadline' => $this->isExpress ? $this->deadline : null,
            'admin_notes' => $this->form['anmerkung'] ?? null,
        ]);

        // 3. Items speichern & Dateien verschieben
        foreach($this->cartItems as $item) {
            $conf = $item['configuration'] ?? [];

            // OPTIONAL: Dateien von temp nach permanent verschieben, damit sie nicht gelöscht werden
            // Hier vereinfacht: Wir gehen davon aus, dass sie in 'public/cart-uploads' liegen und bleiben.

            QuoteRequestItem::create([
                'quote_request_id' => $quote->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'quantity' => $item['qty'],
                // Speichern in Cent
                'unit_price' => (int)($item['calculated_single_price'] * 100),
                'total_price' => (int)($item['calculated_total'] * 100),
                'configuration' => $conf,
            ]);
        }

        // Daten für PDF aufbereiten
        $finalItems = [];
        foreach($this->cartItems as $item) {
            // Konfiguration extrahieren (Fallback falls leer)
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

                    // Wir nutzen nur noch die Labels, keine Koordinaten im PDF
                    'text_pos_label' => $this->getPositionLabel($conf['text_x'] ?? 50, $conf['text_y'] ?? 50),
                    'logo_pos_label' => $this->getPositionLabel($conf['logo_x'] ?? 50, $conf['logo_y'] ?? 30),

                    'logo_storage_path' => $conf['logo_path'] ?? null,
                    'product_image_path' => $item['preview_ref'] ?? null
                ]
            ];
        }

        if ($this->isExpress) {
            $finalItems[] = [
                'name' => 'Express-Service',
                'quantity' => 1,
                'single_price' => '25,00',
                'total_price' => '25,00',
                'config' => []
            ];
        }

        // 4. Daten für Mail & PDF zusammenstellen (INKLUSIVE TOKEN)
        $data = [
            'contact' => $this->form,
            'items' => $finalItems,
            'total_netto' => number_format($this->totalNetto, 2, ',', '.'),
            'total_vat' => number_format($this->totalMwst, 2, ',', '.'),
            'total_gross' => number_format($this->totalBrutto, 2, ',', '.'),
            'express' => $this->isExpress,
            'deadline' => $this->deadline,
            // NEU: Token & Ablaufdatum für die Mail
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
            // Fehler loggen, aber User nicht verwirren
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
        $this->reset(['cartItems', 'form', 'isExpress', 'deadline', 'step', 'gesamtKosten']);
        session()->forget(['calc_cart', 'calc_form']);
        $this->step = 1;
    }

    public function persist()
    {
        session()->put('calc_cart', $this->cartItems);
        session()->put('calc_form', $this->form);
    }

    // Helper für PDF-Ausgabe (Mitte Links, Oben Rechts etc.)
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

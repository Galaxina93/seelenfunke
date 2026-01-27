<?php

namespace App\Livewire\Global\Widgets;

use App\Mail\CalcCustomer;
use App\Mail\CalcInput;
use App\Models\Product;
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
        // 1. Mengen pro Produkt ermitteln (für Staffelpreise über alle Positionen hinweg)
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

            // Staffelpreis ermitteln
            $totalQty = $quantitiesPerProduct[$item['product_id']];
            $unitPriceDb = $this->getTierPrice($product, $totalQty);

            $rate = $product['tax_rate'];
            $isGross = $product['tax_included'];

            // Zeilensumme berechnen
            $lineTotalDb = $unitPriceDb * $item['qty'];

            if ($isGross) {
                // Wenn Preise Brutto sind, müssen wir Netto berechnen für die interne Logik
                $lineGross = $lineTotalDb;
                $lineNet  = $lineGross / (1 + ($rate / 100));
                $lineTax  = $lineGross - $lineNet;
            } else {
                // Wenn Preise Netto sind (Standard B2B)
                $lineNet = $lineTotalDb;
                $lineTax = $lineNet * ($rate / 100);
            }

            // Werte speichern für Anzeige im Frontend
            $this->cartItems[$index]['calculated_single_price'] = $unitPriceDb;
            $this->cartItems[$index]['calculated_total'] = ($isGross ? $lineTotalDb : $lineNet); // Anzeige je nach Shop-Einstellung

            $sumNetto += $lineNet;
            $sumMwst += $lineTax;
        }

        // Express Zuschlag
        if ($this->isExpress && ($sumNetto > 0)) {
            $expressNetto = 25.00;
            $expressTax = $expressNetto * 0.19; // Annahme 19% auf Service
            $sumNetto += $expressNetto;
            $sumMwst += $expressTax;
        }

        $this->totalNetto = $sumNetto;
        $this->totalMwst = $sumMwst;
        $this->totalBrutto = $sumNetto + $sumMwst;
        $this->gesamtKosten = $this->totalNetto; // oder Brutto, je nach Anzeige-Wunsch
    }

    private function getTierPrice($product, $qty)
    {
        $basePrice = $product['price']; // Ist in dbProducts schon / 100 gerechnet? Ja, siehe loadProducts
        $tiers = $product['tier_pricing'] ?? [];

        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']); // Absteigend sortieren
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    return $basePrice - $discount;
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

        $data = [
            'contact' => $this->form,
            'items' => $finalItems,
            'total_netto' => number_format($this->totalNetto, 2, ',', '.'),
            'total_vat' => number_format($this->totalMwst, 2, ',', '.'),
            'total_gross' => number_format($this->totalBrutto, 2, ',', '.'),
            'express' => $this->isExpress,
            'deadline' => $this->deadline,
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

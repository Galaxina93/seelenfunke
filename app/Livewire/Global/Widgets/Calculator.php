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

class Calculator extends Component
{
    use WithFileUploads;

    public $step = 0;
    public $editingIndex = -1;

    public $cartItems = [];
    public $gesamtKosten = 0;

    public $totalNetto = 0;
    public $totalMwst = 0;
    public $totalBrutto = 0;

    public $fonts = [
        'Arial' => 'Arial, sans-serif',
        'Times New Roman' => 'Times New Roman, serif',
        'Verdana' => 'Verdana, sans-serif',
        'Courier New' => 'Courier New, monospace',
        'Georgia' => 'Georgia, serif',
        'Great Vibes' => '"Great Vibes", cursive',
    ];

    public $alignmentOptions = [
        'left' => 'Links', 'center_h' => 'Zentriert', 'right' => 'Rechts',
        'top' => 'Oben', 'center_v' => 'Mittig', 'bottom' => 'Unten', 'center' => 'Mitte'
    ];

    public $positions = [
        'top-left' => 'Oben Links', 'top-center' => 'Oben Mittig', 'top-right' => 'Oben Rechts',
        'center-left' => 'Mitte Links', 'center-center' => 'Mitte Zentriert', 'center-right' => 'Mitte Rechts',
        'bottom-left' => 'Unten Links', 'bottom-center' => 'Unten Mittig', 'bottom-right' => 'Unten Rechts',
    ];

    public $currentConfig = [
        'product_id' => '', 'qty' => 1, 'engraving_text' => '', 'notes' => '',
        'engraving_font' => 'Arial', 'engraving_align' => 'center_h',
        'text_x' => 50.0, 'text_y' => 50.0, 'text_size' => 1.0,
        'logo_x' => 50.0, 'logo_y' => 30.0, 'logo_size' => 130,
        'image_pos' => 'top-center',
        'temp_image' => null, 'existing_image_path' => null
    ];

    public $currentProduct = null;
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
        $products = Product::where('status', 'active')->get();

        $this->dbProducts = $products->map(function($p) {
            $previewPath = $p->preview_image_path;
            if (empty($previewPath) && !empty($p->media_gallery)) {
                foreach($p->media_gallery as $media) {
                    if ($media['type'] === 'image') {
                        $previewPath = $media['path'];
                        break;
                    }
                }
            }

            $config = is_string($p->configurator_settings) ? json_decode($p->configurator_settings, true) : ($p->configurator_settings ?? []);

            $rawPrice = $p->price / 100;
            $rate = $p->tax_rate ? (float)$p->tax_rate : 19.00;
            $isGross = (bool)$p->tax_included;

            $displayNetto = $isGross ? $rawPrice / (1 + ($rate / 100)) : $rawPrice;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'desc' => $p->short_description ?? 'Hochwertiges Produkt',
                'price' => $p->price / 100,
                'display_price' => $displayNetto,
                'staffel' => !empty($p->tier_pricing),
                'tier_pricing' => $p->tier_pricing,
                'image' => !empty($p->media_gallery[0]['path']) ? 'storage/'.$p->media_gallery[0]['path'] : null,
                'preview_image' => $previewPath ? 'storage/'.$previewPath : null,
                'allow_text_pos' => $config['allow_text_pos'] ?? true,
                'allow_logo' => $config['allow_logo'] ?? true,
                'allow_logo_pos' => $config['allow_logo_pos'] ?? true,
                'default_text_pos' => $config['default_text_pos'] ?? 'center-center',
                'default_logo_pos' => $config['default_logo_pos'] ?? 'top-center',
                'default_text_align' => $config['default_text_align'] ?? 'center_h',
                'allowed_pos' => $config['allowed_pos'] ?? array_keys($this->positions),
                'allowed_align' => $config['allowed_align'] ?? array_keys($this->alignmentOptions),
                'area_top' => $config['area_top'] ?? 10,
                'area_left' => $config['area_left'] ?? 10,
                'area_width' => $config['area_width'] ?? 80,
                'area_height' => $config['area_height'] ?? 80,
                'tax_included' => (bool) $p->tax_included,
                'tax_rate' => $p->tax_rate ? (float) $p->tax_rate : 19.00,
            ];
        })->keyBy('id')->toArray();
    }

    public function persist()
    {
        session()->put('calc_cart', $this->cartItems);
        session()->put('calc_form', $this->form);
    }

    public function startCalculator() { $this->step = 1; }

    public function restartCalculator()
    {
        $this->reset(['cartItems', 'form', 'isExpress', 'deadline', 'step', 'gesamtKosten']);
        session()->forget(['calc_cart', 'calc_form']);
        $this->step = 1;
    }

    public function openConfig($productId)
    {
        $this->editingIndex = -1;
        $this->currentProduct = $this->dbProducts[$productId] ?? null;
        if(!$this->currentProduct) return;
        $this->resetConfig($productId);
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

        $this->currentConfig = [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'engraving_text' => $item['text'],
            'notes' => $item['notes'] ?? '',
            'engraving_font' => $item['font'] ?? 'Arial',
            'engraving_align' => $item['align'] ?? 'center_h',
            'text_x' => $item['text_x'] ?? 50.0,
            'text_y' => $item['text_y'] ?? 50.0,
            'text_size' => $item['text_size'] ?? 1.0,
            'logo_x' => $item['logo_x'] ?? 50.0,
            'logo_y' => $item['logo_y'] ?? 30.0,
            'logo_size' => $item['logo_size'] ?? 130,
            'temp_image' => null,
            'existing_image_path' => $item['logo_path'],
        ];
        $this->step = 2;
        $this->dispatch('scroll-top');
    }

    public function cancelConfig() {
        $this->currentConfig['temp_image'] = null;
        $this->step = 1;
        $this->dispatch('scroll-top');
    }

    private function resetConfig($productId)
    {
        $product = $this->dbProducts[$productId];
        $this->currentConfig = [
            'product_id' => $productId,
            'qty' => 1,
            'engraving_text' => '',
            'notes' => '',
            'engraving_font' => 'Arial',
            'engraving_align' => $product['default_text_align'] ?? 'center_h',
            'text_x' => 50.0, 'text_y' => 50.0, 'text_size' => 1.0,
            'logo_x' => 50.0, 'logo_y' => 30.0, 'logo_size' => 130,
            'temp_image' => null,
            'existing_image_path' => null
        ];
    }

    public function saveItem()
    {
        $this->validate([
            'currentConfig.qty' => 'required|integer|min:1',
            'currentConfig.engraving_text' => 'nullable|string|max:100',
            'currentConfig.temp_image' => 'nullable|image|max:10240',
        ]);

        $finalImagePath = $this->currentConfig['existing_image_path'];
        if ($this->currentConfig['temp_image']) {
            $finalImagePath = $this->currentConfig['temp_image']->store('private_uploads', 'local');
        }

        $product = $this->dbProducts[$this->currentConfig['product_id']];

        $itemData = [
            'row_id' => ($this->editingIndex >= 0) ? $this->cartItems[$this->editingIndex]['row_id'] : Str::uuid()->toString(),
            'product_id' => $product['id'],
            'name' => $product['name'],
            'image_ref' => $product['image'],
            'qty' => $this->currentConfig['qty'],
            'text' => $this->currentConfig['engraving_text'],
            'notes' => $this->currentConfig['notes'],
            'font' => $this->currentConfig['engraving_font'],
            'align' => $this->currentConfig['engraving_align'],
            'text_x' => $this->currentConfig['text_x'],
            'text_y' => $this->currentConfig['text_y'],
            'text_size' => $this->currentConfig['text_size'],
            'logo_x' => $this->currentConfig['logo_x'],
            'logo_y' => $this->currentConfig['logo_y'],
            'logo_size' => $this->currentConfig['logo_size'],
            'logo_path' => $finalImagePath,
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
        $this->currentConfig['temp_image'] = null;
    }

    public function removeItem($index) {
        unset($this->cartItems[$index]);
        $this->cartItems = array_values($this->cartItems);
        $this->calculateTotal();
        $this->persist();
    }

    public function calculateTotal()
    {
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

            $totalQty = $quantitiesPerProduct[$item['product_id']];
            $unitPriceDb = $this->getTierPrice($product, $totalQty);
            $rate = $product['tax_rate'];
            $isGross = $product['tax_included'];
            $lineTotalDb = $unitPriceDb * $item['qty'];

            if ($isGross) {
                $lineGross = $lineTotalDb;
                $lineNet  = $lineGross / (1 + ($rate / 100));
                $lineTax  = $lineGross - $lineNet;
            } else {
                $lineNet = $lineTotalDb;
                $lineTax = $lineNet * ($rate / 100);
            }

            $this->cartItems[$index]['calculated_single_price'] = $unitPriceDb;
            $this->cartItems[$index]['calculated_line_net'] = $lineNet;
            $this->cartItems[$index]['calculated_line_tax'] = $lineTax;
            $this->cartItems[$index]['calculated_total'] = $lineNet;

            $sumNetto += $lineNet;
            $sumMwst += $lineTax;
        }

        if ($this->isExpress && ($sumNetto > 0)) {
            $expressNetto = 25.00;
            $expressTax = $expressNetto * 0.19;
            $sumNetto += $expressNetto;
            $sumMwst += $expressTax;
        }

        $this->totalNetto = $sumNetto;
        $this->totalMwst = $sumMwst;
        $this->totalBrutto = $sumNetto + $sumMwst;
        $this->gesamtKosten = $this->totalNetto;
    }

    private function getTierPrice($product, $qty)
    {
        $basePrice = $product['price'];
        $tiers = $product['tier_pricing'] ?? [];
        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    return $basePrice - $discount;
                }
            }
        }
        return $basePrice;
    }

    // NEU: Hilfsfunktion zur Umwandlung von Koordinaten in Text
    private function getPositionLabel($x, $y) {
        $x = (float)$x;
        $y = (float)$y;

        // Horizontale Bestimmung
        $h = 'Mitte'; // Default 35-65%
        if ($x < 35) $h = 'Links';
        if ($x > 65) $h = 'Rechts';

        // Vertikale Bestimmung
        $v = 'Mitte'; // Default 35-65%
        if ($y < 35) $v = 'Oben';
        if ($y > 65) $v = 'Unten';

        if ($h === 'Mitte' && $v === 'Mitte') return 'Zentriert';
        if ($h === 'Mitte') return $v . ' Zentriert'; // z.B. "Oben Zentriert"
        if ($v === 'Mitte') return 'Mitte ' . $h;     // z.B. "Mitte Rechts"

        return "$v $h"; // z.B. "Oben Links"
    }

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

        $finalItems = [];
        foreach($this->cartItems as $item) {
            $logoStoragePath = $item['logo_path'];

            // Wir berechnen hier den lesbaren Positionstext
            $textPosLabel = isset($item['text_x'])
                ? $this->getPositionLabel($item['text_x'], $item['text_y'])
                : 'Standard';

            $logoPosLabel = isset($item['logo_x'])
                ? $this->getPositionLabel($item['logo_x'], $item['logo_y'])
                : 'Standard';

            $finalItems[] = [
                'name' => $item['name'],
                'quantity' => $item['qty'],
                'single_price' => number_format($item['calculated_single_price'], 2, ',', '.'),
                'total_price' => number_format($item['calculated_total'], 2, ',', '.'),
                'config' => [
                    'text' => $item['text'],
                    'notes' => $item['notes'] ?? '',
                    'font' => $item['font'],
                    'align' => $this->alignmentOptions[$item['align']] ?? $item['align'],

                    'text_x' => $item['text_x'] ?? 50.0,
                    'text_y' => $item['text_y'] ?? 50.0,
                    'text_size' => $item['text_size'] ?? 1.0,
                    // Hier übergeben wir den lesbaren Text für das PDF
                    'text_pos_label' => $textPosLabel,

                    'logo_x' => $item['logo_x'] ?? 50.0,
                    'logo_y' => $item['logo_y'] ?? 30.0,
                    'logo_size' => $item['logo_size'] ?? 130,
                    'logo_pos_label' => $logoPosLabel,

                    'logo_url' => null,
                    'logo_storage_path' => $logoStoragePath,
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

        $netto = $this->totalNetto;
        $mwst = $this->totalMwst;
        $brutto = $this->totalBrutto;

        $data = [
            'contact' => $this->form,
            'items' => $finalItems,
            'total' => number_format($netto, 2, ',', '.'),
            'total_netto' => number_format($netto, 2, ',', '.'),
            'total_vat' => number_format($mwst, 2, ',', '.'),
            'total_gross' => number_format($brutto, 2, ',', '.'),
            'express' => $this->isExpress,
            'deadline' => $this->deadline,
        ];

        $pdf = Pdf::loadView('global.mails.calculation_pdf', ['data' => $data]);
        $filename = 'Angebot_' . Str::slug($this->form['firma'] ?: $this->form['nachname']) . '_' . time() . '.pdf';
        $path = storage_path('app/public/tmp/' . $filename);

        if (!file_exists(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, $pdf->output());

        Mail::to($this->form['email'])->send(new CalcCustomer($data, $path));
        Mail::to('kontakt@mein-seelenfunke.de')->send(new CalcInput($data, $path));

        @unlink($path);

        session()->forget(['calc_cart', 'calc_form']);
        $this->cartItems = [];
        $this->gesamtKosten = 0;
        $this->step = 4;
        $this->dispatch('scroll-top');
    }

    public function render()
    {
        return view('livewire.widgets.calculator', ['dbProducts' => $this->dbProducts]);
    }
}

@props([
    'model' => null,   // Für Order oder Quote Objekte
    'totals' => null,  // Für Cart/Checkout Arrays
    'country' => null, // Optional für Checkout (Versand DE/AT...)
    'showTitle' => true, // Ob die Überschrift "Abrechnung/Kostenübersicht" angezeigt werden soll
    'design' => 'light' // 'light' oder 'dark'
])

@php
    // --- 1. THEME STEUERUNG ---
    $isDark = $design === 'dark';

    // Base Container
    $containerClass = $isDark
        ? 'bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-2xl h-full'
        : 'bg-gray-50 border border-gray-200 rounded-2xl p-6 sm:p-8 shadow-sm h-full';

    // Text & Border Colors
    $titleClass = $isDark ? 'text-white border-gray-800' : 'text-gray-900 border-gray-200';
    $iconClass = $isDark ? 'text-primary' : 'text-gray-400';
    $labelClass = $isDark ? 'text-gray-400' : 'text-gray-600';
    $valueClass = $isDark ? 'text-white font-bold' : 'text-gray-900';
    $dividerClass = $isDark ? 'border-gray-800' : 'border-gray-200';

    // Discount Box
    $discountBoxClass = $isDark
        ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-3 rounded-xl shadow-inner -mx-3'
        : 'bg-green-50 border border-green-200 text-green-700 font-bold p-3 rounded-xl shadow-sm -mx-3';
    $discountIconClass = $isDark ? 'text-emerald-400' : 'text-green-700';

    // Express Box
    $expressBoxClass = $isDark
        ? 'bg-red-500/10 border border-red-500/30 text-red-400 p-3 rounded-xl shadow-inner -mx-3'
        : 'bg-red-50 border border-red-200 text-red-700 font-bold p-3 rounded-xl shadow-sm -mx-3';
    $expressIconClass = $isDark ? 'text-red-400' : 'text-red-500';

    // Free Shipping Badge
    $freeShipBadgeClass = $isDark
        ? 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 px-2 py-0.5 rounded-md -mx-2 uppercase tracking-widest shadow-inner'
        : 'bg-green-50 border border-green-200 text-green-700 px-2 py-0.5 rounded-md -mx-2 font-bold uppercase tracking-widest shadow-sm';

    $freeShipTextClass = $isDark ? 'text-emerald-400' : 'text-green-600';

    // Totals Section
    $totalLabelClass = $isDark ? 'text-white' : 'text-gray-900';
    $totalValueClass = $isDark ? 'text-primary drop-shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-primary';
    $totalDividerClass = $isDark ? 'border-gray-800' : 'border-gray-300';
    $taxTextClass = $isDark ? 'text-gray-500' : 'text-gray-500';


    // --- 2. EINSTELLUNGEN LADEN ---
    $isSmallBusiness = (bool)shop_setting('is_small_business', false);
    $taxRate = (float)shop_setting('default_tax_rate', 19.0);
    $expressSurchargeGross = (int)shop_setting('express_surcharge', 2500);

    // --- 3. DATEN NORMALISIEREN ---
    $data = [
        'subtotal' => 0,
        'volume_discount' => 0,
        'coupon_code' => null,
        'discount_amount' => 0,
        'is_express' => false,
        'shipping' => 0,
        'tax_amount' => 0,
        'tax_breakdown' => [],
        'total' => 0
    ];

    if ($model) {
        $isOrder = $model instanceof \App\Models\Order\Order;

        $data['volume_discount'] = $model->volume_discount ?? 0;
        $data['coupon_code'] = $model->coupon_code ?? null;
        $data['discount_amount'] = $model->discount_amount ?? 0;
        $data['is_express'] = (bool)$model->is_express;
        $data['shipping'] = $model->shipping_price ?? ($model->shipping_cost ?? 0);
        $data['tax_amount'] = $isOrder ? $model->tax_amount : ($model->tax_total ?? 0);
        $data['total'] = $isOrder ? $model->total_price : ($model->gross_total ?? 0);

        $rawSubtotal = $isOrder ? $model->subtotal_price : ($model->net_total ?? 0);

        if ($data['is_express']) {
            $expressNet = $expressSurchargeGross / (1 + ($taxRate / 100));
            $data['subtotal'] = $rawSubtotal - $expressNet;
            if($data['subtotal'] < 0) $data['subtotal'] = $rawSubtotal;
        } else {
            $data['subtotal'] = $rawSubtotal;
        }

        // NEU: Zieht den exakten, aufgeschlüsselten Tax Breakdown via Traits für 0% Produkte!
        $data['tax_breakdown'] = [];
        if (!$isSmallBusiness && method_exists($model, 'toFormattedArray')) {
            $summaryData = $model->toFormattedArray();
            if (isset($summaryData['tax_breakdown']) && is_array($summaryData['tax_breakdown'])) {
                foreach($summaryData['tax_breakdown'] as $rate => $formattedStr) {
                    $cleanFloat = (float)str_replace(['.', ','], ['', '.'], $formattedStr);
                    $data['tax_breakdown'][$rate] = (int)round($cleanFloat * 100);
                }
            }
        }
    }
    elseif ($totals) {
        $data['subtotal'] = $totals['subtotal_gross'] ?? ($totals['subtotal_original'] ?? 0);
        if(isset($totals['subtotal_original'])) {
             $data['subtotal'] = $totals['subtotal_original'];
        }

        $data['volume_discount'] = $totals['volume_discount'] ?? 0;
        $data['coupon_code'] = $totals['coupon_code'] ?? null;
        $data['discount_amount'] = $totals['discount_amount'] ?? 0;
        $data['is_express'] = $totals['is_express'] ?? false;
        $data['shipping'] = $totals['shipping'] ?? 0;
        $data['tax_amount'] = $totals['tax'] ?? 0;
        $data['tax_breakdown'] = $totals['taxes_breakdown'] ?? [];
        $data['total'] = $totals['total'] ?? 0;
    }
@endphp

<div class="{{ $containerClass }}">

    @if($showTitle)
        <h4 class="font-bold text-sm uppercase tracking-widest mb-6 border-b pb-3 flex items-center gap-2 {{ $titleClass }}">
            <svg class="w-5 h-5 {{ $iconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Kostenübersicht
        </h4>
    @endif

    <div class="space-y-4 text-sm font-medium">

        {{-- 1. Warenwert --}}
        <div class="flex justify-between">
            <span class="{{ $labelClass }}">Warenwert</span>
            <span class="{{ $valueClass }} whitespace-nowrap">{{ number_format($data['subtotal'] / 100, 2, ',', '.') }}&nbsp;€</span>
        </div>

        {{-- 2. Rabatte --}}
        @if($data['volume_discount'] > 0)
            <div class="flex justify-between {{ $discountBoxClass }}">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 {{ $discountIconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-bold">Mengenrabatt</span>
                </div>
                <span class="whitespace-nowrap font-bold">-{{ number_format($data['volume_discount'] / 100, 2, ',', '.') }}&nbsp;€</span>
            </div>
        @endif

        @if($data['discount_amount'] > 0)
            <div class="flex justify-between {{ $discountBoxClass }}">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 {{ $discountIconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <span class="font-bold">Gutschein ({{ $data['coupon_code'] }})</span>
                </div>
                {{-- Slot für Delete Button im Cart --}}
                <div class="flex items-center gap-2">
                    <span class="whitespace-nowrap font-bold">-{{ number_format($data['discount_amount'] / 100, 2, ',', '.') }}&nbsp;€</span>
                    {{ $slot ?? '' }}
                </div>
            </div>
        @endif

        {{-- Zwischensumme (Optional anzeigen bei Rabatten) --}}
        @if($data['volume_discount'] > 0 || $data['discount_amount'] > 0)
            <div class="border-b my-3 {{ $dividerClass }}"></div>
            <div class="flex justify-between italic text-xs mb-3 {{ $labelClass }}">
                <span>Zwischensumme</span>
                <span class="whitespace-nowrap">{{ number_format(($data['subtotal'] - $data['volume_discount'] - $data['discount_amount']) / 100, 2, ',', '.') }}&nbsp;€</span>
            </div>
        @endif

        {{-- 3. Express Service --}}
        @if($data['is_express'])
            <div class="flex justify-between {{ $expressBoxClass }}">
                <span class="flex items-center gap-2 font-bold">
                    <svg class="w-4 h-4 animate-pulse {{ $expressIconClass }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Express-Service
                </span>
                <span class="whitespace-nowrap font-bold">+ {{ number_format($expressSurchargeGross / 100, 2, ',', '.') }}&nbsp;€</span>
            </div>
        @endif

        {{-- 4. Versand --}}
        <div class="flex justify-between">
            <span class="{{ $labelClass }}">Versand {{ $country ? "($country)" : '' }}</span>
            @if($data['shipping'] > 0)
                <span class="{{ $valueClass }} whitespace-nowrap">{{ number_format($data['shipping'] / 100, 2, ',', '.') }}&nbsp;€</span>
            @else
                @if(isset($totals))
                    <div class="{{ $freeShipBadgeClass }} text-xs font-bold">
                        Kostenlos
                    </div>
                @else
                    <span class="{{ $freeShipTextClass }} font-bold text-xs uppercase tracking-widest">Kostenlos</span>
                @endif
            @endif
        </div>

        {{-- 5. Gesamt --}}
        <div class="border-t pt-5 mt-6 flex justify-between items-end {{ $totalDividerClass }}">
            <span class="font-serif font-bold text-xl {{ $totalLabelClass }}">Gesamtsumme</span>
            <span class="font-serif font-black text-3xl whitespace-nowrap {{ $totalValueClass }}">{{ number_format($data['total'] / 100, 2, ',', '.') }}&nbsp;€</span>
        </div>

        {{-- 6. MwSt --}}
        <div class="text-right space-y-1 pt-1">
            @if(!$isSmallBusiness)
                @if(!empty($data['tax_breakdown']))
                    @foreach($data['tax_breakdown'] as $rate => $amount)
                        @if($amount > 0 || floatval($rate) == 0)
                            <div class="text-[10px] uppercase tracking-widest font-black {{ $taxTextClass }} whitespace-nowrap">
                                inkl. {{ number_format($amount / 100, 2, ',', '.') }}&nbsp;€ MwSt. ({{ floatval($rate) }}%)
                            </div>
                        @endif
                    @endforeach
                @else
                    <div class="text-[10px] uppercase tracking-widest font-black {{ $taxTextClass }} whitespace-nowrap">
                        inkl. {{ number_format($data['tax_amount'] / 100, 2, ',', '.') }}&nbsp;€ MwSt.
                        ({{ number_format($taxRate, 0) }}%)
                    </div>
                @endif
            @else
                <div class="text-[10px] uppercase tracking-widest font-black {{ $taxTextClass }}">Steuerfrei gemäß § 19 UStG</div>
            @endif
        </div>

    </div>
</div>

@if(count($cartItems) > 0)
    <div class="mb-12 bg-yellow-50/50 p-6 rounded-xl border border-yellow-100">
        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
            Ihre aktuelle Kalkulation
        </h3>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm mb-6">
            <table class="hidden md:table w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3">Produkt</th>
                    <th class="px-4 py-3">Details</th>
                    <th class="px-4 py-3 text-center">Menge</th>
                    <th class="px-4 py-3 text-right">Summe</th>
                    <th class="px-4 py-3 text-right"></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach($cartItems as $index => $item)
                    @php
                        $prod = $dbProducts[$item['product_id']] ?? null;
                        $tiers = $prod['tier_pricing'] ?? [];
                        usort($tiers, fn($a, $b) => $a['qty'] <=> $b['qty']);

                        $currentTier = null;
                        $nextTier = null;
                        foreach($tiers as $t) {
                            if($item['qty'] >= $t['qty']) {
                                $currentTier = $t;
                            } else {
                                $nextTier = $t;
                                break;
                            }
                        }
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-4 py-3 align-middle">
                            <div class="font-bold">{{ $item['name'] }}</div>
                            @if($currentTier)
                                <div class="text-[10px] text-green-600 font-bold uppercase flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.64.304 1.24.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
                                    {{ $currentTier['percent'] }}% Mengenrabatt aktiv
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-middle text-gray-500 text-xs">
                            {{ Str::limit($item['text'], 25) ?: 'Keine Gravur' }}
                        </td>
                        <td class="px-4 py-3 text-center align-middle">
                            <div class="font-bold">{{ $item['qty'] }}</div>
                            @if($nextTier)
                                @php
                                    $prevQty = $currentTier ? $currentTier['qty'] : 0;
                                    $range = $nextTier['qty'] - $prevQty;
                                    $progress = (($item['qty'] - $prevQty) / $range) * 100;
                                    $needed = $nextTier['qty'] - $item['qty'];
                                @endphp
                                <div class="mt-1 w-24 mx-auto">
                                    <div class="flex justify-between text-[8px] text-gray-400 mb-0.5">
                                        <span>noch {{ $needed }}</span>
                                        <span>-{{ $nextTier['percent'] }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1 overflow-hidden border border-gray-200">
                                        <div class="bg-green-500 h-full transition-all duration-500" style="width: {{ $progress }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right align-middle font-mono">
                            {{ number_format($item['calculated_total'], 2, ',', '.') }}&nbsp;€
                        </td>
                        <td class="px-4 py-3 text-right align-middle">
                            <div class="flex justify-end gap-2">
                                <button wire:click="editItem({{ $index }})" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 rounded text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-primary hover:border-primary transition shadow-sm">
                                    Bearbeiten
                                </button>
                                <button wire:click="removeItem({{ $index }})" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-300 rounded text-xs font-bold text-red-600 hover:bg-red-50 hover:border-red-300 transition shadow-sm">
                                    Löschen
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="md:hidden divide-y divide-gray-100">
                @foreach($cartItems as $index => $item)
                    @php
                        $prod = $dbProducts[$item['product_id']] ?? null;
                        $tiers = $prod['tier_pricing'] ?? [];
                        usort($tiers, fn($a, $b) => $a['qty'] <=> $b['qty']);
                        $currentTier = null;
                        $nextTier = null;
                        foreach($tiers as $t) {
                            if($item['qty'] >= $t['qty']) $currentTier = $t;
                            else { $nextTier = $t; break; }
                        }
                    @endphp
                    <div class="p-4 flex flex-col gap-3">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <span class="font-bold text-gray-900 text-base">{{ $item['name'] }}</span>
                                @if($currentTier)
                                    <span class="text-[10px] text-green-600 font-bold uppercase">{{ $currentTier['percent'] }}% Rabatt aktiv</span>
                                @endif
                            </div>
                            <span class="font-bold text-gray-900 font-mono">
                                {{ number_format($item['calculated_total'], 2, ',', '.') }}&nbsp;€
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <div class="flex flex-col gap-1">
                                <div class="text-gray-500 text-xs">
                                    {{ Str::limit($item['text'], 30) ?: 'Keine Gravur' }}
                                </div>
                                @if($nextTier)
                                    @php
                                        $prevQty = $currentTier ? $currentTier['qty'] : 0;
                                        $range = $nextTier['qty'] - $prevQty;
                                        $progress = (($item['qty'] - $prevQty) / $range) * 100;
                                    @endphp
                                    <div class="w-32">
                                        <div class="w-full bg-gray-100 rounded-full h-1 border border-gray-200">
                                            <div class="bg-green-500 h-full" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <div class="text-[8px] text-gray-400 mt-0.5">Noch {{ $nextTier['qty'] - $item['qty'] }} bis {{ $nextTier['percent'] }}%</div>
                                    </div>
                                @endif
                            </div>
                            <div class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-bold whitespace-nowrap">
                                {{ $item['qty'] }} Stk.
                            </div>
                        </div>
                        <div class="flex justify-end items-center gap-2 mt-1 pt-2 border-t border-gray-50">
                            <button wire:click="editItem({{ $index }})" class="flex-1 inline-flex justify-center items-center gap-1 px-3 py-2 bg-white border border-gray-300 rounded text-xs font-bold text-gray-700 hover:bg-gray-50 hover:text-primary transition">
                                Bearbeiten
                            </button>
                            <button wire:click="removeItem({{ $index }})" class="flex-1 inline-flex justify-center items-center gap-1 px-3 py-2 bg-white border border-gray-300 rounded text-xs font-bold text-red-600 hover:bg-red-50 transition">
                                Entfernen
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-end gap-6">
            <div class="relative w-full md:w-auto transition-all duration-300">
                @php
                    $expressPercent = (float)shop_setting('express_surcharge_percent', 20.0);
                    $expressMin = (int)shop_setting('express_surcharge_min', 500);
                    
                    $warenwertItemsBrutto = collect($cartItems)->sum('calculated_total');
                    $calculatedExpress = (int) round(($warenwertItemsBrutto * 100) * ($expressPercent / 100));
                    $expressCharge = max($expressMin, $calculatedExpress);
                    
                    $formattedPrice = number_format($expressCharge / 100, 2, ',', '.');
                    $formattedPercent = number_format($expressPercent, 0, ',', '.');
                    $formattedMin = number_format($expressMin / 100, 2, ',', '.');
                    
                    $shopCapacityLevel = (int)\Illuminate\Support\Facades\Cache::get('shop_capacity_level', \App\Models\System\SystemSetting::where('key', 'shop_capacity_level')->value('value') ?? 0);
                    $expressDisabled = $shopCapacityLevel >= 2;
                @endphp

                {{-- Container: Wenn ausgewählt, goldener Rahmen & Hintergrund, sonst grau/weiß --}}
                @if($isExpress)
                <div
                    class="block border rounded-xl p-4 transition-all group relative overflow-hidden
                    {{ $expressDisabled ? 'bg-gray-50 border-gray-200 opacity-70 cursor-not-allowed' : 'bg-amber-50 border-amber-300 ring-1 ring-amber-300 cursor-default' }}"
                >
                    @if($expressDisabled)
                        <div class="absolute inset-0 z-10 cursor-not-allowed" title="Express aktuell deaktiviert." @click.prevent></div>
                    @endif

                    <div class="flex items-start gap-4">



                        <div class="flex-1">
                            <div class="flex justify-between items-center flex-wrap gap-2">
                                {{-- Titel mit Icon --}}
                                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                    <span>🚀 Eiliges express Geschenk?</span>
                                    @if($isExpress)
                                        <span class="text-[10px] bg-primary text-white px-2 py-0.5 rounded-full uppercase tracking-wide font-bold animate-pulse">Aktiv</span>
                                    @endif
                                </h3>

                                {{-- Preis Badge --}}
                                <span class="font-bold text-primary bg-primary/10 px-2 py-1 rounded text-sm whitespace-nowrap">
                        + {{ $formattedPrice }}&nbsp;€
                    </span>
                            </div>

                            <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                                Da du bei einem oder mehreren Artikeln die priorisierte Fertigung gewählt hast, wird <strong>der gesamte Auftrag</strong> für dich vorgezogen.
                                <span class="block mt-0.5 text-[10px] text-gray-400">({{ $formattedPercent }}% Basis-Aufschlag, verhältnismäßig auf Stückzahl/Cart, mind. {{ $formattedMin }}&nbsp;€)</span>
                            </p>

                            @if($expressDisabled)
                                <div class="mt-3 text-xs font-bold text-red-600 bg-red-50/80 p-2.5 border border-red-200 rounded-lg flex items-start gap-2 shadow-sm relative z-20">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    Aufgrund des extrem hohen Lieferaufkommens können wir den Expressversand momentan nicht anbieten. Wir bitten um Verständnis!
                                </div>
                            @endif


                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="text-right w-full md:w-auto">
                {{-- Warenwert Brutto Anzeige (Items Brutto) --}}
                @php
                    $warenwertItemsBrutto = collect($cartItems)->sum('calculated_total');
                    // Warenwert VOR Abzug des Rabatts berechnen
                    $warenwertOriginal = $warenwertItemsBrutto + $volumeDiscount;
                @endphp
                <div class="text-sm text-gray-600">Warenwert: {{ number_format($warenwertOriginal, 2, ',', '.') }}&nbsp;€</div>

                {{-- NEU: Mengenrabatt anzeigen --}}
                @if($volumeDiscount > 0)
                    <div class="text-sm text-green-600 font-bold mt-1">
                        Mengenrabatt: -{{ number_format($volumeDiscount, 2, ',', '.') }}&nbsp;€
                    </div>
                @endif

                {{-- Detaillierte Versandanzeige --}}
                @php
                    $freeShippingThreshold = (int)shop_setting('shipping_free_threshold', 5000);
                @endphp

                @if($shippingCost > 0)
                    <div class="text-sm text-gray-600">
                        Versand ({{ $form['country'] }}): {{ number_format($shippingCost, 2, ',', '.') }}&nbsp;€
                    </div>

                    @if($form['country'] === 'DE')
                        @php
                            $missing = ($freeShippingThreshold / 100) - $warenwertItemsBrutto;
                        @endphp
                        @if($missing > 0.01)
                            <div class="text-xs text-amber-600 font-bold mt-1">
                                Noch <strong>{{ number_format($missing, 2, ',', '.') }}&nbsp;€</strong> bis zum kostenlosen Versand!
                            </div>
                        @endif
                    @endif
                @else
                    <div class="text-sm text-green-600 font-bold flex items-center justify-end gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Versand ({{ $form['country'] }}): Kostenlos
                    </div>
                @endif

                {{-- Express Anzeige falls aktiv --}}
                @if($isExpress)
                    <div class="text-sm text-red-600">Express-Service: {{ number_format($expressCharge / 100, 2, ',', '.') }}&nbsp;€</div>
                @endif

                {{-- Steuerliche Aufschlüsselung (Informativ am Ende) --}}
                <div class="mt-2 pt-2 border-t border-gray-100">
                    <div class="text-2xl font-bold text-primary">Gesamtbetrag: {{ number_format($totalBrutto, 2, ',', '.') }}&nbsp;€</div>

                    <div class="text-[10px] text-gray-400 italic mt-1">
                        Darin enthalten:<br>
                        Nettobetrag: {{ number_format($totalNetto, 2, ',', '.') }}&nbsp;€ |
                        @if(shop_setting('is_small_business', false))
                            Umsatzsteuerfrei gemäß § 19 UStG.
                        @elseif(!empty($taxBreakdown))
                            @foreach($taxBreakdown as $rate => $amount)
                                MwSt. ({{ floatval($rate) }}%): {{ number_format($amount, 2, ',', '.') }}&nbsp;€
                                @if(!$loop->last) | @endif
                            @endforeach
                        @else
                            MwSt. ({{ (float)shop_setting('default_tax_rate', 19) }}%): {{ number_format($totalMwst, 2, ',', '.') }}&nbsp;€
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    <button wire:click="goNext" class="w-full md:w-auto bg-gray-900 text-white px-8 py-3 rounded-xl hover:bg-black transition-all font-bold shadow-lg hover:shadow-xl active:scale-95">
                        Angebot anfordern
                    </button>
                </div>

                @error('cart') <div class="text-red-500 text-xs mt-2 font-bold">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
@endif

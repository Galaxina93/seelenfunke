<div class="bg-gray-50 min-h-screen py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8">Dein Warenkorb</h1>

        @if($items->isEmpty())
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm animate-fade-in-up">
                <div class="mb-4 text-gray-300">
                    <svg class="w-16 h-16 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <h2 class="text-xl font-medium text-gray-900 mb-2">Der Warenkorb ist leer</h2>
                <p class="text-gray-500 mb-6">Finde dein neues Seelenstück in unserer Manufaktur.</p>
                <a href="{{ route('shop') }}" class="inline-block bg-primary text-white px-8 py-3 rounded-full font-bold hover:bg-primary-dark transition shadow-lg shadow-primary/30">
                    Zur Kollektion
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                {{-- Linke Spalte: Artikelliste --}}
                <div class="lg:col-span-8 space-y-4">
                    @foreach($items as $item)
                        @php
                            $prod = $item->product;
                            // Fallback falls Typ noch nicht gesetzt ist
                            $type = $prod->type ?? 'physical';

                            $attributes = $prod->attributes ?? [];
                            $deliveryTime = $attributes['Lieferzeit'] ?? null;
                        @endphp

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden transition hover:shadow-md" wire:key="item-{{ $item->id }}">

                            {{-- Standard-Ansicht der Kachel --}}
                            <div class="p-4 sm:p-6 flex flex-col sm:flex-row gap-6 items-center sm:items-start {{ $editingItemId == $item->id ? 'bg-gray-50 border-b border-gray-100' : '' }}">

                                {{-- Produktbild --}}
                                <div class="w-24 h-24 bg-gray-50 rounded-xl flex-shrink-0 overflow-hidden border border-gray-100 relative group">
                                    @if(isset($prod->media_gallery[0]) && isset($prod->media_gallery[0]['path']))
                                        <img src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-300">
                                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif

                                    {{-- Typ Badge im Bild --}}
                                    @if($type === 'digital')
                                        <div class="absolute bottom-0 left-0 right-0 bg-blue-600/90 text-white text-[9px] font-bold text-center py-0.5">DIGITAL</div>
                                    @elseif($type === 'service')
                                        <div class="absolute bottom-0 left-0 right-0 bg-orange-500/90 text-white text-[9px] font-bold text-center py-0.5">SERVICE</div>
                                    @endif
                                </div>

                                {{-- Details --}}
                                <div class="flex-1 text-center sm:text-left">
                                    <h3 class="font-serif font-bold text-lg text-gray-900 mb-1 leading-tight">
                                        <a href="{{ route('product.show', $prod->slug) }}" class="hover:text-primary transition">{{ $prod->name }}</a>
                                    </h3>

                                    {{-- Dynamische Infos je nach Typ --}}
                                    <div class="flex items-center justify-center sm:justify-start gap-2 mb-2 text-xs font-medium">
                                        @if($type === 'physical')
                                            @if($deliveryTime)
                                                <span class="text-green-700 flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    {{ $deliveryTime }}
                                                </span>
                                            @endif
                                        @elseif($type === 'digital')
                                            <span class="text-blue-600 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                                Sofort-Download
                                            </span>
                                        @elseif($type === 'service')
                                            <span class="text-orange-600 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                                Dienstleistung / Termin
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Konfigurations-Status --}}
                                    <div class="flex flex-wrap gap-2 justify-center sm:justify-start mb-3">
                                        @if(!empty($item->configuration['text']))
                                            <span class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-gray-600 bg-gray-100 px-2 py-1 rounded">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Text
                                            </span>
                                        @endif
                                        @if(!empty($item->configuration['logos']))
                                            <span class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-gray-600 bg-gray-100 px-2 py-1 rounded">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                Motiv
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-sm text-gray-500 font-mono">
                                        Einzelpreis: {{ number_format($item->unit_price / 100, 2, ',', '.') }} €
                                    </p>
                                </div>

                                {{-- Controls --}}
                                <div class="flex flex-col items-center gap-4">
                                    {{-- Quantity: Nur bei Physischen Produkten editierbar --}}
                                    @if($type === 'physical')
                                        <div class="flex items-center bg-gray-50 rounded-full border border-gray-200">
                                            <button wire:click="decrement('{{ $item->id }}')" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary transition font-bold text-lg">-</button>
                                            <span class="w-8 text-center text-sm font-bold text-gray-900">{{ $item->quantity }}</span>
                                            <button wire:click="increment('{{ $item->id }}')" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary transition font-bold text-lg">+</button>
                                        </div>
                                    @else
                                        {{-- Bei Digital/Service nur Anzeige --}}
                                        <div class="flex items-center justify-center bg-gray-50 rounded-full border border-gray-200 px-4 py-1.5">
                                            <span class="text-sm font-bold text-gray-900">{{ $item->quantity }}x</span>
                                        </div>
                                    @endif

                                    {{-- Summe --}}
                                    <div class="font-bold text-gray-900 text-lg">
                                        {{ number_format(($item->unit_price * $item->quantity) / 100, 2, ',', '.') }} €
                                    </div>

                                    {{-- Buttons: Bearbeiten & Entfernen --}}
                                    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                                        {{-- Bearbeiten: NUR bei physischen Produkten anzeigen --}}
                                        @if($type === 'physical')
                                            @if($editingItemId == $item->id)
                                                <button wire:click="closeModal" class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-3 py-1.5 bg-gray-900 border border-gray-900 rounded-lg text-xs font-bold text-white shadow-sm hover:bg-black transition">
                                                    Fertig
                                                </button>
                                            @else
                                                <button wire:click="edit('{{ $item->id }}')" class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50 hover:text-primary hover:border-primary transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                    Bearbeiten
                                                </button>
                                            @endif
                                        @endif

                                        <button wire:click="remove('{{ $item->id }}')" class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs font-bold text-red-600 shadow-sm hover:bg-red-50 hover:border-red-300 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Löschen
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Inline Konfigurator (Nur wenn aktiv & nicht digital) --}}
                            @if($editingItemId == $item->id && $type !== 'digital')
                                <div class="bg-white p-2 sm:p-4 animate-fade-in-down border-t border-primary/10 shadow-inner bg-gray-50/30">
                                    <div class="relative min-h-[400px]">
                                        <livewire:shop.configurator.configurator
                                            :product="$prod"
                                            :cartItem="$item"
                                            context="edit"
                                            :key="'inline-edit-'.$item->id"
                                        />
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Rechte Spalte: Zusammenfassung --}}
                <div class="lg:col-span-4">
                    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-lg sticky top-24">
                        <h3 class="font-serif font-bold text-xl text-gray-900 mb-6">Zusammenfassung</h3>

                        @php
                            // Dynamischen Wert aus Config laden
                            $threshold = (int) shop_setting('shipping_free_threshold', 5000);
                            $currentValue = $totals['subtotal_gross'];

                            // Check: Haben wir überhaupt physische Produkte?
                            // Wenn NUR Digital/Service im Warenkorb -> Kein Versandbalken nötig
                            $hasPhysical = $items->contains(fn($i) => ($i->product->type ?? 'physical') === 'physical');

                            $missing = $totals['missing_for_free_shipping'];
                            $isFree = $totals['is_free_shipping'];

                            // Wenn NUR Digital -> Versand ist immer "Frei" (keine Kosten), Balken nicht anzeigen oder als 100%
                            if (!$hasPhysical) {
                                $percent = 100;
                            } else {
                                $percent = $threshold > 0 ? min(100, ($currentValue / $threshold) * 100) : 100;
                            }
                        @endphp

                        {{-- 1. Fortschrittsanzeige für kostenlosen Versand --}}
                        {{-- Nur anzeigen, wenn Physische Produkte da sind --}}
                        @if($hasPhysical && $threshold > 0)
                            @if(!$isFree && $missing > 0)
                                <div class="mb-6 bg-gray-50 p-3 rounded-xl border border-gray-200">
                                    <p class="text-xs text-gray-600 mb-2">
                                        Noch <span class="font-bold text-primary">{{ number_format($missing / 100, 2, ',', '.') }} €</span> bis zum <span class="text-green-600 font-bold">kostenlosen Versand!</span>
                                    </p>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-green-500 h-1.5 rounded-full transition-all duration-700 ease-out"
                                             style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @else
                                <div class="mb-6 flex items-center gap-2 text-green-600 bg-green-50 p-3 rounded-xl border border-green-100 shadow-sm animate-fade-in">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span class="text-xs font-bold uppercase tracking-wider">Kostenloser Versand aktiviert!</span>
                                </div>
                            @endif
                        @elseif(!$hasPhysical)
                            {{-- Hinweis für rein digitale Warenkörbe --}}
                            <div class="mb-6 flex items-center gap-2 text-blue-600 bg-blue-50 p-3 rounded-xl border border-blue-100 shadow-sm">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span class="text-xs font-bold uppercase tracking-wider">Sofort-Download / Service (Versandfrei)</span>
                            </div>
                        @endif

                        {{-- 2. Kostenaufstellung via Master Component --}}
                        <div class="mb-6 pb-6 border-b border-gray-100">
                            <x-shop.cost-summary :totals="$totals" :showTitle="false">
                                {{-- Slot für Gutschein Löschen Button --}}
                                <button wire:click="removeCoupon" class="text-red-400 hover:text-red-600 transition" title="Gutschein entfernen">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </x-shop.cost-summary>
                        </div>

                        {{-- 3. Gutschein Input --}}
                        @if(empty($totals['coupon_code']))
                            <div class="mb-6">
                                <form wire:submit.prevent="applyCoupon" class="flex gap-2">
                                    <input type="text"
                                           wire:model="couponCodeInput"
                                           placeholder="Gutscheincode"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary uppercase placeholder-gray-400">
                                    <button type="submit"
                                            class="bg-gray-900 text-white px-3 py-2 rounded text-sm font-bold hover:bg-black transition shadow-md">
                                        Einlösen
                                    </button>
                                </form>
                                @error('couponCodeInput')
                                <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                                @enderror
                                @if(session()->has('success'))
                                    <span class="text-green-600 text-xs mt-1 block">{{ session('success') }}</span>
                                @endif
                            </div>
                        @endif

                        {{-- 4. Checkout Button --}}
                        <a href="{{ route('checkout') }}"
                           class="w-full bg-primary text-white py-4 rounded-full font-bold shadow-lg shadow-primary/30 hover:bg-white hover:text-primary border border-transparent hover:border-primary transition transform hover:-translate-y-1 flex justify-center gap-2 items-center">
                            <span>Zur Kasse</span>
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>

                        {{-- 5. Zahlungsanbieter --}}
                        <div class="mt-5">
                            <p class="text-[10px] text-gray-400 text-center mb-2">Sichere Zahlung mit</p>
                            <div class="flex flex-wrap justify-center gap-2 opacity-70">
                                <div class="h-6 w-10 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/mastercard.svg') }}" class="h-4"></div>
                                <div class="h-6 w-10 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/google-pay.svg') }}" class="h-4"></div>
                                <div class="h-6 w-10 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/apple-pay.svg') }}" class="h-4"></div>
                                <div class="h-6 w-10 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/paypal.svg') }}" class="h-4"></div>
                                <div class="h-6 w-10 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/klarna.svg') }}" class="h-4"></div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        @endif
    </div>

    {{-- Loading Indicator --}}
    <div wire:loading class="fixed inset-0 bg-white/50 backdrop-blur-sm z-50 flex items-center justify-center">
        <svg class="animate-spin h-10 w-10 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
    </div>
</div>

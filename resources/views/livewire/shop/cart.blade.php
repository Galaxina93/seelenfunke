<div class="bg-gray-50 min-h-screen py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8">Dein Warenkorb</h1>

        @if($items->isEmpty())
            <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
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
                        <div class="bg-white rounded-2xl p-4 sm:p-6 border border-gray-100 shadow-sm flex flex-col sm:flex-row gap-6 items-center sm:items-start transition hover:shadow-md" wire:key="item-{{ $item->id }}">

                            {{-- Produktbild --}}
                            <div class="w-24 h-24 bg-gray-50 rounded-xl flex-shrink-0 overflow-hidden border border-gray-100">
                                @if(isset($item->product->media_gallery[0]) && isset($item->product->media_gallery[0]['path']))
                                    <img src="{{ asset('storage/'.$item->product->media_gallery[0]['path']) }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                            </div>

                            {{-- Details --}}
                            <div class="flex-1 text-center sm:text-left">
                                <h3 class="font-serif font-bold text-lg text-gray-900 mb-1">
                                    <a href="#" class="hover:text-primary transition">{{ $item->product->name }}</a>
                                </h3>

                                {{-- Lieferzeit --}}
                                @if(isset($item->product->attributes['Lieferzeit']))
                                    <div class="flex items-center justify-center sm:justify-start gap-1.5 text-xs text-green-700 font-medium mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                        </svg>
                                        Lieferzeit: {{ $item->product->attributes['Lieferzeit'] }}
                                    </div>
                                @endif

                                {{-- Konfigurations-Status --}}
                                <div class="flex flex-wrap gap-2 justify-center sm:justify-start mb-3">
                                    @if(!empty($item->configuration['text']))
                                        <span class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-green-700 bg-green-50 px-2 py-1 rounded border border-green-100">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Gravur
                                        </span>
                                    @endif
                                    @if(!empty($item->configuration['logo_path']))
                                        <span class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-green-700 bg-green-50 px-2 py-1 rounded border border-green-100">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Logo
                                        </span>
                                    @endif
                                </div>

                                <p class="text-sm text-gray-500 font-mono">
                                    Einzelpreis: {{ number_format($item->unit_price / 100, 2, ',', '.') }} €
                                </p>
                            </div>

                            {{-- Controls --}}
                            <div class="flex flex-col items-center gap-4">
                                {{-- Quantity --}}
                                <div class="flex items-center bg-gray-50 rounded-full border border-gray-200">
                                    <button wire:click="decrement('{{ $item->id }}')" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary transition font-bold text-lg">-</button>
                                    <span class="w-8 text-center text-sm font-bold text-gray-900">{{ $item->quantity }}</span>
                                    <button wire:click="increment('{{ $item->id }}')" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary transition font-bold text-lg">+</button>
                                </div>

                                {{-- Summe --}}
                                <div class="font-bold text-gray-900">
                                    {{ number_format(($item->unit_price * $item->quantity) / 100, 2, ',', '.') }} €
                                </div>

                                {{-- Buttons: Bearbeiten & Entfernen (VERGRÖßERT) --}}
                                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                                    <button wire:click="edit('{{ $item->id }}')" class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50 hover:text-primary hover:border-primary transition">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        Bearbeiten
                                    </button>

                                    <button wire:click="remove('{{ $item->id }}')" class="flex-1 sm:flex-none inline-flex justify-center items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-red-600 shadow-sm hover:bg-red-50 hover:border-red-300 transition">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Entfernen
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Rechte Spalte: Zusammenfassung --}}
                <div class="lg:col-span-4">
                    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100 shadow-lg sticky top-24">
                        <h3 class="font-serif font-bold text-xl text-gray-900 mb-6">Zusammenfassung</h3>

                        <div class="space-y-3 text-sm text-gray-600 mb-6 pb-6 border-b border-gray-100">

                            {{-- 1. ECHTER WARENWERT (Original) --}}
                            <div class="flex justify-between">
                                <span>Warenwert</span>
                                <span>{{ number_format($totals['subtotal_original'] / 100, 2, ',', '.') }} €</span>
                            </div>

                            {{-- 2. MENGENRABATT --}}
                            @if(!empty($totals['volume_discount']) && $totals['volume_discount'] > 0)
                                <div class="flex justify-between text-green-600 font-bold bg-green-50 p-2 rounded -mx-2">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Mengenrabatt</span>
                                    </div>
                                    <span>-{{ number_format($totals['volume_discount'] / 100, 2, ',', '.') }} €</span>
                                </div>

                                {{-- Zwischenlinie --}}
                                <div class="border-b border-gray-100 my-1"></div>

                                {{-- Zwischensumme --}}
                                <div class="flex justify-between text-gray-500 italic text-xs">
                                    <span>Zwischensumme</span>
                                    <span>{{ number_format($totals['subtotal_gross'] / 100, 2, ',', '.') }} €</span>
                                </div>
                            @endif

                            {{-- 3. GUTSCHEIN --}}
                            @if(!empty($totals['discount_amount']) && $totals['discount_amount'] > 0)
                                <div class="flex justify-between text-green-600 font-bold bg-green-50 p-2 rounded -mx-2">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                        </svg>
                                        <span>Gutschein ({{ $totals['coupon_code'] }})</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span>-{{ number_format($totals['discount_amount'] / 100, 2, ',', '.') }} €</span>
                                        <button wire:click="removeCoupon" class="text-red-400 hover:text-red-600" title="Entfernen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @endif

                            {{-- 4. VERSAND --}}
                            @if($totals['shipping'] > 0)
                                <div class="flex justify-between text-gray-600">
                                    <span>Versand</span>
                                    <span>{{ number_format($totals['shipping'] / 100, 2, ',', '.') }} €</span>
                                </div>
                            @else
                                <div class="flex justify-between items-center bg-gray-50 p-2 rounded -mx-2">
                                    <span class="text-gray-800 font-medium">Versand</span>
                                    <span class="text-green-700 font-bold uppercase text-xs tracking-wider">Kostenlos</span>
                                </div>
                            @endif

                            {{-- 5. ENDSUMME --}}
                            <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                                <span class="text-base font-bold text-gray-900">Gesamtsumme</span>
                                <span class="text-xl font-bold text-gray-900">{{ number_format($totals['total'] / 100, 2, ',', '.') }} €</span>
                            </div>

                            {{-- Steuern --}}
                            <div class="pt-1 space-y-1 text-right">
                                @foreach($totals['taxes_breakdown'] as $rate => $amount)
                                    <div class="text-[10px] text-gray-400">
                                        inkl. {{ number_format($amount / 100, 2, ',', '.') }} € MwSt. ({{ floatval($rate) }}%)
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Gutschein Input (nur wenn keiner aktiv ist) --}}
                        @if(empty($totals['coupon_code']))
                            <div class="mb-6">
                                <form wire:submit.prevent="applyCoupon" class="flex gap-2">
                                    <input type="text"
                                           wire:model="couponCodeInput"
                                           placeholder="Gutscheincode"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded focus:ring-primary focus:border-primary uppercase placeholder-gray-400">
                                    <button type="submit"
                                            class="bg-gray-900 text-white px-3 py-2 rounded text-sm font-bold hover:bg-black transition shadow-md">
                                        OK
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

                        {{-- Checkout Button --}}
                        <a href="{{ route('checkout') }}"
                           class="w-full bg-primary text-white py-4 rounded-full font-bold shadow-lg shadow-primary/30 hover:bg-white hover:text-primary border border-transparent hover:border-primary transition transform hover:-translate-y-1 flex justify-center gap-2 items-center">
                            <span>Zur Kasse</span>
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>

                        {{-- Zahlungsanbieter --}}
                        <div class="mt-5">
                            <p class="text-[10px] text-gray-400 text-center mb-2">Sichere Zahlung mit</p>
                            <div class="flex flex-wrap justify-center gap-2">
                                <div class="h-8 w-12 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/mastercard.svg') }}" class="h-6"></div>
                                <div class="h-8 w-12 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/apple-pay.svg') }}" class="h-6"></div>
                                <div class="h-8 w-12 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/google-pay.svg') }}" class="h-6"></div>
                                <div class="h-8 w-12 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/paypal.svg') }}" class="h-6"></div>
                                <div class="h-8 w-12 flex items-center justify-center rounded bg-gray-50 border border-gray-200"><img src="{{ asset('images/projekt/payments/klarna.svg') }}" class="h-6"></div>
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

    {{-- EDIT MODAL --}}
    <div x-data="{ open: @entangle('showEditModal').live }"
         x-show="open"
         class="relative z-50"
         style="display: none;">

        <div x-show="open" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div x-show="open"
                         class="pointer-events-auto w-screen max-w-md transform transition ease-in-out duration-500 sm:duration-700"
                         x-transition:enter="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="translate-x-0"
                         x-transition:leave-end="translate-x-full">

                        <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-xl">
                            {{-- Header --}}
                            <div class="px-4 py-6 sm:px-6 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                                <h2 class="text-lg font-medium text-gray-900">Konfiguration bearbeiten</h2>

                                <button wire:click="closeModal" type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                                    <span class="sr-only">Schließen</span>
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            {{-- INFO-BOX: Hinweis zur Positionierung --}}
                            <div class="mx-4 mt-6 sm:mx-6 bg-blue-50 border-l-4 border-blue-400 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700">
                                            Die Vorschau dient zur reinen Orientierung. Die exakte Positionierung von Bild und Text erfolgt durch unser Fachteam.
                                            Farben können technisch nicht gelasert werden und werden in der Vorschau lediglich zur besseren Darstellung angezeigt.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Content --}}
                            <div class="relative flex-1 h-full">
                                @if($editingItemId)
                                    @php $editItem = \App\Models\CartItem::find($editingItemId); @endphp
                                    @if($editItem)
                                        <livewire:shop.configurator
                                            :product="$editItem->product"
                                            :cartItem="$editItem"
                                            context="edit"
                                            :key="'conf-edit-'.$editingItemId"
                                        />
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    // Dynamische Farben basierend auf Modus
    $isAuto = $voucherSectionMode === 'auto';
    $bgClass = $isAuto ? 'from-purple-500 to-indigo-600' : 'from-orange-500 to-red-600';
@endphp

<section class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-10 relative overflow-hidden transition-all duration-500 w-full mt-6">
    {{-- Dekorativer Glow-Streifen links --}}
    <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b {{ $bgClass }} opacity-60 transition-colors duration-500"></div>

    {{-- Header Bereich --}}
    <div class="flex flex-col md:flex-row justify-between items-start mb-10 gap-6 relative z-10">
        <div>
            <h3 class="text-2xl font-serif font-bold text-white tracking-tight flex items-center gap-3 transition-all duration-300">
                <i class="{{ $isAuto ? 'solar-ticket-bold-duotone text-purple-400' : 'solar-tag-bold-duotone text-orange-400' }} text-2xl"></i>
                {{ $isAuto ? 'Gutschein Automatisierung' : 'Manuelle Gutscheine' }}
            </h3>
            <div class="flex items-center gap-3 mt-2">
                <span class="text-[10px] font-mono text-gray-500 bg-black/40 px-2 py-0.5 rounded border border-gray-800 uppercase tracking-tighter">
                    {{ $isAuto ? 'COMMAND: FUNKI:SEND-VOUCHERS' : 'MANUELLE VERWALTUNG' }}
                </span>
                @if($isAuto)
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.5)]"></span>
                    </span>
                @endif
            </div>
        </div>

        {{-- SWITCHER --}}
        <button
            wire:click="toggleVoucherSectionMode"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-950 text-gray-400 hover:text-white border border-gray-800 shadow-inner hover:scale-[1.02]">
            @if($isAuto)
                <span>Manuell verwalten</span> <x-heroicon-m-arrow-right class="w-4 h-4 text-orange-400" />
            @else
                <x-heroicon-m-arrow-left class="w-4 h-4 text-purple-400" /> <span>Zu Autopilot</span>
            @endif
        </button>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- MODUS 1: AUTOMATISIERUNG (LILA) --}}
    {{-- ---------------------------------------------------------------- --}}
    @if($isAuto)
        {{-- TIMELINE (HORIZONTALER SLIDER) --}}
        <div class="relative group/slider w-full mt-4 animate-fade-in"
             x-data="{
                 scrollAmount: 0,
                 container: null,
                 init() { this.container = this.$refs.sliderContainer; },
                 scroll(direction) {
                     const scrollVal = 320;
                     if(direction === 'left') this.container.scrollBy({left: -scrollVal, behavior: 'smooth'});
                     else this.container.scrollBy({left: scrollVal, behavior: 'smooth'});
                 }
             }">

            {{-- Arrow Left --}}
            <button @click.stop="scroll('left')" class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-gray-800/90 backdrop-blur border border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-400 hover:text-white hover:border-purple-500 transition-all opacity-0 group-hover/slider:opacity-100 -translate-x-4 group-hover/slider:translate-x-2 duration-300">
                <x-heroicon-m-chevron-left class="w-6 h-6" />
            </button>

            {{-- Arrow Right --}}
            <button @click.stop="scroll('right')" class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-gray-800/90 backdrop-blur border border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-400 hover:text-white hover:border-purple-500 transition-all opacity-0 group-hover/slider:opacity-100 translate-x-4 group-hover/slider:-translate-x-2 duration-300">
                <x-heroicon-m-chevron-right class="w-6 h-6" />
            </button>

            <div x-ref="sliderContainer" class="flex gap-6 overflow-x-auto pb-8 pt-2 custom-scrollbar snap-x scroll-smooth relative z-10 w-full px-2">
                @foreach($autoVouchers as $voucher)
                    @php
                        $isPast = $voucher->valid_until < now();
                        $statusDisabled = !$voucher->is_active;
                        $isSelected = $editingVoucherId === $voucher->id;
                    @endphp

                    <div class="relative group w-[280px] sm:w-[320px] shrink-0 snap-start">
                        {{-- Selektions-Indikator (Glow Effekt) --}}
                        @if($isSelected)
                            <div class="absolute -inset-1 bg-gradient-to-r from-purple-500/20 to-fuchsia-500/20 blur-lg rounded-[2rem] animate-pulse"></div>
                        @endif

                        <div wire:click="editVoucher('{{ $voucher->id }}')"
                             class="relative cursor-pointer transition-all duration-500 group-hover:-translate-y-2 h-full {{ $isSelected ? 'scale-[1.03] z-20' : 'opacity-90 hover:opacity-100' }}">

                            {{-- Custom Card für Voucher (Dark Mode) --}}
                            <div @class([
                                'w-full h-full p-6 rounded-[2rem] border transition-all duration-500 relative flex flex-col justify-between group min-h-[160px] backdrop-blur-sm',
                                'border-gray-800 bg-gray-950/40 opacity-60 grayscale-[0.3]' => $isPast || $statusDisabled,
                                'border-purple-500 bg-gray-900 shadow-[0_0_30px_rgba(168,85,247,0.2)] ring-1 ring-purple-400/30' => !$isPast && !$statusDisabled
                            ])>

                                {{-- 1. STATUS ICON (Gefixt & Zentriert in 8x8 Box) --}}
                                <div class="absolute top-5 right-5 z-20 w-8 h-8 flex items-center justify-center">
                                    @if($isPast)
                                        <div class="text-gray-600 flex items-center justify-center w-full h-full">
                                            <x-heroicon-m-clock class="w-6 h-6"/>
                                        </div>
                                    @else
                                        {{-- Pulsierender Spinner / Sparkles --}}
                                        <div class="relative flex items-center justify-center w-full h-full">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-40"></span>
                                            <div class="relative bg-gray-900 rounded-full p-1.5 border border-purple-500/50 text-purple-400 shadow-xl flex items-center justify-center w-full h-full">
                                                <x-heroicon-m-sparkles class="w-4 h-4 animate-pulse"/>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- 2. INHALT (Mit Padding Right gegen Overlap) --}}
                                <div class="mt-1 relative z-10 pr-12">
                                    <div class="text-[10px] font-black uppercase tracking-[0.2em] mb-3 flex items-center gap-1 {{ $isPast || $statusDisabled ? 'text-gray-500' : 'text-purple-400 drop-shadow-[0_0_8px_rgba(168,85,247,0.5)]' }}">
                                        {{ \Carbon\Carbon::parse($voucher->valid_from)->format('M Y') }}
                                    </div>

                                    <h5 class="font-bold text-gray-100 text-sm leading-snug line-clamp-2 mb-3 group-hover:text-white transition-colors" title="{{ $voucher->title }}">
                                        {{ $voucher->title }}
                                    </h5>

                                    <div class="inline-flex items-center gap-2 bg-black/40 border border-gray-800 px-3 py-1.5 rounded-xl mb-2 max-w-full shadow-inner">
                                        <span class="text-sm leading-none filter drop-shadow-sm">🎟️</span>
                                        <span class="text-[9px] font-mono font-black text-gray-400 uppercase tracking-widest truncate">{{ $voucher->code }}</span>
                                    </div>
                                </div>

                                {{-- 3. BADGE --}}
                                <div class="mt-5 relative group/badge w-full">
                                    <div class="absolute -inset-1 bg-purple-500/20 blur opacity-40 rounded-xl"></div>
                                    <div class="relative flex items-center justify-center gap-2 w-full text-[10px] font-black text-white bg-purple-600 px-3 py-2.5 rounded-xl shadow-lg border border-purple-400/30 uppercase tracking-widest overflow-hidden font-sans">
                                        <div class="absolute inset-0 bg-white/10 transform -skew-x-12 -translate-x-[150%] group-hover/badge:translate-x-[150%] transition-transform duration-700"></div>
                                        <span class="relative z-10">{{ $voucher->type === 'percent' ? $voucher->value . '%' : number_format($voucher->value / 100, 2) . ' €' }} Rabatt</span>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Quick Status Toggle Button --}}
                        <button
                            wire:click.stop="toggleVoucherStatus('{{ $voucher->id }}')"
                            class="absolute top-3 left-3 w-8 h-8 bg-gray-900/90 backdrop-blur-md border border-gray-800 rounded-full text-gray-500 hover:scale-110 shadow-2xl flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 z-30 {{ $voucher->is_active ? 'hover:text-red-400 hover:border-red-500/50' : 'hover:text-emerald-400 hover:border-emerald-500/50' }}"
                            title="{{ $voucher->is_active ? 'Pausieren' : 'Aktivieren' }}">
                            <x-heroicon-m-power class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- VOUCHER INLINE EDITOR (DARK MODE) --}}
        @if($editingVoucherId)
            <div class="mt-12 animate-fade-in-up relative z-20 w-full">
                <div class="bg-gray-950/80 backdrop-blur-xl rounded-[3rem] border border-gray-800 overflow-hidden shadow-[0_30px_100px_rgba(0,0,0,0.6)] w-full">

                    {{-- Editor Header --}}
                    <div class="bg-gray-900 px-6 sm:px-8 py-6 border-b border-gray-800 flex justify-between items-center shadow-inner">
                        <div class="flex items-center gap-5">
                            <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/30 text-purple-400 flex items-center justify-center shadow-[0_0_20px_rgba(168,85,247,0.2)] shrink-0">
                                <x-heroicon-m-ticket class="w-6 h-6" />
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-serif font-bold text-white text-lg sm:text-xl tracking-tight leading-none truncate">Gutschein bearbeiten</h4>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mt-1.5 truncate">{{ $edit_voucher_title }}</p>
                            </div>
                        </div>
                        <button wire:click="cancelEditVoucher" class="w-10 h-10 rounded-full bg-gray-900 border border-gray-800 text-gray-500 hover:text-white hover:bg-red-500/20 hover:border-red-500/50 flex items-center justify-center transition-all shadow-inner hover:rotate-90 shrink-0">
                            <x-heroicon-m-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="p-6 lg:p-10 grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-10 w-full">
                        {{-- Spalte 1: Code & Wert --}}
                        <div class="space-y-8 w-full">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Gutscheincode</label>
                                    <input type="text" wire:model="edit_voucher_code" class="w-full bg-gray-900 border border-gray-800 font-mono text-purple-400 font-bold text-sm rounded-2xl px-5 py-4 focus:ring-2 focus:ring-purple-500/30 focus:border-purple-500 outline-none uppercase shadow-inner transition-all">
                                </div>
                                <div>
                                    <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Wert</label>
                                    <div class="flex gap-2">
                                        <input type="number" wire:model="edit_voucher_value" class="w-full bg-gray-900 border border-gray-800 text-white font-bold text-sm rounded-2xl px-5 py-4 focus:ring-2 focus:ring-purple-500/30 focus:border-purple-500 outline-none shadow-inner transition-all">
                                        <select wire:model="edit_voucher_type" class="bg-gray-900 border border-gray-800 text-gray-300 rounded-2xl px-4 font-bold text-xs outline-none cursor-pointer focus:ring-2 focus:ring-purple-500/30">
                                            <option value="percent" class="bg-gray-950">%</option>
                                            <option value="fixed" class="bg-gray-950">€</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Spalte 2: Timing --}}
                        <div class="space-y-8 w-full">
                            <div class="bg-gray-900/50 rounded-[2rem] p-6 border border-gray-800 shadow-inner w-full relative overflow-hidden">
                                <div class="absolute right-0 top-0 w-24 h-24 bg-purple-500/10 rounded-bl-full blur-xl pointer-events-none"></div>
                                <div class="flex items-center justify-between mb-6 z-10 relative">
                                    <label class="text-[10px] font-black text-purple-400 uppercase tracking-widest drop-shadow-[0_0_8px_rgba(168,85,247,0.5)]">Timing & Gültigkeit</label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 z-10 relative">
                                    <div>
                                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2">Versand (Offset)</span>
                                        <div class="flex items-center gap-3">
                                            <input type="number" wire:model="edit_voucher_offset" class="w-20 bg-gray-950 border border-gray-800 text-purple-400 font-black text-lg text-center rounded-xl py-2 focus:ring-2 focus:ring-purple-500/30 outline-none shadow-inner">
                                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Tage vorher</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest block mb-2">Gültigkeit</span>
                                        <div class="flex items-center gap-3">
                                            <input type="number" wire:model="edit_voucher_validity" class="w-20 bg-gray-950 border border-gray-800 text-white font-black text-lg text-center rounded-xl py-2 focus:ring-2 focus:ring-purple-500/30 outline-none shadow-inner">
                                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Tage lang</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Editor Footer --}}
                    <div class="bg-gray-900/50 px-6 sm:px-8 py-5 sm:py-6 border-t border-gray-800 flex flex-col-reverse sm:flex-row justify-end gap-4 shadow-inner">
                        <button wire:click="cancelEditVoucher" class="w-full sm:w-auto px-8 py-4 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white hover:bg-gray-800 transition-colors text-center">
                            Abbrechen
                        </button>
                        <button wire:click="saveVoucher" class="w-full sm:w-auto px-10 py-4 rounded-xl bg-purple-600 text-white font-black text-[10px] uppercase tracking-[0.2em] hover:bg-purple-500 hover:scale-[1.05] transition-all shadow-[0_0_30px_rgba(168,85,247,0.4)] text-center">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        @endif


        {{-- ---------------------------------------------------------------- --}}
        {{-- MODUS 2: MANUELLE VERWALTUNG (ORANGE) --}}
        {{-- ---------------------------------------------------------------- --}}
    @else
        <div class="animate-fade-in w-full mt-4">

            {{-- Create Button --}}
            @if(!$isCreatingManual && !$isEditingManual)
                <div class="mb-8 flex justify-end">
                    <button wire:click="createManualCoupon" class="bg-orange-600 hover:bg-orange-500 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(234,88,12,0.4)] transition-all flex items-center gap-2 hover:scale-[1.02]">
                        <x-heroicon-m-plus class="w-4 h-4" /> Neuer Gutschein
                    </button>
                </div>
            @endif

            {{-- Formular Manuell (DARK MODE) --}}
            @if($isCreatingManual || $isEditingManual)
                <div class="animate-fade-in-up bg-gray-950/80 backdrop-blur-xl rounded-[3rem] p-8 lg:p-10 border border-gray-800 shadow-[0_30px_100px_rgba(0,0,0,0.6)] mb-10 relative z-10">
                    <div class="flex items-center gap-3 mb-8 border-b border-gray-800 pb-5">
                        <div class="w-10 h-10 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-400 flex items-center justify-center">
                            <x-heroicon-s-ticket class="w-5 h-5" />
                        </div>
                        <h4 class="font-serif font-bold text-white text-xl tracking-tight">{{ $isEditingManual ? 'Gutschein bearbeiten' : 'Gutschein erstellen' }}</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {{-- Code --}}
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Code <span class="text-orange-500">*</span></label>
                            <div class="relative">
                                <input type="text" wire:model="manual_code" class="w-full pl-12 pr-4 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 uppercase font-mono font-bold text-white tracking-wider shadow-inner transition-colors placeholder-gray-700" placeholder="z.B. SOMMER26">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-600">
                                    <x-heroicon-m-ticket class="h-5 w-5" />
                                </div>
                            </div>
                            @error('manual_code') <span class="text-red-400 text-[10px] mt-2 block font-bold uppercase tracking-widest ml-1">{{ $message }}</span> @enderror
                        </div>
                        {{-- Typ --}}
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Rabatt-Typ <span class="text-orange-500">*</span></label>
                            <select wire:model.live="manual_type" class="w-full px-5 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 cursor-pointer shadow-inner text-sm font-bold text-white outline-none">
                                <option value="fixed" class="bg-gray-950">Fester Betrag (€)</option>
                                <option value="percent" class="bg-gray-950">Prozentual (%)</option>
                            </select>
                        </div>
                        {{-- Wert --}}
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">
                                Wert ({{ $manual_type == 'fixed' ? 'Euro' : '%' }}) <span class="text-orange-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" step="{{ $manual_type == 'fixed' ? '0.01' : '1' }}" wire:model="manual_value" class="w-full pl-10 pr-4 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 font-bold shadow-inner text-white" placeholder="{{ $manual_type == 'fixed' ? '10.00' : '15' }}">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500 font-bold text-sm">
                                    {{ $manual_type == 'fixed' ? '€' : '%' }}
                                </div>
                            </div>
                            @error('manual_value') <span class="text-red-400 text-[10px] mt-2 block font-bold uppercase tracking-widest ml-1">{{ $message }}</span> @enderror
                        </div>
                        {{-- Mindestbestellwert --}}
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                Mindestbestellwert
                                <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <div class="relative">
                                <input type="number" step="0.01" wire:model="manual_min_order_value" class="w-full pl-10 pr-4 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 shadow-inner text-white" placeholder="z.B. 50.00">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500">€</div>
                            </div>
                        </div>
                        {{-- Gültig bis --}}
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                Gültig bis
                                <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <input type="date" wire:model="manual_valid_until" class="w-full px-5 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 shadow-inner cursor-pointer text-sm text-white [color-scheme:dark]">
                        </div>
                        {{-- Limit --}}
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                Limit (Anzahl)
                                <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <input type="number" wire:model="manual_usage_limit" class="w-full px-5 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 shadow-inner text-white" placeholder="z.B. 100">
                        </div>
                    </div>

                    {{-- Form Footer --}}
                    <div class="mt-10 pt-6 border-t border-gray-800 flex flex-col md:flex-row justify-between items-center gap-6">
                        <label class="flex items-center gap-3 cursor-pointer group bg-gray-900/50 px-4 py-2.5 rounded-xl border border-gray-800 hover:border-gray-700 transition-colors">
                            <div class="relative flex items-center h-5 mt-0.5 shrink-0">
                                <input type="checkbox" wire:model="manual_is_active" class="peer sr-only">
                                <div class="w-5 h-5 bg-gray-950 border-2 border-gray-700 rounded transition-all peer-checked:bg-orange-500 peer-checked:border-orange-400 shadow-inner"></div>
                                <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xs font-bold text-gray-400 group-hover:text-white uppercase tracking-widest select-none transition-colors">Gutschein aktiv</span>
                        </label>
                        <div class="flex gap-4 w-full md:w-auto">
                            <button wire:click="cancelManualCoupon" class="flex-1 md:flex-none px-6 py-4 border border-gray-800 bg-gray-900 text-gray-400 rounded-xl hover:bg-gray-800 hover:text-white transition-colors uppercase tracking-widest text-[10px] font-black">Abbrechen</button>
                            <button wire:click="saveManualCoupon" class="flex-1 md:flex-none px-8 py-4 bg-orange-600 text-white rounded-xl hover:bg-orange-500 hover:scale-[1.03] transition-all shadow-[0_0_20px_rgba(234,88,12,0.4)] flex justify-center items-center gap-2 uppercase tracking-widest text-[10px] font-black">
                                <x-heroicon-m-check class="w-4 h-4" />
                                Speichern
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- TABELLE (DARK MODE) --}}
            <div class="overflow-x-auto rounded-[2rem] border border-gray-800 shadow-2xl bg-gray-900/80 backdrop-blur-xl relative z-10 w-full no-scrollbar">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                    <tr class="text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-800 bg-gray-950/50 shadow-inner">
                        <th class="px-6 sm:px-8 py-5">Code</th>
                        <th class="px-6 py-5">Wert</th>
                        <th class="px-6 py-5">Mindestwert</th>
                        <th class="px-6 py-5">Gültigkeit</th>
                        <th class="px-6 py-5">Nutzung</th>
                        <th class="px-6 py-5">Status</th>
                        <th class="px-6 sm:px-8 py-5 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                    @forelse($manualCoupons as $c)
                        <tr wire:key="coupon-row-{{ $c->id }}" class="hover:bg-gray-800/30 transition-colors group">
                            <td class="px-6 sm:px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-gray-950 border border-gray-800 rounded-lg text-gray-500 shadow-inner">
                                        <x-heroicon-m-ticket class="w-4 h-4" />
                                    </div>
                                    <span class="font-mono font-bold text-white text-sm tracking-wider">{{ $c->code }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-black border shadow-inner {{ $c->type == 'percent' ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' }}">
                                    @if($c->type == 'fixed')
                                        {{ number_format($c->value / 100, 2, ',', '.') }} €
                                    @else
                                        {{ $c->value }} %
                                    @endif
                                </span>
                            </td>
                            <td class="px-6 py-5 text-sm text-gray-400 font-medium">
                                {{ $c->min_order_value ? number_format($c->min_order_value / 100, 2, ',', '.') . ' €' : '-' }}
                            </td>
                            <td class="px-6 py-5 text-xs">
                                @if($c->valid_until)
                                    <div class="flex flex-col gap-1">
                                        <span class="{{ $c->valid_until->isPast() ? 'text-red-400 font-bold' : 'text-gray-300 font-bold' }}">
                                            {{ $c->valid_until->format('d.m.Y') }}
                                        </span>
                                        @if($c->valid_until->isPast())
                                            <span class="text-[9px] text-red-500 uppercase font-black tracking-widest drop-shadow-[0_0_5px_currentColor]">Abgelaufen</span>
                                        @else
                                            <span class="text-[9px] text-gray-500 uppercase font-black tracking-widest">in {{ $c->valid_until->diffInDays(now()) }} Tagen</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5">
                                        <x-heroicon-o-identification class="w-3.5 h-3.5" /> Unbegrenzt
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-5 text-sm text-gray-300">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold">{{ $c->used_count }}</span>
                                    @if($c->usage_limit)
                                        <span class="text-gray-600">/ {{ $c->usage_limit }}</span>
                                        @if($c->used_count >= $c->usage_limit)
                                            <span class="text-[9px] text-red-400 uppercase font-black tracking-widest bg-red-500/10 border border-red-500/20 px-2 py-0.5 rounded-md ml-2 shadow-inner">Voll</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                @if($c->isValid())
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-[9px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest shadow-inner">
                                        <span class="relative flex h-1.5 w-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500 shadow-[0_0_8px_currentColor]"></span></span>
                                        Aktiv
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-[9px] font-black bg-gray-800 text-gray-500 border border-gray-700 uppercase tracking-widest shadow-inner">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
                                        Inaktiv
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 sm:px-8 py-5 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="editManualCoupon('{{ $c->id }}')" class="p-2.5 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 hover:bg-orange-500/10 hover:border-orange-500/30 hover:text-orange-400 transition-all shadow-inner" title="Bearbeiten">
                                        <x-heroicon-m-pencil-square class="w-4 h-4" />
                                    </button>
                                    <button wire:click="deleteManualCoupon('{{ $c->id }}')" wire:confirm="Möchten Sie den Gutschein '{{ $c->code }}' wirklich löschen?" class="p-2.5 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 hover:bg-red-500/10 hover:border-red-500/30 hover:text-red-400 transition-all shadow-inner" title="Löschen">
                                        <x-heroicon-m-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center text-gray-500 font-serif italic text-lg">
                                Noch keine manuellen Gutscheine erstellt.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>

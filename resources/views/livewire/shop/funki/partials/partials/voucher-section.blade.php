@php
    // Dynamische Farben basierend auf Modus
    $isAuto = $voucherSectionMode === 'auto';
    $sectionBorderColor = $isAuto ? 'border-purple-500' : 'border-orange-500'; // Linke Kante
    $themeColor = $isAuto ? 'purple' : 'orange';
@endphp

<section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-10 relative overflow-hidden transition-all duration-500 pt-4 mt-6">
    <div class="absolute top-0 left-0 w-2 h-full bg-{{ $themeColor }}-500 transition-colors duration-500"></div>

    <div class="flex flex-col md:flex-row justify-between items-start mb-8 gap-4 relative z-10">
        <div>
            <h3 class="text-2xl font-serif font-bold text-slate-900 transition-all">
                {{ $isAuto ? 'Gutschein Automatisierung (Saisonal)' : 'Manuelle Gutscheine' }}
            </h3>
            <p class="text-xs font-mono text-slate-400 mt-1 uppercase tracking-tighter">
                {{ $isAuto ? 'Command: funki:send-vouchers' : 'Manuelle Verwaltung & Erstellung' }}
            </p>
        </div>

        {{-- SWITCHER --}}
        <button
            wire:click="toggleVoucherSectionMode"
            class="bg-white border border-slate-200 text-slate-600 hover:text-slate-900 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            @if($isAuto)
                <span>Zu Manuell wechseln</span> <x-heroicon-m-arrow-right class="w-4 h-4" />
            @else
                <x-heroicon-m-arrow-left class="w-4 h-4" /> <span>Zu Automation wechseln</span>
            @endif
        </button>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- MODUS 1: AUTOMATISIERUNG (LILA) --}}
    {{-- ---------------------------------------------------------------- --}}
    @if($isAuto)
        {{-- TIMELINE --}}
        <div class="flex gap-4 overflow-x-auto pb-6 pt-2 custom-scrollbar snap-x">
            @foreach($autoVouchers as $voucher)
                @php
                    $isPast = $voucher->valid_until < now();
                    $isSelected = $editingVoucherId === $voucher->id;
                    $statusDisabled = !$voucher->is_active;
                @endphp
                <div class="snap-start relative group">
                    <div wire:click="editVoucher('{{ $voucher->id }}')"
                         class="cursor-pointer transition-all duration-300 group-hover:-translate-y-1 {{ $isSelected ? 'ring-4 ring-purple-500/20 scale-[1.02] bg-purple-50/10' : '' }}">

                        {{-- Custom Card für Voucher --}}
                        <div @class([
                            'w-56 p-5 rounded-2xl border transition-all duration-300 relative flex flex-col justify-between group min-h-[130px]',
                            'border-gray-200 bg-gray-50 opacity-60 grayscale' => $isPast || $statusDisabled,
                            'border-purple-200 bg-white' => !$isPast && !$statusDisabled
                        ])>
                            <div class="absolute top-4 right-4">
                                @if($isPast)
                                    <span class="text-gray-400"><x-heroicon-m-clock class="w-5 h-5"/></span>
                                @else
                                    <span class="text-purple-500 animate-pulse"><x-heroicon-m-sparkles class="w-5 h-5"/></span>
                                @endif
                            </div>
                            <div class="mt-1">
                                <div class="text-[10px] font-black uppercase tracking-wider mb-2 text-purple-600">
                                    {{ \Carbon\Carbon::parse($voucher->valid_from)->format('M Y') }}
                                </div>
                                <h5 class="font-bold text-slate-900 text-sm leading-tight line-clamp-2 mb-1">
                                    {{ $voucher->title }}
                                </h5>
                                <div class="text-xs font-mono text-slate-500">{{ $voucher->code }}</div>
                            </div>
                            <div class="mt-3 inline-flex items-center justify-center gap-1.5 w-full text-[10px] font-black text-white bg-purple-500 px-3 py-1.5 rounded-lg shadow-md shadow-purple-200">
                                {{ $voucher->type === 'percent' ? $voucher->value . '%' : number_format($voucher->value / 100, 2) . ' €' }} Rabatt
                            </div>
                        </div>
                    </div>
                    <button
                        wire:click="toggleVoucherStatus('{{ $voucher->id }}')"
                        class="absolute top-2 left-2 w-8 h-8 bg-white rounded-full shadow-md border border-gray-100 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 z-20 {{ $voucher->is_active ? 'text-green-500' : 'text-red-500' }}"
                        title="{{ $voucher->is_active ? 'Pausieren' : 'Aktivieren' }}">
                        <x-heroicon-m-power class="w-4 h-4" />
                    </button>
                </div>
            @endforeach
        </div>

        {{-- VOUCHER EDITOR --}}
        @if($editingVoucherId)
            <div class="mt-10 animate-fade-in-up">
                <div class="bg-slate-50 rounded-[2.5rem] border border-slate-200 overflow-hidden relative shadow-lg">
                    <div class="bg-white px-8 py-5 border-b border-slate-100 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white flex items-center justify-center shadow-purple-200 shadow-lg">
                                <x-heroicon-m-ticket class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="font-black text-slate-800 text-lg leading-none">Gutschein bearbeiten</h4>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">{{ $edit_voucher_title }}</p>
                            </div>
                        </div>
                        <button wire:click="cancelEditVoucher" class="text-slate-400 hover:text-slate-600">
                            <x-heroicon-m-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="p-8 grid grid-cols-1 lg:grid-cols-2 gap-10">
                        {{-- Linke Spalte --}}
                        <div class="space-y-8">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="group">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5 ml-1">Gutscheincode</label>
                                    <input type="text" wire:model="edit_voucher_code" class="w-full bg-white border border-slate-200 font-mono text-purple-600 font-bold text-sm rounded-2xl px-5 py-4 focus:ring-4 focus:ring-purple-100 outline-none uppercase shadow-sm">
                                </div>
                                <div class="group">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5 ml-1">Wert</label>
                                    <div class="flex gap-2">
                                        <input type="number" wire:model="edit_voucher_value" class="w-full bg-white border border-slate-200 font-bold text-sm rounded-2xl px-4 py-4 focus:ring-4 focus:ring-purple-100 outline-none shadow-sm">
                                        <select wire:model="edit_voucher_type" class="bg-white border border-slate-200 rounded-2xl px-3 font-bold text-xs outline-none">
                                            <option value="percent">%</option>
                                            <option value="fixed">€</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex flex-col gap-4 relative overflow-hidden">
                                <div class="absolute right-0 top-0 w-16 h-16 bg-purple-50 rounded-bl-full"></div>
                                <div class="flex items-center justify-between z-10">
                                    <label class="block text-[10px] font-black text-purple-500 uppercase tracking-widest">Timing & Gültigkeit</label>
                                </div>
                                <div class="flex gap-6 z-10">
                                    <div>
                                        <span class="text-[9px] text-slate-400 block mb-1">Versand (Offset)</span>
                                        <div class="flex items-center gap-2">
                                            <input type="number" wire:model="edit_voucher_offset" class="w-16 bg-purple-50 border border-purple-100 text-purple-700 font-black text-center rounded-lg py-1">
                                            <span class="text-xs font-bold text-slate-500">Tage vorher</span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-[9px] text-slate-400 block mb-1">Gültigkeit</span>
                                        <div class="flex items-center gap-2">
                                            <input type="number" wire:model="edit_voucher_validity" class="w-16 bg-slate-50 border border-slate-200 text-slate-700 font-black text-center rounded-lg py-1">
                                            <span class="text-xs font-bold text-slate-500">Tage lang</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="bg-gray-50 px-8 py-6 border-t border-slate-200 flex justify-end gap-4">
                        <button wire:click="cancelEditVoucher" class="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600">Abbrechen</button>
                        <button wire:click="saveVoucher" class="px-8 py-3 rounded-xl bg-slate-900 text-white font-black text-xs uppercase tracking-widest hover:bg-purple-600 transition-all shadow-lg hover:shadow-purple-500/30">Speichern</button>
                    </div>
                </div>
            </div>
        @endif


        {{-- ---------------------------------------------------------------- --}}
        {{-- MODUS 2: MANUELLE VERWALTUNG (ORANGE) --}}
        {{-- ---------------------------------------------------------------- --}}
    @else
        <div class="animate-fade-in">

            {{-- Create Button (wenn nicht im Edit Mode) --}}
            @if(!$isCreatingManual && !$isEditingManual)
                <div class="mb-8 flex justify-end">
                    <button wire:click="createManualCoupon" class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-orange-200 transition-all flex items-center gap-2">
                        <x-heroicon-m-plus class="w-4 h-4" /> Neuer Gutschein
                    </button>
                </div>
            @endif

            {{-- Formular --}}
            @if($isCreatingManual || $isEditingManual)
                <div class="animate-fade-in-up bg-white rounded-3xl p-6 border border-orange-100 shadow-sm mb-10 relative z-10">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {{-- Code --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide">Code <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" wire:model="manual_code" class="w-full pl-10 py-3 border-gray-200 rounded-xl focus:ring-orange-500 focus:border-orange-500 uppercase font-mono font-bold text-gray-900 tracking-wider shadow-sm bg-gray-50 focus:bg-white transition-colors" placeholder="z.B. SOMMER24">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                    <x-heroicon-m-ticket class="h-5 w-5" />
                                </div>
                            </div>
                            @error('manual_code') <span class="text-red-500 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                        </div>
                        {{-- Typ --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide">Rabatt-Typ <span class="text-red-500">*</span></label>
                            <select wire:model.live="manual_type" class="w-full py-3 border-gray-200 rounded-xl focus:ring-orange-500 focus:border-orange-500 cursor-pointer shadow-sm bg-white text-sm font-medium">
                                <option value="fixed">Fester Betrag (€)</option>
                                <option value="percent">Prozentual (%)</option>
                            </select>
                        </div>
                        {{-- Wert --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide">
                                Wert ({{ $manual_type == 'fixed' ? 'Euro' : '%' }}) <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="number" step="{{ $manual_type == 'fixed' ? '0.01' : '1' }}" wire:model="manual_value" class="w-full pl-10 py-3 border-gray-200 rounded-xl focus:ring-orange-500 focus:border-orange-500 font-bold shadow-sm bg-gray-50 focus:bg-white" placeholder="{{ $manual_type == 'fixed' ? '10.00' : '15' }}">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 font-bold text-sm">
                                    {{ $manual_type == 'fixed' ? '€' : '%' }}
                                </div>
                            </div>
                            @error('manual_value') <span class="text-red-500 text-xs mt-1 block font-bold">{{ $message }}</span> @enderror
                        </div>
                        {{-- Mindestbestellwert --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide flex items-center gap-1">
                                Mindestbestellwert (€)
                                <span class="text-[9px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <div class="relative">
                                <input type="number" step="0.01" wire:model="manual_min_order_value" class="w-full pl-10 py-3 border-gray-200 rounded-xl focus:ring-orange-500 focus:border-orange-500 shadow-sm bg-gray-50 focus:bg-white" placeholder="z.B. 50.00">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">€</div>
                            </div>
                        </div>
                        {{-- Gültig bis --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide flex items-center gap-1">
                                Gültig bis
                                <span class="text-[9px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <input type="date" wire:model="manual_valid_until" class="w-full py-3 border-gray-200 rounded-xl focus:ring-orange-500 focus:border-orange-500 shadow-sm cursor-pointer bg-gray-50 focus:bg-white text-sm">
                        </div>
                        {{-- Limit --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide flex items-center gap-1">
                                Gesamtlimit (Anzahl)
                                <span class="text-[9px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <input type="number" wire:model="manual_usage_limit" class="w-full py-3 border-gray-200 rounded-xl focus:ring-orange-500 focus:border-orange-500 shadow-sm bg-gray-50 focus:bg-white" placeholder="z.B. 100">
                        </div>
                    </div>
                    <div class="mt-8 pt-6 border-t border-orange-50 flex flex-col md:flex-row justify-between items-center gap-4">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" wire:model="manual_is_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500 transition-colors"></div>
                            </div>
                            <span class="text-sm font-bold text-gray-700 group-hover:text-gray-900 select-none">Gutschein ist aktiv</span>
                        </label>
                        <div class="flex gap-3 w-full md:w-auto">
                            <button wire:click="cancelManualCoupon" class="flex-1 md:flex-none px-6 py-3 border border-gray-200 text-gray-500 rounded-xl hover:bg-white hover:text-gray-700 font-bold transition uppercase tracking-wider text-xs">Abbrechen</button>
                            <button wire:click="saveManualCoupon" class="flex-1 md:flex-none px-8 py-3 bg-orange-600 text-white rounded-xl hover:bg-orange-700 font-bold transition shadow-lg shadow-orange-200 flex justify-center items-center gap-2 uppercase tracking-wider text-xs">
                                <x-heroicon-m-check class="w-4 h-4" />
                                Speichern
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- TABELLE --}}
            <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-100 shadow-sm bg-white relative z-10">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 bg-gray-50/80">
                        <th class="px-6 py-4">Code</th>
                        <th class="px-6 py-4">Wert</th>
                        <th class="px-6 py-4">Mindestwert</th>
                        <th class="px-6 py-4">Gültigkeit</th>
                        <th class="px-6 py-4">Nutzung</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    @forelse($manualCoupons as $c)
                        <tr wire:key="coupon-row-{{ $c->id }}" class="hover:bg-orange-50/30 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-m-ticket class="w-4 h-4 text-gray-300" />
                                    <span class="font-mono font-bold text-gray-900 text-sm tracking-wider">{{ $c->code }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $c->type == 'percent' ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-green-50 text-green-600 border border-green-100' }}">
                                            @if($c->type == 'fixed')
                                                {{ number_format($c->value / 100, 2, ',', '.') }} €
                                            @else
                                                {{ $c->value }} %
                                            @endif
                                        </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500 font-medium">
                                {{ $c->min_order_value ? number_format($c->min_order_value / 100, 2, ',', '.') . ' €' : '-' }}
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($c->valid_until)
                                    <div class="flex flex-col">
                                                <span class="{{ $c->valid_until->isPast() ? 'text-red-500 font-bold' : 'text-gray-700 font-medium' }}">
                                                    {{ $c->valid_until->format('d.m.Y') }}
                                                </span>
                                        @if($c->valid_until->isPast())
                                            <span class="text-[9px] text-red-400 uppercase font-bold tracking-wider">Abgelaufen</span>
                                        @else
                                            <span class="text-[9px] text-gray-400 uppercase tracking-wide">in {{ $c->valid_until->diffInDays(now()) }} Tagen</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1">
                                                <x-heroicon-o-shopping-bag class="w-3 h-3" /> Unbegrenzt
                                            </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-700">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold">{{ $c->used_count }}</span>
                                    @if($c->usage_limit)
                                        <span class="text-gray-400">/ {{ $c->usage_limit }}</span>
                                        @if($c->used_count >= $c->usage_limit)
                                            <span class="text-[9px] text-red-500 uppercase font-bold bg-red-50 px-1.5 rounded border border-red-100">Voll</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if($c->isValid())
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200 uppercase tracking-wider">
                                                <span class="relative flex h-1.5 w-1.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span></span>
                                                Aktiv
                                            </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-200 uppercase tracking-wider">
                                                <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                                Inaktiv
                                            </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="editManualCoupon('{{ $c->id }}')" class="p-2 rounded-lg text-gray-400 hover:bg-orange-50 hover:text-orange-600 transition-colors" title="Bearbeiten">
                                        <x-heroicon-m-pencil class="w-4 h-4" />
                                    </button>
                                    <button wire:click="deleteManualCoupon('{{ $c->id }}')" wire:confirm="Möchten Sie den Gutschein '{{ $c->code }}' wirklich löschen?" class="p-2 rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors" title="Löschen">
                                        <x-heroicon-m-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400 font-medium italic">
                                Noch keine manuellen Gutscheine erstellt.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
@endif

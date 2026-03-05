@php
    $isAuto = $voucherSectionMode === 'auto';
    $bgClass = $isAuto ? 'from-purple-500 to-indigo-600' : 'from-orange-500 to-red-600';
    $currentMonth = now()->month; // Aktueller Monat für die Highlight-Logik
@endphp

<section class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-10 relative overflow-hidden transition-all duration-500 w-full mt-6">
    <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b {{ $bgClass }} opacity-60 transition-colors duration-500"></div>

    <div class="flex flex-col md:flex-row justify-between items-start mb-10 gap-6 relative z-10">
        <div>
            <h3 class="text-2xl font-serif font-bold text-white tracking-tight flex items-center gap-3 transition-all duration-300">
                <i class="{{ $isAuto ? 'solar-ticket-bold-duotone text-purple-400' : 'solar-tag-bold-duotone text-orange-400' }} text-2xl"></i>
                {{ $isAuto ? 'Saisonale Auto-Gutscheine' : 'Manuelle Gutscheine' }}
            </h3>
            <div class="flex items-center gap-3 mt-2">
                <span class="text-[10px] font-mono text-gray-500 bg-black/40 px-2 py-0.5 rounded border border-gray-800 uppercase tracking-tighter">
                    {{ $isAuto ? 'FRONTEND SLIDER AUTOPILOT' : 'MANUELLE VERWALTUNG' }}
                </span>
                @if($isAuto)
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.5)]"></span>
                    </span>
                @endif
            </div>
        </div>

        <button wire:click="toggleVoucherSectionMode" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-950 text-gray-400 hover:text-white border border-gray-800 shadow-inner hover:scale-[1.02]">
            @if($isAuto)
                <span>Manuell verwalten</span> <x-heroicon-m-arrow-right class="w-4 h-4 text-orange-400" />
            @else
                <x-heroicon-m-arrow-left class="w-4 h-4 text-purple-400" /> <span>Zu Autopilot</span>
            @endif
        </button>
    </div>

    @if($isAuto)
        <div class="animate-fade-in w-full mt-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($autoVouchers as $voucher)
                    @php
                        $statusDisabled = !$voucher->is_active;
                        // Prüfe, ob der Start-Monat des Gutscheins dem aktuellen Monat entspricht
                        $isCurrentMonth = $voucher->valid_from && \Carbon\Carbon::parse($voucher->valid_from)->month === $currentMonth;
                    @endphp

                    <div class="relative group">
                        <div @class([
                            'w-full h-full p-6 rounded-[2rem] border transition-all duration-300 relative flex flex-col justify-between group min-h-[140px] backdrop-blur-sm shadow-inner',
                            'border-gray-800 bg-gray-950/60 opacity-60 grayscale-[0.6]' => $statusDisabled || !$isCurrentMonth,
                            'border-purple-500/80 bg-gray-900 shadow-[0_0_25px_rgba(168,85,247,0.2)] scale-[1.02] z-10' => !$statusDisabled && $isCurrentMonth
                        ])>

                            {{-- Monat Badge (Oben rechts) --}}
                            @if($voucher->valid_from)
                                <div class="absolute top-5 right-5 text-[9px] font-black uppercase tracking-widest {{ $isCurrentMonth && !$statusDisabled ? 'text-purple-400' : 'text-gray-600' }}">
                                    {{ \Carbon\Carbon::parse($voucher->valid_from)->translatedFormat('M') }}
                                </div>
                            @endif

                            <div class="relative z-10 pr-12">
                                <h5 class="font-bold text-gray-100 text-sm leading-snug mb-3 group-hover:text-white transition-colors" title="{{ $voucher->title }}">
                                    {{ $voucher->title }}
                                </h5>
                                <div class="inline-flex items-center gap-2 bg-black/40 border border-gray-800 px-3 py-1.5 rounded-xl">
                                    <span class="text-[9px] font-mono font-black text-gray-400 uppercase tracking-widest truncate">{{ $voucher->code }}</span>
                                </div>
                            </div>

                            <div class="mt-5 mb-2"> {{-- Margin Bottom hinzugefügt, damit Badge nicht unter Button rutscht --}}
                                <span class="inline-block text-[10px] font-black {{ $isCurrentMonth && !$statusDisabled ? 'text-purple-400 bg-purple-500/10 border-purple-500/30' : 'text-gray-500 bg-gray-800/50 border-gray-700' }} px-3 py-1.5 rounded-lg border uppercase tracking-widest">
                                    {{ $voucher->type === 'percent' ? $voucher->value . '%' : number_format($voucher->value / 100, 2) . ' €' }} Rabatt
                                </span>
                            </div>
                        </div>

                        {{-- Toggle Button (Jetzt UNTEN RECHTS) --}}
                        <button wire:click.stop="toggleVoucherStatus('{{ $voucher->id }}')"
                                class="absolute bottom-4 right-4 w-8 h-8 rounded-xl flex items-center justify-center transition-all duration-300 z-30 shadow-lg border {{ $voucher->is_active ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/20' : 'bg-gray-800 border-gray-700 text-gray-500 hover:bg-gray-700' }}"
                                title="{{ $voucher->is_active ? 'Pausieren' : 'Aktivieren' }}">
                            <x-heroicon-m-power class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

    @else
        <div class="animate-fade-in w-full mt-4">
            @if(!$isCreatingManual && !$isEditingManual)
                <div class="mb-8 flex justify-end">
                    <button wire:click="createManualCoupon" class="bg-orange-600 hover:bg-orange-500 text-white px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(234,88,12,0.4)] transition-all flex items-center gap-2 hover:scale-[1.02]">
                        <x-heroicon-m-plus class="w-4 h-4" /> Neuer Gutschein
                    </button>
                </div>
            @endif

            @if($isCreatingManual || $isEditingManual)
                <div class="animate-fade-in-up bg-gray-950/80 backdrop-blur-xl rounded-[3rem] p-8 lg:p-10 border border-gray-800 shadow-[0_30px_100px_rgba(0,0,0,0.6)] mb-10 relative z-10">
                    <div class="flex items-center gap-3 mb-8 border-b border-gray-800 pb-5">
                        <div class="w-10 h-10 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-400 flex items-center justify-center">
                            <x-heroicon-s-ticket class="w-5 h-5" />
                        </div>
                        <h4 class="font-serif font-bold text-white text-xl tracking-tight">{{ $isEditingManual ? 'Gutschein bearbeiten' : 'Gutschein erstellen' }}</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
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
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Rabatt-Typ <span class="text-orange-500">*</span></label>
                            <select wire:model.live="manual_type" class="w-full px-5 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 cursor-pointer shadow-inner text-sm font-bold text-white outline-none">
                                <option value="fixed" class="bg-gray-950">Fester Betrag (€)</option>
                                <option value="percent" class="bg-gray-950">Prozentual (%)</option>
                            </select>
                        </div>
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
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                Mindestbestellwert <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <div class="relative">
                                <input type="number" step="0.01" wire:model="manual_min_order_value" class="w-full pl-10 pr-4 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 shadow-inner text-white" placeholder="z.B. 50.00">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-500">€</div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                Gültig bis <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <input type="date" wire:model="manual_valid_until" class="w-full px-5 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 shadow-inner cursor-pointer text-sm text-white [color-scheme:dark]">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1 flex items-center gap-2">
                                Limit (Anzahl) <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded">Optional</span>
                            </label>
                            <input type="number" wire:model="manual_usage_limit" class="w-full px-5 py-4 bg-gray-900 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 shadow-inner text-white" placeholder="z.B. 100">
                        </div>
                    </div>

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
                                <x-heroicon-m-check class="w-4 h-4" /> Speichern
                            </button>
                        </div>
                    </div>
                </div>
            @endif

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

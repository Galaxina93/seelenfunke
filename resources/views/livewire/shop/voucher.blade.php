<div class="p-6 bg-white rounded-lg shadow-sm">
    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-xl font-bold font-serif text-gray-800">Gutscheinverwaltung</h2>

        <div class="flex gap-4 w-full md:w-auto">
            {{-- Suche --}}
            <div class="relative w-full md:w-64">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Gutschein suchen..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>
            {{-- Neuer Button --}}
            <button wire:click="create" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition flex items-center gap-2 shadow-sm font-bold text-sm whitespace-nowrap">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                Neuer Gutschein
            </button>
        </div>
    </div>

    {{-- FORMULAR (Create/Edit) --}}
    @if($isCreating || $isEditing)
        <div class="mb-8 p-6 bg-gray-50 border border-gray-200 rounded-xl animate-fade-in-up shadow-sm relative overflow-hidden">

            <h3 class="font-bold text-lg mb-6 flex items-center gap-2 text-gray-800 relative z-10">
                @if($isEditing)
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    Gutschein bearbeiten
                @else
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Neuer Gutschein
                @endif
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 relative z-10">
                {{-- Code --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Code <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="text" wire:model="code" class="w-full pl-10 border-gray-300 rounded-lg focus:ring-primary focus:border-primary uppercase font-mono font-bold text-gray-800 tracking-wider" placeholder="z.B. SOMMER24">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                        </div>
                    </div>
                    @error('code') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Typ --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Rabatt-Typ <span class="text-red-500">*</span></label>
                    <select wire:model.live="type" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        <option value="fixed">Fester Betrag (€)</option>
                        <option value="percent">Prozentual (%)</option>
                    </select>
                </div>

                {{-- Wert --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Wert ({{ $type == 'fixed' ? 'Euro' : '%' }}) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="number" step="{{ $type == 'fixed' ? '0.01' : '1' }}" wire:model="value" class="w-full pl-10 border-gray-300 rounded-lg focus:ring-primary focus:border-primary font-bold" placeholder="{{ $type == 'fixed' ? '10.00' : '15' }}">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            @if($type == 'fixed')
                                <span class="font-bold">€</span>
                            @else
                                <span class="font-bold">%</span>
                            @endif
                        </div>
                    </div>
                    @error('value') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Mindestbestellwert --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center gap-1">
                        Mindestbestellwert (€)
                        <span class="text-xs font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Optional</span>
                    </label>
                    <div class="relative">
                        <input type="number" step="0.01" wire:model="min_order_value" class="w-full pl-10 border-gray-300 rounded-lg focus:ring-primary focus:border-primary" placeholder="z.B. 50.00">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">€</div>
                    </div>
                </div>

                {{-- Gültig bis --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center gap-1">
                        Gültig bis
                        <span class="text-xs font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Optional</span>
                    </label>
                    <input type="date" wire:model="valid_until" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                </div>

                {{-- Limit --}}
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center gap-1">
                        Gesamtlimit (Anzahl)
                        <span class="text-xs font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Optional</span>
                    </label>
                    <input type="number" wire:model="usage_limit" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" placeholder="z.B. 100">
                </div>
            </div>

            {{-- Status & Buttons --}}
            <div class="mt-8 pt-6 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4 relative z-10">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </div>
                    <span class="text-sm font-bold text-gray-700 group-hover:text-gray-900 select-none">Gutschein ist aktiv</span>
                </label>

                <div class="flex gap-3 w-full md:w-auto">
                    <button wire:click="cancel" class="flex-1 md:flex-none px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 font-bold transition">Abbrechen</button>
                    <button wire:click="save" class="flex-1 md:flex-none px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark font-bold transition shadow-sm flex justify-center items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- LISTE --}}
    <div class="overflow-x-auto bg-white rounded-xl border border-gray-100 shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50/50">
                <th class="px-6 py-4">Code</th>
                <th class="px-6 py-4">Wert</th>
                <th class="px-6 py-4">Mindestwert</th>
                <th class="px-6 py-4">Gültigkeit</th>
                <th class="px-6 py-4">Nutzung</th>
                <th class="px-6 py-4">Status</th>
                <th class="px-6 py-4 text-right">Aktionen</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($coupons as $c)
                <tr wire:key="coupon-row-{{ $c->id }}" class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                            <span class="font-mono font-bold text-primary text-base tracking-wider">{{ $c->code }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-bold text-gray-900">
                        @if($c->type == 'fixed')
                            {{ number_format($c->value / 100, 2, ',', '.') }} €
                        @else
                            {{ $c->value }} %
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $c->min_order_value ? number_format($c->min_order_value / 100, 2, ',', '.') . ' €' : '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if($c->valid_until)
                            <div class="flex flex-col">
                                    <span class="{{ $c->valid_until->isPast() ? 'text-red-600 font-bold' : 'text-gray-900' }}">
                                        {{ $c->valid_until->format('d.m.Y') }}
                                    </span>
                                @if($c->valid_until->isPast())
                                    <span class="text-[10px] text-red-500 uppercase font-bold">Abgelaufen</span>
                                @else
                                    <span class="text-xs text-gray-400">in {{ $c->valid_until->diffForHumans(null, true) }}</span>
                                @endif
                            </div>
                        @else
                            <span class="text-gray-500 flex items-center gap-1">
                                    <svg class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 3.214L13 21l-2.286-6.857L5 12l5.714-3.214L13 3z" /></svg>
                                    Unbegrenzt
                                </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <div class="flex items-center gap-2">
                            <span class="font-bold">{{ $c->used_count }}</span>
                            @if($c->usage_limit)
                                <span class="text-gray-400">von {{ $c->usage_limit }}</span>
                                @if($c->used_count >= $c->usage_limit)
                                    <span class="text-[10px] text-red-500 uppercase font-bold bg-red-50 px-1.5 rounded">Aufgebraucht</span>
                                @endif
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($c->isValid())
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span></span>
                                    Aktiv
                                </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500">
                                    <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                    Inaktiv
                                </span>
                        @endif
                    </td>

                    {{-- HIER DIE KORREKTUR: Einfache Anführungszeichen um die ID --}}
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            {{-- Bearbeiten Button --}}
                            <button wire:click="edit('{{ $c->id }}')"
                                    class="p-2 rounded-lg text-gray-500 hover:bg-blue-50 hover:text-blue-600 transition-colors focus:ring-2 focus:ring-blue-500/50 outline-none"
                                    title="Bearbeiten">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>

                            {{-- Löschen Button --}}
                            <button wire:click="delete('{{ $c->id }}')"
                                    wire:confirm="Möchten Sie den Gutschein '{{ $c->code }}' wirklich löschen?"
                                    class="p-2 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors focus:ring-2 focus:ring-red-500/50 outline-none"
                                    title="Löschen">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center">
                            <svg class="h-12 w-12 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" /></svg>
                            <p class="text-lg font-medium text-gray-900 mb-1">Keine Gutscheine gefunden</p>
                            <p class="text-sm mb-4">Erstellen Sie Ihren ersten Gutschein-Code.</p>
                            <button wire:click="create" class="text-primary hover:underline font-bold text-sm">Jetzt erstellen</button>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- PAGINATION --}}
    <div class="mt-6">
        {{ $coupons->links() }}
    </div>

</div>

@php
    $missingReceipts = $this->specialsMissingReceipts;
    $missingCount = $missingReceipts->count();
    $allGood = $missingCount === 0;
@endphp

<div x-data="{ open: false }" class="bg-white rounded-3xl shadow-md border overflow-hidden transition-all duration-300 {{ $allGood ? 'border-green-200' : 'border-orange-200' }}">

    {{-- Header (Clickable) --}}
    <div @click="open = !open" class="p-6 cursor-pointer flex items-center justify-between hover:bg-gray-50 transition-colors">
        <div class="flex items-center gap-4">
            {{-- Icon Status --}}
            <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 {{ $allGood ? 'bg-green-100 text-green-600' : 'bg-orange-100 text-orange-600' }}">
                @if($allGood)
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                @else
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                @endif
            </div>

            <div>
                <h3 class="text-lg font-bold {{ $allGood ? 'text-green-800' : 'text-gray-800' }}">
                    @if($allGood)
                        Beleg-Check: Alles vollständig
                    @else
                        Beleg-Check: {{ $missingCount }} Belege fehlen
                    @endif
                </h3>
                <p class="text-sm {{ $allGood ? 'text-green-600' : 'text-gray-500' }}">
                    @if($allGood)
                        Hervorragend! Zu allen Sonderausgaben sind Dateien hinterlegt.
                    @else
                        Laden Sie die fehlenden Rechnungen hoch, um die Buchhaltung sauber zu halten.
                    @endif
                </p>
            </div>
        </div>

        {{-- Chevron Icon --}}
        <svg class="w-6 h-6 text-gray-400 transform transition-transform duration-300" :class="{'rotate-180': open}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </div>

    {{-- Content (Table) --}}
    @if(!$allGood)
        <div x-show="open" x-collapse style="display: none;">
            <div class="border-t border-gray-100 bg-gray-50/50 p-6 pt-0">
                <div class="overflow-x-auto mt-6 bg-white rounded-xl shadow-sm border border-gray-200">
                    <table class="w-full text-left border-collapse">
                        <thead>
                        <tr class="text-xs font-bold text-gray-400 uppercase border-b border-gray-100 bg-gray-50">
                            <th class="py-3 pl-6">Datum</th>
                            <th class="py-3">Titel</th>
                            <th class="py-3">Betrag</th>
                            <th class="py-3">Upload</th>
                        </tr>
                        </thead>
                        <tbody class="text-sm">
                        @foreach($missingReceipts as $missingSpecial)
                            {{-- WICHTIG: wire:key verhindert, dass Livewire bei Updates durcheinander kommt --}}
                            <tr wire:key="missing-{{ $missingSpecial->id }}" class="group hover:bg-orange-50/30 transition-colors border-b border-gray-100 last:border-0">
                                <td class="py-3 pl-6 text-gray-500">
                                    {{ \Carbon\Carbon::parse($missingSpecial->execution_date)->format('d.m.Y') }}
                                </td>
                                <td class="py-3 font-medium text-gray-700">
                                    {{ $missingSpecial->title }}
                                    <span class="text-xs text-gray-400 font-normal ml-1">({{ $missingSpecial->category }})</span>
                                </td>
                                <td class="py-3 font-mono text-gray-600">
                                    {{ number_format(abs($missingSpecial->amount), 2, ',', '.') }} €
                                </td>
                                <td class="py-3">
                                    <div class="relative flex items-center gap-3">
                                        {{-- 1. SPINNER: Wird per CSS angezeigt, wenn ID matcht (flex), sonst unsichtbar (hidden) --}}
                                        <div class="{{ $uploadingMissingSpecialId === $missingSpecial->id ? 'flex' : 'hidden' }} text-xs text-orange-500 font-bold items-center gap-1">
                                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                            Upload...
                                        </div>

                                        {{-- 2. INPUT LABEL: Wird per CSS ausgeblendet (hidden), wenn ID matcht, bleibt aber im DOM! --}}
                                        <label class="{{ $uploadingMissingSpecialId === $missingSpecial->id ? 'hidden' : 'inline-flex' }} cursor-pointer items-center gap-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-600 hover:border-orange-300 hover:text-orange-600 transition-all shadow-sm"
                                               wire:mousedown="$set('uploadingMissingSpecialId', '{{ $missingSpecial->id }}')">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            FILE

                                            {{-- Der Input ist jetzt immer da, nur das Label drumherum wird versteckt --}}
                                            <input type="file" class="hidden"
                                                   wire:model="quickUploadFile"
                                                   accept=".pdf,.jpg,.png,.jpeg,.xml">
                                        </label>

                                        {{-- Optional: Fehler anzeigen, falls Validierung fehlschlägt, damit es nicht "hängt" --}}
                                        @if($uploadingMissingSpecialId === $missingSpecial->id)
                                            @error('quickUploadFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>

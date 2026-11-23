<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="space-y-10 animate-fade-in-up">
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4500)" class="bg-white dark:bg-gray-900 border-l-4 border-[var(--theme-color)] p-4 rounded-r-xl shadow-sm text-gray-900 dark:text-white text-xs font-black uppercase tracking-widest flex items-center gap-3">
            <svg class="w-4 h-4 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-2">
                <h4 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Standard Lieferzeiten</h4>
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-black text-[10px] cursor-help"
                      title="Steuert das im Shop, Konfigurator, Warenkorb und Google-Schema angezeigte Lieferfenster in echten Werktagen (Mo bis Sa ohne Feiertage). Bei aktivem Autopilot schaltet die Produktions-Last auf dem Dashboard automatisch zwischen den 4 Stufen um.">!</span>
            </div>

            {{-- Synchronisierter Kapazitäts-Autopilot Schalter --}}
            <div class="flex items-center justify-between sm:justify-end gap-3 px-3.5 py-2 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-300">
                    <span>Autopilot</span>
                    <span class="px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-mono"
                          title="Aktuelle Manufaktur-Auslastung laut Produktions-Last">
                        {{ $capacityPercentage }} % · Stufe {{ $capacityLevel }}
                    </span>
                </div>
                <button type="button"
                        wire:click="toggleCapacityAutopilot"
                        title="Synchron mit dem Autopilot-Schalter unter Produktions-Last auf dem Dashboard"
                        role="switch"
                        aria-checked="{{ $capacityAutopilotEnabled ? 'true' : 'false' }}"
                        class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $capacityAutopilotEnabled ? 'bg-[var(--theme-color)]' : 'bg-gray-300 dark:bg-gray-700' }}">
                    <span class="sr-only">Kapazitäts-Autopilot umschalten</span>
                    <span aria-hidden="true" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200 ease-in-out {{ $capacityAutopilotEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            @foreach($this->deliveryTimes as $time)
                @php
                    $isActive = (bool) $time->is_active;
                    $dotClass = match($time->color) {
                        'yellow' => 'bg-amber-500',
                        'red' => 'bg-red-500',
                        default => 'bg-emerald-500'
                    };
                    $badgeClass = match($time->color) {
                        'yellow' => 'text-amber-700 dark:text-amber-300 bg-amber-500/10 border-amber-500/30',
                        'red' => 'text-red-700 dark:text-red-300 bg-red-500/10 border-red-500/30',
                        default => 'text-emerald-700 dark:text-emerald-300 bg-emerald-500/10 border-emerald-500/30'
                    };
                @endphp
                <div class="relative p-5 rounded-2xl border transition-all duration-200 flex flex-col justify-between min-h-[165px] {{ $isActive ? 'border-[var(--theme-color)] bg-white dark:bg-gray-900 shadow-sm' : 'bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700' }}">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h5 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $dotClass }}"></span>
                                <span class="truncate">{{ $time->name }}</span>
                            </h5>
                            @if(!empty($time->description))
                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-black text-[9px] shrink-0 cursor-help"
                                      title="{{ $time->description }}">!</span>
                            @endif
                        </div>
                        <div class="mb-4">
                            <span class="text-[10px] font-mono font-bold border px-2.5 py-1 rounded-lg inline-flex items-center gap-1.5 {{ $badgeClass }}">
                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>{{ $time->min_days }}-{{ $time->max_days }} Werktage</span>
                            </span>
                        </div>
                    </div>

                    <button type="button"
                            wire:click="setActiveDeliveryTime('{{ $time->id }}')"
                            class="w-full py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $isActive ? 'bg-[var(--theme-color)] text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white' }}">
                        {{ $isActive ? 'Im Shop aktiv' : 'Im Shop nutzen' }}
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- URLAUBSMODUS --}}
        <div class="bg-white dark:bg-gray-950/80 rounded-[2rem] border p-6 sm:p-8 transition-all duration-300 {{ $isVacationMode ? 'border-[var(--theme-color)]' : 'border-gray-200 dark:border-gray-800' }}">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-2">
                    <h4 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Urlaubsmodus</h4>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-black text-[10px] cursor-help"
                          title="Berechnet das Lieferdatum automatisch ab dem eingetragenen Urlaubsende und pausiert währenddessen Eingriffe des Kapazitäts-Autopiloten.">!</span>
                </div>

                <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4 border-t border-gray-200 dark:border-gray-800 sm:border-0 pt-4 sm:pt-0">
                    @if($this->vacationWishesCount > 0)
                        <span class="text-[10px] font-bold bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-lg" title="Erhaltene Urlaubsgrüße von Kunden">
                            {{ $this->vacationWishesCount }} Grüße
                        </span>
                    @else
                        <div></div>
                    @endif
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" wire:model.live="isVacationMode" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-300 dark:bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[var(--theme-color)]"></div>
                    </label>
                </div>
            </div>

            <div class="space-y-5 transition-all duration-300 {{ $isVacationMode ? 'opacity-100' : 'opacity-40 pointer-events-none' }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Von</label>
                        <input type="date" wire:model.live="vacationStartDate" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:border-[var(--theme-color)] outline-none">
                        @error('vacationStartDate') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Bis</label>
                        <input type="date" wire:model.live="vacationEndDate" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:border-[var(--theme-color)] outline-none">
                        @error('vacationEndDate') <span class="text-[11px] font-bold text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Kunden-Meldung</label>
                    <textarea wire:model.blur="vacationDescription" rows="3" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:border-[var(--theme-color)] outline-none resize-none"></textarea>
                </div>

                <button type="button"
                        x-data="{ saved: false }"
                        @click="$wire.saveSettings().then((ok) => { if (ok) { saved = true; setTimeout(() => saved = false, 2500); } })"
                        :class="saved ? 'bg-emerald-600 text-white' : 'bg-[var(--theme-color)] text-white dark:text-gray-900 hover:brightness-105'"
                        class="w-full py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <span x-show="!saved" wire:loading.remove wire:target="saveSettings">Speichern</span>
                    <span x-show="!saved" wire:loading wire:target="saveSettings">Speichere...</span>
                    <span x-show="saved" x-cloak>Gespeichert</span>
                </button>
            </div>
        </div>

        {{-- KRANKHEITSMODUS --}}
        <div class="bg-white dark:bg-gray-950/80 rounded-[2rem] border p-6 sm:p-8 transition-all duration-300 {{ $isSickMode ? 'border-red-500/60' : 'border-gray-200 dark:border-gray-800' }}">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-2">
                    <h4 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Krankheitsmodus</h4>
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-black text-[10px] cursor-help"
                          title="Addiert automatisch +6 Werktage Puffer auf die aktuelle Lieferzeit und blendet den Hinweis im Shop ein.">!</span>
                </div>

                <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4 border-t border-gray-200 dark:border-gray-800 sm:border-0 pt-4 sm:pt-0">
                    @if($this->sickWishesCount > 0)
                        <span class="text-[10px] font-bold bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-lg" title="Erhaltene Genesungswünsche von Kunden">
                            {{ $this->sickWishesCount }} Grüße
                        </span>
                    @else
                        <div></div>
                    @endif
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" wire:model.live="isSickMode" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-300 dark:bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                    </label>
                </div>
            </div>

            <div class="space-y-5 transition-all duration-300 {{ $isSickMode ? 'opacity-100' : 'opacity-40 pointer-events-none' }}">
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Kunden-Meldung</label>
                    <textarea wire:model.blur="sickDescription" rows="3" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:border-red-500 outline-none resize-none"></textarea>
                </div>

                <button type="button"
                        x-data="{ saved: false }"
                        @click="$wire.saveSettings().then((ok) => { if (ok) { saved = true; setTimeout(() => saved = false, 2500); } })"
                        :class="saved ? 'bg-emerald-600 text-white' : 'bg-red-600 hover:bg-red-500 text-white'"
                        class="w-full py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 cursor-pointer">
                    <span x-show="!saved" wire:loading.remove wire:target="saveSettings">Speichern</span>
                    <span x-show="!saved" wire:loading wire:target="saveSettings">Speichere...</span>
                    <span x-show="saved" x-cloak>Gespeichert</span>
                </button>
            </div>
        </div>
    </div>

    @if(count($this->feedbackLogs) > 0)
        <div class="bg-white dark:bg-gray-900/80 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 mt-8">
            <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white mb-5">Community-Grüße</h3>

            <div class="overflow-x-auto custom-scrollbar bg-white dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800">
                <table class="w-full text-left text-sm min-w-[460px]">
                    <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 text-[9px] uppercase tracking-widest font-black">
                    <tr>
                        <th class="p-4">Kunde</th>
                        <th class="p-4">Gruß</th>
                        <th class="p-4 text-right">Datum</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/50">
                    @foreach($this->feedbackLogs as $fb)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors">
                            <td class="p-4 font-bold text-gray-900 dark:text-white">{{ $fb->user_name ?? 'Unbekannt' }}</td>
                            <td class="p-4">
                                @if($fb->type === 'vacation')
                                    <span class="bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/30 px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest">Urlaubsgruß</span>
                                @else
                                    <span class="bg-red-500/10 text-red-700 dark:text-red-300 border border-red-500/30 px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest">Gute Besserung</span>
                                @endif
                            </td>
                            <td class="p-4 text-right text-gray-500 font-mono text-[10px]">{{ \Carbon\Carbon::parse($fb->created_at)->format('d.m.Y H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
</div>

<div class="space-y-10 animate-fade-in-up">
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="bg-emerald-500/10 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-inner text-emerald-400 text-xs font-black uppercase tracking-widest flex items-center gap-3">
            <svg class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div>
        <div class="flex items-center gap-3 mb-2">
            <h4 class="text-lg font-serif font-bold text-white">Standard Lieferzeiten</h4>
        </div>
        <p class="text-xs text-gray-400 mb-6 font-medium">
            Erstelle verschiedene Lieferzeiten und wähle aus, welche aktuell als Standard im Shop angezeigt werden soll.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach($this->deliveryTimes as $time)
                @php
                    $isActive = $time->is_active;
                    $colorIcon = match($time->color) {
                        'yellow' => '🟡',
                        'red' => '🔴',
                        default => '🟢'
                    };
                    $badgeClass = match($time->color) {
                        'yellow' => 'text-amber-400 bg-amber-500/10 border-amber-500/30',
                        'red' => 'text-red-400 bg-red-500/10 border-red-500/30',
                        default => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30'
                    };
                @endphp
                <div class="relative group p-5 rounded-2xl border transition-all duration-300 flex flex-col justify-between min-h-[160px] {{ $isActive ? 'shadow-[0_0_20px_rgba(197,160,89,0.15)] border-primary/50 bg-gray-900' : 'bg-gray-950 border-gray-800 hover:border-gray-700 shadow-inner' }}">
                    <div>
                        <div class="flex justify-between items-start mb-3">
                            <h5 class="font-bold text-white tracking-wide flex items-center gap-2">
                                {{ $colorIcon }} {{ $time->name }}
                            </h5>
                            <button wire:click="removeDeliveryTime('{{ $time->id }}')" class="text-gray-600 hover:text-red-400 transition-colors" title="Löschen">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        <div class="mb-3">
                            <span class="text-[10px] font-mono border px-2 py-0.5 rounded shadow-inner inline-flex items-center gap-1.5 {{ $badgeClass }}">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ $time->min_days }}-{{ $time->max_days }} Tage
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed line-clamp-3 mb-4">{{ $time->description }}</p>
                    </div>

                    <button wire:click="setActiveDeliveryTime('{{ $time->id }}')" class="w-full py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $isActive ? 'bg-primary text-gray-900 shadow-glow' : 'bg-gray-900 text-gray-400 border border-gray-700 hover:bg-gray-800 hover:text-white' }}">
                        {{ $isActive ? 'Im Shop aktiv' : 'Im Shop nutzen' }}
                    </button>
                </div>
            @endforeach

            @if(!$isAddingNew)
                <button wire:click="openAddForm" class="border-2 border-dashed border-gray-800 rounded-2xl flex flex-col items-center justify-center text-gray-500 hover:text-primary hover:border-primary hover:bg-primary/5 transition-all min-h-[160px] group shadow-inner">
                    <div class="w-10 h-10 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform shadow-inner text-current">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-current transition-colors">Lieferzeit hinzufügen</span>
                </button>
            @else
                <div class="bg-gray-950 p-5 rounded-2xl border border-gray-800 shadow-inner flex flex-col justify-between min-h-[160px] animate-fade-in-up">
                    <div class="space-y-3 mb-4">
                        <input type="text" wire:model="newName" placeholder="Bezeichnung..." class="w-full bg-gray-900 border border-gray-800 text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all shadow-inner">

                        <div class="flex gap-2">
                            <input type="number" wire:model="newMinDays" placeholder="Von" class="w-1/2 bg-gray-900 border border-gray-800 text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all shadow-inner">
                            <input type="number" wire:model="newMaxDays" placeholder="Bis" class="w-1/2 bg-gray-900 border border-gray-800 text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all shadow-inner">
                        </div>

                        <select wire:model="newColor" class="w-full bg-gray-900 border border-gray-800 text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all shadow-inner appearance-none cursor-pointer">
                            <option value="green">🟢 Grün (Normal)</option>
                            <option value="yellow">🟡 Gelb (Verzögert)</option>
                            <option value="red">🔴 Rot (Kritisch)</option>
                        </select>

                        <textarea wire:model="newDescription" placeholder="Beschreibung..." rows="2" class="w-full bg-gray-900 border border-gray-800 text-white rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all shadow-inner resize-none"></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="closeAddForm" class="flex-1 py-2 text-[9px] font-black text-gray-500 hover:text-white uppercase tracking-widest transition-colors">Abbruch</button>
                        <button wire:click="addDeliveryTime" class="flex-1 bg-primary text-gray-900 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-primary-dark transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)]">Speichern</button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-gray-950/80 rounded-[2rem] border border-gray-800 p-6 sm:p-8 shadow-inner relative overflow-hidden transition-all duration-500 {{ $isVacationMode ? 'ring-1 ring-amber-500/50 shadow-[inset_0_0_30px_rgba(245,158,11,0.05)]' : '' }}">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-start sm:items-center gap-3">
                    <span class="p-2 bg-amber-500/10 text-amber-400 rounded-xl border border-amber-500/20 shadow-inner shrink-0">☀️</span>
                    <div>
                        <h4 class="text-lg font-serif font-bold text-white leading-tight">Urlaubsmodus</h4>
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-1">Kreativpause aktivieren</p>
                    </div>
                </div>

                <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4 border-t border-gray-800 sm:border-0 pt-4 sm:pt-0">
                    @if($this->vacationWishesCount > 0)
                        <span class="text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg flex items-center gap-2 shadow-inner" title="Gute Wünsche von Kunden erhalten">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            {{ $this->vacationWishesCount }} <span class="hidden sm:inline">Wünsche</span>
                        </span>
                    @else
                        <div></div>
                    @endif
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" wire:model.live="isVacationMode" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500 border border-gray-700 shadow-inner"></div>
                    </label>
                </div>
            </div>

            <div class="space-y-5 transition-all duration-300 {{ $isVacationMode ? 'opacity-100' : 'opacity-40 grayscale pointer-events-none' }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Von</label>
                        <input type="date" wire:model.live="vacationStartDate" class="w-full bg-gray-900 border border-gray-800 text-white rounded-xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none transition-all shadow-inner [color-scheme:dark]">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Bis</label>
                        <input type="date" wire:model.live="vacationEndDate" class="w-full bg-gray-900 border border-gray-800 text-white rounded-xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none transition-all shadow-inner [color-scheme:dark]">
                    </div>
                </div>
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Kunden-Meldung (Wird im Shop angezeigt)</label>
                    <textarea wire:model.blur="vacationDescription" rows="4" class="w-full bg-gray-900 border border-gray-800 text-amber-100/80 rounded-xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none transition-all shadow-inner resize-none"></textarea>
                    <p class="text-[9px] text-amber-500/60 mt-1.5 ml-1 font-medium">Das Datum und die Lieferzeit werden automatisch unterhalb des Textes im Shop berechnet und angezeigt.</p>
                </div>

                <button x-data="{ saved: false }"
                        @click="$wire.saveSettings().then(() => { saved = true; setTimeout(() => saved = false, 2500) })"
                        :class="saved ? 'bg-emerald-500 text-gray-900 shadow-[0_0_15px_rgba(16,185,129,0.4)]' : 'bg-amber-500 hover:bg-amber-400 text-gray-900 shadow-[0_0_15px_rgba(245,158,11,0.3)]'"
                        class="w-full py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                    <span x-show="!saved" wire:loading.remove wire:target="saveSettings">Speichern</span>
                    <span x-show="!saved" wire:loading wire:target="saveSettings" class="flex items-center gap-2">
                        <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        Speichere...
                    </span>
                    <span x-show="saved" x-cloak class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Gespeichert
                    </span>
                </button>
            </div>
        </div>

        <div class="bg-gray-950/80 rounded-[2rem] border border-gray-800 p-6 sm:p-8 shadow-inner relative overflow-hidden transition-all duration-500 {{ $isSickMode ? 'ring-1 ring-red-500/50 shadow-[inset_0_0_30px_rgba(239,68,68,0.05)]' : '' }}">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-start sm:items-center gap-3">
                    <span class="p-2 bg-red-500/10 text-red-400 rounded-xl border border-red-500/20 shadow-inner shrink-0">🤒</span>
                    <div>
                        <h4 class="text-lg font-serif font-bold text-white leading-tight">Krankheitsmodus</h4>
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-1">Verzögerung melden</p>
                    </div>
                </div>

                <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto gap-4 border-t border-gray-800 sm:border-0 pt-4 sm:pt-0">
                    @if($this->sickWishesCount > 0)
                        <span class="text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/30 px-3 py-1.5 rounded-lg flex items-center gap-2 shadow-inner" title="Gute Wünsche von Kunden erhalten">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                            {{ $this->sickWishesCount }} <span class="hidden sm:inline">Wünsche</span>
                        </span>
                    @else
                        <div></div>
                    @endif
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" wire:model.live="isSickMode" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500 border border-gray-700 shadow-inner"></div>
                    </label>
                </div>
            </div>

            <div class="space-y-5 transition-all duration-300 {{ $isSickMode ? 'opacity-100' : 'opacity-40 grayscale pointer-events-none' }}">
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1.5 ml-1">Kunden-Meldung (Wird im Shop angezeigt)</label>
                    <textarea wire:model="sickDescription" rows="4" class="w-full bg-gray-900 border border-gray-800 text-red-100/80 rounded-xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-red-500/30 focus:border-red-500 outline-none transition-all shadow-inner resize-none"></textarea>
                    <p class="text-[9px] text-red-500/60 mt-1.5 ml-1 font-medium">Dem Kunden wird automatisch eine Verzögerung von 6 Tagen zur aktuellen Lieferzeit mitgeteilt.</p>
                </div>

                <button x-data="{ saved: false }"
                        @click="$wire.saveSettings().then(() => { saved = true; setTimeout(() => saved = false, 2500) })"
                        :class="saved ? 'bg-emerald-500 text-white shadow-[0_0_15px_rgba(16,185,129,0.4)]' : 'bg-red-500 hover:bg-red-400 text-white shadow-[0_0_15px_rgba(239,68,68,0.3)]'"
                        class="w-full py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                    <span x-show="!saved" wire:loading.remove wire:target="saveSettings">Speichern</span>
                    <span x-show="!saved" wire:loading wire:target="saveSettings" class="flex items-center gap-2">
                        <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        Speichere...
                    </span>
                    <span x-show="saved" x-cloak class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Gespeichert
                    </span>
                </button>
            </div>
        </div>
    </div>

    @if(count($this->feedbackLogs) > 0)
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] border border-gray-800 p-6 sm:p-8 shadow-2xl mt-8">
            <h3 class="text-xl font-serif font-bold text-white mb-6 flex items-center gap-3">
                <svg class="w-6 h-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                Herzliche Grüße aus der Community
            </h3>

            <div class="overflow-x-auto custom-scrollbar bg-gray-950 rounded-2xl border border-gray-800 shadow-inner">
                <table class="w-full text-left text-sm min-w-[500px]">
                    <thead class="bg-gray-900 border-b border-gray-800 text-gray-500 text-[9px] uppercase tracking-widest font-black">
                    <tr>
                        <th class="p-4">Kunde</th>
                        <th class="p-4">Gesendeter Wunsch</th>
                        <th class="p-4 text-right">Datum</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                    @foreach($this->feedbackLogs as $fb)
                        <tr class="hover:bg-gray-900/50 transition-colors">
                            <td class="p-4 font-bold text-white">{{ $fb->user_name ?? 'Unbekannt' }}</td>
                            <td class="p-4">
                                @if($fb->type === 'vacation')
                                    <span class="bg-amber-500/10 text-amber-400 border border-amber-500/30 px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest shadow-inner inline-flex items-center gap-1.5"><span class="text-sm">🌴</span> Schönen Urlaub</span>
                                @else
                                    <span class="bg-red-500/10 text-red-400 border border-red-500/30 px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest shadow-inner inline-flex items-center gap-1.5"><span class="text-sm">💊</span> Gute Besserung</span>
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

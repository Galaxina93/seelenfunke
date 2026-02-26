<div x-data="{ open: false }">
    <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up transition-colors hover:border-gray-700">

        {{-- Header (Klickbar zum Aufklappen) & Controls --}}
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5 transition-all" :class="open ? 'mb-6 border-b border-gray-800 pb-5' : ''">

            {{-- Klickbarer Titelbereich --}}
            <div @click="open = !open" class="flex items-center gap-4 cursor-pointer group flex-1 w-full lg:w-auto">
                <div class="p-2 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 group-hover:text-primary group-hover:border-primary/30 transition-all shadow-inner shrink-0">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>
                <h3 class="text-xl font-serif font-bold text-white tracking-wide group-hover:text-primary transition-colors">Details & Eigenschaften</h3>

                @if(!$isManaging)
                    <span class="text-[9px] font-black uppercase tracking-widest text-primary bg-primary/10 border border-primary/20 px-2.5 py-1 rounded-lg shadow-inner animate-pulse whitespace-nowrap ml-2">
                        {{ count($productAttributes) }} aktiv
                    </span>
                @endif
            </div>

            {{-- Controls (Buttons rechts - stoppen Click-Event fürs Accordion) --}}
            <div class="flex flex-row items-center justify-between w-full lg:w-auto gap-4" @click.stop>
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 hidden sm:inline-block">
                    Wähle Eigenschaften & gib Werte ein
                </span>

                <button wire:click="toggleManageMode"
                        @click="open = true" {{-- Beim Klick auf Verwalten automatisch aufklappen --}}
                        class="text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 shadow-inner border w-full sm:w-auto shrink-0
                           {{ $isManaging
                              ? 'bg-primary border-primary/50 text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]'
                              : 'bg-gray-950 border-gray-800 text-gray-400 hover:text-white hover:border-gray-600'
                           }}">
                    @if($isManaging)
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        Fertig
                    @else
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Verwalten
                    @endif
                </button>
            </div>
        </div>

        {{-- ================================================= --}}
        {{-- EINGEKLAPPTER INHALT --}}
        {{-- ================================================= --}}
        <div x-show="open" x-collapse style="display: none;">
            @if(!$isManaging)
                <div class="space-y-8 pt-2">
                    {{-- OBERER BEREICH: Auswahl Pool (Tag-Cloud) --}}
                    <div class="border-b border-gray-800 pb-8">
                        <h4 class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-5 ml-1">1. Verfügbare Eigenschaften auswählen</h4>
                        <div class="flex flex-row flex-wrap gap-3">
                            @forelse($availableAttributes as $attr)
                                @php
                                    $name = $attr['name'];
                                    $isActive = array_key_exists($name, $productAttributes);
                                @endphp
                                <button
                                    wire:click="toggleAttribute('{{ $name }}')"
                                    class="text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl border transition-all shadow-inner
                                       {{ $isActive
                                          ? 'bg-primary/10 text-primary border-primary/50 shadow-[0_0_15px_rgba(197,160,89,0.2)] scale-[1.03]'
                                          : 'bg-gray-950 text-gray-400 border-gray-800 hover:border-gray-600 hover:text-white hover:bg-gray-900'
                                       }}">
                                    {{ $name }}
                                </button>
                            @empty
                                <p class="text-[10px] font-bold text-gray-600">Keine Attribute gefunden. Klicke oben auf "Verwalten".</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- UNTERER BEREICH: Eingabe Werte --}}
                    <div>
                        <h4 class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-5 ml-1">2. Werte eingeben</h4>

                        @if(count($productAttributes) > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                @foreach($productAttributes as $key => $value)
                                    <div class="relative group" wire:key="input-{{ $key }}">
                                        <label class="block text-[9px] font-black uppercase tracking-widest text-primary mb-2 ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $key }}</label>
                                        <div class="relative">
                                            @php
                                                $attrInputClass = "w-full px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-950 text-white text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner outline-none placeholder-gray-600";
                                            @endphp

                                            @if($key === 'Gewicht')
                                                <input type="number"
                                                       wire:model.live.debounce.500ms="productAttributes.{{ $key }}"
                                                       class="{{ $attrInputClass }} pr-10 font-mono font-bold"
                                                       placeholder="0">
                                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500">g</span>
                                            @else
                                                <input type="text"
                                                       wire:model.live.debounce.500ms="productAttributes.{{ $key }}"
                                                       class="{{ $attrInputClass }}"
                                                       placeholder="Wert für {{ $key }}...">
                                            @endif

                                            <button wire:click="toggleAttribute('{{ $key }}')"
                                                    class="absolute -right-2 -top-2 bg-gray-900 border border-gray-700 text-gray-400 hover:text-red-400 hover:border-red-500/30 rounded-full p-1.5 shadow-lg opacity-0 group-hover:opacity-100 transition-all hover:scale-110"
                                                    title="Eigenschaft entfernen">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="bg-gray-950 border border-dashed border-gray-800 rounded-[1.5rem] p-12 text-center shadow-inner">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-600">Wähle oben Eigenschaften aus, um hier Werte einzutragen.</p>
                            </div>
                        @endif
                    </div>
                </div>

            @else
                <div class="space-y-8 pt-2">
                    {{-- 1. Erstellen --}}
                    <div class="bg-gray-950 p-5 sm:p-6 rounded-[1.5rem] border border-gray-800 shadow-inner">
                        <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3 ml-1">Neue Eigenschaft definieren</label>
                        <div class="flex gap-3">
                            <input type="text"
                                   wire:model.live="newAttributeName"
                                   wire:keydown.enter="createAttribute"
                                   placeholder="z.B. Material, Pflegehinweis..."
                                   class="flex-1 px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-900 text-white text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none shadow-inner placeholder-gray-600">
                            <button wire:click="createAttribute"
                                    class="bg-primary text-gray-900 px-6 py-3.5 rounded-xl text-lg font-black hover:bg-primary-dark transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-[0_0_15px_rgba(197,160,89,0.2)] shrink-0 flex items-center justify-center"
                                    @if(empty($newAttributeName)) disabled @endif>
                                +
                            </button>
                        </div>
                        @error('newAttributeName') <span class="text-[10px] font-bold uppercase tracking-widest text-red-400 mt-2 block ml-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- 2. Liste Bearbeiten (Flex-Wrap Tag-Cloud) --}}
                    <div class="max-h-[450px] overflow-y-auto custom-scrollbar flex flex-wrap gap-4 pr-2 pb-2">
                        @foreach($availableAttributes as $attr)
                            @php
                                $attrId = $attr['id'];
                                $attrName = $attr['name'];
                            @endphp

                            <div class="flex items-center justify-between p-3.5 bg-gray-950 border border-gray-800 rounded-2xl shadow-inner hover:border-gray-700 transition-colors group gap-4 shrink-0" wire:key="attr-{{ $attrId }}">

                                @if($editingAttributeId === $attrId)
                                    <div class="flex items-center gap-2 animate-fade-in">
                                        <input type="text"
                                               wire:model="editingAttributeName"
                                               wire:keydown.enter="updateAttribute"
                                               class="w-32 sm:w-48 px-3 py-2 text-sm font-bold border border-primary bg-gray-900 text-white rounded-xl focus:ring-2 focus:ring-primary/30 outline-none shadow-inner">

                                        <button wire:click="updateAttribute" class="text-gray-900 bg-emerald-500 hover:bg-emerald-400 p-2.5 rounded-xl shadow-[0_0_10px_rgba(16,185,129,0.3)] transition-all shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></button>
                                        <button wire:click="cancelEditing" class="text-gray-400 bg-gray-900 border border-gray-700 hover:text-white p-2.5 rounded-xl transition-all shadow-inner shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                    </div>
                                @else
                                    <div class="flex items-center gap-3">
                                        <div class="w-1.5 h-1.5 rounded-full shrink-0 {{ array_key_exists($attrName, $productAttributes) ? 'bg-primary shadow-[0_0_8px_currentColor]' : 'bg-gray-700' }}"></div>
                                        <span class="text-sm font-bold text-gray-300 tracking-wide truncate max-w-[150px]">{{ $attrName }}</span>
                                    </div>

                                    <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                        <button wire:click="startEditing({{ $attrId }}, '{{ addslashes($attrName) }}')" class="p-2 text-gray-500 hover:text-primary bg-gray-900 border border-gray-800 hover:border-primary/30 rounded-xl transition-all shadow-inner" title="Umbenennen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                        <button wire:confirm="Sicher? Dies löscht die Eigenschaft aus dem System."
                                                wire:click="deleteAttribute({{ $attrId }})"
                                                class="p-2 text-gray-500 hover:text-red-400 bg-gray-900 border border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Löschen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

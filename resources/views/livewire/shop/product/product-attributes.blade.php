<div>
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">

        {{-- Header mit Umschalter --}}
        <div class="flex items-center justify-between mb-6 border-b border-gray-100 pb-4">
            <div class="flex items-center gap-1.5">
                <h3 class="text-lg font-serif font-bold text-gray-900">Details & Eigenschaften</h3>
                @if(!$isManaging)
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full animate-pulse">
                    {{ count($productAttributes) }} aktiv
                </span>
                @endif
            </div>

            <div class="flex items-center gap-3">
                {{-- Info Text (Dynamisch) --}}
                <span class="text-[10px] text-gray-400 hidden sm:inline-block">
                Wähle Eigenschaften aus und gib Werte ein.
            </span>

                {{-- Manage Button --}}
                <button wire:click="toggleManageMode"
                        class="text-xs font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-1
                           {{ $isManaging
                              ? 'bg-gray-900 text-white shadow-md'
                              : 'bg-white border border-gray-200 text-gray-500 hover:border-[#C5A059] hover:text-[#C5A059]'
                           }}">
                    @if($isManaging)
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Fertig
                    @else
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        Verwalten
                    @endif
                </button>
            </div>
        </div>

        {{-- ================================================= --}}
        {{-- ANSICHT 1: AUSWAHL & EINGABE MODUS --}}
        {{-- ================================================= --}}
        @if(!$isManaging)
            {{-- Search (Optional, da es hier weniger Items sind) --}}

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- LINKE SPALTE: Auswahl Pool --}}
                <div class="lg:col-span-1 border-r border-gray-100 pr-0 lg:pr-6">
                    <h4 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Verfügbare Eigenschaften</h4>
                    <div class="flex flex-wrap gap-2">
                        @forelse($availableAttributes as $attr)
                            @php
                                $name = $attr['name'];
                                $isActive = array_key_exists($name, $productAttributes);
                            @endphp
                            <button
                                wire:click="toggleAttribute('{{ $name }}')"
                                class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-all
                                   {{ $isActive
                                      ? 'bg-[#C5A059] text-white border-[#C5A059] shadow-sm'
                                      : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-white'
                                   }}">
                                {{ $name }}
                            </button>
                        @empty
                            <p class="text-xs text-gray-400 italic">Keine Attribute gefunden. Klicke auf "Verwalten".</p>
                        @endforelse
                    </div>
                </div>

                {{-- RECHTE SPALTE: Eingabe Werte --}}
                <div class="lg:col-span-2 space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Werte eingeben</h4>

                    @if(count($productAttributes) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($productAttributes as $key => $value)
                                <div class="relative group" wire:key="input-{{ $key }}">
                                    <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1 ml-1">{{ $key }}</label>
                                    <div class="relative">
                                        @if($key === 'Gewicht')
                                            <input type="number"
                                                   wire:model.live.debounce.500ms="productAttributes.{{ $key }}"
                                                   class="w-full pl-3 pr-8 py-2.5 rounded-xl border border-gray-200 text-sm font-medium focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059] transition-all"
                                                   placeholder="0">
                                            <span class="absolute right-3 top-2.5 text-xs text-gray-400 font-bold">g</span>
                                        @else
                                            <input type="text"
                                                   wire:model.live.debounce.500ms="productAttributes.{{ $key }}"
                                                   class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm font-medium focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059] transition-all"
                                                   placeholder="Wert...">
                                        @endif

                                        {{-- Remove Button --}}
                                        <button wire:click="toggleAttribute('{{ $key }}')"
                                                class="absolute -right-2 -top-2 bg-white text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-full p-0.5 shadow-sm border border-gray-100 opacity-0 group-hover:opacity-100 transition-all"
                                                title="Entfernen">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="bg-gray-50 border border-dashed border-gray-200 rounded-xl p-8 text-center">
                            <p class="text-sm text-gray-400">Wähle links Eigenschaften aus, um Werte einzutragen.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================================================= --}}
            {{-- ANSICHT 2: MANAGEMENT MODUS (CRUD) --}}
            {{-- ================================================= --}}
        @else
            <div class="space-y-6">

                {{-- 1. Erstellen --}}
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Neue Eigenschaft definieren</label>
                    <div class="flex gap-2">
                        <input type="text"
                               wire:model.live="newAttributeName"
                               wire:keydown.enter="createAttribute"
                               placeholder="z.B. Material, Pflegehinweis..."
                               class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-[#C5A059] focus:ring-[#C5A059]">
                        <button wire:click="createAttribute"
                                class="bg-[#C5A059] text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-[#b08d4d] transition disabled:opacity-50 disabled:cursor-not-allowed"
                                @if(empty($newAttributeName)) disabled @endif>
                            +
                        </button>
                    </div>
                    @error('newAttributeName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- 2. Liste Bearbeiten --}}
                <div class="max-h-[400px] overflow-y-auto custom-scrollbar space-y-2">
                    @foreach($availableAttributes as $attr)
                        @php
                            $attrId = $attr['id'];
                            $attrName = $attr['name'];
                        @endphp

                        <div class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded-xl hover:border-gray-200 transition group" wire:key="attr-{{ $attrId }}">

                            {{-- Editier Modus --}}
                            @if($editingAttributeId === $attrId)
                                <div class="flex flex-1 items-center gap-2 mr-2">
                                    <input type="text"
                                           wire:model="editingAttributeName"
                                           wire:keydown.enter="updateAttribute"
                                           class="w-full px-2 py-1 text-sm border border-[#C5A059] rounded focus:ring-1 focus:ring-[#C5A059]">

                                    <button wire:click="updateAttribute" class="text-green-600 hover:bg-green-50 p-1 rounded"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg></button>
                                    <button wire:click="cancelEditing" class="text-gray-400 hover:bg-gray-100 p-1 rounded"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                </div>
                            @else
                                {{-- Anzeige Modus --}}
                                <div class="flex items-center gap-3">
                                    <div class="w-1.5 h-1.5 rounded-full {{ array_key_exists($attrName, $productAttributes) ? 'bg-[#C5A059]' : 'bg-gray-300' }}"></div>
                                    <span class="text-sm font-medium text-gray-700">{{ $attrName }}</span>
                                </div>

                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="startEditing({{ $attrId }}, '{{ addslashes($attrName) }}')" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Umbenennen">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button wire:confirm="Sicher? Dies löscht die Eigenschaft aus dem System."
                                            wire:click="deleteAttribute({{ $attrId }})"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Löschen">
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

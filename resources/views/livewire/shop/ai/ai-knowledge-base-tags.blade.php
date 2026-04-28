<div x-data="{ open: false }" class="w-full min-w-0">
    <div class="bg-gray-950 p-5 sm:p-6 rounded-2xl shadow-inner border border-gray-800 animate-fade-in-up transition-colors hover:border-[var(--theme-color-50)] w-full min-w-0">
        
        {{-- Header --}}
        <div class="flex flex-wrap lg:flex-nowrap items-start lg:items-center justify-between gap-4 sm:gap-5 transition-all" :class="open ? 'mb-5 border-b border-gray-800 pb-4' : ''">
            
            <div @click="open = !open" class="flex items-center gap-3 sm:gap-4 cursor-pointer group flex-1 min-w-[200px] w-full lg:w-auto">
                <div class="p-2 rounded-xl bg-gray-900 border border-gray-800 text-gray-500 group-hover:text-[var(--theme-color)] group-hover:border-[var(--theme-color-50)] transition-all shadow-inner shrink-0">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-[var(--theme-color)]' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                </div>
                <h3 class="text-lg sm:text-xl font-bold text-gray-300 tracking-wider group-hover:text-[var(--theme-color)] transition-colors truncate">
                    Tags zuweisen
                </h3>
                
                @if(!$isManaging && count($selectedTagIds) > 0)
                    <span class="text-[9px] font-bold uppercase tracking-widest text-[var(--theme-color)] border border-[var(--theme-color-50)] bg-[var(--theme-color-10)] px-2.5 py-1 rounded-lg shadow-inner whitespace-nowrap ml-2 hidden sm:inline-block">
                        {{ count($selectedTagIds) }} aktiv
                    </span>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-start sm:justify-end w-full lg:w-auto gap-3" @click.stop>
                <button wire:click="toggleManageMode"
                        @click="open = true"
                        class="text-[10px] uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 shadow-inner border w-full sm:w-auto shrink-0 font-bold
                               {{ $isManaging
                                  ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-50)] text-[var(--theme-color)] shadow-xl'
                                  : 'bg-gray-900 border-gray-800 text-gray-400 hover:text-white hover:border-gray-700 hover:bg-gray-800'
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

        {{-- Eingeklappter Inhalt --}}
        <div x-show="open" x-collapse style="display: none;">
            @if(!$isManaging)
                <div class="pt-2">
                    {{-- Search --}}
                    <div class="mb-5 relative group">
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               placeholder="Tag durchsuchen..."
                               class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-gray-800 bg-gray-900 text-sm font-bold text-gray-300 focus:bg-gray-950 focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color-30)] transition-all shadow-inner outline-none placeholder-gray-500">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500 group-focus-within:text-[var(--theme-color)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                    </div>

                    {{-- Grid Selector --}}
                    <div class="flex flex-wrap gap-3 max-h-[350px] overflow-y-auto custom-scrollbar pr-2 pb-2">
                        @forelse($tags as $tag)
                            @php
                                $tId = $tag['id'];
                                $tName = $tag['name'];
                                $isSelected = in_array($tId, $selectedTagIds);
                            @endphp
                            <button
                                wire:click="toggleTag({{ $tId }})"
                                class="group relative flex items-center gap-2 px-4 py-2 rounded-lg border text-left transition-all duration-300 shadow-inner min-w-0 flex-shrink-0
                                       {{ $isSelected
                                          ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-[inset_0_0_15px_var(--theme-color-10)]'
                                          : 'border-gray-800 bg-gray-900 hover:border-gray-600 hover:bg-gray-800'
                                       }}">
                                
                                <div class="flex-shrink-0 w-4 h-4 rounded-sm border flex items-center justify-center transition-all shadow-inner
                                            {{ $isSelected ? 'bg-[var(--theme-color)] border-[var(--theme-color-50)]' : 'border-gray-700 bg-gray-950 group-hover:border-gray-500' }}">
                                    @if($isSelected)
                                        <svg class="w-3 h-3 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    @endif
                                </div>
                                <span class="text-xs font-bold truncate tracking-wide {{ $isSelected ? 'text-[var(--theme-color)]' : 'text-gray-400 group-hover:text-white' }}">
                                    #{{ $tName }}
                                </span>
                            </button>
                        @empty
                            <div class="w-full py-10 text-center text-xs font-bold uppercase tracking-widest text-gray-500 bg-gray-950 rounded-2xl border border-gray-800 shadow-inner">
                                Keine Tags im System gefunden.
                            </div>
                        @endforelse
                    </div>
                </div>

            @else
                {{-- Edit Mode --}}
                <div class="space-y-8 pt-2 animate-fade-in">
                    {{-- 1. Create --}}
                    <div class="bg-gray-900 p-5 sm:p-6 rounded-xl border border-gray-800 shadow-inner w-full">
                        <label class="block text-[9px] font-bold uppercase tracking-[0.2em] text-gray-400 mb-3 pl-1">Neuen Tag anlegen</label>
                        <div class="flex gap-3 w-full">
                            <input type="text"
                                   wire:model.live="newTagName"
                                   wire:keydown.enter="createTag"
                                   placeholder="Name eingeben..."
                                   class="flex-1 px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-950 text-white text-sm font-bold focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color-30)] transition-all outline-none shadow-inner placeholder-gray-500 min-w-0">

                            <button wire:click="createTag"
                                    class="bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-50)] px-6 py-3.5 rounded-xl text-lg font-black hover:bg-[var(--theme-color)] hover:text-black transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-[0_0_15px_var(--theme-color-10)] shrink-0 flex items-center justify-center cursor-pointer"
                                    @if(empty($newTagName)) disabled @endif>
                                +
                            </button>
                        </div>
                        @error('newTagName') <span class="text-[10px] uppercase tracking-widest text-red-500 mt-2 block ml-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- 2. List & Edit --}}
                    <div class="max-h-[400px] overflow-y-auto custom-scrollbar space-y-3 pr-2 w-full">
                        @foreach($tags as $tag)
                            @php
                                $tId = $tag['id'];
                                $tName = $tag['name'];
                            @endphp

                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 bg-gray-950 border border-gray-800 rounded-xl shadow-inner hover:border-gray-700 transition-colors group gap-4 min-w-0" wire:key="tag-manage-{{ $tId }}">
                                
                                @if($editingTagId === $tId)
                                    <div class="flex flex-1 w-full items-center gap-2 sm:gap-3 mr-0 sm:mr-3 animate-fade-in min-w-0">
                                        <input type="text"
                                               wire:model="editingTagName"
                                               wire:keydown.enter="updateTag"
                                               class="w-full flex-1 px-4 py-2.5 text-sm font-bold border border-[var(--theme-color)] bg-gray-900 text-[var(--theme-color)] rounded-xl focus:ring-1 focus:ring-[var(--theme-color-30)] outline-none shadow-[inset_0_0_10px_var(--theme-color-10)] min-w-0">
                                        
                                        <button wire:click="updateTag" class="text-black bg-[var(--theme-color)] hover:bg-[var(--theme-color-80)] p-3 rounded-xl shadow-[0_0_10px_var(--theme-color-30)] transition-all shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></button>
                                        <button wire:click="cancelEditing" class="text-gray-400 bg-gray-900 border border-gray-700 hover:text-white hover:border-gray-500 p-3 rounded-xl transition-all shadow-inner shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                                    </div>
                                    @error('editingTagName') <span class="text-[10px] uppercase tracking-widest text-red-500 block">{{ $message }}</span> @enderror
                                @else
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <div class="w-2 h-2 rounded-full shrink-0 {{ in_array($tId, $selectedTagIds) ? 'bg-[var(--theme-color)] shadow-[0_0_8px_currentColor]' : 'bg-gray-700' }}"></div>
                                        <span class="text-sm font-bold text-gray-300 tracking-wide truncate">#{{ $tName }}</span>
                                    </div>

                                    <div class="flex items-center gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity w-full sm:w-auto justify-end shrink-0 mt-2 sm:mt-0">
                                        <button wire:click="startEditing({{ $tId }}, '{{ addslashes($tName) }}')" class="p-2 text-gray-500 hover:text-white bg-gray-900 border border-gray-800 hover:border-gray-600 rounded-lg transition-all shadow-inner" title="Umbenennen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                        <button wire:confirm="Sicher, dass du diesen Tag löschen möchtest?"
                                                wire:click="deleteTag({{ $tId }})"
                                                class="p-2 text-gray-600 hover:text-white hover:bg-red-900 border border-transparent hover:border-red-700 rounded-lg transition-all shadow-inner" title="Löschen">
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

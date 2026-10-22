<div {{ $attributes->merge(['class' => 'w-64 bg-gray-50/80 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col shrink-0 h-full']) }} x-data="{
    selectedChats: [],
    toggleSelection(id) {
        if(this.selectedChats.includes(id)) {
            this.selectedChats = this.selectedChats.filter(x => x !== id);
        } else {
            this.selectedChats.push(id);
        }
    },
    deleteSelected() {
        if(this.selectedChats.length > 0 && confirm('Möchtest du diese ' + this.selectedChats.length + ' Chats wirklich löschen?')) {
            $wire.deleteChats(this.selectedChats).then(() => {
                this.selectedChats = [];
            });
        }
    }
}">
    <!-- Header -->
    <div class="p-3 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between gap-1">
        <h3 class="font-bold text-gray-800 dark:text-gray-200 uppercase tracking-widest text-xs truncate">Chats</h3>
        
        <div class="flex items-center gap-1 shrink-0">
            <!-- Sync -->
            <button wire:click="syncAll" wire:loading.attr="disabled" class="p-1.5 rounded-lg text-gray-500 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Alles synchronisieren">
                <svg wire:loading.remove wire:target="syncAll" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <svg wire:loading wire:target="syncAll" class="animate-spin h-3.5 w-3.5 text-[var(--theme-color)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>

            <!-- Interne Agenten Modal Trigger -->
            <button @click="$dispatch('open-agents-modal')" class="p-1.5 rounded-lg text-gray-500 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Interne Agenten verwalten">
                <x-heroicon-o-cpu-chip class="w-3.5 h-3.5"/>
            </button>

            <!-- Wissensdatenbank -->
            <button wire:click="$set('activeWorkspaceView', 'knowledge-base')" class="p-1.5 rounded-lg text-gray-500 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Wissensdatenbank">
                <x-heroicon-o-book-open class="w-3.5 h-3.5"/>
            </button>

            <!-- Einstellungen -->
            <button wire:click="$set('activeWorkspaceView', 'settings')" class="p-1.5 rounded-lg text-gray-500 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="Einstellungen">
                <x-heroicon-o-cog-6-tooth class="w-3.5 h-3.5"/>
            </button>

            <!-- Neuer Chat -->
            <button wire:click="createNewChat" class="p-1.5 rounded-lg bg-[var(--theme-color-10)] text-[var(--theme-color)] hover:bg-[var(--theme-color-20)] dark:hover:bg-[var(--theme-color-30)] transition-colors" title="Neuer Chat">
                <x-heroicon-o-plus class="w-3.5 h-3.5"/>
            </button>

            <!-- Mobil: Schließen Button -->
            <button type="button" @click="showMobileChatSidebar = false" class="md:hidden p-1.5 ml-1 rounded-lg text-gray-500 hover:text-red-500 hover:bg-red-500/10 transition-colors" title="Seitenleiste schließen">
                <x-heroicon-s-x-mark class="w-4 h-4"/>
            </button>
        </div>
    </div>

    <!-- Actions (Bulk Delete) -->
    <div x-show="selectedChats.length > 0" style="display: none;" class="p-2 border-b border-gray-200 dark:border-gray-800 bg-red-500/10">
        <button @click="deleteSelected" class="w-full flex items-center justify-center gap-2 text-xs font-bold text-red-500 dark:text-red-400 uppercase tracking-widest p-2 rounded hover:bg-red-500/15 transition-colors">
            <x-heroicon-o-trash class="w-4 h-4"/>
            <span x-text="selectedChats.length + ' Löschen'"></span>
        </button>
    </div>

    <!-- Chat List -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-2 flex flex-col gap-1">
        @foreach($this->chatSessions as $chat)
            <div class="group flex items-stretch rounded-lg transition-colors cursor-pointer {{ $this->currentChatSessionId === $chat->id ? 'bg-[var(--theme-color-10)] dark:bg-[var(--theme-color-15)]' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                
                <div class="pl-2 pr-1 py-3 flex items-center" @click.stop="toggleSelection('{{ $chat->id }}')">
                    <input type="checkbox" :checked="selectedChats.includes('{{ $chat->id }}')" class="rounded border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] w-3.5 h-3.5 cursor-pointer">
                </div>

                <div class="flex-1 py-2 pr-2 pl-1 truncate" x-data="{ editing: false, newTitle: '{{ addslashes($chat->title) }}' }">
                    <div x-show="!editing" @click="$wire.switchChat('{{ $chat->id }}')" class="flex flex-col h-full justify-center">
                        <span class="text-sm font-sans truncate block {{ $this->currentChatSessionId === $chat->id ? 'text-[var(--theme-color)] font-bold' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $chat->title ?: 'Neuer Chat' }}
                        </span>
                        <span class="text-[10px] text-gray-400 dark:text-gray-500 block truncate">{{ $chat->updated_at->diffForHumans() }}</span>
                    </div>
                    
                    <div x-show="editing" style="display: none;" class="flex items-center gap-1 h-full" @click.stop>
                        <input type="text" x-model="newTitle" 
                            x-ref="titleInput"
                            @keydown.enter="$wire.updateChatTitle(newTitle, '{{ $chat->id }}'); editing = false" 
                            @keydown.escape="editing = false; newTitle = '{{ addslashes($chat->title) }}'"
                            @blur="$wire.updateChatTitle(newTitle, '{{ $chat->id }}'); editing = false"
                            class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-[var(--theme-color-50)] rounded px-2 py-1 text-xs text-gray-900 dark:text-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] focus:outline-none">
                    </div>
                </div>

                <div class="opacity-0 group-hover:opacity-100 flex items-center pr-2 transition-opacity">
                    <button class="p-1 text-gray-400 hover:text-[var(--theme-color)]" @click.stop="let el = $el.closest('.group').querySelector('[x-data]'); Alpine.$data(el).editing = true; setTimeout(() => Alpine.$data(el).$refs.titleInput.focus(), 50)">
                        <x-heroicon-o-pencil-square class="w-3.5 h-3.5"/>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

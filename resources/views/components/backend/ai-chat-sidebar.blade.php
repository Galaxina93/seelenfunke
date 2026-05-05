<div {{ $attributes->merge(['class' => 'w-64 bg-gray-900 border-r border-gray-800 flex flex-col shrink-0 h-full']) }} x-data="{
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
    <div class="p-4 border-b border-gray-800 flex items-center justify-between">
        <h3 class="font-bold text-gray-200 uppercase tracking-widest text-sm">Chat Verlauf</h3>
        <button wire:click="createNewChat" class="p-1.5 rounded-lg bg-[var(--theme-color-10)] text-[var(--theme-color)] hover:bg-[var(--theme-color-30)] transition-colors" title="Neuer Chat">
            <x-heroicon-o-plus class="w-4 h-4"/>
        </button>
    </div>

    <!-- Actions (Bulk Delete) -->
    <div x-show="selectedChats.length > 0" style="display: none;" class="p-2 border-b border-gray-800 bg-red-500/5">
        <button @click="deleteSelected" class="w-full flex items-center justify-center gap-2 text-xs font-bold text-red-400 uppercase tracking-widest p-2 rounded hover:bg-red-500/10 transition-colors">
            <x-heroicon-o-trash class="w-4 h-4"/>
            <span x-text="selectedChats.length + ' Löschen'"></span>
        </button>
    </div>

    <!-- Chat List -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-2 flex flex-col gap-1">
        @foreach($this->chatSessions as $chat)
            <div class="group flex items-stretch rounded-lg transition-colors cursor-pointer {{ $this->currentChatSessionId === $chat->id ? 'bg-[var(--theme-color-10)]' : 'hover:bg-gray-800' }}">
                
                <div class="pl-2 pr-1 py-3 flex items-center" @click.stop="toggleSelection('{{ $chat->id }}')">
                    <input type="checkbox" :checked="selectedChats.includes('{{ $chat->id }}')" class="rounded border-gray-700 bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] w-3 h-3 cursor-pointer">
                </div>

                <div class="flex-1 py-2 pr-2 pl-1 truncate" x-data="{ editing: false, newTitle: '{{ addslashes($chat->title) }}' }">
                    <div x-show="!editing" @click="$wire.switchChat('{{ $chat->id }}')" class="flex flex-col h-full justify-center">
                        <span class="text-sm font-sans truncate block {{ $this->currentChatSessionId === $chat->id ? 'text-[var(--theme-color)] font-bold' : 'text-gray-300' }}">
                            {{ $chat->title ?: 'Neuer Chat' }}
                        </span>
                        <span class="text-[10px] text-gray-500 block truncate">{{ $chat->updated_at->diffForHumans() }}</span>
                    </div>
                    
                    <div x-show="editing" style="display: none;" class="flex items-center gap-1 h-full" @click.stop>
                        <input type="text" x-model="newTitle" 
                            x-ref="titleInput"
                            @keydown.enter="$wire.updateChatTitle(newTitle, '{{ $chat->id }}'); editing = false" 
                            @keydown.escape="editing = false; newTitle = '{{ addslashes($chat->title) }}'"
                            @blur="$wire.updateChatTitle(newTitle, '{{ $chat->id }}'); editing = false"
                            class="w-full bg-gray-950 border border-[var(--theme-color-50)] rounded px-2 py-1 text-xs text-[var(--theme-color)] focus:ring-0 focus:outline-none">
                    </div>
                </div>

                <div class="opacity-0 group-hover:opacity-100 flex items-center pr-2 transition-opacity">
                    <button class="p-1 text-gray-500 hover:text-[var(--theme-color)]" @click.stop="let el = $el.closest('.group').querySelector('[x-data]'); Alpine.$data(el).editing = true; setTimeout(() => Alpine.$data(el).$refs.titleInput.focus(), 50)">
                        <x-heroicon-o-pencil-square class="w-3 h-3"/>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>

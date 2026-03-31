<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" x-data="{ mobileView: '{{ $viewMode === 'account_settings' ? 'settings' : ($selectedMessageId ? 'detail' : 'folders') }}' }"
     @message-selected.window="mobileView = 'detail'"
     @folder-selected.window="mobileView = 'list'"
     @settings-opened.window="mobileView = 'settings'"
     class="h-[calc(100vh-8rem)] flex bg-gray-950/50 backdrop-blur-md rounded-2xl border border-gray-800 overflow-hidden shadow-2xl relative w-full">

    {{-- COLUMN 1: Folders & Accounts --}}
    <div class="absolute inset-0 z-30 lg:relative lg:z-10 w-full lg:w-80 bg-gray-900 border-r border-gray-800 flex flex-col shrink-0 transition-transform duration-300"
         :class="mobileView === 'folders' ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="p-4 border-b border-gray-800 flex justify-between items-center bg-gray-900">
            <h2 class="text-white font-serif text-xl tracking-wide">Postfächer</h2>
            <div class="flex gap-1">
                <button wire:click="syncMails" class="p-1 hover:bg-gray-800 rounded text-gray-400 hover:text-white transition-colors" title="Postfächer jetzt synchronisieren">
                    <x-heroicon-o-arrow-path class="w-5 h-5" wire:loading.class="animate-spin" wire:target="syncMails"/>
                </button>
                <button wire:click="openAccountSettings('new')" class="p-1 hover:bg-gray-800 rounded text-gray-400 hover:text-white transition-colors" title="Neues E-Mail Konto anbinden">
                    <x-heroicon-o-plus class="w-5 h-5"/>
                </button>
                <button wire:click="$set('showNewFolderModal', true)" class="p-1 hover:bg-gray-800 rounded text-gray-400 hover:text-white transition-colors" title="Neuen Ordner erstellen">
                    <x-heroicon-o-folder-plus class="w-5 h-5"/>
                </button>
            </div>
        </div>

        {{-- Accounts are now listed in the main sidebar area below --}}

        {{-- Modal: Neuer Ordner --}}
        @if($showNewFolderModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="$wire.set('showNewFolderModal', false)">
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 w-full max-w-md shadow-2xl" @keydown.escape.window="$wire.set('showNewFolderModal', false)">
                    <h3 class="text-xl font-bold text-white mb-4">Neuen Ordner erstellen</h3>
                    <form wire:submit.prevent="createFolder">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Ordnername</label>
                            <input type="text" wire:model="newFolderName" class="w-full bg-gray-950 border border-gray-700 text-white rounded-lg focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] px-4 py-2" placeholder="z.B. Rechnungen, Projekte..." required autofocus>
                            @error('newFolderName') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" wire:click="$set('showNewFolderModal', false)" class="px-4 py-2 text-sm font-bold text-gray-400 hover:text-white transition-colors">Abbrechen</button>
                            <button type="submit" class="bg-[var(--theme-color)] hover:bg-[var(--theme-color)]/80 text-black px-6 py-2 text-sm font-bold rounded-lg transition-colors">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Modal: Auto-Routing Regel --}}
        @if($showRoutingModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="$wire.set('showRoutingModal', false)">
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 w-full max-w-md shadow-2xl" @keydown.escape.window="$wire.set('showRoutingModal', false)">
                    <h3 class="text-xl font-bold text-white mb-2">Automatisches Einsortieren</h3>
                    <p class="text-xs text-gray-400 mb-4">Mails von diesem Absender werden in Zukunft immer sofort in den gewählten Ordner verschoben.</p>
                    <form wire:submit.prevent="saveRoutingRule">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Ziel-Ordner</label>
                            <select wire:model="routingTargetFolder" class="w-full bg-gray-950 border border-gray-700 text-white rounded-lg focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] px-4 py-2" required>
                                <option value="">-- Ordner wählen --</option>
                                @foreach($folders as $key => $label)
                                    @if(!in_array($key, ['Drafts', 'Sent', 'Trash', 'Junk']))
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('routingTargetFolder') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" wire:click="$set('showRoutingModal', false)" class="px-4 py-2 text-sm font-bold text-gray-400 hover:text-white transition-colors">Abbrechen</button>
                            <button type="submit" class="bg-[var(--theme-color)] hover:bg-[var(--theme-color)]/80 text-black px-6 py-2 text-sm font-bold rounded-lg transition-colors">Regel aktivieren</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Modal: E-Mail Schreiben / Antworten --}}
        @if($showComposeModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @click.self="$wire.set('showComposeModal', false)">
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-0 w-full max-w-4xl shadow-2xl h-[80vh] flex flex-col" @keydown.escape.window="$wire.set('showComposeModal', false)">
                    <div class="p-4 border-b border-gray-800 flex justify-between items-center bg-gray-950 rounded-t-2xl">
                        <h3 class="text-lg font-bold text-white">Neue Nachricht</h3>
                        <button wire:click="$set('showComposeModal', false)" class="text-gray-400 hover:text-white transition-colors">
                            <x-heroicon-o-x-mark class="w-6 h-6"/>
                        </button>
                    </div>
                    <form wire:submit.prevent="sendMail" class="flex flex-col flex-1 overflow-hidden">
                        <div class="p-4 border-b border-gray-800 space-y-3 shrink-0">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 text-sm w-16">An:</span>
                                <input type="email" wire:model="composeTo" class="flex-1 bg-transparent border-none text-white focus:ring-0 p-0" placeholder="empfaenger@beispiel.de" required>
                            </div>
                            <div class="h-px bg-gray-800"></div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 text-sm w-16">Betreff:</span>
                                <input type="text" wire:model="composeSubject" class="flex-1 bg-transparent border-none text-white focus:ring-0 p-0 font-bold" placeholder="Worum geht es?" required>
                            </div>
                        </div>
                        <div class="flex-1 p-0 relative">
                            <textarea wire:model="composeBody" class="w-full h-full bg-transparent border-none text-gray-300 focus:ring-0 p-4 resize-none custom-scrollbar" placeholder="Schreibe deine Nachricht..." required></textarea>
                        </div>
                        <div class="p-4 border-t border-gray-800 bg-gray-950 rounded-b-2xl flex justify-end gap-3 shrink-0">
                            <button type="button" wire:click="$set('showComposeModal', false)" class="px-4 py-2 text-sm font-bold text-gray-400 hover:text-white transition-colors">Verwerfen</button>
                            <button type="submit" class="bg-[var(--theme-color)] hover:bg-[var(--theme-color)]/80 text-black px-8 py-2 text-sm font-bold rounded-lg transition-colors flex items-center gap-2">
                                <x-heroicon-o-paper-airplane class="w-4 h-4"/> Senden
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto p-3 space-y-4 custom-scrollbar">
            @if(isset($accountTree) && count($accountTree) > 0)
                @foreach($accountTree as $accountId => $data)
                    @php
                        $acc = $data['model'];
                        $accFolders = $data['folders'];
                        $accCounts = $data['counts'];
                        $accTotalUnread = $data['total_unread'];
                        $isAccActive = $selectedAccountId === $acc->id;
                    @endphp

                    {{-- Alpine.js Accordion for each Account --}}
                    <div x-data="{ open: {{ $isAccActive ? 'true' : 'false' }} }" class="space-y-1">

                        {{-- Account Header / Accordion Toggle --}}
                        <div class="w-full flex items-center justify-between px-2 py-1.5 rounded-lg text-sm font-bold transition-colors {{ $isAccActive ? 'text-white bg-gray-800/50' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800/30' }}">

                            <button @click="open = !open; $wire.selectAccount({{ $acc->id }})" class="flex items-center gap-2 truncate flex-1 text-left">
                                <x-heroicon-s-chevron-right class="w-4 h-4 transition-transform duration-200 shrink-0" x-bind:class="open ? 'rotate-90 text-[var(--theme-color)]' : ''" />
                                <x-heroicon-o-at-symbol class="w-4 h-4 shrink-0 {{ $isAccActive ? 'text-[var(--theme-color)]' : '' }}"/>
                                <span class="truncate">{{ $acc->name }}</span>
                            </button>

                            <div class="flex items-center gap-1 shrink-0">
                                @if($accTotalUnread > 0)
                                    <span class="bg-[var(--theme-color)] text-black text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $accTotalUnread }}</span>
                                @endif
                                <button type="button" @click.prevent.stop="navigator.clipboard.writeText('{{ $acc->email }}'); $el.innerHTML = `<svg class='w-3.5 h-3.5 text-green-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/></svg>`; setTimeout(() => $el.innerHTML = `<svg class='w-3.5 h-3.5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3'/></svg>`, 2000)" class="p-1 text-gray-500 hover:text-white transition-colors" title="E-Mail kopieren">
                                    <x-heroicon-o-clipboard-document class="w-3.5 h-3.5" />
                                </button>
                                <button type="button" wire:click.stop="openAccountSettings({{ $acc->id }})" class="p-1 text-gray-500 hover:text-white transition-colors" title="Konto-Einstellungen">
                                    <x-heroicon-o-cog-6-tooth class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        {{-- Folders List (Collapsible) --}}
                        <div x-show="open" x-collapse>
                            <div class="pl-6 pr-2 py-1 space-y-0.5 border-l border-gray-800/50 ml-4">
                                @foreach($accFolders as $key => $label)
                                    @php
                                        // A folder is only visually "active" if it belongs to the currently selected account AND is the selected folder
                                        $isActiveFolder = $isAccActive && ($selectedFolder === $key);
                                        $count = $accCounts[$key] ?? 0;

                                        $icon = 'folder';
                                        if($key === 'INBOX') $icon = 'inbox';
                                        if($key === 'Sent') $icon = 'paper-airplane';
                                        if($key === 'Drafts') $icon = 'document';
                                        if($key === 'Junk') $icon = 'shield-exclamation';
                                        if($key === 'Trash') $icon = 'trash';
                                        if($key === 'Archive') $icon = 'archive-box';
                                    @endphp

                                    <div class="relative group"
                                         x-data="{ isDragOver: false }"
                                         @dragover.prevent="isDragOver = true"
                                         @dragleave.prevent="isDragOver = false"
                                         @drop.prevent="
                                            isDragOver = false;
                                            $wire.moveMessage($event.dataTransfer.getData('text/plain'), '{{ $key }}')
                                         ">
                                        <button wire:click.stop="selectAccountAndFolder({{ $acc->id }}, '{{ $key }}')"
                                                :class="isDragOver ? 'ring-1 ring-[var(--theme-color)] bg-[var(--theme-color-20)]' : ''"
                                                class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition-colors {{ $isActiveFolder ? 'bg-[var(--theme-color-10)] text-[var(--theme-color)]' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                                            <div class="flex items-center gap-3 truncate">
                                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="w-4 h-4 shrink-0 {{ $isActiveFolder ? 'text-[var(--theme-color)]' : 'text-gray-500' }}" />
                                                <span class="truncate">{{ $label }}</span>
                                            </div>
                                            @if($count > 0)
                                                <span class="bg-gray-700 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-sm shrink-0">{{ $count }}</span>
                                            @endif
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- RIGHT SIDE WRAPPER (Inbox & Settings) --}}
    <div class="absolute inset-0 z-20 lg:relative lg:z-auto lg:flex-1 overflow-hidden flex transition-transform duration-300 pointer-events-none lg:pointer-events-auto"
         :class="(mobileView === 'list' || mobileView === 'detail' || mobileView === 'settings') ? 'translate-x-0 pointer-events-auto' : 'translate-x-full lg:translate-x-0 lg:pointer-events-auto'"
         x-data="{ mode: @entangle('viewMode') }">

        {{-- INBOX VIEW --}}
        <div x-show="mode === 'inbox'" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 lg:translate-x-8" x-transition:enter-end="opacity-100 lg:translate-x-0" x-transition:leave="transition ease-in duration-200 transform absolute" x-transition:leave-start="opacity-100 lg:translate-x-0" x-transition:leave-end="opacity-0 lg:translate-x-8" class="absolute inset-0 flex bg-gray-950/50">

            {{-- COLUMN 2: Message List --}}
            <div class="absolute inset-0 z-20 lg:relative lg:z-10 w-full lg:w-80 bg-gray-950/80 border-r border-gray-800 flex flex-col shrink-0 transition-transform duration-300"
                 :class="(mobileView === 'list' || mobileView === 'folders') ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="p-4 border-b border-gray-800 flex justify-between items-center bg-gray-950">
            <div class="flex items-center gap-2">
                <button @click="mobileView = 'folders'" class="lg:hidden p-1.5 text-gray-400 hover:text-white bg-gray-800 rounded-lg transition-colors">
                    <x-heroicon-o-chevron-left class="w-5 h-5"/>
                </button>
                <h3 class="text-white font-bold truncate">{{ $selectedFolder === 'Archive' ? 'Archiv' : ($folders[$selectedFolder] ?? 'Mails') }}</h3>
            </div>
            <button wire:click="openCompose('new')" class="bg-[var(--theme-color-20)] text-[var(--theme-color)] hover:bg-[var(--theme-color)] hover:text-black p-1.5 rounded-lg transition-colors shrink-0" title="Neue E-Mail schreiben">
                <x-heroicon-o-pencil-square class="w-5 h-5"/>
            </button>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="px-4 py-3 border-b border-gray-800 bg-gray-950/50 flex flex-col gap-2">
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 absolute left-3 top-2.5 text-gray-500"/>
                <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Suchen..." class="w-full bg-gray-900 border border-gray-800 text-white text-sm rounded-lg pl-9 pr-3 py-2 focus:ring-1 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] placeholder-gray-600 transition-colors">
            </div>
            <select wire:model.live="filterMode" class="w-full bg-gray-900 border border-gray-800 text-gray-300 text-xs rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                <option value="all">Alle Nachrichten</option>
                <option value="unread">Nur Ungelesene</option>
                <option value="attachments">Mit Anhang</option>
            </select>
        </div>

        <div class="flex-1 overflow-y-auto">
            @forelse($messages as $msg)
                @php
                    $isMsgActive = $selectedMessage && $selectedMessage->id === $msg->id;
                @endphp
                {{-- Alpine.js for Custom Context Menu (Right Click) --}}
                <div x-data="{ contextMenuOpen: false }"
                     draggable="true"
                     @dragstart="$event.dataTransfer.setData('text/plain', {{ $msg->id }})"
                     @contextmenu.prevent="contextMenuOpen = true; $event.preventDefault();"
                     @click.outside="contextMenuOpen = false"
                     class="relative border-b border-gray-800/50 cursor-grab active:cursor-grabbing">

                    <button wire:click="selectMessage({{ $msg->id }})" class="w-full text-left p-4 hover:bg-gray-800 transition-colors {{ $isMsgActive ? 'bg-gray-800 border-l-2 border-[var(--theme-color)]' : '' }} {{ !$msg->is_read ? 'bg-gray-900/40' : '' }}">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-sm truncate pr-2 flex items-center gap-2 {{ !$msg->is_read ? 'text-white font-bold' : 'text-gray-300' }}">
                                @if(!$msg->is_read)
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></span>
                                @endif
                                {{ $msg->from_name ?: $msg->from_email }}
                            </span>
                            <span class="text-[10px] text-gray-500 shrink-0">{{ $msg->received_at ? $msg->received_at->format('H:i') : '' }}</span>
                        </div>
                        <h4 class="text-xs truncate mb-1 {{ !$msg->is_read ? 'text-gray-200 font-semibold' : 'text-gray-400' }}">{{ $msg->subject }}</h4>
                        <!-- Preview snippet could go here -->
                        <p class="text-[10px] text-gray-500 truncate">{{ Str::limit(strip_tags($msg->body_plain ?? $msg->body_html), 40) }}</p>
                    </button>

                    {{-- Context Menu Dropdown --}}
                    <div x-show="contextMenuOpen"
                         style="display: none;"
                         class="absolute z-50 left-10 top-10 w-48 bg-[#1E1E1E] border border-gray-700 rounded-lg shadow-2xl py-1 text-sm font-medium overflow-hidden">

                        <button wire:click="selectMessage({{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 hover:text-white flex justify-between items-center">
                            Öffnen <x-heroicon-m-envelope-open class="w-4 h-4 text-gray-500"/>
                        </button>

                        <div class="h-px bg-gray-700 my-1"></div>

                        <button wire:click="archiveMessage({{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 hover:text-white flex justify-between items-center">
                            Archivieren <x-heroicon-m-archive-box class="w-4 h-4 text-gray-500"/>
                        </button>

                        <div class="h-px bg-gray-700 my-1"></div>

                        <button wire:click="openCompose('reply', {{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 hover:text-white flex justify-between items-center">
                            Antworten <x-heroicon-m-arrow-uturn-left class="w-4 h-4 text-gray-500"/>
                        </button>

                        <button wire:click="openCompose('forward', {{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 hover:text-white flex justify-between items-center">
                            Weiterleiten <x-heroicon-m-arrow-uturn-right class="w-4 h-4 text-gray-500"/>
                        </button>

                        <div class="h-px bg-gray-700 my-1"></div>

                        <button wire:click="openRoutingModal({{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 hover:text-white flex justify-between items-center">
                            Abonnieren (Autom. in Ordner) <x-heroicon-m-inbox-arrow-down class="w-4 h-4 text-[var(--theme-color)]"/>
                        </button>

                        <div class="h-px bg-gray-700 my-1"></div>

                        @if($msg->folder === 'Junk')
                            <button wire:click="unmarkSpam({{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-emerald-400 hover:bg-emerald-900/30 flex justify-between items-center">
                                Kein Spam (Entsperren) <x-heroicon-m-shield-check class="w-4 h-4 text-emerald-500"/>
                            </button>
                        @else
                            <button wire:click="markAsSpam({{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-red-400 hover:bg-red-900/30 flex justify-between items-center">
                                Als Spam markieren <x-heroicon-m-shield-exclamation class="w-4 h-4 text-red-500"/>
                            </button>
                        @endif

                        <button wire:click="deleteMessage({{ $msg->id }})" @click.stop="contextMenuOpen = false" class="w-full text-left px-4 py-2 text-red-500 hover:bg-red-900/30 flex justify-between items-center">
                            Löschen <x-heroicon-m-trash class="w-4 h-4 text-red-500"/>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    Keine E-Mails vorhanden.
                </div>
            @endforelse
        </div>
    </div>

    {{-- COLUMN 3: Reading Pane --}}
    <div class="absolute inset-0 z-10 lg:relative lg:z-auto w-full lg:flex-1 bg-gray-950 flex flex-col transition-transform duration-300"
         :class="(mobileView === 'detail' || mobileView === 'settings') ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'">
        @if (session()->has('success_message'))
            <div class="absolute top-4 right-4 z-40 p-3 text-sm text-green-400 bg-green-900/90 border border-green-800 rounded-lg shadow-xl backdrop-blur-sm">
                {{ session('success_message') }}
            </div>
        @endif

        @if (session()->has('error_message'))
            <div class="absolute top-4 right-4 z-40 p-3 text-sm text-red-400 bg-red-900/90 border border-red-800 rounded-lg shadow-xl backdrop-blur-sm max-w-sm">
                {{ session('error_message') }}
            </div>
        @endif

        @if($selectedMessage)
            {{-- Toolbar --}}
            <div class="p-4 border-b border-gray-800 flex justify-between items-center bg-gray-900">
                <div class="flex gap-1 sm:gap-2 items-center flex-wrap">
                    <button @click="mobileView = 'list'" class="lg:hidden p-2 text-gray-400 hover:text-white bg-gray-800 rounded-lg transition-colors mr-1" title="Zurück">
                        <x-heroicon-o-chevron-left class="w-5 h-5"/>
                    </button>
                    <button wire:click="openCompose('reply', {{ $selectedMessage->id }})" class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors" title="Antworten">
                        <x-heroicon-o-arrow-uturn-left class="w-5 h-5" />
                    </button>
                    <button wire:click="openCompose('forward', {{ $selectedMessage->id }})" class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors" title="Weiterleiten">
                        <x-heroicon-o-arrow-uturn-right class="w-5 h-5" />
                    </button>
                    <div class="hidden sm:block w-px h-6 bg-gray-700 mx-1 my-auto"></div>
                    <button wire:click="archiveMessage({{ $selectedMessage->id }})" class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors" title="Archivieren">
                        <x-heroicon-o-archive-box class="w-5 h-5" />
                    </button>
                    <button wire:click="deleteMessage({{ $selectedMessage->id }})" class="p-2 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg transition-colors" title="Löschen">
                        <x-heroicon-o-trash class="w-5 h-5" />
                    </button>
                    <button wire:click="markAsSpam({{ $selectedMessage->id }})" class="p-2 text-orange-400 hover:text-orange-300 hover:bg-orange-900/20 rounded-lg transition-colors" title="Als Spam markieren">
                        <x-heroicon-o-shield-exclamation class="w-5 h-5" />
                    </button>
                </div>
                <div class="text-xs text-gray-500">
                    {{ $selectedMessage->received_at ? $selectedMessage->received_at->format('d.m.Y H:i') : '' }}
                </div>
            </div>

            {{-- Mail Header --}}
            <div class="p-4 sm:p-6 border-b border-gray-800/50">
                <h2 class="text-xl sm:text-2xl font-bold text-white mb-4 break-words">{{ $selectedMessage->subject }}</h2>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 w-full">
                        <div class="w-10 h-10 shrink-0 rounded-full bg-gray-800 flex items-center justify-center border border-gray-700">
                            <span class="text-[var(--theme-color)] font-serif font-bold">{{ substr($selectedMessage->from_name ?: $selectedMessage->from_email, 0, 1) }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-white font-medium truncate">{{ $selectedMessage->from_name ?: $selectedMessage->from_email }}</div>
                            <div class="text-[10px] sm:text-xs text-gray-400 truncate">&lt;{{ $selectedMessage->from_email }}&gt; an {{ $selectedMessage->to_email }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attachments Section --}}
            @if($selectedMessage->attachments && $selectedMessage->attachments->count() > 0)
                <div x-data="{ showAttachments: false, previewFile: null }" class="border-b border-gray-800/50 bg-gray-900/30 shrink-0">
                    <button @click="showAttachments = !showAttachments" class="w-full flex items-center justify-between p-3 sm:px-6 text-sm font-medium text-gray-400 hover:text-white transition-colors bg-gray-900/50">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-paper-clip class="w-4 h-4" />
                            <span>{{ $selectedMessage->attachments->count() }} Anhang/Anhänge</span>
                        </div>
                        <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200" x-bind:class="showAttachments ? 'rotate-180' : ''" />
                    </button>

                    <div x-show="showAttachments" x-collapse>
                        <div class="p-3 sm:px-6 flex flex-wrap gap-2">
                            @foreach($selectedMessage->attachments as $attachment)
                                <div class="flex items-center p-2 bg-gray-800 border border-gray-700 rounded-lg hover:border-[var(--theme-color)] hover:bg-gray-800/80 cursor-pointer transition-colors max-w-xs w-full sm:w-auto" @click="previewFile = '{{ $attachment->stream_url }}'">
                                    <div class="w-10 h-10 shrink-0 rounded bg-gray-900 flex items-center justify-center mr-3 border border-gray-700">
                                        @php
                                            $ext = strtolower(pathinfo($attachment->filename, PATHINFO_EXTENSION));
                                            $icon = 'document';
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) $icon = 'photo';
                                            elseif (in_array($ext, ['pdf'])) $icon = 'document-text';
                                        @endphp
                                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="w-5 h-5 text-gray-400" />
                                    </div>
                                    <div class="min-w-0 pr-2 flex-col flex justify-center">
                                        <div class="text-xs text-white font-medium truncate" title="{{ $attachment->filename }}">{{ Str::limit($attachment->filename, 20) }}</div>
                                        <div class="text-[10px] text-gray-500">{{ round($attachment->size / 1024, 1) }} KB</div>
                                    </div>
                                    <button wire:click.prevent="downloadAttachment({{ $attachment->id }})" class="ml-auto p-1.5 text-gray-500 hover:text-[var(--theme-color)] hover:bg-[var(--theme-color-10)] rounded-md transition-colors focus:outline-none" @click.stop title="Herunterladen">
                                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Preview Overlay Modal/Inline --}}
                    <div x-show="previewFile" class="relative bg-black/50 border-t border-gray-800 p-2 h-[50vh] transition-all" style="display: none;">
                        <div class="absolute top-4 right-4 z-20">
                            <button @click="previewFile = null" class="p-2 bg-gray-900/90 backdrop-blur-md border border-gray-700 text-gray-400 hover:text-white hover:bg-red-500/20 hover:border-red-500/50 rounded-lg shadow-xl transition-all" title="Vorschau schließen">
                                <x-heroicon-m-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                        <iframe :src="previewFile" class="w-full h-full border-0 rounded-xl bg-white shadow-inner" frameborder="0"></iframe>
                    </div>
                </div>
            @endif

            {{-- Mail Body --}}
            <div class="p-6 flex-1 overflow-y-auto custom-scrollbar">
                <div class="prose prose-invert max-w-none text-gray-300">
                    @if($selectedMessage->body_html)
                        {!! $selectedMessage->body_html !!}
                    @else
                        {!! nl2br(e($selectedMessage->body_plain)) !!}
                    @endif
                </div>
            </div>
        @else
            <div class="flex-1 flex flex-col items-center justify-center text-gray-500">
                <x-heroicon-o-envelope-open class="w-16 h-16 mb-4 opacity-20" />
                <p class="text-lg">Wähle eine E-Mail aus, um sie zu lesen.</p>
            </div>
        @endif
    </div>
        </div> {{-- End INBOX VIEW --}}

        {{-- SETTINGS VIEW --}}
        <div x-show="mode === 'account_settings'" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 lg:translate-x-8" x-transition:enter-end="opacity-100 lg:translate-x-0" x-transition:leave="transition ease-in duration-200 transform absolute" x-transition:leave-start="opacity-100 lg:translate-x-0" x-transition:leave-end="opacity-0 lg:translate-x-8" class="absolute inset-0 bg-gray-950 overflow-y-auto custom-scrollbar" style="display: none;" x-cloak>
            <div class="p-4 sm:p-8 max-w-5xl mx-auto">
                {{-- Settings Form Header --}}
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 pb-6 border-b border-gray-800">
                    <h3 class="text-xl sm:text-2xl font-serif text-white tracking-wide flex items-center gap-3">
                        <button @click.prevent="mobileView = 'folders'" wire:click="closeAccountSettings" class="lg:hidden p-1.5 text-gray-400 hover:text-white bg-gray-800 rounded-lg transition-colors mr-1 text-sm font-sans flex items-center gap-1">
                            <x-heroicon-o-chevron-left class="w-5 h-5"/>
                        </button>
                        <x-heroicon-o-cog class="w-6 h-6 sm:w-8 sm:h-8 text-[var(--theme-color)]" />
                        <span class="truncate">{{ $editAccountId ? 'E-Mail Konto bearbeiten' : 'Neues E-Mail Konto anbinden' }}</span>
                    </h3>
                    <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                        @if($editAccountId)
                            <button wire:click="deleteAccount({{ $editAccountId }})" wire:confirm="Sicher, dass dieses Postfach gelöscht werden soll?" class="text-red-500 hover:text-red-400 transition-colors flex items-center justify-center gap-2 bg-red-900/20 hover:bg-red-900/40 border border-red-900/50 px-3 py-2 sm:px-4 sm:py-2 rounded-lg font-bold flex-1 sm:flex-none text-xs sm:text-base">
                                <x-heroicon-o-trash class="w-4 h-4 sm:w-5 sm:h-5" /> Löschen
                            </button>
                        @endif
                        <button wire:click="closeAccountSettings" class="hidden sm:flex text-gray-400 hover:text-white transition-colors items-center gap-2 bg-gray-800/50 hover:bg-gray-800 px-4 py-2 rounded-lg font-bold">
                            <x-heroicon-o-arrow-left class="w-5 h-5" /> Zurück
                        </button>
                    </div>
                </div>

                {{-- Presets --}}
                <div class="mb-8">
                    <h4 class="text-xs text-gray-500 uppercase tracking-widest font-bold mb-3">Schnellauswahl (Auto-Fill)</h4>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" wire:click="applyPreset('mittwald')" class="flex items-center gap-2 px-4 py-2 bg-[#1A1A1A] hover:bg-[#2A2A2A] border border-gray-700 hover:border-white text-white text-sm font-semibold rounded-xl transition-all">
                            Mittwald / Agenturserver
                        </button>
                        <button type="button" wire:click="applyPreset('gmail')" class="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 border border-gray-700 hover:border-red-500 text-white text-sm font-semibold rounded-xl transition-all">
                            Google Workspace / GMail
                        </button>
                        <button type="button" wire:click="applyPreset('t-online')" class="flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 border border-gray-700 hover:border-[#E20074] text-white text-sm font-semibold rounded-xl transition-all">
                            T-Online
                        </button>
                    </div>
                </div>

                <form wire:submit.prevent="saveAccount" class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    {{-- Allgemeine Infos --}}
                    <div class="lg:col-span-2 space-y-4">
                        <h4 class="text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase border-b border-gray-800 pb-2">Kontodetails</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Anzeigename</label>
                                <input type="text" wire:model="account_name" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" required placeholder="z.B. Zentrale Inbox" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">E-Mail Adresse</label>
                                <input type="email" wire:model.live.debounce.500ms="email" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" required placeholder="hallo@mein-seelenfunke.de" />
                            </div>
                            <div class="md:col-span-2" x-data="{ showPassword: false }">
                                <label class="block text-xs text-gray-400 mb-1">Passwort / App-Passwort</label>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" wire:model="password" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg pl-3 pr-10 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" {{ $editAccountId ? '' : 'required' }} />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-[var(--theme-color)] transition-colors">
                                        <svg x-show="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="showPassword" style="display: none;" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- IMAP Settings --}}
                    <div class="space-y-4">
                        <h4 class="text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase border-b border-gray-800 pb-2">Eingangsserver (IMAP)</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">IMAP Host</label>
                                <input type="text" wire:model="imap_host" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" required />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Benutzer (optional)</label>
                                <input type="text" wire:model="imap_username" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Port</label>
                                <input type="number" wire:model="imap_port" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" required />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Verschlüsselung</label>
                                <select wire:model="imap_encryption" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]">
                                    <option value="ssl">SSL / TLS</option>
                                    <option value="tls">STARTTLS</option>
                                    <option value="">Keine</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- SMTP Settings --}}
                    <div class="space-y-4">
                        <h4 class="text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase border-b border-gray-800 pb-2">Ausgangsserver (SMTP)</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">SMTP Host</label>
                                <input type="text" wire:model="smtp_host" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" required />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Benutzer (optional)</label>
                                <input type="text" wire:model="smtp_username" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Port</label>
                                <input type="number" wire:model="smtp_port" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]" required />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Verschlüsselung</label>
                                <select wire:model="smtp_encryption" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)]">
                                    <option value="ssl">SSL / TLS</option>
                                    <option value="tls">STARTTLS</option>
                                    <option value="">Keine</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Signatur --}}
                    <div class="lg:col-span-2 space-y-4">
                        <h4 class="text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase border-b border-gray-800 pb-2">Signatur</h4>
                        <div>
                            <textarea wire:model="signature" rows="4" class="w-full bg-gray-950 border border-gray-800 text-white rounded-lg px-3 py-2 focus:border-[var(--theme-color)] focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)] font-mono text-xs"></textarea>
                        </div>
                    </div>

                    <div class="lg:col-span-2 pt-6 border-t border-gray-800 flex justify-end gap-3">
                        <button type="submit" class="px-6 py-2 bg-[var(--theme-color)] text-black hover:bg-[var(--theme-color)]/80 font-bold rounded-lg transition-colors">Konto speichern</button>
                    </div>
                </form>
            </div>
        </div> {{-- End SETTINGS VIEW --}}
    </div> {{-- End RIGHT SIDE WRAPPER --}}

    <style>
    /* Custom Scrollbar for Mail Reader */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #374151; /* gray-700 */
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #4B5563; /* gray-600 */
    }
    </style>
</div>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto h-[calc(100vh-2rem)] flex flex-col"
     x-data="{
        init() {
            this.scrollToBottom();
            $watch('$wire.messages', () => { setTimeout(() => this.scrollToBottom(), 50) });
            $watch('$wire.typing', () => { setTimeout(() => this.scrollToBottom(), 50) });
        },
        scrollToBottom() {
            let el = document.getElementById('health-chat-scroll');
            if(el) el.scrollTop = el.scrollHeight;
        }
     }"
     x-on:start-health-ai-inference.window="$wire.processAgent()">
    
    <!-- Page Header & Tabs -->
    <div class="mb-6">
        <div class="sm:flex sm:justify-between sm:items-center mb-4">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-slate-100 font-bold flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-teal-500/20 text-teal-400 flex items-center justify-center border border-teal-500/30 shadow-[0_0_15px_rgba(20,184,166,0.3)]">
                        <x-heroicon-o-heart class="w-7 h-7 animate-pulse" />
                    </div>
                    Dr. Funki Zentrale
                </h1>
                <p class="text-sm text-slate-400 mt-1 uppercase tracking-wider font-mono">Persönliches KI-Gesundheits- & Diagnostik-Terminal</p>
            </div>
            <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
                <button wire:click="clearChat" wire:confirm="Sicher, dass du den Chat-Verlauf leeren möchtest?" class="flex items-center gap-2 px-4 py-2 text-sm font-medium bg-rose-500/10 border border-rose-500/30 text-rose-500 rounded-lg hover:bg-rose-500 hover:text-white transition-all shadow-lg hover:shadow-rose-500/30">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Chat leeren
                </button>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="w-full flex space-x-4 border-b border-slate-700">
            <button wire:click="selectTab('chat')" 
                    class="py-2 px-4 font-semibold text-sm flex items-center gap-2 border-b-2 transition-colors {{ $activeTab === 'chat' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-chat-bubble-left-ellipsis class="w-4 h-4" />
                Interaktiver Chat
            </button>
            <button wire:click="selectTab('plans')" 
                    class="py-2 px-4 font-semibold text-sm flex items-center gap-2 border-b-2 transition-colors {{ $activeTab === 'plans' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                Behandlungspläne
                @if($plans->count() > 0)
                    <span class="bg-teal-500/20 text-teal-400 py-0.5 px-2 rounded-full text-xs ml-1">{{ $plans->count() }}</span>
                @endif
            </button>
            <button wire:click="selectTab('protocols')" 
                    class="py-2 px-4 font-semibold text-sm flex items-center gap-2 border-b-2 transition-colors {{ $activeTab === 'protocols' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-document-text class="w-4 h-4" />
                Akte / Protokolle
                @if($protocols->count() > 0)
                    <span class="bg-indigo-500/20 text-indigo-400 py-0.5 px-2 rounded-full text-xs ml-1">{{ $protocols->count() }}</span>
                @endif
            </button>
            <button wire:click="selectTab('files')" 
                    class="py-2 px-4 font-semibold text-sm flex items-center gap-2 border-b-2 transition-colors {{ $activeTab === 'files' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-folder-open class="w-4 h-4" />
                Dateimanagement
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col lg:flex-row gap-6 h-full flex-1 min-h-0">
        
        <!-- LEFT: Dynamic View Area (2/3) -->
        <div class="flex-1 bg-black/40 backdrop-blur-md border border-slate-700/60 rounded-xl shadow-[0_0_30px_rgba(0,0,0,0.5)] flex flex-col overflow-hidden relative">
            
            @if($activeTab === 'chat')
                <!-- Chat Header -->
                <div class="bg-teal-950/40 border-b border-teal-900/50 p-4 flex justify-between items-center shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="relative w-3 h-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-teal-500"></span>
                        </div>
                        <div>
                            <span class="text-teal-400 font-bold text-sm tracking-widest uppercase truncate block">Gesicherte Verbindung: Dr. Funki</span>
                        </div>
                    </div>
                </div>

                <!-- Messages Area -->
                <div id="health-chat-scroll" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6 custom-scrollbar scroll-smooth">
                    @forelse($messages as $msg)
                        <div class="flex flex-col {{ $msg['role'] === 'user' ? 'items-end' : 'items-start' }} animate-fade-in-up">
                        <div class="flex items-center gap-4 mb-2 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                                <div class="w-16 h-16 rounded shrink-0 flex justify-center items-center bg-{{ $msg['color'] ?: 'teal-500' }}/10 border border-{{ $msg['color'] ?: 'teal-500' }}/40 shadow-[0_0_12px_currentColor] text-{{ $msg['color'] ?: 'teal-500' }} overflow-hidden">
                                    @if(isset($msg['profile_picture']) && $msg['profile_picture'])
                                        @php
                                            $pp = $msg['profile_picture'];
                                            $src = (str_starts_with($pp, 'images/') || str_starts_with($pp, '/'))
                                                   ? asset($pp) : Storage::url($pp);
                                        @endphp
                                        <img src="{{ $src }}" class="w-full h-full object-cover" alt="Profile">
                                    @else
                                        <x-dynamic-component :component="'heroicon-o-' . ($msg['icon'] ?: 'user')" class="w-8 h-8" />
                                    @endif
                                </div>
                                <span class="text-xs font-bold text-{{ $msg['color'] ?: 'teal-500' }} tracking-widest uppercase truncate max-w-[200px]">{{ $msg['name'] }}</span>
                            </div>
                            <!-- Wichtig: markdown fähige Ausgabe class="prose prose-invert max-w-none" hinzuzufügen, falls Markdown via Parsedown konvertiert werden soll. Wir belassen es bei nl2br fürs einfache. -->
                            <div class="max-w-[90%] md:max-w-[80%] text-sm leading-relaxed p-4 rounded-xl {{ $msg['role'] === 'user' ? 'bg-slate-800 border border-slate-700 text-slate-300 rounded-tr-none shadow-md font-mono' : 'bg-teal-950/20 text-teal-50/90 rounded-tl-none border border-teal-900/60 shadow-[0_0_15px_rgba(20,184,166,0.05)] prose prose-invert prose-headings:text-teal-400 prose-a:text-teal-300 prose-sm focus:outline-none' }}">
                                @if($msg['role'] === 'assistant')
                                    {!! Str::markdown($msg['content']) !!}
                                @else
                                    {!! nl2br(e($msg['content'])) !!}
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-teal-700/40 font-mono tracking-widest gap-4">
                            <x-heroicon-o-heart class="w-12 h-12 opacity-50" />
                            <p>Dr. Funki ist bereit.</p>
                        </div>
                    @endforelse

                    @if($typing)
                        @php
                            $typingAgent = \App\Models\Ai\AiAgent::find($agentId);
                        @endphp
                        <div class="flex flex-col items-start animate-fade-in-up">
                            <div class="flex items-center gap-4 mb-2">
                                <div class="w-16 h-16 rounded shrink-0 flex justify-center items-center bg-teal-500/10 border border-teal-500/40 shadow-[0_0_12px_currentColor] text-teal-500 overflow-hidden">
                                    @if($typingAgent && $typingAgent->profile_picture)
                                        <img src="{{ Storage::url($typingAgent->profile_picture) }}" class="w-full h-full object-cover" alt="Dr. Funki">
                                    @else
                                        <x-heroicon-o-user-plus class="w-8 h-8 animate-pulse" />
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-teal-500 tracking-widest uppercase">{{ $typingAgent->name ?? 'Dr. Funki' }}</span>
                                </div>
                            </div>
                            <div class="max-w-[85%] font-mono text-base bg-teal-950/20 text-teal-400 rounded-xl rounded-tl-none border border-teal-900/60 p-4 shadow-lg flex gap-1">
                                Lokalisiere Lösung <span class="animate-bounce">.</span><span class="animate-bounce delay-75">.</span><span class="animate-bounce delay-150">.</span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Chat Input mit @-Mentions -->
                <div x-data="aiHealthChatInput()" 
                     x-init="files = @js($uploadedHealthFiles)"
                     @health-files-updated.window="files = $event.detail.files"
                     class="p-3 sm:p-4 bg-slate-900/80 backdrop-blur-md border-t border-slate-800 shrink-0 z-10 relative">
                    
                    <!-- Mentions Dropdown -->
                    <div x-show="showMentions" style="display: none;" 
                         x-transition.opacity.duration.200ms
                         class="absolute bottom-full left-12 mb-2 w-72 bg-slate-800 border border-slate-700 shadow-2xl rounded-xl overflow-hidden z-[100] max-h-48 overflow-y-auto custom-scrollbar">
                        <template x-for="(f, index) in filteredFiles" :key="index">
                            <button type="button" 
                                    @click="insertMention(f.name)"
                                    @mouseover="selectedIndex = index"
                                    :class="selectedIndex === index ? 'bg-teal-500/20 text-teal-400 border-l-2 border-teal-500' : 'text-slate-300 hover:bg-slate-700/50 border-l-2 border-transparent'"
                                    class="w-full text-left px-4 py-2.5 text-xs truncate transition-all flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-4 h-4 shrink-0 opacity-70" />
                                <span x-text="f.name"></span>
                            </button>
                        </template>
                        <div x-show="filteredFiles.length === 0" class="px-4 py-3 text-xs text-slate-500 italic bg-slate-800/50">
                            Keine Dokumente gefunden...
                        </div>
                    </div>

                    <form wire:submit.prevent="sendMessage" class="relative flex items-end">
                        <div class="absolute bottom-0 left-0 flex items-center pl-4 pb-3.5 pointer-events-none text-slate-500">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                        </div>
                        
                        <textarea wire:model="input"
                                  x-ref="chatInput"
                                  @input="handleInput"
                                  @keydown="handleKeydown"
                                  rows="1"
                                  class="w-full bg-black/40 border border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 text-slate-200 placeholder-slate-500 rounded-3xl py-3.5 pl-12 pr-14 transition-all resize-none custom-scrollbar"
                                  style="min-height: 52px; max-height: 150px; line-height: 1.5;"
                                  placeholder="Tippe deine Nachricht... (Nutze @ für Dateien)"
                                  autocomplete="off"></textarea>

                        <div class="absolute bottom-0 right-1 flex items-center pr-1 pb-1.5">
                            <button type="submit" 
                                    class="w-10 h-10 flex justify-center items-center rounded-full bg-teal-600 hover:bg-teal-500 text-white transition-all shadow-lg hover:shadow-teal-500/30 disabled:opacity-50 focus:outline-none"
                                    wire:loading.attr="disabled"
                                    wire:target="sendMessage">
                                <x-heroicon-m-paper-airplane class="w-4 h-4 -rotate-45 ml-0.5" />
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            @if($activeTab === 'plans')
                <!-- Behandlungspläne List Area -->
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    <h2 class="text-xl font-bold mb-4 text-slate-100 flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-teal-500" />
                        Behandlungspläne
                    </h2>
                    
                    <div class="grid gap-4">
                        @forelse($plans as $plan)
                            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 hover:bg-slate-800 hover:border-teal-500/30 transition-all group relative overflow-hidden flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                                <div class="absolute inset-y-0 left-0 w-1 bg-[{{ $plan->status === 'active' ? '#f59e0b' : '#10b981' }}]"></div>
                                
                                <div class="pl-2">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h3 class="font-bold text-slate-100">{{ $plan->title }}</h3>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-widest font-bold {{ $plan->status === 'active' ? 'bg-amber-500/20 text-amber-500 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-500 border border-emerald-500/30' }}">
                                            {{ $plan->status === 'active' ? 'Aktiv' : 'Durchgeführt' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 font-mono">
                                        {{ $plan->start_date ? $plan->start_date->format('d.m.Y') : 'Unbekannt' }} - {{ $plan->end_date ? $plan->end_date->format('d.m.Y') : 'Offen' }}
                                        | {{ $plan->items->count() }} Positionen
                                    </p>
                                </div>

                                <div class="flex items-center gap-2 pl-2 sm:pl-0 shrink-0">
                                    <a href="{{ route('ceo.gesundheit.plan.pdf', $plan->id) }}" class="btn btn-sm bg-teal-600 hover:bg-teal-500 text-white rounded-lg shadow-lg flex items-center gap-2">
                                        <x-heroicon-s-arrow-down-tray class="w-4 h-4" />
                                        PDF Download
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-slate-500 bg-slate-800/30 rounded-xl border border-dashed border-slate-700">
                                <x-heroicon-o-clipboard-document-check class="w-12 h-12 mx-auto mb-3 opacity-30" />
                                <p>Dr. Funki hat noch keine Behandlungspläne ausgestellt.<br>Schreibe ihm im Chat deine Symptome, damit er einen Plan erstellt.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

            @if($activeTab === 'protocols')
                <!-- Protokolle List Area -->
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    <h2 class="text-xl font-bold mb-4 text-slate-100 flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-6 h-6 text-indigo-500" />
                        Patientenakte / Med. Protokolle
                    </h2>
                    
                    <div class="space-y-6">
                        @forelse($protocols as $protocol)
                            <div class="bg-indigo-950/20 border border-indigo-900/30 rounded-xl p-5 relative overflow-hidden">
                                <div class="flex items-center justify-between mb-4 border-b border-indigo-500/20 pb-3">
                                    <div class="text-xs text-indigo-400 font-mono tracking-widest uppercase flex items-center gap-2">
                                        <x-heroicon-o-clock class="w-4 h-4" />
                                        {{ $protocol->created_at->format('d.m.Y H:i') }} Uhr
                                    </div>
                                    <div class="text-[10px] bg-indigo-500/10 text-indigo-500 px-2 py-0.5 rounded-full border border-indigo-500/20">Archiviert durch Dr. Funki</div>
                                </div>
                                <div class="prose prose-invert prose-indigo prose-sm max-w-none text-slate-300">
                                    {!! Str::markdown($protocol->content) !!}
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-slate-500 bg-slate-800/30 rounded-xl border border-dashed border-slate-700">
                                <x-heroicon-o-folder-open class="w-12 h-12 mx-auto mb-3 opacity-30" />
                                <p>Keine Befund- oder Analyseprotokolle vorhanden.<br>Du kannst Dr. Funki im Chat bitten, ein Ergebnisprotokoll anzufertigen.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

            @if($activeTab === 'files')
                <!-- Dateimanagement Area -->
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-slate-100 flex items-center gap-2">
                            <x-heroicon-o-folder-open class="w-6 h-6 text-teal-500" />
                            Secure File Management
                        </h2>
                        <div class="text-xs text-slate-400 font-mono bg-slate-800/50 px-3 py-1.5 rounded border border-slate-700 flex items-center gap-2">
                            <x-heroicon-o-server class="w-4 h-4 text-slate-500" />
                            Storage Pfad: <span class="text-teal-400 font-bold">{{ $currentPath }}</span>
                        </div>
                    </div>

                    <!-- Actions & Upload -->
                    <div class="flex gap-4 mb-6" x-data="{ folderName: '' }">
                        @if($currentPath !== 'wiki/health')
                            <button wire:click="goUp" class="btn bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 px-4 py-2 rounded-lg text-sm transition-colors flex items-center">
                                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                                Zurück zur Übersicht
                            </button>
                        @endif
                        <div class="flex gap-2">
                            <input type="text" x-model="folderName" placeholder="Neuer Ordnername..." class="bg-slate-900 border border-slate-700 text-slate-200 text-sm rounded-lg px-4 py-2 focus:ring-teal-500 focus:border-teal-500 w-48">
                            <button @click="$wire.createFolder(folderName); folderName=''" type="button" class="btn bg-teal-600 hover:bg-teal-500 text-white px-4 py-2 rounded-lg transition-colors shadow-lg shadow-teal-500/20 flex items-center">
                                <x-heroicon-o-folder-plus class="w-4 h-4 mr-2" />
                                Erstellen
                            </button>
                        </div>
                    </div>

                    <!-- Drag & Drop Zone -->
                    <div x-data="{ isDropping: false }"
                         x-on:dragover.prevent="isDropping = true"
                         x-on:dragleave.prevent="isDropping = false"
                         x-on:drop.prevent="isDropping = false; $wire.uploadMultiple('healthFiles', $event.dataTransfer.files)"
                         class="w-full relative mb-6">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-xl cursor-pointer transition-all duration-300 bg-slate-900/60 shadow-inner"
                               x-bind:class="isDropping ? 'border-teal-500 bg-teal-500/10 scale-[1.01]' : 'border-slate-700 hover:border-teal-500/50'">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <span x-bind:class="isDropping ? 'text-teal-400 animate-bounce' : 'text-slate-500'" class="mb-2">
                                    <x-heroicon-o-cloud-arrow-up class="w-8 h-8" />
                                </span>
                                <p class="text-sm text-slate-400 font-semibold mb-1">Dateien in <span class="text-teal-500">/{{ basename($currentPath) }}</span> ablegen oder klicken</p>
                                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-mono">PDF, PNG, JPG (MAX. 10MB)</p>
                            </div>
                            <input type="file" wire:model="healthFiles" multiple class="hidden" accept=".pdf,.png,.jpg,.jpeg">
                        </label>
                        <div wire:loading wire:target="healthFiles" class="absolute inset-0 bg-slate-900/90 backdrop-blur-md rounded-xl flex items-center justify-center z-10 border border-teal-500/50">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="animate-spin h-8 w-8 text-teal-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs text-teal-400 font-mono tracking-widest uppercase">Uploading securely...</span>
                            </div>
                        </div>
                    </div>

                    <!-- File List Data -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar border border-slate-700/60 rounded-xl bg-black/20 shadow-inner relative">
                        <!-- Table Header -->
                        <div class="sticky top-0 bg-slate-800 border-b border-slate-700 flex text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-3 z-10">
                            <div class="flex-1">Name / Verzeichnis</div>
                            <div class="w-32 text-right hidden sm:block">Größe</div>
                            <div class="w-16 text-center">Aktion</div>
                        </div>
                        
                        <!-- Items -->
                        <div>
                            @forelse($uploadedHealthFiles as $item)
                                <div class="flex items-center p-3 border-b border-slate-800 hover:bg-slate-800/60 transition-colors group">
                                    <div class="flex-1 flex items-center gap-3 overflow-hidden pr-4">
                                        @if($item['type'] === 'folder')
                                            <div class="w-10 h-10 rounded-lg bg-teal-500/10 flex items-center justify-center shrink-0 border border-teal-500/20 text-teal-400">
                                                <x-heroicon-s-folder class="w-5 h-5" />
                                            </div>
                                            <button wire:click="openFolder('{{ $item['name'] }}')" class="font-bold text-slate-200 hover:text-teal-400 text-sm truncate focus:outline-none">
                                                {{ $item['name'] }}
                                            </button>
                                        @else
                                            @php
                                                $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                            @endphp
                                            @if($ext === 'pdf')
                                                <div class="w-10 h-10 rounded-lg bg-rose-500/10 flex items-center justify-center shrink-0 border border-rose-500/20 text-rose-400">
                                                    <x-heroicon-s-document-text class="w-5 h-5" />
                                                </div>
                                            @elseif(in_array($ext, ['png', 'jpg', 'jpeg']))
                                                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0 border border-blue-500/20 text-blue-400">
                                                    <x-heroicon-m-photo class="w-5 h-5" />
                                                </div>
                                            @else
                                                <div class="w-10 h-10 rounded-lg bg-slate-700 flex items-center justify-center shrink-0">
                                                    <x-heroicon-s-document class="w-5 h-5 text-slate-400" />
                                                </div>
                                            @endif
                                            
                                            <a href="{{ $item['url'] }}" target="_blank" class="text-sm font-medium text-slate-300 hover:text-teal-400 truncate focus:outline-none block">
                                                {{ $item['name'] }}
                                            </a>
                                        @endif
                                    </div>
                                    <div class="w-32 text-right text-xs text-slate-500 font-mono hidden sm:block">
                                        {{ $item['type'] === 'folder' ? '--' : number_format($item['size'] / 1024, 2) . ' KB' }}
                                    </div>
                                    <div class="w-16 flex justify-center">
                                        <button wire:click="deleteItem('{{ $item['path'] }}')" 
                                                wire:confirm="Möchtest du '{{ $item['name'] }}' unwiderruflich löschen?"
                                                class="text-slate-500 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition-opacity p-2 rounded-full hover:bg-rose-500/10 focus:outline-none">
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="p-12 text-center flex flex-col items-center">
                                    <x-heroicon-o-folder-open class="w-16 h-16 text-slate-600 mb-4 opacity-30" />
                                    <h4 class="text-slate-300 font-bold mb-1">Verzeichnis ist leer</h4>
                                    <p class="text-xs text-slate-500">Lege Dateien hier ab oder erstelle einen neuen Ordner.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('aiHealthChatInput', () => ({
        files: [],
        showMentions: false,
        mentionQuery: '',
        selectedIndex: 0,
        mentionStartIndex: -1,

        get filteredFiles() {
            if (!this.mentionQuery) return this.files;
            return this.files.filter(f => f.name.toLowerCase().includes(this.mentionQuery.toLowerCase()));
        },

        handleInput(e) {
            this.autoResize(e.target);

            const val = e.target.value;
            const cursorPos = e.target.selectionStart;
            
            // Check if we are typing a mention
            const textBeforeCursor = val.slice(0, cursorPos);
            const words = textBeforeCursor.split(/\s+/);
            const currentWord = words[words.length - 1];

            if (currentWord.startsWith('@')) {
                this.showMentions = true;
                this.mentionQuery = currentWord.slice(1);
                this.mentionStartIndex = cursorPos - currentWord.length;
                this.selectedIndex = 0; // Reset selection when query changes
            } else {
                this.closeMentions();
            }
        },

        handleKeydown(e) {
            // submit on Enter without Shift
            if (e.key === 'Enter' && !e.shiftKey && !this.showMentions) {
                e.preventDefault();
                this.$wire.sendMessage();
                // Reset textarea soon
                setTimeout(() => {
                    e.target.style.height = 'auto';
                }, 50);
                return;
            }

            if (!this.showMentions) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (this.selectedIndex < this.filteredFiles.length - 1) {
                    this.selectedIndex++;
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (this.selectedIndex > 0) {
                    this.selectedIndex--;
                }
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                if (this.filteredFiles.length > 0) {
                    this.insertMention(this.filteredFiles[this.selectedIndex].name);
                }
            } else if (e.key === 'Escape') {
                this.closeMentions();
            }
        },

        insertMention(fileName) {
            const el = this.$refs.chatInput;
            const val = el.value;
            
            const beforeMention = val.slice(0, this.mentionStartIndex);
            const afterCursor = val.slice(el.selectionStart);
            
            // Insert format: "filename " (with trailing space)
            const insertText = `@${fileName} `;
            
            const newVal = beforeMention + insertText + afterCursor;
            el.value = newVal;
            
            // Dispatch input so livewire catches it
            el.dispatchEvent(new Event('input'));
            
            // Set cursor position after the inserted text
            const newPos = this.mentionStartIndex + insertText.length;
            el.focus();
            el.setSelectionRange(newPos, newPos);
            
            this.closeMentions();
            this.autoResize(el);
        },

        closeMentions() {
            this.showMentions = false;
            this.mentionQuery = '';
            this.mentionStartIndex = -1;
            this.selectedIndex = 0;
        },

        autoResize(el) {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        }
    }))
});
</script>

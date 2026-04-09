<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="px-0 sm:px-6 lg:px-8 py-0 sm:py-8 w-full max-w-9xl mx-auto h-[100dvh] sm:h-[calc(100vh-2rem)] flex flex-col"
     x-data="{
        init() {
            this.scrollToBottom();
            $watch('$wire.messages', () => { setTimeout(() => this.scrollToBottom(), 50) });
            $watch('$wire.typing', () => { setTimeout(() => this.scrollToBottom(), 50) });
            $watch('$wire.activeTab', (val) => {
                if(val === 'chat') { setTimeout(() => this.scrollToBottom(), 50) }
            });
        },
        scrollToBottom() {
            let el = document.getElementById('health-chat-scroll');
            if(el) el.scrollTop = el.scrollHeight;
        }
     }"
     x-on:start-health-ai-inference.window="$wire.processAgent()">

    <!-- Page Header & Tabs -->
    <div class="mb-2 sm:mb-6 px-3 sm:px-0 pt-3 sm:pt-0">
        <div class="flex justify-between items-center mb-2 sm:mb-4">
            <div>
                <h1 class="text-lg sm:text-2xl md:text-3xl text-slate-100 font-bold flex items-center gap-2 sm:gap-3">
                    <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-full bg-teal-500/20 text-teal-400 flex items-center justify-center border border-teal-500/30 shadow-[0_0_15px_rgba(20,184,166,0.3)]">
                        <x-heroicon-o-heart class="w-5 h-5 sm:w-7 sm:h-7 animate-pulse" />
                    </div>
                    Dr. Funki
                </h1>
                <p class="hidden sm:block text-sm text-slate-400 mt-1 uppercase tracking-wider font-mono">Persönliches KI-Gesundheits- & Diagnostik-Terminal</p>
            </div>
            <div class="flex justify-end gap-2 shrink-0">
                <button wire:click="clearChat" wire:confirm="Sicher, dass du den Chat-Verlauf leeren möchtest?" class="flex items-center gap-1 sm:gap-2 px-2 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium bg-rose-500/10 border border-rose-500/30 text-rose-500 rounded-lg hover:bg-rose-500 hover:text-white transition-all shadow-lg hover:shadow-rose-500/30">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    <span class="hidden sm:inline">Chat leeren</span>
                </button>
            </div>
        </div>        <!-- Tab Navigation -->
        <div class="w-full flex space-x-2 sm:space-x-4 border-b border-slate-700 overflow-x-auto custom-scrollbar pb-1 px-3 sm:px-0">
            <button wire:click="selectTab('chat')"
                    class="py-2 px-3 sm:px-4 font-semibold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 shrink-0 border-b-2 transition-colors {{ $activeTab === 'chat' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-chat-bubble-left-ellipsis class="w-4 h-4" />
                Interaktiver Chat
            </button>
            <button wire:click="selectTab('plans')"
                    class="py-2 px-3 sm:px-4 font-semibold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 shrink-0 border-b-2 transition-colors {{ $activeTab === 'plans' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                Behandlungspläne
                @if($plans->count() > 0)
                    <span class="bg-teal-500/20 text-teal-400 py-0.5 px-2 rounded-full text-[10px] sm:text-xs ml-1">{{ $plans->count() }}</span>
                @endif
            </button>
            <button wire:click="selectTab('protocols')"
                    class="py-2 px-3 sm:px-4 font-semibold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 shrink-0 border-b-2 transition-colors {{ $activeTab === 'protocols' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-document-text class="w-4 h-4" />
                Akte / Protokolle
                @if($protocols->count() > 0)
                    <span class="bg-indigo-500/20 text-indigo-400 py-0.5 px-2 rounded-full text-[10px] sm:text-xs ml-1">{{ $protocols->count() }}</span>
                @endif
            </button>
            <button wire:click="selectTab('files')"
                    class="py-2 px-3 sm:px-4 font-semibold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 shrink-0 border-b-2 transition-colors {{ $activeTab === 'files' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-folder-open class="w-4 h-4" />
                Dateimanagement
            </button>
            <button wire:click="selectTab('medications')"
                    class="py-2 px-3 sm:px-4 font-semibold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 shrink-0 border-b-2 transition-colors {{ $activeTab === 'medications' ? 'border-teal-500 text-teal-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-600' }}">
                <x-heroicon-o-beaker class="w-4 h-4" />
                Aktive Medikamente
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col lg:flex-row gap-6 h-full flex-1 min-h-0">

        @if(in_array($activeTab, ['chat', 'plans', 'protocols']))
        <!-- LEFT: Dynamic View Area (2/3) -->
        <div class="flex-1 bg-black/40 backdrop-blur-md border-y sm:border border-slate-700/60 rounded-none sm:rounded-xl shadow-[0_0_30px_rgba(0,0,0,0.5)] flex flex-col overflow-hidden relative">

            @if($activeTab === 'chat')
                <!-- Chat Header -->
                <div class="bg-teal-950/40 border-b border-teal-900/50 p-2 sm:p-4 flex flex-row justify-between items-center shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-teal-400 font-bold text-[10px] sm:text-sm tracking-widest uppercase truncate block hidden sm:block">Gesicherte Verbindung: Dr. Funki</span>
                        <span class="text-teal-400 font-bold text-[10px] uppercase truncate block sm:hidden">Dr. Funki aktiv</span>
                    </div>
                    <!-- Chat Search -->
                    <div class="relative w-32 sm:w-64">
                        <div class="absolute inset-y-0 left-0 pl-2 sm:pl-3 flex items-center pointer-events-none">
                            <x-heroicon-o-magnifying-glass class="w-3 h-3 sm:w-4 sm:h-4 text-teal-500/50" />
                        </div>
                        <input type="text" wire:model.live.debounce.300ms="searchChat" class="w-full bg-teal-950/50 border border-teal-800/60 rounded-full py-1 sm:py-1.5 pl-7 sm:pl-9 pr-3 text-[10px] sm:text-xs text-teal-100 placeholder-teal-600 focus:outline-none focus:ring-1 focus:ring-teal-500 focus:border-teal-500 transition-all font-mono" placeholder="Suchen...">
                    </div>
                </div>

                <!-- Messages Area -->
                <div id="health-chat-scroll" class="flex-1 overflow-y-auto p-3 sm:p-6 space-y-5 sm:space-y-6 custom-scrollbar scroll-smooth relative">
                    <!-- Manual Scroll to Bottom Button -->
                    <button @click="scrollToBottom()" class="fixed bottom-24 lg:bottom-28 right-8 lg:right-12 w-10 h-10 bg-teal-600/80 hover:bg-teal-500 text-white rounded-full shadow-[0_0_15px_rgba(20,184,166,0.5)] flex justify-center items-center backdrop-blur-sm transition-all hover:scale-110 z-50 hover:bg-teal-500/90 hidden sm:flex" title="Nach unten scrollen">
                        <x-heroicon-o-arrow-down class="w-5 h-5" />
                    </button>

                    @php
                        $filteredMessages = empty($searchChat) ? $messages : collect($messages)->filter(function($m) use ($searchChat) {
                            return stripos($m['content'], $searchChat) !== false || stripos($m['name'], $searchChat) !== false;
                        })->all();
                    @endphp

                    @forelse($filteredMessages as $msg)
                        <div class="flex flex-col {{ $msg['role'] === 'user' ? 'items-end' : 'items-start' }} animate-fade-in-up">
                        <div class="flex items-center gap-4 mb-2 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                                <div class="w-16 h-16 rounded shrink-0 flex justify-center items-center bg-{{ $msg['color'] ?: 'teal-500' }}/10 border border-{{ $msg['color'] ?: 'teal-500' }}/40 shadow-[0_0_12px_currentColor] text-{{ $msg['color'] ?: 'teal-500' }} overflow-hidden">
                                    @if(isset($msg['profile_picture']) && $msg['profile_picture'])
                                        @php
                                            $pp = $msg['profile_picture'];
                                            $src = (str_starts_with($pp, 'images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/'))
                                                   ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                                        @endphp
                                        <img src="{{ $src }}" class="w-full h-full object-cover" alt="Profile">
                                    @else
                                        <x-dynamic-component :component="'heroicon-o-' . ($msg['icon'] ?: 'user')" class="w-8 h-8" />
                                    @endif
                                </div>
                                <span class="text-[10px] sm:text-xs font-bold text-{{ $msg['color'] ?: 'teal-500' }} tracking-widest uppercase truncate max-w-[200px]">{{ $msg['name'] }}</span>
                            </div>
                            <!-- Wichtig: markdown fähige Ausgabe class="prose prose-invert max-w-none" hinzuzufügen, falls Markdown via Parsedown konvertiert werden soll. Wir belassen es bei nl2br fürs einfache. -->
                            <div class="relative max-w-[95%] sm:max-w-[85%] md:max-w-[80%] text-sm leading-relaxed p-2.5 sm:p-4 pb-5 sm:pb-6 rounded-xl break-words {{ $msg['role'] === 'user' ? 'bg-slate-800 border border-slate-700 text-slate-300 rounded-tr-none shadow-md font-mono text-left' : 'bg-teal-950/20 text-teal-50/90 rounded-tl-none border border-teal-900/60 shadow-[0_0_15px_rgba(20,184,166,0.05)] prose prose-invert prose-headings:text-teal-400 prose-a:text-teal-300 prose-sm focus:outline-none prose-p:break-words text-left' }}">
                                @php
                                    $rendered = $msg['role'] === 'assistant'
                                        ? Str::markdown($msg['content'])
                                        : nl2br(e($msg['content']));

                                    if (!empty($searchChat)) {
                                        $term = preg_quote($searchChat, '/');
                                        $rendered = preg_replace("/($term)(?![^<]*>)/i", '<mark class="bg-blue-500/30 text-blue-200 px-1 rounded">$1</mark>', $rendered);
                                    }
                                @endphp
                                {!! $rendered !!}

                                @if($msg['role'] === 'user' && isset($msg['id']))
                                    <div class="absolute bottom-1 right-1 sm:right-2 flex gap-1.5 opacity-30 hover:opacity-100 transition-opacity">
                                        <button wire:click="repostMessage('{{ $msg['id'] }}')" class="text-slate-400 hover:text-teal-400 transition-colors" title="Repost">
                                            <x-heroicon-o-arrow-path class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                        </button>
                                        <button wire:click="continueFromMessage('{{ $msg['id'] }}')" class="text-slate-400 hover:text-rose-400 transition-colors" title="Chat ab hier">
                                            <x-heroicon-o-arrow-uturn-up class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                        </button>
                                    </div>
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
                                        <img src="{{ \Illuminate\Support\Str::startsWith($typingAgent->profile_picture, 'shop/') ? asset($typingAgent->profile_picture) : Storage::url($typingAgent->profile_picture) }}" class="w-full h-full object-cover" alt="Dr. Funki">
                                    @else
                                        <x-heroicon-o-user-plus class="w-8 h-8 animate-pulse" />
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-teal-500 tracking-widest uppercase">{{ $typingAgent->name ?? 'Dr. Funki' }}</span>
                                </div>
                            </div>
                            <div class="max-w-[85%] font-mono text-sm bg-teal-950/20 text-teal-400 rounded-xl rounded-tl-none border border-teal-900/60 p-4 shadow-lg flex flex-col gap-3" wire:poll.500ms="updateLiveState">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-book-open class="w-5 h-5 animate-pulse shrink-0" />
                                    <span>
                                        {{ empty($aiLiveState) ? 'Denkprozesse laufen' : ($aiLiveState['action_text'] ?? 'Initialisiere...') }}
                                        <span class="animate-bounce">.</span><span class="animate-bounce delay-75">.</span><span class="animate-bounce delay-150">.</span>
                                    </span>
                                </div>
                                @if(!empty($aiLiveState) && isset($aiLiveState['progress']))
                                <div class="w-full bg-teal-950/60 rounded-full h-1.5 overflow-hidden border border-teal-900/50">
                                    <div class="bg-teal-400 h-1.5 rounded-full transition-all duration-500 shadow-[0_0_8px_rgba(45,212,191,0.8)]" style="width: {{ $aiLiveState['progress'] }}%"></div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Chat Input mit @-Mentions -->
                <div x-data="aiHealthChatInput()"
                     x-init="files = @js($uploadedHealthFiles)"
                     @health-files-updated.window="files = $event.detail.files"
                     class="p-2 sm:p-4 bg-slate-900/80 backdrop-blur-md border-t border-slate-800 shrink-0 z-10 relative">

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
                        <div class="absolute bottom-0 left-0 flex items-center pl-3 sm:pl-4 pb-2.5 sm:pb-3.5 pointer-events-none text-slate-500">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                        </div>

                        <textarea wire:model="input"
                                  x-ref="chatInput"
                                  @input="handleInput"
                                  @keydown="handleKeydown"
                                  rows="1"
                                  class="w-full bg-black/40 border border-slate-700 focus:border-teal-500 focus:ring-1 focus:ring-teal-500 text-slate-200 placeholder-slate-500 rounded-3xl py-2.5 sm:py-3.5 pl-10 sm:pl-12 pr-12 sm:pr-14 transition-all resize-none custom-scrollbar text-base"
                                  style="min-height: 48px; max-height: 150px; line-height: 1.5;"
                                  placeholder="Tippe deine Nachricht... (Nutze @ für Dateien)"
                                  autocomplete="off"></textarea>

                        <div class="absolute bottom-0 right-1 flex items-center pr-1 pb-1 sm:pb-1.5">
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
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 custom-scrollbar">
                    <h2 class="text-lg sm:text-xl font-bold mb-4 text-slate-100 flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 sm:w-6 sm:h-6 text-teal-500" />
                        Behandlungspläne
                    </h2>

                    <div class="grid gap-4">
                        @forelse($plans as $plan)
                            <div x-data="{ expanded: false }" class="bg-slate-800/40 border border-slate-700/50 rounded-xl p-5 hover:border-teal-500/40 transition-all flex flex-col shadow-lg">
                                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 w-full cursor-pointer" @click="expanded = !expanded">
                                    <div class="pl-2 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <x-heroicon-o-chevron-down class="w-5 h-5 text-slate-400 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                                            <h3 class="font-bold text-slate-100 text-lg">{{ $plan->title }}</h3>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-widest font-bold {{ $plan->status === 'active' ? 'bg-amber-500/20 text-amber-500 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-500 border border-emerald-500/30' }}">
                                                {{ $plan->status === 'active' ? 'Aktiv' : 'Durchgeführt' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400 font-mono ml-7">
                                            {{ $plan->start_date ? $plan->start_date->format('d.m.Y') : 'Unbekannt' }} - {{ $plan->end_date ? $plan->end_date->format('d.m.Y') : 'Offen' }}
                                            | {{ $plan->items->count() }} Positionen
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2 pl-7 sm:pl-0 shrink-0">
                                        <button wire:click.prevent="downloadPlanPdf('{{ $plan->id }}')" @click.stop class="btn btn-sm bg-teal-600 hover:bg-teal-500 text-white p-2.5 rounded-lg shadow-lg flex items-center justify-center transition-transform hover:scale-105 z-10 relative" title="PDF Download">
                                            <x-heroicon-s-arrow-down-tray class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                @php
                                    $totalItems = $plan->items->count();
                                    $completedItems = $plan->items->where('is_completed', true)->count();
                                    $progressPercent = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
                                    $nextStep = $plan->items->where('is_completed', false)->first();
                                @endphp

                                <!-- Smart Progress Bar -->
                                <div class="mt-4 mb-2 pl-2 pr-2 w-full">
                                    <div class="flex justify-between items-end mb-1">
                                        <span class="text-xs font-semibold text-slate-300">Behandlungsfortschritt</span>
                                        <span class="text-xs font-bold {{ $progressPercent === 100 ? 'text-emerald-400' : 'text-teal-400' }}">{{ $progressPercent }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-900 rounded-full h-2 border border-slate-700/50 overflow-hidden relative shadow-inner">
                                        <div class="h-full {{ $progressPercent === 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-teal-600 to-teal-400' }} transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                                    </div>
                                    @if($nextStep)
                                        <div class="mt-2 text-[11px] text-slate-400 flex items-start gap-1">
                                            <x-heroicon-m-arrow-right-circle class="w-3.5 h-3.5 text-teal-500 shrink-0 mt-0.5" />
                                            <span><strong class="text-slate-300">Nächstes Todo:</strong> {{ $nextStep->name }} {{ $nextStep->dosage ? '('.$nextStep->dosage.')' : '' }}</span>
                                        </div>
                                    @elseif($totalItems > 0 && $progressPercent === 100)
                                        <div class="mt-2 text-[11px] text-emerald-400 flex items-center gap-1">
                                            <x-heroicon-m-check-badge class="w-3.5 h-3.5 shrink-0" />
                                            <span>Behandlung erfolgreich verifiziert!</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Checkable Items List (Collapsible) -->
                                <div x-show="expanded" x-collapse>
                                    @if($totalItems > 0)
                                        <div class="mt-4 space-y-2 border-t border-slate-700/50 pt-5 w-full pl-2 pr-2">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 block">Rezeptierte Aufgaben & Medikamente</span>
                                            @foreach($plan->items as $item)
                                                <div class="flex items-start gap-3 p-3 rounded-lg hover:bg-slate-800/80 transition-colors {{ $item->is_completed ? 'opacity-50 bg-slate-900/50 border border-slate-700/30' : 'bg-slate-800/30 border border-slate-700/50 shadow-sm' }}">
                                                    <button wire:click="togglePlanItem('{{ $item->id }}')" class="mt-0.5 shrink-0 focus:outline-none transition-transform hover:scale-110">
                                                        @if($item->is_completed)
                                                            <x-heroicon-s-check-circle class="w-6 h-6 text-emerald-500 drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]" />
                                                        @else
                                                            <x-heroicon-o-check-circle class="w-6 h-6 text-slate-500 hover:text-teal-400 transition-colors" />
                                                        @endif
                                                    </button>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-semibold {{ $item->is_completed ? 'line-through text-slate-500' : 'text-slate-200' }}">{{ $item->name }}</p>
                                                        <div class="flex items-center gap-3 mt-1.5 mb-1">
                                                            <span class="text-[10px] text-teal-400 font-mono bg-teal-900/40 px-2 py-0.5 rounded border border-teal-500/20 uppercase tracking-widest"><span class="text-teal-500/70">Dosis:</span> {{ $item->dosage }}</span>
                                                            <span class="text-[10px] text-slate-400 font-mono bg-slate-900 px-2 py-0.5 rounded border border-slate-700/50 uppercase tracking-widest"><span class="text-slate-500">Dauer:</span> {{ $item->duration_days ?? 'Dauerhaft' }} {{ $item->duration_days ? 'Tage' : '' }}</span>
                                                        </div>
                                                        @if($item->notes)
                                                            <p class="text-[11px] text-slate-400 italic mt-2 bg-slate-900/80 border-l-2 border-teal-500/30 px-3 py-2 rounded leading-relaxed">{{ $item->notes }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-slate-500 bg-slate-800/30 rounded-xl border border-dashed border-slate-700">
                                <x-heroicon-o-clipboard-document-check class="w-12 h-12 mx-auto mb-3 opacity-30" />
                                <p>Dr. Funki hat noch keine Behandlungspläne ausgestellt.<br>Schreibe ihm im Chat deine Symptome, damit er einen Plan erstellt.</p>
                            </div>
                        @endforelse
                    </div>
                    <!-- Pagination -->
                    <div class="mt-6">
                        {{ $plans->links() }}
                    </div>
                </div>
            @endif

            @if($activeTab === 'protocols')
                <!-- Protokolle List Area -->
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 custom-scrollbar">
                    <h2 class="text-lg sm:text-xl font-bold mb-4 text-slate-100 flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-500" />
                        Patientenakte / Med. Protokolle
                    </h2>

                    <div class="space-y-4 sm:space-y-6">
                        @forelse($protocols as $protocol)
                            <div x-data="{ expanded: false }" class="bg-indigo-950/20 border border-indigo-900/30 rounded-xl p-4 sm:p-5 relative overflow-hidden transition-all hover:bg-indigo-950/30">
                                <div class="flex items-center justify-between mb-4 border-b border-indigo-500/20 pb-3 cursor-pointer" @click="expanded = !expanded">
                                    <div class="flex items-center gap-3">
                                        <x-heroicon-o-chevron-down class="w-5 h-5 text-indigo-400 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                                        <div class="text-xs text-indigo-400 font-mono tracking-widest uppercase flex items-center gap-2">
                                            <x-heroicon-o-clock class="w-4 h-4" />
                                            {{ $protocol->created_at->format('d.m.Y H:i') }} Uhr
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($protocol->treatmentPlan)
                                            <div class="text-[10px] bg-teal-500/10 text-teal-400 px-2 py-0.5 rounded-full border border-teal-500/20 flex items-center gap-1 hidden sm:flex">
                                                <x-heroicon-o-clipboard-document-check class="w-3 h-3" />
                                                {{ $protocol->treatmentPlan->title }}
                                            </div>
                                        @endif
                                        <div class="text-[10px] bg-indigo-500/10 text-indigo-500 px-2 py-0.5 rounded-full border border-indigo-500/20">Protokoll</div>
                                    </div>
                                </div>
                                <div x-show="expanded" x-collapse class="prose prose-invert prose-indigo prose-sm max-w-none text-slate-300">
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
                    <!-- Pagination -->
                    <div class="mt-6">
                        {{ $protocols->links() }}
                    </div>
                </div>
            @endif
        </div>
        @endif

            @if($activeTab === 'files')
                <!-- Dateimanagement Area -->
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 custom-scrollbar flex flex-col bg-black/40 backdrop-blur-md border border-slate-700/60 rounded-xl shadow-[0_0_30px_rgba(0,0,0,0.5)]">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-0 mb-6">
                        <h2 class="text-lg sm:text-xl font-bold text-slate-100 flex items-center gap-2">
                            <x-heroicon-o-folder-open class="w-5 h-5 sm:w-6 sm:h-6 text-teal-500" />
                            Secure File Management
                        </h2>
                        <div class="text-xs text-slate-400 font-mono bg-slate-800/50 px-3 py-1.5 rounded border border-slate-700 flex items-center gap-2">
                            <x-heroicon-o-server class="w-4 h-4 text-slate-500" />
                            Storage Pfad: <span class="text-teal-400 font-bold">{{ $currentPath }}</span>
                        </div>
                    </div>

                    <!-- Actions & Upload -->
                    <div class="flex flex-wrap gap-3 sm:gap-4 mb-6" x-data="{ folderName: '' }">
                        @if($currentPath !== 'wiki/health')
                            <button wire:click="goUp" class="btn bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 px-4 py-2 rounded-lg text-sm transition-colors flex items-center">
                                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                                Zurück zur Übersicht
                            </button>
                        @endif
                        <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                            <input type="text" x-model="folderName" placeholder="Neuer Ordnername..." class="bg-slate-900 border border-slate-700 text-slate-200 text-sm rounded-lg px-4 py-2 focus:ring-teal-500 focus:border-teal-500 flex-1 sm:w-48 text-base sm:text-sm">
                            <button @click="$wire.createFolder(folderName); folderName=''" type="button" class="btn bg-teal-600 hover:bg-teal-500 text-white px-4 py-2 rounded-lg transition-colors shadow-lg shadow-teal-500/20 flex items-center shrink-0">
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

            @if($activeTab === 'medications')
                <!-- Aktive Medikamente Area -->
                <div class="p-4 sm:p-6 overflow-y-auto flex-1 custom-scrollbar bg-black/40 backdrop-blur-md border border-slate-700/60 rounded-xl shadow-[0_0_30px_rgba(0,0,0,0.5)]">
                    @if($viewingMedicationId && $viewingMedication)
                        <!-- Inline Detail View -->
                        <div class="bg-slate-800/80 border border-teal-500/30 rounded-xl max-w-4xl mx-auto shadow-2xl p-5 sm:p-8 relative overflow-hidden">
                            <button wire:click="closeMedicationView" class="absolute top-4 sm:top-6 right-4 sm:right-6 text-slate-400 hover:text-white transition-colors focus:outline-none">
                                <x-heroicon-o-x-mark class="w-6 h-6" />
                            </button>

                            <button wire:click="closeMedicationView" class="mb-6 flex items-center text-teal-400 hover:text-teal-300 transition-colors text-sm font-semibold">
                                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                                Zurück zur Übersicht
                            </button>

                            <div class="flex flex-col sm:flex-row items-center sm:items-start text-center sm:text-left gap-4 sm:gap-6 mb-6 sm:mb-8 mt-6 sm:mt-0">
                                <div class="w-16 sm:w-20 h-16 sm:h-20 rounded-2xl bg-teal-900/40 border border-teal-500/30 text-teal-400 flex items-center justify-center shrink-0 shadow-lg shadow-teal-500/10">
                                    <x-heroicon-s-beaker class="w-8 sm:w-10 h-8 sm:h-10" />
                                </div>
                                <div class="pt-1">
                                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white mb-3 sm:mb-2">{{ $viewingMedication->name }}</h2>
                                    <span class="text-xs uppercase font-bold tracking-widest px-3 py-1 rounded-full {{ $viewingMedication->is_long_term ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-700 text-slate-300' }}">
                                        {{ $viewingMedication->is_long_term ? 'Dauermedikation' : 'Akut / Bedarf' }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-6">
                                    <div class="bg-slate-900/50 p-5 rounded-xl border border-slate-700/50">
                                        <h4 class="text-xs text-slate-500 uppercase tracking-widest font-bold mb-2">Dosierung</h4>
                                        <p class="text-lg font-mono text-teal-400">{{ $viewingMedication->dosage ?? 'Nicht angegeben' }}</p>
                                    </div>
                                    <div class="bg-slate-900/50 p-5 rounded-xl border border-slate-700/50">
                                        <h4 class="text-xs text-slate-500 uppercase tracking-widest font-bold mb-2">Häufigkeit</h4>
                                        <p class="text-slate-200">{{ $viewingMedication->frequency ?? 'Nicht angegeben' }}</p>
                                    </div>
                                    <div class="bg-slate-900/50 p-5 rounded-xl border border-slate-700/50">
                                        <h4 class="text-xs text-slate-500 uppercase tracking-widest font-bold mb-2">Wirkstoffe</h4>
                                        <p class="text-slate-300 font-mono">{{ $viewingMedication->active_ingredients ?? 'Unbekannt' }}</p>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <div class="bg-slate-900/50 p-5 rounded-xl border border-slate-700/50 h-full">
                                        <h4 class="text-xs text-slate-500 uppercase tracking-widest font-bold mb-3 flex items-center gap-2">
                                            <x-heroicon-o-information-circle class="w-4 h-4" />
                                            Einsatzzweck & Notizen
                                        </h4>
                                        <div class="text-slate-300 text-sm leading-relaxed prose prose-invert prose-sm">
                                            @if($viewingMedication->description)
                                                {!! nl2br(e($viewingMedication->description)) !!}
                                            @else
                                                <em class="text-slate-500">Keine weiteren Details hinterlegt.</em>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-6 border-t border-slate-700/50 flex justify-end gap-3">
                                <button wire:click="editMedication('{{ $viewingMedication->id }}')" class="btn bg-slate-700 hover:bg-slate-600 text-white rounded-lg px-4 py-2 flex items-center gap-2 transition-colors">
                                    <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    Bearbeiten
                                </button>
                            </div>
                        </div>
                    @else
                        <!-- Table View -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-0 mb-6">
                            <h2 class="text-lg sm:text-xl font-bold text-slate-100 flex items-center gap-2">
                                <x-heroicon-o-beaker class="w-5 h-5 sm:w-6 sm:h-6 text-teal-500" />
                                Aktive Medikamente
                            </h2>
                            <button wire:click="editMedication" class="btn btn-sm bg-teal-600 hover:bg-teal-500 text-white rounded-lg shadow-lg flex items-center gap-2 px-3 py-1.5 focus:outline-none">
                                <x-heroicon-o-plus class="w-4 h-4" />
                                Hinzufügen
                            </button>
                        </div>

                        <div class="bg-slate-800/40 border border-slate-700/50 rounded-xl overflow-hidden shadow-lg w-full">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-900/60 border-b border-slate-700/50">
                                            <th class="py-4 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest w-12"></th>
                                            <th class="py-4 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Medikament</th>
                                            <th class="py-4 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Dosierung / Intervall</th>
                                            <th class="py-4 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest">Typ</th>
                                            <th class="py-4 px-5 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-700/30">
                                        @forelse($medications as $med)
                                            <tr class="hover:bg-slate-800/60 transition-colors group">
                                                <td class="py-3 px-5">
                                                    <div class="w-8 h-8 rounded shrink-0 bg-teal-900/40 border border-teal-500/20 text-teal-400 flex items-center justify-center">
                                                        <x-heroicon-s-beaker class="w-4 h-4" />
                                                    </div>
                                                </td>
                                                <td class="py-3 px-5 min-w-[200px]">
                                                    <div class="font-bold text-slate-200 text-sm">{{ $med->name }}</div>
                                                    @if($med->active_ingredients)
                                                        <div class="text-[10px] text-slate-500 font-mono mt-0.5 truncate max-w-[250px]">{{ $med->active_ingredients }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-3 px-5">
                                                    <div class="text-sm text-teal-400 font-mono">{{ $med->dosage ?? '-' }}</div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $med->frequency ?? '-' }}</div>
                                                </td>
                                                <td class="py-3 px-5">
                                                    <span class="text-[10px] uppercase font-bold tracking-widest px-2 py-0.5 rounded-full {{ $med->is_long_term ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-700 text-slate-300 border border-slate-600' }}">
                                                        {{ $med->is_long_term ? 'Dauer' : 'Bedarf' }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-5 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button wire:click="viewMedication('{{ $med->id }}')" class="btn btn-sm bg-slate-700 hover:bg-slate-600 text-slate-200 px-3 py-1 rounded-md text-xs font-semibold transition-colors">
                                                            Ansehen
                                                        </button>
                                                        <div class="flex opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                                                            <button wire:click="editMedication('{{ $med->id }}')" class="p-1.5 text-slate-400 hover:text-teal-400 focus:outline-none transition-colors" title="Bearbeiten">
                                                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                            </button>
                                                            <button wire:click="deleteMedication('{{ $med->id }}')" wire:confirm="Dieses Medikament aus der Akte löschen?" class="p-1.5 text-slate-400 hover:text-rose-500 focus:outline-none transition-colors" title="Löschen">
                                                                <x-heroicon-o-trash class="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-12 text-center text-slate-500">
                                                    <x-heroicon-o-beaker class="w-10 h-10 mx-auto mb-3 opacity-30" />
                                                    <div class="font-bold text-slate-300">Keine Medikamente in der Akte</div>
                                                    <div class="text-xs mt-1">Die Liste ist derzeit leer.</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if($showMedicationModal)
            <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-fade-in">
                <div class="bg-slate-900 border border-slate-700 shadow-2xl rounded-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                    <div class="p-4 border-b border-slate-700/60 flex justify-between items-center bg-slate-800/50">
                        <h3 class="font-bold text-slate-100 flex items-center gap-2 text-sm sm:text-base">
                            <x-heroicon-o-beaker class="w-4 h-4 sm:w-5 sm:h-5 text-teal-500" />
                            {{ $medicationForm['id'] ? 'Medikament entwerfen' : 'Neues Medikament injizieren' }}
                        </h3>
                        <button wire:click="$set('showMedicationModal', false)" class="text-slate-400 hover:text-white transition-colors focus:outline-none">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar flex-1 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Produktname</label>
                            <input type="text" wire:model.defer="medicationForm.name" class="w-full bg-slate-800 border {{ $errors->has('medicationForm.name') ? 'border-rose-500' : 'border-slate-700' }} focus:border-teal-500 focus:ring-1 focus:ring-teal-500 text-slate-200 rounded-lg px-3 py-2 text-base sm:text-sm" placeholder="z.B. Ibuprofen 400">
                            @error('medicationForm.name') <span class="text-[10px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Wirkstoffe (Optional)</label>
                            <input type="text" wire:model.defer="medicationForm.active_ingredients" class="w-full bg-slate-800 border border-slate-700 focus:border-teal-500 text-slate-200 rounded-lg px-3 py-2 text-base sm:text-sm font-mono" placeholder="z.B. Ibuprofen">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Dosierung</label>
                                <input type="text" wire:model.defer="medicationForm.dosage" class="w-full bg-slate-800 border border-slate-700 focus:border-teal-500 text-slate-200 rounded-lg px-3 py-2 text-base sm:text-sm font-mono" placeholder="z.B. 400mg">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Frequenz</label>
                                <input type="text" wire:model.defer="medicationForm.frequency" class="w-full bg-slate-800 border border-slate-700 focus:border-teal-500 text-slate-200 rounded-lg px-3 py-2 text-base sm:text-sm font-mono" placeholder="z.B. 1x morgens">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1.5">Beschreibung / Hinweise</label>
                            <textarea wire:model.defer="medicationForm.description" rows="3" class="w-full bg-slate-800 border border-slate-700 focus:border-teal-500 text-slate-200 rounded-lg px-3 py-2 text-base sm:text-sm resize-none custom-scrollbar" placeholder="Zusätzliche Infos, Nebenwirkungen, Notizen..."></textarea>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <div class="relative">
                                    <input type="checkbox" wire:model.defer="medicationForm.is_long_term" class="sr-only">
                                    <div class="block bg-slate-700 w-10 h-6 rounded-full transition-colors {{ $medicationForm['is_long_term'] ? 'bg-teal-500' : '' }}"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform {{ $medicationForm['is_long_term'] ? 'transform translate-x-4' : '' }}"></div>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-slate-200 group-hover:text-teal-400 transition-colors">Als Dauermedikation markieren</span>
                                    <span class="text-[10px] text-slate-500 uppercase tracking-widest">Aktiv bei ständiger Einnahme</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="p-4 border-t border-slate-700/60 bg-slate-800/80 flex justify-end gap-3">
                        <button wire:click="$set('showMedicationModal', false)" class="px-4 py-2 text-sm font-bold text-slate-400 hover:text-white transition-colors">Abbrechen</button>
                        <button wire:click="saveMedication" class="px-5 py-2 text-sm font-bold bg-teal-600 hover:bg-teal-500 text-white rounded-lg shadow-lg shadow-teal-500/20 transition-colors flex items-center gap-2">
                            <x-heroicon-o-check class="w-4 h-4" />
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        @endif
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

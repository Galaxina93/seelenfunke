<div class="bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] p-5 md:p-8 shadow-2xl border border-gray-800 relative overflow-hidden group w-full flex flex-col h-full">
    <div class="hidden sm:block absolute top-6 right-6 text-gray-600 hover:text-primary transition-colors cursor-help" title="Kritische Systemzustände.">
        <i class="solar-info-circle-bold-duotone text-2xl"></i>
    </div>

    <div class="flex justify-between items-center mb-6 md:mb-8 shrink-0">
        <h3 class="text-xs md:text-sm font-black text-gray-500 uppercase tracking-[0.2em]">Operativer Status</h3>
        <span class="text-[9px] md:text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-800 px-3 py-1 md:px-4 md:py-1.5 rounded-full border border-gray-700">Live Action</span>
    </div>

    <div class="space-y-4 md:space-y-6 flex-1">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">
            @foreach($healthChecks as $key => $check)
                <div wire:key="health-card-{{ $key }}" class="bg-gray-950 border border-gray-800 rounded-2xl md:rounded-3xl overflow-hidden shadow-inner transition-all {{ $expandedHealthKey === $key ? 'ring-2 ring-primary/50 ring-offset-0' : 'hover:border-primary/30' }}">
                    <div wire:click="toggleHealthCard('{{ $key }}')" class="p-4 md:p-5 cursor-pointer flex justify-between items-center transition-colors">
                        <div class="flex gap-3 md:gap-4 items-center min-w-0">
                            <div class="p-2.5 md:p-3.5 rounded-xl md:rounded-2xl shrink-0 flex items-center justify-center {{ $check['status'] === 'success' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20 shadow-[0_0_15px_rgba(239,68,68,0.3)] animate-pulse' }}">
                                <i class="bi {{ $check['icon'] }} text-lg md:text-xl"></i>
                            </div>
                            <div class="text-left min-w-0 pr-2">
                                <h4 class="text-[10px] md:text-xs font-black text-white uppercase tracking-tighter truncate">{{ $check['title'] }}</h4>
                                <p class="text-[9px] md:text-[10px] text-gray-400 font-medium leading-tight mt-0.5 md:mt-1 truncate">{{ $check['message'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 md:gap-3 shrink-0">
                            @if($check['count'] > 0)
                                <span class="text-[9px] md:text-[10px] font-black px-2 py-0.5 md:px-2.5 md:py-1 rounded-lg bg-primary text-gray-900 shadow-glow">{{ $check['count'] }}</span>
                            @endif
                            <i class="bi bi-chevron-{{ $expandedHealthKey === $key ? 'up' : 'down' }} text-gray-500 text-sm md:text-base transition-transform duration-300"></i>
                        </div>
                    </div>

                    @if($expandedHealthKey === $key)
                        <div class="border-t border-gray-800 bg-gray-900/50 p-4 md:p-5 animate-in slide-in-from-top-2 duration-200">
                            @if(count($check['data']) > 0)
                                <div class="space-y-3 max-h-[280px] overflow-y-auto custom-scrollbar pr-2">

                                    @if($key === 'inventory')
                                        @foreach($check['data'] as $prod)
                                            <div wire:key="inv-{{ $prod['id'] }}" class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-950 p-3.5 rounded-2xl border border-gray-800 shadow-inner gap-3">
                                                <span class="text-[10px] font-bold text-gray-300 truncate sm:mr-2">{{ $prod['name'] ?? '' }}</span>
                                                <div class="flex items-center gap-3 shrink-0">
                                                    <input type="number" wire:model="stockUpdate.{{ $prod['id'] }}" placeholder="{{ $prod['quantity'] ?? '' }}" class="w-16 h-8 text-[10px] font-black rounded-lg border-gray-700 bg-gray-900 text-white text-center focus:ring-primary focus:border-primary">
                                                    <button type="button" wire:click="updateStock('{{ $prod['id'] }}')" class="bg-primary text-gray-900 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest hover:scale-105 transition-transform shadow-glow">Fix</button>
                                                </div>
                                            </div>
                                        @endforeach

                                    @elseif($key === 'special_issues')
                                        @foreach($check['data'] as $issue)
                                            <div wire:key="spec-{{ $issue['id'] }}" class="bg-gray-950 p-4 rounded-2xl border border-gray-800 shadow-inner">
                                                <div class="flex justify-between items-start mb-3 gap-2">
                                                    <span class="text-[10px] font-bold text-white truncate">{{ $issue['title'] ?? '' }}</span>
                                                    <span class="text-[10px] text-red-400 font-black px-2 py-0.5 bg-red-500/10 rounded-md border border-red-500/20 shrink-0">{{ number_format($issue['amount'] ?? 0, 2, ',', '.') }} €</span>
                                                </div>
                                                <div class="flex flex-col gap-3">
                                                    <input type="file" wire:model="uploadFile" id="upload-special-{{ $issue['id'] }}" class="text-[9px] w-full text-gray-400 file:bg-gray-800 file:text-white file:border file:border-gray-700 file:rounded-lg file:px-3 file:py-1.5 file:mr-2 file:hover:bg-gray-700 file:transition-colors file:cursor-pointer">
                                                    @error('uploadFile') <span class="text-[9px] text-red-400 font-bold">{{ $message }}</span> @enderror
                                                    <div wire:loading wire:target="uploadFile" class="text-[9px] text-primary font-bold animate-pulse">Datei wird vorbereitet...</div>
                                                    <button type="button" wire:click="uploadSpecialReceipt('{{ $issue['id'] }}')" wire:loading.attr="disabled" class="w-full bg-primary text-gray-900 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-dark transition-colors shadow-glow disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="uploadSpecialReceipt('{{ $issue['id'] }}')">Beleg hinterlegen</span>
                                                        <span wire:loading wire:target="uploadSpecialReceipt('{{ $issue['id'] }}')">Speichert...</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach

                                    @elseif($key === 'contracts')
                                        @foreach($check['data'] as $item)
                                            <div wire:key="con-{{ $item['id'] }}" class="bg-gray-950 p-4 rounded-2xl border border-gray-800 shadow-inner">
                                                <div class="flex justify-between items-start mb-3 gap-2 text-[10px]">
                                                    <span class="font-bold text-white truncate">{{ $item['name'] ?? '' }}</span>
                                                    <span class="text-gray-500 italic bg-gray-900 px-2 py-0.5 rounded-md border border-gray-800 shrink-0">{{ $item['group']['name'] ?? '' }}</span>
                                                </div>
                                                <div class="flex flex-col gap-3">
                                                    <input type="file" wire:model="uploadFile" id="upload-contract-{{ $item['id'] }}" class="text-[9px] w-full text-gray-400 file:bg-gray-800 file:text-white file:border file:border-gray-700 file:rounded-lg file:px-3 file:py-1.5 file:mr-2 file:hover:bg-gray-700 file:transition-colors file:cursor-pointer">
                                                    @error('uploadFile') <span class="text-[9px] text-red-400 font-bold">{{ $message }}</span> @enderror
                                                    <div wire:loading wire:target="uploadFile" class="text-[9px] text-primary font-bold animate-pulse">Datei wird vorbereitet...</div>
                                                    <button type="button" wire:click="uploadContract('{{ $item['id'] }}')" wire:loading.attr="disabled" class="w-full bg-primary text-gray-900 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-dark transition-colors shadow-glow disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="uploadContract('{{ $item['id'] }}')">Vertrag hochladen</span>
                                                        <span wire:loading wire:target="uploadContract('{{ $item['id'] }}')">Speichert...</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach

                                    @elseif($key === 'open_tickets')
                                        @foreach($check['data'] as $ticket)
                                            <div wire:key="ticket-{{ $ticket['id'] }}" class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-950 p-3.5 rounded-2xl border border-gray-800 shadow-inner gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <span class="text-[10px] font-bold text-gray-300 truncate block">#{{ $ticket['ticket_number'] }} - {{ $ticket['subject'] }}</span>
                                                    <span class="text-[9px] text-gray-500">{{ $ticket['customer_name'] }}</span>
                                                </div>
                                                <div class="flex items-center shrink-0">
                                                    <a href="/admin/funki-tickets?ticket={{ $ticket['id'] }}" class="bg-primary text-gray-900 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest hover:scale-105 transition-transform shadow-glow">Öffnen</a>
                                                </div>
                                            </div>
                                        @endforeach

                                    @elseif($key === 'product_reviews')
                                        @foreach($check['data'] as $review)
                                            <div wire:key="review-{{ $review['id'] }}" class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-950 p-3.5 rounded-2xl border border-gray-800 shadow-inner gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <span class="text-[10px] font-bold text-gray-300 truncate block">{{ $review['product_name'] }}</span>
                                                    <div class="flex items-center gap-1 mt-0.5 text-yellow-500 text-[9px]">
                                                        @for($i=0; $i < $review['rating']; $i++) ★ @endfor
                                                    </div>
                                                </div>
                                                <div class="flex items-center shrink-0">
                                                    <button type="button" wire:click="approveReview('{{ $review['id'] }}')" class="bg-emerald-500 text-gray-900 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest hover:scale-105 transition-transform shadow-glow mr-2">Freigeben</button>
                                                    <button type="button" wire:click="rejectReview('{{ $review['id'] }}')" class="bg-gray-800 text-gray-400 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-colors">Löschen</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif

                                </div>
                            @else
                                <div class="p-4 text-center text-gray-500 text-[10px] font-black uppercase tracking-widest bg-gray-950 rounded-2xl border border-gray-800 shadow-inner">
                                    Alles erledigt! Keine offenen Punkte.
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- SYSTEM & INFRASTRUKTUR DASHBOARD --}}
    <div class="mt-6 border-t border-gray-800 pt-6 shrink-0" wire:init="checkSystemHealth" wire:poll.120s="checkSystemHealth">

        <div class="flex items-center justify-between mb-6 ml-1">
            <h4 class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">System & Infrastruktur</h4>
            <div class="flex items-center gap-2 text-[9px] font-bold text-gray-400 bg-gray-950 px-3 py-1.5 rounded-full border border-gray-800 shadow-inner">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-50"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                </span>
                Live Monitoring
            </div>
        </div>

        @php
            $services = [
                'database' => ['label' => 'Datenbank', 'host' => config('database.connections.mysql.host', '127.0.0.1'), 'port' => config('database.connections.mysql.port', '3306'), 'desc' => 'Speichert alle Produkte, Benutzer und Bestellungen.'],
                'storage' => ['label' => 'Server Speicher', 'host' => 'Lokal (SSD)', 'port' => 'N/A', 'desc' => 'Überwacht den verfügbaren Speicherplatz auf dem Server.'],
                'stripe' => ['label' => 'Stripe API', 'host' => 'api.stripe.com', 'port' => '443', 'desc' => 'Schnittstelle zu unserem Zahlungsdienstleister.'],
                'smtp' => ['label' => 'Mail Server', 'host' => config('mail.mailers.smtp.host', 'lokal'), 'port' => config('mail.mailers.smtp.port', '2525'), 'desc' => 'Postausgangsserver für alle E-Mails.'],
                'redis' => ['label' => 'Cache / Redis', 'host' => config('database.redis.default.host', '127.0.0.1'), 'port' => config('database.redis.default.port', '6379'), 'desc' => 'Schneller In-Memory-Speicher für Sessions.'],
                'queue' => ['label' => 'Queue Worker', 'host' => 'Hintergrund', 'port' => 'N/A', 'desc' => 'Verarbeitet Aufgaben (Mails, PDFs) im Hintergrund.'],
                'scheduler' => ['label' => 'Task Scheduler', 'host' => 'Cronjob', 'port' => 'CLI', 'desc' => 'Führt zeitgesteuerte Hintergrundaufgaben aus (z.B. Bereinigungen, Erinnerungen).'],
                'backup' => ['label' => 'System Backup', 'host' => 'Storage', 'port' => 'N/A', 'desc' => 'Prüft, ob in den letzten 48 Stunden eine Sicherung der Datenbank erstellt wurde.'],
            ];

            // Hier definieren wir unsere schicken Gruppierungen!
            $systemGroups = [
                'Kernsysteme' => [
                    'color' => 'bg-primary shadow-[0_0_8px_rgba(197,160,89,0.8)]',
                    'items' => ['database', 'storage', 'redis']
                ],
                'Schnittstellen & API' => [
                    'color' => 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)]',
                    'items' => ['ws', 'stripe', 'smtp']
                ],
                'Background & Security' => [
                    'color' => 'bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.8)]',
                    'items' => ['queue', 'scheduler', 'backup']
                ]
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 lg:gap-8">
            @foreach($systemGroups as $groupName => $groupInfo)
                <div class="bg-gray-950/40 rounded-[1.5rem] border border-gray-800/60 p-5 shadow-inner">

                    {{-- Gruppen Header --}}
                    <div class="flex items-center gap-2.5 mb-5 pb-3 border-b border-gray-800/60">
                        <div class="w-1.5 h-3.5 rounded-full {{ $groupInfo['color'] }}"></div>
                        <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.15em]">{{ $groupName }}</h5>
                    </div>

                    {{-- Items der Gruppe --}}
                    <div class="flex flex-col gap-4">
                        @foreach($groupInfo['items'] as $sKey)

                            @if($sKey === 'ws')
                                {{-- WEBSOCKET SONDERFALL (Alpine.js Logik) --}}
                                <div x-data="{
                                    wsStatus: 'checking',
                                    wsHost: '{{ env('VITE_REVERB_HOST', env('MIX_PUSHER_HOST', '127.0.0.1')) }}',
                                    wsPort: '{{ env('VITE_REVERB_PORT', env('MIX_PUSHER_PORT', 6001)) }}',
                                    checkConnection() {
                                        if(typeof window.Echo !== 'undefined' && window.Echo.connector && window.Echo.connector.pusher) {
                                            let state = window.Echo.connector.pusher.connection.state;
                                            if(state === 'connected') this.wsStatus = 'connected';
                                            else if(state === 'connecting') this.wsStatus = 'connecting';
                                            else this.wsStatus = 'disconnected';

                                            window.Echo.connector.pusher.connection.bind('state_change', (states) => {
                                                if(states.current === 'connected') this.wsStatus = 'connected';
                                                else if(states.current === 'connecting') this.wsStatus = 'connecting';
                                                else this.wsStatus = 'disconnected';
                                            });
                                        } else {
                                            this.wsStatus = 'unavailable';
                                        }
                                    }
                                }" x-init="setTimeout(() => checkConnection(), 1500)">
                                    <div class="flex items-center gap-2.5 relative">
                                        <div class="relative flex h-2 w-2 shrink-0">
                                            <span x-show="wsStatus === 'connected' || wsStatus === 'checking'" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 transition-colors duration-300" :class="{'bg-emerald-400': wsStatus === 'connected', 'bg-gray-400': wsStatus === 'checking'}"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 transition-colors duration-300" :class="{'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]': wsStatus === 'connected', 'bg-amber-500': wsStatus === 'connecting', 'bg-red-500': wsStatus === 'disconnected' || wsStatus === 'unavailable', 'bg-gray-500': wsStatus === 'checking'}"></span>
                                        </div>

                                        <div class="relative group cursor-help flex items-center justify-between w-full"
                                             x-data="{ showWsInfo: false, alignRight: false }"
                                             @mouseenter="showWsInfo = true; alignRight = ($el.getBoundingClientRect().left + 300 > window.innerWidth)"
                                             @mouseleave="showWsInfo = false">

                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 transition-colors" :class="showWsInfo ? 'text-white' : ''">WebSocket</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 text-gray-600 transition-colors" :class="showWsInfo ? 'text-primary' : ''"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                                            </div>

                                            <span class="text-[9px] font-bold uppercase tracking-widest text-right truncate"
                                                  :class="{'text-emerald-400': wsStatus === 'connected', 'text-amber-400': wsStatus === 'connecting', 'text-red-400': wsStatus === 'disconnected' || wsStatus === 'unavailable', 'text-gray-500': wsStatus === 'checking'}"
                                                  x-text="wsStatus === 'connected' ? 'Online' : (wsStatus === 'checking' ? 'Prüfe...' : 'Offline')">
                                            </span>

                                            <div x-show="showWsInfo" x-cloak x-transition.opacity.duration.200ms
                                                 class="absolute bottom-[calc(100%+12px)] w-[280px] sm:w-[320px] p-4 bg-gray-900 border border-gray-700 rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.8)] z-[100] pointer-events-none"
                                                 :class="alignRight ? 'right-0' : 'left-0'">
                                                <div class="absolute -bottom-1.5 w-3 h-3 bg-gray-900 border-b border-r border-gray-700 transform rotate-45" :class="alignRight ? 'right-6' : 'left-6'"></div>
                                                <div class="relative z-10 flex flex-col gap-2 text-[9px] font-mono text-gray-400">
                                                    <div class="flex justify-between gap-4"><span class="font-bold text-gray-500">HOST:</span><span class="text-primary truncate" x-text="wsHost"></span></div>
                                                    <div class="flex justify-between gap-4"><span class="font-bold text-gray-500">PORT:</span><span class="text-primary" x-text="wsPort"></span></div>
                                                    <div class="border-t border-gray-800 my-1"></div>
                                                    <div x-show="wsStatus === 'disconnected'" class="text-red-400 font-sans font-bold leading-relaxed">Fehler: Der WebSocket-Server antwortet nicht.</div>
                                                    <div x-show="wsStatus === 'unavailable'" class="text-red-400 font-sans font-bold leading-relaxed">Fehler: Laravel Echo konnte nicht initialisiert werden.</div>
                                                    <div x-show="wsStatus === 'connected'" class="text-emerald-400 font-sans font-bold leading-relaxed">System läuft stabil. Echtzeit-Events empfangen.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else

                                {{-- STANDARD SERVICE (aus Array) --}}
                                @php
                                    $sInfo = $services[$sKey];
                                    $health = $systemHealth[$sKey] ?? null;
                                    $status = $health ? $health['status'] : 'checking';
                                    $msg = $health ? $health['value'] : 'Prüfe...';
                                    $errorMsg = $health ? $health['error'] : null;

                                    $dotColor = match($status) {
                                        'connected' => 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]',
                                        'warning' => 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]',
                                        'error', 'unavailable' => 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]',
                                        default => 'bg-gray-500'
                                    };
                                    $textColor = match($status) {
                                        'connected' => 'text-emerald-400',
                                        'warning' => 'text-amber-400',
                                        'error', 'unavailable' => 'text-red-400',
                                        default => 'text-gray-500'
                                    };
                                @endphp

                                <div class="flex items-center gap-2.5 relative">
                                    <div class="relative flex h-2 w-2 shrink-0">
                                        @if($status === 'connected' || $status === 'checking' || $status === 'warning')
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 {{ $status === 'checking' ? 'bg-gray-400' : ($status === 'warning' ? 'bg-amber-400' : 'bg-emerald-400') }}"></span>
                                        @endif
                                        <span class="relative inline-flex rounded-full h-2 w-2 transition-colors duration-300 {{ $dotColor }}"></span>
                                    </div>

                                    <div class="relative group cursor-help flex items-center justify-between w-full"
                                         x-data="{ showInfo: false, alignRight: false }"
                                         @mouseenter="showInfo = true; alignRight = ($el.getBoundingClientRect().left + 300 > window.innerWidth)"
                                         @mouseleave="showInfo = false">

                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 transition-colors" :class="showInfo ? 'text-white' : ''">
                                                {{ $sInfo['label'] }}
                                            </span>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 text-gray-600 transition-colors" :class="showInfo ? 'text-primary' : ''">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                            </svg>
                                        </div>

                                        <span class="text-[9px] font-bold uppercase tracking-widest text-right truncate {{ $textColor }}">
                                            {{ $msg }}
                                        </span>

                                        <div x-show="showInfo" x-cloak x-transition.opacity.duration.200ms
                                             class="absolute bottom-[calc(100%+12px)] w-[280px] sm:w-[320px] p-4 bg-gray-900 border border-gray-700 rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.8)] z-[100] pointer-events-none"
                                             :class="alignRight ? 'right-0' : 'left-0'">
                                            <div class="absolute -bottom-1.5 w-3 h-3 bg-gray-900 border-b border-r border-gray-700 transform rotate-45" :class="alignRight ? 'right-6' : 'left-6'"></div>

                                            <div class="relative z-10 flex flex-col gap-2 text-[9px] font-mono text-gray-400">
                                                <div class="flex justify-between gap-4"><span class="font-bold text-gray-500">HOST:</span><span class="text-primary truncate">{{ $sInfo['host'] }}</span></div>
                                                <div class="flex justify-between gap-4"><span class="font-bold text-gray-500">PORT:</span><span class="text-primary">{{ $sInfo['port'] }}</span></div>
                                                <div class="border-t border-gray-800 my-1"></div>

                                                <p class="text-xs font-sans text-gray-300 leading-relaxed">{{ $sInfo['desc'] }}</p>

                                                @if($sKey === 'queue' && $health)
                                                    <div class="flex justify-between gap-4 mt-1 bg-gray-950 p-2 rounded-lg border border-gray-800">
                                                        <span class="font-bold text-gray-500">WARTEND: <span class="text-white">{{ $health['pending'] ?? 0 }} Jobs</span></span>
                                                        <span class="font-bold text-gray-500">FEHLER: <span class="{{ ($health['failed'] ?? 0) > 0 ? 'text-red-400' : 'text-emerald-400' }}">{{ $health['failed'] ?? 0 }} Jobs</span></span>
                                                    </div>
                                                @endif

                                                @if($sKey === 'backup' && isset($health['path']))
                                                    <div class="mt-1 bg-gray-950 p-2 rounded-lg border border-gray-800">
                                                        <span class="font-bold text-gray-500 block mb-0.5 text-[9px]">SPEICHERORT:</span>
                                                        <span class="text-white font-mono text-[10px] break-all">{{ $health['path'] }}</span>
                                                    </div>
                                                @endif

                                                @if($errorMsg)
                                                    <div class="text-red-400 font-sans font-bold leading-relaxed bg-red-500/10 p-2 rounded-xl border border-red-500/20 mt-1">
                                                        {{ $errorMsg }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

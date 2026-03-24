<div x-data="{ activeTab: null }" class="space-y-6">

    @php
        // --- 1. SHOP HEALTH ---
        $shopScore = $stats['health_score'] ?? 0;
        $shopColorClass = $shopScore >= 80 ? 'text-emerald-400' : ($shopScore >= 50 ? 'text-amber-400' : 'text-red-400');
        $shopStrokeColor = $shopScore >= 80 ? '#34d399' : ($shopScore >= 50 ? '#fbbf24' : '#f87171');
        $circumference = 2 * pi() * 40;
        $shopOffset = $circumference - ($shopScore / 100) * $circumference;

        // --- 2. OPERATIVER SCORE ---
        $totalTodos = collect($healthChecks)->sum('count');
        $opHasErrors = collect($healthChecks)->contains('status', 'error');
        $opHasWarnings = collect($healthChecks)->contains('status', 'warning');
        $opErrorCount = collect($healthChecks)->where('status', 'error')->count();
        $opWarningCount = collect($healthChecks)->where('status', 'warning')->count();
        
        $operativeScore = 100 - ($opErrorCount * 25) - ($opWarningCount * 10) - ($totalTodos * 2);
        $operativeScore = max(0, min(100, $operativeScore)); 
        
        $opColorClass = $operativeScore >= 80 ? 'text-emerald-400' : ($operativeScore >= 50 ? 'text-amber-400' : 'text-red-400');
        $opStrokeColor = $operativeScore >= 80 ? '#34d399' : ($operativeScore >= 50 ? '#fbbf24' : '#f87171');
        $opOffset = $circumference - ($operativeScore / 100) * $circumference;

        $opScoreText = 'Alles im grünen Bereich';
        if ($opHasErrors || $operativeScore < 50) {
            $opScoreText = 'Kritische Todos offen';
        } elseif ($opHasWarnings || $operativeScore < 80) {
            $opScoreText = 'Aufgaben warten';
        }

        // --- 3. SYSTEM SCORE ---
        $services = [
            'database' => ['label' => 'Datenbank', 'host' => config('database.connections.mysql.host', '127.0.0.1'), 'port' => config('database.connections.mysql.port', '3306'), 'desc' => 'Speichert alle Produkte, Benutzer und Bestellungen.'],
            'storage' => ['label' => 'Server Speicher', 'host' => 'Lokal (SSD)', 'port' => 'N/A', 'desc' => 'Überwacht den verfügbaren Speicherplatz auf dem Server.'],
            'stripe' => ['label' => 'Stripe API', 'host' => 'api.stripe.com', 'port' => '443', 'desc' => 'Schnittstelle zu unserem Zahlungsdienstleister.'],
            'smtp' => ['label' => 'Mail Server', 'host' => config('mail.mailers.smtp.host', 'lokal'), 'port' => config('mail.mailers.smtp.port', '2525'), 'desc' => 'Postausgangsserver für alle E-Mails.'],
            'redis' => ['label' => 'Cache / Redis', 'host' => config('database.redis.default.host', '127.0.0.1'), 'port' => config('database.redis.default.port', '6379'), 'desc' => 'Speichert Sessions & Cache. Nutzt lokal den "file"-Cache und live den schnellen "redis"-Server.'],
            'queue' => ['label' => 'Queue Worker', 'host' => 'Hintergrund', 'port' => 'N/A', 'desc' => 'Verarbeitet Aufgaben (Mails, PDFs) im Hintergrund.'],
            'scheduler' => ['label' => 'Task Scheduler', 'host' => 'Cronjob', 'port' => 'CLI', 'desc' => 'Führt zeitgesteuerte Hintergrundaufgaben aus (z.B. Bereinigungen, Erinnerungen).'],
            'backup' => ['label' => 'System Backup', 'host' => 'Storage', 'port' => 'N/A', 'desc' => 'Prüft, ob in den letzten 48 Stunden eine Sicherung der Datenbank erstellt wurde.'],
        ];

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
        
        $sysErrors = 0;
        $sysWarnings = 0;
        foreach($services as $sKey => $sInfo) {
            if (isset($systemHealth[$sKey])) {
                if ($systemHealth[$sKey]['status'] === 'error' || $systemHealth[$sKey]['status'] === 'unavailable') {
                    $sysErrors++;
                } elseif ($systemHealth[$sKey]['status'] === 'warning') {
                    $sysWarnings++;
                }
            }
        }
        $storageData = $systemHealth['storage'] ?? null;
        if ($storageData && isset($storageData['percent_free'])) {
            if ($storageData['percent_free'] < 10) {
                $sysErrors++;
            } elseif ($storageData['percent_free'] < 20) {
                $sysWarnings++;
            }
        }
        $systemScore = 100 - ($sysErrors * 25) - ($sysWarnings * 10);
        $systemScore = max(0, min(100, $systemScore));

        $sysColorClass = $systemScore >= 80 ? 'text-emerald-400' : ($systemScore >= 50 ? 'text-amber-400' : 'text-red-400');
        $sysStrokeColor = $systemScore >= 80 ? '#34d399' : ($systemScore >= 50 ? '#fbbf24' : '#f87171');
        $sysOffset = $circumference - ($systemScore / 100) * $circumference;

        $sysText = 'Alle Systeme online';
        if ($sysErrors > 0 || $systemScore < 50) {
            $sysText = 'Kritische System-Ausfälle';
        } elseif ($sysWarnings > 0 || $systemScore < 80) {
            $sysText = 'System-Warnungen';
        }
    @endphp

    <!-- TOP ROW: THE 3 SCORES -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- 1. SHOP HEALTH SCORE -->
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300"
             :class="activeTab === 'shop' ? 'border-primary/80 ring-1 ring-primary/50' : 'border-gray-800 hover:border-gray-700'">
             
            <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Shop Health</h3>
            
            <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="#1f2937" stroke-width="8"></circle>
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $shopStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $shopOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out drop-shadow-[0_0_8px_currentColor]"></circle>
                </svg>
                <div class="absolute flex flex-col items-center justify-center">
                    <span class="text-3xl font-black {{ $shopColorClass }} drop-shadow-[0_0_10px_currentColor]">{{ $shopScore }}</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Score</span>
                </div>
            </div>
            
            <div class="flex-1 flex flex-col justify-end w-full">
                <button @click="activeTab = activeTab === 'shop' ? null : 'shop'" class="w-full px-5 py-3 bg-gray-950 border border-gray-700 hover:border-primary/50 text-gray-300 hover:text-white rounded-xl text-[11px] font-black uppercase tracking-widest transition-all shadow-inner active:scale-95 flex items-center justify-center gap-2">
                    <span x-text="activeTab === 'shop' ? 'Details ausblenden' : 'Details anzeigen'"></span>
                    <i :class="activeTab === 'shop' ? 'bi-chevron-up' : 'bi-chevron-down'" class="bi transition-transform text-primary"></i>
                </button>
            </div>
        </div>

        <!-- 2. OPERATIVER SCORE -->
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300"
             :class="activeTab === 'operative' ? 'border-primary/80 ring-1 ring-primary/50' : 'border-gray-800 hover:border-gray-700'">
             
            <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Operativer Status</h3>
            
            <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="#1f2937" stroke-width="8"></circle>
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $opStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $opOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out drop-shadow-[0_0_8px_currentColor]"></circle>
                </svg>
                <div class="absolute flex flex-col items-center justify-center">
                    <span class="text-3xl font-black {{ $opColorClass }} drop-shadow-[0_0_10px_currentColor]">{{ $operativeScore }}</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Score</span>
                </div>
            </div>
            
            <div class="mb-4">
                @if($totalTodos > 0)
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-400">
                        <span class="text-white">{{ $totalTodos }}</span> Aufgaben
                    </p>
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 {{ $opColorClass }} animate-pulse">{{ $opScoreText }}</p>
                @else
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-400">Keine Todos</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 text-emerald-500">{{ $opScoreText }}</p>
                @endif
            </div>
            
            <div class="flex-1 flex flex-col justify-end w-full">
                <button @click="activeTab = activeTab === 'operative' ? null : 'operative'" class="w-full px-5 py-3 bg-gray-950 border border-gray-700 hover:border-primary/50 text-gray-300 hover:text-white rounded-xl text-[11px] font-black uppercase tracking-widest transition-all shadow-inner active:scale-95 flex items-center justify-center gap-2">
                    <span x-text="activeTab === 'operative' ? 'Details ausblenden' : 'Details anzeigen'"></span>
                    <i :class="activeTab === 'operative' ? 'bi-chevron-up' : 'bi-chevron-down'" class="bi transition-transform text-primary"></i>
                </button>
            </div>
        </div>

        <!-- 3. SYSTEM SCORE -->
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300"
             :class="activeTab === 'system' ? 'border-primary/80 ring-1 ring-primary/50' : 'border-gray-800 hover:border-gray-700'">
             
            <div class="absolute top-4 right-4 text-[9px] font-bold uppercase tracking-wider text-gray-400 bg-gray-800 px-2.5 py-1 rounded-full border border-gray-700">Live</div>
            
            <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Infrastruktur</h3>
            
            <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="#1f2937" stroke-width="8"></circle>
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $sysStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $sysOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out drop-shadow-[0_0_8px_currentColor]"></circle>
                </svg>
                <div class="absolute flex flex-col items-center justify-center">
                    <span class="text-3xl font-black {{ $sysColorClass }} drop-shadow-[0_0_10px_currentColor]">{{ $systemScore }}</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Score</span>
                </div>
            </div>
            
            <div class="mb-4">
                @if($sysErrors > 0 || $sysWarnings > 0)
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-400">
                        <span class="text-white">{{ $sysErrors + $sysWarnings }}</span> Warnungen
                    </p>
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 {{ $sysColorClass }} animate-pulse">{{ $sysText }}</p>
                @else
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-400">Alle APIs stabil</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 text-emerald-500">{{ $sysText }}</p>
                @endif
            </div>
            
            <div class="flex-1 flex flex-col justify-end w-full">
                <button @click="activeTab = activeTab === 'system' ? null : 'system'" class="w-full px-5 py-3 bg-gray-950 border border-gray-700 hover:border-primary/50 text-gray-300 hover:text-white rounded-xl text-[11px] font-black uppercase tracking-widest transition-all shadow-inner active:scale-95 flex items-center justify-center gap-2">
                    <span x-text="activeTab === 'system' ? 'Details ausblenden' : 'Details anzeigen'"></span>
                    <i :class="activeTab === 'system' ? 'bi-chevron-up' : 'bi-chevron-down'" class="bi transition-transform text-primary"></i>
                </button>
            </div>
        </div>

    </div>

    <!-- MASTER DETAILS VIEW -->
    <div x-show="activeTab !== null" x-collapse x-cloak class="mt-8">
        <div class="bg-gray-900/90 backdrop-blur-xl rounded-[2.5rem] p-6 lg:p-10 shadow-2xl border border-gray-800 relative z-20">
            
            <!-- 1. SHOP DETAILS -->
            <div x-show="activeTab === 'shop'" x-transition.opacity.duration.300ms class="space-y-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-800 pb-6">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">Financial Health Analytics</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Gesundheitswert aus Break-Even, Marge, Trend und Liquidität im aktuellen Zeitfenster.</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right hidden sm:block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 block mb-1">Ø Mtl. Gewinn</span>
                            <span class="text-2xl font-black text-white tracking-tighter">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
                        </div>
                        @include('livewire.global.ai.ask-ai-dropdown', ['actionMethod' => 'startAiRecommendation', 'buttonText' => 'Agent fragen'])
                    </div>
                </div>
                
                @if($aiRecommendation)
                    <div class="bg-gray-950/50 rounded-2xl p-6 shadow-inner border border-purple-500/30 relative overflow-hidden group">
                        <div class="flex items-center gap-3 mb-4 pb-4 border-b border-gray-800">
                            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400 border border-purple-500/20 shadow-[0_0_15px_rgba(168,85,247,0.2)]">
                                <i class="solar-cpu-bold-duotone text-xl"></i>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Virtueller CFO Analyse</h3>
                        </div>
                        <div class="prose prose-sm prose-invert max-w-none text-gray-300">
                            {!! \Illuminate\Support\Str::markdown($aiRecommendation) !!}
                        </div>
                    </div>
                @endif
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Säule 1: WACHSTUM -->
                    <div class="bg-gray-950/40 rounded-[2rem] p-6 border border-gray-800 flex flex-col gap-6 shadow-inner">
                        <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20 shadow-[0_0_15px_rgba(59,130,246,0.2)]">
                                <i class="solar-rocket-bold-duotone text-xl"></i>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Wachstum</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner group hover:border-blue-500/30 transition-colors">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2"><i class="bi bi-exclamation-circle text-gray-500"></i> Shop Umsatz</span>
                                <span class="text-lg font-black text-white">{{ number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2"><i class="bi bi-exclamation-circle text-gray-500"></i> Umsatz-Trend</span>
                                <span class="text-lg font-black {{ ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ ($stats['revenue_growth'] ?? 0) > 0 ? '+' : '' }}{{ $stats['revenue_growth'] ?? 0 }} %</span>
                            </div>
                            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2"><i class="bi bi-exclamation-circle text-gray-500"></i> Prognose (Jahr)</span>
                                <span class="text-lg font-black text-purple-400">{{ number_format($stats['projected_year'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                        </div>
                    </div>

                    <!-- Säule 2: EFFIZIENZ -->
                    <div class="bg-gray-950/40 rounded-[2rem] p-6 border border-gray-800 flex flex-col gap-6 shadow-inner">
                        <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                                <i class="solar-wallet-money-bold-duotone text-xl"></i>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Effizienz</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2"><i class="bi bi-exclamation-circle text-gray-500"></i> Gewinn-Marge</span>
                                <span class="text-lg font-black text-emerald-400">{{ $stats['margin'] ?? 0 }} %</span>
                            </div>
                            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2"><i class="bi bi-exclamation-circle text-gray-500"></i> Ø Mtl. Gewinn</span>
                                <span class="text-lg font-black text-white">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                        </div>
                    </div>

                    <!-- Säule 3: SICHERHEIT -->
                    <div class="bg-gray-950/40 rounded-[2rem] p-6 border border-gray-800 flex flex-col gap-6 shadow-inner" x-data="{ showCosts: false }">
                        <div class="flex items-center gap-3 border-b border-gray-800 pb-4">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 border border-amber-500/20 shadow-[0_0_15px_rgba(245,158,11,0.2)]">
                                <i class="solar-shield-check-bold-duotone text-xl"></i>
                            </div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Sicherheit</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-xl border border-gray-800 shadow-inner">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2"><i class="bi bi-exclamation-circle text-gray-500"></i> Break-Even Point</span>
                                <span class="text-lg font-black text-amber-400">{{ number_format($stats['break_even_monthly'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                            @php
                                $hasPending = ($stats['pending_invoices']['sum'] ?? 0) > 0;
                                $pendingCount = $stats['pending_invoices']['count'] ?? 0;
                            @endphp
                            <div class="flex justify-between items-center bg-gray-900 p-4 rounded-xl border {{ $hasPending ? 'border-red-500/40 bg-red-900/10' : 'border-gray-800 shadow-inner' }}">
                                <span class="text-[10px] font-bold uppercase tracking-widest flex items-center gap-2 {{ $hasPending ? 'text-red-300' : 'text-gray-400' }}">
                                    <i class="bi bi-exclamation-circle text-gray-500"></i> Offene Posten
                                </span>
                                <div class="text-right">
                                    <span class="text-lg font-black {{ $hasPending ? 'text-red-400' : 'text-gray-500' }}">{{ number_format($stats['pending_invoices']['sum'] ?? 0, 0, ',', '.') }} €</span>
                                    @if($pendingCount > 0)
                                        <span class="text-[8px] text-gray-500 font-black tracking-widest block">({{ $pendingCount }} Rechnungen)</span>
                                    @endif
                                </div>
                            </div>

                            <div class="border-t border-gray-800 pt-4 mt-2">
                                <button @click="showCosts = !showCosts" class="w-full flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition-colors bg-gray-900 px-3 py-2 rounded-xl border border-gray-800">
                                    <span>Kosten & Fix-Einnahmen Details</span>
                                    <i :class="showCosts ? 'bi bi-chevron-up' : 'bi-chevron-down'"></i>
                                </button>
                                <div x-show="showCosts" x-cloak x-collapse class="space-y-2 mt-3">
                                    <div class="flex justify-between items-center px-2 py-1"><span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Fix-Einnahmen</span><span class="text-xs font-black text-emerald-400">{{ number_format($stats['fixed_income_total'] ?? 0, 0, ',', '.') }} €</span></div>
                                    <div class="flex justify-between items-center px-2 py-1"><span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Fixkosten (Privat)</span><span class="text-xs font-black text-pink-400">{{ number_format($stats['fixed_expenses_priv'] ?? 0, 0, ',', '.') }} €</span></div>
                                    <div class="flex justify-between items-center px-2 py-1"><span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Fixkosten (Gewerbe)</span><span class="text-xs font-black text-rose-400">{{ number_format($stats['fixed_expenses_gew'] ?? 0, 0, ',', '.') }} €</span></div>
                                    <div class="flex justify-between items-center px-2 py-1"><span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Variabel</span><span class="text-xs font-black text-orange-400">{{ number_format($stats['variable_expenses'] ?? 0, 0, ',', '.') }} €</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. OPERATIVE DETAILS -->
            <div x-show="activeTab === 'operative'" x-transition.opacity.duration.300ms>
                <div class="flex justify-between items-end border-b border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">To-do Liste & Operatives</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Aktuelle Warnungen aus dem Tagesgeschäft, Buchhaltung und Kundenkommunikation.</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
                    @foreach($healthChecks as $key => $check)
                        <div wire:key="master-health-{{ $key }}" class="bg-gray-950/40 border border-gray-800/60 rounded-[1.5rem] overflow-hidden shadow-inner flex flex-col h-full transform transition-all {{ $expandedHealthKey === $key ? 'ring-2 ring-primary/50' : 'hover:-translate-y-1 hover:border-primary/30' }}">
                            
                            <!-- Header der Karte -->
                            <div wire:click="toggleHealthCard('{{ $key }}')" class="p-5 flex justify-between items-center {{ $expandedHealthKey === $key ? 'border-b border-gray-800/40' : '' }} relative cursor-pointer group">
                                @php
                                    $statusClass = match($check['status'] ?? 'error') {
                                        'success' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
                                        'warning' => 'bg-amber-500/10 text-amber-500 border border-amber-500/20 shadow-[0_0_15px_rgba(245,158,11,0.15)]',
                                        default => 'bg-red-500/10 text-red-500 border border-red-500/20 shadow-[0_0_15px_rgba(239,68,68,0.3)] animate-pulse',
                                    };
                                @endphp
                                <div class="flex items-center gap-4 min-w-0">
                                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 {{ $statusClass }}">
                                        <i class="bi {{ $check['icon'] }} text-2xl"></i>
                                    </div>
                                    <div class="min-w-0 pr-2 pb-1">
                                        <h4 class="text-xs font-black text-white uppercase tracking-tighter truncate">{{ $check['title'] }}</h4>
                                        <p class="text-[10px] text-gray-400 font-medium leading-tight mt-1 line-clamp-2 md:truncate">{{ $check['message'] }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 pt-1 flex items-center gap-3">
                                    @if($check['count'] > 0)
                                        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center shrink-0 shadow-[0_0_15px_rgba(197,160,89,0.4)]">
                                            <span class="text-xs font-black text-gray-900">{{ $check['count'] }}</span>
                                        </div>
                                    @else
                                        <div class="w-8 h-8 rounded-full bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 shrink-0">
                                            <i class="solar-check-read-bold-duotone text-emerald-400"></i>
                                        </div>
                                    @endif
                                    <i class="bi {{ $expandedHealthKey === $key ? 'bi-chevron-up' : 'bi-chevron-down' }} text-gray-600 group-hover:text-primary transition-colors text-sm"></i>
                                </div>
                            </div>
                            
                            <!-- Interaktive Listenansicht (versteckt/Scrollbar wenn lang) -->
                            @if($expandedHealthKey === $key)
                            <div class="bg-gray-900/30 p-5 flex-1 relative flex flex-col border-t border-gray-800/40">
                                @if($check['count'] > 0)
                                    <div class="flex-1 flex flex-col items-center justify-center pt-2">
                                        @php
                                            $actionUrl = match($key) {
                                                'inventory' => '/admin/products',
                                                'special_issues' => '/admin/financial-variable-costs',
                                                'contracts' => '/admin/financial-fix-costs',
                                                'open_tickets' => '/admin/tickets',
                                                'product_reviews' => '/admin/reviews',
                                                'open_credits' => '/admin/credit-management',
                                                'unassigned_tx' => '/admin/financial-banks',
                                                'open_tasks' => '/admin/tasks',
                                                'open_quotes' => '/admin/quote-requests',
                                                'open_revocations' => '/admin/widerruf',
                                                default => '/admin/dashboard'
                                            };
                                            
                                            $actionText = match($key) {
                                                'inventory' => 'Bestand anpassen',
                                                'special_issues' => 'Belege hochladen',
                                                'contracts' => 'Dokumente hochladen',
                                                'open_tickets' => 'Tickets bearbeiten',
                                                'product_reviews' => 'Reviews prüfen',
                                                'open_credits' => 'Gutschriften prüfen',
                                                'unassigned_tx' => 'Umsätze verbuchen',
                                                'open_tasks' => 'Aufgaben erledigen',
                                                'open_quotes' => 'Angebote prüfen',
                                                'open_revocations' => 'Widerrufe bearbeiten',
                                                default => 'Jetzt bearbeiten'
                                            };
                                        @endphp

                                        <div class="bg-gray-950/70 border border-gray-800/80 rounded-2xl p-5 w-full flex flex-col items-center shadow-inner relative overflow-hidden group">
                                            <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                                            <i class="bi {{ $check['icon'] ?? 'bi-link' }} text-[2rem] text-primary/40 mb-3 group-hover:text-primary transition-colors duration-500"></i>
                                            <p class="text-[9px] font-bold text-gray-400 mb-4 text-center leading-relaxed">
                                                Verwalte diese Aktionen direkt im <span class="text-gray-300">Hauptmodul</span>, um alle verfügbaren Werkzeuge zu nutzen.
                                            </p>
                                            <a href="{{ $actionUrl }}" class="relative w-full overflow-hidden rounded-xl bg-gray-900 border border-primary/30 group-hover:border-primary/60 transition-colors shadow-glow text-center group-hover:-translate-y-0.5 transform duration-300 ease-out">
                                                <div class="absolute inset-0 w-1/4 h-full bg-gradient-to-r from-transparent via-white/10 to-transparent skew-x-[-30deg] -translate-x-[150%] animate-[shine_3s_infinite_ease-in-out]"></div>
                                                <span class="block px-6 py-3 text-[10px] font-black uppercase tracking-widest text-primary group-hover:text-amber-300 transition-colors relative z-10">{{ $actionText }}</span>
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="h-full flex flex-col items-center justify-center opacity-40 py-6">
                                        <i class="solar-check-circle-bold-duotone text-4xl text-gray-600 mb-2"></i>
                                        <div class="bg-gray-900 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest text-gray-500 border border-gray-800">Leer</div>
                                    </div>
                                @endif
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 3. SYSTEM DETAILS -->
            <div x-show="activeTab === 'system'" x-transition.opacity.duration.300ms wire:init="checkSystemHealth">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">System & Infrastruktur</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Latenzen, API Verbindungen und Hintergrund-Jobs zur Serverintegrität.</p>
                    </div>
                    <button type="button" wire:click="fixSystem" wire:loading.attr="disabled" class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-900 bg-primary hover:bg-primary-dark shadow-glow flex items-center gap-2">
                        <span wire:loading.remove wire:target="fixSystem">Fix System Starten</span>
                        <span wire:loading wire:target="fixSystem" class="animate-pulse">Arbeite...</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 lg:gap-8">
                    @foreach($systemGroups as $groupName => $groupInfo)
                        <div class="bg-gray-950/40 rounded-[2rem] border border-gray-800 flex flex-col p-6 shadow-inner gap-5">
                            <div class="flex items-center gap-3 border-b border-gray-800 pb-3">
                                <div class="w-1.5 h-4 rounded-full {{ $groupInfo['color'] }}"></div>
                                <h5 class="text-xs font-black text-gray-300 uppercase tracking-widest">{{ $groupName }}</h5>
                            </div>
                            
                            <div class="flex flex-col gap-4">
                                @foreach($groupInfo['items'] as $sKey)
                                    @if($sKey === 'ws')
                                        <div x-data="{ wsStatus: 'checking', checkConnection() { if(window.Echo){ this.wsStatus = 'connected'; } else { this.wsStatus = 'unavailable'; } } }" x-init="checkConnection()">
                                            <div class="flex items-center gap-3">
                                                <span class="w-2 h-2 rounded-full shadow-glow shrink-0" :class="{'bg-emerald-400': wsStatus === 'connected', 'bg-red-400': wsStatus === 'unavailable', 'bg-gray-500': wsStatus === 'checking'}"></span>
                                                <div class="flex-1 flex justify-between items-center text-[10px] uppercase tracking-widest font-black">
                                                    <span class="text-gray-400">WebSocket</span>
                                                    <span :class="{'text-emerald-400': wsStatus === 'connected', 'text-red-400': wsStatus === 'unavailable', 'text-gray-500': wsStatus === 'checking'}" x-text="wsStatus"></span>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        @php
                                            $health = $systemHealth[$sKey] ?? null;
                                            $status = $health ? $health['status'] : 'checking';
                                            $msg = $health ? $health['value'] : 'Prüfe...';
                                            $dotColor = match($status) { 'connected' => 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]', 'warning' => 'bg-amber-500', 'error', 'unavailable' => 'bg-red-500', default => 'bg-gray-500' };
                                            $textColor = match($status) { 'connected' => 'text-emerald-400', 'warning' => 'text-amber-400', 'error', 'unavailable' => 'text-red-400', default => 'text-gray-500' };
                                        @endphp
                                        <div class="flex items-center gap-3 group">
                                            <span class="relative w-2 h-2 rounded-full shrink-0 {{ $dotColor }}">
                                                @if($status === 'connected' || $status === 'warning') <span class="absolute inset-0 rounded-full animate-ping opacity-50 {{ $dotColor }}"></span> @endif
                                            </span>
                                            <div class="flex-1 flex justify-between items-center text-[10px] uppercase tracking-widest font-black">
                                                <span class="text-gray-400">{{ $services[$sKey]['label'] }}</span>
                                                <span class="{{ $textColor }} text-right">{{ $msg }}</span>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                
                {{-- STORAGE --}}
                @if($storageData && isset($storageData['percent_used']))
                    <div class="mt-8 bg-gray-950/40 rounded-[2rem] border border-gray-800 p-6 shadow-inner">
                        <div class="flex justify-between items-end mb-3">
                            <div>
                                <h5 class="text-xs font-black text-gray-500 uppercase tracking-widest mb-1">Server Speicherkapazität</h5>
                                <div class="text-[10px] font-bold text-gray-400 tracking-wide">{{ $storageData['percent_free'] }}% frei ({{ $storageData['free_gb'] }} GB von {{ $storageData['total_gb'] }} GB)</div>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-black {{ $storageData['percent_free'] < 10 ? 'text-red-400' : 'text-white' }} leading-none block">{{ $storageData['percent_used'] }}%</span>
                            </div>
                        </div>
                        <div class="w-full h-3 bg-gray-900 rounded-full overflow-hidden border border-gray-800 flex items-center justify-start">
                             <div class="h-full rounded-full transition-all duration-1000 bg-primary" style="width: {{ $storageData['percent_used'] }}%"></div>
                        </div>
                    </div>
                @endif
                
                {{-- REPAIR LOG --}}
                @if(count($repairLogs) > 0)
                    <div class="mt-6 border border-gray-800 rounded-[1.5rem] bg-gray-950 overflow-hidden shadow-inner flex flex-col">
                        <div class="bg-gray-900 border-b border-gray-800 px-4 py-3 flex items-center justify-between">
                            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Reparatur-Log</h4>
                            <button type="button" wire:click="$set('repairLogs', [])" class="text-gray-500 hover:text-white"><i class="bi bi-x-lg text-xs"></i></button>
                        </div>
                        <div class="p-4 font-mono text-[10px] sm:text-xs leading-relaxed max-h-[200px] overflow-y-auto custom-scrollbar flex flex-col gap-1.5">
                            @foreach($repairLogs as $log)
                                <div class="flex gap-3"><span class="text-gray-600 shrink-0">[{{ $log['time'] }}]</span><span class="text-gray-300">{{ $log['message'] }}</span></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            
        </div>
    </div>

</div>

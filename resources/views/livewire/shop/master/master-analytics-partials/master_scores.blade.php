<style>
@keyframes theme-pulse-glow {
    0%, 100% { box-shadow: 0 0 5px var(--theme-color), inset 0 0 5px var(--theme-color); border-color: var(--theme-color); }
    50% { box-shadow: 0 0 25px var(--theme-color), inset 0 0 10px var(--theme-color); border-color: var(--theme-color); }
}
.hover-theme-pulse { cursor: pointer; transition: all 0.3s ease; }
.hover-theme-pulse:hover, .active-theme-pulse { animation: theme-pulse-glow 2s ease-in-out infinite; border-color: var(--theme-color) !important; z-index: 10; }
</style>
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

        $operativeScore = 100 - ($opErrorCount * 25) - ($opWarningCount * 10) - ($totalTodos * 1);
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
            'ws' => ['label' => 'WebSocket ' . (app()->environment('local') ? '(Lokal)' : '(Stage/Live)'), 'host' => env('MIX_PUSHER_HOST', env('VITE_REVERB_HOST', env('PUSHER_HOST', '127.0.0.1'))), 'port' => env('MIX_PUSHER_PORT', env('VITE_REVERB_PORT', env('PUSHER_PORT', '6001'))), 'desc' => 'WebSocket-Verbindung (z.B. Reverb / Pusher) für Live-Updates (Chat, Analytics).'],
            'dhl' => ['label' => 'DHL Paket API', 'host' => 'api.dhl.com', 'port' => '443', 'desc' => 'Label-Generierung und Sendungsverfolgung.'],
            'finapi' => ['label' => 'finAPI (Bank)', 'host' => env('FINAPI_ENV', 'live') === 'live' ? 'live.finapi.io' : 'sandbox.finapi.io', 'port' => '443', 'desc' => 'Schnittstelle zur Bankkonto-Synchronisation.'],
            'gemini' => ['label' => 'Google Gemini', 'host' => 'generativelanguage.googleapis.com', 'port' => '443', 'desc' => 'Google AI Basismodell für komplexe Task-Bewältigung.'],
            'google_places' => ['label' => 'Google Places', 'host' => 'maps.googleapis.com', 'port' => '443', 'desc' => 'Schnittstelle für automatische Kundenbewertungen.'],
            'elster' => ['label' => 'Elster (ERiC)', 'host' => 'elster.de', 'port' => '443', 'desc' => 'Anbindung zur Finanzbehörde der Bundesrepublik Deutschland.'],
            'scraperapi' => ['label' => 'ScraperAPI', 'host' => 'api.scraperapi.com', 'port' => '80', 'desc' => 'Proxy-Service für Marktforschung und SEO-Agenten.'],
            'telephony' => ['label' => 'Audio-Bridge', 'host' => 'localhost', 'port' => env('TWILIO_WS_PORT', '8081'), 'desc' => app()->environment('local') ? 'Lokal deaktiviert. Funktioniert nur auf Stage/Live und hat keinen Einfluss auf den Health-Score.' : 'Node.js WebSocket Server für Echtzeit-Sprachanrufe zwischen Twilio und KI.'],
        ];

        $systemGroups = [
            'Kernsysteme' => [
                'color' => 'bg-primary shadow-[0_0_8px_rgba(197,160,89,0.8)]',
                'items' => ['database', 'storage', 'redis']
            ],
            'Schnittstellen & API' => [
                'color' => 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.8)]',
                'items' => ['ws', 'stripe', 'smtp', 'dhl', 'finapi', 'elster']
            ],
            'AI & Daten-Agenten' => [
                'color' => 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]',
                'items' => ['gemini', 'telephony', 'google_places', 'scraperapi']
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

        // --- 4. SECURITY SCORE ---
        $failedLogins24h = class_exists(\App\Models\System\SystemLoginAttempt::class) ? \App\Models\System\SystemLoginAttempt::where('success', false)->where('attempted_at', '>=', now()->subHours(24))->count() : 0;
        $securityWarnings24h = class_exists(\App\Models\System\SystemLog::class) ? \App\Models\System\SystemLog::whereIn('type', ['security', 'system'])->where('status', 'error')->where('started_at', '>=', now()->subHours(24))->count() : 0;
        
        $securityScore = 100 - ($failedLogins24h * 5) - ($securityWarnings24h * 10);
        $securityScore = max(0, min(100, $securityScore));

        $secColorClass = $securityScore >= 80 ? 'text-purple-400' : ($securityScore >= 50 ? 'text-amber-400' : 'text-red-400');
        $secStrokeColor = $securityScore >= 80 ? '#c084fc' : ($securityScore >= 50 ? '#fbbf24' : '#f87171');
        $secOffset = $circumference - ($securityScore / 100) * $circumference;

        $secText = 'System gesichert';
        if ($securityScore < 50) {
            $secText = 'Kritische Angriffe!';
        } elseif ($securityScore < 80) {
            $secText = 'Erhöhte Aktivität';
        }
    @endphp

    <!-- TOP ROW: THE 4 SCORES -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">

        <!-- 1. SHOP HEALTH SCORE -->
        <div @click="activeTab = activeTab === 'shop' ? null : 'shop'"
             class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'shop' ? 'active-theme-pulse' : 'border-gray-800'">

            <div class="absolute top-4 right-4 z-10" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                <div class="text-gray-400 hover:text-white cursor-help">
                    <x-heroicon-o-information-circle class="w-5 h-5" />
                </div>
                <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute top-full right-0 mt-2 w-56 p-4 bg-gray-950 border border-gray-700 rounded-xl shadow-2xl text-left pointer-events-none" style="display: none;">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 border-b border-gray-800 pb-2">Zusammensetzung</p>
                    <ul class="text-xs text-gray-300 space-y-1.5">
                        <li class="flex justify-between items-center"><span class="text-gray-400">Break-Even:</span> <span class="text-emerald-400">+30</span></li>
                        <li class="flex justify-between items-center"><span class="text-gray-400">Netto-Ziel:</span> <span class="text-emerald-400">+20</span></li>
                        <li class="flex justify-between items-center"><span class="text-gray-400">Gewinn-Marge:</span> <span class="text-emerald-400">+25</span></li>
                        <li class="flex justify-between items-center"><span class="text-gray-400">Umsatz-Trend:</span> <span class="text-emerald-400">+15</span></li>
                        <li class="flex justify-between items-center"><span class="text-gray-400">Offene Posten:</span> <span class="text-emerald-400">+10</span></li>
                        <li class="flex justify-between items-center pt-1 mt-1 border-t border-gray-800"><span class="text-gray-400">Abzüge Op.:</span> <span class="text-red-400">-5 / -1</span></li>
                        <li class="flex justify-between items-center"><span class="text-gray-400">Abzüge Sys.:</span> <span class="text-red-400">-10 / -2</span></li>
                    </ul>
                </div>
            </div>

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

        </div>

        <!-- 2. OPERATIVER SCORE -->
        <div @click="activeTab = activeTab === 'operative' ? null : 'operative'"
             class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'operative' ? 'active-theme-pulse' : 'border-gray-800'">

            <div class="absolute top-4 right-4 z-10" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                <div class="text-gray-400 hover:text-white cursor-help">
                    <x-heroicon-o-information-circle class="w-5 h-5" />
                </div>
                <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute top-full right-0 mt-2 w-56 p-4 bg-gray-950 border border-gray-700 rounded-xl shadow-2xl text-left pointer-events-none" style="display: none;">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 border-b border-gray-800 pb-2">Berechnung</p>
                    <div class="text-xs text-gray-300 space-y-2">
                        <p class="text-gray-400 text-[10px] leading-tight mb-2">Startwert: <span class="text-white">100 Punkte</span></p>
                        <ul class="space-y-1.5">
                            <li class="flex justify-between items-center"><span class="text-gray-400">Pro offenes Todo:</span> <span class="text-red-400">-1</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-400">Pro Warnung:</span> <span class="text-red-400">-10</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-400">Pro Fehler:</span> <span class="text-red-400">-25</span></li>
                        </ul>
                    </div>
                </div>
            </div>

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

        </div>

        <!-- 3. SYSTEM SCORE -->
        <div @click="activeTab = activeTab === 'system' ? null : 'system'"
             class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'system' ? 'active-theme-pulse' : 'border-gray-800'">

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

        </div>

        <!-- 4. SECURITY SCORE -->
        <div @click="activeTab = activeTab === 'security' ? null : 'security'"
             class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'security' ? 'active-theme-pulse' : 'border-gray-800'">

            <div class="absolute top-4 right-4 z-10" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                <div class="text-gray-400 hover:text-white cursor-help">
                    <x-heroicon-o-information-circle class="w-5 h-5" />
                </div>
                <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute top-full right-0 mt-2 w-56 p-4 bg-gray-950 border border-gray-700 rounded-xl shadow-2xl text-left pointer-events-none" style="display: none;">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 border-b border-gray-800 pb-2">Berechnung (Letzte 24h)</p>
                    <div class="text-xs text-gray-300 space-y-2">
                        <p class="text-gray-400 text-[10px] leading-tight mb-2">Startwert: <span class="text-white">100 Punkte</span></p>
                        <ul class="space-y-1.5">
                            <li class="flex justify-between items-center"><span class="text-gray-400">Pro fehlgeschlagenem Login:</span> <span class="text-red-400">-5</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-400">Pro System-Fehler/Angriff:</span> <span class="text-red-400">-10</span></li>
                        </ul>
                    </div>
                </div>
            </div>

            <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-4">Sicherheit & Abwehr</h3>

            <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="#1f2937" stroke-width="8"></circle>
                    <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $secStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $secOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out drop-shadow-[0_0_8px_currentColor]"></circle>
                </svg>
                <div class="absolute flex flex-col items-center justify-center">
                    <span class="text-3xl font-black {{ $secColorClass }} drop-shadow-[0_0_10px_currentColor]">{{ $securityScore }}</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Score</span>
                </div>
            </div>

            <div class="mb-4">
                @if($failedLogins24h > 0 || $securityWarnings24h > 0)
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-400">
                        <span class="text-white">{{ $failedLogins24h + $securityWarnings24h }}</span> Ereignisse (24h)
                    </p>
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 {{ $secColorClass }} animate-pulse">{{ $secText }}</p>
                @else
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-400">0 Ereignisse</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 text-purple-400">{{ $secText }}</p>
                @endif
            </div>

        </div>
    </div>

    <!-- 2nd ROW: THE 4 BENTO WIDGETS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
        <!-- 4. SPEICHER & PRODUKTIONS-LAST -->
        <div @click="activeTab = activeTab === 'capacities' ? null : 'capacities'"
             class="bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 shadow-xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'capacities' ? 'active-theme-pulse' : 'border-gray-800'">
             
            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2.5">Kapazitäten</h3>

            @php $shopLoad = \Illuminate\Support\Facades\Cache::get('shop_capacity_percentage', 0); @endphp
            <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-950 rounded-2xl border border-gray-800">
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <x-heroicon-s-cog class="w-6 h-6 {{ $shopLoad > 90 ? 'text-red-500 animate-spin-slow' : ($shopLoad > 70 ? 'text-orange-500' : 'text-emerald-500') }} mb-1 opacity-80" />
                    <span class="text-base font-black text-white tracking-tighter leading-none">{{ $shopLoad }}%</span>
                    <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Auslastung</span>
                </div>
            </div>

            <div class="mb-3">
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Speicherplatz</p>
                <p class="text-[10px] font-black uppercase tracking-widest mt-1 text-blue-500">{{ isset($storageData['percent_free']) ? $storageData['percent_free'] . '% Frei' : 'N/A' }}</p>
            </div>

        </div>

        <!-- 5. GEWINN-ENTWICKLUNG -->
        <div @click="activeTab = activeTab === 'profit' ? null : 'profit'"
             class="bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 shadow-xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'profit' ? 'active-theme-pulse' : 'border-gray-800'">
             
            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2.5">Gewinn</h3>

            @php
                $bEven = $stats['break_even_monthly'] ?? 0;
                $currentRev = $stats['avg_revenue_monthly'] ?? 0;
                $missing = max(0, $bEven - $currentRev);
                $beColor = $missing > 0 ? 'text-red-500' : 'text-emerald-500';
            @endphp
            <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-950 rounded-2xl border border-gray-800">
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <x-heroicon-s-currency-euro class="w-6 h-6 text-emerald-400 mb-1 opacity-80 drop-shadow-[0_0_8px_rgba(52,211,153,0.5)]" />
                    <span class="text-base font-black text-white tracking-tighter leading-none">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }}</span>
                    <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Mtl. Gewinn</span>
                </div>
            </div>

            <div class="mb-3">
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Break-Even</p>
                @if($missing > 0)
                    <p class="text-[10px] font-black uppercase tracking-widest mt-1 {{ $beColor }}">-{{ number_format($missing, 0, ',', '.') }} € fehlend</p>
                @else
                    <p class="text-[10px] font-black uppercase tracking-widest mt-1 {{ $beColor }}">Erreicht!</p>
                @endif
            </div>

        </div>

        <!-- 6. E-COMMERCE EINBLICKE -->
        <div @click="activeTab = activeTab === 'ecommerce' ? null : 'ecommerce'"
             class="bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 shadow-xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'ecommerce' ? 'active-theme-pulse' : 'border-gray-800'">
             
            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2.5">E-Commerce</h3>

            <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-950 rounded-2xl border border-gray-800">
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <x-heroicon-s-shopping-bag class="w-6 h-6 text-[#C5A059] mb-1 opacity-80" />
                    <span class="text-base font-black text-white tracking-tighter leading-none">{{ number_format($stats['orders_total'] ?? 0, 0, ',', '.') }}</span>
                    <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Insg. Orders</span>
                </div>
            </div>

            <div class="mb-3">
                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Verkäufe Detail</p>
                <p class="text-[10px] font-black uppercase tracking-widest mt-1 text-[#C5A059]">Umsatz & Artikel</p>
            </div>

        </div>

        <!-- 7. TRAFFIC & KUNDEN -->
        <div @click="activeTab = activeTab === 'traffic' ? null : 'traffic'"
             class="bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 shadow-xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
             :class="activeTab === 'traffic' ? 'active-theme-pulse' : 'border-gray-800'">
             
            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-2.5">Traffic & Kunden</h3>

            @php $onlineCount = collect($systemHealth)->count() > 0 ? $this->getActiveSessionsCount() : 0; @endphp
            <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-950 rounded-2xl border border-gray-800">
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <x-heroicon-s-globe-alt class="w-6 h-6 text-blue-400 mb-1 opacity-80 drop-shadow-[0_0_8px_rgba(96,165,250,0.5)]" />
                    <span class="text-base font-black text-white tracking-tighter leading-none">{{ number_format($stats['frontend_unique_total'] ?? 0, 0, ',', '.') }}</span>
                    <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Besucher</span>
                </div>
            </div>

            <div class="mb-3 space-y-2">
                <div>
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Total Pageviews</p>
                    <p class="text-[10px] font-black uppercase tracking-widest mt-0.5 text-blue-300">{{ number_format($stats['frontend_visits_total'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Aktuell Online</p>
                    <div class="flex items-center justify-center gap-1.5 mt-0.5">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                        </span>
                        <p class="text-[10px] font-black uppercase tracking-widest text-emerald-400">{{ $onlineCount }} Nutzer</p>
                    </div>
                </div>
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
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">Finanzielle Gesundheit</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Gesicherter Statuswert aus Liquiditätsreserven, Break-Even, Rentabilität und aktuellen Markttrends.</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right hidden sm:block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 block mb-1">Monatsgewinn (Ø)</span>
                            <span class="text-2xl font-black text-white tracking-tighter">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
                        </div>
                    </div>
                </div>

                @if($opErrorCount > 0 || $opWarningCount > 0)
                    <div class="bg-red-950/20 rounded-xl p-5 border border-red-500/20 relative mt-4">
                         <div class="flex items-start gap-3">
                             <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
                             <div>
                                 <h3 class="text-xs font-bold text-red-400 uppercase tracking-widest mb-1.5">Operative Abzüge: -{{ ($opErrorCount * 5) + ($opWarningCount * 1) }} Punkte</h3>
                                 <p class="text-[10px] text-red-400/80 leading-relaxed font-semibold">Dein finanzieller Basis-Score wurde aufgrund operativer Störfälle aus den Fachbereichen (z.B. Fehlerhafte Bestellungen, offene Schadensmeldungen, liegengebliebene Todos) reduziert. Behebe diese Fehlstände im <span class="text-white cursor-pointer" @click="activeTab = 'operative'">Operativen Status</span>, um die 100 Punkte wieder zu erreichen.</p>
                             </div>
                         </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Säule 1: WACHSTUM -->
                    <div class="bg-gray-950 border border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-5">
                        <div class="flex items-center gap-3 border-b border-gray-800/80 pb-3">
                            <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-blue-500" />
                            <h3 class="text-sm font-semibold text-white uppercase tracking-widest">Wachstum</h3>
                        </div>
                        <div class="flex flex-col gap-3">
                            <div class="flex justify-between items-center group">
                                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest flex items-center gap-2"><x-heroicon-o-banknotes class="w-3.5 h-3.5 text-gray-500" /> Umsatzrahmen</span>
                                <span class="text-base font-black text-gray-200 group-hover:text-white transition-colors">{{ number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                            <div class="w-full h-px bg-gray-800/50"></div>
                            <div class="flex justify-between items-center group">
                                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest flex items-center gap-2"><x-heroicon-o-chart-bar class="w-3.5 h-3.5 text-gray-500" /> Wachstumstrend</span>
                                <span class="text-base font-black {{ ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-emerald-500' : 'text-red-500' }} group-hover:drop-shadow-[0_0_8px_currentColor] transition-all">{{ ($stats['revenue_growth'] ?? 0) > 0 ? '+' : '' }}{{ $stats['revenue_growth'] ?? 0 }} %</span>
                            </div>
                            <div class="w-full h-px bg-gray-800/50"></div>
                            <div class="flex justify-between items-center group">
                                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest flex items-center gap-2"><x-heroicon-o-calendar-days class="w-3.5 h-3.5 text-gray-500" /> Jahreshochlauf</span>
                                <span class="text-base font-black text-blue-400 group-hover:text-blue-300 transition-colors">{{ number_format($stats['projected_year'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                        </div>
                    </div>

                    <!-- Säule 2: EFFIZIENZ -->
                    <div class="bg-gray-950 border border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-5">
                        <div class="flex items-center gap-3 border-b border-gray-800/80 pb-3">
                            <x-heroicon-o-scale class="w-5 h-5 text-emerald-500" />
                            <h3 class="text-sm font-semibold text-white uppercase tracking-widest">Rentabilität</h3>
                        </div>
                        <div class="flex flex-col gap-3">
                            <div class="flex justify-between items-center group">
                                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest flex items-center gap-2"><x-heroicon-o-receipt-percent class="w-3.5 h-3.5 text-gray-500" /> Nettomarge</span>
                                <span class="text-base font-black text-emerald-500 group-hover:text-emerald-400 transition-colors">{{ $stats['margin'] ?? 0 }} %</span>
                            </div>
                            <div class="w-full h-px bg-gray-800/50"></div>
                            <div class="flex justify-between items-center group">
                                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest flex items-center gap-2"><x-heroicon-o-currency-dollar class="w-3.5 h-3.5 text-gray-500" /> Gewinn pro Monat</span>
                                <span class="text-base font-black text-gray-200 group-hover:text-white transition-colors">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                        </div>
                    </div>

                    <!-- Säule 3: SICHERHEIT -->
                    <div class="bg-gray-950 border border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-5" x-data="{ showCosts: false }">
                        <div class="flex items-center gap-3 border-b border-gray-800/80 pb-3">
                            <x-heroicon-o-shield-check class="w-5 h-5 text-amber-500" />
                            <h3 class="text-sm font-semibold text-white uppercase tracking-widest">Sicherheit</h3>
                        </div>
                        <div class="flex flex-col gap-3">
                            <div class="flex justify-between items-center group">
                                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest flex items-center gap-2"><x-heroicon-o-flag class="w-3.5 h-3.5 text-gray-500" /> Break-Even (Mtl.)</span>
                                <span class="text-base font-black text-amber-500 group-hover:text-amber-400 transition-colors">{{ number_format($stats['break_even_monthly'] ?? 0, 0, ',', '.') }} €</span>
                            </div>
                            <div class="w-full h-px bg-gray-800/50"></div>

                            @php
                                $hasPending = ($stats['pending_invoices']['sum'] ?? 0) > 0;
                                $pendingCount = $stats['pending_invoices']['count'] ?? 0;
                            @endphp
                            <div class="flex justify-between items-center group {{ $hasPending ? 'bg-red-950/20 -mx-2 px-2 py-1 rounded-lg border border-red-500/20' : '' }}">
                                <span class="text-[10px] font-semibold uppercase tracking-widest flex items-center gap-2 {{ $hasPending ? 'text-red-400' : 'text-gray-400' }}">
                                    <x-heroicon-o-document-magnifying-glass class="w-3.5 h-3.5 {{ $hasPending ? 'text-red-500' : 'text-gray-500' }}" /> Offene Posten
                                </span>
                                <div class="text-right">
                                    <span class="text-base font-black {{ $hasPending ? 'text-red-500 animate-pulse' : 'text-gray-500' }}">{{ number_format($stats['pending_invoices']['sum'] ?? 0, 0, ',', '.') }} €</span>
                                    @if($pendingCount > 0)
                                        <span class="text-[8px] text-gray-400 font-bold uppercase tracking-widest block -mt-1">{{ $pendingCount }} Rechnungen</span>
                                    @endif
                                </div>
                            </div>

                            <div class="w-full h-px bg-gray-800/50 mt-1"></div>

                            <div class="pt-2">
                                <button @click="showCosts = !showCosts" class="w-full flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors bg-gray-900 px-3 py-2 rounded-lg border border-gray-800 hover:border-gray-700">
                                    <span>Kostenstruktur Details</span>
                                    <x-heroicon-m-chevron-down class="w-3 h-3 transition-transform" ::class="showCosts ? 'rotate-180' : ''" />
                                </button>
                                <div x-show="showCosts" x-cloak x-collapse class="space-y-2 mt-3 pt-1 border-t border-gray-800/50">
                                    <div class="flex justify-between items-center"><span class="text-[9px] font-semibold text-gray-500 uppercase tracking-widest">Einnahmen Fix</span><span class="text-xs font-black text-emerald-500/80">{{ number_format($stats['fixed_income_total'] ?? 0, 0, ',', '.') }} €</span></div>
                                    <div class="flex justify-between items-center"><span class="text-[9px] font-semibold text-gray-500 uppercase tracking-widest">Kosten Privat</span><span class="text-xs font-black text-purple-400/80">{{ number_format($stats['fixed_expenses_priv'] ?? 0, 0, ',', '.') }} €</span></div>
                                    <div class="flex justify-between items-center"><span class="text-[9px] font-semibold text-gray-500 uppercase tracking-widest">Kosten Gewerbe</span><span class="text-xs font-black text-blue-400/80">{{ number_format($stats['fixed_expenses_gew'] ?? 0, 0, ',', '.') }} €</span></div>
                                    <div class="flex justify-between items-center"><span class="text-[9px] font-semibold text-gray-500 uppercase tracking-widest">Sonderkosten</span><span class="text-xs font-black text-amber-500/80">{{ number_format($stats['variable_expenses'] ?? 0, 0, ',', '.') }} €</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. OPERATIVE DETAILS -->
            <div x-show="activeTab === 'operative'" x-transition.opacity.duration.300ms x-data="{ showAllOperative: false }">
                <div class="flex justify-between items-end border-b border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">To-do Liste & Operatives</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Aktuelle Aufgaben aus dem Tagesgeschäft, Buchhaltung und Service.</p>
                    </div>
                    <button type="button" @click="showAllOperative = !showAllOperative" :class="showAllOperative ? 'bg-[#C5A059]/20 text-[#C5A059] border-[#C5A059]/50' : 'bg-gray-900 border-gray-700 text-gray-400 hover:text-white hover:border-gray-500'" class="w-10 h-10 rounded-full border flex items-center justify-center transition-colors shadow-lg" title="Ansicht umschalten (Nur offene / Alle zeigen)">
                        <x-heroicon-o-funnel class="w-5 h-5" />
                    </button>
                </div>

                @php
                    $descriptions = [
                        'inventory' => 'Prüft, ob physische Artikel den Mindestbestand unterschritten haben und nachbestellt werden müssen.',
                        'special_issues' => 'Zeigt Sonderausgaben, für die noch kein Beleg oder keine Rechnung hochgeladen wurde.',
                        'leitung/contracts' => 'Listet Fixkosten-Positionen auf, für die noch kein Vertragsdokument hinterlegt ist.',
                        'open_orders' => 'Bestellungen, die bezahlt, aber noch nicht komplett versendet / abgeschlossen sind.',
                        'open_tickets' => 'Kunden-Tickets aus dem Support, die noch nicht auf "erledigt" gesetzt wurden.',
                        'open_chats' => 'Live-Chat Anfragen von Kunden, die auf eine Antwort vom System warten.',
                        'open_contact_requests' => 'Eingegangene Anfragen über das Kontaktformular, die unbearbeitet sind.',
                        'open_mails' => 'Ungelesene E-Mails im Posteingang, die Aufmerksamkeit erfordern.',
                        'product_reviews' => 'Von Kunden eingereichte Produktbewertungen, die auf manuelle Freigabe warten.',
                        'open_credits' => 'Erstellte Gutschriften oder Stornos, die noch nicht per E-Mail an den Kunden verschickt wurden.',
                        'unassigned_tx' => 'Banktransaktionen, die noch keinem Auftrag und keiner Rechnung zugeordnet wurden.',
                        'open_tasks' => 'Allgemeine, systemübergreifende To-dos, die von Mitarbeitern noch offen sind.',
                        'open_quotes' => 'Kunden-Anfragen für individuelle Spezial-Angebote, die noch nicht kalkuliert wurden.',
                        'open_revocations' => 'Kunden-Widerrufe, die noch nicht final bearbeitet oder gutgeschrieben wurden.',
                        'open_losses' => 'Transport-Schäden oder Bruch auf dem Transportweg, die auf Rückerstattung oder Nachsendung warten.',
                        'open_blog_posts' => 'Artikel, die sich momentan im Entwurf befinden oder auf ihre Veröffentlichung warten.',
                        'open_abandoned_carts' => 'Kunden haben ihren Einkaufsprozess abgebrochen. Überprüfe die Warenkörbe und sende ggf. eine Erinnerung.',
                        'system_logs' => 'Offene System-Fehler und Warnungen, die im Hintergrund-Prozess protokolliert wurden.',
                    ];

                    $actionNeeded = [];
                    $done = [];

                    foreach($healthChecks as $key => $check) {
                        $check['description'] = $descriptions[$key] ?? 'Systemhinweis zu ' . $check['title'];
                        if ($check['count'] > 0) {
                            $actionNeeded[$key] = $check;
                        } else {
                            $done[$key] = $check;
                        }
                    }
                @endphp

                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr>
                            <th class="pb-2 pl-4 text-[10px] font-bold uppercase tracking-widest text-gray-500">Kategorie / Aufgabe</th>
                            <th class="pb-2 pr-4 text-[10px] font-bold uppercase tracking-widest text-gray-500 text-right">To-Do Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_merge($actionNeeded, $done) as $key => $check)
                            @php
                                $actionUrl = match($key) {
                                    'inventory' => '/admin/products',
                                    'special_issues' => '/admin/financial-variable-costs',
                                    'leitung/contracts' => '/admin/financial-fix-costs',
                                    'open_orders' => '/admin/orders',
                                    'open_tickets' => '/admin/support-tickets',
                                    'open_chats' => '/admin/support-chats',
                                    'open_contact_requests' => '/admin/support-contact-form',
                                    'open_mails' => '/admin/inbox',
                                    'product_reviews' => '/admin/reviews',
                                    'open_credits' => '/admin/credit-management',
                                    'unassigned_tx' => '/admin/financial-banks',
                                    'open_tasks' => '/admin/tasks',
                                    'open_quotes' => '/admin/quote-requests',
                                    'open_revocations' => '/admin/widerruf',
                                    'open_losses' => '/admin/product-fracture',
                                    'open_blog_posts' => '/admin/blog',
                                    'open_abandoned_carts' => '/admin/shopping-carts',
                                    'system_logs' => '/admin/global-logs',
                                    default => '/admin/dashboard'
                                };

                                $statusClass = match($check['status'] ?? 'error') {
                                    'success' => 'text-emerald-500 group-hover:text-emerald-400',
                                    'todo' => 'text-blue-500 group-hover:text-blue-400',
                                    'warning' => 'text-amber-500 group-hover:text-amber-400',
                                    default => 'text-red-500 group-hover:text-red-400',
                                };

                                $needsAction = $check['count'] > 0;
                            @endphp

                            <tr @if(!$needsAction) x-show="showAllOperative" x-transition.opacity @endif
                                class="bg-gray-950 hover:bg-gray-900/80 transition-all group cursor-pointer relative"
                                onclick="window.open('{{ $actionUrl }}', '_blank')">

                                <td class="py-4 pl-4 rounded-l-xl border-y border-l border-gray-800/80 group-hover:border-gray-700 relative w-full">
                                    <div class="flex items-center gap-4">
                                        <div class="shrink-0 transition-colors {{ $statusClass }}">
                                            <x-dynamic-component :component="'heroicon-o-' . $check['icon']" class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-semibold text-gray-300 group-hover:text-white transition-colors">{{ $check['title'] }}</h4>

                                                <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false" @click.stop>
                                                    <x-heroicon-m-information-circle class="w-4 h-4 text-gray-600 hover:text-[#C5A059] transition-colors" />

                                                    <!-- Tooltip -->
                                                    <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-[280px] p-4 bg-gray-950 border border-[#C5A059]/40 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.8)] z-[100] text-center" style="display: none;">
                                                        <div class="text-[11.5px] text-gray-300 font-medium leading-relaxed">{{ $check['description'] }}</div>
                                                        <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-gray-950 border-b border-r border-[#C5A059]/40 rotate-45"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $check['message'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 pr-4 rounded-r-xl border-y border-r border-gray-800/80 group-hover:border-gray-700 text-right whitespace-nowrap">
                                    @if($needsAction)
                                        <div class="inline-flex items-center gap-2 bg-gray-900/50 border border-gray-800 px-3 py-1.5 rounded-lg">
                                            <div class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></div>
                                            <span class="text-xs font-bold text-gray-300">{{ $check['count'] }}</span>
                                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-600 group-hover:text-[#C5A059] transition-colors ml-2" />
                                        </div>
                                    @else
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5">
                                            <x-heroicon-m-check-circle class="w-5 h-5 text-emerald-500/50" />
                                            <span class="text-xs font-bold text-gray-500">Erledigt</span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div x-show="!showAllOperative && {{ count($actionNeeded) }} === 0" class="py-12 text-center border border-dashed border-gray-800 rounded-2xl">
                    <div class="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <x-heroicon-o-check-badge class="w-8 h-8 text-emerald-500" />
                    </div>
                    <h3 class="text-lg font-bold text-white mb-1">Alles erledigt!</h3>
                    <p class="text-sm text-gray-500 max-w-sm mx-auto">Es gibt aktuell keine systemseitigen Aufgaben oder Probleme, die deiner Aufmerksamkeit bedürfen.</p>
                </div>
            </div>

            <!-- 3. SYSTEM DETAILS -->
            <div x-show="activeTab === 'system'" x-transition.opacity.duration.300ms wire:init="checkSystemHealth">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">System & Infrastruktur</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Latenzen, API Verbindungen und Hintergrund-Jobs zur Serverintegrität.</p>
                    </div>
                    <button type="button" wire:click="fixSystem" wire:loading.attr="disabled" class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-900 bg-primary hover:bg-primary-dark hover:text-white shadow-glow flex items-center gap-2">
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
                                        <div x-data="{ 
                                            wsStatus: 'checking', 
                                            tooltip: false,
                                            wsHost: 'Lade...',
                                            wsPort: 'Lade...',
                                            checkConnection() { 
                                                if(window.Echo && window.Echo.connector && window.Echo.connector.pusher) { 
                                                    this.wsStatus = window.Echo.connector.pusher.connection.state;
                                                    
                                                    // Wir lesen die ECHTEN, aktiv genutzten Werte direkt aus dem laufenden Echo-Client aus
                                                    this.wsHost = window.Echo.connector.pusher.config.wsHost || 'Unbekannt';
                                                    this.wsPort = window.Echo.connector.pusher.config.wsPort || 'Unbekannt';

                                                    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
                                                        this.wsStatus = states.current;
                                                    });
                                                } else { 
                                                    this.wsStatus = 'unavailable'; 
                                                } 
                                            } 
                                        }" x-init="checkConnection()" @mouseenter="tooltip = true" @mouseleave="tooltip = false" class="relative cursor-help">
                                            <div class="flex items-center gap-3">
                                                <span class="relative w-2 h-2 rounded-full shrink-0" :class="{'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]': wsStatus === 'connected', 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]': wsStatus === 'unavailable', 'bg-gray-500': wsStatus === 'checking'}">
                                                </span>
                                                <div class="flex-1 flex justify-between items-center text-[10px] uppercase tracking-widest font-black">
                                                    <span class="text-gray-400">{{ $services['ws']['label'] }}</span>
                                                    <span :class="{'text-emerald-400': wsStatus === 'connected', 'text-red-400': wsStatus === 'unavailable', 'text-gray-500': wsStatus === 'checking'}" x-text="wsStatus"></span>
                                                </div>
                                            </div>

                                            <!-- Tooltip für WebSocket -->
                                            <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute bottom-full mb-2 right-0 w-[240px] sm:w-[280px] p-4 bg-gray-950 border border-[#C5A059]/40 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.8)] z-[100] text-center" style="display: none;">
                                                
                                                @php
                                                    $correctWsHost = app()->environment('local') ? '127.0.0.1' : 'ws.mein-seelenfunke.de';
                                                    $correctWsPort = app()->environment('local') ? '6001' : '443';
                                                @endphp

                                                <div class="flex flex-col gap-2 text-[9px] text-left font-mono text-gray-400">
                                                    <div class="flex justify-between gap-4">
                                                        <span class="font-bold text-gray-500">REALER JS HOST:</span>
                                                        <span class="truncate" :class="wsHost === '{{ $correctWsHost }}' || wsHost === 'localhost' ? 'text-emerald-400' : 'text-red-400 font-black'" x-text="wsHost"></span>
                                                    </div>
                                                    <div class="flex justify-between gap-4">
                                                        <span class="font-bold text-gray-500">REALER JS PORT:</span>
                                                        <span :class="wsPort == '{{ $correctWsPort }}' ? 'text-emerald-400' : 'text-red-400 font-black'" x-text="wsPort"></span>
                                                    </div>
                                                    <div class="flex justify-between gap-4">
                                                        <span class="font-bold text-gray-500 opacity-60">SOLL-WERT:</span>
                                                        <span class="text-gray-600">{{ $correctWsHost }} : {{ $correctWsPort }}</span>
                                                    </div>

                                                    <div class="border-t border-gray-800 my-1"></div>
                                                    
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-emerald-900/20 p-2 rounded-lg border border-emerald-800/50">
                                                        <span class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest mb-1"><i class="bi bi-robot"></i> Intelligentes Routing aktiv:</span>
                                                        <div class="text-[9px] text-emerald-400/80 leading-relaxed">Das JS-Frontend erkennt die Umgebung dynamisch anhand der Browser-URL. Es sind <b>keine</b> manuellen `.env`-Wechsel vor dem lokalen Kompilieren mehr nötig!</div>
                                                    </div>

                                                    <div class="border-t border-gray-800 my-1"></div>
                                                    
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-900/50 p-2 rounded-lg border border-gray-800">
                                                        <span class="text-[9px] font-bold text-[#C5A059] uppercase tracking-widest mb-1"><i class="bi bi-hdd-network"></i> Backend Status:</span>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Server Ping:</span>
                                                            <span class="{{ ($systemHealth['ws']['status'] ?? '') === 'connected' ? 'text-emerald-400' : 'text-red-400' }} font-black">
                                                                {{ $systemHealth['ws']['value'] ?? 'Unbekannt' }}
                                                            </span>
                                                        </div>
                                                        @if(($systemHealth['ws']['status'] ?? '') !== 'connected')
                                                            <div class="text-[9px] text-red-400 mt-1 whitespace-normal leading-tight">
                                                                {{ $systemHealth['ws']['error'] ?? '' }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="border-t border-gray-800 my-1"></div>
                                                    


                                                    <div x-show="wsStatus === 'disconnected'" class="text-red-400 font-sans font-bold leading-relaxed text-center">Fehler: Der WebSocket-Server antwortet nicht.</div>
                                                    <div x-show="wsStatus === 'unavailable'" class="text-red-400 font-sans font-bold leading-relaxed text-center">Fehler: Laravel Echo konnte (im Browser) nicht initialisiert werden.</div>
                                                    <div x-show="wsStatus === 'connected'" class="text-emerald-400 font-sans font-bold leading-relaxed flex items-center justify-center gap-1.5"><i class="bi bi-shield-check"></i> System läuft zu 100% stabil.</div>
                                                </div>

                                                <div class="mt-3">
                                                    <button type="button" wire:click="fixSystem('ws')" wire:loading.attr="disabled" class="w-full px-2 py-1.5 rounded-lg border border-primary/50 bg-primary/10 hover:bg-primary/30 text-primary hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer flex items-center justify-center gap-1.5">
                                                        <span wire:loading.remove wire:target="fixSystem('ws')"><i class="bi bi-braces-asterisk"></i> JS Frontend neu bauen</span>
                                                        <span wire:loading wire:target="fixSystem('ws')" class="animate-pulse">Baut JS... (kann dauern)</span>
                                                    </button>
                                                </div>

                                                <div class="absolute -bottom-1.5 right-4 w-3 h-3 bg-gray-950 border-b border-r border-[#C5A059]/40 rotate-45"></div>
                                            </div>
                                        </div>
                                    @else
                                        @php
                                            $health = $systemHealth[$sKey] ?? null;
                                            $status = $health ? $health['status'] : 'checking';
                                            $msg = $health ? $health['value'] : 'Prüfe...';
                                            $dotColor = match($status) { 'connected' => 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]', 'warning' => 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]', 'error', 'unavailable' => 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]', default => 'bg-gray-500' };
                                            $textColor = match($status) { 'connected' => 'text-emerald-400', 'warning' => 'text-amber-400', 'error', 'unavailable' => 'text-red-400', default => 'text-gray-500' };
                                        @endphp
                                        <div class="relative cursor-help" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                            <div class="flex items-center gap-3">
                                                <span class="relative w-2 h-2 rounded-full shrink-0 {{ $dotColor }}">
                                                    @if($status === 'connected' || $status === 'warning') <span class="absolute inset-0 rounded-full animate-ping opacity-50 {{ $dotColor }}"></span> @endif
                                                </span>
                                                <div class="flex-1 flex justify-between items-center text-[10px] uppercase tracking-widest font-black">
                                                    <span class="text-gray-400">{{ $services[$sKey]['label'] }}</span>
                                                    <span class="{{ $textColor }} text-right">{{ $msg }}</span>
                                                </div>
                                            </div>

                                            <!-- Tooltip für Services -->
                                            <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute bottom-full mb-2 right-0 w-[240px] p-3 bg-gray-950 border border-[#C5A059]/40 rounded-xl shadow-[0_10px_40px_rgba(0,0,0,0.8)] z-[100] text-center" style="display: none;">
                                                <div class="text-[10px] text-[#C5A059] font-black uppercase tracking-widest mb-1">{{ $services[$sKey]['host'] }}:{{ $services[$sKey]['port'] }}</div>
                                                <div class="text-[11px] text-gray-300 font-medium leading-relaxed">{{ $services[$sKey]['desc'] }}</div>
                                                
                                                @if($sKey === 'queue' && $health)
                                                    <div class="flex justify-between gap-4 mt-2 bg-gray-900/50 p-2 rounded-lg border border-gray-800 text-left">
                                                        <span class="font-bold text-[9px] text-gray-500">WARTEND: <span class="text-white">{{ $health['pending'] ?? 0 }} Jobs</span></span>
                                                        <span class="font-bold text-[9px] text-gray-500">FEHLER: <span class="{{ ($health['failed'] ?? 0) > 0 ? 'text-red-400' : 'text-emerald-400' }}">{{ $health['failed'] ?? 0 }} Jobs</span></span>
                                                    </div>
                                                    @if(($health['failed'] ?? 0) > 0)
                                                        <button type="button" wire:click="flushFailedJobs" class="w-full mt-2 px-2 py-1.5 rounded-lg border border-red-700 bg-red-900/30 hover:bg-red-800 text-red-300 hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer">
                                                            Fehlgeschlagene Jobs final löschen
                                                        </button>
                                                    @endif
                                                @endif

                                                @if($sKey === 'scheduler')
                                                    <div class="border-t border-gray-800 my-2"></div>
                                                    
                                                    <!-- Cronjob Status -->
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-900/50 p-2 rounded-lg border border-gray-800">
                                                        <span class="text-[9px] font-bold text-[#C5A059] uppercase tracking-widest mb-1"><i class="bi bi-clock-history"></i> Cronjob Status:</span>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Heartbeat:</span>
                                                            <span class="{{ ($health['status'] ?? '') === 'connected' ? 'text-emerald-400' : 'text-red-400' }} font-black">
                                                                {{ $health['value'] ?? 'Unbekannt' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Letzter Boot-Versuch:</span>
                                                            @if(isset($health['last_bootstrap_diff']))
                                                                <span class="{{ $health['last_bootstrap_diff'] < 10 ? 'text-emerald-400' : 'text-amber-500' }} font-black">
                                                                    Vor {{ $health['last_bootstrap_diff'] }} Min
                                                                </span>
                                                            @else
                                                                <span class="text-red-400 font-black">Bisher nie (Cache leer)</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Herzschlag-Job (DB):</span>
                                                            @if(isset($health['heartbeat_present']) && $health['heartbeat_present'])
                                                                <span class="{{ (isset($health['heartbeat_active']) && $health['heartbeat_active']) ? 'text-emerald-400' : 'text-amber-500' }} font-bold">
                                                                    {{ (isset($health['heartbeat_active']) && $health['heartbeat_active']) ? 'Aktiv' : 'Deaktiviert' }}
                                                                </span>
                                                            @else
                                                                <span class="text-red-400 font-black">Fehlt in DB!</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Artisan Datei:</span>
                                                            @if(isset($health['artisan_file_exists']) && $health['artisan_file_exists'])
                                                                <span class="{{ (isset($health['artisan_file_readable']) && $health['artisan_file_readable']) ? 'text-emerald-400' : 'text-amber-500' }} font-bold">
                                                                    Vorhanden {{ (isset($health['artisan_file_readable']) && $health['artisan_file_readable']) ? '(Lesbar)' : '(Nicht lesbar!)' }}
                                                                </span>
                                                            @else
                                                                <span class="text-red-400 font-black">Fehlt!</span>
                                                            @endif
                                                        </div>
                                                        @if(($health['status'] ?? '') !== 'connected')
                                                            <div class="text-[9px] text-red-400 mt-1 whitespace-normal leading-tight">
                                                                {{ $health['error'] ?? 'Mittwald Cronjob läuft nicht oder hängt.' }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Globaler Boot-Fehler -->
                                                    @if(isset($health['last_exception']) && $health['last_exception'])
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-red-950/30 p-2 rounded-lg border border-red-900/50">
                                                            <span class="text-[9px] font-bold text-red-400 uppercase tracking-widest mb-1">
                                                                <i class="bi bi-exclamation-octagon-fill"></i> Globaler Boot-Fehler:
                                                            </span>
                                                            <div class="text-[9px] text-red-300 font-semibold leading-normal whitespace-normal break-words">
                                                                {{ $health['last_exception']['message'] }}
                                                            </div>
                                                            <div class="text-[8px] text-gray-400 mt-1 truncate">
                                                                In {{ basename($health['last_exception']['file']) }}:{{ $health['last_exception']['line'] }}
                                                            </div>
                                                            <div class="text-[8px] text-gray-500">
                                                                {{ \Carbon\Carbon::parse($health['last_exception']['timestamp'])->diffForHumans() }}
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- Job-Registrierungsfehler -->
                                                    @if(isset($health['job_errors']) && count($health['job_errors']) > 0)
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-amber-950/20 p-2 rounded-lg border border-amber-900/50">
                                                            <span class="text-[9px] font-bold text-amber-400 uppercase tracking-widest mb-1">
                                                                <i class="bi bi-exclamation-triangle-fill"></i> Job-Registrierungsfehler ({{ count($health['job_errors']) }}):
                                                            </span>
                                                            <div class="space-y-1.5 max-h-[150px] overflow-y-auto pr-1">
                                                                @foreach($health['job_errors'] as $jobId => $jobError)
                                                                    <div class="text-[8px] border-b border-amber-900/30 pb-1 last:border-0 last:pb-0">
                                                                        <div class="text-white font-bold truncate">Job ID: {{ $jobId }}</div>
                                                                        <div class="text-amber-300 whitespace-normal leading-tight">
                                                                            {{ $jobError['message'] }}
                                                                        </div>
                                                                        <div class="text-gray-500 text-[7px] mt-0.5">
                                                                            Gemeldet: {{ $jobError['timestamp'] }}
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- Ungültige Präfix-Befehle -->
                                                    @if(isset($health['invalid_prefix_commands']) && count($health['invalid_prefix_commands']) > 0)
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-amber-950/30 p-2 rounded-lg border border-amber-900/50">
                                                            <span class="text-[9px] font-bold text-amber-400 uppercase tracking-widest mb-1">
                                                                <i class="bi bi-exclamation-triangle-fill"></i> Präfix-Fehler ({{ count($health['invalid_prefix_commands']) }}):
                                                            </span>
                                                            <div class="text-[8.5px] text-amber-300 leading-normal whitespace-normal">
                                                                Folgende Jobs enthalten das Präfix "php artisan", was im Scheduler scheitert. Klicke unten auf "reparieren & anstoßen" zum Bereinigen:
                                                            </div>
                                                            <div class="space-y-1 mt-1 max-h-[80px] overflow-y-auto pr-1">
                                                                @foreach($health['invalid_prefix_commands'] as $invCmd)
                                                                    <div class="text-[8px] text-gray-400 border-t border-amber-900/20 pt-1">
                                                                        <b>{{ $invCmd['name'] }}:</b> <span class="text-red-400 font-mono break-all">{{ $invCmd['command'] }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- PHP CLI Versionen -->
                                                    @if(isset($health['cli_versions']) && count($health['cli_versions']) > 0)
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-900/50 p-2 rounded-lg border border-gray-800">
                                                            <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-1"><i class="bi bi-cpu"></i> PHP CLI Versionen:</span>
                                                            @foreach($health['cli_versions'] as $path => $diag)
                                                                <div class="flex justify-between gap-4 text-[9px] truncate">
                                                                    <span class="text-gray-500 shrink-0">{{ basename($path) }}:</span>
                                                                    <span class="{{ version_compare($diag['version'], '8.4.0', '>=') ? 'text-emerald-400 font-bold' : 'text-amber-500' }}">
                                                                        {{ $diag['version'] }} (proc: {{ $diag['proc_open'] ? 'JA' : 'NEIN' }})
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    <!-- Hängende Sperren -->
                                                    @if(isset($health['locked_count']))
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-red-950/20 p-2 rounded-lg border border-red-900/50">
                                                            <span class="text-[9px] font-bold text-red-400 uppercase tracking-widest mb-1"><i class="bi bi-lock"></i> Hängende Sperren:</span>
                                                            <div class="flex justify-between gap-4 text-[9px]">
                                                                <span class="text-gray-500">Sperren aktiv:</span>
                                                                <span class="{{ $health['locked_count'] > 0 ? 'text-red-400 font-bold animate-pulse' : 'text-emerald-400' }}">
                                                                    {{ $health['locked_count'] }}
                                                                </span>
                                                            </div>
                                                            @if($health['locked_count'] > 0)
                                                                <div class="text-[8px] text-red-400/80 leading-normal mt-1 whitespace-normal">
                                                                    Sperren blockieren den Cronjob. Beim manuellen Anstoßen werden sie automatisch bereinigt.
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-purple-900/20 p-2 rounded-lg border border-purple-800/50">
                                                        <span class="text-[9px] font-bold text-purple-500 uppercase tracking-widest mb-1"><i class="bi bi-shield-lock"></i> Daemon-Sperre Aktiv:</span>
                                                        <div class="text-[9px] text-purple-400/80 leading-relaxed whitespace-normal">
                                                            Der interne Scheduler blockiert automatisch Befehle wie <code>reverb:start</code> oder <code>queue:work</code>, um Endlos-Hänger im Mittwald-Docker-Container zu verhindern.
                                                        </div>
                                                    </div>

                                                    <div class="mt-2">
                                                        <button type="button" wire:click="fixSystem('scheduler')" wire:loading.attr="disabled" class="w-full px-2 py-1.5 rounded-lg border border-primary/50 bg-primary/10 hover:bg-primary/30 text-primary hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer flex items-center justify-center gap-1.5 pointer-events-auto">
                                                            <span wire:loading.remove wire:target="fixSystem('scheduler')"><i class="bi bi-play-circle"></i> Scheduler reparieren & anstoßen</span>
                                                            <span wire:loading wire:target="fixSystem('scheduler')" class="animate-pulse">Repariert...</span>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Mittwald Setup Hilfe -->
                                                     <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-900/80 p-2.5 rounded-lg border border-gray-800">
                                                         <span class="text-[9px] font-bold text-gray-300 uppercase tracking-widest mb-1.5"><i class="bi bi-info-circle-fill text-primary"></i> Mittwald Cronjob Setup:</span>
                                                         <div class="flex flex-col gap-1 text-[8.5px] text-gray-400">
                                                             <div class="flex justify-between"><span class="font-bold">Intervall:</span><span class="text-white">* * * * * (Jede Minute)</span></div>
                                                             <div class="flex justify-between"><span class="font-bold">Typ:</span><span class="text-white">Befehl ausführen</span></div>
                                                             <div class="flex justify-between"><span class="font-bold">Interpreter:</span><span class="text-white">/usr/bin/php (PHP 8.4+)</span></div>
                                                             <div class="flex justify-between gap-2"><span class="font-bold shrink-0">Datei:</span><span class="text-emerald-400 truncate">{{ base_path('artisan') }}</span></div>
                                                             <div class="flex justify-between"><span class="font-bold">Parameter:</span><span class="text-white">schedule:run</span></div>
                                                         </div>
                                                     </div>
                                                @endif

                                                @if($sKey === 'backup')
                                                    <button type="button" wire:click="fixSystem('backup')" wire:loading.attr="disabled" class="w-full mt-2 px-2 py-1.5 rounded-lg border border-purple-700 bg-purple-900/30 hover:bg-purple-800 text-purple-300 hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer flex items-center justify-center gap-1.5">
                                                        <span wire:loading.remove wire:target="fixSystem('backup')"><i class="bi bi-clock-history"></i> Backup manuell anstoßen</span>
                                                        <span wire:loading wire:target="fixSystem('backup')" class="animate-pulse">Backup läuft...</span>
                                                    </button>
                                                @endif

                                                @if($sKey === 'telephony')
                                                    @if($status === 'connected')
                                                        <div class="mt-2 text-[10px] text-emerald-400 bg-emerald-950/30 p-2 rounded-lg border border-emerald-900/50 text-left font-medium leading-relaxed">
                                                            <i class="bi bi-check-circle-fill mr-1"></i> Perfekt! Die Node.js App ist wach, der Port ist offen und das Routing funktioniert. Du kannst jetzt anrufen!
                                                        </div>
                                                    @else
                                                        <div class="mt-2 text-[10px] text-gray-300 bg-red-950/30 p-2 rounded-lg border border-red-900/50 text-left font-medium leading-relaxed">
                                                            <strong class="text-red-400 block mb-2 uppercase tracking-wider"><i class="bi bi-exclamation-triangle-fill"></i> SSH Notfall-Befehl (Copy & Paste):</strong>
                                                            <div class="bg-black/50 rounded p-2 text-gray-300 font-mono text-[9.5px] leading-relaxed cursor-text selection:bg-purple-500/30 border border-red-900/50 mb-2">
                                                                cd /html/seelenfunke-stage<br>
                                                                git reset --hard<br>
                                                                git pull<br>
                                                                cd /html/twilio-bridge<br>
                                                                cp ../seelenfunke-stage/server-twilio.js .<br>
                                                                cp ../seelenfunke-stage/.env .<br>
                                                                npm install ws dotenv wavefile<br>
                                                                kill -9 $(ps aux | grep '[n]ode' | awk '{print $2}')
                                                            </div>
                                                            <span class="text-gray-400 italic">Die Node.js App startet danach von alleine neu. Warte ca. 1-2 Minuten, bis die Anzeige grün wird!</span>
                                                        </div>
                                                    @endif
                                                @endif

                                                @if(isset($health['error']) && $health['error'])
                                                    <div class="mt-1.5 pt-1.5 border-t border-red-500/20 text-red-500 text-[10px] font-bold">{{ $health['error'] }}</div>
                                                @endif
                                                @if(isset($health['path']) && $health['path'])
                                                    <div class="mt-1.5 pt-1.5 border-t border-gray-800 text-gray-400 text-[9px] font-mono break-all">{{ $health['path'] }}</div>
                                                @endif
                                                <div class="absolute -bottom-1.5 right-4 w-3 h-3 bg-gray-950 border-b border-r border-[#C5A059]/40 rotate-45"></div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>



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

            <!-- 4. SECURITY DETAILS -->
            <div x-show="activeTab === 'security'" x-transition.opacity.duration.300ms>
                <div class="flex justify-between items-end border-b border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">Sicherheit & Abwehr</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Aktuelle Angriffe, Rate-Limits und Systembedrohungen in Echtzeit.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Stat 1: Failed Logins -->
                    <div class="bg-gray-950 border border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-3">
                        <div class="flex items-center gap-3 border-b border-gray-800/80 pb-3">
                            <x-heroicon-o-finger-print class="w-5 h-5 text-purple-500" />
                            <h3 class="text-sm font-semibold text-white uppercase tracking-widest">Login Versuche</h3>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-3xl font-black {{ $failedLogins24h > 10 ? 'text-red-500' : 'text-purple-400' }}">{{ $failedLogins24h }}</span>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-right">Fehlgeschlagen<br>(Letzte 24h)</span>
                        </div>
                    </div>

                    <!-- Stat 2: Security Errors -->
                    <div class="bg-gray-950 border border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-3">
                        <div class="flex items-center gap-3 border-b border-gray-800/80 pb-3">
                            <x-heroicon-o-shield-exclamation class="w-5 h-5 text-red-500" />
                            <h3 class="text-sm font-semibold text-white uppercase tracking-widest">System Alarme</h3>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-3xl font-black {{ $securityWarnings24h > 0 ? 'text-red-500' : 'text-emerald-500' }}">{{ $securityWarnings24h }}</span>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-right">Kritische<br>Warnungen</span>
                        </div>
                    </div>

                    <!-- Stat 3: WAF / Rate Limit Status -->
                    <div class="bg-gray-950 border border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-3">
                        <div class="flex items-center gap-3 border-b border-gray-800/80 pb-3">
                            <x-heroicon-o-lock-closed class="w-5 h-5 text-emerald-500" />
                            <h3 class="text-sm font-semibold text-white uppercase tracking-widest">Rate Limiter</h3>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-3xl font-black text-emerald-500">Aktiv</span>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-right">DDoS Schutz<br>Routing</span>
                        </div>
                    </div>
                </div>

                <!-- THREAT MONITOR -->
                <div class="bg-gray-950/40 rounded-[2rem] border border-gray-800 p-6 shadow-inner">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                            <x-heroicon-s-eye class="w-5 h-5 text-purple-500" />
                            Threat Monitor (Letzte Ereignisse)
                        </h3>
                        <button x-data="{ success: false }" 
                                x-on:click="$wire.clearSecurityLogs().then(() => { success = true; setTimeout(() => success = false, 3000) })" 
                                :class="success ? 'bg-emerald-500/20 border-emerald-500/50 text-emerald-400' : 'text-gray-400 hover:text-white border-gray-800 hover:border-gray-600 bg-gray-900'" 
                                class="text-[10px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg border transition-colors flex items-center gap-2">
                            <x-heroicon-o-trash x-show="!success" class="w-3.5 h-3.5" />
                            <x-heroicon-o-check x-show="success" class="w-3.5 h-3.5" x-cloak />
                            <span x-text="success ? 'Erfolgreich' : 'Alle leeren'"></span>
                        </button>
                    </div>
                    
                    <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                        @php
                            $recentSecurityLogs = $this->systemLogs->filter(function($log) {
                                // Nur Logs anzeigen, die den Status 'error' haben (also ungelöst sind)
                                return ($log['status'] ?? '') === 'error' && in_array(($log['type'] ?? ''), ['system', 'security']);
                            })->take(20);
                        @endphp

                        @forelse($recentSecurityLogs as $log)
                            <div class="bg-gray-900 rounded-xl p-4 border border-gray-800/80 hover:border-gray-700 transition-colors flex gap-4 items-start">
                                <div class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center {{ ($log['type'] ?? '') === 'security' ? 'bg-purple-500/10 border-purple-500/20 text-purple-500' : 'bg-red-500/10 border-red-500/20 text-red-500' }} border">
                                    @if(($log['type'] ?? '') === 'security')
                                        <x-heroicon-o-finger-print class="w-5 h-5" />
                                    @else
                                        <x-heroicon-o-exclamation-circle class="w-5 h-5" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <div class="flex justify-between items-start gap-4">
                                        <h4 class="text-sm font-bold text-gray-200 truncate">{{ $log['title'] ?? 'Unbekanntes Ereignis' }}</h4>
                                        <span class="text-[10px] font-mono text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($log['timestamp'])->format('d.m.Y H:i:s') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1.5 leading-relaxed">{{ $log['message'] ?? '' }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 flex flex-col items-center justify-center text-gray-500 text-center">
                                <x-heroicon-o-shield-check class="w-12 h-12 mb-4 text-emerald-500/50" />
                                <p class="font-bold text-sm text-gray-300">Keine Bedrohungen erkannt.</p>
                                <p class="text-xs mt-1">Dein System hat in letzter Zeit keine Sicherheitswarnungen protokolliert.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- 4. CAPACITIES (SPEICHER & PRODUKTION) -->
            <div x-show="activeTab === 'capacities'" x-transition.opacity.duration.300ms>
                <div class="flex justify-between items-end border-b border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">Kapazitäten</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Aktuelle Speicherauslastung und produzierende Shop-Gewerke.</p>
                    </div>
                </div>
                <!-- Capacities Content -->
                <div class="space-y-8">
                    <livewire:shop.master.master-shop-capacity />
                    <livewire:shop.master.master-storage-capacity />
                </div>
            </div>

            <!-- 5. PROFIT -->
            <div x-show="activeTab === 'profit'" x-transition.opacity.duration.300ms>
                <div class="flex justify-between items-end border-b border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">Gewinn-Entwicklung</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Darstellung der realen Shop-Umsätze gegenüber den Ausgaben, zur Kalkulation des Break-Even.</p>
                    </div>
                </div>
                <!-- Profit Content -->
                @include('livewire.shop.master.master-analytics-partials.profit')
            </div>

            <!-- 6. E-COMMERCE -->
            <div x-show="activeTab === 'ecommerce'" x-transition.opacity.duration.300ms>
                <div class="flex justify-between items-end border-b border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">E-Commerce Einblicke</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Detaillierte Analyse zu Warenkörben, verlassenen Checkouts und Artikel-Statistiken.</p>
                    </div>
                </div>
                <!-- E-Commerce Content -->
                @include('livewire.shop.master.master-analytics-partials.charts')
            </div>

            <!-- 7. TRAFFIC & KUNDEN -->
            <div x-show="activeTab === 'traffic'" x-transition.opacity.duration.300ms>
                <div class="flex justify-between items-end border-b border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white mb-1">Traffic & Kunden</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Web-Traffic, Kundenverhalten und Wachstumsstatistiken für optimale Performance.</p>
                    </div>
                </div>
                <!-- Traffic Content -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    @include('livewire.shop.master.master-analytics-partials.traffic')
                </div>
                <div class="mt-8 pt-8 border-t border-gray-800/80">
                    <h3 class="text-xl font-serif font-bold text-white mb-6">Kundengewinnung & Registrierungen</h3>
                    @include('livewire.shop.master.master-analytics-partials.customers')
                </div>
            </div>

        </div>
    </div>

</div>

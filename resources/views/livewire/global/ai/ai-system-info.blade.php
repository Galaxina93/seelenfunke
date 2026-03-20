<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-black/90 backdrop-blur-md p-6 sm:p-10 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.05)] border border-emerald-900/40 relative overflow-hidden mb-8 mt-8">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-server class="w-40 h-40 text-emerald-500 drop-shadow-[0_0_20px_rgba(16,185,129,1)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-black text-emerald-500 tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md font-mono">System-Info & Hosting</h1>
                <p class="text-emerald-700 mt-2 text-sm font-bold uppercase tracking-widest font-mono">Server-Metriken, Systemumgebung und KI-Hosting-Tarife.</p>
            </div>
        </div>

        <!-- System Metriken Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-3xl p-6 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Laravel Core</div>
                    <div class="text-2xl font-black text-gray-200 group-hover:text-emerald-400 transition-colors">v{{ $laravelVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-code-bracket class="w-10 h-10 text-emerald-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-3xl p-6 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">PHP Engine</div>
                    <div class="text-2xl font-black text-gray-200 group-hover:text-indigo-400 transition-colors">v{{ $phpVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-command-line class="w-10 h-10 text-indigo-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-3xl p-6 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Datenbank</div>
                    <div class="text-2xl font-black text-gray-200 group-hover:text-pink-400 transition-colors">SQLite (In-Memory)</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-circle-stack class="w-10 h-10 text-pink-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-emerald-900/50 shadow-[inset_0_0_20px_rgba(16,185,129,0.1)] rounded-3xl p-6 font-mono relative overflow-hidden group hover:border-emerald-500/80 transition-all cursor-pointer flex justify-between items-center">
                <div>
                    <div class="text-emerald-700 text-[10px] font-black uppercase tracking-widest mb-1">KI Hosting Partner</div>
                    <div class="text-2xl font-black text-emerald-400 drop-shadow-[0_0_5px_rgba(16,185,129,0.4)]">Mittwald</div>
                </div>
                <div class="opacity-40 transition-opacity group-hover:opacity-80 group-hover:animate-pulse">
                    <x-heroicon-o-shield-check class="w-10 h-10 text-emerald-500" />
                </div>
            </div>
        </div>

        <!-- Tariff Matrix Header -->
        <div class="text-center mb-10 pt-4">
            <h2 class="text-2xl sm:text-3xl font-black text-gray-200 tracking-widest uppercase font-mono mb-2">KI-Hosting Tarife</h2>
            <p class="text-gray-500 font-mono text-xs uppercase tracking-widest">DSGVO-konformes Hosting in Deutschland (Mittwald API)</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 items-center">

            <!-- Starter -->
            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-3xl p-8 font-mono flex flex-col h-full transition-all hover:border-gray-500 relative">
                <h3 class="text-xl font-black text-gray-300 uppercase tracking-widest mb-2">Starter</h3>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-3xl font-black text-white">9 €</span>
                </div>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mb-6">pro Monat zzgl. USt.*</p>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-8 border-b border-gray-800 pb-4 h-8">Perfekt zum Testen und Experimentieren</p>

                <ul class="space-y-4 mb-4 flex-1 text-xs text-gray-400 tracking-wider">
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> 5 Mio. Tokens/Monat</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> 30 Requests/Minute</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> 5 parallele Requests</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting in Deutschland</li>
                </ul>
            </div>

            <!-- PRO (HIGHLIGHTED GOLDEN/PERU) -->
            <div class="bg-gray-950 backdrop-blur-xl border-2 border-[peru] shadow-[0_0_30px_rgba(205,133,63,0.15),inset_0_0_20px_rgba(0,0,0,0.8)] rounded-3xl p-8 font-mono flex flex-col h-full relative transform xl:scale-105 z-10 w-full">

                <h3 class="text-xl font-black text-[peru] uppercase tracking-widest mb-2 mt-2 drop-shadow-[0_0_5px_rgba(205,133,63,0.5)]">Pro</h3>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-4xl font-black text-white">39 €</span>
                </div>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mb-6">pro Monat zzgl. USt.*</p>
                <p class="text-[10px] text-[peru] font-bold uppercase tracking-widest mb-8 border-b border-gray-800 pb-4 h-8">Für Agenturen und Produktiveinsatz</p>

                <ul class="space-y-4 mb-4 flex-1 text-xs text-gray-300 tracking-wider">
                    <li class="flex items-center gap-3"><x-heroicon-s-check-circle class="w-4 h-4 text-[peru] shrink-0" /> <span class="font-bold text-white drop-shadow-[0_0_3px_rgba(255,255,255,0.4)]">75 Mio. Tokens/Monat</span></li>
                    <li class="flex items-start gap-3"><x-heroicon-s-check-circle class="w-4 h-4 text-[peru] shrink-0 mt-0.5" /> 60 Requests/Minute</li>
                    <li class="flex items-start gap-3"><x-heroicon-s-check-circle class="w-4 h-4 text-[peru] shrink-0 mt-0.5" /> 10 parallele Requests</li>
                    <li class="flex items-start gap-3"><x-heroicon-s-check-circle class="w-4 h-4 text-[peru] shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-3"><x-heroicon-s-check-circle class="w-4 h-4 text-[peru] shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-3"><x-heroicon-s-check-circle class="w-4 h-4 text-[peru] shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-3"><x-heroicon-s-check-circle class="w-4 h-4 text-[peru] shrink-0 mt-0.5" /> DSGVO-konformes Hosting in Deutschland</li>
                    <li class="flex items-center gap-3 font-bold text-[peru] mt-6 pt-6 border-t border-[peru]/20"><x-heroicon-s-star class="w-4 h-4 text-[peru] shrink-0" /> Perfekt für Production</li>
                </ul>
            </div>

            <!-- Business -->
            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-3xl p-8 font-mono flex flex-col h-full transition-all hover:border-gray-500 relative">
                <h3 class="text-xl font-black text-gray-300 uppercase tracking-widest mb-2">Business</h3>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-3xl font-black text-white">149 €</span>
                </div>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mb-6">pro Monat zzgl. USt.*</p>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-8 border-b border-gray-800 pb-4 h-8">Für größere Teams und Projekte</p>

                <ul class="space-y-4 mb-4 flex-1 text-xs text-gray-400 tracking-wider">
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> 300 Mio. Tokens/Monat</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> 150 Requests/Minute</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> 20 parallele Requests</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting in Deutschland</li>
                </ul>
            </div>

            <!-- Enterprise -->
            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-3xl p-8 font-mono flex flex-col h-full transition-all hover:border-purple-500/50 relative overflow-hidden group">
                <div class="absolute top-0 right-0 bg-gray-900/80 backdrop-blur-sm text-gray-500 group-hover:text-purple-400 transition-colors text-[8px] font-black uppercase tracking-widest px-3 py-1.5 rounded-bl-xl border-b border-l border-gray-800/60 z-10">ENTERPRISE</div>
                <h3 class="text-xl font-black text-gray-300 group-hover:text-purple-400 transition-colors uppercase tracking-widest mb-2 mt-4 relative z-10">Dedicated</h3>
                <div class="flex items-baseline gap-2 mb-2 mt-1 relative z-10">
                    <span class="text-3xl font-black text-white">999 €</span>
                </div>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mb-6 relative z-10">pro Monat zzgl. USt.*</p>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-8 border-b border-gray-800 pb-4 h-8 relative z-10">Eigene GPU-Ressourcen</p>

                <ul class="space-y-4 mb-4 flex-1 text-xs text-gray-400 tracking-wider relative z-10">
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-purple-500 shrink-0 mt-0.5" /> Milliarden Tokens/Monat</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-purple-500 shrink-0 mt-0.5" /> Eigene RTX PRO 6000</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-purple-500 shrink-0 mt-0.5" /> Custom-Deployments möglich</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-purple-500 shrink-0 mt-0.5" /> Technischer Ansprechpartner</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-3"><x-heroicon-o-check class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting in Deutschland</li>
                </ul>
            </div>

        </div>
    </div>
</div>

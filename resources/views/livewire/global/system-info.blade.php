<div>
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-black/90 backdrop-blur-md p-4 sm:p-6 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.05)] border border-emerald-900/40 relative overflow-hidden my-4">
            <div class="absolute top-0 right-0 p-4 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-server class="w-24 h-24 text-emerald-500 drop-shadow-[0_0_20px_rgba(16,185,129,1)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-2xl sm:text-3xl font-black text-emerald-500 tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md font-mono">System-Info & Hosting</h1>
                <p class="text-emerald-700 mt-1 text-xs font-bold uppercase tracking-widest font-mono">Server-Metriken, Systemumgebung und KI-Hosting-Tarife.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Laravel Core</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-emerald-400 transition-colors">v{{ $laravelVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-code-bracket class="w-8 h-8 text-emerald-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">PHP Engine</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-indigo-400 transition-colors">v{{ $phpVersion }}</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-command-line class="w-8 h-8 text-indigo-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-4 font-mono relative overflow-hidden group flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Datenbank</div>
                    <div class="text-xl font-black text-gray-200 group-hover:text-pink-400 transition-colors">SQLite (In-Memory)</div>
                </div>
                <div class="opacity-30 group-hover:opacity-60 transition-opacity">
                    <x-heroicon-o-circle-stack class="w-8 h-8 text-pink-500" />
                </div>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-emerald-900/50 shadow-[inset_0_0_20px_rgba(16,185,129,0.1)] rounded-2xl p-4 font-mono relative overflow-hidden group hover:border-emerald-500/80 transition-all cursor-pointer flex justify-between items-center">
                <div>
                    <div class="text-emerald-700 text-[10px] font-black uppercase tracking-widest mb-1">KI Hosting Partner</div>
                    <div class="text-xl font-black text-emerald-400 drop-shadow-[0_0_5px_rgba(16,185,129,0.4)]">Mittwald</div>
                </div>
                <div class="opacity-40 transition-opacity group-hover:opacity-80 group-hover:animate-pulse">
                    <x-heroicon-o-shield-check class="w-8 h-8 text-emerald-500" />
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <h2 class="text-xl sm:text-2xl font-black text-gray-200 tracking-widest uppercase font-mono mb-1">KI-Hosting Tarife</h2>
            <p class="text-gray-500 font-mono text-[10px] uppercase tracking-widest">DSGVO-konformes Hosting in Deutschland (Mittwald API)</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-stretch">

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full transition-all hover:border-gray-500 relative">
                <h3 class="text-lg font-black text-gray-300 uppercase tracking-widest mb-1">Starter</h3>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-2xl font-black text-white">9 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2">Perfekt zum Testen und Experimentieren</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-400 tracking-wider">
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 5 Mio. Tokens/Monat</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 30 Requests/Minute</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 5 parallele Requests</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                </ul>
            </div>

            <div class="bg-gray-950 backdrop-blur-xl border-2 border-[peru] shadow-[0_0_30px_rgba(205,133,63,0.15),inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full relative transform xl:scale-105 z-10 w-full">
                <h3 class="text-lg font-black text-[peru] uppercase tracking-widest mb-1 drop-shadow-[0_0_5px_rgba(205,133,63,0.5)]">Pro</h3>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-3xl font-black text-white">39 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-[peru] font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2">Für Agenturen und Produktiveinsatz</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-300 tracking-wider">
                    <li class="flex items-center gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0" /> <span class="font-bold text-white drop-shadow-[0_0_3px_rgba(255,255,255,0.4)]">75 Mio. Tokens/Monat</span></li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> 60 Requests/Minute</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> 10 parallele Requests</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-s-check-circle class="w-3.5 h-3.5 text-[peru] shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                    <li class="flex items-center gap-2 font-bold text-[peru] mt-3 pt-3 border-t border-[peru]/20"><x-heroicon-s-star class="w-3.5 h-3.5 text-[peru] shrink-0" /> Perfekt für Production</li>
                </ul>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full transition-all hover:border-gray-500 relative">
                <h3 class="text-lg font-black text-gray-300 uppercase tracking-widest mb-1">Business</h3>
                <div class="flex items-baseline gap-2 mb-1">
                    <span class="text-2xl font-black text-white">149 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2">Für größere Teams und Projekte</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-400 tracking-wider">
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 300 Mio. Tokens/Monat</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 150 Requests/Minute</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> 20 parallele Requests</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Alle Modelle verfügbar</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> Unbegrenzte API-Keys</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                </ul>
            </div>

            <div class="bg-black/80 backdrop-blur-xl border border-gray-800/60 shadow-[inset_0_0_20px_rgba(0,0,0,0.8)] rounded-2xl p-5 font-mono flex flex-col h-full transition-all hover:border-purple-500/50 relative overflow-hidden group">
                <div class="absolute top-0 right-0 bg-gray-900/80 backdrop-blur-sm text-gray-500 group-hover:text-purple-400 transition-colors text-[7px] font-black uppercase tracking-widest px-2 py-1 rounded-bl-xl border-b border-l border-gray-800/60 z-10">ENTERPRISE</div>
                <h3 class="text-lg font-black text-gray-300 group-hover:text-purple-400 transition-colors uppercase tracking-widest mb-1 mt-2 relative z-10">Dedicated</h3>
                <div class="flex items-baseline gap-2 mb-1 relative z-10">
                    <span class="text-2xl font-black text-white">999 €</span>
                </div>
                <p class="text-[9px] text-gray-500 uppercase tracking-widest mb-2 relative z-10">pro Monat zzgl. USt.*</p>
                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-3 border-b border-gray-800 pb-2 relative z-10">Eigene GPU-Ressourcen</p>

                <ul class="space-y-2 mb-2 flex-1 text-[11px] text-gray-400 tracking-wider relative z-10">
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Milliarden Tokens/Monat</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Eigene RTX PRO 6000</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Custom-Deployments</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-purple-500 shrink-0 mt-0.5" /> Technischer Ansprechpartner</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> OpenAI-kompatible API</li>
                    <li class="flex items-start gap-2"><x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" /> DSGVO-konformes Hosting</li>
                </ul>
            </div>

        </div>
    </div>
</div>

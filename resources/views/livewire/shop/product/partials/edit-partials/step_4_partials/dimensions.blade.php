<div class="bg-gray-950 p-6 rounded-2xl border border-gray-800 shadow-inner">
    <div class="flex items-center justify-between mb-6 border-b border-gray-800 pb-4">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
            </svg>
            <div>
                <h4 class="text-sm font-black text-white uppercase tracking-widest">xTool Laser-Parameter</h4>
                <p class="text-[10px] text-gray-500 mt-1">Physische Millimeter-Maße für den fehlerfreien SVG-Export.</p>
            </div>
        </div>
        <span class="px-2.5 py-1 text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg" x-text="configSettings.overlay_type === 'cylinder' ? 'Rotary (Rund)' : 'Flachbett'"></span>
    </div>

    {{-- MODUS: ZYLINDER / RUND --}}
    <div x-show="configSettings.overlay_type === 'cylinder'" x-transition class="space-y-6">
        {{-- KERN-MAßE --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Ø Arbeitsbereich Oben</label>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_d_top" step="0.1" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all shadow-inner" placeholder="z.B. 80">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-600">mm</span>
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Ø Arbeitsbereich Unten</label>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_d_bottom" step="0.1" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all shadow-inner" placeholder="z.B. 60">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-600">mm</span>
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-gray-400">Reine Gravierhöhe</label>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_height" step="0.1" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 outline-none transition-all shadow-inner" placeholder="z.B. 150">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-600">mm</span>
                </div>
            </div>
        </div>

        {{-- SPERRZONEN (Abstände) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 border-t border-gray-800 pt-6">
            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-amber-400">Sperrzone Oben (Trinkrand)</label>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_offset_top" step="0.1" class="w-full bg-gray-900 border border-gray-700 focus:border-amber-500 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-amber-500/50 outline-none transition-all shadow-inner" placeholder="z.B. 30">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-600">mm</span>
                </div>
                <p class="text-[9px] text-gray-500">Abstand vom oberen Glasrand bis zum Beginn der Gravur.</p>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-amber-400">Sperrzone Unten (Boden)</label>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_offset_bottom" step="0.1" class="w-full bg-gray-900 border border-gray-700 focus:border-amber-500 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-amber-500/50 outline-none transition-all shadow-inner" placeholder="z.B. 40">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-600">mm</span>
                </div>
                <p class="text-[9px] text-gray-500">Abstand vom Boden bis zum Ende der Gravur (Bereich der Rollen).</p>
            </div>
        </div>

        {{-- INFOGRAFIK --}}
        <div class="mt-8 bg-gray-900/50 border border-gray-800 rounded-2xl p-6 flex flex-col lg:flex-row gap-8 items-center shadow-inner">
            <div class="relative w-48 shrink-0">
                <svg viewBox="0 0 200 300" class="w-full h-auto drop-shadow-2xl font-sans" xmlns="http://www.w3.org/2000/svg">
                    <path d="M40 20 L160 20 L140 280 L60 280 Z" fill="rgba(255,255,255,0.05)" stroke="#4b5563" stroke-width="2"/>
                    <ellipse cx="100" cy="20" rx="60" ry="10" fill="none" stroke="#4b5563" stroke-width="2"/>
                    <ellipse cx="100" cy="280" rx="40" ry="8" fill="none" stroke="#4b5563" stroke-width="2"/>
                    <path d="M48 80 L152 80 L132 230 L68 230 Z" fill="rgba(16, 185, 129, 0.2)" stroke="#10b981" stroke-width="2" stroke-dasharray="4"/>
                    <ellipse cx="100" cy="80" rx="52" ry="8" fill="rgba(16, 185, 129, 0.1)" stroke="#10b981" stroke-width="2"/>
                    <ellipse cx="100" cy="230" rx="32" ry="6" fill="rgba(16, 185, 129, 0.1)" stroke="#10b981" stroke-width="2"/>
                    <line x1="100" y1="20" x2="100" y2="80" stroke="#fbbf24" stroke-width="2" stroke-dasharray="2" marker-start="url(#arrowYellow)" marker-end="url(#arrowYellow)"/>
                    <text x="105" y="55" fill="#fbbf24" font-size="9" font-weight="bold">SPERRZONE</text>
                    <line x1="100" y1="230" x2="100" y2="280" stroke="#fbbf24" stroke-width="2" stroke-dasharray="2" marker-start="url(#arrowYellow)" marker-end="url(#arrowYellow)"/>
                    <text x="105" y="260" fill="#fbbf24" font-size="9" font-weight="bold">SPERRZONE</text>
                    <line x1="48" y1="80" x2="10" y2="80" stroke="#f87171" stroke-width="1.5"/>
                    <line x1="152" y1="80" x2="190" y2="80" stroke="#f87171" stroke-width="1.5"/>
                    <line x1="48" y1="70" x2="152" y2="70" stroke="#f87171" stroke-width="1.5" marker-start="url(#arrow)" marker-end="url(#arrow)"/>
                    <text x="100" y="62" fill="#f87171" font-size="10" font-weight="bold" text-anchor="middle">Ø OBEN</text>
                    <line x1="68" y1="230" x2="10" y2="230" stroke="#f87171" stroke-width="1.5"/>
                    <line x1="132" y1="230" x2="190" y2="230" stroke="#f87171" stroke-width="1.5"/>
                    <line x1="68" y1="240" x2="132" y2="240" stroke="#f87171" stroke-width="1.5" marker-start="url(#arrow)" marker-end="url(#arrow)"/>
                    <text x="100" y="252" fill="#f87171" font-size="10" font-weight="bold" text-anchor="middle">Ø UNTEN</text>
                    <line x1="20" y1="80" x2="20" y2="230" stroke="#60a5fa" stroke-width="2" marker-start="url(#arrowBlue)" marker-end="url(#arrowBlue)"/>
                    <text x="12" y="155" fill="#60a5fa" font-size="10" font-weight="bold" transform="rotate(-90 12 155)" text-anchor="middle">GRAVIERHÖHE</text>
                    <defs>
                        <marker id="arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#f87171" /></marker>
                        <marker id="arrowBlue" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#60a5fa" /></marker>
                        <marker id="arrowYellow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#fbbf24" /></marker>
                    </defs>
                </svg>
            </div>
            <div class="flex-1 space-y-4">
                <h5 class="text-sm font-bold text-white uppercase tracking-widest flex items-center gap-2">So stellst du den Konfigurator perfekt ein:</h5>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3"><span class="w-5 h-5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">1</span><p class="text-[11px] text-gray-400 leading-relaxed"><strong class="text-amber-300">Sperrzonen:</strong> Miss am Glas, wie viele Millimeter oben (Trinkrand) und unten (Rollen-Einspannung) nicht graviert werden können.</p></li>
                    <li class="flex items-start gap-3"><span class="w-5 h-5 rounded bg-red-500/10 border border-red-500/30 text-red-400 flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">2</span><p class="text-[11px] text-gray-400 leading-relaxed"><strong class="text-red-300">Durchmesser:</strong> Miss den Durchmesser exakt an den Stellen, wo der freie Arbeitsbereich beginnt und endet.</p></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- MODUS: FLACHBETT (Ebene) --}}
    <div x-show="configSettings.overlay_type === 'plane'" x-transition style="display: none;" class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-blue-400">Gravier-Breite (X)</label>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_plane_width" step="0.1" class="w-full bg-gray-900 border border-gray-700 focus:border-blue-500 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-blue-500/50 outline-none transition-all shadow-inner" placeholder="z.B. 50">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-600">mm</span>
                </div>
                <p class="text-[9px] text-gray-500">Physikalische Breite des 100% Bereiches.</p>
            </div>

            <div class="space-y-2">
                <label class="text-[10px] font-black uppercase tracking-widest text-blue-400">Gravier-Höhe (Y)</label>
                <div class="relative">
                    <input type="number" x-model.number="configSettings.xtool_plane_height" step="0.1" class="w-full bg-gray-900 border border-gray-700 focus:border-blue-500 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-blue-500/50 outline-none transition-all shadow-inner" placeholder="z.B. 50">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-600">mm</span>
                </div>
                <p class="text-[9px] text-gray-500">Physikalische Höhe des 100% Bereiches.</p>
            </div>
        </div>

        <div class="mt-4 p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl flex gap-4 items-start">
            <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">Flache Gravur</p>
                <p class="text-[10px] text-gray-400 leading-relaxed">
                    Da es sich um ein flaches Objekt handelt (z.B. Schlüsselanhänger, Holzbrett), wird kein Zylinder berechnet. Gib hier exakt die Breite und Höhe in Millimetern an, die dem voll aufgezogenen grünen Arbeitsbereich (100%) entsprechen. Das Skript erzeugt daraus eine maßstabsgetreue 1:1 SVG für den xTool.
                </p>
            </div>
        </div>
    </div>
</div>

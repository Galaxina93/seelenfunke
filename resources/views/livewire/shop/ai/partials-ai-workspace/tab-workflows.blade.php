<!-- WORKFLOWS TAB CONTENT -->
<div wire:key="tab-workflows" :class="{'hidden': activeTab !== 'workflows'}" class="flex-1 shrink-0 rounded-2xl border border-[var(--theme-color-50)] bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-[0_0_30px_var(--theme-color-20)] h-full w-full p-6">
    
    <div class="flex justify-between items-center border-b border-gray-800 pb-3 mb-6 shrink-0">
        <h2 class="text-xl md:text-2xl font-black text-[var(--theme-color)] uppercase tracking-widest flex items-center gap-3">
            <x-heroicon-o-queue-list class="w-7 h-7" /> Aktive Workflows (SOPs)
        </h2>
        <div class="text-xs text-gray-400 font-mono hidden sm:block">Übersicht der definierten Agenten-Abläufe</div>
    </div>

    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        
        <!-- Workflow 1: KI-Agentenwechsel -->
        <div class="bg-black/60 border border-[var(--theme-color-30)] hover:border-[var(--theme-color-60)] transition-colors rounded-xl overflow-hidden flex flex-col shadow-lg shadow-[var(--theme-color-10)]">
            <div class="bg-gradient-to-r from-gray-900 to-black p-4 border-b border-[var(--theme-color-20)] flex items-center gap-3">
                <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] p-2 rounded-lg">
                    <x-heroicon-o-users class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="font-bold text-gray-100 text-lg uppercase tracking-wider">KI-Agentenwechsel</h3>
                    <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-mono mt-0.5">Globaler Workflow (Alle Agenten)</div>
                </div>
            </div>
            
            <div class="p-5 flex-1 text-sm text-gray-300 font-mono">
                <div class="mb-4 text-gray-400 border-b border-gray-800 pb-3">
                    Dieser Workflow definiert den reibungslosen Wechsel zwischen den KI-Spezialisten, wenn ein Nutzer explizit nach einem anderen Agenten verlangt.
                </div>
                
                <div class="space-y-4 relative before:absolute before:inset-0 before:ml-3 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-[var(--theme-color)] before:to-gray-800">
                    
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full border-2 border-[var(--theme-color)] bg-black text-[var(--theme-color)] font-bold text-xs shrink-0 z-10 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-[0_0_10px_var(--theme-color-50)]">1</div>
                        <div class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.5rem)] p-3 rounded bg-gray-900/80 border border-[var(--theme-color-20)] shadow-md">
                            <div class="font-bold text-[var(--theme-color)] text-xs mb-1">Erkennung</div>
                            <div class="text-xs text-gray-400">Absicht erkennen (z.B. "Ich möchte mit Marketi sprechen")</div>
                        </div>
                    </div>

                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full border-2 border-[var(--theme-color-50)] bg-black text-[var(--theme-color-80)] font-bold text-xs shrink-0 z-10 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2">2</div>
                        <div class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.5rem)] p-3 rounded bg-gray-800/50 border border-gray-700">
                            <div class="font-bold text-gray-300 text-xs mb-1">Überprüfung</div>
                            <div class="text-xs text-gray-400">Existenz des Agenten im System-Register prüfen</div>
                        </div>
                    </div>

                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full border-2 border-[var(--theme-color-50)] bg-black text-[var(--theme-color-80)] font-bold text-xs shrink-0 z-10 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2">3</div>
                        <div class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.5rem)] p-3 rounded bg-gray-800/50 border border-gray-700">
                            <div class="font-bold text-gray-300 text-xs mb-1">Ausführung</div>
                            <div class="text-xs text-gray-400">Verabschiedung & Aufruf von <span class="text-[var(--theme-color)]">system_switch_agent</span></div>
                        </div>
                    </div>

                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full border-2 border-[var(--theme-color-50)] bg-black text-red-500 font-bold text-xs shrink-0 z-10 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2">!</div>
                        <div class="w-[calc(100%-2.5rem)] md:w-[calc(50%-1.5rem)] p-3 rounded bg-red-900/20 border border-red-900/50">
                            <div class="font-bold text-red-400 text-xs mb-1">Fehlerbehandlung</div>
                            <div class="text-xs text-gray-400">Falls Agent nicht existiert: Alternativhilfe anbieten</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Workflow 2: Urlaubsplanung -->
        <div class="bg-black/60 border border-[var(--theme-color-30)] hover:border-[var(--theme-color-60)] transition-colors rounded-xl overflow-hidden flex flex-col shadow-lg shadow-[var(--theme-color-10)]">
            <div class="bg-gradient-to-r from-gray-900 to-black p-4 border-b border-[var(--theme-color-20)] flex items-center gap-3">
                <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] p-2 rounded-lg">
                    <x-heroicon-o-globe-alt class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="font-bold text-gray-100 text-lg uppercase tracking-wider">Urlaubsplanung</h3>
                    <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-mono mt-0.5">Spezifisch: Mapi (Leiter Planung)</div>
                </div>
            </div>
            
            <div class="p-5 flex-1 text-sm text-gray-300 font-mono">
                <div class="mb-4 text-gray-400 border-b border-gray-800 pb-3">
                    Visueller, datengetriebener End-to-End Workflow zur Erstellung von dynamischen Reiseprofilen inklusive News, 3D-Karte und PDF-Mail.
                </div>
                
                <div class="flex flex-col gap-3">
                    <div class="bg-gray-900/80 border border-[var(--theme-color-20)] p-3 rounded flex items-center gap-3">
                        <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] rounded p-1.5"><x-heroicon-o-map class="w-4 h-4"/></div>
                        <div>
                            <div class="font-bold text-gray-200 text-xs">1. 3D-Kartenflug</div>
                            <div class="text-[10px] text-gray-500">Navigation der virtuellen Map an den Zielort</div>
                        </div>
                    </div>

                    <div class="bg-gray-900/80 border border-[var(--theme-color-20)] p-3 rounded flex items-center gap-3">
                        <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] rounded p-1.5"><x-heroicon-o-newspaper class="w-4 h-4"/></div>
                        <div>
                            <div class="font-bold text-gray-200 text-xs">2. Live-News abrufen</div>
                            <div class="text-[10px] text-gray-500">Einblenden von ortsspezifischen Nachrichten</div>
                        </div>
                    </div>

                    <div class="bg-gray-900/80 border border-[var(--theme-color-20)] p-3 rounded flex items-center gap-3">
                        <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] rounded p-1.5"><x-heroicon-o-document-text class="w-4 h-4"/></div>
                        <div>
                            <div class="font-bold text-gray-200 text-xs">3. Reiseroute & Packliste</div>
                            <div class="text-[10px] text-gray-500">Zusammenstellen der detaillierten Reiseinfos</div>
                        </div>
                    </div>

                    <div class="bg-gray-900/80 border border-indigo-900/50 p-3 rounded flex items-center gap-3">
                        <div class="bg-indigo-900/30 text-indigo-400 rounded p-1.5"><x-heroicon-o-arrow-down-tray class="w-4 h-4"/></div>
                        <div>
                            <div class="font-bold text-gray-200 text-xs">4. PDF-Generierung</div>
                            <div class="text-[10px] text-gray-500">Erstellung des Urlaubsplans über holiday_generate_pdf_plan</div>
                        </div>
                    </div>

                    <div class="bg-gray-900/80 border border-emerald-900/50 p-3 rounded flex items-center gap-3 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/10 to-transparent pointer-events-none"></div>
                        <div class="bg-emerald-900/30 text-emerald-400 rounded p-1.5"><x-heroicon-o-paper-airplane class="w-4 h-4"/></div>
                        <div>
                            <div class="font-bold text-gray-200 text-xs">5. E-Mail Versand</div>
                            <div class="text-[10px] text-gray-500">Vollautomatischer Versand des PDFs an den Nutzer</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

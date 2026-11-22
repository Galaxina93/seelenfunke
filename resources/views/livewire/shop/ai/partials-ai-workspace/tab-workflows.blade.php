<!-- WORKFLOWS TAB CONTENT -->
<div wire:key="tab-workflows" :class="{'hidden': activeTab !== 'workflows'}" class="flex-1 shrink-0 rounded-2xl border border-gray-200 dark:border-[var(--theme-color-50)] bg-white dark:bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-sm h-full w-full p-6">

    <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 pb-3 mb-6 shrink-0">
        <h2 class="text-xl md:text-2xl font-black text-[var(--theme-color)] uppercase tracking-widest flex items-center gap-3">
            <x-heroicon-o-queue-list class="w-7 h-7" /> Aktive Workflows (SOPs)
        </h2>
        <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500 text-[9px] font-bold flex items-center justify-center cursor-help" title="Standardisierte Abläufe der Seelenfunke KI-Agenten im Live- und Hintergrundbetrieb">!</span>
    </div>

    <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">

        <!-- Workflow 1: Dynamischer Skill-Pool & Agenten-Routing -->
        <div class="bg-gray-50/80 dark:bg-black/60 border border-gray-200 dark:border-[var(--theme-color-30)] hover:border-gray-300 dark:hover:border-[var(--theme-color-60)] transition-colors rounded-xl overflow-hidden flex flex-col shadow-sm">
            <div class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-900 dark:to-black p-4 border-b border-gray-200 dark:border-[var(--theme-color-20)] flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] p-2 rounded-lg">
                        <x-heroicon-o-cpu-chip class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base uppercase tracking-wider">Dynamischer Skill-Pool</h3>
                        <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-mono mt-0.5">Funkira &amp; Falcone</div>
                    </div>
                </div>
                <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500 text-[9px] font-bold flex items-center justify-center cursor-help shrink-0" title="Lädt benötigte Fachwerkzeuge bei Bedarf über funkira_equip_skill_pool nach, statt alle Tools dauerhaft in den Kontext zu laden">!</span>
            </div>

            <div class="p-4 flex flex-col gap-2.5 text-xs font-mono">
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">1</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Wissensabruf</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Abfrage der Wissensdatenbank via <span class="text-[var(--theme-color)]">brain_search</span></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">2</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Pool-Aktivierung</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Nachladen des Fach-Pools via <span class="text-[var(--theme-color)]">funkira_equip_skill_pool</span></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-center shrink-0">3</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Ausführung &amp; Rückmeldung</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Direkter Werkzeugaufruf inkl. Telemetrie in <span class="text-[var(--theme-color)]">AiToolUsage</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow 2: E-Mail-Trichter & Beleg-Erkennung -->
        <div class="bg-gray-50/80 dark:bg-black/60 border border-gray-200 dark:border-[var(--theme-color-30)] hover:border-gray-300 dark:hover:border-[var(--theme-color-60)] transition-colors rounded-xl overflow-hidden flex flex-col shadow-sm">
            <div class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-900 dark:to-black p-4 border-b border-gray-200 dark:border-[var(--theme-color-20)] flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] p-2 rounded-lg">
                        <x-heroicon-o-inbox-arrow-down class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base uppercase tracking-wider">Mail- &amp; Beleg-Trichter</h3>
                        <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-mono mt-0.5">Funkira &amp; Buchi</div>
                    </div>
                </div>
                <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500 text-[9px] font-bold flex items-center justify-center cursor-help shrink-0" title="Automatische Vorfilterung eingehender Mails und strukturierte Extraktion von Belegen mit Prompt-Injection-Schutz">!</span>
            </div>

            <div class="p-4 flex flex-col gap-2.5 text-xs font-mono">
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">1</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Untrusted-Kapselung</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Schutz vor Prompt-Injection durch <span class="text-[var(--theme-color)]">UNTRUSTED_BODY</span>-Grenzen</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">2</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Klassifizierung &amp; Beleg-Scan</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Sortierung via <span class="text-[var(--theme-color)]">MailAiSortService</span> &amp; OCR via <span class="text-[var(--theme-color)]">ReceiptAiScannerService</span></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-center shrink-0">3</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Kommandozentrale</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Übergabe wichtiger Vorgänge an <span class="text-[var(--theme-color)]">/admin/dashboard</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow 3: Air-Gapped Kunden-Support -->
        <div class="bg-gray-50/80 dark:bg-black/60 border border-gray-200 dark:border-[var(--theme-color-30)] hover:border-gray-300 dark:hover:border-[var(--theme-color-60)] transition-colors rounded-xl overflow-hidden flex flex-col shadow-sm">
            <div class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-900 dark:to-black p-4 border-b border-gray-200 dark:border-[var(--theme-color-20)] flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] p-2 rounded-lg">
                        <x-heroicon-o-shield-check class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base uppercase tracking-wider">Air-Gapped Kundenchat</h3>
                        <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-mono mt-0.5">Funki (Shop-Frontend)</div>
                    </div>
                </div>
                <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500 text-[9px] font-bold flex items-center justify-center cursor-help shrink-0" title="Strikte Sandbox-Isolation für Kundenanfragen im Shop ohne Zugriff auf Admin- oder Finanzwerkzeuge">!</span>
            </div>

            <div class="p-4 flex flex-col gap-2.5 text-xs font-mono">
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">1</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Sandbox-Prüfung</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Harte Sperre interner Admin-Tools in <span class="text-[var(--theme-color)]">AIFunctionsRegistry</span></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">2</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Produkt- &amp; Lieferauskunft</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Faktenbasierte Beratung anhand freigegebener Shop-Daten</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-center shrink-0">3</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Ticket-Eskalation</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Übergabe offener Sonderfälle an <span class="text-[var(--theme-color)]">/admin/support-chats</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow 4: Globale Recherche & Reiseplanung (Globi) -->
        <div class="bg-gray-50/80 dark:bg-black/60 border border-gray-200 dark:border-[var(--theme-color-30)] hover:border-gray-300 dark:hover:border-[var(--theme-color-60)] transition-colors rounded-xl overflow-hidden flex flex-col shadow-sm">
            <div class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-900 dark:to-black p-4 border-b border-gray-200 dark:border-[var(--theme-color-20)] flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="bg-[var(--theme-color-20)] text-[var(--theme-color)] p-2 rounded-lg">
                        <x-heroicon-o-globe-alt class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base uppercase tracking-wider">Standort- &amp; Reiseplanung</h3>
                        <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-mono mt-0.5">Globi (Agenten-Pool)</div>
                    </div>
                </div>
                <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500 text-[9px] font-bold flex items-center justify-center cursor-help shrink-0" title="Datengetriebener Recherche- und Reiseplanungs-Ablauf inkl. Kartensteuerung, News-Abruf und PDF-Versand">!</span>
            </div>

            <div class="p-4 flex flex-col gap-2.5 text-xs font-mono">
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">1</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Kartenflug &amp; Live-News</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Zielort-Navigation und ortsspezifischer Nachrichtenabruf</div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-15)] text-[var(--theme-color)] font-bold flex items-center justify-center shrink-0">2</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">Route &amp; PDF-Plan</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Generierung des Plans über <span class="text-[var(--theme-color)]">holiday_generate_pdf_plan</span></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 p-3 rounded-lg flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold flex items-center justify-center shrink-0">3</span>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-gray-100">E-Mail-Zustellung</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">Direktversand des fertigen PDF-Dokuments an Alina</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

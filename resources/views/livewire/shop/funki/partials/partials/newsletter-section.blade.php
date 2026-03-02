<section class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-10 relative overflow-hidden transition-all duration-500 w-full">
    {{-- Dekorativer Glow-Streifen links --}}
    <div class="absolute top-0 left-0 w-1.5 h-full bg-gradient-to-b from-blue-500 to-indigo-600 opacity-60"></div>

    {{-- Header Bereich --}}
    <div class="flex flex-col md:flex-row justify-between items-start mb-10 gap-6 relative z-10">
        <div>
            <h3 class="text-2xl font-serif font-bold text-white tracking-tight flex items-center gap-3">
                <i class="solar-letter-opened-bold-duotone text-blue-400 text-2xl"></i>
                Newsletter Automatisierung
            </h3>
            <div class="flex items-center gap-3 mt-2">
                <span class="text-[10px] font-mono text-gray-500 bg-black/40 px-2 py-0.5 rounded border border-gray-800 uppercase tracking-tighter">
                    COMMAND: FUNKI:SEND-NEWSLETTERS
                </span>
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></span>
                </span>
            </div>
        </div>

        {{-- Archiv Button --}}
        <button wire:click="toggleNewsletterArchive"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $showNewsletterArchive ? 'bg-blue-600 text-white shadow-[0_0_20px_rgba(37,99,235,0.3)] hover:bg-blue-500' : 'bg-gray-950 text-gray-400 hover:text-white border border-gray-800 shadow-inner' }}">
            <x-heroicon-m-archive-box class="w-4 h-4" />
            {{ $showNewsletterArchive ? 'Archiv schließen' : 'Archiv öffnen' }}
        </button>
    </div>

    @if($showNewsletterArchive)
        {{-- ARCHIV ANSICHT --}}
        <div class="animate-fade-in space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 w-full">
                @forelse($archivedTemplates as $tmpl)
                    <div class="p-5 rounded-[1.5rem] border border-gray-800 bg-gray-950/50 flex justify-between items-center group hover:border-blue-500/30 transition-all duration-300 shadow-inner">
                        <div class="min-w-0 pr-4">
                            <h5 class="font-bold text-gray-200 text-sm truncate">{{ $tmpl->subject }}</h5>
                            <p class="text-[9px] text-gray-500 uppercase font-black tracking-widest mt-1.5 flex items-center gap-1.5">
                                <span class="w-1 h-1 rounded-full bg-gray-700"></span>
                                {{ $tmpl->target_event_key }}
                            </p>
                        </div>
                        <button wire:click="restoreTemplate('{{ $tmpl->id }}')"
                                class="p-2.5 bg-gray-900 border border-gray-800 rounded-xl text-blue-400 hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-all shadow-lg shrink-0"
                                title="Wiederherstellen">
                            <x-heroicon-m-arrow-path class="w-4 h-4" />
                        </button>
                    </div>
                @empty
                    <div class="col-span-full text-center py-16 bg-gray-950 rounded-[2rem] border border-dashed border-gray-800 w-full">
                        <x-heroicon-o-archive-box class="w-12 h-12 text-gray-700 mx-auto mb-3" />
                        <p class="text-sm text-gray-500 italic font-serif">Das Archiv ist noch unberührt.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        {{-- TIMELINE NEWSLETTER (HORIZONTALER SLIDER WIE BEI GUTSCHEINEN) --}}
        <div class="relative group/slider w-full mt-4"
             x-data="{
                 scrollAmount: 0,
                 container: null,
                 init() { this.container = this.$refs.sliderContainer; },
                 scroll(direction) {
                     const scrollVal = 320;
                     if(direction === 'left') this.container.scrollBy({left: -scrollVal, behavior: 'smooth'});
                     else this.container.scrollBy({left: scrollVal, behavior: 'smooth'});
                 }
             }">

            {{-- Arrow Left --}}
            <button @click.stop="scroll('left')" class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-gray-800/90 backdrop-blur border border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-400 hover:text-white hover:border-blue-500 transition-all opacity-0 group-hover/slider:opacity-100 -translate-x-4 group-hover/slider:translate-x-2 duration-300">
                <x-heroicon-m-chevron-left class="w-6 h-6" />
            </button>

            {{-- Arrow Right --}}
            <button @click.stop="scroll('right')" class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-gray-800/90 backdrop-blur border border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-400 hover:text-white hover:border-blue-500 transition-all opacity-0 group-hover/slider:opacity-100 translate-x-4 group-hover/slider:-translate-x-2 duration-300">
                <x-heroicon-m-chevron-right class="w-6 h-6" />
            </button>

            <div x-ref="sliderContainer" class="flex gap-6 overflow-x-auto pb-8 pt-2 custom-scrollbar snap-x scroll-smooth relative z-10 w-full px-2">
                @foreach($newsletterTimeline as $event)
                    @php
                        $isPast = $event['date']->isPast() && !$event['date']->isToday();
                        $isNext = !$isPast && !($foundNext ?? false);
                        if ($isNext) $foundNext = true;
                        $isSelected = $editingTemplateId === $event['template_id'];
                    @endphp

                    {{-- FESTE BREITE FÜR DIE KACHELN WIE IM GUTSCHEIN BEREICH --}}
                    <div class="relative group w-[280px] sm:w-[320px] shrink-0 snap-start">
                        {{-- Selektions-Indikator (Glow Effekt) --}}
                        @if($isSelected)
                            <div class="absolute -inset-1 bg-gradient-to-r from-blue-500/20 to-indigo-500/20 blur-lg rounded-[2rem] animate-pulse"></div>
                        @endif

                        <div wire:click="editTemplate('{{ $event['template_id'] }}')"
                             class="relative cursor-pointer transition-all duration-500 group-hover:-translate-y-2 h-full {{ $isSelected ? 'scale-[1.03] z-20' : 'opacity-90 hover:opacity-100' }}">

                            {{-- Einbinden der funki-timeline-card Component --}}
                            <x-shop.funki-timeline-card
                                :date="$event['date']"
                                :title="$event['title']"
                                :event-key="$event['event_key'] ?? null"
                                :event-name="$event['event_name'] ?? null"
                                :state="$isPast ? 'past' : ($isNext ? 'next' : 'future')"
                                type="mail" />
                        </div>

                        {{-- Quick Archive Button --}}
                        <button
                            wire:click.stop="archiveTemplate('{{ $event['template_id'] }}')"
                            wire:confirm="Diese Kampagne wirklich archivieren?"
                            class="absolute top-3 left-3 w-8 h-8 bg-gray-900/90 backdrop-blur-md border border-gray-800 rounded-full text-gray-500 hover:text-red-400 hover:border-red-500/50 shadow-2xl flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 z-30"
                            title="Archivieren">
                            <x-heroicon-m-archive-box-arrow-down class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- NEWSLETTER INLINE EDITOR --}}
    @if($editingTemplateId)
        <div class="mt-12 animate-fade-in-up relative z-20 w-full">
            <div class="bg-gray-950/80 backdrop-blur-xl rounded-[3rem] border border-gray-800 overflow-hidden shadow-[0_30px_100px_rgba(0,0,0,0.6)] w-full">

                {{-- Editor Header --}}
                <div class="bg-gray-900 px-6 sm:px-8 py-6 border-b border-gray-800 flex justify-between items-center shadow-inner">
                    <div class="flex items-center gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 border border-blue-500/30 text-blue-400 flex items-center justify-center shadow-[0_0_20px_rgba(37,99,235,0.2)] shrink-0">
                            <x-heroicon-m-pencil-square class="w-6 h-6" />
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-serif font-bold text-white text-lg sm:text-xl tracking-tight leading-none truncate">Kampagne veredeln</h4>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mt-1.5 truncate">Inhalt & Timing anpassen</p>
                        </div>
                    </div>
                    <button wire:click="cancelEdit" class="w-10 h-10 rounded-full bg-gray-900 border border-gray-800 text-gray-500 hover:text-white hover:bg-red-500/20 hover:border-red-500/50 flex items-center justify-center transition-all shadow-inner hover:rotate-90 shrink-0">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-6 lg:p-10 grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 w-full">
                    {{-- Linke Spalte: Settings --}}
                    <div class="lg:col-span-4 space-y-6 lg:space-y-8 w-full">
                        <div class="space-y-2">
                            <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">E-Mail Betreffzeile</label>
                            <input type="text" wire:model="edit_subject"
                                   class="w-full bg-gray-900 border border-gray-800 text-white font-bold text-sm rounded-2xl px-5 py-4 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition-all shadow-inner placeholder-gray-700">
                        </div>

                        <div class="bg-gray-900/50 rounded-[2rem] p-6 border border-gray-800 shadow-inner w-full">
                            <div class="flex items-center justify-between mb-4">
                                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest">Timing Offset</label>
                                <span class="bg-blue-500/10 text-blue-400 text-[9px] px-2 py-0.5 rounded border border-blue-500/20 font-bold">Autopilot</span>
                            </div>
                            <div class="flex flex-col xl:flex-row items-start xl:items-center gap-4 xl:gap-5">
                                <input type="number" wire:model="edit_offset"
                                       class="w-full xl:w-24 bg-gray-950 border border-gray-800 text-blue-400 font-black text-2xl rounded-xl px-2 py-3 text-center outline-none focus:ring-2 focus:ring-blue-500/30 transition-all shadow-inner">
                                <p class="text-[11px] text-gray-400 font-medium leading-relaxed">
                                    Tage <strong class="text-gray-300">vor dem Ereignis</strong> wird dieser Funke automatisch versendet.
                                </p>
                            </div>
                        </div>

                        <div class="bg-gray-900/30 rounded-[2rem] p-6 border border-gray-800 border-dashed w-full">
                            <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                                Qualitätssicherung
                            </h5>
                            <button wire:click="sendTestMail"
                                    class="w-full py-4 rounded-xl bg-gray-900 border border-gray-800 text-gray-300 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-gray-800 hover:text-blue-400 hover:border-blue-500/30 transition-all shadow-lg active:scale-[0.98]">
                                <span wire:loading.remove wire:target="sendTestMail">Testmail an mich senden</span>
                                <span wire:loading wire:target="sendTestMail" class="animate-pulse text-blue-400">Sende Funken...</span>
                            </button>

                            @if(session()->has('test_success'))
                                <div class="mt-4 p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-black uppercase tracking-widest text-center rounded-xl animate-bounce">
                                    {{ session('test_success') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Rechte Spalte: HTML Editor --}}
                    <div class="lg:col-span-8 flex flex-col bg-gray-900 rounded-[2rem] border border-gray-800 overflow-hidden shadow-inner h-[500px] lg:h-[600px] w-full">
                        <div class="px-6 py-4 border-b border-gray-800 bg-gray-950/80 flex items-center justify-between shrink-0">
                            <div class="flex items-center gap-3">
                                <i class="solar-code-bold-duotone text-blue-400"></i>
                                <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Newsletter HTML Source</span>
                            </div>
                            <div class="flex gap-1.5 shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-500/20"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500/20"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/20"></span>
                            </div>
                        </div>
                        <textarea wire:model="edit_content"
                                  class="flex-1 w-full h-full bg-transparent border-none p-6 sm:p-8 font-mono text-xs sm:text-sm text-blue-100/80 focus:ring-0 outline-none resize-none custom-scrollbar leading-relaxed"
                                  placeholder=""></textarea>
                        <div class="px-6 py-3 bg-gray-950/50 border-t border-gray-800 text-[9px] font-mono text-gray-600 shrink-0 truncate">
                            VERFÜGBARE TAGS: {NAME}, {URL}, {UNSUBSCRIBE_LINK}
                        </div>
                    </div>
                </div>

                {{-- Editor Footer --}}
                <div class="bg-gray-900/50 px-6 sm:px-8 py-5 sm:py-6 border-t border-gray-800 flex flex-col-reverse sm:flex-row justify-end gap-4 shadow-inner">
                    <button wire:click="cancelEdit" class="w-full sm:w-auto px-8 py-4 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white hover:bg-gray-800 transition-colors text-center">
                        Änderungen verwerfen
                    </button>
                    <button wire:click="saveTemplate"
                            class="w-full sm:w-auto px-10 py-4 rounded-xl bg-primary text-gray-900 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white hover:scale-[1.05] transition-all shadow-[0_0_30px_rgba(197,160,89,0.3)] text-center flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="saveTemplate">Konfiguration speichern</span>
                        <span wire:loading wire:target="saveTemplate" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            Speichere...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>

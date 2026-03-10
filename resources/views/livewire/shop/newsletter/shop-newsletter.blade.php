<div class="animate-fade-in-up font-sans antialiased text-gray-300 min-h-screen">
    <div class="pb-20">
        <div class="max-w-7xl mx-auto">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 mb-8">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-wide">Newsletter</h1>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-2">Abonnenten und Kampagnen verwalten.</p>
                </div>
            </div>

            {{-- KPIs --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
                <div class="bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Abonnenten</p>
                        <h3 class="text-3xl font-serif font-bold text-white">{{ $stats['subscribers'] }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20 shadow-inner">
                        <x-heroicon-s-users class="w-6 h-6" />
                    </div>
                </div>
                <div class="bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-800 shadow-2xl flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Aktive Kampagnen</p>
                        <h3 class="text-3xl font-serif font-bold text-white">{{ $stats['active_templates'] }}</h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20 shadow-inner">
                        <x-heroicon-s-paper-airplane class="w-6 h-6" />
                    </div>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="bg-emerald-500/10 border-l-4 border-emerald-500 p-5 mb-8 text-emerald-400 shadow-inner rounded-r-2xl flex justify-between items-center text-[10px] font-black uppercase tracking-widest">
                    <span>{{ session('success') }}</span>
                    <x-heroicon-s-check-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                </div>
            @endif

            @if (session()->has('error'))
                <div class="bg-red-500/10 border-l-4 border-red-500 p-5 mb-8 text-red-400 shadow-inner rounded-r-2xl flex justify-between items-center text-[10px] font-black uppercase tracking-widest">
                    <span>{{ session('error') }}</span>
                    <x-heroicon-s-exclamation-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                </div>
            @endif

            {{-- Tabs --}}
            <div class="flex border-b border-gray-800 mb-8 overflow-x-auto no-scrollbar gap-6">
                <button wire:click="setTab('templates')" class="pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $activeTab === 'templates' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300' }}">Automationen</button>
                <button wire:click="setTab('subscribers')" class="pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $activeTab === 'subscribers' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300' }}">Abonnenten</button>
                <button wire:click="setTab('archive')" class="pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap {{ $activeTab === 'archive' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300' }}">Archiv</button>
            </div>

            {{-- Content Area --}}
            <div class="animate-fade-in-up">

                {{-- TAB: TEMPLATES --}}
                @if($activeTab === 'templates')
                    @if($editingTemplateId)
                        {{-- Edit Template View --}}
                        <div class="bg-gray-950/80 backdrop-blur-xl rounded-[3rem] border border-gray-800 overflow-hidden shadow-[0_30px_100px_rgba(0,0,0,0.6)] w-full">
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
                                <div class="lg:col-span-4 space-y-6 lg:space-y-8 w-full">
                                    <div class="space-y-2">
                                        <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1">E-Mail Betreffzeile</label>
                                        <input type="text" wire:model="edit_subject" class="w-full bg-gray-900 border border-gray-800 text-white font-bold text-sm rounded-2xl px-5 py-4 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition-all shadow-inner placeholder-gray-700">
                                    </div>

                                    <div class="bg-gray-900/50 rounded-[2rem] p-6 border border-gray-800 shadow-inner w-full"
                                         x-data="{
                                             offset: @entangle('edit_offset').live,
                                             eventDateStr: '{{ $edit_event_date }}',
                                             get sendDateFormatted() {
                                                 if(!this.eventDateStr || this.offset === null || this.offset === '') return '';
                                                 let d = new Date(this.eventDateStr);
                                                 d.setDate(d.getDate() - parseInt(this.offset));
                                                 return d.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit', year: 'numeric'});
                                             },
                                             get eventDateFormatted() {
                                                 if(!this.eventDateStr) return '';
                                                 let d = new Date(this.eventDateStr);
                                                 return d.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit', year: 'numeric'});
                                             }
                                         }">

                                        @if($edit_type === 'automated')
                                            <div class="flex items-center justify-between mb-4">
                                                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest">Timing Offset (Tage vorher)</label>
                                                <span class="bg-blue-500/10 text-blue-400 text-[9px] px-2 py-0.5 rounded border border-blue-500/20 font-bold">Autopilot</span>
                                            </div>

                                            <div class="flex items-center gap-4 mb-4">
                                                <input type="number" x-model="offset" class="w-24 bg-gray-950 border border-gray-800 text-blue-400 font-black text-2xl rounded-xl px-2 py-3 text-center outline-none focus:ring-2 focus:ring-blue-500/30 transition-all shadow-inner">
                                            </div>

                                            {{-- Live Datumsvorschau Box --}}
                                            <div class="text-[11px] text-gray-400 font-medium leading-relaxed bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner w-full space-y-2">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-gray-500">Datum des Ereignisses:</span>
                                                    <span class="text-white font-bold" x-text="eventDateFormatted"></span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-blue-400 font-bold">Wird versendet am:</span>
                                                    <span class="text-blue-400 font-bold" x-text="sendDateFormatted"></span>
                                                </div>

                                                <template x-if="offset == 0 || offset == ''">
                                                    <div class="mt-3 pt-3 border-t border-gray-800 text-emerald-400 flex items-center gap-2">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        Versand erfolgt exakt am Tag des Ereignisses.
                                                    </div>
                                                </template>

                                                <template x-if="offset < 0">
                                                    <div class="mt-3 pt-3 border-t border-gray-800 text-red-400 font-bold">
                                                        Negative Werte sind hierfür nicht vorgesehen.
                                                    </div>
                                                </template>
                                            </div>
                                        @else
                                            <div class="flex items-center justify-between mb-4">
                                                <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest">Exaktes Sendedatum</label>
                                                <span class="bg-amber-500/10 text-amber-400 text-[9px] px-2 py-0.5 rounded border border-amber-500/20 font-bold">Manuell</span>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <input type="datetime-local" wire:model="edit_event_date" class="w-full bg-gray-950 border border-gray-800 text-amber-400 font-black text-sm rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-amber-500/30 transition-all shadow-inner">
                                            </div>
                                        @endif
                                    </div>

                                    <div class="bg-gray-900/30 rounded-[2rem] p-6 border border-gray-800 border-dashed w-full">
                                        <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                            <x-heroicon-o-paper-airplane class="w-4 h-4 text-primary" />
                                            Qualitätssicherung
                                        </h5>
                                        <button wire:click="sendTestMail" class="w-full py-4 rounded-xl bg-gray-900 border border-gray-800 text-gray-300 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-gray-800 hover:text-blue-400 hover:border-blue-500/30 transition-all shadow-lg active:scale-[0.98]">
                                            <span wire:loading.remove wire:target="sendTestMail">Testmail an mich senden</span>
                                            <span wire:loading wire:target="sendTestMail" class="animate-pulse text-blue-400">Sende Test...</span>
                                        </button>
                                        @if(session()->has('test_success'))
                                            <div class="mt-4 p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-black uppercase tracking-widest text-center rounded-xl animate-bounce">{{ session('test_success') }}</div>
                                        @endif
                                        @if(session()->has('test_error'))
                                            <div class="mt-4 p-3 bg-red-500/10 border border-red-500/30 text-red-400 text-[10px] font-black uppercase tracking-widest text-center rounded-xl">{{ session('test_error') }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="lg:col-span-8 flex flex-col bg-gray-900 rounded-[2rem] border border-gray-800 overflow-hidden shadow-inner h-[500px] lg:h-[600px] w-full">
                                    <div class="px-6 py-4 border-b border-gray-800 bg-gray-950/80 flex items-center justify-between shrink-0">
                                        <div class="flex items-center gap-3">
                                            <x-heroicon-o-code-bracket class="w-5 h-5 text-blue-400" />
                                            <span class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Newsletter HTML Source</span>
                                        </div>
                                        <div class="flex gap-1.5 shrink-0">
                                            <span class="w-2.5 h-2.5 rounded-full bg-red-500/20"></span>
                                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500/20"></span>
                                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/20"></span>
                                        </div>
                                    </div>
                                    <textarea wire:model="edit_content" class="flex-1 w-full h-full bg-transparent border-none p-6 sm:p-8 font-mono text-xs sm:text-sm text-blue-100/80 focus:ring-0 outline-none resize-none custom-scrollbar leading-relaxed"></textarea>
                                    <div class="px-6 py-3 bg-gray-950/50 border-t border-gray-800 text-[9px] font-mono text-gray-600 shrink-0 truncate">
                                        VERFÜGBARE TAGS: {first_name}, {year}, {NAME}, {URL}, {UNSUBSCRIBE_LINK}
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-900/50 px-6 sm:px-8 py-5 sm:py-6 border-t border-gray-800 flex flex-col-reverse sm:flex-row justify-end gap-4 shadow-inner">
                                <button wire:click="cancelEdit" class="w-full sm:w-auto px-8 py-4 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white hover:bg-gray-800 transition-colors text-center">
                                    Änderungen verwerfen
                                </button>
                                <button wire:click="saveTemplate" class="w-full sm:w-auto px-10 py-4 rounded-xl bg-primary text-gray-900 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white hover:scale-[1.05] transition-all shadow-[0_0_30px_rgba(197,160,89,0.3)] text-center flex items-center justify-center gap-2">
                                    <span wire:loading.remove wire:target="saveTemplate">Speichern</span>
                                    <span wire:loading wire:target="saveTemplate" class="flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                        Speichere...
                                    </span>
                                </button>
                            </div>
                        </div>
                    @else
                        {{-- Template Slider Header --}}
                        <div class="flex items-center justify-between mt-4 mb-2">
                            <h3 class="text-white font-serif font-bold text-xl tracking-wide">Aktive Kampagnen</h3>
                            <button wire:click="openCreateModal" class="px-5 py-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500 hover:text-white hover:border-emerald-500 transition-all font-black text-[10px] uppercase tracking-widest shadow-lg flex items-center gap-2">
                                <x-heroicon-o-plus class="w-4 h-4" />
                                Neue Automation
                            </button>
                        </div>
                        
                        {{-- Template Slider --}}
                        <div class="relative group/slider w-full mt-4" x-data="{
                                scrollAmount: 0,
                                container: null,
                                init() { this.container = this.$refs.sliderContainer; },
                                scroll(direction) {
                                    const scrollVal = 320;
                                    if (direction === 'left') this.container.scrollBy({ left: -scrollVal, behavior: 'smooth' });
                                    else this.container.scrollBy({ left: scrollVal, behavior: 'smooth' });
                                }
                            }">
                            <button @click.stop="scroll('left')" class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-gray-800/90 backdrop-blur border border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-400 hover:text-white hover:border-blue-500 transition-all opacity-0 group-hover/slider:opacity-100 -translate-x-4 group-hover/slider:translate-x-2 duration-300">
                                <x-heroicon-m-chevron-left class="w-6 h-6" />
                            </button>
                            <button @click.stop="scroll('right')" class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-gray-800/90 backdrop-blur border border-gray-700 rounded-full shadow-lg flex items-center justify-center text-gray-400 hover:text-white hover:border-blue-500 transition-all opacity-0 group-hover/slider:opacity-100 translate-x-4 group-hover/slider:-translate-x-2 duration-300">
                                <x-heroicon-m-chevron-right class="w-6 h-6" />
                            </button>

                            <div x-ref="sliderContainer" class="flex gap-6 overflow-x-auto pb-8 pt-2 custom-scrollbar snap-x scroll-smooth relative z-10 w-full px-2">
                                @foreach($newsletterTimeline as $event)
                                    @php
                                        // $event['date'] ist das Sendedatum. $event['event_date'] das Feiertags-Datum.
                                        $isPast = $event['date']->isPast() && !$event['date']->isToday();
                                        $isNext = !$isPast && !($foundNext ?? false);
                                        if($isNext) $foundNext = true;
                                    @endphp
                                    <div class="relative group w-[280px] sm:w-[320px] shrink-0 snap-start">
                                        <div wire:click="editTemplate('{{ $event['template_id'] }}')" class="relative cursor-pointer transition-all duration-500 group-hover:-translate-y-2 h-full opacity-90 hover:opacity-100">
                                            <x-shop.funki-timeline-card
                                                :date="$event['event_date']"
                                                :title="$event['title']"
                                                :subtitle="'Versand am: ' . $event['date']->format('d.m.Y')"
                                                :event-key="$event['event_key'] ?? null"
                                                :event-name="$event['event_name'] ?? null"
                                                :state="$isPast ? 'past' : ($isNext ? 'next' : 'future')"
                                                type="mail"
                                            />
                                        </div>
                                        <button wire:click.stop="archiveTemplate('{{ $event['template_id'] }}')" wire:confirm="Diese Kampagne wirklich archivieren?" class="absolute top-3 left-3 w-8 h-8 bg-gray-900/90 backdrop-blur-md border border-gray-800 rounded-full text-gray-500 hover:text-red-400 hover:border-red-500/50 shadow-2xl flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 z-30" title="Archivieren">
                                            <x-heroicon-m-archive-box-arrow-down class="w-4 h-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Create Automation Modal --}}
                        @if($showCreateModal)
                            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md z-[100] flex items-center justify-center p-4 sm:p-6" x-data @keydown.escape.window="$wire.showCreateModal = false">
                                <div class="bg-gray-900 border border-gray-800 rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden animate-fade-in-up">
                                    <div class="px-6 py-5 border-b border-gray-800 flex justify-between items-center bg-gray-950/50">
                                        <h3 class="text-white font-serif font-bold text-xl">Neue Automation</h3>
                                        <button wire:click="$set('showCreateModal', false)" class="text-gray-500 hover:text-white transition-colors">
                                            <x-heroicon-m-x-mark class="w-6 h-6" />
                                        </button>
                                    </div>
                                    <div class="p-6">
                                        <div class="flex gap-4 mb-6">
                                            <label class="flex-1 cursor-pointer relative">
                                                <input type="radio" wire:model.live="new_type" value="automated" class="peer sr-only">
                                                <div class="p-4 rounded-xl border border-gray-800 bg-gray-950 peer-checked:bg-blue-500/10 peer-checked:border-blue-500/50 peer-checked:text-blue-400 text-gray-500 transition-all flex flex-col items-center justify-center gap-2 text-center h-full">
                                                    <x-heroicon-o-calendar-days class="w-6 h-6" />
                                                    <span class="text-[10px] font-black uppercase tracking-widest">Automatisiert<br><span class="text-[8px] font-medium tracking-normal normal-case opacity-70">(Jährlich)</span></span>
                                                </div>
                                            </label>
                                            <label class="flex-1 cursor-pointer relative">
                                                <input type="radio" wire:model.live="new_type" value="manual" class="peer sr-only">
                                                <div class="p-4 rounded-xl border border-gray-800 bg-gray-950 peer-checked:bg-amber-500/10 peer-checked:border-amber-500/50 peer-checked:text-amber-400 text-gray-500 transition-all flex flex-col items-center justify-center gap-2 text-center h-full">
                                                    <x-heroicon-o-cursor-arrow-rays class="w-6 h-6" />
                                                    <span class="text-[10px] font-black uppercase tracking-widest">Manuell<br><span class="text-[8px] font-medium tracking-normal normal-case opacity-70">(Einmalig)</span></span>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="space-y-4">
                                            @if($new_type === 'automated')
                                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 leading-relaxed">
                                                    Wähle ein Ereignis, für das noch keine aktive Automation existiert.
                                                </p>
                                                <div>
                                                    <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1 mb-2">Ziel-Ereignis</label>
                                                    <select wire:model="new_target_event_key" class="w-full bg-gray-950 border border-gray-800 text-white font-bold text-sm rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all shadow-inner appearance-none cursor-pointer">
                                                        <option value="">Bitte wählen...</option>
                                                        @foreach($availableEvents as $key => $label)
                                                            @if(!in_array($key, $activeTemplateKeys))
                                                                <option value="{{ $key }}">{{ $label }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @else
                                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 leading-relaxed">
                                                    Lege den Namen und den Versandzeitpunkt fest.
                                                </p>
                                                <div>
                                                    <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1 mb-2">Interner Name</label>
                                                    <input type="text" wire:model="new_manual_title" placeholder="z.B. Sommer Special 2026" class="w-full bg-gray-950 border border-gray-800 text-white font-bold text-sm rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none transition-all shadow-inner">
                                                </div>
                                                <div>
                                                    <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest ml-1 mb-2">Versanddatum & Uhrzeit</label>
                                                    <input type="datetime-local" wire:model="new_manual_send_at" class="w-full bg-gray-950 border border-gray-800 text-amber-400 font-bold text-sm rounded-xl px-4 py-3.5 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none transition-all shadow-inner" style="color-scheme: dark;">
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="px-6 py-5 border-t border-gray-800 bg-gray-950/50 flex justify-end gap-4">
                                        <button wire:click="$set('showCreateModal', false)" class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white hover:bg-gray-800 transition-colors">
                                            Abbrechen
                                        </button>
                                        <button wire:click="createTemplate" class="px-6 py-2.5 rounded-xl bg-primary text-gray-900 font-black text-[10px] uppercase tracking-widest hover:bg-white hover:scale-105 transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)] flex items-center gap-2">
                                            <span wire:loading.remove wire:target="createTemplate">Erstellen</span>
                                            <span wire:loading wire:target="createTemplate" class="flex items-center gap-2">
                                                <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                Erstelle...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                @endif

                {{-- TAB: SUBSCRIBERS --}}
                @if($activeTab === 'subscribers')
                    <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">
                        <div class="p-6 sm:p-8 border-b border-gray-800 bg-gray-950/50 shadow-inner flex flex-col sm:flex-row gap-6 items-center justify-between">
                            <div class="relative flex-1 group w-full sm:max-w-md">
                                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Abonnent suchen..." class="w-full pl-12 pr-4 py-3.5 bg-gray-900 border border-gray-800 rounded-xl text-sm text-white focus:bg-gray-950 focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-600">
                                <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-primary transition-colors" />
                            </div>

                            <form wire:submit.prevent="addSubscriber" class="flex items-center gap-3 w-full sm:w-auto">
                                <div class="relative flex-1 sm:w-64">
                                    <input wire:model="newSubscriberEmail" type="email" required placeholder="Neue E-Mail Adresse..." class="w-full px-4 py-3.5 bg-gray-900 border border-gray-800 rounded-xl text-sm text-white focus:bg-gray-950 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 shadow-inner outline-none transition-all placeholder-gray-600">
                                </div>
                                <button type="submit" class="p-3.5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 hover:bg-emerald-500 hover:text-white hover:border-emerald-500 rounded-xl transition-all shadow-lg shrink-0" title="Abonnent manuell prüfen & eintragen">
                                    <svg wire:loading.remove wire:target="addSubscriber" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    <svg wire:loading wire:target="addSubscriber" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                </button>
                            </form>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[800px]">
                                <thead class="bg-gray-950/80 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-800 shadow-inner">
                                <tr>
                                    <th class="px-6 sm:px-8 py-5">E-Mail</th>
                                    <th class="px-6 sm:px-8 py-5">Datum</th>
                                    <th class="px-6 sm:px-8 py-5 text-center">Bestätigt</th>
                                    <th class="px-6 sm:px-8 py-5 text-right">Aktion</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/50">
                                @forelse($subscribers as $sub)
                                    <tr class="hover:bg-gray-800/30 transition-colors group">
                                        <td class="px-6 sm:px-8 py-6 align-middle">
                                            <div class="font-bold text-white text-sm tracking-wide">{{ $sub->email }}</div>
                                        </td>
                                        <td class="px-6 sm:px-8 py-6 align-middle text-sm text-gray-400 font-medium">
                                            {{ $sub->created_at->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="px-6 sm:px-8 py-6 align-middle text-center">
                                            @if($sub->is_verified)
                                                <span class="inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-emerald-500/10 text-emerald-400 border-emerald-500/30 shadow-[0_0_8px_rgba(16,185,129,0.4)]">Ja</span>
                                            @else
                                                <span class="inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-amber-500/10 text-amber-400 border-amber-500/30 shadow-[0_0_8px_rgba(245,158,11,0.4)]">Nein</span>
                                            @endif
                                        </td>
                                        <td class="px-6 sm:px-8 py-6 align-middle text-right">
                                            <button wire:click="deleteSubscriber('{{ $sub->id }}')" wire:confirm="Diesen Abonnenten wirklich löschen?" class="p-2.5 bg-gray-950 border border-gray-800 text-gray-500 hover:bg-red-500/10 hover:border-red-500/30 hover:text-red-400 rounded-xl transition-all shadow-inner" title="Löschen">
                                                <x-heroicon-m-trash class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="w-16 h-16 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center mb-4 shadow-inner">
                                                    <x-heroicon-o-user-group class="w-8 h-8 text-gray-600" />
                                                </div>
                                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Keine Abonnenten gefunden.</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($subscribers->hasPages())
                            <div class="p-6 border-t border-gray-800 bg-gray-950/30 shadow-inner">
                                {{ $subscribers->links() }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- TAB: ARCHIVE --}}
                @if($activeTab === 'archive')
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 w-full">
                        @forelse($archivedTemplates as $tmpl)
                            <div class="p-5 rounded-[1.5rem] border border-gray-800 bg-gray-950/50 flex justify-between items-center group hover:border-blue-500/30 transition-all duration-300 shadow-inner">
                                <div class="min-w-0 pr-4">
                                    <h5 class="font-bold text-gray-200 text-sm truncate">{{ $tmpl->subject }}</h5>
                                    <p class="text-[9px] text-gray-500 uppercase font-black tracking-widest mt-1.5 flex items-center gap-1.5">
                                        <span class="w-1 h-1 rounded-full bg-gray-700"></span>{{ $tmpl->target_event_key }}
                                    </p>
                                </div>
                                <button wire:click="restoreTemplate('{{ $tmpl->id }}')" class="p-2.5 bg-gray-900 border border-gray-800 rounded-xl text-blue-400 hover:bg-blue-500 hover:text-white hover:border-blue-500 transition-all shadow-lg shrink-0" title="Wiederherstellen">
                                    <x-heroicon-m-arrow-path class="w-4 h-4" />
                                </button>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-16 bg-gray-900/80 backdrop-blur-xl rounded-[2rem] border border-dashed border-gray-800 w-full">
                                <x-heroicon-o-archive-box class="w-12 h-12 text-gray-700 mx-auto mb-3" />
                                <p class="text-sm text-gray-500 italic font-serif">Das Archiv ist noch unberührt.</p>
                            </div>
                        @endforelse
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

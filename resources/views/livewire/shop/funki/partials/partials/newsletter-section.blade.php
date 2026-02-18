<section class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-10 relative overflow-hidden transition-all duration-500">
    <div class="absolute top-0 left-0 w-2 h-full bg-blue-500"></div>

    <div class="flex flex-col md:flex-row justify-between items-start mb-8 gap-4">
        <div>
            <h3 class="text-2xl font-serif font-bold text-slate-900">Newsletter Automatisierung</h3>
            <p class="text-xs font-mono text-slate-400 mt-1 uppercase tracking-tighter">Command: funki:send-newsletters</p>
        </div>

        {{-- Archiv Button --}}
        <button wire:click="toggleNewsletterArchive"
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $showNewsletterArchive ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-slate-50 text-slate-400 hover:bg-slate-100' }}">
            <x-heroicon-m-archive-box class="w-4 h-4" />
            {{ $showNewsletterArchive ? 'Archiv schließen' : 'Archiv öffnen' }}
        </button>
    </div>

    @if($showNewsletterArchive)
        {{-- ARCHIV ANSICHT --}}
        <div class="animate-fade-in">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($archivedTemplates as $tmpl)
                    <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50 flex justify-between items-center group">
                        <div>
                            <h5 class="font-bold text-slate-700 text-sm">{{ $tmpl->subject }}</h5>
                            <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mt-1">{{ $tmpl->target_event_key }}</p>
                        </div>
                        <button wire:click="restoreTemplate('{{ $tmpl->id }}')" class="p-2 bg-white rounded-lg shadow-sm text-blue-600 hover:bg-blue-600 hover:text-white transition-all" title="Wiederherstellen">
                            <x-heroicon-m-arrow-path class="w-4 h-4" />
                        </button>
                    </div>
                @empty
                    <p class="col-span-full text-center py-10 text-slate-400 italic text-sm">Das Archiv ist leer.</p>
                @endforelse
            </div>
        </div>
    @else
        {{-- TIMELINE NEWSLETTER (Normal) --}}
        <div class="flex gap-4 overflow-x-auto pb-6 pt-2 custom-scrollbar snap-x">
            @foreach($newsletterTimeline as $event)
                @php
                    $isPast = $event['date']->isPast() && !$event['date']->isToday();
                    $isNext = !$isPast && !($foundNext ?? false);
                    if ($isNext) $foundNext = true;
                    $isSelected = $editingTemplateId === $event['template_id'];
                @endphp

                <div class="relative group snap-start">
                    <div wire:click="editTemplate('{{ $event['template_id'] }}')"
                         class="cursor-pointer transition-all duration-300 group-hover:-translate-y-1 {{ $isSelected ? 'ring-4 ring-blue-500/20 scale-[1.02] bg-blue-50/10' : '' }}">
                        <x-shop.funki-timeline-card
                            :date="$event['date']"
                            :title="$event['title']"
                            :event-key="$event['event_key'] ?? null"
                            :event-name="$event['event_name'] ?? null"
                            :state="$isPast ? 'past' : ($isNext ? 'next' : 'future')"
                            type="mail" />
                    </div>

                    <button
                        wire:click="archiveTemplate('{{ $event['template_id'] }}')"
                        wire:confirm="Diese Kampagne wirklich archivieren?"
                        class="absolute top-2 left-2 w-8 h-8 bg-white rounded-full text-gray-300 hover:text-red-500 hover:bg-red-50 shadow-md border border-gray-100 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 z-20"
                        title="Archivieren">
                        <x-heroicon-m-archive-box-arrow-down class="w-4 h-4" />
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- NEWSLETTER INLINE EDITOR --}}
    @if($editingTemplateId)
        <div class="mt-10 animate-fade-in-up">
            <div class="bg-slate-50 rounded-[2.5rem] border border-slate-200 overflow-hidden relative shadow-lg">
                {{-- ... (Editor Inhalt bleibt gleich) ... --}}
                <div class="bg-white px-8 py-5 border-b border-slate-100 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white flex items-center justify-center shadow-blue-200 shadow-lg">
                            <x-heroicon-m-pencil-square class="w-5 h-5" />
                        </div>
                        <div><h4 class="font-black text-slate-800 text-lg leading-none">Kampagne bearbeiten</h4></div>
                    </div>
                    <button wire:click="cancelEdit" class="text-slate-400 hover:text-slate-600">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-8 grid grid-cols-1 lg:grid-cols-2 gap-10">
                    <div class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5 ml-1">Betreff</label>
                            <input type="text" wire:model="edit_subject" class="w-full bg-white border border-slate-200 text-slate-800 font-bold text-sm rounded-2xl px-5 py-4 focus:ring-4 focus:ring-blue-100 outline-none shadow-sm">
                        </div>
                        <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm flex items-center gap-4">
                            <input type="number" wire:model="edit_offset" class="w-24 bg-blue-50/50 border border-blue-100 text-blue-700 font-black text-xl rounded-xl px-2 py-3 text-center outline-none">
                            <p class="text-xs text-slate-500 font-medium">Tage vor dem Event versenden.</p>
                        </div>
                        <div class="bg-slate-100/50 rounded-3xl p-6 border border-slate-200/60">
                            <button wire:click="sendTestMail" class="w-full py-4 rounded-xl bg-white border border-slate-200 text-slate-600 font-bold text-xs uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all shadow-sm">Testmail senden</button>
                            @if(session()->has('test_success')) <div class="mt-3 text-[10px] text-green-600 font-bold text-center">{{ session('test_success') }}</div> @endif
                            @if(session()->has('test_error')) <div class="mt-3 text-[10px] text-red-600 font-bold text-center">{{ session('test_error') }}</div> @endif
                        </div>
                    </div>
                    <div class="flex flex-col h-full bg-slate-900 rounded-3xl p-1 shadow-inner">
                        <div class="px-4 py-2 border-b border-white/5 text-[9px] font-mono text-slate-500 uppercase">HTML Source</div>
                        <textarea wire:model="edit_content" class="flex-1 w-full bg-transparent border-none p-5 font-mono text-sm text-blue-200 focus:ring-0 outline-none min-h-[450px] resize-none custom-scrollbar"></textarea>
                    </div>
                </div>
                <div class="bg-gray-50 px-8 py-6 border-t border-slate-200 flex justify-end gap-4">
                    <button wire:click="cancelEdit" class="px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest text-slate-400">Abbrechen</button>
                    <button wire:click="saveTemplate" class="px-8 py-3 rounded-xl bg-slate-900 text-white font-black text-xs uppercase tracking-widest hover:bg-blue-600 transition-all shadow-lg">Speichern</button>
                </div>
            </div>
        </div>
    @endif
</section>

{{--
<div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-10 animate-fade-in">
    <div class="mb-10">
        <h2 class="text-2xl font-serif font-bold text-gray-900 italic">Ruhende Vorlagen</h2>
        <p class="text-sm text-gray-500">Diese Mails befinden sich im Tiefschlaf und werden nicht automatisch versendet.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($archivedTemplates as $tmpl)
            <div class="group border border-gray-200 rounded-[2rem] p-8 bg-gray-50/50 flex flex-col justify-between opacity-80 hover:opacity-100 hover:bg-white hover:border-green-200 transition-all duration-500 hover:shadow-xl">
                <div>
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2">
                        {{ $availableEvents[$tmpl->target_event_key] ?? $tmpl->target_event_key }}
                    </div>
                    <h3 class="font-bold text-gray-900 text-xl">{{ $tmpl->title }}</h3>
                    <p class="text-sm text-gray-500 mt-4 line-clamp-2 italic">"{{ $tmpl->subject }}"</p>
                </div>
                <button wire:click="restoreTemplate('{{ $tmpl->id }}')" class="mt-8 w-full py-4 bg-white border border-gray-200 rounded-2xl text-xs font-black uppercase tracking-widest text-gray-600 hover:bg-green-600 hover:text-white hover:border-green-600 transition-all duration-300 shadow-sm hover:shadow-lg flex items-center justify-center gap-3">
                    <i class="bi bi-lightning-charge-fill"></i> Reaktivieren
                </button>
            </div>
        @empty
            <div class="col-span-full py-20 text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-box2 text-gray-300 fs-2"></i>
                </div>
                <p class="text-gray-400 font-bold uppercase tracking-widest text-xs">Keine inaktiven Vorlagen</p>
            </div>
        @endforelse
    </div>
</div>
--}}

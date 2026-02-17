{{--
@if($editingTemplateId)
    <div class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/90 backdrop-blur-md p-4 animate-fade-in">
        <div class="bg-white rounded-[3rem] shadow-2xl w-full max-w-4xl overflow-hidden animate-zoom-in flex flex-col max-h-[90vh] border border-white/20">
            <div class="p-8 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-orange-500 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-orange-500/30">
                        <i class="bi bi-pencil-square fs-4"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-gray-900 uppercase tracking-tighter">Vorlage bearbeiten</h3>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-widest">Globales Automation Template</p>
                    </div>
                </div>
                <button wire:click="cancelEdit" class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-gray-200 transition-all text-gray-400"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="p-8 space-y-8 overflow-y-auto custom-scrollbar flex-1">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    --}}
{{-- Left Column: Settings --}}{{--

                    <div class="space-y-6">
                        <div class="group">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">E-Mail Betreff</label>
                            <input type="text" wire:model="edit_subject" class="w-full bg-gray-50 border-gray-200 rounded-2xl px-5 py-4 focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 font-bold text-gray-800 transition-all outline-none">
                        </div>

                        <div class="bg-orange-50/50 rounded-[2rem] p-6 border border-orange-100">
                            <label class="block text-[10px] font-black text-orange-400 uppercase tracking-[0.2em] mb-4">Timing (Offset)</label>
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <input type="number" wire:model="edit_offset" class="w-24 bg-white border-orange-200 rounded-xl px-4 py-3 text-center font-black text-orange-600 focus:ring-orange-500 outline-none">
                                    <span class="absolute -top-2 left-3 bg-white px-2 text-[8px] font-black text-orange-400 rounded-full border border-orange-100">Tage</span>
                                </div>
                                <p class="text-xs text-orange-800/60 font-medium leading-relaxed">
                                    Diese Mail wird automatisch <span class="font-black text-orange-600">{{ $edit_offset ?: 0 }} Tage</span> vor dem eigentlichen Event-Datum verschickt.
                                </p>
                            </div>
                        </div>

                        <div class="bg-blue-50/50 rounded-[2rem] p-6 border border-blue-100">
                            <div class="flex items-center gap-4 mb-4">
                                <i class="bi bi-magic text-blue-500"></i>
                                <span class="text-[10px] font-black text-blue-400 uppercase tracking-[0.2em]">Quality Check</span>
                            </div>
                            <button type="button" wire:click="sendTestMail" wire:loading.attr="disabled" class="w-full py-4 rounded-2xl bg-white border border-blue-200 text-blue-600 font-black text-xs uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all duration-300 shadow-sm flex items-center justify-center gap-3">
                                <span wire:loading.remove wire:target="sendTestMail"><i class="bi bi-send-check-fill"></i> Testmail an mich</span>
                                <span wire:loading wire:target="sendTestMail" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i> Sende...</span>
                            </button>
                            @if(session()->has('test_success'))
                                <p class="text-[10px] text-green-600 font-bold mt-3 text-center animate-bounce">✨ {{ session('test_success') }}</p>
                            @endif
                        </div>
                    </div>

                    --}}
{{-- Right Column: Content --}}{{--

                    <div class="flex flex-col">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2 ml-1">Inhalt (HTML-Editor)</label>
                        <textarea wire:model="edit_content" class="flex-1 w-full bg-slate-900 border-none rounded-[2rem] p-6 font-mono text-sm text-blue-300 focus:ring-4 focus:ring-blue-500/20 outline-none min-h-[300px] shadow-inner"></textarea>
                        <div class="mt-3 flex justify-between px-2">
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Snippet: {first_name}</span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Autosave: On</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-8 bg-gray-50/50 border-t border-gray-100 flex justify-end gap-4">
                <button type="button" wire:click="cancelEdit" class="px-8 py-4 rounded-2xl text-xs font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-all">Abbrechen</button>
                <button type="button" wire:click="saveTemplate" class="px-10 py-4 rounded-2xl bg-slate-900 text-white font-black text-xs uppercase tracking-widest hover:bg-black transition-all shadow-xl shadow-slate-900/20">Änderungen Speichern</button>
            </div>
        </div>
    </div>
@endif
--}}

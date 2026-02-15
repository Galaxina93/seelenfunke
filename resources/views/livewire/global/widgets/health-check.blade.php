<div>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($this->performAllChecks as $key => $check)
                <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm transition-all {{ $expandedHealthKey === $key ? 'ring-2 ring-blue-500 ring-offset-2' : '' }}">

                    {{-- Kachel Header --}}
                    <div wire:click="toggleHealthCard('{{ $key }}')" class="p-4 cursor-pointer flex justify-between items-center bg-white hover:bg-slate-50 transition-colors">
                        <div class="flex gap-4">
                            {{-- Icon: Rund und kräftigeres Leuchten --}}
                            <div class="p-3 rounded-full {{ $check['status'] === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600 shadow-[0_0_15px_rgba(244,63,94,0.5)] animate-pulse' }}">
                                <i class="bi {{ $check['icon'] }} fs-4"></i>
                            </div>
                            <div class="text-left">
                                <h4 class="text-xs font-black text-slate-800 uppercase tracking-tighter">{{ $check['title'] }}</h4>
                                <p class="text-[10px] text-slate-500 font-medium leading-tight">{{ $check['message'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($check['count'] > 0)
                                <span class="text-[10px] font-black px-2 py-0.5 rounded-lg bg-slate-900 text-white">{{ $check['count'] }}</span>
                            @endif
                            <i class="bi bi-chevron-{{ $expandedHealthKey === $key ? 'up' : 'down' }} text-slate-300"></i>
                        </div>
                    </div>

                    {{-- Kachel Body (Quick Actions) --}}
                    @if($expandedHealthKey === $key)
                        <div class="border-t border-slate-100 bg-slate-50/50 p-4 animate-in slide-in-from-top-2 duration-200">

                            {{-- LAGERBESTAND LÖSEN --}}
                            @if($key === 'inventory')
                                <div class="space-y-3">
                                    @foreach($check['data'] as $prod)
                                        <div class="flex items-center justify-between bg-white p-3 rounded-2xl border border-slate-100">
                                            <span class="text-[10px] font-bold text-slate-700 truncate mr-2">{{ $prod->name }}</span>
                                            <div class="flex items-center gap-2">
                                                <input type="number" wire:model="stockUpdate.{{ $prod->id }}" placeholder="{{ $prod->quantity }}" class="w-16 h-8 text-[10px] font-black rounded-lg border-slate-200 bg-slate-50 text-center focus:ring-blue-500">
                                                <button wire:click="updateStock('{{ $prod->id }}')" class="bg-slate-900 text-white px-2 py-1.5 rounded-lg text-[9px] font-bold">Fix</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- SONDERAUSGABEN / BELEGE LÖSEN --}}
                            @elseif($key === 'special_issues')
                                <div class="space-y-3">
                                    @foreach($check['data'] as $issue)
                                        <div class="bg-white p-3 rounded-2xl border border-slate-100">
                                            <div class="flex justify-between mb-2">
                                                <span class="text-[10px] font-bold text-slate-800">{{ $issue->title }}</span>
                                                <span class="text-[9px] text-rose-500 font-black">{{ number_format($issue->amount, 2, ',', '.') }} €</span>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <input type="file" wire:model="uploadFile" id="upload-special-{{ $issue->id }}" class="text-[9px] w-full file:bg-blue-600 file:text-white file:border-0 file:rounded-lg file:px-3 file:py-1 file:mr-2">
                                                @error('uploadFile') <span class="text-[9px] text-red-500 font-bold">{{ $message }}</span> @enderror
                                                <div wire:loading wire:target="uploadFile" class="text-[9px] text-blue-500 font-bold">Datei wird vorbereitet...</div>

                                                <button wire:click="uploadSpecialReceipt('{{ $issue->id }}')"
                                                        wire:loading.attr="disabled"
                                                        class="w-full bg-slate-900 text-white py-1.5 rounded-lg text-[10px] font-black hover:bg-slate-800 transition-colors disabled:opacity-50">
                                                    <span wire:loading.remove wire:target="uploadSpecialReceipt('{{ $issue->id }}')">Hinterlegen</span>
                                                    <span wire:loading wire:target="uploadSpecialReceipt('{{ $issue->id }}')">Speichert...</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- VERTRÄGE LÖSEN --}}
                            @elseif($key === 'contracts')
                                <div class="space-y-3">
                                    @foreach($check['data'] as $item)
                                        <div class="bg-white p-3 rounded-2xl border border-slate-100">
                                            <div class="flex justify-between mb-2 text-[10px]">
                                                <span class="font-bold text-slate-800">{{ $item->name }}</span>
                                                <span class="text-slate-400 italic">({{ $item->group->name }})</span>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <input type="file" wire:model="uploadFile" id="upload-contract-{{ $item->id }}" class="text-[9px] w-full file:bg-emerald-600 file:text-white file:border-0 file:rounded-lg file:px-3 file:py-1 file:mr-2">
                                                @error('uploadFile') <span class="text-[9px] text-red-500 font-bold">{{ $message }}</span> @enderror
                                                <div wire:loading wire:target="uploadFile" class="text-[9px] text-emerald-500 font-bold">Datei wird vorbereitet...</div>

                                                <button wire:click="uploadContract('{{ $item->id }}')"
                                                        wire:loading.attr="disabled"
                                                        class="w-full bg-slate-900 text-white py-1.5 rounded-lg text-[10px] font-black hover:bg-slate-800 transition-colors disabled:opacity-50">
                                                    <span wire:loading.remove wire:target="uploadContract('{{ $item->id }}')">Vertrag hochladen</span>
                                                    <span wire:loading wire:target="uploadContract('{{ $item->id }}')">Speichert...</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

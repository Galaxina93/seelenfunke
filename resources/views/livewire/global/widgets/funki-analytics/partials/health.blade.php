<div class="bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] p-5 md:p-8 shadow-2xl border border-gray-800 relative overflow-hidden group w-full">
    <div class="hidden sm:block absolute top-6 right-6 text-gray-600 hover:text-primary transition-colors cursor-help" title="Kritische Systemzustände.">
        <i class="solar-info-circle-bold-duotone text-2xl"></i>
    </div>

    <div class="flex justify-between items-center mb-6 md:mb-8">
        <h3 class="text-xs md:text-sm font-black text-gray-500 uppercase tracking-[0.2em]">Operativer Status</h3>
        <span class="text-[9px] md:text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-800 px-3 py-1 md:px-4 md:py-1.5 rounded-full border border-gray-700">Live Action</span>
    </div>

    <div class="space-y-4 md:space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">
            @foreach($healthChecks as $key => $check)
                <div class="bg-gray-950 border border-gray-800 rounded-2xl md:rounded-3xl overflow-hidden shadow-inner transition-all {{ $expandedHealthKey === $key ? 'ring-2 ring-primary/50 ring-offset-0' : 'hover:border-primary/30' }}">
                    <div wire:click="toggleHealthCard('{{ $key }}')" class="p-4 md:p-5 cursor-pointer flex justify-between items-center transition-colors">
                        <div class="flex gap-3 md:gap-4 items-center min-w-0">
                            <div class="p-2.5 md:p-3.5 rounded-xl md:rounded-2xl shrink-0 flex items-center justify-center {{ $check['status'] === 'success' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20 shadow-[0_0_15px_rgba(239,68,68,0.3)] animate-pulse' }}">
                                <i class="bi {{ $check['icon'] }} text-lg md:text-xl"></i>
                            </div>
                            <div class="text-left min-w-0 pr-2">
                                <h4 class="text-[10px] md:text-xs font-black text-white uppercase tracking-tighter truncate">{{ $check['title'] }}</h4>
                                <p class="text-[9px] md:text-[10px] text-gray-400 font-medium leading-tight mt-0.5 md:mt-1 truncate">{{ $check['message'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 md:gap-3 shrink-0">
                            @if($check['count'] > 0)
                                <span class="text-[9px] md:text-[10px] font-black px-2 py-0.5 md:px-2.5 md:py-1 rounded-lg bg-primary text-gray-900 shadow-glow">{{ $check['count'] }}</span>
                            @endif
                            <i class="bi bi-chevron-{{ $expandedHealthKey === $key ? 'up' : 'down' }} text-gray-500 text-sm md:text-base"></i>
                        </div>
                    </div>

                    @if($expandedHealthKey === $key)
                        <div class="border-t border-gray-800 bg-gray-900/50 p-4 md:p-5 animate-in slide-in-from-top-2 duration-200">

                            @if($key === 'inventory')
                                <div class="space-y-3">
                                    @foreach($check['data'] as $prod)
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-950 p-3.5 rounded-2xl border border-gray-800 shadow-inner gap-3">
                                            <span class="text-[10px] font-bold text-gray-300 truncate sm:mr-2">{{ $prod->name }}</span>
                                            <div class="flex items-center gap-3 shrink-0">
                                                <input type="number" wire:model="stockUpdate.{{ $prod->id }}" placeholder="{{ $prod->quantity }}" class="w-16 h-8 text-[10px] font-black rounded-lg border-gray-700 bg-gray-900 text-white text-center focus:ring-primary focus:border-primary">
                                                <button wire:click="updateStock('{{ $prod->id }}')" class="bg-primary text-gray-900 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest hover:scale-105 transition-transform shadow-glow">Fix</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($key === 'special_issues')
                                <div class="space-y-3">
                                    @foreach($check['data'] as $issue)
                                        <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800 shadow-inner">
                                            <div class="flex justify-between items-start mb-3 gap-2">
                                                <span class="text-[10px] font-bold text-white truncate">{{ $issue->title }}</span>
                                                <span class="text-[10px] text-red-400 font-black px-2 py-0.5 bg-red-500/10 rounded-md border border-red-500/20 shrink-0">{{ number_format($issue->amount, 2, ',', '.') }} €</span>
                                            </div>
                                            <div class="flex flex-col gap-3">
                                                <input type="file" wire:model="uploadFile" id="upload-special-{{ $issue->id }}" class="text-[9px] w-full text-gray-400 file:bg-gray-800 file:text-white file:border file:border-gray-700 file:rounded-lg file:px-3 file:py-1.5 file:mr-2 file:hover:bg-gray-700 file:transition-colors file:cursor-pointer">
                                                @error('uploadFile') <span class="text-[9px] text-red-400 font-bold">{{ $message }}</span> @enderror
                                                <div wire:loading wire:target="uploadFile" class="text-[9px] text-primary font-bold animate-pulse">Datei wird vorbereitet...</div>
                                                <button wire:click="uploadSpecialReceipt('{{ $issue->id }}')" wire:loading.attr="disabled" class="w-full bg-primary text-gray-900 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-dark transition-colors shadow-glow disabled:opacity-50">
                                                    <span wire:loading.remove wire:target="uploadSpecialReceipt('{{ $issue->id }}')">Beleg hinterlegen</span>
                                                    <span wire:loading wire:target="uploadSpecialReceipt('{{ $issue->id }}')">Speichert...</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($key === 'contracts')
                                <div class="space-y-3">
                                    @foreach($check['data'] as $item)
                                        <div class="bg-gray-950 p-4 rounded-2xl border border-gray-800 shadow-inner">
                                            <div class="flex justify-between items-start mb-3 gap-2 text-[10px]">
                                                <span class="font-bold text-white truncate">{{ $item->name }}</span>
                                                <span class="text-gray-500 italic bg-gray-900 px-2 py-0.5 rounded-md border border-gray-800 shrink-0">{{ $item->group->name }}</span>
                                            </div>
                                            <div class="flex flex-col gap-3">
                                                <input type="file" wire:model="uploadFile" id="upload-contract-{{ $item->id }}" class="text-[9px] w-full text-gray-400 file:bg-gray-800 file:text-white file:border file:border-gray-700 file:rounded-lg file:px-3 file:py-1.5 file:mr-2 file:hover:bg-gray-700 file:transition-colors file:cursor-pointer">
                                                @error('uploadFile') <span class="text-[9px] text-red-400 font-bold">{{ $message }}</span> @enderror
                                                <div wire:loading wire:target="uploadFile" class="text-[9px] text-primary font-bold animate-pulse">Datei wird vorbereitet...</div>
                                                <button wire:click="uploadContract('{{ $item->id }}')" wire:loading.attr="disabled" class="w-full bg-primary text-gray-900 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-dark transition-colors shadow-glow disabled:opacity-50">
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

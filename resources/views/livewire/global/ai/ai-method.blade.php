<div>
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h4 class="text-sm font-medium text-white flex items-center gap-2">
                <i class="bi bi-tools text-cyan-500"></i> Aktive Werkzeuge ({{ count($methods) }})
            </h4>
            <p class="text-xs text-gray-500 mt-1">Diese Werkzeuge stehen der KI in den zugewiesenen Bereichen zur Verfügung.</p>
        </div>
        <div class="relative w-full sm:w-64">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="bi bi-search text-gray-500"></i>
            </div>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Durchsuchen..." class="w-full bg-gray-950/50 border border-gray-800 rounded-lg py-1.5 pl-9 pr-3 text-xs text-gray-300 placeholder-gray-600 focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/50 transition-all">
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($methods as $method)
            <div x-data="{ open: false }" class="bg-gray-950/30 border border-gray-800 hover:border-gray-700 rounded-lg p-3 transition-colors flex flex-col h-full">
                <div class="flex items-start justify-between mb-2 cursor-pointer select-none" @click="open = !open">
                    <h5 class="font-bold text-gray-200 text-xs font-mono group-hover:text-primary transition-colors pr-2">
                        {{ $method['name'] }}
                    </h5>
                    <div class="flex items-center gap-2">
                        @if(($method['usage_count'] ?? 0) > 0)
                            <span class="text-[9px] bg-primary/20 border border-primary/30 text-primary px-1.5 py-0.5 rounded-full">{{ $method['usage_count'] }}x</span>
                        @endif
                        <i class="bi text-gray-600 text-[10px] transition-transform duration-200" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </div>
                </div>
                
                <div x-show="open" x-transition class="mt-1">
                    <p class="text-[10px] text-gray-400 mb-2 leading-relaxed">
                        {{ $method['description'] }}
                    </p>
                    
                    @if(!empty($method['parameters']))
                        <div class="bg-black/30 rounded border border-gray-800/50 p-2 mt-auto">
                            <ul class="text-[9px] text-gray-500 space-y-1 font-mono">
                                @foreach($method['parameters'] as $paramName => $paramData)
                                    <li class="flex flex-col gap-0.5">
                                        <span class="text-primary/80 font-semibold">{{ $paramName }}:</span>
                                        <span class="text-gray-500 break-words leading-tight">{{ $paramData['description'] ?? '-' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

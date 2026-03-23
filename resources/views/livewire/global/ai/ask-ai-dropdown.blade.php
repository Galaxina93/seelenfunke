<div class="flex items-center bg-gray-900/50 border border-purple-500/20 rounded-xl overflow-hidden pl-2 shadow-[0_0_15px_rgba(168,85,247,0.15)]">
    @php
        $selectedId = $this->{$model ?? 'selectedAgentId'};
        $selectedAgent = collect($availableAgents)->firstWhere('id', $selectedId);
    @endphp
    @if($selectedAgent && !empty($selectedAgent['icon']))
        <div class="pl-2 pr-1 text-purple-400 flex items-center justify-center transition-all">
            @if(str_starts_with($selectedAgent['icon'], 'bi-'))
                <i class="{{ $selectedAgent['icon'] }} text-lg drop-shadow-[0_0_5px_currentColor]"></i>
            @elseif(str_starts_with(trim($selectedAgent['icon']), '<svg'))
                <div class="w-5 h-5 [&>svg]:w-full [&>svg]:h-full drop-shadow-[0_0_5px_currentColor]">{!! $selectedAgent['icon'] !!}</div>
            @else
                <x-dynamic-component :component="'heroicon-o-' . $selectedAgent['icon']" class="w-5 h-5 drop-shadow-[0_0_5px_currentColor]" />
            @endif
        </div>
    @else
        <div class="pl-2 pr-1 text-purple-400 flex items-center justify-center">
            <x-heroicon-o-cpu-chip class="w-5 h-5 drop-shadow-[0_0_5px_currentColor]" />
        </div>
    @endif
    <div class="text-xs font-semibold text-purple-400 uppercase tracking-wider pr-2 border-r border-gray-800 hidden sm:block ml-2">Agent</div>
    <select wire:model.live="{{ $model ?? 'selectedAgentId' }}" class="bg-transparent text-sm py-2 pl-2 pr-6 focus:outline-none cursor-pointer text-gray-300 appearance-none min-w-[200px]">
        <option value="" class="bg-gray-900 text-white">Agent wählen...</option>
        @foreach($availableAgents as $agent)
            <option value="{{ $agent['id'] }}" class="bg-gray-900 text-white">
                {{ $agent['name'] }} @if(!empty($agent['role']) && !empty($agent['role']['name']))- {{ $agent['role']['name'] }}@endif
            </option>
        @endforeach
    </select>
    <button wire:click="{{ $actionMethod }}" wire:loading.attr="disabled" class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-500/10 to-transparent hover:from-purple-500/20 text-purple-400 font-medium transition-all disabled:opacity-50 border-l border-gray-800 focus:outline-none">
        <span wire:loading.remove wire:target="{{ $actionMethod }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </span>
        <span wire:loading wire:target="{{ $actionMethod }}" class="animate-spin w-5 h-5 rounded-full border-t-2 border-r-2 border-purple-500"></span>
        <span class="text-sm tracking-wide">{{ $buttonText ?? 'Analysieren' }}</span>
    </button>
</div>

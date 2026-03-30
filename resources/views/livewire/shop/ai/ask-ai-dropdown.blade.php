    @php
        $selectedId = $this->{$model ?? 'selectedAgentId'};
        $selectedAgent = collect($availableAgents)->firstWhere('id', $selectedId);
        
        $colorTheme = $themeColor ?? 'purple-500';

        $styles = match($colorTheme) {
            'blue-500' => ['border' => 'border-blue-500/20', 'shadow' => 'shadow-[0_0_15px_rgba(59,130,246,0.15)]', 'text' => 'text-blue-400', 'gradient' => 'from-blue-500/10 hover:from-blue-500/20', 'spinner' => 'border-blue-500'],
            'emerald-500' => ['border' => 'border-emerald-500/20', 'shadow' => 'shadow-[0_0_15px_rgba(16,185,129,0.15)]', 'text' => 'text-emerald-400', 'gradient' => 'from-emerald-500/10 hover:from-emerald-500/20', 'spinner' => 'border-emerald-500'],
            'amber-500' => ['border' => 'border-amber-500/20', 'shadow' => 'shadow-[0_0_15px_rgba(245,158,11,0.15)]', 'text' => 'text-amber-400', 'gradient' => 'from-amber-500/10 hover:from-amber-500/20', 'spinner' => 'border-amber-500'],
            'red-500' => ['border' => 'border-red-500/20', 'shadow' => 'shadow-[0_0_15px_rgba(239,68,68,0.15)]', 'text' => 'text-red-400', 'gradient' => 'from-red-500/10 hover:from-red-500/20', 'spinner' => 'border-red-500'],
            'rose-500' => ['border' => 'border-rose-500/20', 'shadow' => 'shadow-[0_0_15px_rgba(244,63,94,0.15)]', 'text' => 'text-rose-400', 'gradient' => 'from-rose-500/10 hover:from-rose-500/20', 'spinner' => 'border-rose-500'],
            'cyan-500' => ['border' => 'border-cyan-500/20', 'shadow' => 'shadow-[0_0_15px_rgba(6,182,212,0.15)]', 'text' => 'text-cyan-400', 'gradient' => 'from-cyan-500/10 hover:from-cyan-500/20', 'spinner' => 'border-cyan-500'],
            'primary' => ['border' => 'border-primary/30', 'shadow' => 'shadow-[0_0_15px_rgba(197,160,89,0.15)]', 'text' => 'text-primary', 'gradient' => 'from-primary/10 hover:from-primary/20', 'spinner' => 'border-primary'],
            default => ['border' => 'border-purple-500/20', 'shadow' => 'shadow-[0_0_15px_rgba(168,85,247,0.15)]', 'text' => 'text-purple-400', 'gradient' => 'from-purple-500/10 hover:from-purple-500/20', 'spinner' => 'border-purple-500'],
        };
    @endphp

<div class="flex items-center bg-gray-900/50 border {{ $styles['border'] }} rounded-xl overflow-hidden pl-2 {{ $styles['shadow'] }} w-full sm:w-auto">
    @if($selectedAgent && !empty($selectedAgent['icon']))
        <div class="pl-2 pr-1 {{ $styles['text'] }} flex items-center justify-center transition-all">
            @if(str_starts_with($selectedAgent['icon'], 'bi-'))
                <i class="{{ $selectedAgent['icon'] }} text-lg drop-shadow-[0_0_5px_currentColor]"></i>
            @elseif(str_starts_with(trim($selectedAgent['icon']), '<svg'))
                <div class="w-5 h-5 [&>svg]:w-full [&>svg]:h-full drop-shadow-[0_0_5px_currentColor]">{!! $selectedAgent['icon'] !!}</div>
            @else
                <x-dynamic-component :component="'heroicon-o-' . $selectedAgent['icon']" class="w-5 h-5 drop-shadow-[0_0_5px_currentColor]" />
            @endif
        </div>
    @else
        <div class="pl-2 pr-1 {{ $styles['text'] }} flex items-center justify-center">
            <x-heroicon-o-cpu-chip class="w-5 h-5 drop-shadow-[0_0_5px_currentColor]" />
        </div>
    @endif
    <div class="text-[10px] sm:text-xs font-semibold {{ $styles['text'] }} uppercase tracking-wider pr-2 border-r border-gray-800 hidden sm:block ml-2">Agent</div>
    <select wire:model.live="{{ $model ?? 'selectedAgentId' }}" class="bg-transparent text-xs sm:text-sm py-2 pl-2 pr-6 focus:outline-none cursor-pointer text-gray-300 appearance-none min-w-0 sm:min-w-[200px] w-full truncate">
        <option value="" class="bg-gray-900 text-white">Agent wählen...</option>
        @foreach($availableAgents as $agent)
            <option value="{{ $agent['id'] }}" class="bg-gray-900 text-white">
                {{ $agent['name'] }} @if(!empty($agent['role']) && !empty($agent['role']['name']))- {{ $agent['role']['name'] }}@endif
            </option>
        @endforeach
    </select>
    <button wire:click="{{ $actionMethod }}" wire:loading.attr="disabled" class="whitespace-nowrap flex items-center gap-2 px-3 sm:px-4 py-2 bg-gradient-to-r {{ $styles['gradient'] }} {{ $styles['text'] }} font-medium transition-all disabled:opacity-50 border-l border-gray-800 focus:outline-none">
        <span wire:loading.remove wire:target="{{ $actionMethod }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </span>
        <span wire:loading wire:target="{{ $actionMethod }}" class="animate-spin w-5 h-5 rounded-full border-t-2 border-r-2 {{ $styles['spinner'] }}"></span>
        <span class="text-sm tracking-wide">{{ $buttonText ?? 'Analysieren' }}</span>
    </button>
</div>

{{-- FILE: resources\views\components\forms\list-item.blade.php --}}
@props(['route', 'title', 'pageName', 'icon', 'noColor' => false, 'themeColor' => null, 'dotState' => null, 'dotEvent' => null, 'dotClearEvent' => null])

@php
    $currentPath = request()->path();
    $routePath = ltrim(parse_url($route, PHP_URL_PATH), '/');
    $isActive = $routePath === $currentPath;
    
    $activeClasses = 'bg-primary/10 text-primary dark:shadow-[0_0_15px_rgba(197,160,89,0.15)]';
    $activeIconClasses = 'text-primary';
    
    $inactiveClasses = 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100/80 dark:hover:bg-white/5';
    $inactiveIconClasses = 'text-gray-400 dark:text-gray-500 group-hover:text-gray-900 dark:group-hover:text-white group-hover:scale-110';

    if ($themeColor) {
        $activeClasses = match($themeColor) {
            'blue-500' => 'bg-blue-500/10 text-blue-500 dark:text-blue-400 dark:shadow-[0_0_15px_rgba(59,130,246,0.15)]',
            'purple-500' => 'bg-purple-500/10 text-purple-500 dark:text-purple-400 dark:shadow-[0_0_15px_rgba(168,85,247,0.15)]',
            'amber-500' => 'bg-amber-500/10 text-amber-500 dark:text-amber-400 dark:shadow-[0_0_15px_rgba(245,158,11,0.15)]',
            'emerald-500' => 'bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 dark:shadow-[0_0_15px_rgba(16,185,129,0.15)]',
            'red-500' => 'bg-red-500/10 text-red-500 dark:text-red-400 dark:shadow-[0_0_15px_rgba(239,68,68,0.15)]',
            'rose-500' => 'bg-rose-500/10 text-rose-500 dark:text-rose-400 dark:shadow-[0_0_15px_rgba(244,63,94,0.15)]',
            'cyan-500' => 'bg-cyan-500/10 text-cyan-500 dark:text-cyan-400 dark:shadow-[0_0_15px_rgba(6,182,212,0.15)]',
            'indigo-500' => 'bg-indigo-500/10 text-indigo-500 dark:text-indigo-400 dark:shadow-[0_0_15px_rgba(99,102,241,0.15)]',
            'teal-500' => 'bg-teal-500/10 text-teal-500 dark:text-teal-400 dark:shadow-[0_0_15px_rgba(20,184,166,0.15)]',
            'orange-500' => 'bg-orange-500/10 text-orange-500 dark:text-orange-400 dark:shadow-[0_0_15px_rgba(249,115,22,0.15)]',
            'yellow-500' => 'bg-yellow-500/10 text-yellow-500 dark:text-yellow-400 dark:shadow-[0_0_15px_rgba(234,179,8,0.15)]',
            'green-500' => 'bg-green-500/10 text-green-500 dark:text-green-400 dark:shadow-[0_0_15px_rgba(34,197,94,0.15)]',
            'sky-500' => 'bg-sky-500/10 text-sky-500 dark:text-sky-400 dark:shadow-[0_0_15px_rgba(14,165,233,0.15)]',
            'pink-500' => 'bg-pink-500/10 text-pink-500 dark:text-pink-400 dark:shadow-[0_0_15px_rgba(236,72,153,0.15)]',
            default => $activeClasses
        };
        $activeIconClasses = 'text-' . $themeColor;
        $inactiveIconClasses = 'text-' . $themeColor . ' group-hover:scale-110';
    }

    if ($noColor) {
        $activeClasses = 'bg-gray-900/10 dark:bg-white/10 text-gray-900 dark:text-white dark:shadow-[0_0_15px_rgba(255,255,255,0.05)]';
        $activeIconClasses = 'text-gray-900 dark:text-white';
        $inactiveIconClasses = 'text-gray-400 dark:text-gray-500 group-hover:text-gray-900 dark:group-hover:text-white group-hover:scale-110';
    }
@endphp

<li @if($dotState) x-data="{ unread: {{ $dotState }} }" @endif
    @if($dotEvent) @{{ $dotEvent }}.window="unread = true" @endif
    @if($dotClearEvent) @{{ $dotClearEvent }}.window="unread = false" @endif>
    <a href="{{ $route }}" wire:navigate
       @if($dotState) @click="unread = false; {{ $dotState }} = false" @endif
       class="group flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isActive ? $activeClasses : $inactiveClasses }}">
        <div class="relative">
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 shrink-0 transition-transform duration-300 {{ $isActive ? $activeIconClasses : $inactiveIconClasses }}"/>
            @if($dotState)
                <span x-show="unread" style="display: none;" class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 dark:shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
                </span>
            @endif
        </div>
        <span class="truncate">{{ $title }}</span>
    </a>
</li>

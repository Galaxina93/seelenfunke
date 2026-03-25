{{-- FILE: resources\views\components\forms\list-item.blade.php --}}
@props(['route', 'title', 'pageName', 'icon', 'noColor' => false, 'themeColor' => null])

@php
    $currentPage = basename(request()->path());
    $isActive = $pageName === $currentPage;
    
    $activeClasses = 'bg-primary/10 text-primary shadow-[0_0_15px_rgba(197,160,89,0.15)]';
    $activeIconClasses = 'text-primary';
    
    $inactiveClasses = 'text-gray-400 hover:text-white hover:bg-white/5';
    $inactiveIconClasses = 'text-gray-500 group-hover:text-white group-hover:scale-110';

    if ($themeColor) {
        $activeClasses = match($themeColor) {
            'blue-500' => 'bg-blue-500/10 text-blue-400 shadow-[0_0_15px_rgba(59,130,246,0.15)]',
            'purple-500' => 'bg-purple-500/10 text-purple-400 shadow-[0_0_15px_rgba(168,85,247,0.15)]',
            'amber-500' => 'bg-amber-500/10 text-amber-400 shadow-[0_0_15px_rgba(245,158,11,0.15)]',
            'emerald-500' => 'bg-emerald-500/10 text-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.15)]',
            'rose-500' => 'bg-rose-500/10 text-rose-400 shadow-[0_0_15px_rgba(244,63,94,0.15)]',
            'cyan-500' => 'bg-cyan-500/10 text-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.15)]',
            default => $activeClasses
        };
        $activeIconClasses = 'text-' . $themeColor;
        $inactiveIconClasses = 'text-' . $themeColor . ' group-hover:scale-110';
    }

    if ($noColor) {
        $activeClasses = 'bg-white/10 text-white shadow-[0_0_15px_rgba(255,255,255,0.05)]';
        $activeIconClasses = 'text-white';
        $inactiveIconClasses = 'text-gray-500 group-hover:text-white group-hover:scale-110';
    }
@endphp

<li>
    <a href="{{ $route }}" class="group flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isActive ? $activeClasses : $inactiveClasses }}">
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 shrink-0 transition-transform duration-300 {{ $isActive ? $activeIconClasses : $inactiveIconClasses }}"/>
        <span class="truncate">{{ $title }}</span>
    </a>
</li>

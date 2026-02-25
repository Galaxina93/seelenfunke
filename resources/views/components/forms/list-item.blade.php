{{-- FILE: resources\views\components\forms\list-item.blade.php --}}
@props(['route', 'title', 'pageName', 'icon'])

@php
    $currentPage = basename(request()->path());
    $isActive = $pageName === $currentPage;
@endphp

<li>
    <a href="{{ $route }}" class="group flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isActive ? 'bg-primary/10 text-primary shadow-[0_0_15px_rgba(197,160,89,0.15)]' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 shrink-0 transition-transform duration-300 {{ $isActive ? 'text-primary' : 'text-gray-500 group-hover:text-white group-hover:scale-110' }}"/>
        <span class="truncate">{{ $title }}</span>
    </a>
</li>

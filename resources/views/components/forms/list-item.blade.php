@props(['route', 'title', 'pageName', 'icon'])

@php
    $currentPage = basename(request()->path());
@endphp

<li>
    <a href="{{ $route }}" class="@if($pageName === $currentPage) bg-primary text-white @endif text-primary hover:text-white hover:bg-primary group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-6 h-6 text-white"/>
        {{ $title }}
    </a>
</li>

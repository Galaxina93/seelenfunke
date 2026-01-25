@props([
    'title' => '',
    'category' => 'primary', // primary | secondary | danger | link | x
    'type' => 'button',
    'href' => null,
    'wireClick' => null,
    'disabled' => false,
])

@php
    $base = 'inline-flex items-center justify-center font-semibold rounded-lg transition focus:outline-none focus:ring-2 focus:ring-offset-2';
    $classes = match($category) {
        'primary' => 'bg-primary text-white py-2 px-4 hover:bg-primary-dark',
        'secondary' => 'bg-white text-gray-700 border border-gray-300 py-3 px-6 hover:bg-gray-100',
        'danger' => 'bg-red-600 text-white py-3 px-6 hover:bg-red-700',
        'link' => 'text-indigo-600 underline bg-transparent p-0',
        'x' => 'text-red-600 hover:text-green-500 text-lg px-2 py-1',
        default => 'bg-gray-200 text-gray-800 py-3 px-6',
    };

    if ($disabled) {
        $classes .= ' opacity-50 cursor-not-allowed';
    }
@endphp

<div {{ $attributes->class(['inline-block']) }}>
    @if($category === 'link' && $href)
        <a
            href="{{ $href }}"
            @if($wireClick) wire:click.prevent="{{ $wireClick }}" @endif
            class="{{ $base }} {{ $classes }}"
            aria-label="{{ $title }}"
        >
            {{ $title }}
        </a>
    @else
        <button
            type="{{ $type }}"
            @if($wireClick) wire:click="{{ $wireClick }}" @endif
            @if($disabled) disabled @endif
            class="{{ $base }} {{ $classes }}"
        >
            @if($category === 'x')
                ✕
            @else
                <span class="inline-flex items-center gap-2">
                    {{ $title }}
                    <span wire:loading wire:target="{{ $wireClick ?: $type }}">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </span>
                </span>
            @endif
        </button>
    @endif
</div>

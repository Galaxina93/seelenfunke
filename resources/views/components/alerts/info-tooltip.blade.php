@php
    $tooltipContent = $text ?? ($infoTexts[$key ?? ''] ?? null);
@endphp
@if(!empty($tooltipContent))
    <div x-data="{ show: false }" class="relative inline-flex items-center">
        <span role="button" tabindex="0" @mouseenter="show = true" @mouseleave="show = false" @click.prevent.stop="show = !show" class="inline-flex items-center text-gray-400 hover:text-[var(--theme-color)] transition-colors focus:outline-none cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
        </span>
        <div x-show="show" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-72 p-3 bg-gray-900 text-white text-[11px] leading-relaxed rounded-xl shadow-xl z-50 text-left font-normal normal-case tracking-normal pointer-events-none">
            {{ $tooltipContent }}
            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
        </div>
    </div>
@endif

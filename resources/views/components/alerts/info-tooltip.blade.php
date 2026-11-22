@php
    $tooltipContent = $text ?? ($infoTexts[$key ?? ''] ?? null);
@endphp
@if(!empty($tooltipContent))
    <div x-data="{ show: false }"
         class="relative inline-flex items-center"
         @mouseenter="show = true"
         @mouseleave="show = false"
         @click.outside="show = false">
        <button type="button"
                @click.prevent.stop="show = !show"
                aria-label="Info anzeigen"
                class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300 text-[10px] font-black inline-flex items-center justify-center hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-colors focus:outline-none cursor-pointer shrink-0">
            !
        </button>
        <div x-show="show"
             x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-60 p-2.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 text-[11px] leading-snug rounded-xl shadow-2xl z-50 text-left font-normal normal-case tracking-normal pointer-events-none">
            {{ $tooltipContent }}
        </div>
    </div>
@endif

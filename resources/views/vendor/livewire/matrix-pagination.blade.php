@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-gray-900/50 border border-gray-800 cursor-default leading-5 rounded-md">
                    {!! '|&lt;' !!}
                </span>
            @else
                <button wire:click="previousPage" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-primary bg-gray-900/50 border border-primary/30 leading-5 rounded-md hover:text-white hover:bg-primary/20 hover:border-primary shadow-[0_0_15px_rgba(197,160,89,0.1)] focus:outline-none focus:ring ring-primary/30 focus:border-primary/50 active:bg-primary/30 transition ease-in-out duration-150">
                    {!! '|&lt;' !!}
                </button>
            @endif

            @if ($paginator->hasMorePages())
                <button wire:click="nextPage" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-primary bg-gray-900/50 border border-primary/30 leading-5 rounded-md hover:text-white hover:bg-primary/20 hover:border-primary shadow-[0_0_15px_rgba(197,160,89,0.1)] focus:outline-none focus:ring ring-primary/30 focus:border-primary/50 active:bg-primary/30 transition ease-in-out duration-150">
                    {!! '&gt;|' !!}
                </button>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-500 bg-gray-900/50 border border-gray-800 cursor-default leading-5 rounded-md">
                    {!! '&gt;|' !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 leading-5">
                    Zeige
                    <span class="font-medium text-primary shadow-glow">{{ $paginator->firstItem() }}</span>
                    bis
                    <span class="font-medium text-primary shadow-glow">{{ $paginator->lastItem() }}</span>
                    von
                    <span class="font-medium text-primary shadow-glow">{{ $paginator->total() }}</span>
                    Einträgen
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex rtl:flex-row-reverse rounded-md shadow-[0_0_15px_rgba(0,0,0,0.5)]">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-600 bg-gray-900 border border-gray-800 cursor-default rounded-l-md leading-5" aria-hidden="true">
                                <svg class="w-4 h-4" transform="scale(-1, 1)" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </span>
                        </span>
                    @else
                        <button wire:click="previousPage" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" rel="prev" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-primary/70 bg-gray-900 border border-gray-700/50 rounded-l-md leading-5 hover:text-primary hover:bg-gray-800 hover:border-primary/50 focus:z-10 focus:outline-none focus:border-primary/50 focus:ring ring-primary/30 active:bg-gray-800 active:text-primary transition ease-in-out duration-150" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-4 h-4" transform="scale(-1, 1)" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-mono text-gray-500 bg-gray-900 border border-gray-800 cursor-default leading-5">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <span wire:key="paginator-{{ $paginator->getPageName() }}-{{ $this->getId() }}-page{{ $page }}">
                                    @if ($page == $paginator->currentPage())
                                        <span aria-current="page">
                                            <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-mono font-bold text-gray-900 bg-primary border border-primary cursor-default leading-5 shadow-[0_0_15px_rgba(197,160,89,0.3)]">{{ $page }}</span>
                                        </span>
                                    @else
                                        <button wire:click="gotoPage({{ $page }})" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-mono text-gray-400 bg-gray-900 border border-gray-700/50 leading-5 hover:text-primary hover:bg-gray-800 hover:border-primary/30 focus:z-10 focus:outline-none focus:border-primary/50 focus:ring ring-primary/30 active:bg-gray-800 active:text-primary transition ease-in-out duration-150" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                            {{ $page }}
                                        </button>
                                    @endif
                                </span>
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <button wire:click="nextPage" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" rel="next" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-primary/70 bg-gray-900 border border-gray-700/50 rounded-r-md leading-5 hover:text-primary hover:bg-gray-800 hover:border-primary/50 focus:z-10 focus:outline-none focus:border-primary/50 focus:ring ring-primary/30 active:bg-gray-800 active:text-primary transition ease-in-out duration-150" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-gray-600 bg-gray-900 border border-gray-800 cursor-default rounded-r-md leading-5" aria-hidden="true">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif

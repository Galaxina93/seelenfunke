@if ($paginator->hasPages())
    <div class="flex-1 flex justify-between sm:hidden">
        @if ($paginator->currentPage() > 1)
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1 bg-primary text-white rounded-l-md hover:bg-primary-dark">
                <i class="fa fa-angle-left"></i>
            </a>
        @endif
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1 bg-primary text-white rounded-r-md hover:bg-primary-dark">
                <i class="fa fa-angle-right"></i>
            </a>
        @endif
    </div>
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-700 leading-5 px-4">
                Zeige
                <span class="font-medium">{{ $paginator->firstItem() }}</span>
                bis
                <span class="font-medium">{{ $paginator->lastItem() }}</span>
                von insgesamt
                <span class="font-medium">{{ $paginator->total() }}</span>
                Ergebnissen
            </p>
        </div>
        @if ($paginator->lastPage() > 1)
            <div>
                <nav class="relative z-0 inline-flex shadow-sm">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="px-3 py-1 bg-gray-200 text-gray-600">{{ $element }}</span>
                        @endif
                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <a href="{{ $url }}" class="px-3 py-1 bg-white border border-gray-300 text-sm leading-5 font-medium text-gray-700 {{ $page == $paginator->currentPage() ? 'bg-gray-200' : '' }} hover:bg-gray-200">
                                        {{ $page }}
                                    </a>
                                @endforeach
                            @endif
                    @endforeach
                </nav>
            </div>
        @endif
        <div>
            @if ($paginator->currentPage() > 1)
                <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1 text-white rounded-r-md hover:bg-primary-dark flex items-center">
                    <x-heroicon-m-arrow-long-left x-show="show" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100"/>
                </a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1 text-white rounded-r-md hover:bg-primary-dark flex items-center">
                    <x-heroicon-m-arrow-long-right x-show="show" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100"/>
                </a>
            @endif
        </div>
    </div>
@endif

<x-sections.vertical-nav>
    @php
        $currentPath = request()->path();
        $structure = \App\Services\Navigation\BackendNavigationService::getStructure();
    @endphp

    @foreach($structure as $section)
        <li>
            @if($section['section'])
                <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">{{ $section['section'] }}</div>
            @endif
            <ul role="list" class="-mx-2 space-y-1">
                @foreach($section['items'] as $item)
                    @if($item['type'] === 'single')
                        @if(isset($item['is_ticket']) && $item['is_ticket'])
                            {{-- Ticketsystem inkl. rotem Benachrichtigungspunkt --}}
                            <li x-data="{ unread: hasUnreadSupport }"
                                @admin-ticket-badge-update.window="unread = true"
                                @clear-admin-ticket-badge.window="unread = false">

                                <a href="{{ $item['route'] }}" @click="unread = false; hasUnreadSupport = false" class="group flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ '/' . ltrim($currentPath, '/') === $item['route'] ? 'bg-primary/10 text-primary shadow-[0_0_15px_rgba(197,160,89,0.15)]' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                    <div class="relative">
                                        <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-5 h-5 shrink-0 transition-transform duration-300 {{ '/' . ltrim($currentPath, '/') === $item['route'] ? 'text-primary' : 'text-gray-500 group-hover:text-white group-hover:scale-110' }}" />

                                        {{-- Nutzt nun die isolierte 'unread' Variable, die sich sofort aktualisiert --}}
                                        <span x-show="unread" style="display: none;" class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
                                        </span>
                                    </div>
                                    <span class="truncate">{{ $item['title'] }}</span>
                                </a>
                            </li>
                        @else
                            <x-forms.list-item route="{{ $item['route'] }}" title="{{ $item['title'] }}" pageName="{{ basename($item['route']) }}" icon="{{ $item['icon'] }}" />
                        @endif
                    @elseif($item['type'] === 'group')
                        @php
                            $isActive = \App\Services\Navigation\BackendNavigationService::isGroupActive($item, $currentPath);
                        @endphp
                        <li x-data="{ open: {{ $isActive ? 'true' : 'false' }} }">
                            <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="h-5 w-5 shrink-0 transition-colors {{ $isActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                                <span class="flex-1">{{ $item['title'] }}</span>
                                <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                            </button>
                            <ul x-show="open" x-collapse style="{{ $isActive ? '' : 'display: none;' }}" class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                                @foreach($item['children'] as $child)
                                    <x-forms.list-item route="{{ $child['route'] }}" title="{{ $child['title'] }}" pageName="{{ basename($child['route']) }}" icon="{{ $child['icon'] }}" />
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        </li>
    @endforeach
</x-sections.vertical-nav>

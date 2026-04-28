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
                        @php
                            $dotProps = [];
                            if ($item['id'] === 'support-ticket') {
                                $dotProps = [
                                    'dotState' => 'hasUnreadSupport',
                                    'dotEvent' => 'admin-ticket-badge-update',
                                    'dotClearEvent' => 'clear-admin-ticket-badge'
                                ];
                            }
                        @endphp
                        <x-forms.list-item 
                            route="{{ $item['route'] }}" 
                            title="{{ $item['title'] }}" 
                            pageName="{{ basename($item['route']) }}" 
                            icon="{{ $item['icon'] }}" 
                            :noColor="$item['id'] === 'dashboard'" 
                            :dotState="$dotProps['dotState'] ?? null"
                            :dotEvent="$dotProps['dotEvent'] ?? null"
                            :dotClearEvent="$dotProps['dotClearEvent'] ?? null" />
                    @elseif($item['type'] === 'group')
                        @php
                            $isActive = \App\Services\Navigation\BackendNavigationService::isGroupActive($item, $currentPath);
                            
                            // Check if this nav-group has a linked AiDepartment with Agenten
                            $hasAgents = false;
                            $deptColorCss = '';
                            $deptColorName = null;
                            if (isset($item['ai_department_id'])) {
                                $dept = \App\Models\Ai\AiDepartment::where('id', $item['ai_department_id'])
                                                                   ->orWhere('name', $item['title'])
                                                                   ->orWhere('name', rtrim($item['title'], 'e'))
                                                                   ->withCount('agents')->first();
                                if ($dept) {
                                    $hasAgents = $dept->agents_count > 0;
                                    $deptColorName = $dept->color;
                                    // Map department colors to specific tailwind glow classes
                                    $deptColorCss = match($dept->color) {
                                        'blue-500' => 'text-blue-500 drop-shadow-[0_0_8px_rgba(59,130,246,0.8)]',
                                        'purple-500' => 'text-purple-500 drop-shadow-[0_0_8px_rgba(168,85,247,0.8)]',
                                        'amber-500' => 'text-amber-500 drop-shadow-[0_0_8px_rgba(245,158,11,0.8)]',
                                        'emerald-500' => 'text-emerald-500 drop-shadow-[0_0_8px_rgba(16,185,129,0.8)]',
                                        'red-500' => 'text-red-500 drop-shadow-[0_0_8px_rgba(239,68,68,0.8)]',
                                        'rose-500' => 'text-rose-500 drop-shadow-[0_0_8px_rgba(244,63,94,0.8)]',
                                        'cyan-500' => 'text-cyan-500 drop-shadow-[0_0_8px_rgba(6,182,212,0.8)]',
                                        'indigo-500' => 'text-indigo-500 drop-shadow-[0_0_8px_rgba(99,102,241,0.8)]',
                                        'teal-500' => 'text-teal-500 drop-shadow-[0_0_8px_rgba(20,184,166,0.8)]',
                                        'orange-500' => 'text-orange-500 drop-shadow-[0_0_8px_rgba(249,115,22,0.8)]',
                                        'yellow-500' => 'text-yellow-500 drop-shadow-[0_0_8px_rgba(234,179,8,0.8)]',
                                        'green-500' => 'text-green-500 drop-shadow-[0_0_8px_rgba(34,197,94,0.8)]',
                                        'sky-500' => 'text-sky-500 drop-shadow-[0_0_8px_rgba(14,165,233,0.8)]',
                                        'pink-500' => 'text-pink-500 drop-shadow-[0_0_8px_rgba(236,72,153,0.8)]',
                                        'primary' => 'text-primary drop-shadow-[0_0_8px_rgba(197,160,89,0.8)]',
                                        default => 'text-gray-500' // Better default so we can see when it fails instead of forcing emerald
                                    };
                                }
                            }
                            
                            $displayIcon = isset($dept) && $dept->icon ? $dept->icon : $item['icon'];
                        @endphp
                        <li x-data="{ open: {{ $isActive ? 'true' : 'false' }} }">
                            <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                                @if(!empty($deptColorCss))
                                    <x-dynamic-component :component="'heroicon-o-' . $displayIcon" class="h-5 w-5 shrink-0 transition-colors {{ $deptColorCss }} {{ $hasAgents ? 'animate-pulse-slow' : '' }}" />
                                @else
                                    <x-dynamic-component :component="'heroicon-o-' . $displayIcon" class="h-5 w-5 shrink-0 transition-colors {{ $isActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                                @endif
                                <span class="flex-1">{{ $item['title'] }}</span>
                                <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                            </button>
                            <ul x-show="open" x-collapse style="{{ $isActive ? '' : 'display: none;' }}" class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                                @foreach($item['children'] as $child)
                                    @php
                                        $dotProps = [];
                                        if ($child['id'] === 'support-ticket') {
                                            $dotProps = [
                                                'dotState' => 'hasUnreadSupport',
                                                'dotEvent' => 'admin-ticket-badge-update',
                                                'dotClearEvent' => 'clear-admin-ticket-badge'
                                            ];
                                        } elseif ($child['id'] === 'support-chats') {
                                            $dotProps = [
                                                'dotState' => 'hasUnreadCustomerChats',
                                                'dotEvent' => 'admin-customerchat-badge-update',
                                                'dotClearEvent' => 'clear-admin-customerchat-badge'
                                            ];
                                        } elseif ($child['id'] === 'support-contact-form') {
                                            $dotProps = [
                                                'dotState' => 'hasUnreadContactReqs',
                                                'dotEvent' => 'admin-contactreq-badge-update',
                                                'dotClearEvent' => 'clear-admin-contactreq-badge'
                                            ];
                                        }
                                    @endphp
                                    <x-forms.list-item 
                                        route="{{ $child['route'] }}" 
                                        title="{{ $child['title'] }}" 
                                        pageName="{{ basename($child['route']) }}" 
                                        icon="{{ $child['icon'] }}" 
                                        themeColor="{{ $deptColorName }}"
                                        :dotState="$dotProps['dotState'] ?? null"
                                        :dotEvent="$dotProps['dotEvent'] ?? null"
                                        :dotClearEvent="$dotProps['dotClearEvent'] ?? null" />
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        </li>
    @endforeach
</x-sections.vertical-nav>

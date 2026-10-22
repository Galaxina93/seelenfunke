<!-- Internal Agents Modal -->
<div x-data="{ showAgentsModal: false }"
     x-on:open-agents-modal.window="showAgentsModal = true"
     x-on:close-agents-modal.window="showAgentsModal = false">

    <div x-show="showAgentsModal"
         style="display: none;"
         class="fixed inset-0 z-[120] flex items-center justify-center bg-black/60 dark:bg-black/80 backdrop-blur-sm p-4 transition-opacity duration-300"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div class="relative w-full max-w-4xl max-h-[90vh] bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-3xl overflow-hidden shadow-2xl flex flex-col"
             x-transition:enter="ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 translate-y-6 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-6 scale-95"
             @click.away="showAgentsModal = false">

            <!-- Modal Header -->
            <div class="p-5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-900/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center">
                        <x-heroicon-o-cpu-chip class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base sm:text-lg">Interne KI-Agenten</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Verwalte Agenten für den Chat oder erstelle neue Rollen.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="$dispatch('open-agent-manager', { agentId: 'new' }); showAgentsModal = false"
                            class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] hover:bg-[var(--theme-color-20)] text-xs font-bold uppercase tracking-wider transition-colors">
                        <x-heroicon-o-plus class="w-4 h-4" />
                        <span>Agent erschaffen</span>
                    </button>
                    <button @click="showAgentsModal = false" class="p-2 text-gray-400 hover:text-gray-700 dark:hover:text-white rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <x-heroicon-s-x-mark class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Agents Grid -->
            <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($agents as $agent)
                        @php $isActiveForChat = in_array($agent->id, $activeAgentIds); @endphp
                        <div class="bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex flex-col justify-between gap-3 {{ $isActiveForChat ? '!border-[var(--theme-color-50)] shadow-sm dark:shadow-[0_0_15px_rgba(var(--theme-color-rgb),0.1)]' : 'hover:border-gray-300 dark:hover:border-gray-700' }} transition-all">
                            <div class="flex items-start gap-3">
                                <div class="relative shrink-0">
                                    <div class="w-12 h-12 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 overflow-hidden flex items-center justify-center relative shadow-sm">
                                        @if($isActiveForChat)
                                            <span class="absolute inset-0 bg-[var(--theme-color)] opacity-20 animate-pulse-slow z-0"></span>
                                        @endif
                                        @if($agent->profile_picture)
                                            <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') || \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shopverwaltung/images/') || \Illuminate\Support\Str::startsWith($agent->profile_picture, '/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover relative z-10">
                                        @else
                                            <span class="text-sm text-gray-500 font-bold relative z-10">{{ substr($agent->name, 0, 2) }}</span>
                                        @endif
                                    </div>

                                    @if(isset($pingResults[$agent->id]['llm']))
                                        <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-gray-900 z-30 {{ $pingResults[$agent->id]['llm'] === 'Offline' || $pingResults[$agent->id]['llm'] === 'Fehler' ? 'bg-red-500' : ($pingResults[$agent->id]['llm'] === 'Inaktiv' ? 'bg-gray-500' : 'bg-emerald-500') }}" title="LLM: {{ $pingResults[$agent->id]['llm'] }}"></div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">{{ $agent->name }}</h4>
                                        <button @click="$dispatch('open-agent-manager', { agentId: '{{ $agent->id }}' }); showAgentsModal = false"
                                                title="Agent konfigurieren"
                                                class="text-gray-400 hover:text-[var(--theme-color)] p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors">
                                            <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <span class="inline-block text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider bg-gray-200/70 dark:bg-gray-800 text-gray-600 dark:text-gray-400 mt-1">
                                        {{ $agent->role->name ?? 'Keine Rolle' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Chat Toggle Button -->
                            <button wire:click="toggleAgent('{{ $agent->id }}')"
                                    class="w-full py-2 px-3 rounded-xl border text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-2 transition-all {{ $isActiveForChat ? 'bg-[var(--theme-color)] text-white border-[var(--theme-color)] shadow-sm' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color-50)]' }}">
                                <x-heroicon-s-chat-bubble-left-right class="w-3.5 h-3.5" />
                                <span>{{ $isActiveForChat ? 'Im Chat aktiv' : 'Dem Chat beitreten' }}</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Modal Footer (Mobile "+ Agent erschaffen" button) -->
            <div class="sm:hidden p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                <button @click="$dispatch('open-agent-manager', { agentId: 'new' }); showAgentsModal = false"
                        class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl bg-[var(--theme-color)] text-white text-xs font-bold uppercase tracking-wider shadow-sm">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    <span>Neuen Agent erschaffen</span>
                </button>
            </div>
        </div>
    </div>
</div>

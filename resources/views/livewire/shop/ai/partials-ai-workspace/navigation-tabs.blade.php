                    <!-- Navigation Tabs -->
                    <div x-show="!isChatFullScreen" class="bg-white dark:bg-gray-900/80 backdrop-blur-xl border border-gray-200 dark:border-gray-800 rounded-xl px-4 sm:px-5 flex items-center justify-between gap-4 text-xs sm:text-sm tracking-wider font-mono shrink-0 shadow-sm relative overflow-x-auto overflow-y-hidden whitespace-nowrap scrollbar-hide">
                        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-[var(--theme-color-50)] to-transparent pointer-events-none"></div>

                        <div class="flex items-center gap-3 sm:gap-6">
                            <button @click="activeTab = 'workspace'" class="py-3 px-2 shrink-0 relative transition-colors border-b-2" :class="activeTab === 'workspace' ? 'text-[var(--theme-color)] border-[var(--theme-color)] font-bold' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-gray-200'">
                                <x-heroicon-o-squares-2x2 class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Workspace
                            </button>
                            <button @click="activeTab = 'chat'" class="py-3 px-2 shrink-0 relative transition-colors border-b-2" :class="activeTab === 'chat' ? 'text-[var(--theme-color)] border-[var(--theme-color)] font-bold' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-gray-200'">
                                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Chat
                            </button>
                            <button @click="activeTab = 'files'" class="py-3 px-2 shrink-0 relative transition-colors border-b-2" :class="activeTab === 'files' ? 'text-[var(--theme-color)] border-[var(--theme-color)] font-bold' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-gray-200'">
                                <x-heroicon-o-folder-open class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Dateien
                                @if(count($this->fileManagerItems) > 0)
                                    <span class="ml-1 bg-[var(--theme-color)] text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-sm">{{ count($this->fileManagerItems) }}</span>
                                @endif
                            </button>
                            <button @click="activeTab = 'health'" class="py-3 px-2 shrink-0 relative transition-colors border-b-2" :class="activeTab === 'health' ? 'text-[var(--theme-color)] border-[var(--theme-color)] font-bold' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-gray-200'">
                                <x-heroicon-o-heart class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Gesundheit
                            </button>
                            <button @click="activeTab = 'workflows'" class="py-3 px-2 shrink-0 relative transition-colors border-b-2" :class="activeTab === 'workflows' ? 'text-[var(--theme-color)] border-[var(--theme-color)] font-bold' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-gray-200'">
                                <x-heroicon-o-queue-list class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Workflows
                            </button>
                            <button @click="activeTab = 'cronjobs'" class="py-3 px-2 shrink-0 relative transition-colors border-b-2" :class="activeTab === 'cronjobs' ? 'text-[var(--theme-color)] border-[var(--theme-color)] font-bold' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-900 dark:hover:text-gray-200'">
                                <x-heroicon-o-clock class="w-4 h-4 inline-block -mt-0.5 mr-1"/> Cronjobs
                            </button>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0 py-1.5">
                            <button type="button" wire:click="$set('activeWorkspaceView', 'knowledge-base')" class="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[11px] font-bold flex items-center gap-1.5 transition-colors" title="Wissensdatenbank">
                                <x-heroicon-o-book-open class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                <span class="hidden xl:inline">Wissen</span>
                            </button>
                            <button type="button" @click="$dispatch('open-internal-agents-modal')" class="px-2.5 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[11px] font-bold flex items-center gap-1.5 transition-colors" title="Interne Agenten & Verbindungstest">
                                <x-heroicon-o-cpu-chip class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                <span class="hidden xl:inline">Agenten</span>
                            </button>
                            <button type="button" wire:click="$set('activeWorkspaceView', 'settings')" class="p-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 transition-colors" title="Einstellungen & Tarife">
                                <x-heroicon-o-cog-6-tooth class="w-4 h-4 text-[var(--theme-color)]" />
                            </button>
                        </div>
                    </div>

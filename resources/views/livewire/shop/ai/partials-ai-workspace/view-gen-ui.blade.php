                <div wire:key="gen-ui-view-container" class="flex-1 overflow-y-auto w-full h-full relative rounded-2xl flex flex-col">
                    <div class="fixed bottom-4 left-4 right-4 lg:absolute lg:bottom-auto lg:left-auto lg:right-4 top-auto lg:top-4 z-50 mb-4 lg:mb-0 shrink-0 shadow-2xl lg:shadow-none">
                        <button wire:click="$set('activeWorkspaceView', 'workspace')" class="w-full lg:w-auto justify-center bg-[var(--theme-color-10)] lg:bg-gray-950 border border-[var(--theme-color-50)] lg:border-gray-800 text-[var(--theme-color)] lg:text-gray-400 px-4 py-3.5 lg:py-2.5 rounded-xl text-xs font-black uppercase tracking-widest hover:text-white hover:border-gray-600 transition-all shadow-[inset_0_0_15px_var(--theme-color-10)] lg:shadow-xl flex items-center gap-2 backdrop-blur-3xl lg:backdrop-blur-xl shrink-0 z-50">
                            <x-heroicon-o-arrow-left class="w-4 h-4"/> Zurück zur Schaltzentrale
                        </button>
                    </div>
                    <livewire:shop.ai.ai-visualization-registry />
                    <div class="h-32 lg:hidden w-full shrink-0"></div>

        <!-- PLANS TAB CONTENT -->
        <div wire:key="tab-plans" :class="{'hidden': activeTab !== 'plans'}" class="flex-1 shrink-0 rounded-2xl border border-gray-800 bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-[0_0_30px_rgba(0,0,0,0.5)] h-full w-full p-6">
            @if(count($this->artifacts) > 0)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 h-full">
                    <!-- Sidebar: List of Plans -->
                    <div class="md:col-span-1 border-r border-gray-800 pr-4 space-y-2">
                        @foreach($this->artifacts as $idx => $art)
                            <button type="button" 
                                    wire:key="artifact-{{ md5($art['name'] ?? $idx) }}"
                                    @click="$dispatch('open-artifact', { id: {{ $idx }} })"
                                    class="w-full text-left p-3 rounded-lg border transition-all hover:border-[var(--theme-color)] {{ $loop->first ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)]' : 'bg-gray-950 border-gray-800' }}">
                                <div class="font-bold text-[var(--theme-color)] text-sm mb-1 truncate"><x-heroicon-o-document-check class="w-4 h-4 inline-block -mt-0.5" /> {{ $art['name'] }}</div>
                                <div class="text-[10px] text-gray-500 font-mono">{{ \Carbon\Carbon::createFromTimestamp($art['last_modified'])->diffForHumans() }}</div>
                            </button>
                        @endforeach
                    </div>

                    <!-- Main View: Artifact Viewer -->
                    <div class="md:col-span-3 h-full flex flex-col pt-2" 
                         x-data="{ 
                            currentArtifactId: 0,
                            artifacts: @js($this->artifacts),
                            get current() { return this.artifacts[this.currentArtifactId] || null; },
                            viewMode: 'markdown' // 'markdown' or 'code'
                         }"
                         @open-artifact.window="currentArtifactId = $event.detail.id; viewMode = 'markdown'">
                        
                        <template x-if="current">
                            <div class="flex flex-col h-full bg-gray-950 rounded-xl border border-gray-800 shadow-xl overflow-hidden">
                                <!-- Viewer Header -->
                                <div class="bg-[var(--theme-color-10)] border-b border-gray-800 px-4 py-3 flex justify-between items-center">
                                    <div class="font-mono text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase">
                                        <x-heroicon-o-document-text class="w-5 h-5 inline-block mr-2" />
                                        <span x-text="current.filename"></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="viewMode = 'markdown'" :class="viewMode === 'markdown' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">Preview</button>
                                        <button @click="viewMode = 'code'"     :class="viewMode === 'code' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">RAW Editor</button>
                                    </div>
                                </div>
                                <!-- Viewer Body -->
                                <div class="flex-1 overflow-y-auto custom-scrollbar p-6 bg-gray-900 relative">
                                    <!-- Markdown Preview -->
                                    <div x-show="viewMode === 'markdown'" 
                                         class="ai-markdown-content w-full"
                                         x-html="window.renderAiMarkdown ? window.renderAiMarkdown(current.content) : current.content">
                                    </div>

                                    <!-- RAW Code Block -->
                                    <div x-show="viewMode === 'code'" style="display: none;" class="w-full h-full">
                                        <textarea class="w-full h-full bg-gray-950 text-emerald-400 font-mono text-sm p-4 border border-gray-800 rounded outline-none custom-scrollbar" readonly x-html="current.content"></textarea>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @else
                <div class="h-full flex flex-col items-center justify-center text-gray-500 font-mono space-y-4">
                    <x-heroicon-o-document-magnifying-glass class="w-20 h-20 opacity-20" />
                    <p>Noch keine Pläne / Artefakte in diesem Projektordner generiert.</p>
                </div>
            @endif
        </div>

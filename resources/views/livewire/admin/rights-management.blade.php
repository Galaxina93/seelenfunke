<div class="animate-fade-in-up font-sans antialiased text-gray-300">
    <div class="space-y-6 md:space-y-8 pb-20" x-data="{ draggingId: null }">

        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-2 bg-gray-950 p-2 rounded-2xl w-fit border border-gray-800 shadow-inner">
            <button wire:click="$set('activeTab', 'roles')" @class(['px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all', 'bg-gray-800 shadow-lg text-primary border border-gray-700' => $activeTab === 'roles', 'text-gray-500 hover:text-white' => $activeTab !== 'roles'])>
                <x-heroicon-o-shield-check class="w-4 h-4 inline mr-2" /> Rollen & Rechte
            </button>
            <button wire:click="$set('activeTab', 'logs')" @class(['px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all', 'bg-gray-800 shadow-lg text-primary border border-gray-700' => $activeTab === 'logs', 'text-gray-500 hover:text-white' => $activeTab !== 'logs'])>
                <x-heroicon-o-clipboard-document-list class="w-4 h-4 inline mr-2" /> Audit-Log
            </button>
        </div>

        @if($activeTab === 'roles')
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 sm:gap-8 items-start">

                {{-- LINKER POOL: Verfügbare Rechte --}}
                <div class="xl:col-span-1 space-y-4 sticky top-24">
                    <div class="bg-gray-900/80 backdrop-blur-md p-6 rounded-[2.5rem] shadow-2xl border border-gray-800">
                        <h3 class="text-lg font-serif font-bold text-white mb-5 flex items-center gap-3 tracking-wide">
                            <div class="p-2 bg-primary/10 border border-primary/20 rounded-xl text-primary shadow-inner shrink-0">
                                <x-heroicon-o-key class="w-5 h-5" />
                            </div>
                            Rechte-Pool
                        </h3>

                        <div class="relative group mb-6">
                            <input wire:model.live.debounce.300ms="searchPermission" type="text" placeholder="Recht suchen..."
                                   class="w-full pl-12 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-xl focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm text-white placeholder-gray-600 outline-none shadow-inner">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-500 group-focus-within:text-primary transition-colors" />
                            </div>
                        </div>

                        <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                            @foreach($permissions as $permission)
                                <div
                                    draggable="true"
                                    @dragstart="draggingId = '{{ $permission->id }}'; $event.dataTransfer.setData('text/plain', '{{ $permission->id }}'); $el.classList.add('opacity-50')"
                                    @dragend="draggingId = null; $el.classList.remove('opacity-50')"
                                    class="p-4 bg-gray-950 border border-gray-800 rounded-2xl shadow-inner cursor-grab active:cursor-grabbing hover:border-primary/50 hover:shadow-[0_0_15px_rgba(197,160,89,0.1)] transition-all group flex items-center justify-between"
                                >
                                    <span class="text-xs font-bold text-gray-400 group-hover:text-white transition-colors">{{ $permission->name }}</span>
                                    <x-heroicon-m-bars-2 class="w-4 h-4 text-gray-600 group-hover:text-primary transition-colors" />
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-800 text-center">
                            <p class="text-[9px] font-black uppercase tracking-widest text-primary/60 flex items-center justify-center gap-2">
                                <x-heroicon-m-information-circle class="w-4 h-4" /> Tipp: Recht auf Rolle ziehen
                            </p>
                        </div>
                    </div>
                </div>

                {{-- RECHTER BEREICH: Rollen als Dropzones --}}
                <div class="xl:col-span-3">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
                        @foreach($roles as $role)
                            <div
                                @dragover.prevent="$el.classList.add('ring-2', 'ring-primary', 'bg-primary/[0.02]', 'shadow-[0_0_30px_rgba(197,160,89,0.15)]')"
                                @dragleave="$el.classList.remove('ring-2', 'ring-primary', 'bg-primary/[0.02]', 'shadow-[0_0_30px_rgba(197,160,89,0.15)]')"
                                @drop="$el.classList.remove('ring-2', 'ring-primary', 'bg-primary/[0.02]', 'shadow-[0_0_30px_rgba(197,160,89,0.15)]'); $wire.addPermissionToRole('{{ $role->id }}', draggingId)"
                                class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8 transition-all duration-300 relative overflow-hidden group hover:border-gray-700"
                            >
                                <div class="absolute top-0 right-0 p-8 opacity-[0.02] group-hover:opacity-[0.05] transition-opacity text-primary pointer-events-none">
                                    <x-heroicon-o-shield-check class="w-32 h-32" />
                                </div>

                                <div class="flex items-start justify-between mb-8 relative z-10 border-b border-gray-800 pb-5">
                                    <div>
                                        <h4 class="text-2xl font-serif font-bold text-white capitalize tracking-wide">{{ $role->name }}</h4>
                                        <p class="text-[9px] font-black text-primary uppercase tracking-[0.3em] mt-1.5 opacity-80">System-Rolle</p>
                                    </div>
                                    <div class="h-12 w-12 rounded-xl bg-gray-950 flex flex-col items-center justify-center border border-gray-800 text-gray-400 shadow-inner">
                                        <span class="text-lg font-bold text-white leading-none">{{ $role->permissions->count() }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2.5 relative z-10 min-h-[100px]">
                                    @forelse($role->permissions as $rolePermission)
                                        <span class="inline-flex items-center gap-2 px-3 py-2 bg-gray-950 text-gray-300 rounded-xl text-[10px] font-bold border border-gray-800 shadow-inner hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/30 transition-all cursor-default group/tag">
                                            {{ $rolePermission->name }}
                                            <button wire:click="removePermissionFromRole('{{ $role->id }}', '{{ $rolePermission->id }}')" class="hover:scale-110 transition-transform pl-1 border-l border-gray-800 group-hover/tag:border-red-500/30">
                                                <x-heroicon-m-x-mark class="w-3.5 h-3.5 text-gray-500 group-hover/tag:text-red-400" />
                                            </button>
                                        </span>
                                    @empty
                                        <div class="w-full py-10 border-2 border-dashed border-gray-800 rounded-2xl flex flex-col items-center justify-center opacity-50 bg-gray-950 shadow-inner">
                                            <x-heroicon-o-plus-circle class="w-8 h-8 mb-3 text-gray-500" />
                                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Rechte hierher ziehen</span>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        @else
            {{-- AUDIT LOG --}}
            <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden animate-fade-in">
                <div class="p-8 sm:p-10 border-b border-gray-800 flex justify-between items-center bg-gray-950/50 shadow-inner">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-serif font-bold text-white tracking-wide">Berechtigungs-Historie</h2>
                        <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-2">Überwachung aller Änderungen an Sicherheits-Rollen.</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-800/50">
                    @forelse($logs as $log)
                        <div class="p-6 sm:p-8 hover:bg-gray-800/30 transition-colors group">
                            <div class="flex items-start gap-5 sm:gap-6">
                                <div @class(['p-3.5 rounded-[1rem] shrink-0 border shadow-inner',
                                'bg-blue-500/10 text-blue-400 border-blue-500/20' => $log->action_id === 'rights:attach',
                                'bg-orange-500/10 text-orange-400 border-orange-500/20' => $log->action_id === 'rights:detach'])>
                                    <x-heroicon-o-shield-check class="w-6 h-6" />
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row justify-between items-start gap-2">
                                        <div>
                                            <h4 class="font-bold text-white text-base tracking-wide">{{ $log->title }}</h4>
                                            <p class="text-xs text-gray-400 font-medium mt-1">{{ $log->message }}</p>
                                        </div>
                                        <div class="text-left sm:text-right shrink-0 mt-2 sm:mt-0">
                                            <div class="text-sm font-black text-white">{{ $log->created_at->format('H:i') }} <span class="text-xs text-gray-500 font-medium">Uhr</span></div>
                                            <div class="text-[9px] text-gray-500 font-black uppercase tracking-[0.2em] mt-0.5">{{ $log->created_at->format('d. M Y') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-16 text-center text-gray-500 font-serif italic text-lg">Bisher keine Änderungen protokolliert.</div>
                    @endforelse
                </div>

                @if($logs->hasPages())
                    <div class="p-6 sm:p-8 bg-gray-900/30 border-t border-gray-800">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

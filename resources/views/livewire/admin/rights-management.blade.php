<div>
    <div class="space-y-6 pb-20" x-data="{ draggingId: null }">
        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-2 bg-gray-100/50 p-1.5 rounded-2xl w-fit border border-gray-200 shadow-sm">
            <button wire:click="$set('activeTab', 'roles')" @class(['px-6 py-2.5 rounded-xl text-sm font-bold transition-all', 'bg-white shadow-sm text-primary border border-gray-100' => $activeTab === 'roles', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'roles'])>
                <x-heroicon-o-shield-check class="w-4 h-4 inline mr-2" /> Rollen & Rechte
            </button>
            <button wire:click="$set('activeTab', 'logs')" @class(['px-6 py-2.5 rounded-xl text-sm font-bold transition-all', 'bg-white shadow-sm text-primary border border-gray-100' => $activeTab === 'logs', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'logs'])>
                <x-heroicon-o-clipboard-document-list class="w-4 h-4 inline mr-2" /> Audit-Log
            </button>
        </div>

        @if($activeTab === 'roles')
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-8 items-start">

                {{-- LINKER POOL: Verfügbare Rechte --}}
                <div class="xl:col-span-1 space-y-4 sticky top-24">
                    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                        <h3 class="text-lg font-serif font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <x-heroicon-o-key class="w-5 h-5 text-primary" />
                            Rechte-Pool
                        </h3>

                        <div class="relative group mb-6">
                            <input wire:model.live.debounce.300ms="searchPermission" type="text" placeholder="Recht suchen..."
                                   class="w-full pl-10 pr-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-4 focus:ring-primary/10 transition-all text-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-300" />
                            </div>
                        </div>

                        <div class="space-y-2 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                            @foreach($permissions as $permission)
                                <div
                                    draggable="true"
                                    @dragstart="draggingId = '{{ $permission->id }}'; $event.dataTransfer.setData('text/plain', '{{ $permission->id }}')"
                                    class="p-3 bg-white border border-gray-100 rounded-xl shadow-sm cursor-grab active:cursor-grabbing hover:border-primary/30 hover:shadow-md transition-all group flex items-center justify-between"
                                >
                                    <span class="text-xs font-bold text-gray-600 group-hover:text-primary transition-colors">{{ $permission->name }}</span>
                                    <x-heroicon-m-bars-2 class="w-4 h-4 text-gray-200 group-hover:text-primary/30" />
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-4 text-[10px] text-gray-400 italic text-center">Tipp: Ziehen Sie ein Recht auf eine Rolle rechts.</p>
                    </div>
                </div>

                {{-- RECHTER BEREICH: Rollen als Dropzones --}}
                <div class="xl:col-span-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($roles as $role)
                            <div
                                @dragover.prevent="$el.classList.add('ring-2', 'ring-primary', 'bg-primary/[0.02]')"
                                @dragleave="$el.classList.remove('ring-2', 'ring-primary', 'bg-primary/[0.02]')"
                                @drop="$el.classList.remove('ring-2', 'ring-primary', 'bg-primary/[0.02]'); $wire.addPermissionToRole('{{ $role->id }}', draggingId)"
                                class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-8 transition-all duration-300 relative overflow-hidden group"
                            >
                                <div class="absolute top-0 right-0 p-6 opacity-[0.03] group-hover:opacity-10 transition-opacity">
                                    <x-heroicon-o-shield-check class="w-24 h-24" />
                                </div>

                                <div class="flex items-center justify-between mb-6 relative z-10">
                                    <div>
                                        <h4 class="text-xl font-serif font-bold text-gray-900 capitalize">{{ $role->name }}</h4>
                                        <p class="text-[10px] font-black text-primary uppercase tracking-[0.2em] opacity-60">System-Rolle</p>
                                    </div>
                                    <div class="h-10 w-10 rounded-xl bg-gray-50 flex items-center justify-center border border-gray-100 text-gray-400">
                                        <span class="text-xs font-bold">{{ $role->permissions->count() }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 relative z-10">
                                    @forelse($role->permissions as $rolePermission)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 text-gray-600 rounded-lg text-[11px] font-bold border border-gray-100 hover:bg-red-50 hover:text-red-600 hover:border-red-100 transition-all cursor-default group/tag">
                                        {{ $rolePermission->name }}
                                        <button wire:click="removePermissionFromRole('{{ $role->id }}', '{{ $rolePermission->id }}')" class="hover:scale-110 transition-transform">
                                            <x-heroicon-m-x-mark class="w-3 h-3 text-gray-300 group-hover/tag:text-red-400" />
                                        </button>
                                    </span>
                                    @empty
                                        <div class="w-full py-8 border-2 border-dashed border-gray-50 rounded-2xl flex flex-col items-center justify-center opacity-30">
                                            <x-heroicon-o-plus-circle class="w-8 h-8 mb-2" />
                                            <span class="text-xs font-bold uppercase tracking-widest">Rechte hierher ziehen</span>
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
            <div class="bg-white rounded-[2.5rem] shadow-xl border border-gray-100 overflow-hidden animate-fade-in">
                <div class="p-8 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900">Berechtigungs-Historie</h2>
                        <p class="text-sm text-gray-500">Überwachung aller Änderungen an Sicherheits-Rollen.</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-50">
                    @foreach($logs as $log)
                        <div class="p-6 hover:bg-gray-50/50 transition-colors group">
                            <div class="flex items-start gap-6">
                                <div @class(['p-3 rounded-2xl shrink-0 shadow-sm',
                                'bg-blue-50 text-blue-500' => $log->action_id === 'rights:attach',
                                'bg-orange-50 text-orange-500' => $log->action_id === 'rights:detach'])>
                                    <x-heroicon-o-shield-check class="w-6 h-6" />
                                </div>

                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-bold text-gray-900">{{ $log->title }}</h4>
                                            <p class="text-sm text-gray-600 mt-0.5">{{ $log->message }}</p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs font-black text-gray-900">{{ $log->created_at->format('H:i') }} Uhr</div>
                                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-tighter">{{ $log->created_at->format('d. M Y') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-8 bg-gray-50/30">
                    {{ $logs->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

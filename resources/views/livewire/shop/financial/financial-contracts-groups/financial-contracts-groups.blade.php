<div x-data="{ draggingItemId: null }">
    <div class="min-h-screen bg-transparent pb-20 font-sans text-gray-300">

        {{-- Success Notification --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="fixed bottom-6 right-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-6 py-4 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.2)] z-50 flex items-center gap-3 animate-fade-in-up backdrop-blur-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-black uppercase tracking-widest text-[10px]">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Error Notification --}}
        @if (session()->has('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                 class="fixed bottom-6 right-6 bg-red-500/10 border border-red-500/30 text-red-400 px-6 py-4 rounded-2xl shadow-[0_0_30px_rgba(239,68,68,0.2)] z-50 flex items-center gap-3 animate-fade-in-up backdrop-blur-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-black uppercase tracking-widest text-[10px]">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border-b border-gray-800 sticky top-0 z-30 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 py-4 md:py-6 flex justify-between items-center">
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-white flex items-center gap-3 tracking-tight">
                    <div class="p-2.5 bg-blue-500/10 border border-blue-500/20 rounded-xl text-blue-400 shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span>Verträge & Gruppen</span>
                </h1>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 mt-8 md:mt-12 space-y-8 md:space-y-10 animate-fade-in-up" style="animation-delay: 100ms;">

            {{-- Validierungstabelle: Fehlende Verträge ODER Alles OK --}}
            @if($missingContracts->count() > 0)
                {{-- Helper für Total Count --}}
                @php
                    $totalItems = $groups->sum(fn($group) => $group->items->count());
                @endphp

                <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-[0_0_30px_rgba(239,68,68,0.05)] border border-red-500/20 overflow-hidden transition-all duration-300"
                     x-data="{ expanded: false }">
                    <div class="bg-red-900/10 px-6 sm:px-8 py-5 border-b border-red-500/20 flex justify-between items-center cursor-pointer hover:bg-red-900/20 transition-colors shadow-inner"
                         @click="expanded = !expanded">
                        <div class="flex items-center gap-4">
                            <div class="p-2.5 bg-red-500/10 text-red-500 border border-red-500/20 rounded-xl shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-serif font-bold text-red-400 flex flex-wrap items-center gap-3 tracking-wide">
                                    Vertrags-Check: Fehlende Unterlagen
                                    <span class="bg-red-500/20 text-red-300 border border-red-500/30 text-[9px] font-black tracking-widest px-2.5 py-0.5 rounded-md uppercase">{{ $missingContracts->count() }} / {{ $totalItems }}</span>
                                </h3>
                                <p class="text-[10px] sm:text-xs text-red-300/80 font-medium mt-1">Bei folgenden Kostenstellen wurde noch kein Vertrag oder Beleg hinterlegt.</p>
                            </div>
                        </div>

                        <div class="text-red-500/50 transition-transform duration-300 shrink-0 ml-4" :class="expanded ? 'rotate-180 text-red-400' : ''">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    <div x-show="expanded" x-collapse>
                        <div class="block md:hidden divide-y divide-gray-800/50">
                            @foreach($missingContracts as $missingItem)
                                <div class="p-5 hover:bg-gray-800/30 transition-colors">
                                    <div class="mb-3">
                                        <p class="font-bold text-white text-sm mb-1">{{ $missingItem->name }}</p>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ $missingItem->group->name }}</p>
                                    </div>

                                    <div>
                                        @if($uploadingMissingItemId === $missingItem->id)
                                            <div class="flex flex-col gap-3 bg-gray-950 p-4 rounded-xl border border-red-500/20">
                                                <input type="file" wire:model="quickUploadFile"
                                                       class="w-full text-[9px] text-gray-400 file:mr-2 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-red-500/10 file:text-red-400 file:border file:border-red-500/20 hover:file:bg-red-500/20 transition-all cursor-pointer">

                                                <div class="flex justify-end gap-2 mt-2">
                                                    <button wire:click="cancelQuickUpload" class="px-4 py-2 bg-gray-900 border border-gray-800 text-gray-400 rounded-xl text-[9px] font-black uppercase tracking-widest hover:text-white transition-colors">
                                                        Abbrechen
                                                    </button>
                                                    <button wire:click="saveQuickUpload" wire:loading.attr="disabled" class="bg-red-500 text-gray-900 px-6 py-2 rounded-xl hover:bg-red-400 transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] disabled:opacity-50 text-[9px] font-black uppercase tracking-widest flex items-center gap-2">
                                                        <span wire:loading.remove wire:target="saveQuickUpload">Speichern</span>
                                                        <span wire:loading wire:target="saveQuickUpload" class="flex items-center gap-1">
                                                            <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                        </span>
                                                    </button>
                                                </div>
                                                @error('quickUploadFile') <span class="text-[9px] font-bold text-red-400 uppercase tracking-widest block mt-1">{{ $message }}</span> @enderror
                                            </div>
                                        @else
                                            <button wire:click="startQuickUpload('{{ $missingItem->id }}')" class="w-full text-[9px] bg-red-500/10 text-red-400 px-4 py-3 rounded-xl hover:bg-red-500/20 hover:text-red-300 transition-all font-black uppercase tracking-widest shadow-inner border border-red-500/20 flex items-center justify-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                Jetzt hochladen
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="hidden md:block overflow-x-auto w-full no-scrollbar">
                            <table class="w-full text-sm text-left min-w-[600px]">
                                <thead class="bg-gray-950/50 text-[10px] text-gray-500 font-black uppercase tracking-widest border-b border-gray-800">
                                <tr>
                                    <th class="px-8 py-4">Kostenstelle</th>
                                    <th class="px-4 py-4">Gruppe</th>
                                    <th class="px-8 py-4 text-right w-[400px]">Aktion</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800/50 bg-transparent">
                                @foreach($missingContracts as $missingItem)
                                    <tr class="hover:bg-gray-800/30 transition-colors group">
                                        <td class="px-8 py-5 font-bold text-white tracking-wide">{{ $missingItem->name }}</td>
                                        <td class="px-4 py-5 text-gray-400 font-medium">{{ $missingItem->group->name }}</td>
                                        <td class="px-8 py-5 text-right">
                                            @if($uploadingMissingItemId === $missingItem->id)
                                                <div class="flex items-center justify-end gap-3 bg-gray-950 p-2 rounded-xl border border-red-500/20">
                                                    <input type="file" wire:model="quickUploadFile"
                                                           class="w-[200px] text-[9px] text-gray-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-red-500/10 file:text-red-400 file:border file:border-red-500/20 hover:file:bg-red-500/20 transition-all cursor-pointer">

                                                    <button wire:click="saveQuickUpload" wire:loading.attr="disabled"
                                                            class="bg-red-500 text-gray-900 px-4 py-2 rounded-lg hover:bg-red-400 transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] disabled:opacity-50 text-[9px] font-black uppercase tracking-widest shrink-0">
                                                        <span wire:loading.remove wire:target="saveQuickUpload">Speichern</span>
                                                        <span wire:loading wire:target="saveQuickUpload" class="flex items-center gap-1">
                                                            <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                        </span>
                                                    </button>
                                                    <button wire:click="cancelQuickUpload" class="text-gray-500 hover:text-red-400 px-2 transition-colors">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                                @error('quickUploadFile') <span class="text-[9px] font-bold text-red-400 uppercase tracking-widest block mt-2">{{ $message }}</span> @enderror
                                            @else
                                                <button wire:click="startQuickUpload('{{ $missingItem->id }}')" class="text-[9px] bg-red-500/10 text-red-400 px-4 py-2 rounded-xl hover:bg-red-500/20 hover:text-red-300 transition-all font-black uppercase tracking-widest shadow-inner border border-red-500/20 inline-flex items-center gap-2">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                    Jetzt hochladen
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-[0_0_30px_rgba(16,185,129,0.05)] border border-emerald-500/20 overflow-hidden animate-fade-in-down">
                    <div class="bg-emerald-900/10 px-6 sm:px-8 py-5 flex items-center gap-4 shadow-inner">
                        <div class="p-2.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-xl shadow-[0_0_15px_rgba(16,185,129,0.2)] shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-serif font-bold text-emerald-400 tracking-wide drop-shadow-[0_0_8px_currentColor]">Vertrags-Check: Alle Unterlagen liegen vor</h3>
                            <p class="text-[10px] sm:text-xs text-emerald-300/70 font-medium mt-0.5">Es wurden zu allen Kostenstellen entsprechende Belege oder Verträge hinterlegt.</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- 1. Donut Chart Section (WIRE:IGNORE WICHTIG) --}}
            <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-10 flex flex-col md:flex-row items-center gap-8 relative overflow-hidden" wire:ignore>
                <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
                <div class="w-full md:w-1/3 relative z-10 text-center md:text-left">
                    <h3 class="text-xl font-serif font-bold text-white mb-3 tracking-wide">Finanzielle Aufteilung</h3>
                    <p class="text-xs text-gray-400 leading-relaxed font-medium">Übersicht der monatlichen Volumina nach Gruppen. <br class="hidden md:block mt-2"> <span class="text-emerald-400 font-bold drop-shadow-[0_0_5px_currentColor]">Grün = Einnahmen</span>, <span class="text-red-400 font-bold drop-shadow-[0_0_5px_currentColor]">Rot = Ausgaben</span>.</p>
                </div>
                <div class="w-full md:w-2/3 h-64 sm:h-80 relative z-10">
                    <canvas id="groupsChart"></canvas>
                </div>
            </div>

            {{-- 2. Gruppen Grid (mit Drag & Drop für Gruppen) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8"
                 x-data="{
                    draggedGroup: null,
                    handleGroupDrop(e) {
                        const target = e.target.closest('.group-sortable-card');
                        if (this.draggedGroup && target && this.draggedGroup !== target) {
                            const container = this.$refs.groupListContainer;
                            const items = Array.from(container.querySelectorAll('.group-sortable-card'));
                            const draggedIndex = items.indexOf(this.draggedGroup);
                            const targetIndex = items.indexOf(target);

                            if (draggedIndex < targetIndex) {
                                target.after(this.draggedGroup);
                            } else {
                                target.before(this.draggedGroup);
                            }

                            const newOrder = Array.from(container.querySelectorAll('.group-sortable-card')).map(el => el.dataset.groupId);
                            $wire.updateGroupOrder(newOrder);
                        }
                    }
                 }"
                 x-ref="groupListContainer">

                @foreach($groups as $group)
                    @php
                        $groupMonthly = 0;
                        $groupYearly = 0;
                        foreach($group->items as $item) {
                            $groupMonthly += $item->amount / ($item->interval_months ?: 1);
                            $groupYearly += ($item->amount / ($item->interval_months ?: 1)) * 12;
                        }
                    @endphp

                    {{-- GROUP CARD (Dropzone für Gruppen UND Items) --}}
                    <div data-group-id="{{ $group->id }}"
                         class="group-sortable-card bg-gray-900/80 backdrop-blur-md border rounded-[2rem] overflow-hidden shadow-2xl transition-all duration-300 {{ $activeGroupId === $group->id ? 'border-primary/50 shadow-[0_0_30px_rgba(197,160,89,0.15)] lg:col-span-2' : 'border-gray-800 hover:border-gray-700' }}"
                         draggable="true"
                         @dragstart.stop="draggedGroup = $el; $el.classList.add('opacity-40', 'scale-[0.98]', 'border-dashed')"
                         @dragend.stop="draggedGroup = null; $el.classList.remove('opacity-40', 'scale-[0.98]', 'border-dashed')"
                         x-data="{ isDraggingOver: false }"
                         @dragover.prevent="if(!draggedGroup && draggingItemId) isDraggingOver = true"
                         @dragleave.prevent="isDraggingOver = false"
                         @drop.prevent="
                            isDraggingOver = false;
                            if(draggedGroup) {
                                handleGroupDrop($event);
                            } else if(draggingItemId) {
                                $wire.moveCostItem(draggingItemId, '{{ $group->id }}');
                            }
                         "
                         :class="isDraggingOver ? 'ring-2 ring-primary bg-gray-900 scale-[1.01] shadow-[0_0_40px_rgba(197,160,89,0.2)]' : ''"
                    >
                        {{-- Drag Handle Indikator für die Gruppe --}}
                        <div class="w-full py-2 bg-gray-950 flex items-center justify-center cursor-grab active:cursor-grabbing border-b border-gray-800 opacity-50 hover:opacity-100 transition-opacity shadow-inner" title="Gruppe verschieben">
                            <div class="w-12 h-1.5 bg-gray-700 rounded-full"></div>
                        </div>

                        {{-- Group Header --}}
                        @include('livewire.shop.financial.financial-contracts-groups.partials.group_header')

                        {{-- Group Body (Items) --}}
                        @if($activeGroupId === $group->id)
                            <div class="border-t border-gray-800 bg-gray-950/50 p-4 sm:p-8 animate-fade-in-down shadow-inner" @dragstart.stop>

                                <div class="space-y-4">
                                    @foreach($group->items as $item)
                                        {{-- ITEM CARD (Draggable) --}}
                                        <div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-inner hover:border-primary/30 hover:shadow-[0_0_15px_rgba(197,160,89,0.1)] transition-all cursor-grab active:cursor-grabbing group/item overflow-hidden"
                                             draggable="true"
                                             @dragstart.stop="draggingItemId = '{{ $item->id }}'; $event.dataTransfer.effectAllowed = 'move';"
                                             @dragend.stop="draggingItemId = null">
                                            @if($editingItemId === $item->id)
                                                <div class="p-4 sm:p-6 bg-gray-800/30">
                                                    @include('livewire.shop.financial.financial-contracts-groups.partials.edit_cost_item')
                                                </div>
                                            @else
                                                <div class="p-4 sm:p-6">
                                                    @include('livewire.shop.financial.financial-contracts-groups.partials.cost_item')
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- "NEUE KOSTENSTELLE" --}}
                                <div class="mt-6">
                                    @include('livewire.shop.financial.financial-contracts-groups.partials.new_cost_item')
                                </div>

                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- KACHEL: "NEUE GRUPPE" (Am Ende des Grids) --}}
                @include('livewire.shop.financial.financial-contracts-groups.partials.new_group')

            </div>
        </div>
    </div>

    {{-- Chart Initialization Script --}}
    @include('livewire.shop.financial.financial-contracts-groups.partials.chart_scripts')
</div>

<div x-data="{ draggingItemId: null }">
    <div class="min-h-screen bg-gray-50 pb-20 font-sans text-gray-800">

        {{-- Success Notification --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Error Notification --}}
        @if (session()->has('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                 class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-bold">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-30 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3 tracking-tight">
                    <div class="p-2 bg-blue-100 rounded-xl text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span>Verträge & Gruppen</span>
                </h1>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 mt-8 space-y-8">

            {{-- Validierungstabelle: Fehlende Verträge ODER Alles OK --}}
            @if($missingContracts->count() > 0)
                {{-- Helper für Total Count --}}
                @php
                    $totalItems = $groups->sum(fn($group) => $group->items->count());
                @endphp

                {{-- Bereich standardmäßig eingeklappt (expanded: false) --}}
                <div class="bg-white rounded-xl shadow-lg border border-red-100 overflow-hidden transition-all duration-300" x-data="{ expanded: false }">
                    <div
                        class="bg-red-50 px-6 py-4 border-b border-red-100 flex justify-between items-center cursor-pointer hover:bg-red-100/80 transition-colors"
                        @click="expanded = !expanded"
                    >
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-red-100 text-red-500 rounded-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-red-700 flex items-center gap-2">
                                    Vertrags-Check: Fehlende Unterlagen
                                    {{-- Counter Anzeige --}}
                                    <span class="bg-red-200 text-red-800 text-xs px-2 py-0.5 rounded-full">{{ $missingContracts->count() }} / {{ $totalItems }}</span>
                                </h3>
                                <p class="text-xs text-red-500">Bei folgenden Kostenstellen wurde noch kein Vertrag oder Beleg hinterlegt.</p>
                            </div>
                        </div>

                        {{-- Chevron Icon für Collapse --}}
                        <div class="text-red-400 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>

                    {{-- Collapsible Content --}}
                    <div x-show="expanded" x-collapse>
                        <div class="p-0 overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-100">
                                <tr>
                                    <th class="p-4">Kostenstelle</th>
                                    <th class="p-4">Gruppe</th>
                                    <th class="p-4 text-right">Aktion</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                @foreach($missingContracts as $missingItem)
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="p-4 font-medium text-gray-700">{{ $missingItem->name }}</td>
                                        <td class="p-4 text-gray-500">{{ $missingItem->group->name }}</td>
                                        <td class="p-4 text-right">
                                            @if($uploadingMissingItemId === $missingItem->id)
                                                {{-- Inline Upload Form --}}
                                                <div class="flex items-center justify-end gap-2">
                                                    <input type="file" wire:model="quickUploadFile" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                                                    <button wire:click="saveQuickUpload" wire:loading.attr="disabled" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition flex items-center shadow-md disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="saveQuickUpload">Speichern</span>
                                                        <span wire:loading wire:target="saveQuickUpload">...</span>
                                                    </button>
                                                    <button wire:click="cancelQuickUpload" class="text-gray-400 hover:text-gray-600 px-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                                @error('quickUploadFile') <span class="text-[10px] text-red-500 block mt-1">{{ $message }}</span> @enderror
                                            @else
                                                <button wire:click="startQuickUpload('{{ $missingItem->id }}')" class="text-xs bg-red-50 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-100 transition font-bold shadow-sm border border-red-100">
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
                {{-- GRÜNE SUCCESS BOX --}}
                <div class="bg-white rounded-xl shadow-lg border border-emerald-100 overflow-hidden animate-fade-in-down">
                    <div class="bg-emerald-50 px-6 py-4 flex items-center gap-3">
                        <div class="p-2 bg-emerald-100 text-emerald-500 rounded-full">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-emerald-700">Vertrags-Check: Alle Unterlagen liegen vor</h3>
                            <p class="text-xs text-emerald-600">Es wurden zu allen Kostenstellen entsprechende Belege oder Verträge hinterlegt.</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- 1. Donut Chart Section (WIRE:IGNORE WICHTIG) --}}
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 flex flex-col md:flex-row items-center gap-8" wire:ignore>
                <div class="w-full md:w-1/3">
                    <h3 class="text-lg font-bold text-gray-700 mb-2">Finanzielle Aufteilung</h3>
                    <p class="text-xs text-gray-400">Übersicht der monatlichen Volumina nach Gruppen. <br> <span
                            class="text-emerald-500 font-bold">Grün = Einnahmen</span>, <span
                            class="text-red-500 font-bold">Rot = Ausgaben</span>.</p>
                </div>
                <div class="w-full md:w-2/3 h-64 relative">
                    <canvas id="groupsChart"></canvas>
                </div>
            </div>

            {{-- 2. Gruppen Grid (mit Drag & Drop für Gruppen) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"
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
                         class="group-sortable-card bg-white border rounded-xl overflow-hidden shadow-sm transition-all duration-300 {{ $activeGroupId === $group->id ? 'ring-2 ring-primary/20 shadow-md lg:col-span-2' : 'border-gray-100' }}"
                         draggable="true"
                         @dragstart.stop="draggedGroup = $el; $el.classList.add('opacity-50', 'scale-[0.98]')"
                         @dragend.stop="draggedGroup = null; $el.classList.remove('opacity-50', 'scale-[0.98]')"
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
                         :class="isDraggingOver ? 'ring-2 ring-orange-400 bg-orange-50 scale-[1.01]' : ''"
                    >
                        {{-- Drag Handle Indikator für die Gruppe --}}
                        <div class="w-full py-1.5 bg-gray-50 flex items-center justify-center cursor-grab active:cursor-grabbing border-b border-gray-100 opacity-60 hover:opacity-100 transition-opacity" title="Gruppe verschieben">
                            <div class="w-10 h-1.5 bg-gray-300 rounded-full"></div>
                        </div>

                        {{-- Group Header --}}
                        @include('livewire.shop.financial.financial-contracts-groups.partials.group_header')

                        {{-- Group Body (Items) --}}
                        @if($activeGroupId === $group->id)
                            <div class="border-t border-gray-100 bg-gray-50/50 p-4 sm:p-6 animate-fade-in-down" @dragstart.stop>

                                <div class="space-y-3">
                                    @foreach($group->items as $item)
                                        {{-- ITEM CARD (Draggable) --}}
                                        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-grab active:cursor-grabbing"
                                             draggable="true"
                                             @dragstart.stop="draggingItemId = '{{ $item->id }}'; $event.dataTransfer.effectAllowed = 'move';"
                                             @dragend.stop="draggingItemId = null"
                                        >
                                            @if($editingItemId === $item->id)
                                                {{-- EDIT COSTITEM --}}
                                                @include('livewire.shop.financial.financial-contracts-groups.partials.edit_cost_item')
                                            @else
                                                {{-- DISPLAY MODE --}}
                                                @include('livewire.shop.financial.financial-contracts-groups.partials.cost_item')
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                {{-- "NEUE KOSTENSTELLE" --}}
                                @include('livewire.shop.financial.financial-contracts-groups.partials.new_cost_item')

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
    @include('livewire.shop.financial.financial-contracts-groups.partials.chart_scripts_financial_contracts_groups')
</div>

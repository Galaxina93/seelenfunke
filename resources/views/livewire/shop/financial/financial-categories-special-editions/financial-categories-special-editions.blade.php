<div>
    <div class="min-h-screen bg-gray-50 pb-20 font-sans text-gray-800">
        {{-- Success Notification --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Header --}}
        <div class="bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-30 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3 tracking-tight">
                    <div class="p-2 bg-orange-100 rounded-xl text-orange-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <span>Variable Kosten & Kategorien</span>
                </h1>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 mt-8 space-y-8">

            {{-- BELEG-CHECK (NEU) --}}
            @if($missingReceipts->count() > 0)
                <div class="bg-white rounded-xl shadow-lg border border-red-100 overflow-hidden transition-all duration-300" x-data="{ expanded: false }">
                    {{-- Header --}}
                    <div class="bg-red-50 px-6 py-4 border-b border-red-100 flex justify-between items-center cursor-pointer hover:bg-red-100/80 transition-colors" @click="expanded = !expanded">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-red-100 text-red-500 rounded-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-red-700 flex items-center gap-2">
                                    Beleg-Check: Fehlende Dateien
                                    <span class="bg-red-200 text-red-800 text-xs px-2 py-0.5 rounded-full">{{ $missingReceipts->count() }} offen</span>
                                </h3>
                                <p class="text-xs text-red-500">Für diese Sonderausgaben wurden noch keine Belege hochgeladen.</p>
                            </div>
                        </div>
                        <div class="text-red-400 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div x-show="expanded" x-collapse>
                        <div class="p-0 overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-100">
                                <tr>
                                    <th class="p-4">Titel</th>
                                    <th class="p-4">Datum</th>
                                    <th class="p-4">Betrag</th>
                                    <th class="p-4 text-right">Aktion</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                @foreach($missingReceipts as $missing)
                                    <tr class="hover:bg-gray-50/50 transition">
                                        <td class="p-4 font-medium text-gray-700">{{ $missing->title }}</td>
                                        <td class="p-4 text-gray-500">{{ $missing->execution_date->format('d.m.Y') }}</td>
                                        <td class="p-4 text-gray-700 font-mono">{{ number_format($missing->amount, 2, ',', '.') }} €</td>
                                        <td class="p-4 text-right">
                                            @if($uploadingMissingSpecialId === $missing->id)
                                                <div class="flex items-center justify-end gap-2">
                                                    <input type="file" wire:model="quickSpecialUploadFile" class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                                                    <button wire:click="saveSpecialUpload" wire:loading.attr="disabled" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition flex items-center shadow-md disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="saveSpecialUpload">Speichern</span>
                                                        <span wire:loading wire:target="saveSpecialUpload">...</span>
                                                    </button>
                                                    <button wire:click="cancelSpecialUpload" class="text-gray-400 hover:text-gray-600 px-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </div>
                                                @error('quickSpecialUploadFile') <span class="text-[10px] text-red-500 block mt-1">{{ $message }}</span> @enderror
                                            @else
                                                <button wire:click="startSpecialUpload('{{ $missing->id }}')" class="text-xs bg-red-50 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-100 transition font-bold shadow-sm border border-red-100">
                                                    Beleg hochladen
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
                            <h3 class="text-base font-bold text-emerald-700">Beleg-Check: Alle Unterlagen liegen vor</h3>
                            <p class="text-xs text-emerald-600">Es wurden zu allen Sonderausgaben entsprechende Belege hochgeladen.</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- 1. Chart Section (Donut) --}}
            <div
                class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 flex flex-col md:flex-row items-center gap-8"
                wire:ignore>
                <div class="w-full md:w-1/3">
                    <h3 class="text-lg font-bold text-gray-700 mb-2">Finanzielle Aufteilung</h3>
                    <p class="text-xs text-gray-400">Übersicht der Ausgaben nach Kategorie (Kumuliert über alle Jahre).</p>
                </div>
                <div class="w-full md:w-2/3 h-64 relative">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            {{--Quick Add--}}
            @livewire('shop.financial.financial-quick-entry')

            {{-- 2. Kategorien & Liste --}}
            @include('livewire.shop.financial.financial-categories-special-editions.partials.categories')

        </div>
    </div>

    {{-- DELETE CATEGORY MODAL --}}
    @if($showCategoryDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Kategorie löschen</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Die Kategorie <strong class="text-gray-900">{{ $categoryToDeleteName }}</strong> wird von Einträgen verwendet.
                        Bitte wähle eine neue Kategorie, in die diese Einträge verschoben werden sollen.
                    </p>

                    <div class="mb-6">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-2">Neue Kategorie</label>
                        <select wire:model="targetCategoryId" class="w-full text-sm rounded-lg border-gray-300 focus:ring-orange-400">
                            <option value="">Bitte wählen...</option>
                            @foreach($this->manageableCategories as $cat)
                                @if($cat->id !== $categoryToDeleteId)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('targetCategoryId') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <button wire:click="cancelDeleteCategory" class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 rounded-lg">Abbrechen</button>
                        <button wire:click="confirmDeleteCategory" class="px-4 py-2 text-sm font-bold text-white bg-red-500 hover:bg-red-600 rounded-lg shadow-md">Jetzt übertragen & Löschen</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Chart Script --}}
    @include('livewire.shop.financial.financial-categories-special-editions.partials.chart_scripts')
</div>

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
            <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3 tracking-tight">
                    <div class="p-2 bg-orange-100 rounded-lg text-orange-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    Sonderausgaben & Kategorien
                </h1>

                {{-- Datumsauswahl --}}
                <div class="flex bg-gray-100 rounded-lg p-1 shadow-inner">
                    <select wire:model.live="selectedMonth" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer text-gray-700 py-1 pl-3 pr-8">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                        @endforeach
                    </select>
                    <div class="w-px bg-gray-300 my-1"></div>
                    <select wire:model.live="selectedYear" class="bg-transparent border-none text-sm font-semibold focus:ring-0 cursor-pointer text-gray-700 py-1 pl-3 pr-8">
                        @foreach(range(date('Y')-2, date('Y')+1) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 py-8 space-y-8">

            {{--
                1. BELEG-CHECK / VALIDIERUNG (Eingeklappt & Statusfarben)
            --}}
            @include('livewire.shop.financial.partials.bill_check')

            {{--
                2. KATEGORIEN VERWALTEN (Eingeklappt)
            --}}
            @include('livewire.shop.financial.partials.category_management')

            {{--
                3. LISTE DER SONDERAUSGABEN (Tabelle & Chart)
            --}}
            @include('livewire.shop.financial.partials.special_issue_list')

        </div>
    </div>

    {{-- Delete Modal für Kategorien --}}
    @if($showCategoryDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 animate-fade-in">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="p-6">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-red-500 mb-4 mx-auto">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-center text-gray-900 mb-2">Kategorie löschen?</h3>
                    <p class="text-center text-gray-500 text-sm mb-6">
                        Die Kategorie <strong>"{{ $categoryToDeleteName }}"</strong> enthält noch Buchungen.
                        Bitte wählen Sie eine Ersatz-Kategorie, auf die diese Buchungen übertragen werden sollen.
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

<div>

    <div class="bg-transparent pb-20 font-sans text-gray-300">
        {{-- Success Notification --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="fixed bottom-6 right-6 bg-emerald-500/10 border border-emerald-500/30 backdrop-blur-md text-emerald-400 px-6 py-4 rounded-2xl shadow-[0_0_30px_rgba(16,185,129,0.2)] z-50 flex items-center gap-3 animate-fade-in-up">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-black uppercase tracking-widest text-[10px]">{{ session('success') }}</span>
            </div>
        @endif

        {{-- Header (Anklickbar gemacht, um die Liste auf-/zuzuklappen) --}}
        <div class="bg-gray-900/80 backdrop-blur-md shadow-2xl border-b border-gray-800 sticky top-0 z-30 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 py-4 md:py-6 flex justify-between items-center gap-4">
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-white flex items-center gap-3 tracking-tight">
                    <div class="p-2.5 bg-orange-500/10 border border-orange-500/20 shadow-inner rounded-xl text-orange-400 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    Variable Ausgaben
                </h1>
            </div>
        </div>

        {{-- Der einklappbare Inhaltsbereich --}}
        <div>
            <div class="max-w-7xl mx-auto px-4 py-8 md:py-12 space-y-8 md:space-y-12 animate-fade-in-up" style="animation-delay: 100ms;">
                {{-- 1. KATEGORIEN VERWALTEN (Eingeklappt) --}}
                @include('livewire.shop.financial.partials.category_management')

                {{-- 2. LISTE DER SONDERAUSGABEN (Tabelle & Chart) --}}
                @include('livewire.shop.financial.partials.special_issue_list')
            </div>
        </div>
    </div>

    {{-- Delete Modal für Kategorien --}}
    @if($showCategoryDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-fade-in">
            <div class="bg-gray-900/90 backdrop-blur-xl border border-gray-800 rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.5)] max-w-md w-full overflow-hidden">
                <div class="p-8">
                    <div class="w-16 h-16 rounded-2xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-400 mb-6 mx-auto shadow-inner drop-shadow-[0_0_15px_rgba(239,68,68,0.2)]">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 class="text-xl font-serif font-bold text-center text-white mb-3 tracking-wide">Kategorie löschen?</h3>
                    <p class="text-center text-gray-400 text-sm mb-8 leading-relaxed">
                        Die Kategorie <strong class="text-white">"{{ $categoryToDeleteName }}"</strong> enthält noch Buchungen.
                        Bitte wählen Sie eine Ersatz-Kategorie, auf die diese Buchungen übertragen werden sollen.
                    </p>

                    <div class="mb-8">
                        <label class="block text-[9px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Neue Kategorie</label>
                        <select wire:model="targetCategoryId" class="w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm p-3.5 focus:bg-black focus:ring-2 focus:ring-orange-500/30 focus:border-orange-500 transition-all shadow-inner outline-none cursor-pointer">
                            <option value="" class="bg-gray-900">Bitte wählen...</option>
                            @foreach($this->manageableCategories as $cat)
                                @if($cat->id !== $categoryToDeleteId)
                                    <option value="{{ $cat->id }}" class="bg-gray-900">{{ $cat->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('targetCategoryId') <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest mt-2 block ml-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 sm:gap-4 mt-8 pt-6 border-t border-gray-800">
                        <button wire:click="cancelDeleteCategory" class="px-5 py-3.5 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white hover:bg-gray-800 rounded-xl transition-colors w-full sm:w-auto text-center">Abbrechen</button>
                        <button wire:click="confirmDeleteCategory" class="px-5 py-3.5 text-[10px] font-black uppercase tracking-widest text-gray-900 bg-red-500 hover:bg-red-400 rounded-xl shadow-[0_0_20px_rgba(239,68,68,0.3)] hover:scale-[1.02] transition-all w-full sm:w-auto text-center">Löschen & Übertragen</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Chart Script --}}
    @include('livewire.shop.financial.financial-categories-special-editions.partials.chart_scripts')
</div>

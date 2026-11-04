<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="min-h-screen bg-transparent font-sans text-gray-700 dark:text-gray-300" x-data="{ showPreview: true }">
    @if($viewMode === 'list')
        {{-- ZENTRALE PRODUKT-SUITE NAVIGATION (KOLLEKTION · VERPACKUNG & LUCID · LIEFERANTEN · VORLAGEN) --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-gray-200 dark:border-gray-800">
            <div class="grid grid-cols-2 sm:inline-flex sm:flex-wrap items-center gap-1.5 p-1.5 bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xs w-full sm:w-auto">
                <button type="button"
                        wire:click="setActiveSection('collection')"
                        class="px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center sm:justify-start gap-2 cursor-pointer {{ $activeSection === 'collection' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                    <x-heroicon-o-cube class="w-4 h-4 shrink-0" />
                    <span class="truncate">Kollektion</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black shrink-0 {{ $activeSection === 'collection' ? 'bg-black/15 text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' }}">{{ $suiteCounts['products'] ?? 0 }}</span>
                </button>

                <button type="button"
                        wire:click="setActiveSection('packaging')"
                        class="px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center sm:justify-start gap-2 cursor-pointer {{ $activeSection === 'packaging' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                    <x-heroicon-o-archive-box class="w-4 h-4 shrink-0" />
                    <span class="truncate">Verpackung &amp; LUCID</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black shrink-0 {{ $activeSection === 'packaging' ? 'bg-black/15 text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' }}">{{ $suiteCounts['packaging'] ?? 0 }}</span>
                </button>

                <button type="button"
                        wire:click="setActiveSection('suppliers')"
                        class="px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center sm:justify-start gap-2 cursor-pointer {{ $activeSection === 'suppliers' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                    <x-heroicon-o-truck class="w-4 h-4 shrink-0" />
                    <span class="truncate">Lieferanten</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black shrink-0 {{ $activeSection === 'suppliers' ? 'bg-black/15 text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' }}">{{ $suiteCounts['suppliers'] ?? 0 }}</span>
                </button>

                <button type="button"
                        wire:click="setActiveSection('templates')"
                        class="px-3 sm:px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center sm:justify-start gap-2 cursor-pointer {{ $activeSection === 'templates' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                    <x-heroicon-o-clipboard-document-list class="w-4 h-4 shrink-0" />
                    <span class="truncate">Gravur-Vorlagen</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black shrink-0 {{ $activeSection === 'templates' ? 'bg-black/15 text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' }}">{{ $suiteCounts['templates'] ?? 0 }}</span>
                </button>
            </div>
        </div>

        @if($activeSection === 'collection')
            @include('livewire.shop.product.partials._list')
        @elseif($activeSection === 'packaging')
            <livewire:shop.product.product-packaging-configurator wire:key="suite-packaging" />
        @elseif($activeSection === 'suppliers')
            <livewire:shop.product.product-suppliers wire:key="suite-suppliers" />
        @elseif($activeSection === 'templates')
            <livewire:shop.product.product-templates
                :initialProductId="$templatePreselectProductId"
                :initialTemplateId="$templatePreselectId"
                :startCreate="$templateStartCreate"
                wire:key="suite-templates-{{ $templateEmbedVersion }}" />
        @endif
    @elseif($viewMode === 'edit')
        @include('livewire.shop.product.partials._edit')
    @endif
</div>

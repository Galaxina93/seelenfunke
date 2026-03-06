<div class="p-4 lg:p-8 bg-transparent min-h-screen font-sans antialiased text-gray-300" x-data="{ activeTab: localStorage.getItem('shop_config_tab') || 'general' }" x-init="$watch('activeTab', value => localStorage.setItem('shop_config_tab', value))">
    <div class="max-w-6xl mx-auto animate-fade-in-up">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight">Shop-Konfiguration</h1>
                <p class="text-xs sm:text-sm text-gray-400 mt-2 font-medium">Zentrale Steuerung aller Shop-Parameter und rechtlichen Grundlagen.</p>
            </div>
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                x-data="{ saved: @entangle('saved') }"
                x-effect="if (saved) { setTimeout(() => { saved = false; $wire.resetSaved(); }, 3000) }"
                :class="saved ? 'bg-emerald-500 text-gray-900 shadow-[0_0_20px_rgba(16,185,129,0.4)]' : 'bg-primary text-gray-900 hover:bg-primary-dark hover:scale-[1.02] shadow-[0_0_15px_rgba(197,160,89,0.2)]'"
                class="px-8 py-3.5 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all flex items-center gap-3 min-w-[240px] justify-center border border-transparent">

                <svg wire:loading class="animate-spin h-5 w-5 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <template x-if="saved">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-900 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Erfolgreich gespeichert!</span>
                    </div>
                </template>

                <template x-if="!saved">
                    <div class="flex items-center gap-2 hover:text-white" wire:loading.remove>
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        <span>Einstellungen sichern</span>
                    </div>
                </template>
            </button>
        </div>

        {{-- Tab Navigation --}}
        <div class="flex border-b border-gray-800 mb-8 overflow-x-auto no-scrollbar gap-2 sm:gap-6">
            <button @click="activeTab = 'general'" :class="activeTab === 'general' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Allgemein & Steuern</button>
            <button @click="activeTab = 'products'" :class="activeTab === 'products' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Produkt & Marketing</button>
            <button @click="activeTab = 'shipping'" :class="activeTab === 'shipping' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Versand & Lieferzeit</button>
            <button @click="activeTab = 'owner'" :class="activeTab === 'owner' ? 'border-primary text-primary drop-shadow-[0_0_8px_currentColor]' : 'border-transparent text-gray-500 hover:text-gray-300'" class="whitespace-nowrap pb-4 px-2 border-b-2 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all">Stammdaten (Impressum)</button>
        </div>

        @php
            $inputClass = "w-full bg-gray-950 border border-gray-800 text-white rounded-xl text-sm p-3.5 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all shadow-inner outline-none placeholder-gray-600";
            $labelClass = "block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1";
            $checkboxContainerClass = "flex items-center gap-4 cursor-pointer p-4 rounded-2xl border border-gray-800 bg-gray-950 hover:border-gray-700 hover:bg-gray-900/80 transition-all shadow-inner group";
        @endphp

        <div class="grid grid-cols-1 gap-8">

            {{-- TAB: ALLGEMEIN --}}
            @include('livewire.shop.config.partials.general_tab')

            {{-- TAB: PRODUKT & MARKETING --}}
            @include('livewire.shop.config.partials.products_tab')

            {{-- TAB: VERSAND --}}
            @include('livewire.shop.config.partials.shipping_tab')

            {{-- TAB: STAMMDATEN --}}
            @include('livewire.shop.config.partials.ower_tab')

        </div>
    </div>
</div>

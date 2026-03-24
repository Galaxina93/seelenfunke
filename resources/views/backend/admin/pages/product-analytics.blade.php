<x-layouts.backend_layout guard="admin">

    <x-slot name="header">
        <i class="bi bi-chart-pie text-3xl text-primary shrink-0"></i>
        <h2 class="font-bold text-2xl text-white tracking-widest uppercase ml-3">
            Produkt Analytics & Economics
        </h2>
        <div class="ml-auto">
            <button onclick="window.print()" class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-400 bg-gray-900 border border-gray-800 hover:text-white hover:border-gray-500 transition-all shadow-inner flex items-center gap-2">
                <i class="bi bi-printer"></i>
                Drucken
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
            <!-- LIVEWIRE COMPONENT -->
            @livewire('shop.product.product-analytics')
        </div>
    </div>

</x-layouts.backend_layout>

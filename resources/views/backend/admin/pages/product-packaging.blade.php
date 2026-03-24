<x-layouts.backend_layout guard="admin">

    <x-slot name="header">
        <i class="bi bi-box-seam text-3xl text-primary shrink-0"></i>
        <h2 class="font-bold text-2xl text-white tracking-widest uppercase ml-3">
            Verpackungsmaterialien
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
            <!-- LIVEWIRE COMPONENT -->
            @livewire('shop.product.product-packaging-configurator')
        </div>
    </div>

</x-layouts.backend_layout>

<x-layouts.backend_layout guard="admin">

    <x-slot name="header">
        <x-heroicon-o-archive-box-x-mark class="w-8 h-8 text-primary shrink-0" />
        <h2 class="font-bold text-2xl text-white tracking-widest uppercase">
            Widerruf Verwaltung
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:backend.admin.revocation.revocation-index />
        </div>
    </div>
</x-layouts.backend_layout>

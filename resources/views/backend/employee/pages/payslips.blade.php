<x-layouts.backend_layout guard="employee">

    @section('content')
        <div class="mb-8 animate-fade-in-up">
            <h1 class="text-3xl font-serif font-bold text-white tracking-tight flex items-center gap-3">
                <x-heroicon-o-document-currency-euro class="w-8 h-8 text-cyan-500 drop-shadow-[0_0_10px_rgba(6,182,212,0.5)]" />
                Gehaltsabrechnungen
            </h1>
            <p class="text-gray-400 mt-2 text-sm">Hier findest du eine Übersicht und Downloads all deiner monatlichen Gehalts- und Lohnabrechnungen.</p>
        </div>

        <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl p-8 shadow-[0_4px_30px_rgba(0,0,0,0.3)] min-h-[400px] flex flex-col items-center justify-center animate-fade-in-up" style="animation-delay: 0.1s;">
            <div class="w-20 h-20 rounded-full bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center mb-6">
                <x-heroicon-o-document-text class="w-10 h-10 text-cyan-400" />
            </div>
            <h2 class="text-xl font-bold text-white mb-2">Sicheres Dokumentenarchiv</h2>
            <p class="text-gray-400 text-center max-w-md mb-6">Das Modul für Gehaltsabrechnungen wird derzeit vorbereitet und in Kürze hier für dich verfügbar sein.</p>
            
            <!-- Platzhalter für die zukünftige Livewire Komponente -->
            <div class="w-full max-w-2xl border border-gray-800/50 border-dashed rounded-xl p-6 bg-gray-950/30 flex items-center justify-center">
                <span class="text-gray-500 text-sm font-mono text-center">@livewire('employee.documents.payslip-list')<br><span class="text-xs italic opacity-70">(Zukünftige Komponente)</span></span>
            </div>
        </div>
    @endsection

</x-layouts.backend_layout>

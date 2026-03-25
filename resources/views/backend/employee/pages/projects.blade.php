<x-layouts.backend_layout guard="employee">

    @section('content')
        <div class="mb-8 animate-fade-in-up">
            <h1 class="text-3xl font-serif font-bold text-white tracking-tight flex items-center gap-3">
                <x-heroicon-o-briefcase class="w-8 h-8 text-primary drop-shadow-[0_0_10px_rgba(197,160,89,0.5)]" />
                Meine Projekte
            </h1>
            <p class="text-gray-400 mt-2 text-sm">Übersicht aller Projekte und Aufgaben, denen du zugeteilt bist.</p>
        </div>

        <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl p-8 shadow-[0_4px_30px_rgba(0,0,0,0.3)] min-h-[400px] flex flex-col items-center justify-center animate-fade-in-up" style="animation-delay: 0.1s;">
            <div class="w-20 h-20 rounded-full bg-gray-800/50 border border-gray-700 flex items-center justify-center mb-6">
                <x-heroicon-o-clipboard-document-list class="w-10 h-10 text-gray-500" />
            </div>
            <h2 class="text-xl font-bold text-white mb-2">Keine aktiven Projekte</h2>
            <p class="text-gray-400 text-center max-w-md mb-6">Aktuell bist du keinen Projekten zugewiesen. Sobald es neue Aufgaben gibt, werden diese hier erscheinen.</p>
            
            <!-- Platzhalter für eine Livewire Projekt-Listen Komponente -->
            <div class="w-full max-w-2xl border border-gray-800/50 border-dashed rounded-xl p-6 bg-gray-950/30 flex items-center justify-center">
                <span class="text-gray-500 text-sm font-mono text-center">@livewire('employee.projects.project-list')<br><span class="text-xs italic opacity-70">(Komponente wird hier eingebunden)</span></span>
            </div>
        </div>
    @endsection

</x-layouts.backend_layout>

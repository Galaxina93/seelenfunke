<x-layouts.backend_layout guard="employee">

    @section('content')
        <div class="mb-8 animate-fade-in-up">
            <h1 class="text-3xl font-serif font-bold text-white tracking-tight flex items-center gap-3">
                <x-heroicon-o-home class="w-8 h-8 text-primary drop-shadow-[0_0_10px_rgba(197,160,89,0.5)]" />
                Mitarbeiter Dashboard
            </h1>
            <p class="text-gray-400 mt-2 text-sm">Willkommen in deinem persönlichen Bereich. Hier findest du eine Übersicht deiner aktuellen Aktivitäten und Aufgaben.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-fade-in-up" style="animation-delay: 0.1s;">
            <!-- Placeholder Card 1 -->
            <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl p-6 shadow-[0_4px_30px_rgba(0,0,0,0.3)] hover:shadow-[0_0_20px_rgba(197,160,89,0.1)] hover:border-gray-700 transition-all group flex flex-col h-full">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0 group-hover:bg-primary/20 transition-colors">
                        <x-heroicon-o-briefcase class="w-6 h-6 text-primary" />
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-lg">Aktuelle Projekte</h3>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-black">Laufende Aufgaben</p>
                    </div>
                </div>
                <div class="flex-1 flex items-center justify-center py-6 border-t border-b border-gray-800/50 border-dashed my-2">
                    <span class="text-gray-500 text-sm italic">Inhalt wird geladen...</span>
                </div>
                <a href="/employee/projects" class="mt-4 w-full py-2.5 px-4 bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold uppercase tracking-widest rounded-xl text-center transition-colors">
                    Alle Projekte ansehen
                </a>
            </div>

            <!-- Placeholder Card 2 -->
            <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl p-6 shadow-[0_4px_30px_rgba(0,0,0,0.3)] hover:shadow-[0_0_20px_rgba(197,160,89,0.1)] hover:border-gray-700 transition-all group flex flex-col h-full">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center shrink-0 group-hover:bg-cyan-500/20 transition-colors">
                        <x-heroicon-o-document-currency-euro class="w-6 h-6 text-cyan-400" />
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-lg">Abrechnungen</h3>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-black">Neueste Dokumente</p>
                    </div>
                </div>
                <div class="flex-1 flex items-center justify-center py-6 border-t border-b border-gray-800/50 border-dashed my-2">
                    <span class="text-gray-500 text-sm italic">Livewire Komponente einfügen</span>
                </div>
                <a href="/employee/payslips" class="mt-4 w-full py-2.5 px-4 bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold uppercase tracking-widest rounded-xl text-center transition-colors">
                    Zur Verwaltung
                </a>
            </div>
            
            <!-- Placeholder Card 3 -->
            <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl p-6 shadow-[0_4px_30px_rgba(0,0,0,0.3)] hover:shadow-[0_0_20px_rgba(197,160,89,0.1)] hover:border-gray-700 transition-all group flex flex-col h-full">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center shrink-0 group-hover:bg-emerald-500/20 transition-colors">
                        <x-heroicon-o-clock class="w-6 h-6 text-emerald-400" />
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-lg">Zeiterfassung</h3>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-black">Heute gearbeitet</p>
                    </div>
                </div>
                <div class="flex-1 flex items-center justify-center py-6 border-t border-b border-gray-800/50 border-dashed my-2">
                    <span class="text-gray-500 text-sm italic">Bald verfügbar</span>
                </div>
                <button class="mt-4 w-full py-2.5 px-4 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-bold uppercase tracking-widest rounded-xl text-center transition-colors opacity-50 cursor-not-allowed">
                    Einstempeln
                </button>
            </div>
        </div>

    @endsection

</x-layouts.backend_layout>

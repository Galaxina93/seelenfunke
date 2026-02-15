{{--
<div class="space-y-8"> <div class="relative overflow-hidden bg-white border border-slate-100 rounded-3xl p-6 shadow-sm">
        <div class="relative z-10 flex flex-col lg:flex-row items-center gap-8">
            <div class="relative flex-shrink-0">
                <div class="absolute inset-0 bg-blue-400 rounded-full blur-2xl opacity-10 animate-pulse"></div>
                <div class="relative">
                    <div class="absolute -inset-1 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-400 opacity-20 animate-spin-slow"></div>
                    <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                         alt="Funki Selfie"
                         class="relative w-28 h-28 lg:w-32 lg:h-32 rounded-full object-cover border-4 border-white shadow-xl z-10">
                    <span class="absolute bottom-2 right-2 w-5 h-5 bg-green-500 border-4 border-white rounded-full z-20 shadow-sm" title="Funki ist aktiv"></span>
                </div>
            </div>

            <div class="flex-1 space-y-4 text-center lg:text-left">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 tracking-wider uppercase mb-2">
                        <i class="bi bi-stars me-1"></i> System-Zentrale
                    </span>
                    <h2 class="text-2xl lg:text-3xl font-extrabold text-slate-900 tracking-tight">
                        "Ich bin das Gehirn von <span class="text-blue-600">Mein Seelenfunke</span>!"
                    </h2>
                </div>
                <p class="text-lg text-slate-600 leading-relaxed italic font-medium">
                    Hey Alina! Lehn dich zurück. Ich steuere unsere gesamte Firma im Hintergrund.
                    Während du neue Magie erschaffst, kümmere ich mich um den Versand, die Kundenbindung und das Wachstum. ✨
                </p>
            </div>
        </div>
    </div>

    <div class="bg-indigo-900 rounded-2xl p-5 shadow-lg shadow-indigo-200">
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm text-indigo-100 leading-relaxed">
                    <strong class="text-white">Autonomie-Status:</strong> Damit Funki reibungslos arbeiten kann, läuft bei All-Inkl der Taktgeber:
                    <code class="bg-black/20 text-white px-2 py-0.5 rounded mx-1 font-mono text-xs border border-white/10">php artisan schedule:run</code>
                </p>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        @foreach($autoTasks as $task)
            <div class="group bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:border-blue-200 hover:shadow-md transition-all duration-300">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start gap-5">
                        <div class="w-14 h-14 shrink-0 rounded-2xl {{ $task['status'] === 'active' ? 'bg-blue-50 text-blue-600' : 'bg-slate-50 text-slate-400' }} flex items-center justify-center transition-colors group-hover:scale-110 duration-300">
                            <i class="bi {{ $task['icon'] }} fs-3"></i>
                        </div>

                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-lg font-bold text-slate-900">{{ $task['name'] }}</h4>
                                <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md font-mono uppercase tracking-wider">{{ $task['id'] }}</span>
                            </div>
                            <p class="text-sm text-slate-500 max-w-2xl leading-relaxed">{{ $task['description'] }}</p>

                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 pt-2">
                                <span class="text-xs font-medium text-slate-400 flex items-center gap-1.5">
                                    <i class="bi bi-clock"></i>
                                    {{ $task['schedule'] }}
                                </span>
                                <span class="text-xs font-medium text-slate-400 flex items-center gap-1.5">
                                    <i class="bi bi-arrow-repeat"></i>
                                    Zuletzt: {{ $task['last_run'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        @if($task['status'] === 'active')
                            <div class="flex items-center gap-3 bg-green-50 text-green-700 px-4 py-2.5 rounded-xl border border-green-100 shadow-sm">
                                <div class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                </div>
                                <span class="text-sm font-bold uppercase tracking-wider">Automatisiert</span>
                            </div>
                        @else
                            <div class="bg-slate-50 text-slate-400 px-4 py-2.5 rounded-xl border border-slate-100 italic text-sm font-medium text-center min-w-[140px]">
                                In Planung
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="pt-6">
        <h4 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-3 mb-6">
            <span class="w-8 h-px bg-slate-200"></span>
            Letzte Aktivitäten
        </h4>

        <div class="bg-white border border-slate-100 rounded-3xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-8 py-4 font-bold text-slate-600">Prozess</th>
                        <th class="px-8 py-4 font-bold text-slate-600">Zeitpunkt</th>
                        <th class="px-8 py-4 font-bold text-slate-600 text-center">Dauer</th>
                        <th class="px-8 py-4 font-bold text-slate-600 text-right">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                    @forelse($this->history as $entry)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-8 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                    <div>
                                        <span class="block font-bold text-slate-900">{{ $entry->task_name }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono tracking-tighter">{{ $entry->task_id }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-4 text-slate-500 font-medium">
                                {{ $entry->started_at->format('d.m.Y') }}
                                <span class="text-slate-300 mx-1">|</span>
                                <span class="text-slate-900">{{ $entry->started_at->format('H:i') }} Uhr</span>
                            </td>
                            <td class="px-8 py-4 text-slate-500 text-center font-mono text-xs">
                                {{ $entry->finished_at ? $entry->finished_at->diffInSeconds($entry->started_at) . 's' : '-' }}
                            </td>
                            <td class="px-8 py-4 text-right">
                                @if($entry->status === 'success')
                                    <span class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-bold bg-green-100 text-green-700">Erfolgreich</span>
                                @elseif($entry->status === 'error')
                                    <span class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-bold bg-red-100 text-red-700">Fehler</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-full text-xs font-bold bg-blue-100 text-blue-700 animate-pulse">Aktiv...</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-8 py-12 text-center text-slate-400 italic">Bisher wurden noch keine autonomen Prozesse protokolliert.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin-slow {
            animation: spin-slow 12s linear infinite;
        }
    </style>
</div>
--}}

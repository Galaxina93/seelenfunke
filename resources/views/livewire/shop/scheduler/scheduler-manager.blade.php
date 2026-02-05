<div>
    <div class="space-y-6">
        {{-- Info-Box für Cronjob-Status --}}
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-xl mb-8">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>System-Status:</strong> Damit diese Prozesse automatisch laufen, muss ein Cronjob bei All-Inkl eingerichtet sein:
                        <code class="bg-blue-100 px-2 py-1 rounded">php artisan schedule:run</code> (Intervall: Jede Minute)
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
            @foreach($tasks as $task)
                <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center text-gray-400">
                                @if($task['status'] === 'running')
                                    <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @else
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-gray-900">{{ $task['name'] }}</h4>
                                    <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded font-mono uppercase">{{ $task['id'] }}</span>
                                </div>
                                <p class="text-sm text-gray-500 mt-0.5">{{ $task['description'] }}</p>
                                <div class="flex items-center gap-4 mt-2">
                                <span class="text-xs text-gray-400 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    {{ $task['schedule'] }}
                                </span>
                                    <span class="text-xs text-gray-400 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M5 13l4 4L19 7" /></svg>
                                    Zuletzt: {{ $task['last_run'] }}
                                </span>
                                </div>
                            </div>
                        </div>

                        <button
                            wire:click="runTask('{{ $task['id'] }}')"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 bg-gray-900 hover:bg-black text-white text-sm font-bold rounded-xl transition-all flex items-center gap-2 justify-center"
                        >
                            <span wire:loading.remove wire:target="runTask('{{ $task['id'] }}')">Jetzt ausführen</span>
                            <span wire:loading wire:target="runTask('{{ $task['id'] }}')">Wird ausgeführt...</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Letzte Aktivitäten (History) --}}
        <div class="mt-12">
            <h4 class="text-sm font-bold text-gray-900 uppercase tracking-widest mb-6 flex items-center gap-2">
                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Letzte Aktivitäten
            </h4>

            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 font-bold text-gray-600">Task</th>
                        <th class="px-6 py-3 font-bold text-gray-600">Zeitpunkt</th>
                        <th class="px-6 py-3 font-bold text-gray-600">Dauer</th>
                        <th class="px-6 py-3 font-bold text-gray-600">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    @foreach($this->history as $entry)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="block font-bold text-gray-900">{{ $entry->task_name }}</span>
                                <span class="text-[10px] text-gray-400 font-mono">{{ $entry->task_id }}</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $entry->started_at->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $entry->finished_at ? $entry->finished_at->diffInSeconds($entry->started_at) . ' Sek.' : '-' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($entry->status === 'success')
                                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Erfolgreich
                                </span>
                                @elseif($entry->status === 'error')
                                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold bg-red-100 text-red-700" title="{{ $entry->output }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Fehler
                                </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700 animate-pulse">
                                    Läuft...
                                </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

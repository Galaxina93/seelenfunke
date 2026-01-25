<div x-data="{ showFailedLogins: false, showFullLogins: false }">

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Statistik-Karten --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-2xl p-4 w-full text-sm flex flex-col">

            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Systemübersicht (Backend)</h2>

            <dl class="space-y-2 flex-grow">
                <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-1">
                    <dt class="text-gray-500 dark:text-gray-400">Gesamt Benutzer</dt>
                    <dd class="font-semibold text-gray-800 dark:text-white">{{ $stats['total_users'] }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-1">
                    <dt class="text-gray-500 dark:text-gray-400">Heute aktiv</dt>
                    <dd class="font-semibold text-gray-800 dark:text-white">{{ $stats['active_users_today'] }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-1">
                    <dt class="text-gray-500 dark:text-gray-400">Registrierungen (Woche)</dt>
                    <dd class="font-semibold text-gray-800 dark:text-white">{{ $stats['new_registrations_week'] }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-1">
                    <dt class="text-gray-500 dark:text-gray-400">Noch nie eingeloggt</dt>
                    <dd class="font-semibold text-gray-800 dark:text-white">{{ $stats['never_logged_in'] }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-1">
                    <dt class="text-gray-500 dark:text-gray-400">Inaktive (30+ Tage)</dt>
                    <dd class="font-semibold text-gray-800 dark:text-white">{{ $stats['inactive_30_days'] }}</dd>
                </div>
                <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-1">
                    <dt class="text-gray-500 dark:text-gray-400">Aktive Sessions</dt>
                    <dd class="font-semibold text-gray-800 dark:text-white">{{ $stats['active_sessions'] }}</dd>
                </div>

                {{-- Fehlgeschlagene Logins --}}
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Fehlgeschl. Logins</dt>
                    <dd class="font-semibold text-primary cursor-pointer"
                        @click="showFailedLogins = !showFailedLogins">
                        {{ $stats['failed_logins'] }}
                    </dd>
                </div>

                {{-- Letzte Logins klein --}}
                <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-2 text-xs">
                    <div class="flex justify-between border-b border-gray-200 dark:border-gray-700 pb-1">
                        <dt class="text-gray-500 dark:text-gray-400">Letzte Logins</dt>
                        <dd class="text-gray-800 dark:text-white">
                            <button class="mt-2 text-primary text-[11px] hover:underline"
                                    @click="showFullLogins = !showFullLogins">
                                Tabelle anzeigen
                            </button>
                        </dd>
                    </div>
                </div>
            </dl>
        </div>

        {{-- Website Analytics --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-2xl p-4 w-full text-sm flex flex-col">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-4">Webseitenanalyse (Frontend)</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 shadow rounded-2xl p-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">insgesamt</h3>
                    <p class="text-2xl font-bold text-primary mt-2">{{ $stats['frontend_visits_total'] }}</p>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow rounded-2xl p-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">heute</h3>
                    <p class="text-2xl font-bold text-primary mt-2">{{ $stats['frontend_visits_today'] }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow rounded-2xl p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Besucherentwicklung</h3>
                <canvas id="visitsChart" height="100"></canvas>
            </div>

            @push('scripts')
                <script>
                    const ctx = document.getElementById('visitsChart').getContext('2d');
                    const visitsChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: {!! json_encode($stats['visit_days']) !!},
                            datasets: [{
                                label: 'Seitenaufrufe',
                                data: {!! json_encode($stats['visit_counts']) !!},
                                borderColor: '#3B82F6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointRadius: 4,
                                pointBackgroundColor: '#3B82F6'
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            },
                            plugins: {
                                legend: { display: false }
                            }
                        }
                    });
                </script>
            @endpush
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {{-- Login Verlauf (ausblendbar) --}}
        <div x-show="showFullLogins"
             x-transition:enter="transition-all ease-out duration-500"
             x-transition:enter-start="opacity-0 max-h-0"
             x-transition:enter-end="opacity-100 max-h-[1000px]"
             x-transition:leave="transition-all ease-in duration-400"
             x-transition:leave-start="opacity-100 max-h-[1000px]"
             x-transition:leave-end="opacity-0 max-h-0"
             class="bg-white dark:bg-gray-800 shadow rounded-2xl p-4 w-full text-sm flex flex-col mt-6 overflow-hidden">
            <h2 class="text-lg font-bold mb-3 text-gray-800 dark:text-white">Login Verlauf</h2>
            <div class="overflow-x-auto flex-grow">
                <table class="table-auto w-full text-sm">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <th class="px-3 py-2 text-left">Typ</th>
                        <th class="px-3 py-2 text-left">Name</th>
                        <th class="px-3 py-2 text-left">Letzter Login</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($paginatedLogins as $login)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-3 py-2">{{ $login['type'] }}</td>
                            <td class="px-3 py-2">{{ $login['first_name'] }} {{ $login['last_name'] }}</td>
                            <td class="px-3 py-2">
                                @if(!$login['last_seen'])
                                    <span class="text-gray-400">Noch nie eingeloggt</span>
                                @else
                                    {{ \Carbon\Carbon::parse($login['last_seen'])->format('d.m.Y H:i') }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-gray-400 py-3">Keine Logins gefunden</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $paginatedLogins->links() }}
            </div>
        </div>

        {{-- Fehlgeschlagene Logins (ausblendbar) --}}
        <div x-show="showFailedLogins"
             x-transition:enter="transition-all ease-out duration-500"
             x-transition:enter-start="opacity-0 max-h-0"
             x-transition:enter-end="opacity-100 max-h-[1000px]"
             x-transition:leave="transition-all ease-in duration-400"
             x-transition:leave-start="opacity-100 max-h-[1000px]"
             x-transition:leave-end="opacity-0 max-h-0"
             class="bg-white dark:bg-gray-800 shadow rounded-2xl p-4 w-full text-sm flex flex-col mt-6 overflow-hidden">

        <h2 class="text-lg font-bold mb-3 text-gray-800 dark:text-white">Fehlgeschlagene Logins</h2>
            <div class="overflow-x-auto flex-grow">
                <table class="table-auto w-full text-sm">
                    <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <th class="px-3 py-2 text-left">E-Mail</th>
                        <th class="px-3 py-2 text-left">IP-Adresse</th>
                        <th class="px-3 py-2 text-left">Datum</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($paginatedFailedLogins as $attempt)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-3 py-2">{{ $attempt->email }}</td>
                            <td class="px-3 py-2">{{ $attempt->ip_address }}</td>
                            <td class="px-3 py-2">
                                {{ \Carbon\Carbon::parse($attempt->attempted_at)->format('d.m.Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-gray-400 py-3">Keine fehlgeschlagenen Logins</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $paginatedFailedLogins->links() }}
            </div>
        </div>
    </div>

</div>

<div class="p-6 bg-slate-50 min-h-screen">
    {{-- Notwendig für Charts --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="max-w-7xl mx-auto space-y-8">

        {{-- Header --}}
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                <i class="solar-pulse-bold-duotone text-indigo-500"></i>
                System & Health Check
            </h1>
            <p class="text-slate-500 mt-2">Zentrale Überwachung der Systemintegrität und Analysen.</p>
        </div>

        {{-- SECTION 1: BUSINESS LOGIC CHECKS --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($checks as $check)
                <div class="relative group bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-lg transition-all duration-300">

                    {{-- Status Indicator Bar --}}
                    <div class="absolute top-0 left-0 w-full h-1
                        {{ $check['status'] === 'success' ? 'bg-emerald-400' : '' }}
                        {{ $check['status'] === 'warning' ? 'bg-amber-400' : '' }}
                        {{ $check['status'] === 'danger' ? 'bg-rose-500' : '' }}
                        {{ $check['status'] === 'info' ? 'bg-sky-400' : '' }}">
                    </div>

                    <div class="flex justify-between items-start mb-3">
                        <div class="p-2.5 rounded-xl
                            {{ $check['status'] === 'success' ? 'bg-emerald-50 text-emerald-600' : '' }}
                            {{ $check['status'] === 'warning' ? 'bg-amber-50 text-amber-600' : '' }}
                            {{ $check['status'] === 'danger' ? 'bg-rose-50 text-rose-600' : '' }}
                            {{ $check['status'] === 'info' ? 'bg-sky-50 text-sky-600' : '' }}">
                            <i class="{{ $check['icon'] }} text-2xl"></i>
                        </div>
                        @if($check['count'] > 0)
                            <span class="font-bold text-lg text-slate-800">{{ $check['count'] }}</span>
                        @endif
                    </div>

                    <h3 class="font-bold text-slate-700">{{ $check['title'] }}</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4 line-clamp-2 min-h-[2.5em]">{{ $check['message'] }}</p>

                    <a href="{{ $check['action_url'] }}" class="text-xs font-semibold flex items-center gap-1
                        {{ $check['status'] === 'danger' ? 'text-rose-600 hover:text-rose-800' : 'text-indigo-600 hover:text-indigo-800' }}">
                        {{ $check['action_label'] }}
                        <svg class="w-3 h-3 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            @endforeach
        </div>

        {{-- SECTION 2: ANALYTICS & STATS --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- 2.1 Backend Stats --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col">
                <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class="solar-server-square-bold-duotone text-slate-400"></i> Backend Status
                </h2>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-slate-50 p-4 rounded-xl">
                        <div class="text-xs text-slate-500 uppercase font-semibold">User Gesamt</div>
                        <div class="text-2xl font-bold text-slate-800">{{ $stats['total_users'] }}</div>
                    </div>
                    <div class="bg-indigo-50 p-4 rounded-xl">
                        <div class="text-xs text-indigo-500 uppercase font-semibold">Heute Aktiv</div>
                        <div class="text-2xl font-bold text-indigo-600">{{ $stats['active_users_today'] }}</div>
                    </div>
                </div>

                <div class="space-y-3 text-sm flex-grow">
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500">Neu diese Woche</span>
                        <span class="font-semibold text-emerald-600">+{{ $stats['new_registrations_week'] }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500">Inaktiv (>30 Tage)</span>
                        <span class="font-semibold text-slate-700">{{ $stats['inactive_30_days'] }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-100">
                        <span class="text-slate-500">Aktive Sessions</span>
                        <span class="font-semibold text-indigo-600 animate-pulse">{{ $stats['active_sessions'] }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-slate-500">Nie eingeloggt</span>
                        <span class="font-semibold text-slate-400">{{ $stats['never_logged_in'] }}</span>
                    </div>
                </div>
            </div>

            {{-- 2.2 Frontend Analytics Chart --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 xl:col-span-2 flex flex-col">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="solar-chart-2-bold-duotone text-blue-500"></i> Traffic Analyse
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">Besucherzahlen der aktuellen Woche</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-slate-500">Seitenaufrufe Heute</div>
                        <div class="text-2xl font-bold text-blue-600">{{ $stats['frontend_visits_today'] }}</div>
                    </div>
                </div>

                <div class="relative w-full h-64">
                    <canvas id="visitsChart"></canvas>
                </div>
            </div>
        </div>

        {{-- SECTION 3: DETAIL TABLES --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-data="{ showFailed: @entangle('showFailedLogins'), showLogins: @entangle('showFullLogins') }">

            {{-- 3.1 Login History --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-700">Letzte Aktivitäten</h3>
                    <button @click="showLogins = !showLogins" class="text-xs text-indigo-500 hover:text-indigo-700 font-medium">
                        <span x-text="showLogins ? 'Einklappen' : 'Anzeigen'"></span>
                    </button>
                </div>

                <div x-show="showLogins" x-collapse>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                            <tr>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Rolle</th>
                                <th class="px-4 py-3 text-right">Zuletzt gesehen</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($paginatedLogins as $login)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 font-medium text-slate-700">
                                        {{ $login['first_name'] }} {{ $login['last_name'] }}
                                    </td>
                                    <td class="px-4 py-3">
                                            <span class="px-2 py-1 rounded-md text-[10px] font-bold
                                                {{ $login['type'] === 'Admin' ? 'bg-purple-100 text-purple-700' : '' }}
                                                {{ $login['type'] === 'Customer' ? 'bg-blue-100 text-blue-700' : '' }}
                                                {{ $login['type'] === 'Employee' ? 'bg-teal-100 text-teal-700' : '' }}">
                                                {{ $login['type'] }}
                                            </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-500">
                                        {{ \Carbon\Carbon::parse($login['last_seen'])->diffForHumans() }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-4 text-center text-slate-400">Keine Daten</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t border-slate-100">
                        {{ $paginatedLogins->links() }}
                    </div>
                </div>
            </div>

            {{-- 3.2 Failed Logins --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-rose-50/30">
                    <h3 class="font-bold text-rose-700 flex items-center gap-2">
                        @if($stats['failed_logins'] > 0)
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                        @endif
                        Fehlgeschlagene Logins
                    </h3>
                    <div class="flex gap-4 items-center">
                        <span class="text-xs font-bold bg-rose-100 text-rose-600 px-2 py-0.5 rounded">{{ $stats['failed_logins'] }} Total</span>
                        <button @click="showFailed = !showFailed" class="text-xs text-rose-500 hover:text-rose-700 font-medium">
                            <span x-text="showFailed ? 'Einklappen' : 'Anzeigen'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="showFailed" x-collapse>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-rose-800 uppercase bg-rose-50">
                            <tr>
                                <th class="px-4 py-3">IP Address</th>
                                <th class="px-4 py-3">Email Versuch</th>
                                <th class="px-4 py-3 text-right">Zeitpunkt</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-rose-100">
                            @forelse($paginatedFailedLogins as $fail)
                                <tr class="hover:bg-rose-50/50 transition-colors">
                                    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $fail->ip_address }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $fail->email }}</td>
                                    <td class="px-4 py-3 text-right text-slate-500 text-xs">
                                        {{ \Carbon\Carbon::parse($fail->attempted_at)->format('d.m.Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="p-4 text-center text-slate-400">Keine Einträge</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-t border-slate-100">
                        {{ $paginatedFailedLogins->links() }}
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Chart Initialization --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('visitsChart').getContext('2d');

            // Gradient erstellen
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.5)'); // Start color (Blue)
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)'); // End color (Transparent)

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($stats['visit_days']),
                    datasets: [{
                        label: 'Seitenaufrufe',
                        data: @json($stats['visit_counts']),
                        borderColor: '#3B82F6',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        tension: 0.4, // Weiche Kurven
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#FFFFFF',
                        pointBorderColor: '#3B82F6',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9',
                                borderDash: [5, 5]
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 11 }
                            },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 11 }
                            },
                            border: { display: false }
                        }
                    }
                }
            });
        });
    </script>
</div>

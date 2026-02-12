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

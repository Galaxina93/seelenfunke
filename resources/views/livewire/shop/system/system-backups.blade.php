<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">

    <!-- Header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div>
            <h1 class="text-2xl md:text-3xl font-black tracking-widest uppercase shadow-emerald-500/20 drop-shadow-md text-[var(--theme-color)]">Sicherungen</h1>
            <p class="mt-1 text-sm text-gray-400">Übersicht und Verwaltung der Datenbank-Backups</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button wire:click="runTestBackup" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white font-bold rounded-lg border border-gray-700 hover:border-[var(--theme-color)] transition-all shadow-lg focus:outline-none focus:ring-2 focus:ring-[var(--theme-color)] focus:ring-opacity-50">
                <x-heroicon-o-play class="w-5 h-5 text-[var(--theme-color)]" wire:loading.remove wire:target="runTestBackup" />
                <svg class="animate-spin w-5 h-5 text-[var(--theme-color)] hidden" wire:loading.class.remove="hidden" wire:target="runTestBackup" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Test Backup</span>
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mb-8 p-4 bg-emerald-900/30 border border-emerald-800 rounded-xl flex items-center gap-3">
            <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-500 flex-shrink-0" />
            <p class="text-emerald-400 text-sm font-medium">{{ session('success') }}</p>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-8 p-4 bg-red-900/30 border border-red-800 rounded-xl flex items-center gap-3">
            <x-heroicon-o-x-circle class="w-6 h-6 text-red-500 flex-shrink-0" />
            <p class="text-red-400 text-sm font-medium">{{ session('error') }}</p>
        </div>
    @endif

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Count -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Anzahl Backups</p>
                    <p class="text-2xl font-black text-white tracking-wider">{{ $stats['count'] ?? 0 }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gray-800 border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shadow-inner">
                    <x-heroicon-o-archive-box class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- Total Size -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Gesamtspeicher</p>
                    <p class="text-2xl font-black text-white tracking-wider">{{ $stats['total_size'] ?? '0 B' }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gray-800 border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shadow-inner">
                    <x-heroicon-o-server class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- Newest -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Neuestes Backup</p>
                    <p class="text-sm font-bold text-white">{{ $stats['newest_date'] ? $stats['newest_date']->format('d.m.Y H:i:s') : 'Keines' }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $stats['newest_size'] ?? '0 B' }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gray-800 border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shadow-inner">
                    <x-heroicon-o-clock class="w-5 h-5" />
                </div>
            </div>
        </div>

        <!-- Oldest -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div class="flex items-center justify-between relative z-10">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold mb-1">Ältestes Backup</p>
                    <p class="text-sm font-bold text-white">{{ $stats['oldest_date'] ? $stats['oldest_date']->format('d.m.Y H:i:s') : 'Keines' }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gray-800 border border-[var(--theme-color-20)] flex items-center justify-center text-[var(--theme-color)] shadow-inner">
                    <x-heroicon-o-calendar class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    <!-- Details/Table -->
    <div class="bg-gray-900/50 backdrop-blur-xl border border-gray-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center bg-gray-900/80">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                <x-heroicon-o-table-cells class="w-5 h-5 text-[var(--theme-color)]" /> Backup-Historie
            </h2>
            <div class="text-[10px] uppercase tracking-widest text-gray-400 font-mono">
                APP: <span class="text-[var(--theme-color)]">{{ $appName }}</span>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="text-[10px] uppercase tracking-widest text-gray-400 bg-gray-950/50 border-b border-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-bold">Applikationsname</th>
                        <th scope="col" class="px-6 py-4 font-bold">Backup Name</th>
                        <th scope="col" class="px-6 py-4 font-bold">Speicherort</th>
                        <th scope="col" class="px-6 py-4 font-bold">Größe</th>
                        <th scope="col" class="px-6 py-4 font-bold">Datum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60">
                    @forelse($backups as $index => $backup)
                        <tr class="transition-colors hover:bg-gray-800/30">
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $appName }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-950 text-gray-300 border border-gray-800">
                                    <x-heroicon-o-archive-box class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                    {{ basename($backup['path']) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-gray-400 font-mono text-[11px] uppercase tracking-wider">{{ $diskName }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono text-gray-300 text-xs">{{ $backup['sizeFormatted'] }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-white">{{ $backup['date']->format('d.m.Y') }}</div>
                                <div class="text-gray-500 text-[10px] font-mono">{{ $backup['date']->format('H:i:s') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-800/50 mb-4">
                                    <x-heroicon-o-archive-box-x-mark class="w-8 h-8 text-gray-600" />
                                </div>
                                <h3 class="text-lg font-medium text-white mb-1">Keine Backups gefunden</h3>
                                <p class="text-gray-500 text-sm">Es wurden noch keine Backups für diese Applikation erstellt.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

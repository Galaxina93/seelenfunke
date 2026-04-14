@props(['voucher'])

<div class="relative bg-gray-950 border border-gray-800 rounded-3xl p-8 overflow-hidden group shadow-2xl mx-auto max-w-sm w-full">
    <!-- Glowing Accent Background -->
    <div class="absolute -top-32 -right-32 w-64 h-64 bg-[color:var(--theme-color-15)] rounded-full blur-3xl opacity-50 group-hover:opacity-100 transition-all duration-700"></div>
    <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-[color:var(--theme-color-10)] rounded-full blur-3xl opacity-50 group-hover:opacity-100 transition-all duration-700"></div>

    <div class="relative z-10 flex flex-col items-center">
        <!-- Icon -->
        <div class="w-16 h-16 rounded-2xl bg-[color:var(--theme-color-15)] border border-[color:var(--theme-color-30)] flex items-center justify-center mb-6 shadow-xl">
            <i class="bi bi-ticket-perforated-fill text-3xl text-[color:var(--theme-color)]"></i>
        </div>

        <!-- Voucher Code (Click to Copy Style) -->
        <div class="text-center mb-8">
            <span class="text-xs text-gray-500 font-medium mb-2 block">Gutschein Code</span>
            <div class="inline-flex items-center justify-center px-6 py-3 bg-gray-900 rounded-xl border border-gray-800 font-mono text-xl sm:text-2xl text-[color:var(--theme-color)] shadow-inner select-all">
                {{ $voucher['code'] ?? 'FEHLER' }}
            </div>
        </div>

        <!-- Voucher Stats Grid -->
        <div class="grid grid-cols-2 gap-4 w-full">
            <div class="bg-gray-900/50 rounded-xl p-4 border border-gray-800/50 flex flex-col items-center justify-center">
                <span class="text-xs text-gray-500 mb-1">Wert</span>
                <span class="text-lg font-bold text-white">{{ $voucher['value'] ?? '-' }}</span>
            </div>
            <div class="bg-gray-900/50 rounded-xl p-4 border border-gray-800/50 flex flex-col items-center justify-center">
                <span class="text-xs text-gray-500 mb-1">Limit</span>
                <span class="text-lg font-bold text-gray-300">{{ $voucher['usage_limit'] ?? '∞' }}</span>
            </div>
            <div class="bg-gray-900/50 rounded-xl p-4 border border-gray-800/50 flex flex-col items-center justify-center col-span-2">
                <span class="text-xs text-gray-500 mb-1">Ablaufdatum</span>
                <span class="text-sm font-medium text-rose-400">{{ $voucher['valid_until'] ?? 'Kein Ablaufdatum' }}</span>
            </div>
        </div>
        
        <!-- Status Badge -->
        <div class="mt-6 w-full flex justify-center">
             @if(($voucher['is_active'] ?? true))
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-medium bg-[color:var(--theme-color-10)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-30)] shadow-inner">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[color:var(--theme-color)] opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-[color:var(--theme-color)]"></span></span>
                    Aktiv & Einsatzbereit
                </span>
            @else
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-medium bg-gray-800 text-gray-500 border border-gray-700 shadow-inner">
                    <span class="h-2 w-2 rounded-full bg-gray-500"></span>
                    Pausiert
                </span>
            @endif
        </div>
    </div>
</div>

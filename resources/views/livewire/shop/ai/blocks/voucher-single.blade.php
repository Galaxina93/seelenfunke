@props(['voucher'])

<div class="relative bg-gray-950 border border-gray-800 rounded-3xl p-8 overflow-hidden group shadow-2xl mx-auto max-w-sm w-full">
    <!-- Glowing Accent Background -->
    <div class="absolute -top-32 -right-32 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl opacity-50 group-hover:opacity-100 group-hover:bg-emerald-400/20 transition-all duration-700"></div>
    <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl opacity-50 group-hover:opacity-100 group-hover:bg-cyan-400/20 transition-all duration-700"></div>

    <div class="relative z-10 flex flex-col items-center">
        <!-- Icon -->
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-400/20 to-cyan-500/20 flex items-center justify-center mb-6 shadow-glow ring-1 ring-emerald-500/30">
            <i class="bi bi-ticket-perforated-fill text-3xl text-emerald-400"></i>
        </div>

        <!-- Voucher Code (Click to Copy Style) -->
        <div class="text-center mb-8">
            <span class="text-xs uppercase tracking-[0.2em] text-gray-400 font-bold mb-2 block">Gutschein Code</span>
            <div class="inline-flex items-center justify-center px-6 py-3 bg-gray-900 rounded-xl border border-gray-700 font-mono text-xl sm:text-2xl text-emerald-300 tracking-wider shadow-inner select-all">
                {{ $voucher['code'] ?? 'FEHLER' }}
            </div>
        </div>

        <!-- Voucher Stats Grid -->
        <div class="grid grid-cols-2 gap-4 w-full">
            <div class="bg-gray-900/50 rounded-xl p-4 border border-gray-800/50 flex flex-col items-center justify-center">
                <span class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">Wert</span>
                <span class="text-lg font-bold text-white">{{ $voucher['value'] ?? '-' }}</span>
            </div>
            <div class="bg-gray-900/50 rounded-xl p-4 border border-gray-800/50 flex flex-col items-center justify-center">
                <span class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">Limit</span>
                <span class="text-lg font-bold text-gray-300">{{ $voucher['usage_limit'] ?? '∞' }}</span>
            </div>
            <div class="bg-gray-900/50 rounded-xl p-4 border border-gray-800/50 flex flex-col items-center justify-center col-span-2">
                <span class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">Ablaufdatum</span>
                <span class="text-sm font-mono text-rose-400">{{ $voucher['valid_until'] ?? 'Kein Ablaufdatum' }}</span>
            </div>
        </div>
        
        <!-- Status Badge -->
        <div class="mt-6 w-full flex justify-center">
             @if(($voucher['is_active'] ?? true))
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest shadow-inner">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-[0_0_8px_currentColor]"></span></span>
                    Aktiv & Einsatzbereit
                </span>
            @else
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-black bg-gray-800 text-gray-500 border border-gray-700 uppercase tracking-widest shadow-inner">
                    <span class="h-2 w-2 rounded-full bg-gray-500"></span>
                    Pausiert
                </span>
            @endif
        </div>
    </div>
</div>

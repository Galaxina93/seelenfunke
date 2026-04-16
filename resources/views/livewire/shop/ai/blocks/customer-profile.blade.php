@php
    $c = is_array($customer) ? $customer : (array)$customer;
    $name = $c['full_name'] ?? (($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? 'Unbekannt'));
    $email = $c['email'] ?? null;
    $phone = $c['phone'] ?? null;
    $orders = $c['orders_count'] ?? $c['orders'] ?? 0;
    $city = $c['city'] ?? null;
@endphp
<div>
    <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-40 h-40 bg-[color:var(--theme-color)] opacity-10 rounded-full blur-3xl group-hover:opacity-20 transition-all duration-700 pointer-events-none"></div>
        <div class="relative z-10">
            <div class="flex items-center gap-6 mb-6">
                <!-- Avatar -->
                <div class="w-20 h-20 shrink-0 rounded-full bg-gradient-to-br from-gray-800 to-gray-900 border border-[color:var(--theme-color-50)] flex items-center justify-center text-[color:var(--theme-color)] font-bold text-3xl shadow-[0_0_20px_var(--theme-color-20)]">
                    {{ substr(trim($name), 0, 1) }}
                </div>
                <!-- Name -->
                <div class="flex-1">
                    <h3 class="text-2xl font-sans font-bold text-white tracking-tight leading-tight">
                        {{ $name }}
                    </h3>
                    <div class="text-[color:var(--theme-color-70)] text-sm mt-1 flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></span>
                        Status: Akte geladen
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if($email)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                        <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">E-Mail</div>
                            <div class="text-sm text-gray-300 truncate">{{ $email }}</div>
                        </div>
                    </div>
                @endif
                @if($phone)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                        <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                            <i class="bi bi-phone"></i>
                        </div>
                        <div>
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Telefon</div>
                            <div class="text-sm text-gray-300">{{ $phone }}</div>
                        </div>
                    </div>
                @endif
                @if($city)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                        <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Standort</div>
                            <div class="text-sm text-gray-300">{{ $city }}</div>
                        </div>
                    </div>
                @endif
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                    <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                        <i class="bi bi-bag-check"></i>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Bestellungen</div>
                        <div class="text-sm font-bold text-white">{{ $orders }}</div>
                    </div>
                </div>
            </div>

            <!-- Dump the rest nicely -->
            <div class="mt-6 border-t border-gray-800/80 pt-4">
                <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 mb-3">Zusätzliche Datenfelder</div>
                <div class="bg-black/50 rounded-xl p-4 font-mono text-[10px] text-[color:var(--theme-color-70)] max-h-48 overflow-y-auto custom-scrollbar break-all">
                    @foreach($c as $k => $v)
                        @if(!in_array($k, ['full_name', 'first_name', 'last_name', 'email', 'phone', 'city', 'orders_count', 'orders']))
                            <div class="mb-1"><strong class="text-white opacity-70">{{ $k }}:</strong> {{ is_array($v) ? json_encode($v) : $v }}</div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

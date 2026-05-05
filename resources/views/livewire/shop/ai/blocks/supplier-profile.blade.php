@php
    $s = is_array($supplier) ? $supplier : (array)$supplier;
    $name = $s['name'] ?? 'Unbekannter Lieferant';
    $contact = $s['contact_person'] ?? null;
    $email = $s['email'] ?? null;
    $phone = $s['phone'] ?? null;
    $country = $s['country'] ?? null;
    $city = $s['city'] ?? null;
    $shipping = $s['shipping_method'] ?? 'Unbekannt';
    $products = collect($s['products'] ?? []);
@endphp
<div>
    <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-40 h-40 bg-[color:var(--theme-color)] opacity-10 rounded-full blur-3xl group-hover:opacity-20 transition-all duration-700 pointer-events-none"></div>
        <div class="relative z-10">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 mb-6 text-center sm:text-left">
                <!-- Avatar -->
                <div class="w-16 h-16 sm:w-20 sm:h-20 shrink-0 rounded-full bg-gradient-to-br from-gray-800 to-gray-900 border border-[color:var(--theme-color-50)] flex items-center justify-center text-[color:var(--theme-color)] font-bold text-2xl sm:text-3xl shadow-[0_0_20px_var(--theme-color-20)]">
                    <i class="bi bi-building"></i>
                </div>
                <!-- Name -->
                <div class="flex-1 w-full overflow-hidden">
                    <h3 class="text-xl sm:text-2xl font-sans font-bold text-white tracking-tight leading-tight truncate px-2 sm:px-0">
                        {{ $name }}
                    </h3>
                    <div class="text-[color:var(--theme-color-70)] text-xs sm:text-sm mt-1 flex items-center justify-center sm:justify-start gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></span>
                        Status: Lieferant geladen
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if($email || $contact)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                        <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">{{ $contact ?: 'E-Mail' }}</div>
                            <div class="text-sm text-gray-300 truncate">{{ $email ?: 'Keine E-Mail' }}</div>
                        </div>
                    </div>
                @endif
                @if($phone)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                        <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div>
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Telefon</div>
                            <div class="text-sm text-gray-300">{{ $phone }}</div>
                        </div>
                    </div>
                @endif
                @if($country || $city)
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                        <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Standort</div>
                            <div class="text-sm text-gray-300">{{ $city ? $city . ', ' : '' }}{{ $country }}</div>
                        </div>
                    </div>
                @endif
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:border-[color:var(--theme-color-50)] transition-all">
                    <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-[color:var(--theme-color)]">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500">Lieferweg</div>
                        <div class="text-sm font-bold text-white uppercase">{{ $shipping }}</div>
                    </div>
                </div>
            </div>

            <!-- PRODUCTS KACHELN -->
            @if($products->count() > 0)
                <div class="mt-8 border-t border-gray-800/80 pt-6">
                    <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 mb-4 flex items-center gap-2">
                        <i class="bi bi-boxes"></i> Lieferbare Produkte ({{ $products->count() }})
                    </div>
                    <div class="flex overflow-x-auto gap-4 pb-4 custom-scrollbar snap-x flex-nowrap">
                        @foreach($products as $product)
                            @php
                                $p = is_array($product) ? $product : (array)$product;
                                $galleryImage = null;
                                if(isset($p['media_gallery']) && is_array($p['media_gallery'])) {
                                    foreach($p['media_gallery'] as $media) {
                                        if(is_array($media) && isset($media['path'])) {
                                            if(!isset($media['type']) || $media['type'] === 'image') {
                                                $galleryImage = $media['path'];
                                                break;
                                            }
                                        } elseif(is_string($media)) {
                                            $galleryImage = $media;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            <div class="w-32 h-32 sm:w-40 sm:h-40 shrink-0 snap-start rounded-2xl bg-gray-950 border border-gray-800 overflow-hidden group/product relative shadow-inner hover:border-[color:var(--theme-color-50)] transition-all cursor-pointer" title="{{ $p['name'] ?? '' }}">
                                @if($galleryImage)
                                    <img src="{{ asset('storage/' . $galleryImage) }}" class="w-full h-full object-cover group-hover/product:scale-110 transition-transform duration-500">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="bi bi-box text-4xl text-gray-700 group-hover/product:text-gray-500 transition-colors"></i>
                                    </div>
                                @endif
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-gray-950 via-gray-950/80 to-transparent p-3 pt-6">
                                    <div class="text-[10px] sm:text-xs font-bold text-white truncate">{{ $p['name'] ?? 'Unbekannt' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Dump the rest nicely -->
            <div class="mt-6 border-t border-gray-800/80 pt-4">
                <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 mb-3">Zusätzliche Datenfelder</div>
                <div class="bg-black/50 rounded-xl p-4 font-mono text-[10px] text-[color:var(--theme-color-70)] max-h-48 overflow-y-auto custom-scrollbar break-all">
                    @foreach($s as $k => $v)
                        @if(!in_array($k, ['name', 'contact_person', 'email', 'phone', 'city', 'country', 'shipping_method', 'products']))
                            <div class="mb-1"><strong class="text-white opacity-70">{{ $k }}:</strong> {{ is_array($v) ? json_encode($v) : $v }}</div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

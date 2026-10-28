<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <style>
        .supplier-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
        }
        .supplier-scroll:hover {
            scrollbar-color: var(--theme-color) rgba(148, 163, 184, 0.12);
        }
        .supplier-scroll::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        .supplier-scroll::-webkit-scrollbar-button {
            display: none;
            width: 0;
            height: 0;
        }
        .supplier-scroll::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.08);
            border-radius: 9999px;
        }
        .supplier-scroll::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.45);
            border-radius: 9999px;
            transition: background-color 0.2s ease;
        }
        .supplier-scroll:hover::-webkit-scrollbar-thumb {
            background: var(--theme-color);
        }
    </style>
    <!-- Top Action & Filter Bar (Kompakt) -->
    @if(!$showModal)
    @php
        $totalCount = $suppliers->count();
        $deCount = $suppliers->filter(fn($s) => strtoupper((string) $s->country_code) === 'DE')->count();
        $linkedProductsCount = $suppliers->sum(fn($s) => $s->products->count());
    @endphp
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 mb-4 shadow-sm animate-fade-in-up">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white tracking-tight">Übersicht deiner Lieferanten</h2>
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-black bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">{{ $totalCount }} Händler</span>
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">🇩🇪 {{ $deCount }} DE</span>
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">{{ $linkedProductsCount }} Produkte verknüpft</span>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">Kompakte B2B-Großhandels- & Lieferantenmatrix (Deutsche Händler zuerst sortiert).</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Schnellfilter Land -->
                <div class="inline-flex items-center bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold">
                    <button type="button" wire:click="setCountryFilter('all')" class="px-2.5 py-1 rounded-lg transition-all {{ $countryFilter === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        Alle
                    </button>
                    <button type="button" wire:click="setCountryFilter('DE')" class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 {{ $countryFilter === 'DE' ? 'bg-white dark:bg-gray-800 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        <span>🇩🇪</span> DE
                    </button>
                    <button type="button" wire:click="setCountryFilter('INT')" class="px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 {{ $countryFilter === 'INT' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-800 dark:hover:text-gray-300' }}">
                        <span>🌍</span> Int.
                    </button>
                </div>

                <!-- Live-Suche -->
                <div class="relative w-full sm:w-56">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input type="text" wire:model.live.debounce.200ms="search" placeholder="Händler, Ort, Produkt..." class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl pl-8 pr-7 py-1.5 text-xs font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] placeholder-gray-400">
                    @if($search !== '')
                        <button type="button" wire:click="$set('search', '')" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                        </button>
                    @endif
                </div>

                <button wire:click="create" class="bg-[var(--theme-color)] hover:opacity-90 text-gray-900 font-bold px-3.5 py-1.5 rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5 shrink-0">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Lieferant anlegen
                </button>
            </div>
        </div>
    </div>

    <!-- Kompakte Lieferanten-Tabelle -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm dark:shadow-xl overflow-hidden animate-fade-in-up">
        <div class="overflow-x-auto supplier-scroll">
            <table class="w-full lg:table-fixed text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/90 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="py-2.5 pl-4 pr-3 lg:w-[22%]">Lieferant & Firmensitz</th>
                        <th class="py-2.5 px-3 lg:w-[19%]">Kontakt & Steuern</th>
                        <th class="py-2.5 px-3 lg:w-[12%]">Lieferzeit & Konditionen</th>
                        <th class="py-2.5 px-3 lg:w-[19%]">Direktlinks & Portale</th>
                        <th class="py-2.5 px-3 lg:w-[21%]">Gelieferte Produkte</th>
                        <th class="py-2.5 pl-2 pr-4 text-right lg:w-[7%]">Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                    @forelse($suppliers as $supplier)
                        @php
                            $code = strtoupper((string) $supplier->country_code);
                            $flag = match($code) {
                                'DE' => '🇩🇪', 'CN' => '🇨🇳', 'US' => '🇺🇸', 'GB' => '🇬🇧',
                                'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸', 'PL' => '🇵🇱',
                                'NL' => '🇳🇱', 'AT' => '🇦🇹', 'CH' => '🇨🇭', default => '🏳️'
                            };
                            $isDe = ($code === 'DE');
                            $activeDays = match($supplier->shipping_method) {
                                'air' => $supplier->lead_time_air_days,
                                'sea' => $supplier->lead_time_sea_days,
                                'train' => $supplier->lead_time_train_days,
                                default => $supplier->lead_time_land_days,
                            };
                            $methodLabel = match($supplier->shipping_method) {
                                'air' => 'Luft',
                                'sea' => 'See',
                                'train' => 'Bahn',
                                default => 'Land',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/30 transition-colors align-top group">
                            <!-- 1. Lieferant & Sitz (ohne lange Beschreibung für kompakte Spaltenbreite) -->
                            <td class="py-2.5 pl-4 pr-3">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-lg border {{ $isDe ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-gray-100 dark:bg-gray-950 border-gray-200 dark:border-gray-800' }} flex items-center justify-center shrink-0 text-sm shadow-inner" title="{{ $supplier->country ?: $code }}">
                                        {{ $flag }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="font-bold text-gray-900 dark:text-white text-xs leading-snug truncate" title="{{ $supplier->name }}{{ $supplier->notes ? ' — ' . $supplier->notes : '' }}">
                                                {{ $supplier->name }}
                                            </span>
                                            @if($isDe)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-black uppercase tracking-wider bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 shrink-0">DE</span>
                                            @elseif($code)
                                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0">{{ $code }}</span>
                                            @endif
                                        </div>
                                        @if($supplier->company_name && $supplier->company_name !== $supplier->name)
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate" title="{{ $supplier->company_name }}">
                                                {{ $supplier->company_name }}
                                            </div>
                                        @endif
                                        @if($supplier->zip || $supplier->city || $supplier->street)
                                            @php
                                                $fullAddress = trim(($supplier->street ? $supplier->street . ' ' . $supplier->house_number . ', ' : '') . $supplier->zip . ' ' . $supplier->city, ', ');
                                            @endphp
                                            <div class="text-[10px] text-gray-400 dark:text-gray-500 truncate mt-0.5" title="{{ $fullAddress }}">
                                                {{ $fullAddress }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- 2. Kontakt & Steuern -->
                            <td class="py-2.5 px-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1 text-[11px] font-semibold text-gray-800 dark:text-gray-200 truncate" title="{{ $supplier->contact_person }}">
                                        <x-heroicon-o-user class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                        <span class="truncate">{{ $supplier->contact_person ?: '—' }}</span>
                                    </div>
                                    @if($supplier->email)
                                        <a href="mailto:{{ $supplier->email }}" class="flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] truncate transition-colors" title="{{ $supplier->email }}">
                                            <x-heroicon-o-envelope class="w-3.5 h-3.5 shrink-0" />
                                            <span class="truncate">{{ $supplier->email }}</span>
                                        </a>
                                    @endif
                                    @if($supplier->phone)
                                        <a href="tel:{{ $supplier->phone }}" class="flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] truncate transition-colors" title="{{ $supplier->phone }}">
                                            <x-heroicon-o-phone class="w-3.5 h-3.5 shrink-0" />
                                            <span class="truncate">{{ $supplier->phone }}</span>
                                        </a>
                                    @endif
                                    @if($supplier->vat_id || $supplier->customer_number)
                                        <div class="flex flex-wrap gap-1 pt-0.5">
                                            @if($supplier->vat_id)
                                                <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-950 text-[9px] font-mono text-gray-500 dark:text-gray-400 border border-gray-200/70 dark:border-gray-800" title="USt-IdNr.">{{ $supplier->vat_id }}</span>
                                            @endif
                                            @if($supplier->customer_number)
                                                <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-950 text-[9px] font-mono text-gray-500 dark:text-gray-400 border border-gray-200/70 dark:border-gray-800" title="Kundennummer">KD: {{ $supplier->customer_number }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- 3. Lieferzeit & Konditionen -->
                            <td class="py-2.5 px-3">
                                <div class="space-y-1">
                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-[11px] font-bold text-gray-800 dark:text-gray-200">
                                        @if($supplier->shipping_method === 'air')
                                            <x-heroicon-s-paper-airplane class="w-3.5 h-3.5 text-sky-400 -rotate-45 shrink-0" />
                                        @elseif($supplier->shipping_method === 'sea')
                                            <x-heroicon-s-globe-europe-africa class="w-3.5 h-3.5 text-blue-400 shrink-0" />
                                        @elseif($supplier->shipping_method === 'train')
                                            <x-heroicon-s-ticket class="w-3.5 h-3.5 text-orange-400 shrink-0" />
                                        @else
                                            <x-heroicon-s-truck class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                                        @endif
                                        <span>{{ $activeDays ? $activeDays . ' Tage' : 'k.A.' }}</span>
                                        <span class="text-[9px] text-gray-400 font-normal">({{ $methodLabel }})</span>
                                    </div>

                                    @if($supplier->payment_terms)
                                        <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate" title="{{ $supplier->payment_terms }}">
                                            💳 {{ Str::limit($supplier->payment_terms, 32) }}
                                        </div>
                                    @endif

                                    @if($supplier->minimum_order_value || $supplier->shipping_costs)
                                        <div class="flex flex-wrap gap-1 text-[10px] text-gray-400">
                                            @if($supplier->minimum_order_value)
                                                <span>MOQ: {{ number_format($supplier->minimum_order_value / 100, 0, ',', '.') }} €</span>
                                            @endif
                                            @if($supplier->shipping_costs)
                                                <span>• Versand: {{ number_format($supplier->shipping_costs / 100, 2, ',', '.') }} €</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- 4. Direktlinks & Portale (Kompakte Scroll-Pills) -->
                            <td class="py-2.5 px-3">
                                <div class="max-h-20 overflow-y-auto supplier-scroll pr-1.5 flex flex-wrap gap-1">
                                    @if($supplier->website)
                                        <a href="{{ $supplier->website }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] text-[11px] font-bold text-gray-700 dark:text-gray-300 hover:text-[var(--theme-color)] transition-colors">
                                            <x-heroicon-o-globe-alt class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" />
                                            <span class="truncate max-w-[125px]">Hauptshop</span>
                                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3 opacity-60 shrink-0" />
                                        </a>
                                    @endif

                                    @if(is_array($supplier->dynamic_links) && count($supplier->dynamic_links) > 0)
                                        @foreach($supplier->dynamic_links as $link)
                                            @if(!empty($link['url']) && !empty($link['title']))
                                                <a href="{{ $link['url'] }}" target="_blank" title="{{ $link['title'] }}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] text-[10px] font-semibold text-gray-600 dark:text-gray-400 hover:text-[var(--theme-color)] transition-colors">
                                                    <x-heroicon-o-link class="w-3 h-3 shrink-0 opacity-70" />
                                                    <span class="truncate max-w-[130px]">{{ $link['title'] }}</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    @endif

                                    @if(!$supplier->website && (empty($supplier->dynamic_links) || count($supplier->dynamic_links) === 0))
                                        <span class="text-[11px] text-gray-400 italic">Keine Links</span>
                                    @endif
                                </div>
                            </td>

                            <!-- 5. Gelieferte Produkte (Kompakter Scrollbereich) -->
                            <td class="py-2.5 px-3">
                                @if($supplier->products->count() > 0)
                                    <div class="max-h-20 overflow-y-auto supplier-scroll pr-1.5 space-y-1">
                                        @foreach($supplier->products as $product)
                                            @php
                                                $galleryImage = null;
                                                if(isset($product->media_gallery) && is_array($product->media_gallery)) {
                                                    foreach($product->media_gallery as $media) {
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
                                                if (!$galleryImage && $product->preview_image_path) {
                                                    $galleryImage = $product->preview_image_path;
                                                }
                                            @endphp
                                            <div class="flex items-center justify-between gap-2 p-1 rounded-lg bg-gray-50 dark:bg-gray-950/90 border border-gray-200/80 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 transition-colors">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    @if($galleryImage)
                                                        <img src="{{ asset('storage/' . $galleryImage) }}" alt="{{ $product->name }}" class="w-7 h-7 rounded-md object-cover shrink-0 border border-gray-200 dark:border-gray-800">
                                                    @else
                                                        <div class="w-7 h-7 rounded-md bg-gray-200 dark:bg-gray-900 flex items-center justify-center shrink-0">
                                                            <x-heroicon-o-cube class="w-4 h-4 text-gray-400" />
                                                        </div>
                                                    @endif
                                                    <div class="min-w-0">
                                                        <a href="{{ url('/produkt/' . $product->slug) }}" target="_blank" class="block text-[11px] font-bold text-gray-900 dark:text-white hover:text-[var(--theme-color)] truncate max-w-[135px]" title="{{ $product->name }}">
                                                            {{ $product->name }}
                                                        </a>
                                                        <div class="text-[9px] text-gray-400 font-mono truncate">
                                                            EK {{ number_format(($product->purchase_price ?? 0) / 100, 2, ',', '.') }} € • {{ $product->quantity }} Stk.
                                                        </div>
                                                    </div>
                                                </div>
                                                @if($product->reorder_url)
                                                    <a href="{{ $product->reorder_url }}" target="_blank" title="Rohling beim Händler nachbestellen" class="p-1 rounded-md text-gray-400 hover:text-[var(--theme-color)] hover:bg-gray-200/50 dark:hover:bg-gray-800 shrink-0 transition-colors">
                                                        <x-heroicon-o-shopping-bag class="w-3.5 h-3.5" />
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100/70 dark:bg-gray-950/60 text-[10px] text-gray-400 border border-dashed border-gray-200 dark:border-gray-800">
                                        Noch kein Produkt verknüpft
                                    </span>
                                @endif
                            </td>

                            <!-- 6. Aktionen -->
                            <td class="py-2.5 pl-2 pr-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <button wire:click="edit('{{ $supplier->id }}')" title="Lieferant bearbeiten" class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-500/20 text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 inline-flex items-center justify-center transition-colors">
                                        <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                    </button>
                                    <button wire:click="delete('{{ $supplier->id }}')" wire:confirm="Lieferant {{ $supplier->name }} wirklich löschen?" title="Lieferant löschen" class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-500/20 text-gray-500 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 inline-flex items-center justify-center transition-colors">
                                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 px-4 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <x-heroicon-o-truck class="w-8 h-8 text-gray-400 dark:text-gray-600 mb-2" />
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">Keine passenden Lieferanten gefunden</p>
                                    <p class="text-[11px] text-gray-500 mt-0.5 mb-3">Passe den Suchfilter an oder lege einen neuen Großhändler an.</p>
                                    <button wire:click="create" class="bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white font-bold px-3.5 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 transition-colors text-xs">
                                        Lieferant anlegen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- INLINE FORMULAR -->
    @if($showModal)
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-3xl shadow-lg dark:shadow-2xl border border-gray-200 dark:border-gray-700 w-full transform transition-all p-6 sm:p-10 animate-fade-in-up">

            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-6 mb-8">
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">
                    {{ $isEditing ? 'Lieferant bearbeiten' : 'Neuen Lieferanten anlegen' }}
                </h3>
                <button wire:click="resetForm" class="text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors bg-gray-100 dark:bg-gray-800 w-10 h-10 flex items-center justify-center rounded-xl shadow-inner border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <form wire:submit.prevent="save" class="space-y-10">
                <!-- Sektion 1: Stammdaten -->
                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 px-2 flex items-center gap-2">
                        <x-heroicon-o-building-storefront class="w-4 h-4" /> Stammdaten
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 px-2">
                        <div class="col-span-1 md:col-span-2 lg:col-span-3">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Firmenname / Händler (Anzeigename) *</label>
                            <input type="text" wire:model="name" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-4 text-base font-bold text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="z.B. Alibaba Merchant XYZ">
                            @error('name') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-1 md:col-span-2 lg:col-span-3">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Offizieller Firmenname (Rechnung)</label>
                            <input type="text" wire:model="company_name" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="z.B. Shenzhen Technology Co., Ltd.">
                        </div>

                        <div class="col-span-1 md:col-span-2 lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Straße & Hausnummer</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="street" class="w-2/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="Straße">
                                <input type="text" wire:model="house_number" class="w-1/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="Nr.">
                            </div>
                        </div>

                        <div class="col-span-1 md:col-span-2 lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">PLZ & Stadt</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="zip" class="w-1/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="PLZ">
                                <input type="text" wire:model="city" class="w-2/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="Stadt">
                            </div>
                        </div>

                        <div class="col-span-1 md:col-span-2 lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Land & Flagge</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="country" class="w-2/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="Land (Ausgeschrieben)">
                                <select wire:model="country_code" class="w-1/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner">
                                    <option value="" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">Keine</option>
                                    <option value="DE" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇩🇪 DE</option>
                                    <option value="CN" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇨🇳 CN</option>
                                    <option value="US" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇺🇸 US</option>
                                    <option value="GB" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇬🇧 GB</option>
                                    <option value="IT" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇮🇹 IT</option>
                                    <option value="FR" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇫🇷 FR</option>
                                    <option value="ES" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇪🇸 ES</option>
                                    <option value="PL" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇵🇱 PL</option>
                                    <option value="NL" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇳🇱 NL</option>
                                    <option value="AT" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇦🇹 AT</option>
                                    <option value="CH" class="bg-white dark:bg-gray-950 text-gray-900 dark:text-white">🇨🇭 CH</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-span-1 md:col-span-2 lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Ansprechpartner</label>
                            <input type="text" wire:model="contact_person" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="z.B. Mr. Chen / Mia">
                        </div>

                        <!-- Shipping Methods und Lieferzeiten -->
                        <div class="col-span-1 md:col-span-2 lg:col-span-3 mt-4" x-data="{ method: @entangle('shipping_method') }">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wide">Standard-Lieferweg & Laufzeiten</label>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- Land -->
                                <div class="bg-gray-50 dark:bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'land' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] dark:shadow-[0_0_20px_rgba(16,185,129,0.1)]' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="land" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'land' ? 'border-[var(--theme-color)]' : 'border-gray-400 dark:border-gray-600 group-hover:border-gray-500'">
                                        <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'land' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-truck class="w-5 h-5 transition-colors shrink-0" x-bind:class="method === 'land' ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'land' ? 'text-[var(--theme-color)]' : 'text-gray-600 dark:text-gray-400'">Landweg</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_land_days" min="0" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-gray-900 dark:text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-inner placeholder-gray-400 dark:placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>

                                <!-- Air -->
                                <div class="bg-gray-50 dark:bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'air' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] dark:shadow-[0_0_20px_rgba(14,165,233,0.1)]' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="air" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'air' ? 'border-[var(--theme-color)]' : 'border-gray-400 dark:border-gray-600 group-hover:border-gray-500'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'air' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-paper-airplane class="w-5 h-5 -rotate-45 transition-colors shrink-0" x-bind:class="method === 'air' ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'air' ? 'text-[var(--theme-color)]' : 'text-gray-600 dark:text-gray-400'">Luftfracht</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_air_days" min="0" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-gray-900 dark:text-white focus:border-sky-500 focus:ring-1 focus:ring-sky-500 shadow-inner placeholder-gray-400 dark:placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>

                                <!-- Train -->
                                <div class="bg-gray-50 dark:bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'train' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] dark:shadow-[0_0_20px_rgba(249,115,22,0.1)]' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="train" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'train' ? 'border-[var(--theme-color)]' : 'border-gray-400 dark:border-gray-600 group-hover:border-gray-500'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'train' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-ticket class="w-5 h-5 transition-colors shrink-0" x-bind:class="method === 'train' ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'train' ? 'text-[var(--theme-color)]' : 'text-gray-600 dark:text-gray-400'">Zug / Bahn</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_train_days" min="0" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-gray-900 dark:text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500 shadow-inner placeholder-gray-400 dark:placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>

                                <!-- Sea -->
                                <div class="bg-gray-50 dark:bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'sea' ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] dark:shadow-[0_0_20px_rgba(59,130,246,0.1)]' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="sea" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'sea' ? 'border-[var(--theme-color)]' : 'border-gray-400 dark:border-gray-600 group-hover:border-gray-500'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'sea' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-globe-europe-africa class="w-5 h-5 transition-colors shrink-0" x-bind:class="method === 'sea' ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'sea' ? 'text-[var(--theme-color)]' : 'text-gray-600 dark:text-gray-400'">Seefracht</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_sea_days" min="0" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-inner placeholder-gray-400 dark:placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sektion 2: Kontakt & System -->
                <div class="border-t border-gray-200 dark:border-gray-800/50 pt-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 px-2 flex items-center gap-2">
                        <x-heroicon-o-phone class="w-4 h-4" /> Direkter Kontakt
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">E-Mail Adresse</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                    <x-heroicon-o-envelope class="w-5 h-5" />
                                </span>
                                <input type="email" wire:model="email" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl pl-12 pr-4 py-4 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="z.B. support@supplier.com">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Telefon / WhatsApp / WeChat-Nr</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                    <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                                </span>
                                <input type="text" wire:model="phone" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl pl-12 pr-4 py-4 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="+86 ...">
                            </div>
                        </div>
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Haupt-Webseite / AliBaba Shop</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                    <x-heroicon-o-globe-alt class="w-5 h-5" />
                                </span>
                                <input type="url" wire:model="website" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl pl-12 pr-4 py-4 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="https://...">
                            </div>
                            @error('website') <span class="text-xs text-red-500 mt-2 block font-bold">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Sektion: Geschäftsdaten & Bank -->
                <div class="border-t border-gray-200 dark:border-gray-800/50 pt-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 px-2 flex items-center gap-2">
                        <x-heroicon-o-banknotes class="w-4 h-4" /> Geschäftsdaten & Bankverbindung
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Unsere Kundennummer (beim Lieferant)</label>
                            <input type="text" wire:model="customer_number" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="z.B. KD-12345">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Steuernummer / VAT ID</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="tax_id" class="w-1/2 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="Steuernummer">
                                <input type="text" wire:model="vat_id" class="w-1/2 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="USt-IdNr (VAT)">
                            </div>
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Bankname & Konto</label>
                            <input type="text" wire:model="bank_name" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600 mb-3" placeholder="Name der Bank (z.B. Deutsche Bank)">
                            <div class="flex gap-2">
                                <input type="text" wire:model="iban" class="w-2/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="IBAN">
                                <input type="text" wire:model="bic" class="w-1/3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="BIC / SWIFT">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sektion: Konditionen -->
                <div class="border-t border-gray-200 dark:border-gray-800/50 pt-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 px-2 flex items-center gap-2">
                        <x-heroicon-o-scale class="w-4 h-4" /> Konditionen & Zahlungsziele
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 px-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Zahlungsziel</label>
                            <input type="text" wire:model="payment_terms" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="z.B. 30 Tage Netto">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Mindestbestellwert (MOQ in €)</label>
                            <input type="number" wire:model="minimum_order_value" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="€">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-2 uppercase tracking-wide">Standard Versandkosten (€)</label>
                            <input type="number" wire:model="shipping_costs" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-3 text-sm font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="€">
                        </div>
                    </div>
                </div>

                <!-- Sektion: Dynamische Links -->
                <div class="border-t border-gray-200 dark:border-gray-800/50 pt-8">
                    <div class="bg-gray-50 dark:bg-gray-950/40 p-6 sm:p-8 rounded-3xl border border-gray-200 dark:border-gray-800">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] flex items-center gap-2">
                                <x-heroicon-o-link class="w-4 h-4" /> Dynamische Links (Chats, Portale, Docs)
                            </h4>
                            <button type="button" wire:click="addLink" class="text-xs font-bold bg-white dark:bg-gray-800 hover:bg-[var(--theme-color-20)] text-gray-800 dark:text-white hover:text-[var(--theme-color)] border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color-50)] rounded-lg px-4 py-2 transition-colors flex items-center gap-2">
                                <x-heroicon-o-plus class="w-4 h-4" /> Link hinzufügen
                            </button>
                        </div>

                        <div class="space-y-4">
                            @foreach($dynamic_links as $index => $link)
                                <div class="flex flex-col md:flex-row gap-4 items-start md:items-center p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-inner">
                                    <div class="w-full md:w-1/3">
                                        <label class="block text-[10px] font-black tracking-widest text-gray-500 uppercase mb-2">Titel der Verlinkung</label>
                                        <input type="text" wire:model="dynamic_links.{{ $index }}.title" placeholder="z.B. WeChat Chat" class="w-full bg-gray-50 dark:bg-black border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)]">
                                        @error('dynamic_links.'.$index.'.title') <span class="text-xs text-red-500 block mt-1 font-bold">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="flex-1 w-full flex items-end gap-3">
                                        <div class="flex-1">
                                            <label class="block text-[10px] font-black tracking-widest text-gray-500 uppercase mb-2">URL / Hyperlink</label>
                                            <div class="flex items-center gap-2">
                                                <input type="url" wire:model.live.debounce.500ms="dynamic_links.{{ $index }}.url" placeholder="https://..." class="w-full bg-gray-50 dark:bg-black border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)]">
                                                @if(!empty($link['url']))
                                                    <a href="{{ $link['url'] }}" target="_blank" title="Link im neuen Tab öffnen" class="w-12 h-12 shrink-0 bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color-20)] border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color-50)] text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] rounded-xl flex items-center justify-center transition-all shadow-inner">
                                                        <x-heroicon-o-arrow-top-right-on-square class="w-5 h-5" />
                                                    </a>
                                                @endif
                                            </div>
                                            @error('dynamic_links.'.$index.'.url') <span class="text-xs text-red-500 block mt-1 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <button type="button" wire:click="removeLink({{ $index }})" title="Entfernen" class="w-12 h-12 shrink-0 bg-red-500/10 hover:bg-red-500 border border-red-500/20 hover:border-red-500 text-red-500 dark:text-red-400 hover:text-white rounded-xl flex items-center justify-center transition-all shadow-inner">
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                            @if(count($dynamic_links) === 0)
                                <div class="flex items-center gap-3 p-4 bg-white dark:bg-gray-900 border border-dashed border-gray-300 dark:border-gray-700 rounded-2xl">
                                    <x-heroicon-o-information-circle class="w-6 h-6 text-gray-400 dark:text-gray-500" />
                                    <p class="text-sm text-gray-600 dark:text-gray-400 tracking-wide">Hier kannst du z.B. direkte WhatsApp Links, Links zu Google Drive Dokumenten oder zu B2B Plattform-Chats ablegen, für den reibungslosen Workflow.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Notizen -->
                <div class="border-t border-gray-200 dark:border-gray-800/50 pt-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-4 px-2 flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-4 h-4" /> Zusätzliche Infos
                    </h4>
                    <div class="px-2">
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-3 uppercase tracking-wide">Interne Notizen & Adresse</label>
                        <textarea wire:model="notes" rows="4" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-xl px-5 py-4 text-base leading-relaxed font-medium text-gray-900 dark:text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-400 dark:placeholder-gray-600" placeholder="Spezielle Rabatte, Mindestabnahmemengen (MOQ), Liefersituationen..."></textarea>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-4 pt-8 border-t border-gray-200 dark:border-gray-800">
                    <button type="button" wire:click="resetForm" class="w-full sm:w-auto text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white font-bold px-8 py-4 transition-colors">
                        Abbrechen
                    </button>
                    <button type="submit"
                        x-data="{ hasError: false }"
                        @validation-failed.window="hasError = true; setTimeout(() => hasError = false, 3000)"
                        :class="hasError ? 'bg-red-500 hover:bg-red-600 dark:shadow-[0_0_20px_rgba(239,68,68,0.2)] text-white' : 'bg-[var(--theme-color)] hover:bg-[var(--theme-color)]-hover dark:shadow-[0_0_20px_var(--theme-color-20)] dark:hover:shadow-[0_0_25px_var(--theme-color-40)] text-gray-900'"
                        class="w-full sm:w-auto font-black text-sm uppercase tracking-widest px-10 py-4 rounded-xl transition-all flex items-center justify-center gap-3">
                        <span x-show="!hasError" class="flex items-center gap-2"><x-heroicon-o-check class="w-5 h-5" /> Lieferanten speichern</span>
                        <span x-show="hasError" x-cloak class="flex items-center gap-2"><x-heroicon-o-exclamation-triangle class="w-5 h-5" /> Fehlende Angaben</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>

@php
    $resolvedTiles = \App\Models\Product\ProductCategory::resolveShowcaseTiles(10);
    $worldCounts = \App\Models\Product\ProductCategory::resolveCollectionWorldCounts();
@endphp

<section id="services" x-data="{ activeWorld: 'manufaktur' }" class="bg-white text-black py-20" aria-labelledby="services-heading">
    <style>
        .sf-card {
            transform: translate3d(0, 0, 0);
            transition:
                transform 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                box-shadow 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                border-color 0.75s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform;
        }
        .sf-card.sf-is-active {
            transform: translate3d(0, -6px, 0);
            box-shadow: 0 22px 30px -8px rgba(0, 0, 0, 0.14), 0 8px 12px -6px rgba(0, 0, 0, 0.06);
        }
        .sf-card-img {
            transform: scale(1) translateZ(0);
            transition: transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform;
        }
        .sf-card.sf-is-active .sf-card-img {
            transform: scale(1.045) translateZ(0);
        }
        .sf-card-sheen {
            position: absolute;
            inset: -100%;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0) 40%,
                rgba(255, 251, 235, 0.05) 44%,
                rgba(255, 248, 225, 0.18) 47.5%,
                rgba(255, 246, 215, 0.38) 50%,
                rgba(255, 248, 225, 0.18) 52.5%,
                rgba(255, 251, 235, 0.05) 56%,
                rgba(255, 255, 255, 0) 60%
            );
            transform: translate3d(-32%, -32%, 0);
            transition: transform 1.45s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform;
        }
        .sf-card.sf-is-active .sf-card-sheen {
            transform: translate3d(32%, 32%, 0);
        }
        @keyframes sfSparkleFloatA {
            0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
            33% { transform: translate3d(4px, -6px, 0) rotate(18deg); }
            66% { transform: translate3d(-3px, -2px, 0) rotate(-12deg); }
        }
        @keyframes sfSparkleFloatB {
            0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
            33% { transform: translate3d(-5px, -4px, 0) rotate(-20deg); }
            66% { transform: translate3d(3px, -7px, 0) rotate(14deg); }
        }
        .sf-card-sparkle {
            opacity: 0;
            transform: scale(0.35) translateZ(0);
            transition:
                opacity 0.75s cubic-bezier(0.22, 0.61, 0.36, 1) 0s,
                transform 0.85s cubic-bezier(0.22, 0.61, 0.36, 1) 0s;
            will-change: transform, opacity;
        }
        .sf-card.sf-is-active .sf-card-sparkle {
            opacity: 0.92;
            transform: scale(1) translateZ(0);
        }
        .sf-card.sf-is-active .sf-card-sparkle-1 { transition-delay: 0.04s; }
        .sf-card.sf-is-active .sf-card-sparkle-2 { transition-delay: 0.11s; }
        .sf-card.sf-is-active .sf-card-sparkle-3 { transition-delay: 0.18s; }
        .sf-card.sf-is-active .sf-card-sparkle-4 { transition-delay: 0.25s; }
        .sf-card.sf-is-active .sf-card-sparkle-5 { transition-delay: 0.32s; }
        .sf-card.sf-is-active .sf-card-sparkle-6 { transition-delay: 0.39s; }
        .sf-card-sparkle > svg { display: block; width: 100%; height: 100%; will-change: transform; }
        .sf-card-sparkle-1 > svg { animation: sfSparkleFloatA 3.4s ease-in-out infinite; }
        .sf-card-sparkle-2 > svg { animation: sfSparkleFloatB 4.0s ease-in-out infinite 0.4s; }
        .sf-card-sparkle-3 > svg { animation: sfSparkleFloatB 3.2s ease-in-out infinite 0.9s; }
        .sf-card-sparkle-4 > svg { animation: sfSparkleFloatA 3.8s ease-in-out infinite 1.3s; }
        .sf-card-sparkle-5 > svg { animation: sfSparkleFloatA 4.2s ease-in-out infinite 0.7s; }
        .sf-card-sparkle-6 > svg { animation: sfSparkleFloatB 3.6s ease-in-out infinite 1.6s; }
    </style>
    <script>
        window.sfRandomizeCardSparkles = window.sfRandomizeCardSparkles || function(cardEl) {
            const zones = [
                { top: [10, 28], left: [10, 35] },
                { top: [10, 28], left: [62, 86] },
                { top: [36, 60], left: [8, 28] },
                { top: [36, 60], left: [68, 88] },
                { top: [66, 86], left: [12, 40] },
                { top: [66, 86], left: [58, 86] },
            ];
            const rand = (min, max) => (min + Math.random() * (max - min)).toFixed(1) + '%';
            cardEl.querySelectorAll('.sf-card-sparkle').forEach((el, i) => {
                const z = zones[i % zones.length];
                el.style.top = rand(z.top[0], z.top[1]);
                el.style.left = rand(z.left[0], z.left[1]);
                el.style.right = 'auto';
                el.style.bottom = 'auto';
            });
        };
        window.sfCardHoverEnter = window.sfCardHoverEnter || function(cardEl) {
            clearTimeout(cardEl._sfLeaveTimer);
            clearTimeout(cardEl._sfCleanupTimer);
            if (!cardEl._sfBusy) {
                window.sfRandomizeCardSparkles(cardEl);
                void cardEl.offsetWidth;
            }
            cardEl._sfBusy = true;
            if (!cardEl.classList.contains('sf-is-active')) {
                cardEl._sfEnterTime = performance.now();
                cardEl.classList.add('sf-is-active');
            }
        };
        window.sfCardHoverLeave = window.sfCardHoverLeave || function(cardEl) {
            clearTimeout(cardEl._sfLeaveTimer);
            clearTimeout(cardEl._sfCleanupTimer);
            const minForward = 950;
            const elapsed = cardEl._sfEnterTime ? (performance.now() - cardEl._sfEnterTime) : minForward;
            const waitMs = Math.max(0, minForward - elapsed);
            cardEl._sfLeaveTimer = setTimeout(() => {
                cardEl.classList.remove('sf-is-active');
                cardEl._sfCleanupTimer = setTimeout(() => {
                    cardEl._sfBusy = false;
                }, 1350);
            }, waitMs);
        };
    </script>

    <header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-10">
        <h2 id="services-heading" class="text-primary-dark font-serif font-bold text-3xl sm:text-4xl lg:text-5xl">
            Veredelungen & Materialien für Ihre Unikate
        </h2>
        <p class="mt-4 text-gray-600 text-base max-w-3xl mx-auto">
            Entdecken Sie unsere drei klar getrennten Kollektions-Welten aus unserer Manufaktur in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}.
        </p>

        {{-- 3-Welten-Umschalter (Manufaktur-Kollektion · Atelier Echtgold & Silber · Geschenkgutscheine) --}}
        <div class="mt-7 max-w-4xl mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-2 bg-white rounded-2xl border border-gray-200 shadow-sm">
                <button type="button"
                        @click="activeWorld = 'manufaktur'"
                        :class="activeWorld === 'manufaktur'
                            ? 'bg-[#F5F1E8] text-gray-900 shadow-sm border-[#D8C9A8]'
                            : 'bg-transparent text-gray-600 hover:bg-gray-50 border-transparent'"
                        class="flex items-center justify-center gap-2.5 px-4 py-3 rounded-xl border text-xs sm:text-sm font-bold uppercase tracking-wider transition-all cursor-pointer">
                    <svg class="w-4 h-4 shrink-0 text-[#9A7B38]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span class="truncate">Manufaktur-Kollektion</span>
                    <span :class="activeWorld === 'manufaktur' ? 'bg-white/80 text-gray-900 border border-[#D8C9A8]' : 'bg-gray-100 text-gray-600'"
                          class="px-2 py-0.5 rounded-full text-[11px] font-black">{{ $worldCounts['manufaktur'] ?? 6 }}</span>
                </button>

                <button type="button"
                        @click="activeWorld = 'atelier'"
                        :class="activeWorld === 'atelier'
                            ? 'bg-[#F5F1E8] text-gray-900 shadow-sm border-[#D8C9A8]'
                            : 'bg-transparent text-gray-600 hover:bg-gray-50 border-transparent'"
                        class="flex items-center justify-center gap-2.5 px-4 py-3 rounded-xl border text-xs sm:text-sm font-bold uppercase tracking-wider transition-all cursor-pointer">
                    <svg class="w-4 h-4 shrink-0 text-[#9A7B38]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    <span class="truncate">Atelier Echtgold & Silber</span>
                    <span :class="activeWorld === 'atelier' ? 'bg-white/80 text-gray-900 border border-[#D8C9A8]' : 'bg-gray-100 text-gray-600'"
                          class="px-2 py-0.5 rounded-full text-[11px] font-black">{{ $worldCounts['atelier'] ?? 1 }}</span>
                </button>

                <button type="button"
                        @click="activeWorld = 'voucher'"
                        :class="activeWorld === 'voucher'
                            ? 'bg-[#F5F1E8] text-gray-900 shadow-sm border-[#D8C9A8]'
                            : 'bg-transparent text-gray-600 hover:bg-gray-50 border-transparent'"
                        class="flex items-center justify-center gap-2.5 px-4 py-3 rounded-xl border text-xs sm:text-sm font-bold uppercase tracking-wider transition-all cursor-pointer">
                    <svg class="w-4 h-4 shrink-0 text-[#9A7B38]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                    <span class="truncate">Geschenkgutscheine</span>
                    <span :class="activeWorld === 'voucher' ? 'bg-white/80 text-gray-900 border border-[#D8C9A8]' : 'bg-gray-100 text-gray-600'"
                          class="px-2 py-0.5 rounded-full text-[11px] font-black">{{ $worldCounts['voucher'] ?? 1 }}</span>
                </button>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
        @foreach($resolvedTiles as $tile)
            @php
                $tileWorld = $tile['collection_line'] ?? 'manufaktur';
            @endphp
            @if($tile['is_available'])
                {{-- =====================================================================
                     AKTIVE & VERFÜGBARE KACHEL (Dynamisch aus realem Bestand)
                ===================================================================== --}}
                <article x-show="activeWorld === '{{ $tileWorld }}'"
                         x-cloak
                         onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                         onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                         onclick="if(!event.target.closest('a, button')) window.location.href='{{ $tile['shop_url'] }}'"
                         class="sf-card bg-white rounded-2xl shadow-lg border-2 border-primary overflow-hidden group relative z-10 flex flex-col cursor-pointer">
                    <figure class="overflow-hidden relative bg-gray-900" style="aspect-ratio: 10 / 9;">
                        @if($tile['sale_badge'])
                            <div class="absolute top-3 left-3 {{ !empty($tile['is_new_arrival']) && str_contains((string) $tile['sale_badge'], 'Neu') ? 'bg-[#F5F1E8]/95 text-gray-900 border border-[#D8C9A8]' : 'bg-red-600/95 text-white' }} backdrop-blur-sm text-[9px] font-black px-2.5 py-1 rounded-full z-20 shadow-sm uppercase tracking-wider">
                                {{ $tile['sale_badge'] }}
                            </div>
                        @endif

                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             width="500"
                             height="450"
                             loading="lazy"
                             decoding="async"
                             @if(!empty($tile['is_ai_generated'])) data-is-ai-generated="true" @endif
                             class="sf-card-img w-full h-full object-cover object-center block">

                        <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                        @for($sp = 1; $sp <= 6; $sp++)
                            <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-3.5 h-3.5 text-amber-300' : 'w-3 h-3 text-amber-200' }} drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                            </span>
                        @endfor

                        @if(!empty($tile['is_ai_generated']))
                            <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                  class="pointer-events-none select-none absolute bottom-2 left-2.5 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                KI-generiert
                            </span>
                        @endif

                        @if(!empty($tile['hero_product_name']) && ($tile['matched_count'] ?? 0) > 1)
                            <span style="font-size: 9px; line-height: 1.1; padding: 3px 8px; background: rgba(0,0,0,0.52); color: rgba(255,255,255,0.92);"
                                  class="pointer-events-none select-none absolute top-3 right-3 z-20 backdrop-blur-sm font-semibold tracking-wide rounded-full border border-white/15 shadow-sm max-w-[60%] truncate">
                                {{ $tile['hero_product_name'] }}
                            </span>
                        @endif
                    </figure>

                    <div class="bg-gradient-to-br from-primary to-primary-dark px-5 py-4 relative">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-md absolute -top-6 right-5">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-primary">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1 pr-12">{{ $tile['title'] }}</h3>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-white/80 text-[11px] font-bold tracking-wider uppercase">{{ $tile['subtitle'] }}</span>
                            <span class="text-white/95 text-[11px] font-extrabold bg-black/20 px-2 py-0.5 rounded-full whitespace-nowrap">
                                {{ $tile['stock_badge_text'] }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-gray-600 mb-3.5 text-xs leading-relaxed">{!! $tile['description'] !!}</p>
                        <ul class="text-xs text-gray-500 space-y-1.5 mb-5" aria-label="Produktmerkmale {{ $tile['title'] }}">
                            @foreach($tile['features'] as $feature)
                                <li class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-auto">
                            <a href="{{ $tile['shop_url'] }}"
                               title="Zur Kategorie {{ $tile['title'] }} im Shop"
                               class="block text-center w-full bg-primary text-gray-900 py-2 rounded-lg text-xs font-bold hover:bg-primary-dark hover:text-white group-hover:bg-primary-dark group-hover:text-white transition-colors shadow-sm">
                                Jetzt im Shop entdecken →
                            </a>
                        </div>
                    </div>
                </article>
            @elseif($tile['has_active_products'])
                {{-- =====================================================================
                     PRODUKT EXISTIERT, ABER AKTUELL AUSVERKAUFT (Bestand = 0)
                ===================================================================== --}}
                <article x-show="activeWorld === '{{ $tileWorld }}'"
                         x-cloak
                         class="bg-gray-50 rounded-2xl shadow-sm border border-amber-200 overflow-hidden opacity-80 relative group flex flex-col">
                    <div class="absolute top-3 right-3 z-20 bg-amber-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-full shadow-md uppercase tracking-wider">
                        Aktuell vergriffen
                    </div>
                    <figure class="overflow-hidden relative bg-gray-900" style="aspect-ratio: 10 / 9;">
                        <img src="{{ $tile['image'] }}"
                             alt="{{ $tile['alt'] }}"
                             width="500"
                             height="450"
                             loading="lazy"
                             decoding="async"
                             @if(!empty($tile['is_ai_generated'])) data-is-ai-generated="true" @endif
                             class="w-full h-full object-cover object-center block grayscale-[0.5]">
                        @if(!empty($tile['is_ai_generated']))
                            <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                  class="pointer-events-none select-none absolute bottom-2 left-2.5 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                KI-generiert
                            </span>
                        @endif
                    </figure>
                    <div class="bg-gray-700 px-5 py-4 relative">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center shadow-sm absolute -top-6 right-5">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600">
                                {!! $tile['icon_svg'] !!}
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1 pr-12">{{ $tile['title'] }}</h3>
                        <span class="text-gray-300 text-[11px] font-bold tracking-wider uppercase">NACHPRODUKTION LÄUFT</span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col">
                        <p class="text-gray-500 mb-3.5 text-xs">{!! $tile['description'] !!}</p>
                        <ul class="text-xs text-gray-400 space-y-1.5">
                            @foreach(array_slice($tile['features'], 0, 2) as $feature)
                                <li class="flex items-center gap-2">• {{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                </article>
            @endif
        @endforeach
    </div>

    <p class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 text-center text-xs text-gray-500">
        * Alle angegebenen Preise verstehen sich {{ ((bool) shop_setting('is_small_business', false)) ? 'als Endpreise gem. § 19 UStG' : 'inkl. gesetzl. MwSt.' }}, <a href="{{ route('versand') }}" class="underline hover:text-gray-700">zzgl. Versandkosten</a>.
    </p>
</section>

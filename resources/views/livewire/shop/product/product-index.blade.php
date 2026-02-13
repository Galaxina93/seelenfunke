<div>
    <div class="bg-white min-h-screen">

        {{-- Header / Hero Section --}}
        <div class="bg-gray-50 border-b border-gray-100 py-24 text-center">
            <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">
                Unsere Kollektion
            </h1>
            <p class="text-gray-500 max-w-2xl mx-auto text-lg">
                Entdecke handgefertigte Unikate, die deine Seele berühren.
                Jedes Stück wird mit Liebe und Sorgfalt für dich personalisiert.
            </p>
        </div>

        {{-- Produkt Grid --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

            @if($products->isEmpty())
                <div class="text-center py-20">
                    <p class="text-gray-400 text-lg">Aktuell sind keine Produkte verfügbar.</p>
                    <p class="text-sm text-gray-400 mt-2">Schau bald wieder vorbei!</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                    @foreach($products as $product)
                        @php
                            /** * Logik-Check:
                             * 1. Ist das Produkt komplett weg? (Menge 0 und kein Weiterverkauf)
                             * 2. Ist es eine Nachbestellung? (Menge 0 aber Weiterverkauf erlaubt)
                             */
                            $isSoldOut = ($product->track_quantity && $product->quantity <= 0 && !$product->continue_selling_when_out_of_stock);
                            $isBackorder = ($product->track_quantity && $product->quantity <= 0 && $product->continue_selling_when_out_of_stock);

                            // Knappheits-Indikator (Scarcity)
                            $lowStock = ($product->track_quantity && $product->quantity > 0 && $product->quantity <= 5);
                        @endphp

                        <div class="group relative flex flex-col h-full {{ $isSoldOut ? 'opacity-90' : '' }}">

                            {{-- Bild Container --}}
                            <div class="aspect-square bg-gray-100 rounded-2xl overflow-hidden mb-4 relative border border-gray-100 shadow-sm transition-all duration-300 group-hover:shadow-md">

                                {{--
                                    NEU: PRODUKT TYP SCHLEIFEN (OBEN RECHTS)
                                    Wir zeigen diese nur an, wenn das Produkt NICHT ausverkauft/nachbestellbar ist,
                                    um Überlappungen mit dem roten/grauen Banner zu vermeiden.
                                --}}
                                @if(!$isSoldOut && !$isBackorder)
                                    {{-- BLAUE SCHLEIFE: DIGITAL --}}
                                    @if($product->type === 'digital')
                                        <div class="absolute top-6 -right-12 z-30 pointer-events-none">
                                            <div class="bg-blue-600 text-white text-[10px] font-bold py-1 w-48 transform rotate-45 shadow-md border-b border-blue-800 uppercase tracking-widest text-center">
                                                Digital
                                            </div>
                                        </div>
                                    @endif

                                    {{-- ORANGE SCHLEIFE: DIENSTLEISTUNG --}}
                                    @if($product->type === 'service')
                                        <div class="absolute top-6 -right-12 z-30 pointer-events-none">
                                            <div class="bg-orange-500 text-white text-[10px] font-bold py-1 w-48 transform rotate-45 shadow-md border-b border-orange-700 uppercase tracking-widest text-center">
                                                Service
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                <a href="{{ route('product.show', $product->slug) }}" class="block w-full h-full">

                                    {{-- Visueller Status: Graustufen NUR bei echtem Ausverkauf --}}
                                    <div class="w-full h-full {{ $isSoldOut ? 'grayscale opacity-50' : '' }}">
                                        @if(is_array($product->media_gallery) && count($product->media_gallery) > 0)
                                            @if(isset($product->media_gallery[0]['type']) && $product->media_gallery[0]['type'] === 'video')
                                                <video src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}" class="w-full h-full object-cover" muted autoplay loop></video>
                                            @else
                                                <img src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}"
                                                     alt="{{ $product->name }}"
                                                     class="w-full h-full object-cover transition-transform duration-700 {{ !$isSoldOut ? 'group-hover:scale-105' : '' }}">
                                            @endif
                                        @elseif($product->preview_image_path)
                                            <img src="{{ asset('storage/'.$product->preview_image_path) }}"
                                                 class="w-full h-full object-cover">
                                        @else
                                            <div class="flex items-center justify-center h-full text-gray-300 text-sm italic">Kein Bild</div>
                                        @endif
                                    </div>

                                    @if(!$isSoldOut)
                                        <div class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    @endif
                                </a>

                                {{-- DYNAMISCHE SCHLEIFE (Rot für Ausverkauft / Anthrazit für Nachbestellung) --}}
                                @if($isSoldOut || $isBackorder)
                                    <div class="absolute inset-0 pointer-events-none overflow-hidden z-30 rounded-2xl">
                                        <div class="absolute top-0 right-0 w-32 h-32">
                                            @if($isSoldOut)
                                                {{-- STATUS: AUSVERKAUFT (ROT) --}}
                                                <div class="absolute top-[25%] -right-[35%] w-[160%] bg-red-600 text-white text-[10px] md:text-[11px] font-black py-2 shadow-xl transform rotate-45 uppercase tracking-[0.2em] border-y border-white/20 text-center">
                                                    Ausverkauft
                                                </div>
                                            @else
                                                {{-- STATUS: AUF BESTELLUNG (ANTHRAZIT) --}}
                                                <div class="absolute top-[25%] -right-[35%] w-[160%] bg-gray-800 text-white text-[9px] md:text-[10px] font-black py-2 shadow-xl transform rotate-45 uppercase tracking-[0.15em] border-y border-white/10 text-center">
                                                    Auf Bestellung
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                {{-- Badges (Neu / Sale) --}}
                                <div class="absolute top-3 left-3 flex flex-col gap-2 pointer-events-none z-20">
                                    @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                        <span class="bg-red-500 text-white text-[10px] font-bold px-2.5 py-1 rounded shadow-sm uppercase tracking-wide">Sale</span>
                                    @endif
                                    @if(!$isSoldOut && $product->created_at->diffInDays() < 14)
                                        <span class="bg-white/90 backdrop-blur text-gray-900 text-[10px] font-bold px-2.5 py-1 rounded shadow-sm uppercase tracking-wide border border-gray-200">Neu</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Infos --}}
                            <div class="flex-1 flex flex-col text-center px-2">
                                <h3 class="text-lg font-serif font-bold {{ $isSoldOut ? 'text-gray-400' : 'text-gray-900' }} mb-1">
                                    <a href="{{ route('product.show', $product->slug) }}" class="hover:text-primary transition-colors">
                                        {{ $product->name }}
                                    </a>
                                </h3>

                                {{-- Knappheits-Indikator (Scarcity) --}}
                                @if($lowStock && !$isSoldOut)
                                    <div class="mb-1">
                                        <span class="text-[10px] font-bold text-orange-600 uppercase tracking-widest animate-pulse">
                                            Nur noch {{ $product->quantity }} Stück verfügbar!
                                        </span>
                                    </div>
                                @endif

                                {{-- Preis --}}
                                <div class="mb-4 flex items-center justify-center gap-2">
                                    <span class="{{ $isSoldOut ? 'text-gray-400' : 'text-primary' }} font-bold text-lg">
                                        {{ number_format($product->price / 100, 2, ',', '.') }} €
                                    </span>
                                    @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                        <span class="text-sm text-gray-400 line-through">
                                            {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                        </span>
                                    @endif
                                </div>

                                {{-- Button --}}
                                <div class="mt-auto {{ $isSoldOut ? 'opacity-100' : 'opacity-0 transform translate-y-2 group-hover:opacity-100 group-hover:translate-y-0' }} transition-all duration-300">
                                    @if($isSoldOut)
                                        <span class="inline-block w-full px-6 py-3 bg-gray-50 text-gray-400 text-xs font-bold rounded-xl border border-gray-100 cursor-not-allowed uppercase tracking-widest">
                                            Aktuell nicht lieferbar
                                        </span>
                                    @else
                                        <a href="{{ route('product.show', $product->slug) }}"
                                           class="inline-block w-full px-6 py-3 border-2 border-gray-900 text-gray-900 text-xs font-bold rounded-xl hover:bg-gray-900 hover:text-white transition-all duration-300 uppercase tracking-widest">
                                            @if($isBackorder)
                                                Vorbestellen
                                            @else
                                                Produkt ansehen
                                            @endif
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination Links --}}
                <div class="mt-16">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

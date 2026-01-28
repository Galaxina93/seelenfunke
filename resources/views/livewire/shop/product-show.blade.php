<div class="min-h-screen bg-white">

    {{-- SEO Meta Tags (Push in den Head Bereich deines Layouts) --}}
    @push('head')
        <meta name="description" content="{{ $this->product->seo_description ?? $this->product->short_description }}">
    @endpush

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">

        {{-- Breadcrumb --}}
        <nav class="flex text-sm font-medium text-gray-500 mb-8" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">Startseite</a>
            <span class="mx-2 text-gray-300">/</span>
            <a href="/shop" class="hover:text-primary transition-colors">Shop</a>
            <span class="mx-2 text-gray-300">/</span>
            <span class="text-gray-900 truncate max-w-[200px]">{{ $this->product->name }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 xl:gap-16 items-start">

            {{-- LINKE SEITE: Medien Galerie (AlpineJS) --}}
            {{-- UPDATE: 'sticky top-24 self-start' hinzugefügt --}}
            <div class="space-y-6 lg:sticky lg:top-24 lg:self-start"
                 x-data="{
            activeMedia: '{{ !empty($product->media_gallery[0]) ? asset('storage/'.$product->media_gallery[0]['path']) : '' }}',
            activeType: '{{ !empty($product->media_gallery[0]) ? ($product->media_gallery[0]['type'] ?? 'image') : 'image' }}'
         }">

                {{-- Hauptbild / Video Container --}}
                <div class="aspect-square bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 shadow-sm relative group">

                    @if(!empty($product->media_gallery))
                        {{-- Bild Anzeige --}}
                        <img x-show="activeType === 'image'"
                             :src="activeMedia"
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                             alt="{{ $this->product->name }}">

                        {{-- Video Anzeige --}}
                        <video x-show="activeType === 'video'"
                               :src="activeMedia"
                               controls
                               class="w-full h-full object-cover">
                        </video>
                    @else
                        <div class="flex items-center justify-center h-full text-gray-300">
                            <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    @endif

                    {{-- Badges --}}
                    <div class="absolute top-4 left-4 flex flex-col gap-2">
                        @if($this->product->compare_at_price > $this->product->price)
                            <span class="bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">
                        Sale
                    </span>
                        @endif
                        {{-- Beispiel für 'Neu' Logik (z.B. jünger als 30 Tage) --}}
                        @if($this->product->created_at->diffInDays() < 30)
                            <span class="bg-primary text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">
                        Neu
                    </span>
                        @endif
                    </div>
                </div>

                {{-- Thumbnails --}}
                @if(count($product->media_gallery ?? []) > 1)
                    <div class="grid grid-cols-5 gap-4">
                        @foreach($product->media_gallery as $media)
                            <button @click="activeMedia = '{{ asset('storage/'.$media['path']) }}'; activeType = '{{ $media['type'] ?? 'image' }}'"
                                    class="aspect-square rounded-lg overflow-hidden border-2 transition-all"
                                    :class="activeMedia === '{{ asset('storage/'.$media['path']) }}' ? 'border-primary ring-2 ring-primary/20' : 'border-transparent hover:border-gray-300'">
                                @if(isset($media['type']) && $media['type'] === 'video')
                                    <div class="w-full h-full bg-gray-900 flex items-center justify-center text-white relative">
                                        <video src="{{ asset('storage/'.$media['path']) }}" class="absolute inset-0 w-full h-full object-cover opacity-50"></video>
                                        <svg class="w-6 h-6 relative z-10" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                                    </div>
                                @else
                                    <img src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover">
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Desktop Beschreibung (SEO Text) --}}
                <div class="hidden lg:block pt-8 border-t border-gray-100 prose prose-stone text-gray-600">
                    <h3 class="font-serif text-xl text-gray-900 mb-4">Produktdetails</h3>
                    <p class="whitespace-pre-line leading-relaxed">{{ $this->product->description }}</p>

                    {{-- Attribute Tabelle --}}
                    @if(!empty($this->product->attributes))
                        <div class="mt-6 bg-gray-50 rounded-xl p-6">
                            <h4 class="text-sm font-bold uppercase text-gray-900 mb-4 tracking-wider">Eigenschaften</h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4 text-sm">
                                @foreach($this->product->attributes as $key => $val)
                                    @if(!empty($val))
                                        <div class="flex flex-col">
                                            <dt class="text-gray-500">{{ $key }}</dt>
                                            <dd class="font-bold text-gray-900">{{ $val }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
            </div>

            {{-- RECHTE SEITE: Info & Konfigurator --}}
            {{-- Hinweis: Da du jetzt beide Seiten sticky hast, scrollen sie unabhängig voneinander mit, solange der Container hoch genug ist --}}
            <div class="sticky top-24">

                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 mb-2 leading-tight">
                    {{ $this->product->name }}
                </h1>

                <div class="flex items-center gap-4 mb-6">
                    {{-- Preis --}}
                    <div class="flex flex-col">
                        <div class="flex items-baseline gap-3">
                            <span class="text-2xl font-bold text-primary">
                                {{ number_format($this->product->price / 100, 2, ',', '.') }} €
                            </span>
                            @if($this->product->compare_at_price > $this->product->price)
                                <span class="text-lg text-gray-400 line-through decoration-red-400">
                                    {{ number_format($this->product->compare_at_price / 100, 2, ',', '.') }} €
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-col gap-1 mt-1">
                            {{-- 1. Steuerhinweis (Bestehend) --}}
                            <span class="text-xs text-gray-500">
                                @if($this->product->tax_included)
                                    inkl. MwSt.
                                @else
                                    zzgl. MwSt.
                                @endif
                            </span>

                            {{-- 2. Dynamischer Versandhinweis (NEU) --}}
                            @php
                                $freeThreshold = config('shop.shipping.free_threshold', 5000); // z.B. 5000 Cents
                                $shippingCost = config('shop.shipping.cost', 490); // z.B. 490 Cents
                                $isFree = $this->product->price >= $freeThreshold;
                            @endphp

                            @if($isFree)
                                {{-- Fall A: Artikel ist teurer als die Grenze -> Kostenlos --}}
                                <span class="text-xs font-bold text-green-700 flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                                        <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z" />
                                    </svg>
                                    Kostenloser Versand
                                </span>
                            @else
                                {{-- Fall B: Artikel kostet Versand -> Hinweis auf Grenze --}}
                                <span class="text-xs text-gray-500">
                                    zzgl. {{ number_format($shippingCost / 100, 2, ',', '.') }} € Versand
                                    <span class="text-gray-400">(frei ab {{ number_format($freeThreshold / 100, 2, ',', '.') }} €)</span>
                                    <a href="{{ route('terms') }}#versand" target="_blank" class="underline hover:text-primary ml-1">Details</a>
                                </span>
                            @endif
                        </div>

                {{-- Lagerbestand & SKU --}}
                <div class="flex items-center gap-4 mb-8 mt-4 text-sm">
                    @if($this->product->track_quantity)
                        @if($this->product->quantity > 0)
                            <span class="inline-flex items-center gap-1.5 text-green-700 font-medium">
                        <span class="relative flex h-2.5 w-2.5">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                        </span>
                        Auf Lager, sofort lieferbar
                    </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-red-600 font-medium">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                        Derzeit ausverkauft
                    </span>
                        @endif
                    @endif

                    @if($this->product->sku)
                        <span class="text-gray-400 border-l border-gray-200 pl-4 ml-2">
                    Art.-Nr.: {{ $this->product->sku }}
                </span>
                    @endif
                </div>

                {{-- Kurz-Beschreibung (Mobile) --}}
                @if($this->product->short_description)
                    <div class="text-gray-600 leading-relaxed mb-8">
                        {{ $this->product->short_description }}
                    </div>
                @endif

                <hr class="border-gray-100 mb-8">

                {{-- KONFIGURATOR / WARENKORB BUTTON --}}
                {{--
                   Wir binden hier den Configurator ein.
                   Dieser übernimmt die Auswahl der Optionen und das "Add to Cart" Event.
                --}}
                {{--<livewire:shop.product-configurator :product="$this->product" />--}}

                <div class="bg-white rounded-2xl border border-gray-200 shadow-xl overflow-hidden">
                    <livewire:shop.configurator
                        :product="$product"
                        context="add"
                    />
                </div>

                {{-- USP Icons --}}
                <div class="grid grid-cols-2 gap-4 mt-10">
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm font-bold text-gray-700">Handgefertigt</span>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                        <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-sm font-bold text-gray-700">Schneller Versand</span>
                    </div>
                </div>

                {{-- Mobile Beschreibung (unter den Buttons sichtbar) --}}
                <div class="lg:hidden mt-10 pt-8 border-t border-gray-100">
                    <h3 class="font-serif text-lg font-bold text-gray-900 mb-3">Beschreibung</h3>
                    <div class="prose prose-sm text-gray-600">
                        <p>{{ $this->product->description }}</p>
                    </div>

                    @if(!empty($this->product->attributes))
                        <div class="mt-6 bg-gray-50 rounded-xl p-4">
                            <dl class="grid grid-cols-1 gap-y-2 text-sm">
                                @foreach($this->product->attributes as $key => $val)
                                    @if(!empty($val))
                                        <div class="flex justify-between border-b border-gray-200 pb-2 last:border-0 last:pb-0">
                                            <dt class="text-gray-500">{{ $key }}</dt>
                                            <dd class="font-bold text-gray-900">{{ $val }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

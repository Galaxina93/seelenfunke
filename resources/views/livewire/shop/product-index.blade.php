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
                        <div class="group relative flex flex-col h-full">

                            {{-- Bild Container --}}
                            <div class="aspect-square bg-gray-100 rounded-2xl overflow-hidden mb-4 relative border border-gray-100 shadow-sm transition-all duration-300 group-hover:shadow-md">
                                <a href="{{ route('product.show', $product->slug) }}" class="block w-full h-full">
                                    @if(!empty($product->media_gallery[0]))
                                        {{-- Prüfen ob Video oder Bild --}}
                                        @if(isset($product->media_gallery[0]['type']) && $product->media_gallery[0]['type'] === 'video')
                                            <video src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}" class="w-full h-full object-cover"></video>
                                        @else
                                            <img src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}"
                                                 alt="{{ $product->name }}"
                                                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                                        @endif
                                    @else
                                        <div class="flex items-center justify-center h-full text-gray-300 text-sm">
                                            Kein Bild
                                        </div>
                                    @endif

                                    {{-- Overlay beim Hovern (Optional) --}}
                                    <div class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                </a>

                                {{-- Badges (Neu / Sale) --}}
                                <div class="absolute top-3 left-3 flex flex-col gap-2 pointer-events-none">
                                    @if($product->compare_at_price > $product->price)
                                        <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded shadow-sm uppercase tracking-wide">Sale</span>
                                    @endif
                                    @if($product->created_at->diffInDays() < 14)
                                        <span class="bg-white/90 backdrop-blur text-gray-900 text-[10px] font-bold px-2 py-1 rounded shadow-sm uppercase tracking-wide border border-gray-200">Neu</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Infos --}}
                            <div class="flex-1 flex flex-col text-center">
                                <h3 class="text-lg font-serif font-bold text-gray-900 mb-1">
                                    <a href="{{ route('product.show', $product->slug) }}" class="hover:text-primary transition-colors">
                                        {{ $product->name }}
                                    </a>
                                </h3>

                                {{-- Preis --}}
                                <div class="mb-4 flex items-center justify-center gap-2">
                                <span class="text-primary font-bold">
                                    {{ number_format($product->price / 100, 2, ',', '.') }} €
                                </span>
                                    @if($product->compare_at_price > $product->price)
                                        <span class="text-sm text-gray-400 line-through">
                                        {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                    </span>
                                    @endif
                                </div>

                                {{-- Button (Optional, da Klick aufs Bild reicht, aber gut für CTA) --}}
                                <div class="mt-auto opacity-0 transform translate-y-2 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-300">
                                    <a href="{{ route('product.show', $product->slug) }}"
                                       class="inline-block px-6 py-2 border border-gray-900 text-gray-900 text-sm font-bold rounded-full hover:bg-primary hover:border-primary hover:text-white transition-colors">
                                        Zum Produkt
                                    </a>
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

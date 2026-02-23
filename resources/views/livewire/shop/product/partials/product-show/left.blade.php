{{-- LINKE SEITE: Medien Galerie, Details & Bewertungen --}}
<div class="w-full lg:sticky lg:top-24 lg:self-start lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto no-scrollbar lg:pb-12">

    {{-- ========================================== --}}
    {{-- 1. MEDIEN GALERIE (Immer sichtbar) --}}
    {{-- ========================================== --}}
    <div class="space-y-4 sm:space-y-6"
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
                     class="w-full h-full object-cover transition-transform duration-700 lg:group-hover:scale-105"
                     alt="{{ $product->name }}">

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
                @if($product->compare_at_price > $product->price)
                    <span class="bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">Sale</span>
                @endif
                @if($product->created_at->diffInDays() < 30)
                    <span class="bg-primary text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">Neu</span>
                @endif
                @if($product->type === 'digital')
                    <span class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">Digital</span>
                @endif
            </div>

        </div>

        {{-- Thumbnails Slider --}}
        @if(count($product->media_gallery ?? []) > 1)
            <div class="relative group/slider mt-4"
                 x-data="{
                    scroll(direction) {
                        const container = this.$refs.thumbnailContainer;
                        const scrollAmount = container.offsetWidth / 2;
                        container.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
                    }
                 }">

                {{-- Linker Pfeil (Nur Desktop sichtbar) --}}
                <button @click="scroll('left')" class="hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md items-center justify-center text-gray-600 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 -translate-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M15 19l-7-7 7-7" /></svg>
                </button>

                {{-- Container für Thumbnails --}}
                <div x-ref="thumbnailContainer" class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide snap-x no-scrollbar" style="scrollbar-width: none; -ms-overflow-style: none;">
                    @foreach($product->media_gallery as $media)
                        <div class="snap-start shrink-0" style="width: calc(20% - 13px);">
                            <button @click="activeMedia = '{{ asset('storage/'.$media['path']) }}'; activeType = '{{ $media['type'] ?? 'image' }}'"
                                    class="aspect-square w-full rounded-lg overflow-hidden border-2 transition-all block relative"
                                    :class="activeMedia === '{{ asset('storage/'.$media['path']) }}' ? 'border-primary ring-2 ring-primary/20' : 'border-transparent hover:border-gray-300'">

                                @if(isset($media['type']) && $media['type'] === 'video')
                                    <div class="w-full h-full bg-gray-900 flex items-center justify-center text-white">
                                        <video src="{{ asset('storage/'.$media['path']) }}" class="absolute inset-0 w-full h-full object-cover opacity-50"></video>
                                        <svg class="w-6 h-6 relative z-10" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                                    </div>
                                @else
                                    <img src="{{ asset('storage/'.$media['path']) }}" class="w-full h-full object-cover">
                                @endif
                            </button>
                        </div>
                    @endforeach
                </div>

                {{-- Rechter Pfeil (Nur Desktop sichtbar) --}}
                <button @click="scroll('right')" class="hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md items-center justify-center text-gray-600 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 translate-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        @endif

        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    </div>

    {{-- ========================================== --}}
    {{-- 2. PRODUKTDETAILS & BEWERTUNGEN (NUR AUF DESKTOP) --}}
    {{-- Auf Mobile wird dieser Teil in der right.blade.php ganz unten angezeigt --}}
    {{-- ========================================== --}}
    <div class="hidden lg:flex flex-col mt-12 w-full">
        <div class="border-t border-gray-100 pt-8 w-full block clear-both">

            <div class="text-gray-600 w-full block">
                <h3 class="font-serif text-2xl font-bold text-gray-900 mb-4">Beschreibung</h3>
                <div class="whitespace-pre-line leading-relaxed break-words text-sm">
                    {!! nl2br(e($this->product->description)) !!}
                </div>
            </div>

            @if(!empty($this->product->attributes))
                <div class="mt-10 bg-gray-50 rounded-2xl p-8 border border-gray-100 w-full block clear-both">
                    <h4 class="text-sm font-bold uppercase text-gray-900 mb-6 tracking-wider flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        Eigenschaften
                    </h4>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5 text-sm w-full">
                        @foreach($this->product->attributes as $key => $val)
                            @if(!empty($val))
                                <div class="flex flex-col border-b border-gray-200 pb-3 sm:border-0 sm:pb-0 w-full">
                                    <dt class="text-gray-500 mb-1">{{ $key }}</dt>
                                    <dd class="font-bold text-gray-900 break-words">{{ $val }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </div>
            @endif

        </div>

        {{-- Kundenbewertungen (Desktop Ansicht) --}}
        <div id="kundenbewertungen-desktop" class="mt-20 scroll-mt-24 w-full block clear-both">
            <livewire:shop.product.product-reviews :product="$product" />
        </div>
    </div>

</div>

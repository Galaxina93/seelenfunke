<div class="min-h-screen bg-white" style="--theme-color: #C9A66B; --theme-color-10: #C9A66B1A; --theme-color-20: #C9A66B33; --theme-color-30: #C9A66B4D; --theme-color-50: #C9A66B80;">

    {{-- Produkt- & Breadcrumb Structured Data (JSON-LD) + OpenGraph Product Price --}}
    @push('structured_data')
        @if(!empty($productSchema))
            <script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endpush
    @push('head')
        <meta property="product:price:amount" content="{{ number_format($this->product->price / 100, 2, '.', '') }}">
        <meta property="product:price:currency" content="EUR">
        <meta property="product:availability" content="{{ $this->product->isAvailable() ? 'in stock' : 'out of stock' }}">
    @endpush

    @if($this->product->slug === 'geschenkgutschein')
        @livewire('shop.marketing.marketing-voucher-purchase-page')
    @else
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">

            {{-- Breadcrumb --}}
            <nav class="flex text-sm font-medium text-gray-500 mb-8" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-[var(--theme-color)] transition-colors">Startseite</a>
                <span class="mx-2 text-gray-300">/</span>
                <a href="/shop" class="hover:text-[var(--theme-color)] transition-colors">Shop</a>
                <span class="mx-2 text-gray-300">/</span>
                <span class="text-gray-900 truncate max-w-[200px]">{{ $this->product->name }}</span>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 xl:gap-16 items-start">

                {{-- LINKE SEITE: Medien Galerie (AlpineJS) --}}
                @include('livewire.shop.product.partials.product-show.left')

                {{-- RECHTE SEITE: Info & Konfigurator --}}
                @include('livewire.shop.product.partials.product-show.right')

            </div>

            {{-- CROSS-SELLING / PASSENDE PRODUKTE UNTERHALB DES ZWEI-SPALTEN-DESIGNS --}}
            @include('livewire.shop.product.partials.product-show.related-products')
        </div>
    @endif
</div>

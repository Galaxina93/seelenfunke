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
            @include('livewire.shop.product.partials.product-show.left')

            {{-- RECHTE SEITE: Info & Konfigurator --}}
            @include('livewire.shop.product.partials.product-show.right')

        </div>
    </div>
</div>

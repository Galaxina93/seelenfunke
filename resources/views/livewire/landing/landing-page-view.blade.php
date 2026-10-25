<div style="--theme-color: #C9A66B; --theme-color-10: #C9A66B1A; --theme-color-20: #C9A66B33; --theme-color-30: #C9A66B4D; --theme-color-50: #C9A66B80;">
    @section('title', ($landingPage->title ?: $product->name) . ' | Mein Seelenfunke')
    @section('meta_description', \Illuminate\Support\Str::limit(strip_tags($landingPage->sales_copy ?: ($landingPage->headline ?: $product->description)), 155))
    @section('og_title', ($landingPage->title ?: $product->name) . ' – ' . ($landingPage->headline ?: 'Geschenke, die bleiben.'))
    @section('og_description', \Illuminate\Support\Str::limit(strip_tags($landingPage->sales_copy ?: ($landingPage->headline ?: $product->description)), 155))
    @if(!empty($heroImageMeta['url']))
        @section('og_image', $heroImageMeta['url'])
    @endif
    @section('og_type', 'product')
    @push('structured_data')
        <script type="application/ld+json">{!! json_encode($product->buildStructuredDataSchema(route('landing-page', $landingPage->slug)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    @if($landingPage->status !== 'active' || $product->status !== 'active')
        <div class="bg-amber-500/95 text-gray-950 px-4 py-2.5 text-xs font-bold text-center flex flex-wrap items-center justify-center gap-3 z-50 relative shadow-md">
            <span class="inline-flex items-center gap-1.5 uppercase tracking-wider font-black">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Admin-Vorschau (Entwurfsmodus)
            </span>
            <span class="font-medium">
                @if($product->status !== 'active')
                    Das zugehörige Produkt ist aktuell noch auf „{{ $product->status }}“ gesetzt.
                @else
                    Diese Landing Page befindet sich aktuell im Status „Entwurf“.
                @endif
            </span>
            <a href="{{ route('admin.marketing-landing-pages') }}" class="underline font-black hover:text-white transition-colors">
                Zurück zur Verwaltung →
            </a>
        </div>
    @endif

    <x-sections.page-container>
        @if(view()->exists('frontend.pages.landingpages.' . $landingPage->slug))
            {{-- Individueller Blade-Override (falls jemals ein abweichendes Spezialdesign gewünscht wird) --}}
            @include('frontend.pages.landingpages.' . $landingPage->slug)
        @else
            {{-- Zentrales, dynamisches Master-Template mit Jahreszeiten-, Produktbild- & Herzensprojekt-Logik --}}
            @include('livewire.landing.partials.master-template')
        @endif
    </x-sections.page-container>
</div>

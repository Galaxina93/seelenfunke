@php
    $seoTitle = $resolvedProfile['title'] ?? ($landingPage->title ?: $product->name);
    $seoHeadline = $resolvedProfile['headline'] ?? ($landingPage->headline ?: 'Geschenke, die bleiben.');
    $seoSalesCopy = $resolvedProfile['sales_copy'] ?? ($landingPage->sales_copy ?: ($product->description ?? ''));
    $fullPageTitle = str_contains((string) $seoTitle, '|') ? $seoTitle : ($seoTitle . ' | Mein Seelenfunke');
@endphp
<div style="--theme-color: #C5A059; --theme-color-10: #C5A0591A; --theme-color-20: #C5A05933; --theme-color-30: #C5A0594D; --theme-color-50: #C5A05980;">
    @section('title', $fullPageTitle)
    @section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) ($seoSalesCopy ?: $seoHeadline)), 155))
    @section('og_title', $seoTitle . ' | ' . $seoHeadline)
    @section('og_description', \Illuminate\Support\Str::limit(strip_tags((string) ($seoSalesCopy ?: $seoHeadline)), 155))
    @if(!empty($heroImageMeta['url']))
        @section('og_image', $heroImageMeta['url'])
    @endif
    @section('og_type', 'product')
    @push('structured_data')
        <script type="application/ld+json">{!! json_encode($product->buildStructuredDataSchema(route('landing-page', $landingPage->slug)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush

    @if($landingPage->status !== 'active' || $product->status !== 'active')
        <div class="bg-stone-900 text-white px-4 py-2.5 text-xs font-bold text-center flex flex-wrap items-center justify-center gap-3 z-50 relative shadow-md border-b border-[#C5A059]/40">
            <span class="inline-flex items-center gap-1.5 uppercase tracking-wider font-black text-[#C5A059]">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Admin-Vorschau (Entwurfsmodus)
            </span>
            <span class="font-medium text-stone-200">
                @if($product->status !== 'active')
                    Das zugehörige Produkt ist aktuell noch auf „{{ $product->status }}“ gesetzt.
                @else
                    Diese Landing Page befindet sich aktuell im Status „Entwurf“.
                @endif
            </span>
            <a href="{{ route('admin.marketing-landing-pages') }}" class="underline font-black text-[#C5A059] hover:text-white transition-colors">
                Zurück zur Verwaltung →
            </a>
        </div>
    @endif

    <x-sections.page-container>
        @if(view()->exists('frontend.pages.landingpages.' . $landingPage->slug))
            {{-- Individueller Blade-Override (falls jemals ein abweichendes Spezialdesign gewünscht wird) --}}
            @include('frontend.pages.landingpages.' . $landingPage->slug)
        @else
            {{-- Zentrales, dynamisches Master-Template mit Jahreszeiten-, Feiertags-Profil- & Herzensprojekt-Logik --}}
            @include('livewire.landing.partials.master-template')
        @endif
    </x-sections.page-container>
</div>

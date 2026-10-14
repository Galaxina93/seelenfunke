<div>
    @section('title', ($landingPage->title ?: $landingPage->product->name) . ' | Mein Seelenfunke')
    @section('meta_description', \Illuminate\Support\Str::limit(strip_tags($landingPage->sales_copy ?: ($landingPage->headline ?: $landingPage->product->description)), 155))
    @section('og_title', ($landingPage->title ?: $landingPage->product->name) . ' – ' . ($landingPage->headline ?: 'Geschenke, die bleiben.'))
    @section('og_description', \Illuminate\Support\Str::limit(strip_tags($landingPage->sales_copy ?: ($landingPage->headline ?: $landingPage->product->description)), 155))
    @if($landingPage->product && is_array($landingPage->product->media_gallery) && count($landingPage->product->media_gallery) > 0)
        @section('og_image', asset('storage/' . $landingPage->product->media_gallery[0]['path']))
    @endif

    <x-sections.page-container>
        @if(view()->exists('frontend.pages.landingpages.' . $landingPage->slug))
            {{-- Individueller Blade-Override (falls jemals ein abweichendes Spezialdesign gewünscht wird) --}}
            @include('frontend.pages.landingpages.' . $landingPage->slug)
        @else
            {{-- Zentrales, dynamisches Master-Template mit Herzensprojekt-Sektion --}}
            @include('livewire.landing.partials.master-template')
        @endif
    </x-sections.page-container>
</div>

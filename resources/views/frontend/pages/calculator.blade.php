@php
    $calcSchema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                '@id' => route('calculator') . '#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'B2B Projekt-Kalkulator', 'item' => route('calculator')],
                ],
            ],
            [
                '@type' => 'WebApplication',
                '@id' => route('calculator') . '#webapp',
                'name' => 'Mein Seelenfunke B2B & Projekt-Kalkulator für Lasergravuren',
                'url' => route('calculator'),
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'All',
                'description' => 'Interaktiver Sofort-Kalkulator für individuelle Lasergravuren, Trophäen, Mitarbeitergeschenke und Serienfertigungen inklusive Staffelrabatten und Express-Option.',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0.00',
                    'priceCurrency' => 'EUR',
                    'description' => 'Kostenlose Online-Projektkalkulation & Sofort-Angebot',
                ],
                'provider' => [
                    '@id' => url('/#organization'),
                ],
            ],
        ],
    ];
@endphp
<x-layouts.frontend_layout
    title="B2B & Projekt-Kalkulator für Lasergravur & Großbestellungen | Mein Seelenfunke"
    meta_description="Kalkuliere individuelle Lasergravuren, Pokale, Mitarbeitergeschenke und Serienfertigungen sofort online – inklusive Staffelrabatten & Express-Option.">
    @push('structured_data')
        <script type="application/ld+json">{!! json_encode($calcSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush
    <x-sections.page-container>

        <section class="bg-white py-20 sm:py-20 md:py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-12">

                <div class="my-16 sm:mt-20 scroll-mt-24" id="calculator-area">
                    {{-- Hinweis: Stelle sicher, dass deine Komponente auch wirklich unter diesem Namen registriert ist --}}
                    @livewire('shop.product.product-calculator.product-calculator')
                </div>

            </div>
        </section>

    </x-sections.page-container>
    <script src="{{ asset('vendor/html2canvas/html2canvas.min.js') }}"></script>
    <script src="{{ asset('js/admin-bundle.js') }}"></script>
</x-layouts.frontend_layout>

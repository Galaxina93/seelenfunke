@php
    $contactSchema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                '@id' => route('contact') . '#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Kontakt & Anfrage', 'item' => route('contact')],
                ],
            ],
            [
                '@type' => 'ContactPage',
                '@id' => route('contact') . '#contactpage',
                'url' => route('contact'),
                'name' => 'Kontakt & Individuelle Gravur-Anfrage | Mein Seelenfunke',
                'description' => 'Direkter Draht zur Lasergravur-Manufaktur Mein Seelenfunke in Gifhorn für Fragen zu Wunschgravuren, Sonderanfertigungen und Firmenaufträgen.',
                'mainEntity' => [
                    '@id' => url('/#organization'),
                ],
            ],
        ],
    ];
@endphp
<x-layouts.frontend_layout
    title="Kontakt & Individuelle Gravur-Anfrage | Mein Seelenfunke"
    meta_description="Hast du Fragen zu deiner Wunschgravur, Sonderanfertigungen oder Firmenaufträgen? Kontaktiere die Mein Seelenfunke Manufaktur in Gifhorn direkt.">
    @push('structured_data')
        <script type="application/ld+json">{!! json_encode($contactSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <x-sections.page-container>

        @livewire('frontend.contact-form')

    </x-sections.page-container>

</x-layouts.frontend_layout>

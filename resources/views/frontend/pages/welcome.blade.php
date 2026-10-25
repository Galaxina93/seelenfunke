<x-layouts.frontend_layout
    title="Personalisierte Geschenke & exklusive Lasergravur aus Gifhorn | Mein Seelenfunke"
    meta_description="Verwandle Momente in Ewigkeit ✨ Handveredelte Unikate aus K9-Kristallglas, Naturschiefer & Edelmetall mit 2D/3D-Live-Konfigurator. Manufaktur aus Gifhorn.">

    <x-sections.page-container>

        <!-- Hero Section -->
        @include('frontend.pages.partials.hero_section')

        <!-- Service Section -->
        @include('frontend.pages.partials.services_section')

        <!-- Work Areas Section -->
        @include('frontend.pages.partials.work_section')

        <!-- About Section -->
        @include('frontend.pages.partials.about_section')

        <!-- Process Section -->
        @include('frontend.pages.partials.process_section')

        <!-- 360° carefree -->
        @include('frontend.pages.partials.carefree_section')

        <!--FAQ Section-->
        @include('frontend.pages.partials.faq_section')

        {{--Contact Section--}}
        @livewire('frontend.contact-form')

    </x-sections.page-container>

</x-layouts.frontend_layout>

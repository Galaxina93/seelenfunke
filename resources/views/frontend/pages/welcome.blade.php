<x-layouts.frontend_layout
    title="Personalisierte Geschenke, Gravur-Schmuck & K9-Kristall aus Deutschland | Mein Seelenfunke"
    meta_description="Ein Funke, der bleibt ✨ Personalisierter Edelstahl-Schmuck, Schlüsselanhänger, Hundemarken, 360° Gläser & K9-Kristall mit Live-3D-Konfigurator. Deutsche Laser-Manufaktur in Gifhorn.">

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

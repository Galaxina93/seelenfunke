<x-layouts.frontend_layout>
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
</x-layouts.frontend_layout>

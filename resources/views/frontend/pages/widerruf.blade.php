<x-layouts.frontend_layout>
    <div class="px-4 py-16 sm:px-6 lg:px-8 bg-gray-50/50 min-h-screen">
        <div class="max-w-3xl mx-auto space-y-12">
            
            <div class="text-center mt-12">
                <h1 class="text-3xl md:text-4xl font-black text-gray-900 mb-4 tracking-tight">Elektronischer Widerruf</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Nutzen Sie dieses Formular, um Ihre Vertragserklärung über nicht-personalisierte Waren (falls zutreffend) auf elektronischem Wege fristgerecht zu widerrufen.
                </p>
            </div>

            <livewire:shop.order.order-revocation-form />

            <div class="text-center pb-24">
                <a href="{{ route('agb') }}#widerrufsbelehrung" class="text-sm text-gray-500 hover:text-primary underline underline-offset-4 transition-colors">
                    Zur vollständigen Widerrufsbelehrung & AGB
                </a>
            </div>

        </div>
    </div>
</x-layouts.frontend_layout>

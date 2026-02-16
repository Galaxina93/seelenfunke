<div class="bg-gray-50 min-h-screen py-12 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- START: Wrapper mit State für den Page-Switch --}}
        {{-- Wir hören auf das Window-Event, falls der Button im Child-Component (Rechte Spalte) das Event dispatcht --}}
        <div class="relative" x-data="{ isProcessing: false }" @checkout-processing.window="isProcessing = true">

            {{-- NEU: LADE-ANIMATION (Als Fullscreen Overlay mit weißer Box) --}}
            <div x-show="isProcessing"
                 x-cloak
                 class="fixed top-0 left-0 w-screen h-screen z-[9999] flex flex-col items-center justify-center bg-white/80 backdrop-blur-md transition-all animate-fade-in">

                {{-- DIE WEISSE BOX (Wrapper für den Inhalt) --}}
                <div class="flex flex-col items-center bg-white p-10 shadow-2xl border border-gray-100 rounded-3xl m-4 max-w-sm w-full transform transition-all scale-100">

                    {{-- FUNKI BILD --}}
                    <div x-data
                         x-init="window.scrollTo({ top: 0, behavior: 'smooth' })"
                         class="relative w-40 h-40 mb-6">
                        <img src="{{ asset('images/projekt/funki/checkout/funki_party.png') }}"
                             class="w-full h-full object-contain animate-bounce-slow"
                             alt="Verarbeite Bestellung">
                    </div>

                    {{-- TEXT & SPINNER --}}
                    <h3 class="text-xl font-serif font-bold text-gray-900 text-center flex items-center justify-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Einen Moment...
                    </h3>
                    <p class="text-gray-500 text-sm mt-2 text-center">Deine Bestellung wird sicher übertragen.</p>
                </div>
            </div>

            {{-- HAUPT-FORMULAR (Wird ausgeblendet bei Verarbeitung) --}}
            <div wire:loading.class="opacity-30 blur-[2px] pointer-events-none"
                 wire:target="handlePaymentSuccess"
                 x-show="!isProcessing"
                 class="transition-opacity duration-300">

                <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8 sr-only">Checkout</h1>
                <form id="payment-form" class="lg:grid lg:grid-cols-12 lg:gap-x-12 xl:gap-x-16">
                    @include("livewire.shop.checkout.partials.left-column-payment-adress-login")
                    @include("livewire.shop.checkout.partials.right-column-summary")
                </form>
            </div>
        </div>
    </div>
    @include("livewire.shop.checkout.partials.stripe-js")
</div>

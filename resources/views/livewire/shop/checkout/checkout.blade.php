<div class="bg-gray-50 min-h-screen py-12 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- START: Umschließender Container für Loading-Effekt --}}
        <div class="relative">

            {{-- Das Overlay mit Spinner und Blur-Effekt --}}
            <div wire:loading wire:target="handlePaymentSuccess"
                 class="fixed inset-0 lg:absolute z-[100] flex flex-col items-center justify-center bg-white/70 backdrop-blur-md transition-all">
                <div class="flex flex-col items-center bg-white p-8 shadow-2xl border border-gray-100">
                    <svg class="animate-spin h-12 w-12 text-primary mb-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                         viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <h3 class="text-xl font-serif font-bold text-gray-900">Bestellung wird finalisiert</h3>
                    <p class="text-gray-500 text-sm mt-2">Wir bereiten deine Rechnung vor und senden dir die
                        Bestätigung...</p>
                </div>
            </div>

            {{-- Der eigentliche Inhalt, der bei Belastung unscharf/blass wird --}}
            <div wire:loading.class="opacity-30 blur-[2px] pointer-events-none" wire:target="handlePaymentSuccess">
                <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8 sr-only">Checkout</h1>
                <form id="payment-form" class="lg:grid lg:grid-cols-12 lg:gap-x-12 xl:gap-x-16">

                    @include("livewire.shop.checkout.partials.left-column-payment-adress-login")

                    @include("livewire.shop.checkout.partials.right-column-summary")

                </form>
            </div>
        </div> {{-- ENDE: Umschließender Container --}}

    </div>

    @include("livewire.shop.checkout.partials.stripe-js")

</div>

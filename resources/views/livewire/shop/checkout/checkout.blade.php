<div class="bg-gray-50 min-h-screen py-12 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- START: Wrapper mit State für den Page-Switch --}}
        {{-- Wir hören auf das Window-Event, falls der Button im Child-Component (Rechte Spalte) das Event dispatcht --}}
        <div class="relative" x-data="{ isProcessing: false }" @checkout-processing.window="isProcessing = true">

            {{-- NEU: LADE-OVERLAY (Als Fullscreen Overlay) --}}
            <div x-show="isProcessing"
                 x-cloak
                 class="fixed top-0 left-0 w-screen h-screen z-[9999] flex flex-col items-center justify-center bg-white/80 backdrop-blur-md transition-all animate-fade-in">
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

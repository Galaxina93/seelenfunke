<div class="bg-gray-50 min-h-screen py-12 lg:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- START: Wrapper mit State für den Page-Switch --}}
        <div class="relative" x-data="{ isProcessing: false }" @checkout-processing.window="isProcessing = true" @checkout-processing-done.window="isProcessing = false">

            {{-- LADE-OVERLAY (Nur für Mobile, da Desktop die rechte Spalte nutzt) --}}
            <div x-show="isProcessing"
                 x-cloak
                 class="lg:hidden fixed top-0 left-0 w-screen h-screen z-[9999] flex flex-col items-center justify-center bg-white/80 backdrop-blur-md transition-all animate-fade-in">
            </div>

            {{-- HAUPT-FORMULAR --}}
            <div wire:loading.class="opacity-30 blur-[2px] pointer-events-none"
                 wire:target="handlePaymentSuccess"
                 class="transition-opacity duration-300">

                <h1 class="text-3xl font-serif font-bold text-gray-900 mb-8 sr-only">Checkout</h1>

                {{-- CSS Grid stellt sicher, dass col-span funktioniert und items-start erlaubt das sticky Verhalten --}}
                <form id="payment-form" class="grid grid-cols-1 lg:grid-cols-12 gap-y-10 lg:gap-x-12 xl:gap-x-16 items-start">
                    @include("livewire.shop.checkout.partials.left-column-payment-adress-login")
                    @include("livewire.shop.checkout.partials.right-column-summary")
                </form>
            </div>
        </div>
    </div>
    @include("livewire.shop.checkout.partials.stripe-js")
</div>

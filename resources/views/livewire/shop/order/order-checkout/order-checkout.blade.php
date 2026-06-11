<div class="min-h-screen bg-white" x-data="{ isProcessing: false }" @checkout-processing.window="isProcessing = true" @checkout-processing-done.window="isProcessing = false">
    {{-- LADE-OVERLAY (Nur für Mobile) --}}
    <div x-show="isProcessing"
         x-cloak
         class="lg:hidden fixed top-0 left-0 w-screen h-screen z-[9999] flex flex-col items-center justify-center bg-white/80 backdrop-blur-md transition-all animate-fade-in">
    </div>

    {{-- HAUPT-FORMULAR --}}
    <div wire:loading.class="opacity-30 blur-[2px] pointer-events-none"
         wire:target="handlePaymentSuccess"
         class="transition-opacity duration-300">

        <h1 class="sr-only">Checkout</h1>

        <form id="payment-form" class="grid grid-cols-1 lg:grid-cols-12 min-h-screen">
            {{-- LINKE SPALTE: Rechnungsdetails & Zahlung --}}
            <div class="lg:col-span-7 bg-white px-4 sm:px-6 lg:px-8 xl:px-16 py-12 lg:py-24 lg:flex lg:justify-end">
                <div class="w-full lg:max-w-2xl">
                    @include("livewire.shop.order.order-checkout.partials.left-column-payment-adress-login")
                </div>
            </div>

            {{-- RECHTE SPALTE: Bestellübersicht --}}
            <div class="lg:col-span-5 bg-[#FCFAF7] border-t lg:border-t-0 lg:border-l border-[#F3EDE2] px-4 sm:px-6 lg:px-8 xl:px-16 py-12 lg:py-24">
                <div class="w-full lg:max-w-md">
                    @include("livewire.shop.order.order-checkout.partials.right-column-summary")
                </div>
            </div>
        </form>
    </div>

    @include("livewire.shop.order.order-checkout.partials.stripe-js")

    {{-- Google Places API integration --}}
    @if(config('services.google.places_key'))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.places_key') }}&libraries=places&language=de"></script>
        <script>
            document.addEventListener('livewire:initialized', () => {
                function initAutocomplete() {
                    const addressInput = document.getElementById('address');
                    if (addressInput) {
                        const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                            types: ['address'],
                            componentRestrictions: { country: ['de', 'at', 'ch'] },
                            fields: ['address_components', 'geometry']
                        });
                        autocomplete.addListener('place_changed', () => {
                            const place = autocomplete.getPlace();
                            let streetNumber = '';
                            let route = '';
                            let postalCode = '';
                            let city = '';
                            let country = 'DE';

                            for (const component of place.address_components) {
                                const componentType = component.types[0];
                                switch (componentType) {
                                    case 'street_number':
                                        streetNumber = component.long_name;
                                        break;
                                    case 'route':
                                        route = component.long_name;
                                        break;
                                    case 'postal_code':
                                        postalCode = component.long_name;
                                        break;
                                    case 'locality':
                                        city = component.long_name;
                                        break;
                                    case 'country':
                                        country = component.short_name;
                                        break;
                                }
                            }

                            const fullStreet = route + (streetNumber ? ' ' + streetNumber : '');
                            
                            // Update Livewire properties
                            @this.set('address', fullStreet);
                            @this.set('postal_code', postalCode);
                            @this.set('city', city);
                            @this.set('country', country);
                        });
                    }

                    const shippingInput = document.getElementById('shipping_address');
                    if (shippingInput) {
                        const shippingAutocomplete = new google.maps.places.Autocomplete(shippingInput, {
                            types: ['address'],
                            componentRestrictions: { country: ['de', 'at', 'ch'] },
                            fields: ['address_components', 'geometry']
                        });
                        shippingAutocomplete.addListener('place_changed', () => {
                            const place = shippingAutocomplete.getPlace();
                            let streetNumber = '';
                            let route = '';
                            let postalCode = '';
                            let city = '';
                            let country = 'DE';

                            for (const component of place.address_components) {
                                const componentType = component.types[0];
                                switch (componentType) {
                                    case 'street_number':
                                        streetNumber = component.long_name;
                                        break;
                                    case 'route':
                                        route = component.long_name;
                                        break;
                                    case 'postal_code':
                                        postalCode = component.long_name;
                                        break;
                                    case 'locality':
                                        city = component.long_name;
                                        break;
                                    case 'country':
                                        country = component.short_name;
                                        break;
                                }
                            }

                            const fullStreet = route + (streetNumber ? ' ' + streetNumber : '');
                            
                            // Update Livewire properties
                            @this.set('shipping_address', fullStreet);
                            @this.set('shipping_postal_code', postalCode);
                            @this.set('shipping_city', city);
                            @this.set('shipping_country', country);
                        });
                    }
                }

                initAutocomplete();

                Livewire.on('checkout-updated', () => {
                    setTimeout(initAutocomplete, 200);
                });
            });
        </script>
    @endif
</div>

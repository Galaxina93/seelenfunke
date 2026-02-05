{{-- STRIPE JS --}}
<script src="https://js.stripe.com/v3/"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        let stripe, elements, paymentElement;
        let lastClientSecret = null;
        const stripeKey = "{{ $stripeKey }}";

        async function initializeStripe() {
            const clientSecret = await @this.get('clientSecret');

            if (!stripeKey || !clientSecret) {
                console.error("Stripe Konfiguration fehlt.");
                return;
            }

            // WICHTIG: Wenn das Secret identisch ist, brechen wir ab.
            // Das verhindert das Resetten des IFrames bei AGB-Checkboxen.
            if (clientSecret === lastClientSecret) {
                return;
            }

            lastClientSecret = clientSecret;
            stripe = Stripe(stripeKey);

            const appearance = {
                theme: 'stripe',
                variables: { colorPrimary: '#C5A059', borderRadius: '8px' }
            };

            const container = document.getElementById("payment-element");
            container.innerHTML = '';

            elements = stripe.elements({ appearance, clientSecret });
            paymentElement = elements.create("payment", { layout: "tabs" });
            paymentElement.mount("#payment-element");
        }

        // Erstinitialisierung
        initializeStripe();

        // Re-Initialisierung nur bei echtem Update (z.B. Preisänderung durch Land)
        Livewire.on('checkout-updated', () => {
            initializeStripe();
        });

        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const spinner = document.getElementById('spinner');
        const buttonText = document.getElementById('button-text');
        const messageContainer = document.getElementById('payment-message');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if(submitButton.disabled) return;
            setLoading(true);
            messageContainer.classList.add("hidden");

            try {
                // 1. Validierung und Order in DB erstellen
                const orderId = await @this.validateAndCreateOrder();

                if(!orderId) {
                    setLoading(false);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }

                // 2. Stripe Zahlung bestätigen
                const { error, paymentIntent } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: "{{ route('checkout.success') }}",
                        payment_method_data: {
                            billing_details: {
                                name: @this.get('first_name') + ' ' + @this.get('last_name'),
                                email: @this.get('email'),
                                address: {
                                    city: @this.get('city'),
                                    country: @this.get('country'),
                                    line1: @this.get('address'),
                                    postal_code: @this.get('postal_code')
                                }
                            }
                        }
                    },
                    redirect: 'if_required'
                });

                if (error) {
                    showMessage(error.type === "card_error" || error.type === "validation_error" ? error.message : "Ein unerwarteter Fehler ist aufgetreten.");
                    setLoading(false);
                }
                else if (paymentIntent && paymentIntent.status === 'succeeded') {
                    await @this.handlePaymentSuccess(orderId);
                }

            } catch (error) {
                console.error("System Error:", error);
                showMessage("Verbindungsfehler. Bitte versuche es erneut.");
                setLoading(false);
            }
        });

        function setLoading(isLoading) {
            if (isLoading) { submitButton.disabled = true; spinner.classList.remove("hidden"); buttonText.classList.add("hidden"); }
            else { submitButton.disabled = false; spinner.classList.add("hidden"); buttonText.classList.remove("hidden"); }
        }
        function showMessage(text) { messageContainer.classList.remove("hidden"); messageContainer.textContent = text; messageContainer.scrollIntoView({ behavior: "smooth", block: "center" }); }
    });
</script>

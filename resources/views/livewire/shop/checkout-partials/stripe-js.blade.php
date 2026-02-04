{{-- STRIPE JS --}}
<script src="https://js.stripe.com/v3/"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        let stripe, elements, paymentElement;
        const stripeKey = "{{ $stripeKey }}";

        async function initializeStripe() {
            const clientSecret = await @this.get('clientSecret');

            if (!stripeKey || !clientSecret) {
                console.error("Stripe Konfiguration fehlt.");
                return;
            }

            stripe = Stripe(stripeKey);
            const appearance = { theme: 'stripe', variables: { colorPrimary: '#C5A059', borderRadius: '8px' } };

            const container = document.getElementById("payment-element");
            container.innerHTML = '';

            elements = stripe.elements({ appearance, clientSecret });
            paymentElement = elements.create("payment", { layout: "tabs" });
            paymentElement.mount("#payment-element");
        }

        initializeStripe();

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
                // 1. Validierung und Order in DB erstellen (Status pending)
                // Dies stellt sicher, dass die OrderItems in der Datenbank existieren.
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
                    // Verhindert den Redirect, falls die Zahlung sofort bestätigt wird
                    redirect: 'if_required'
                });

                // 3. Ergebnis verarbeiten
                if (error) {
                    // Fehler von Stripe (Karte abgelehnt etc.)
                    showMessage(error.type === "card_error" || error.type === "validation_error" ? error.message : "Ein unerwarteter Fehler ist aufgetreten.");
                    setLoading(false);
                }
                // Wenn kein Fehler vorliegt ODER der Status 'succeeded' ist
                else if (paymentIntent && paymentIntent.status === 'succeeded') {
                    // ZAHLUNG ERFOLGREICH (Ohne Redirect)
                    // Wir triggern jetzt die Livewire-Methode und übergeben die orderId,
                    // damit Livewire exakt weiß, welche Bestellung finalisiert werden soll.
                    await @this.handlePaymentSuccess(orderId);
                }
                else if (!error) {
                    // Fallback für den Fall, dass ein Redirect eingeleitet wurde (stripe übernimmt dann)
                    // (Z.B. bei 3D Secure, wo das Fenster oben wegnavigiert)
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

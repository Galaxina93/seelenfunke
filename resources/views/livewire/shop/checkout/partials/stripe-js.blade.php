<script src="https://js.stripe.com/v3/"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        let stripe, elements, paymentElement, expressCheckoutElement;
        let lastClientSecret = null;
        const stripeKey = "{{ $stripeKey }}";

        async function initializeStripe() {
            const clientSecret = await @this.get('clientSecret');
            if (!stripeKey || !clientSecret) {
                console.error("Stripe Konfiguration fehlt.");
                return;
            }

            if (clientSecret === lastClientSecret) { return; }
            lastClientSecret = clientSecret;
            stripe = Stripe(stripeKey);

            const appearance = {
                theme: 'stripe',
                variables: { colorPrimary: '#C5A059', borderRadius: '8px' }
            };

            const container = document.getElementById("payment-element");
            const expressContainer = document.getElementById("express-checkout-element");

            if(container) container.innerHTML = '';
            if(expressContainer) expressContainer.innerHTML = '';

            elements = stripe.elements({ appearance, clientSecret });

            // 1. EXPRESS CHECKOUT ELEMENT LADEN
            if(expressContainer) {
                expressCheckoutElement = elements.create('expressCheckout', {
                    layout: {
                        maxColumns: 2,
                        maxRows: 1
                    }
                });
                expressCheckoutElement.mount('#express-checkout-element');

                // A) Validierung BEVOR das Apple Pay / Google Pay Fenster aufgeht
                expressCheckoutElement.on('click', async (event) => {
                    const msgBox = document.getElementById('express-message');
                    msgBox.classList.add("hidden");

                    try {
                        // Wir zwingen Livewire, zuerst das Formular (Adressen & AGB) zu prüfen
                        await @this.validateCheckoutData();
                    } catch (error) {
                        // Formular ist noch nicht komplett! Wir blockieren das Öffnen des Wallets
                        event.preventDefault();
                        msgBox.textContent = "Bitte füllen Sie zuerst die Rechnungsdaten und die rechtlichen Bedingungen (Schritt 1) vollständig aus.";
                        msgBox.classList.remove("hidden");
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });

                // B) Der Kunde hat via Apple/Google Pay authentifiziert und bestätigt
                expressCheckoutElement.on('confirm', async (event) => {

                    const {billingDetails, shippingAddress} = event; // Daten aus dem Wallet

                    // Wir füllen die Livewire Variablen automatisch mit den Daten von Apple/Google Pay
                    if (billingDetails) {
                    @this.set('email', billingDetails.email, false);
                        const nameParts = billingDetails.name.split(' ');
                    @this.set('first_name', nameParts[0], false);
                    @this.set('last_name', nameParts.slice(1).join(' '), false);
                    }

                    if (shippingAddress) {
                    @this.set('address', shippingAddress.line1 + (shippingAddress.line2 ? ' ' + shippingAddress.line2 : ''), false);
                    @this.set('postal_code', shippingAddress.postalCode, false);
                    @this.set('city', shippingAddress.city, false);
                    @this.set('country', shippingAddress.country, false);
                    }

                    setLoading(true);
                    const msgBox = document.getElementById('express-message');
                    msgBox.classList.add("hidden");

                    try {
                        // Order in deiner Datenbank anlegen
                        const orderId = await @this.validateAndCreateOrder();

                        if (!orderId) {
                            setLoading(false);
                            return;
                        }

                        // Zahlung bei Stripe final verbuchen
                        const { error, paymentIntent } = await stripe.confirmPayment({
                            elements,
                            clientSecret: lastClientSecret,
                            confirmParams: {
                                return_url: "{{ route('checkout.success') }}",
                            },
                            redirect: 'if_required'
                        });

                        if (error) {
                            msgBox.textContent = error.message;
                            msgBox.classList.remove("hidden");
                            setLoading(false);
                        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                            await @this.handlePaymentSuccess(orderId);
                        }
                    } catch (error) {
                        console.error("System Error:", error);
                        msgBox.textContent = "Verbindungsfehler bei der Express-Zahlung.";
                        msgBox.classList.remove("hidden");
                        setLoading(false);
                    }
                });
            }

            // 2. STANDARD PAYMENT ELEMENT LADEN
            if(container) {
                paymentElement = elements.create("payment", { layout: "tabs" });
                paymentElement.mount("#payment-element");
            }
        }

        initializeStripe();

        Livewire.on('checkout-updated', () => {
            initializeStripe();
        });

        // --- STANDARD BUTTON LOGIK ---
        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const spinner = document.getElementById('spinner');
        const buttonText = document.getElementById('button-text');
        const messageContainer = document.getElementById('payment-message');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (submitButton.disabled) return;

            setLoading(true);
            messageContainer.classList.add("hidden");

            try {
                const orderId = await @this.validateAndCreateOrder();
                if (!orderId) {
                    setLoading(false);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }

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
                } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                    await @this.handlePaymentSuccess(orderId);
                }
            } catch (error) {
                console.error("System Error:", error);
                showMessage("Verbindungsfehler. Bitte versuche es erneut.");
                setLoading(false);
            }
        });

        function setLoading(isLoading) {
            if (isLoading) {
                submitButton.disabled = true;
                spinner.classList.remove("hidden");
                buttonText.classList.add("hidden");
            } else {
                submitButton.disabled = false;
                spinner.classList.add("hidden");
                buttonText.classList.remove("hidden");
            }
        }

        function showMessage(text) {
            messageContainer.classList.remove("hidden");
            messageContainer.textContent = text;
            messageContainer.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    });
</script>

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
                    window.dispatchEvent(new CustomEvent('checkout-processing'));
                    const msgBox = document.getElementById('express-message');
                    msgBox.classList.add("hidden");

                    try {
                        // Order in deiner Datenbank anlegen
                        const orderId = await @this.validateAndCreateOrder();

                        if (!orderId) {
                            setLoading(false);
                            window.dispatchEvent(new CustomEvent('checkout-processing-done'));
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
                            window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                            await @this.handlePaymentSuccess(orderId);
                        }
                    } catch (error) {
                        console.error("System Error:", error);
                        msgBox.textContent = "Verbindungsfehler bei der Express-Zahlung.";
                        msgBox.classList.remove("hidden");
                        setLoading(false);
                        window.dispatchEvent(new CustomEvent('checkout-processing-done'));
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

            messageContainer.classList.add("hidden");

            // 1. ZUERST STRIPE FELDER PRÜFEN (Ohne das Backend zu berühren!)
            const {error: submitError} = await elements.submit();
            if (submitError) {
                // Wenn z.B. die Kreditkartennummer fehlt, brechen wir hier sofort ab
                showMessage(submitError.message);
                return;
            }

            // Erst jetzt wird das Lade-UI getriggert
            setLoading(true);
            window.dispatchEvent(new CustomEvent('checkout-processing'));

            try {
                // 2. ERST JETZT die Bestellung im Backend validieren und anlegen
                const orderId = await @this.validateAndCreateOrder();
                if (!orderId) {
                    setLoading(false);
                    window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }

                // 3. Wenn alles erfolgreich war, die Zahlung bei Stripe auslösen
                const { error, paymentIntent } = await stripe.confirmPayment({
                    elements,
                    clientSecret: lastClientSecret, // clientSecret muss hier übergeben werden, wenn elements.submit() genutzt wird
                    confirmParams: {
                        return_url: "{{ route('checkout.success') }}",
                        payment_method_data: {
                            billing_details: {
                                name: await @this.get('first_name') + ' ' + await @this.get('last_name'),
                                email: await @this.get('email'),
                                address: {
                                    city: await @this.get('city'),
                                    country: await @this.get('country'),
                                    line1: await @this.get('address'),
                                    postal_code: await @this.get('postal_code')
                                }
                            }
                        }
                    },
                    redirect: 'if_required'
                });

                if (error) {
                    showMessage(error.type === "card_error" || error.type === "validation_error" ? error.message : "Ein unerwarteter Fehler ist aufgetreten.");
                    setLoading(false);
                    window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                    await @this.handlePaymentSuccess(orderId);
                }
            } catch (error) {
                console.error("System Error:", error);
                showMessage("Bitte fülle alle Pflichtfelder korrekt aus!");
                setLoading(false);
                window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                window.scrollTo({ top: 0, behavior: 'smooth' });
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

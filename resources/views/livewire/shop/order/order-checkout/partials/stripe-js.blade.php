<script src="https://js.stripe.com/v3/"></script>

<script>
    document.addEventListener('livewire:initialized', () => {

        let stripe, elements, paymentElement, expressCheckoutElement;
        let lastClientSecret = null;
        const stripeKey = "{{ $stripeKey }}";

        async function initializeStripe() {
            const total = await @this.get('totalAmount');
            if (total === 0) {
                return;
            }

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
                expressCheckoutElement.on('click', (event) => {
                    const msgBox = document.getElementById('express-message');
                    msgBox.classList.add("hidden");

                    // Schneller, synchroner Client-Check für die rechtlichen AGB-Checkboxen
                    const termsChecked = document.getElementById('terms')?.checked;
                    const privacyChecked = document.getElementById('privacy')?.checked;

                    if (!termsChecked || !privacyChecked) {
                        msgBox.textContent = "Bitte bestätige zuerst die AGB und die Datenschutzerklärung am Ende der Seite (Schritt 2).";
                        msgBox.classList.remove("hidden");
                        msgBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return; // Kein event.resolve() aufrufen -> Das Wallet-Fenster bleibt geschlossen
                    }

                    // Erfolgreich: Wallet-Fenster freigeben (Muss innerhalb 1s geschehen!)
                    event.resolve();
                });

                // B) Der Kunde hat via Apple/Google Pay authentifiziert und bestätigt
                expressCheckoutElement.on('confirm', async (event) => {
                    const {billingDetails, shippingAddress} = event; // Daten aus dem Wallet

                    const splitName = (fullName) => {
                        if (!fullName) return { first: '', last: '' };
                        const parts = fullName.trim().split(/\s+/);
                        if (parts.length === 1) {
                            return { first: parts[0], last: parts[0] }; // Fallback
                        }
                        return {
                            first: parts[0],
                            last: parts.slice(1).join(' ')
                        };
                    };

                    let bName = billingDetails && billingDetails.name ? billingDetails.name : '';
                    let sName = shippingAddress && shippingAddress.name ? shippingAddress.name : bName;

                    const bNameParts = splitName(bName);
                    const sNameParts = splitName(sName);

                    // 1. E-Mail setzen
                    if (billingDetails && billingDetails.email) {
                        @this.set('email', billingDetails.email, false);
                    }

                    // 2. Rechnungsadresse (Billing) setzen
                    @this.set('first_name', bNameParts.first, false);
                    @this.set('last_name', bNameParts.last, false);

                    let bAddr = billingDetails && billingDetails.address ? billingDetails.address : null;
                    let sAddr = shippingAddress && shippingAddress.address ? shippingAddress.address : null;

                    // Fallback, falls kein explizites Billing-Address-Objekt vorhanden ist
                    let finalBAddr = bAddr || sAddr;
                    if (finalBAddr) {
                        const line1 = finalBAddr.line1 || '';
                        const line2 = finalBAddr.line2 || '';
                        @this.set('address', line1 + (line2 ? ' ' + line2 : ''), false);
                        @this.set('postal_code', finalBAddr.postal_code || finalBAddr.postalCode || '', false);
                        @this.set('city', finalBAddr.city || '', false);
                        @this.set('country', finalBAddr.country || 'DE', false);
                    }

                    // 3. Lieferadresse (Shipping) prüfen und setzen
                    if (sAddr) {
                        const isDifferentAddress = !bAddr || 
                            bAddr.line1 !== sAddr.line1 || 
                            bAddr.city !== sAddr.city || 
                            bAddr.postal_code !== sAddr.postal_code || 
                            bAddr.country !== sAddr.country;
                        
                        const isDifferentName = bName !== sName;

                        if (isDifferentAddress || isDifferentName) {
                            @this.set('has_separate_shipping', true, false);
                            @this.set('shipping_first_name', sNameParts.first, false);
                            @this.set('shipping_last_name', sNameParts.last, false);
                            
                            const sLine1 = sAddr.line1 || '';
                            const sLine2 = sAddr.line2 || '';
                            @this.set('shipping_address', sLine1 + (sLine2 ? ' ' + sLine2 : ''), false);
                            @this.set('shipping_postal_code', sAddr.postal_code || sAddr.postalCode || '', false);
                            @this.set('shipping_city', sAddr.city || '', false);
                            @this.set('shipping_country', sAddr.country || 'DE', false);
                        } else {
                            @this.set('has_separate_shipping', false, false);
                        }
                    } else {
                        @this.set('has_separate_shipping', false, false);
                    }

                    setLoadingState(true);
                    window.dispatchEvent(new CustomEvent('checkout-processing'));
                    const msgBox = document.getElementById('express-message');
                    msgBox.classList.add("hidden");

                    try {
                        // Order in deiner Datenbank anlegen
                        const orderId = await @this.validateAndCreateOrder();

                        if (!orderId) {
                            setLoadingState(false);
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
                            setLoadingState(false);
                            window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                            await @this.handlePaymentSuccess(orderId);
                        }
                    } catch (error) {
                        console.error("System Error:", error);
                        msgBox.textContent = "Verbindungsfehler bei der Express-Zahlung.";
                        msgBox.classList.remove("hidden");
                        setLoadingState(false);
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

        // --- STANDARD BUTTON LOGIK VIA EVENT DELEGATION ---
        if (!window.hasStripeSubmitListener) {
            window.hasStripeSubmitListener = true;

            document.addEventListener('submit', async (e) => {
                if (e.target && e.target.id === 'payment-form') {
                    e.preventDefault();

                    const submitButton = document.getElementById('submit-button');
                    if (submitButton && submitButton.disabled) return;

                    const messageContainer = document.getElementById('payment-message');
                    if (messageContainer) messageContainer.classList.add("hidden");

                    // 1. ZUERST STRIPE FELDER PRÜFEN (Ohne das Backend zu berühren!)
                    if (elements) {
                        const { error: submitError } = await elements.submit();
                        if (submitError) {
                            showMessage(submitError.message);
                            return;
                        }
                    }

                    // Erst jetzt wird das Lade-UI getriggert
                    setLoadingState(true);
                    window.dispatchEvent(new CustomEvent('checkout-processing'));

                    try {
                        // 2. ERST JETZT die Bestellung im Backend validieren und anlegen
                        const orderId = await @this.validateAndCreateOrder();

                        if (!orderId) {
                            setLoadingState(false);
                            window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                            return;
                        }

                        // Wenn kein clientSecret vorhanden ist (z. B. vollbezahlt mit Gutschein), direkt abschließen
                        const currentClientSecret = await @this.get('clientSecret');
                        if (!currentClientSecret) {
                            await @this.handlePaymentSuccess(orderId);
                            return;
                        }

                        // 3. Wenn alles erfolgreich war, die Zahlung bei Stripe auslösen
                        const { error, paymentIntent } = await stripe.confirmPayment({
                            elements,
                            clientSecret: lastClientSecret,
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
                            setLoadingState(false);
                            window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                        } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                            await @this.handlePaymentSuccess(orderId);
                        }
                    } catch (error) {
                        console.error("System Error:", error);
                        showMessage("Bitte fülle alle Pflichtfelder korrekt aus!");
                        setLoadingState(false);
                        window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            });
        }

        function setLoadingState(isLoading) {
            const submitButton = document.getElementById('submit-button');
            const spinner = document.getElementById('spinner');
            const buttonText = document.getElementById('button-text');

            if (isLoading) {
                if(submitButton) submitButton.disabled = true;
                if(spinner) spinner.classList.remove("hidden");
                if(buttonText) buttonText.classList.add("hidden");
            } else {
                if(submitButton) submitButton.disabled = false;
                if(spinner) spinner.classList.add("hidden");
                if(buttonText) buttonText.classList.remove("hidden");
            }
        }

        function showMessage(text) {
            const messageContainer = document.getElementById('payment-message');
            if (messageContainer) {
                messageContainer.classList.remove("hidden");
                messageContainer.textContent = text;
                messageContainer.scrollIntoView({ behavior: "smooth", block: "center" });
            }
        }
    });
</script>

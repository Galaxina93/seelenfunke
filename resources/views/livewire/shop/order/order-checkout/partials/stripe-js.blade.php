<script src="https://js.stripe.com/v3/"></script>

<script>
    (function () {
        function bootStripeCheckout() {
            let stripe, elements, paymentElement, expressCheckoutElement;
            let lastClientSecret = null;
            const stripeKey = "{{ $stripeKey }}";

            async function syncDomFieldsToLivewire() {
                const fieldMap = [
                    ['email', 'email'],
                    ['first_name', 'first_name'],
                    ['last_name', 'last_name'],
                    ['company', 'company'],
                    ['address', 'address'],
                    ['postal_code', 'postal_code'],
                    ['city', 'city'],
                    ['country', 'country'],
                ];
                for (const [domId, prop] of fieldMap) {
                    const el = document.getElementById(domId);
                    if (el && typeof el.value === 'string') {
                        @this.set(prop, el.value, false);
                    }
                }
                const termsEl = document.getElementById('terms');
                if (termsEl) {
                    @this.set('terms_accepted', !!termsEl.checked, false);
                }
                const waiverEl = document.getElementById('digital_waiver');
                if (waiverEl) {
                    @this.set('digital_waiver_accepted', !!waiverEl.checked, false);
                }
            }

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

                const container = document.getElementById("payment-element");
                const alreadyMounted = container && container.children && container.children.length > 0;

                if (clientSecret === lastClientSecret && alreadyMounted) {
                    if (elements && typeof elements.fetchUpdates === 'function') {
                        try { await elements.fetchUpdates(); } catch (e) {}
                    }
                    return;
                }
                lastClientSecret = clientSecret;
                stripe = Stripe(stripeKey);

                const appearance = {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '#C5A059',
                        colorBackground: '#F9FAFB',
                        colorText: '#111827',
                        colorTextSecondary: '#374151',
                        colorTextPlaceholder: '#9CA3AF',
                        colorDanger: '#DC2626',
                        borderRadius: '8px'
                    },
                    rules: {
                        '.Input': {
                            backgroundColor: '#F9FAFB',
                            border: '1px solid #D1D5DB',
                            color: '#111827',
                            caretColor: '#111827',
                            cursor: 'text',
                            boxShadow: '0 1px 2px 0 rgba(0, 0, 0, 0.05)'
                        },
                        '.Input:hover': {
                            backgroundColor: '#FFFFFF',
                            borderColor: '#C5A059'
                        },
                        '.Input:focus': {
                            backgroundColor: '#FFFFFF',
                            borderColor: '#C5A059',
                            boxShadow: '0 0 0 3px rgba(197, 160, 89, 0.22)'
                        },
                        '.Tab': {
                            border: '1px solid #E5E7EB',
                            backgroundColor: '#F9FAFB',
                            cursor: 'pointer'
                        },
                        '.Tab:hover': {
                            borderColor: '#C5A059',
                            backgroundColor: '#FFFFFF'
                        },
                        '.Tab--selected': {
                            borderColor: '#C5A059',
                            backgroundColor: '#FFFFFF',
                            boxShadow: '0 0 0 2px rgba(197, 160, 89, 0.2)'
                        }
                    }
                };

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
                        if (msgBox) msgBox.classList.add("hidden");

                        // Schneller, synchroner Client-Check für die rechtlichen AGB-Checkboxen
                        const termsChecked = document.getElementById('terms')?.checked;
                        const waiverEl = document.getElementById('digital_waiver');
                        const waiverChecked = !waiverEl || waiverEl.checked;

                        if (!termsChecked || !waiverChecked) {
                            window.dispatchEvent(new CustomEvent('checkout-legal-warning'));
                            if (msgBox) {
                                msgBox.textContent = "Bitte bestätige zuerst den rot markierten Pflichthaken (AGB & Widerrufsbelehrung) in der Bestellübersicht.";
                                msgBox.classList.remove("hidden");
                            }
                            document.getElementById('checkout-legal-box')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
                        if (msgBox) msgBox.classList.add("hidden");

                        try {
                            // Order in deiner Datenbank anlegen
                            const orderId = await @this.validateAndCreateOrder();

                            if (!orderId) {
                                setLoadingState(false);
                                window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                                window.dispatchEvent(new CustomEvent('checkout-open-billing'));
                                showSubmitError("Bitte prüfe die rot markierten Pflichtfelder bei den Rechnungsdetails (Schritt 1).");
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
                                if (msgBox) {
                                    msgBox.textContent = error.message;
                                    msgBox.classList.remove("hidden");
                                }
                                setLoadingState(false);
                                window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                            } else if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
                                await @this.handlePaymentSuccess(orderId);
                            }
                        } catch (error) {
                            console.error("System Error:", error);
                            if (msgBox) {
                                msgBox.textContent = "Verbindungsfehler bei der Express-Zahlung.";
                                msgBox.classList.remove("hidden");
                            }
                            setLoadingState(false);
                            window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                        }
                    });
                }

                // 2. STANDARD PAYMENT ELEMENT LADEN (Link & Wallets deaktiviert, da Express Checkout oben bereits Apple/Google Pay anbietet)
                if(container) {
                    paymentElement = elements.create("payment", {
                        layout: "tabs",
                        wallets: {
                            link: 'never',
                            applePay: 'never',
                            googlePay: 'never'
                        }
                    });
                    paymentElement.mount("#payment-element");
                }
            }

            initializeStripe();

            Livewire.on('checkout-updated', () => {
                initializeStripe();
            });

            // --- STANDARD BUTTON LOGIK VIA EVENT DELEGATION (immer auf aktuelle Component-Instanz gebunden) ---
            if (window._stripeSubmitHandler) {
                document.removeEventListener('submit', window._stripeSubmitHandler);
            }

            window._stripeSubmitHandler = async (e) => {
                if (!e.target || e.target.id !== 'payment-form') return;
                e.preventDefault();

                const submitButton = document.getElementById('submit-button');
                if (submitButton && submitButton.disabled) return;

                hideSubmitError();

                // Pflichthaken prüfen: Falls nicht alle gesetzt sind, rot markieren und hinscrollen
                const termsEl = document.getElementById('terms');
                const waiverEl = document.getElementById('digital_waiver');
                if ((termsEl && !termsEl.checked) || (waiverEl && !waiverEl.checked)) {
                    window.dispatchEvent(new CustomEvent('checkout-legal-warning'));
                    document.getElementById('checkout-legal-box')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                const messageContainer = document.getElementById('payment-message');
                if (messageContainer) messageContainer.classList.add("hidden");

                // Alle aktuellen DOM-Eingaben (falls noch nicht geblurred) synchronisieren
                await syncDomFieldsToLivewire();

                // 1. ZUERST STRIPE FELDER PRÜFEN (Ohne das Backend zu berühren!)
                const total = await @this.get('totalAmount');
                if (total > 0) {
                    if (!elements) {
                        await initializeStripe();
                    }
                    if (elements) {
                        const { error: submitError } = await elements.submit();
                        if (submitError) {
                            showMessage(submitError.message);
                            showSubmitError(submitError.message);
                            return;
                        }
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
                        window.dispatchEvent(new CustomEvent('checkout-open-billing'));
                        showSubmitError("Bitte fülle alle rot markierten Pflichtfelder in Schritt 1 (Rechnungsdetails) vollständig aus.");
                        document.getElementById('checkout-billing-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        return;
                    }

                    // Wenn kein clientSecret vorhanden ist (z. B. vollbezahlt mit Gutschein), direkt abschließen
                    const currentClientSecret = await @this.get('clientSecret');
                    if (!currentClientSecret || total === 0) {
                        await @this.handlePaymentSuccess(orderId);
                        return;
                    }

                    // 3. Wenn alles erfolgreich war, die Zahlung bei Stripe auslösen
                    const { error, paymentIntent } = await stripe.confirmPayment({
                        elements,
                        clientSecret: currentClientSecret || lastClientSecret,
                        confirmParams: {
                            return_url: "{{ route('checkout.success') }}",
                            payment_method_data: {
                                billing_details: {
                                    name: (await @this.get('first_name')) + ' ' + (await @this.get('last_name')),
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
                        const errMsg = error.type === "card_error" || error.type === "validation_error"
                            ? error.message
                            : (error.message || "Ein unerwarteter Fehler bei der Zahlung ist aufgetreten.");
                        showMessage(errMsg);
                        showSubmitError(errMsg);
                        setLoadingState(false);
                        window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                    } else if (paymentIntent && (paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing')) {
                        await @this.handlePaymentSuccess(orderId);
                    }
                } catch (error) {
                    console.error("System Error:", error);
                    showMessage("Bitte fülle alle Pflichtfelder in Schritt 1 (Rechnungsdetails) korrekt aus!");
                    showSubmitError("Bitte fülle alle Pflichtfelder in Schritt 1 (Rechnungsdetails) korrekt aus!");
                    setLoadingState(false);
                    window.dispatchEvent(new CustomEvent('checkout-processing-done'));
                    window.dispatchEvent(new CustomEvent('checkout-open-billing'));
                    document.getElementById('checkout-billing-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };

            document.addEventListener('submit', window._stripeSubmitHandler);

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

            function showSubmitError(text) {
                const submitErr = document.getElementById('checkout-submit-error');
                if (submitErr) {
                    submitErr.textContent = text;
                    submitErr.classList.remove('hidden');
                }
            }

            function hideSubmitError() {
                const submitErr = document.getElementById('checkout-submit-error');
                if (submitErr) {
                    submitErr.textContent = '';
                    submitErr.classList.add('hidden');
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
        }

        if (window.Livewire) {
            bootStripeCheckout();
        } else {
            document.addEventListener('livewire:initialized', bootStripeCheckout, { once: true });
        }
    })();
</script>

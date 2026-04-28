import './cookieconsent.umd.js';

// Darkmode aktivieren
document.documentElement.classList.add('cc--darkmode');

CookieConsent.run({
    guiOptions: {
        consentModal: {
            layout: "box",
            position: "bottom left",
            equalWeightButtons: true,
            flipButtons: false
        },
        preferencesModal: {
            layout: "box",
            position: "right",
            equalWeightButtons: true,
            flipButtons: false
        }
    },
    categories: {
        necessary: {
            readOnly: true
        },
        analytics: {}
    },
    language: {
        default: "de",
        autoDetect: "browser",
        translations: {
            de: {
                consentModal: {
                    title: "Cookies für dein Erlebnis",
                    description:
                        "Wir verwenden Cookies, um den Besuch unserer Website attraktiv zu gestalten und Zugriffe zu analysieren. Einige Cookies sind essenziell, andere helfen uns, diese Website und Ihr Erlebnis zu verbessern.",
                    acceptAllBtn: "Alle akzeptieren",
                    acceptNecessaryBtn: "Nur notwendige Cookies",
                    showPreferencesBtn: "Einstellungen",
                    footer: '<a href="/datenschutz" class="cc__link">Datenschutzerklärung</a>'
                },
                preferencesModal: {
                    title: "Cookie-Einstellungen",
                    acceptAllBtn: "Alle akzeptieren",
                    acceptNecessaryBtn: "Nur notwendige Cookies",
                    savePreferencesBtn: "Einstellungen speichern",
                    closeIconLabel: "Schließen",
                    serviceCounterLabel: "Dienst|Dienste",
                    sections: [
                        {
                            title: "Verwendung von Cookies",
                            description:
                                "Wir verwenden Cookies, um grundlegende Funktionen bereitzustellen und die Nutzung der Website zu analysieren. Sie können selbst entscheiden, welche Kategorien Sie erlauben möchten."
                        },
                        {
                            title: "Unbedingt erforderliche Cookies <span class='pm__badge'>Immer aktiv</span>",
                            description:
                                "Diese Cookies sind notwendig, damit die Website korrekt funktioniert. Sie können nicht deaktiviert werden.",
                            linkedCategory: "necessary"
                        },
                        {
                            title: "Statistik / Analyse-Cookies",
                            description:
                                "Diese Cookies helfen uns zu verstehen, wie Besucher mit der Website interagieren, indem Informationen anonym gesammelt werden.",
                            linkedCategory: "analytics"
                        },
                        {
                            title: "Weitere Informationen",
                            description:
                                "Für Fragen zu unseren Cookie-Richtlinien <a class='cc__link' href='/datenschutz'>klicken Sie hier</a>."
                        }
                    ]
                }
            }
        }
    }
});

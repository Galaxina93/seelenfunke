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
                    title: "Hand aufs Herz: Auch wir müssen kurz über Cookies sprechen 🍪✨",
                    description:
                        '<span style="display:block;margin-bottom:8px;">Leider können wir dir durch den Bildschirm keine frisch gebackenen Kekse aus unserer Manufaktur reichen (und mit dem Laser lassen sich Schokodrops auch echt schlecht gravieren). Stattdessen nutzen wir digitale Cookies, um unsere Seite und deinen Konfigurator noch schöner zu machen.</span><span style="display:block;margin-bottom:6px;">Einige sind technisch notwendig, damit dein Warenkorb dein Unikat nicht vergisst. Optionale Cookies helfen uns mit anonymen Statistiken und Komfort-Extras (wie der automatischen Adress-Vervollständigung im Checkout). Geht das für dich klar?</span><button type="button" data-cc="show-preferencesModal" class="cc__link">Ich will mehr wissen.</button>',
                    acceptAllBtn: "Geht klar! (Alle akzeptieren)",
                    acceptNecessaryBtn: "Nur Notwendige",
                    showPreferencesBtn: "Einstellungen",
                    footer: '<a href="/datenschutz" class="cc__link">Datenschutzerklärung</a><a href="/impressum" class="cc__link">Impressum</a>'
                },
                preferencesModal: {
                    title: "Deine Cookie-Einstellungen (Ganz ohne Krümel)",
                    acceptAllBtn: "Geht klar! (Alle akzeptieren)",
                    acceptNecessaryBtn: "Nur Notwendige",
                    savePreferencesBtn: "Auswahl speichern",
                    closeIconLabel: "Schließen",
                    serviceCounterLabel: "Dienst|Dienste",
                    sections: [
                        {
                            title: "Warum wir überhaupt fragen (Klartext statt Juristen-Kauderwelsch)",
                            description:
                                "Wir gravieren lieber persönliche Herzensstücke, als dich mit trockenen Textwüsten zu langweilen – aber deine Privatsphäre ist uns heilig. Hier entscheidest du selbst, welche Kategorien du erlaubst. Deine Einwilligung (§ 25 Abs. 1 TDDDG / Art. 6 Abs. 1 lit. a DSGVO) ist freiwillig und jederzeit über das Cookie-Symbol am Bildschirmrand oder im Footer widerrufbar."
                        },
                        {
                            title: "Unbedingt erforderliche Cookies <span class='pm__badge'>Immer aktiv</span>",
                            description:
                                "Ohne diese kleinen Helfer läuft in unserer Online-Manufaktur nichts (§ 25 Abs. 2 Nr. 2 TDDDG). Sie merken sich dein gestaltetes Unikat im Warenkorb, schützen deine Sitzung (Session/CSRF), sorgen im Checkout für Zahlungssicherheit & Betrugsprävention (__stripe_mid, __stripe_sid auf /checkout), ordnen aktive Partner-Links oder Gutscheine zu (seelenfunke_*) und speichern deine Support-Chat-Sitzung (sf_chat_uuid) sowie lokale Audio-Einstellungen. Da der Shop ohne sie nicht funktioniert, sind sie immer aktiv.",
                            linkedCategory: "necessary"
                        },
                        {
                            title: "Statistik & Komfort-Funktionen (inkl. Google Maps Adress-Autovervollständigung)",
                            description:
                                "Wenn du uns hier grünes Licht gibst (§ 25 Abs. 1 TDDDG i.V.m. Art. 6 Abs. 1 lit. a DSGVO), machst du dir und uns das Leben leichter: Wir aktivieren im Checkout die Google Maps Places API (Google Ireland Limited), damit sich deine Lieferadresse beim Tippen fast wie von Zauberhand vervollständigt, und erfassen anonymisierte Interaktionsstatistiken, um zu verstehen, welche Unikate und Funktionen euch am meisten begeistern.",
                            linkedCategory: "analytics"
                        },
                        {
                            title: "Noch Fragen offen?",
                            description:
                                "Alle Details bis ins kleinste Paragraphen-Detail findest du jederzeit in unserer <a class='cc__link' href='/datenschutz'>Datenschutzerklärung</a> sowie im <a class='cc__link' href='/impressum'>Impressum</a>."
                        }
                    ]
                }
            }
        }
    }
});

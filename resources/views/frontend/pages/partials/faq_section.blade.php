@php
    $freeThreshold = (int) shop_setting('shipping_free_threshold', 5000);
    $shippingCost = (int) shop_setting('shipping_cost', 490);
    $expressSurcharge = (int) shop_setting('express_surcharge', 2500);
@endphp

<section id="faq" class="bg-gradient-to-b from-gray-50 to-white py-24 scroll-mt-20" aria-labelledby="faq-heading">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- HEADLINE --}}
        <div class="text-center mb-20 fade-in">
            <span class="inline-block px-4 py-1.5 mb-4 text-xs font-bold tracking-widest text-primary uppercase bg-primary/10 rounded-full">
                Häufige Fragen
            </span>
            <h2 id="faq-heading" class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-6 text-serif">
                Alles, was du über deinen <span class="text-primary italic">Seelenfunken</span> wissen musst
            </h2>
            <div class="w-24 h-1 bg-primary/30 mx-auto rounded-full mb-8 text-serif" aria-hidden="true"></div>
            <p class="text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto text-serif">
                Du hast noch offene Punkte? Hier findest du die Antworten rund um unsere Manufaktur in Gifhorn, den Versand und die Personalisierung.
            </p>
        </div>

        {{-- FAQ ACCORDION --}}
        <div class="space-y-6 fade-in" id="faq-container">

            {{-- FRAGE: Material --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Ist das wirklich Glas oder Acryl/Plastik?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                    Wir verwenden ausschließlich <strong>massives K9-Kristallglas</strong>. Das ist kein leichtes Plastik oder Acryl, sondern schweres, optisch reines Glas, das speziell für Laserinnengravuren entwickelt wurde. Du wirst den Qualitätsunterschied sofort am Gewicht und der Brillanz spüren.
                </div>
            </details>

            {{-- FRAGE: Foto --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4 text-serif">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary text-serif" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 00-2 2z" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif text-serif m-0">Kann ich auch ein eigenes Foto gravieren lassen?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif text-serif">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                    Ja, absolut! Das ist unsere Spezialität. Du kannst uns dein Wunschfoto einfach im Konfigurator hochladen. Wichtig ist eine möglichst gute Auflösung. Bevor wir den Laser starten, prüft unser Team jedes Bild manuell. Sollte die Qualität nicht ausreichen, melden wir uns proaktiv bei dir.
                </div>
            </details>

            {{-- FRAGE: Versandkosten (DYNAMISCH) --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4 text-serif">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6 text-serif" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Was kostet der Versand & wie schnell seid ihr?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif">
                            <svg class="w-6 h-6 transition-transform group-open:hidden text-serif text-serif" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block text-serif text-serif" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                    <p class="mb-4">
                        Innerhalb Deutschlands versenden wir ab einem Bestellwert von <strong>{{ number_format($freeThreshold / 100, 2, ',', '.') }} €</strong> grundsätzlich <strong>versandkostenfrei</strong>. Darunter berechnen wir eine kleine Pauschale von {{ number_format($shippingCost / 100, 2, ',', '.') }} €.
                    </p>
                    <p class="mb-4">
                        Die Fertigung dauert in der Regel 1–3 Werktage. Der Versand erfolgt sicher per DHL.
                    </p>
                    <a href="{{ url('/versand') }}"
                       title="Übersicht der Versandkosten und Lieferzeiten"
                       class="inline-flex items-center gap-1.5 text-primary font-bold hover:underline">
                        Alle Details zur Lieferung & EU-Versand ansehen
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            </details>

            {{-- FRAGE: Express --}}
            @if($expressSurcharge > 0)
                <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                    <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                        <div class="flex items-center gap-4 text-serif">
                            <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary text-serif" aria-hidden="true">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <h3 class="text-lg md:text-xl font-bold font-serif text-serif italic m-0">Bietet ihr einen Express-Service an?</h3>
                        </div>
                        <div class="ml-4 flex-shrink-0 text-serif" aria-hidden="true">
                            <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif text-serif">
                                <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                            </span>
                        </div>
                    </summary>
                    <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                        Ja! Wenn es besonders eilig ist, kannst du für {{ number_format($expressSurcharge / 100, 2, ',', '.') }} € unseren Express-Service buchen. Dein Auftrag wird dann mit höchster Priorität gefertigt und bevorzugt dem Versanddienstleister übergeben.
                    </div>
                </details>
            @endif

        </div>

        {{-- Footer Text --}}
        <div class="mt-20 text-center bg-white p-10 rounded-[2.5rem] shadow-sm border border-gray-50 text-serif">
            <h4 class="text-lg font-bold text-gray-900 mb-2">Noch etwas unklar?</h4>
            <p class="text-gray-500 mb-6">
                Schreib uns einfach eine Nachricht. Wir antworten meist innerhalb weniger Stunden.
            </p>
            <a href="mailto:kontakt@mein-seelenfunke.de"
               title="Kundenservice per E-Mail kontaktieren"
               class="inline-flex items-center gap-2 px-8 py-3 bg-gray-900 text-white font-bold rounded-full hover:bg-primary transition-all duration-300 group text-serif">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Jetzt Kontakt aufnehmen
            </a>
        </div>

    </div>
</section>

{{-- STRUCTURED DATA FOR FAQ (JSON-LD) --}}
<script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Ist das wirklich Glas oder Acryl/Plastik?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Wir verwenden ausschließlich massives K9-Kristallglas. Das ist kein leichtes Plastik oder Acryl, sondern schweres, optisch reines Glas, das speziell für Laserinnengravuren entwickelt wurde."
          }
        },
        {
          "@type": "Question",
          "name": "Kann ich auch ein eigenes Foto gravieren lassen?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Ja, absolut! Das ist unsere Spezialität. Du kannst uns dein Wunschfoto einfach im Konfigurator hochladen. Unser Team prüft die Qualität manuell, bevor wir den Laser starten."
          }
        },
        {
          "@type": "Question",
          "name": "Was kostet der Versand & wie schnell seid ihr?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Innerhalb Deutschlands versenden wir ab einem Bestellwert von {{ number_format($freeThreshold / 100, 2, ',', '.') }} € versandkostenfrei. Darunter beträgt die Pauschale {{ number_format($shippingCost / 100, 2, ',', '.') }} €. Die Fertigung dauert ca. 1–3 Werktage."
                  }
                }
    @if($expressSurcharge > 0)
        ,{
          "@type": "Question",
          "name": "Bietet ihr einen Express-Service an?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Ja! Für einen Aufpreis von {{ number_format($expressSurcharge / 100, 2, ',', '.') }} € wird Ihr Auftrag mit höchster Priorität gefertigt und bevorzugt versendet."
                    }
                }
    @endif
    ]
  }
</script>

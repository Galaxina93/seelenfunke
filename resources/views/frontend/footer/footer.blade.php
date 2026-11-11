<footer class="relative bg-black text-white py-16 overflow-hidden border-t border-white/10" style="background-color: #000000;" role="contentinfo">

    <div class="relative z-10 max-w-7xl mx-auto px-6 lg:px-12">

        {{-- Grid angepasst auf 4 Spalten für Desktop --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

            {{-- Spalte 1: Laser Info --}}
            <div class="flex flex-col items-start">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-full border-2 border-primary flex items-center justify-center text-primary bg-white/5 shadow-sm" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-serif font-bold text-white leading-tight">Laserzertifiziert</h3>
                        <span class="text-xs text-primary font-bold uppercase tracking-wider">Industrie & Gewerbe</span>
                    </div>
                </div>
                <p class="text-sm text-white leading-relaxed">
                    Mit einer zertifizierten <a href="{{ route('manufacture') }}#kompetenz" title="Zu unserem Laserschutz-Zertifikat: Unsere Kompetenz für Ihre Sicherheit" class="font-bold text-white underline decoration-primary underline-offset-4 hover:text-primary hover:decoration-primary transition-colors">Laserschutzbeauftragten</a> stehen Sicherheit und Präzision bei der Veredelung Ihrer Unikate an erster Stelle.
                </p>

                {{-- 3 Kompakte Manufaktur-Qualitätsmerkmale --}}
                <div class="grid grid-cols-3 gap-2 mt-4 w-full">
                    <div class="rounded-xl border border-white/10 bg-white/[0.03] px-2 py-2.5 flex flex-col items-center justify-center text-center gap-1 hover:border-primary/40 transition-colors" title="Manuelle Endkontrolle in der Manufaktur">
                        <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-gray-200 leading-tight">Premium Qualität</span>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/[0.03] px-2 py-2.5 flex flex-col items-center justify-center text-center gap-1 hover:border-primary/40 transition-colors" title="Persönlich für dich gelasert in Gifhorn">
                        <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                        </svg>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-gray-200 leading-tight">Handveredelt</span>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-white/[0.03] px-2 py-2.5 flex flex-col items-center justify-center text-center gap-1 hover:border-primary/40 transition-colors" title="Liebevoll & sicher gepolstert">
                        <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-gray-200 leading-tight">Sorgfältig verpackt</span>
                    </div>
                </div>
            </div>

            {{-- Spalte 2: Adresse --}}
            <div>
                <h3 class="text-lg font-serif font-bold mb-4 text-white">Manufaktur & Büro</h3>
                <div class="flex items-start gap-3 text-white text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary mt-1 shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                    </svg>
                    <address class="not-italic">
                        @php
                            $footerCountryCode = strtoupper((string) shop_setting('company_country', 'DE'));
                            $footerCountriesMap = shop_setting('active_countries', ['DE' => 'Deutschland']);
                            $footerCountryLabel = is_array($footerCountriesMap) && isset($footerCountriesMap[$footerCountryCode])
                                ? $footerCountriesMap[$footerCountryCode]
                                : ($footerCountryCode === 'DE' ? 'Deutschland' : $footerCountryCode);
                        @endphp
                        <span class="font-bold text-white">{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}</span><br>
                        {{ shop_setting('company_street') ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '26')) : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}<br>
                        {{ shop_setting('company_city') ? trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city')) : shop_setting('owner_city', '38518 Gifhorn') }}<br>
                        {{ $footerCountryLabel }}
                    </address>
                </div>
            </div>

            {{-- Spalte 3: Links --}}
            <div>
                <h3 class="text-lg font-serif font-bold mb-4 text-white">Service & Rechtliches</h3>
                <nav aria-label="Footer Navigation">
                    <ul class="text-white text-sm space-y-3">
                        <li>
                            <a href="{{ route('shop') }}"
                               title="Direkt zum Online-Shop"
                               class="text-white hover:text-primary transition flex items-center gap-2">
                                <span aria-hidden="true">🛍️</span> Zum Shop & Kollektion
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('manufacture') }}"
                               title="Die Manufaktur, Materialkunde & Alina Steinhauer"
                               class="text-white hover:text-primary transition flex items-center gap-2">
                                <span aria-hidden="true">✨</span> Die Manufaktur
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('marketing/marketing/blog') }}"
                               title="Magazin, Inspiration & Ratgeber zur Lasergravur"
                               class="text-white hover:text-primary transition flex items-center gap-2">
                                <span aria-hidden="true">📖</span> Magazin & Ratgeber
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('calculator') }}"
                               title="B2B & Projekt-Kalkulator für Großbestellungen"
                               class="text-white hover:text-primary transition flex items-center gap-2">
                                <span aria-hidden="true">🧮</span> B2B Projekt-Kalkulator
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('product.show', 'geschenkgutschein') }}"
                               title="Personalisierte Geschenkgutscheine bestellen"
                               class="text-white hover:text-primary transition flex items-center gap-2">
                                <span aria-hidden="true">🎁</span> Geschenkgutscheine
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('manufacture') }}#herzensprojekte"
                               title="Unsere Herzensprojekte für Kitas & Kinder vor Ort"
                               class="text-white hover:text-primary transition flex items-center gap-2 font-semibold">
                                <span aria-hidden="true">❤️</span> Unsere Herzensprojekte
                            </a>
                        </li>

                        {{-- 1. Allgemeine Kontakt-Mail --}}
                        <li>
                            <a href="mailto:{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}"
                               title="Senden Sie uns eine E-Mail"
                               class="text-white hover:text-primary transition flex items-center gap-2">
                                <span aria-hidden="true">📧</span> {{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}
                            </a>
                        </li>

                        <li class="h-px bg-white/15 w-full my-2" aria-hidden="true"></li>

                        <li><a href="/impressum" title="Impressum ansehen" class="text-white hover:text-primary transition">Impressum</a></li>
                        <li><a href="/datenschutz" title="Datenschutzerklärung lesen" class="text-white hover:text-primary transition">Datenschutzerklärung</a></li>
                        <li><a href="/agb" title="Allgemeine Geschäftsbedingungen" class="text-white hover:text-primary transition">AGB & Widerrufsbelehrung</a></li>
                        <li><a href="/verhaltenskodex" title="Unser Verhaltenskodex" class="text-white hover:text-primary transition">Verhaltenskodex</a></li>
                        <li><a href="/versand" title="Informationen zu Versand & Lieferung" class="text-white hover:text-primary transition">Versand</a></li>
                        <li><a href="/barrierefreiheit" title="Erklärung zur Barrierefreiheit" class="text-white hover:text-primary transition">Barrierefreiheit</a></li>
                        <li>
                            <button type="button"
                                    onclick="if (window.CookieConsent && typeof window.CookieConsent.showPreferences === 'function') { window.CookieConsent.showPreferences(); } else { window.location.href = '/datenschutz'; }"
                                    title="Cookie-Einwilligungen verwalten oder widerrufen"
                                    class="text-white hover:text-primary transition text-left cursor-pointer">
                                Cookie-Einstellungen
                            </button>
                        </li>

                        <li class="pt-2"><a href="{{ route('widerruf') }}" title="Vertrag elektronisch widerrufen" class="text-white hover:text-primary transition flex items-center gap-1.5 font-medium"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-primary"><path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" /></svg>Vertrag widerrufen</a></li>
                    </ul>
                </nav>
            </div>

            {{-- Spalte 4: MarketingNewsletter --}}
            <div>
                @livewire('shop.marketing.marketing-newsletter-signup')
            </div>

        </div>

        <div class="border-t border-white/15 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-white text-sm text-center md:text-left">
                &copy; {{ date('Y') }} <strong>{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}</strong>. Alle Rechte vorbehalten.<br>
                <span class="text-xs text-white">Handveredelt mit ❤️ in unserer Manufaktur in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}.</span>
                @php
                    $footerHerzensEuro = \App\Services\HerzensprojektService::resolveFormattedEuro();
                    $footerHerzensIsPerItem = \App\Services\HerzensprojektService::isPerItem();
                @endphp
                <p class="text-[11px] text-white mt-2 leading-relaxed max-w-3xl">
                    * Transparenzhinweis zu unseren <a href="{{ route('manufacture') }}#herzensprojekte" class="underline hover:text-primary transition-colors">Herzensprojekten</a> (§ 5a UWG): Unsere Herzensprojekte sind eine freiwillige unternehmerische Initiative von {{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }} (Cause-Related Marketing). {{ $footerHerzensIsPerItem ? 'Pro verkauftem und bezahltem Artikel (nicht widerrufen)' : 'Pro bezahlter und nicht widerrufener Bestellung' }} führen wir {{ $footerHerzensEuro }}&nbsp;€ aus unseren erwirtschafteten Unternehmensmitteln unserem regionalen Herzensprojekte-Fonds für Kitas und Kinderprojekte in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }} und Umgebung zu. Als Kunde tätigst du mit deinem Einkauf keine eigenständige Spende und erhältst keine steuerliche Spendenquittung.
                </p>
            </div>

            <div class="flex space-x-4">
                <a href="https://www.instagram.com/Mein_Seelenfunke/"
                   target="_blank"
                   rel="noopener noreferrer"
                   title="Folgen Sie uns auf Instagram"
                   class="text-white hover:text-primary transition">
                    <span class="sr-only">Instagram</span>
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                <a href="https://www.tiktok.com/@mein_seelenfunke"
                   target="_blank"
                   rel="noopener noreferrer"
                   title="Folgen Sie uns auf TikTok"
                   class="text-white hover:text-primary transition">
                    <span class="sr-only">TikTok</span>
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                </a>
            </div>
        </div>

        {{-- Zahlungsmöglichkeiten ganz unten im Footer --}}
        @php
            $footerPaymentMethods = [
                ['file' => 'paypal.svg',     'name' => 'PayPal',                        'img_class' => 'h-4 w-auto'],
                ['file' => 'klarna.svg',     'name' => 'Klarna (Rechnung & Sofort)',    'img_class' => 'h-5 w-auto'],
                ['file' => 'visa.svg',       'name' => 'Visa Kreditkarte',              'img_class' => 'h-3.5 w-auto'],
                ['file' => 'mastercard.svg', 'name' => 'Mastercard Kreditkarte',        'img_class' => 'h-5 w-auto'],
                ['file' => 'amex.svg',       'name' => 'American Express',              'img_class' => 'h-5 w-auto'],
                ['file' => 'apple-pay.svg',  'name' => 'Apple Pay',                     'img_class' => 'h-4 w-auto'],
                ['file' => 'google-pay.svg', 'name' => 'Google Pay',                    'img_class' => 'h-4 w-auto'],
                ['file' => 'sepa.svg',       'name' => 'SEPA-Lastschrift & Vorkasse',   'img_class' => 'h-5 w-auto'],
            ];
        @endphp
        <div class="border-t border-white/15 mt-8 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-xs text-white font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-primary shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <span>Sichere &amp; SSL-verschlüsselte Zahlungsabwicklung</span>
            </div>

            <ul class="flex flex-wrap items-center justify-center sm:justify-end gap-2" aria-label="Akzeptierte Zahlungsmethoden">
                @foreach($footerPaymentMethods as $pm)
                    <li>
                        <div class="h-8 w-14 px-2 rounded-lg bg-white border border-white/20 shadow-sm flex items-center justify-center transition-all duration-200 hover:border-primary hover:shadow-md hover:-translate-y-0.5"
                             title="{{ $pm['name'] }}">
                            <img src="{{ asset('shop/frontend/payments/' . $pm['file']) }}"
                                 alt="{{ $pm['name'] }}"
                                 loading="lazy"
                                 decoding="async"
                                 width="44"
                                 height="20"
                                 class="{{ $pm['img_class'] }} max-w-full object-contain select-none">
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

    </div>
</footer>

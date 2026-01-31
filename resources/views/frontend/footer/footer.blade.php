<footer class="relative bg-gray-900 text-white py-16 overflow-hidden border-t border-gray-800">

    {{--<div class="absolute inset-0 z-0 opacity-10">
        <img src="{{ asset('images/projekt/products/werkstatt-laser.jpg') }}" alt="Background" class="w-full h-full object-cover grayscale">
    </div>--}}

    <div class="relative z-10 max-w-7xl mx-auto px-6 lg:px-12">

        {{-- Grid angepasst auf 4 Spalten für Desktop --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

            {{-- Spalte 1: Laser Info --}}
            <div class="flex flex-col items-start">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-full border-2 border-primary flex items-center justify-center text-primary bg-primary/10">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-lg font-serif font-bold text-white leading-tight">Laserzertifiziert</h4>
                        <span class="text-xs text-primary uppercase tracking-wider">Industrie & Gewerbe</span>
                    </div>
                </div>
                <p class="text-sm text-gray-400 leading-relaxed">
                    Mit einer zertifizierten <strong>Laserschutzbeauftragten</strong> stehen Sicherheit und Präzision an erster Stelle.
                </p>
            </div>

            {{-- Spalte 2: Adresse --}}
            <div>
                <h4 class="text-lg font-serif font-semibold mb-4 text-primary">Manufaktur & Büro</h4>
                <div class="flex items-start gap-3 text-gray-300 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary mt-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                    </svg>
                    <p>
                        <span class="font-medium text-white">Mein Seelenfunke</span><br>
                        Carl-Goerdeler-Ring 26<br>
                        38518 Gifhorn<br>
                        Deutschland
                    </p>
                </div>
            </div>

            {{-- Spalte 3: Links --}}
            <div>
                <h4 class="text-lg font-serif font-semibold mb-4 text-primary">Service & Rechtliches</h4>
                <ul class="text-gray-300 text-sm space-y-3">
                    <li>
                        <a href="{{ route('shop') }}" class="hover:text-primary transition flex items-center gap-2">
                            <span>🛍️ Zum Shop</span>
                        </a>
                    </li>
                    <li><a href="mailto:kontakt@mein-seelenfunke.de" class="hover:text-primary transition">📧 kontakt@mein-seelenfunke.de</a></li>
                    <li class="h-px bg-gray-800 w-full my-2"></li>
                    <li><a href="/impressum" class="hover:text-white transition opacity-80 hover:opacity-100">Impressum</a></li>
                    <li><a href="/datenschutz" class="hover:text-white transition opacity-80 hover:opacity-100">Datenschutzerklärung</a></li>
                    <li><a href="/agb" class="hover:text-white transition opacity-80 hover:opacity-100">AGB & Widerruf</a></li>
                    <li><a href="/verhaltenskodex" class="hover:text-white transition opacity-80 hover:opacity-100">Verhaltenskodex</a></li>
                    <li><a href="/versand" class="hover:text-white transition opacity-80 hover:opacity-100">Versand</a></li>
                </ul>
            </div>

            {{-- Spalte 4: Newsletter (NEU) --}}
            <div>
                @livewire('shop.newsletter-signup')
            </div>

        </div>

        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-gray-500 text-sm text-center md:text-left">
                &copy; {{ date('Y') }} <strong>Mein Seelenfunke</strong>. Alle Rechte vorbehalten.<br>
                <span class="text-xs">Handmade with ❤️ in Gifhorn.</span>
            </div>

            <div class="flex space-x-4">
                <a href="https://www.instagram.com/Mein_Seelenfunke/" target="_blank" class="text-gray-400 hover:text-primary transition">
                    <span class="sr-only">Instagram</span>
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                <a href="https://www.tiktok.com/@mein_seelenfunke" target="_blank" class="text-gray-400 hover:text-primary transition">
                    <span class="sr-only">TikTok</span>
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                </a>
            </div>
        </div>

    </div>
</footer>
